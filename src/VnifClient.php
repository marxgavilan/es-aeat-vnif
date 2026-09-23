<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif;

use Closure;
use Iberfacil\AeatVnif\Certificate\CertificateLoader;
use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Contracts\CheckObserver;
use Iberfacil\AeatVnif\Contracts\NameNormalizer;
use Iberfacil\AeatVnif\Contracts\ResultCache;
use Iberfacil\AeatVnif\Contracts\Transport;
use Iberfacil\AeatVnif\Data\CheckResult;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\InvalidTaxpayer;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;
use Iberfacil\AeatVnif\Name\DefaultNameNormalizer;
use Iberfacil\AeatVnif\Soap\RequestBuilder;
use Iberfacil\AeatVnif\Soap\ResponseParser;
use Iberfacil\AeatVnif\Transport\CurlTransport;
use Throwable;

/**
 * Cliente del servicio VNifV2. Trocea en lotes, reintenta los fallos transitorios de red y
 * casa cada respuesta con su peticion por NIF (nunca por nombre: la AEAT devuelve el nombre
 * censal, no el que le mandamos).
 *
 * @phpstan-import-type Row from VnifRequest
 * @phpstan-type Pending array{rows: list<Row>, expected: array<string, array{name: string, indexes: list<int>}>}
 */
final class VnifClient
{
    private readonly VnifOptions $options;

    private readonly Transport $transport;

    private readonly NameNormalizer $normalizer;

    private readonly RequestBuilder $builder;

    private readonly ResponseParser $parser;

    /** @var list<CheckObserver> */
    private array $observers;

    /** @var Closure(int): void */
    private readonly Closure $sleep;

