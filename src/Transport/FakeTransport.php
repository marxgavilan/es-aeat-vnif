<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Transport;

use Iberfacil\AeatVnif\Certificate\PemCredential;
use Iberfacil\AeatVnif\Contracts\ProbeableTransport;
use Iberfacil\AeatVnif\Data\ProbeResult;
use Iberfacil\AeatVnif\Exceptions\AeatVnifException;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Soap\ResponseParser;
use Throwable;

/**
 * Transporte sin red para tests: encola respuestas (cuerpos XML o excepciones) y guarda
 * lo que se le envio. Tambien puede fabricar respuestas a partir de un array de filas.
 */
final class FakeTransport implements ProbeableTransport
{
    /** @var list<string|Throwable> */
    private array $queue = [];

    /** @var list<array{endpoint: string, xml: string, timeout: int, certificatePath: string, keyExisted: bool}> */
    public array $sent = [];

    public function queue(string|Throwable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * @param list<array{nif: string, name?: string, result: string}> $rows
     */
    public function queueRows(array $rows): self
    {
        return $this->queue(self::xmlFor($rows));
    }

    /**
     * @param list<array{nif: string, name?: string, result: string}> $rows
     */
    public static function xmlFor(array $rows): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $envelope = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soap:Envelope');
        $document->appendChild($envelope);
        $body = $envelope->appendChild($document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soap:Body'));
        $out = $body->appendChild($document->createElementNS(ResponseParser::NAMESPACE, 'VNifV2Sal'));

        foreach ($rows as $row) {
            $taxpayer = $out->appendChild($document->createElementNS(ResponseParser::NAMESPACE, 'Contribuyente'));
            foreach (['Nif' => $row['nif'], 'Nombre' => $row['name'] ?? '', 'Resultado' => $row['result']] as $tag => $value) {
                $element = $taxpayer->appendChild($document->createElementNS(ResponseParser::NAMESPACE, $tag));
                $element->appendChild($document->createTextNode($value));
            }
        }

        return (string) $document->saveXML();
    }

    public function send(string $endpoint, string $xml, PemCredential $credential, int $timeoutSeconds): string
    {
        $this->sent[] = [
            'endpoint' => $endpoint,
            'xml' => $xml,
            'timeout' => $timeoutSeconds,
            'certificatePath' => $credential->certificatePath,
            'keyExisted' => is_file($credential->privateKeyPath),
        ];

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new DefinitiveFailure('FakeTransport: no hay respuestas encoladas.');
        }
        if ($next instanceof Throwable) {
            if ($next instanceof AeatVnifException) {
                throw $next;
            }
            throw new DefinitiveFailure('FakeTransport: ' . $next->getMessage(), 0, $next);
        }

        return $next;
    }

    public function probe(string $endpoint, PemCredential $credential, int $timeoutSeconds): ProbeResult
    {
        return new ProbeResult(true, 200, 'FakeTransport', 0.0);
    }
}