    /**
     * @param list<CheckObserver> $observers
     * @param (Closure(int): void)|null $sleep Espera en milisegundos entre reintentos; inyectable para tests.
     */
    public function __construct(
        private readonly Certificate $certificate,
        ?VnifOptions $options = null,
        ?Transport $transport = null,
        ?NameNormalizer $normalizer = null,
        private readonly ?ResultCache $cache = null,
        array $observers = [],
        ?Closure $sleep = null,
    ) {
        $this->options = $options ?? new VnifOptions();
        $this->transport = $transport ?? new CurlTransport();
        $this->normalizer = $normalizer ?? new DefaultNameNormalizer();
        $this->builder = new RequestBuilder();
        $this->parser = new ResponseParser();
        $this->observers = $observers;
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * Atajo: ruta del .p12/.pfx (o PEM) y su contraseña, con los valores por defecto.
     */
    public static function create(string $certificatePath, #[\SensitiveParameter] ?string $password, ?VnifOptions $options = null): self
    {
        return new self(CertificateLoader::fromFile($certificatePath, $password), $options);
    }

    public function withObserver(CheckObserver $observer): self
    {
        $clone = clone $this;
        $clone->observers[] = $observer;

        return $clone;
    }

    public function options(): VnifOptions
    {
        return $this->options;
    }

    public function certificate(): Certificate
    {
        return $this->certificate;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    public function normalizer(): NameNormalizer
    {
        return $this->normalizer;
    }

    /**
     * Comprueba un contribuyente. Lanza TransientFailure si la AEAT responde NO PROCESADO.
     *
     * @param TaxpayerName|string $name Nombre en partes o ya compuesto (apellidos y nombre).
     *
     * @throws TransientFailure
     * @throws DefinitiveFailure
     * @throws InvalidTaxpayer
     */
    public function check(string $nif, TaxpayerName|string $name): CheckResult
    {
        $taxpayer = new Taxpayer($nif, $name instanceof TaxpayerName ? $name : TaxpayerName::composed($name));
        $result = $this->checkBatch([$taxpayer])[0];

        if ($result->failure !== null) {
            throw $result->failure;
        }

        return $result;
    }

    /**
     * Comprueba varios contribuyentes y devuelve los resultados en el mismo orden. Un
     * NO PROCESADO individual no tira el lote: llega como resultado con `failure`.
     *
     * @param list<Taxpayer> $taxpayers
     * @return list<CheckResult>
     *
     * @throws TransientFailure
     * @throws DefinitiveFailure
     * @throws InvalidTaxpayer
     */
    public function checkBatch(array $taxpayers): array
    {
        if ($taxpayers === []) {
            return [];
        }

        $results = [];
        $pendingRows = [];

        foreach (array_values($taxpayers) as $index => $taxpayer) {
            $name = $this->normalizer->normalize($taxpayer->name->censusName());
            if ($name === '') {
                throw InvalidTaxpayer::emptyName();
            }

            $cached = $this->cache?->get(self::cacheKey($taxpayer->nif, $name));
            if ($cached !== null) {
                $results[$index] = $cached->withFromCache(true);

                continue;
            }

            $pendingRows[$index] = ['nif' => $taxpayer->nif, 'name' => $name];
        }

        foreach ($this->plan($pendingRows) as $pending) {
            foreach ($this->execute($pending) as $index => $result) {
                $results[$index] = $result;
            }
        }

        ksort($results);

        return array_values($results);
    }

    public static function cacheKey(string $nif, string $normalizedName): string
    {
        return hash('sha256', $nif . '|' . $normalizedName);
    }

    /**
     * Reparte las filas en peticiones de hasta batchSize. Un mismo NIF no puede repetirse
     * dentro de una peticion (la respuesta se casa por NIF), salvo que lleve el mismo
     * nombre: entonces comparte la respuesta.
     *
     * @param array<int, Row> $rows
     * @return list<Pending>
     */
    private function plan(array $rows): array
    {
        $requests = [];

        foreach ($rows as $index => $row) {
            $placed = false;

            foreach ($requests as &$request) {
                if (isset($request['expected'][$row['nif']])) {
                    if ($request['expected'][$row['nif']]['name'] === $row['name']) {
                        $request['expected'][$row['nif']]['indexes'][] = $index;
                        $placed = true;
                        break;
                    }

                    continue;
                }

                if (count($request['rows']) < $this->options->batchSize) {
                    $request['rows'][] = $row;
                    $request['expected'][$row['nif']] = ['name' => $row['name'], 'indexes' => [$index]];
                    $placed = true;
                    break;
                }
            }
            unset($request);

            if (! $placed) {
                $requests[] = [
                    'rows' => [$row],
                    'expected' => [$row['nif'] => ['name' => $row['name'], 'indexes' => [$index]]],
                ];
            }
        }

        return $requests;
    }

    /**
     * @param Pending $pending
     * @return array<int, CheckResult>
     */
    private function execute(array $pending): array
    {
        $xml = $this->builder->build($pending['rows']);
        $hash = hash('sha256', $xml);
        $credential = $this->certificate->open();

        try {
            $attempt = 0;
            while (true) {
                $attempt++;
                $request = new VnifRequest($this->options->endpoint, $pending['rows'], $hash, $attempt);

                foreach ($this->observers as $observer) {
                    $observer->beforeRequest($request);
                }

                $start = hrtime(true);
                try {
                    $body = $this->transport->send($this->options->endpoint, $xml, $credential, $this->options->timeoutSeconds);
                    $answers = $this->match($this->parser->parse($body), $pending['expected']);
                } catch (Throwable $failure) {
                    foreach ($this->observers as $observer) {
                        $observer->onFailure($request, $failure);
                    }

                    if ($failure instanceof TransientFailure && $attempt <= $this->options->retries) {
                        ($this->sleep)($this->options->retryDelayMs * (2 ** ($attempt - 1)));

                        continue;
                    }

                    throw $failure;
                }

                $response = new VnifResponse(array_values($answers), (hrtime(true) - $start) / 1e6);
                foreach ($this->observers as $observer) {
                    $observer->afterRequest($request, $response);
                }

                $results = [];
                foreach ($answers as $answer) {
                    if ($this->cache !== null && ! $answer->isTransient()) {
                        $this->cache->put(self::cacheKey($answer->nif, $answer->sentName), $answer);
                    }
                    foreach ($pending['expected'][$answer->nif]['indexes'] as $index) {
                        $results[$index] = $answer;
                    }
                }

                return $results;
            }
        } finally {
            $credential->release();
        }
    }

    /**
     * Casa las filas de la respuesta con lo enviado, por NIF. El nombre devuelto es el
     * censal (con tildes, completo, a veces con espacios de relleno): se guarda tal cual
     * y se compara normalizado para saber si la AEAT lo ha corregido.
     *
     * @param list<array{nif: string, name: ?string, result: Data\IdentificationResult, raw: array<string, string>}> $answers
     * @param array<string, array{name: string, indexes: list<int>}> $expected
     * @return array<string, CheckResult> Indexado por NIF.
     */
    private function match(array $answers, array $expected): array
    {
        if (count($answers) !== count($expected)) {
            throw DefinitiveFailure::batchMismatch();
        }

        $results = [];
        foreach ($answers as $answer) {
            if (! isset($expected[$answer['nif']]) || isset($results[$answer['nif']])) {
                throw DefinitiveFailure::batchMismatch();
            }

            $censusName = $answer['name'] === null ? null : (preg_replace('/\s+/u', ' ', trim($answer['name'])) ?? $answer['name']);

            $results[$answer['nif']] = new CheckResult(
                nif: $answer['nif'],
                result: $answer['result'],
                aeatResult: $answer['raw']['Resultado'],
                sentName: $expected[$answer['nif']]['name'],
                censusName: $censusName,
                normalizedCensusName: $censusName === null ? null : $this->normalizer->normalize($censusName),
                raw: $answer['raw'],
                failure: $answer['result']->isTransient() ? TransientFailure::notProcessed() : null,
            );
        }

        return $results;
    }
}
