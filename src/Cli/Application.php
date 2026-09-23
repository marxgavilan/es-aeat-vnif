<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cli;

use Iberfacil\AeatVnif\Certificate\CertificateLoader;
use Iberfacil\AeatVnif\Contracts\Transport;
use Iberfacil\AeatVnif\Data\CheckResult;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Doctor;
use Iberfacil\AeatVnif\Exceptions\AeatVnifException;
use Iberfacil\AeatVnif\Exceptions\CertificateException;
use Iberfacil\AeatVnif\Exceptions\ConfigurationException;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;
use Iberfacil\AeatVnif\VnifClient;
use Iberfacil\AeatVnif\VnifOptions;
use InvalidArgumentException;
use Throwable;

/**
 * Ejecutable `aeat-vnif`. Codigos de salida:
 *   0 la consulta se hizo (sea cual sea el resultado de la AEAT) / doctor sin fallos
 *   1 error de uso o de configuracion (certificado, argumentos...)
 *   2 fallo transitorio (red, timeout, NO PROCESADO)
 *   3 fallo definitivo (respuesta rechazada o no reconocida)
 */
final class Application
{
    public const VERSION = '1.0.0';

    public const ENV_CERT = 'AEAT_VNIF_CERT';

    public const ENV_CERT_PASSWORD = 'AEAT_VNIF_CERT_PASSWORD';

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    /**
     * @param resource|null $out
     * @param resource|null $err
     * @param Transport|null $transport Para tests; en uso normal, cURL.
     */
    public function __construct($out = null, $err = null, private readonly ?Transport $transport = null)
    {
        $this->out = $out ?? STDOUT;
        $this->err = $err ?? STDERR;
    }

    /** @param list<string> $argv Sin el nombre del script. */
    public function run(array $argv): int
    {
        $arguments = new Arguments($argv);
        $command = $arguments->argument(0) ?? 'help';

        try {
            return match ($command) {
                'check' => $this->check($arguments),
                'batch' => $this->batch($arguments),
                'doctor' => $this->doctor($arguments),
                'help', '--help', '-h' => $this->help(),
                'version', '--version' => $this->line(self::VERSION),
                default => $this->fail('Comando desconocido: ' . $command . '. Use "aeat-vnif help".', 1),
            };
        } catch (CertificateException|ConfigurationException|InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 1);
        } catch (TransientFailure $e) {
            return $this->fail('Fallo transitorio: ' . $e->getMessage(), 2);
        } catch (DefinitiveFailure $e) {
            return $this->fail('Fallo definitivo: ' . $e->getMessage(), 3);
        } catch (AeatVnifException $e) {
            return $this->fail($e->getMessage(), 3);
        } catch (Throwable $e) {
            // No volcamos la traza: podria arrastrar rutas o datos de la consulta.
            return $this->fail('Error inesperado: ' . $e::class . ': ' . $e->getMessage(), 3);
        }
    }

    private function check(Arguments $arguments): int
    {
        $nif = $arguments->argument(1);
        if ($nif === null) {
            return $this->fail('Falta el NIF. Uso: aeat-vnif check <NIF> --nombre=... --apellido1=... [--apellido2=...] | --razon-social=...', 1);
        }

        $legalName = $arguments->value('razon-social');
        if ($legalName !== null) {
            $name = TaxpayerName::entity($legalName);
        } else {
            $firstName = $arguments->value('nombre');
            $lastName1 = $arguments->value('apellido1');
            if ($firstName === null || $lastName1 === null) {
                return $this->fail('Indique --nombre y --apellido1 (y --apellido2 si lo tiene), o --razon-social para una entidad.', 1);
            }
            $name = TaxpayerName::naturalPerson($firstName, $lastName1, $arguments->value('apellido2'));
        }

        $client = $this->client($arguments);
        $result = $client->checkBatch([new Taxpayer($nif, $name)])[0];

        $this->printResults([$result], $arguments->flag('json'));

        return 0;
    }

    private function batch(Arguments $arguments): int
    {
        $path = $arguments->argument(1);
        if ($path === null) {
            return $this->fail('Falta el fichero CSV. Uso: aeat-vnif batch fichero.csv', 1);
        }
        if (! is_file($path) || ! is_readable($path)) {
            return $this->fail('No se puede leer el fichero ' . $path, 1);
        }

        $taxpayers = CsvParser::parse((string) file_get_contents($path));
        if ($taxpayers === []) {
            return $this->fail('El fichero no contiene ninguna línea válida.', 1);
        }

        $client = $this->client($arguments);
        $results = $client->checkBatch($taxpayers);

        $this->printResults($results, $arguments->flag('json'));

        return 0;
    }

    private function doctor(Arguments $arguments): int
    {
        $doctor = new Doctor($this->options($arguments), $this->transport);
        [$certificatePath, $password, $keyPath] = $this->certificateArguments($arguments, promptAllowed: true);

        $checks = $doctor->run($certificatePath, $password, $keyPath, probe: ! $arguments->flag('no-probe'));

        if ($arguments->flag('json')) {
            $this->line((string) json_encode(['ok' => Doctor::allOk($checks), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('aeat-vnif doctor ' . self::VERSION);
            foreach ($checks as $check) {
                $mark = $check['ok'] ? (($check['warning'] ?? false) ? 'AVISO' : 'OK   ') : 'FALLO';
                $this->line(sprintf('  [%s] %-28s %s', $mark, $check['name'], $check['detail']));
            }
            $this->line(Doctor::allOk($checks) ? 'Todo listo. Ya puede consultar: aeat-vnif check <NIF> --nombre=... --apellido1=...' : 'Hay fallos que corregir antes de consultar.');
        }

        return Doctor::allOk($checks) ? 0 : 1;
    }

    private function help(): int
    {
        $this->line(<<<'TXT'
        aeat-vnif — comprobación de NIF y nombre en el censo de la AEAT (VNifV2)

        Uso:
          aeat-vnif doctor  [--cert=ruta.p12] [--endpoint=URL] [--no-probe] [--json]
          aeat-vnif check <NIF> --nombre=... --apellido1=... [--apellido2=...] [--cert=ruta.p12] [--json]
          aeat-vnif check <NIF> --razon-social=... [--cert=ruta.p12] [--json]
          aeat-vnif batch fichero.csv [--cert=ruta.p12] [--json]

        Certificado:
          --cert=ruta        Fichero .p12/.pfx (o .pem). También por la variable AEAT_VNIF_CERT.
          --key=ruta         Solo para PEM con la clave en otro fichero.
          Contraseña         Por la variable de entorno AEAT_VNIF_CERT_PASSWORD o, si hay
                             terminal, se pide sin eco. Nunca como argumento.

        Opciones:
          --endpoint=URL     Endpoint del servicio (por defecto el de producción).
          --timeout=N        Segundos de espera (por defecto 30).
          --retries=N        Reintentos ante fallo transitorio (por defecto 2).
          --json             Salida en JSON.

        Formato del CSV (separador ";"):
          NIF;APELLIDO1;APELLIDO2;NOMBRE     persona física
          NIF;APELLIDO1;NOMBRE               persona física con un apellido
          NIF;RAZON_SOCIAL                   entidad

        Códigos de salida: 0 consulta realizada · 1 uso/configuración · 2 fallo transitorio · 3 fallo definitivo
        TXT);

        return 0;
    }

    private function client(Arguments $arguments): VnifClient
    {
        [$certificatePath, $password, $keyPath] = $this->certificateArguments($arguments, promptAllowed: true);
        $certificate = CertificateLoader::fromFile($certificatePath, $password, $keyPath);

        return new VnifClient($certificate, $this->options($arguments), $this->transport);
    }

    private function options(Arguments $arguments): VnifOptions
    {
        return VnifOptions::fromArray([
            'endpoint' => $arguments->value('endpoint') ?? getenv('AEAT_VNIF_ENDPOINT'),
            'timeout' => $arguments->value('timeout') ?? getenv('AEAT_VNIF_TIMEOUT'),
            'retries' => $arguments->value('retries') ?? getenv('AEAT_VNIF_RETRIES'),
            'batch_size' => $arguments->value('batch-size') ?? getenv('AEAT_VNIF_BATCH_SIZE'),
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string} ruta, contraseña, ruta de clave PEM
     */
    private function certificateArguments(Arguments $arguments, bool $promptAllowed): array
    {
        if ($arguments->value('password') !== null || $arguments->value('cert-password') !== null) {
            throw new InvalidArgumentException('No pase la contraseña como argumento (queda en el historial del shell). Use la variable AEAT_VNIF_CERT_PASSWORD o deje que se pida por teclado.');
        }

        $path = $arguments->value('cert') ?? (getenv(self::ENV_CERT) ?: null);
        if ($path === null) {
            throw CertificateException::missing();
        }

        $password = getenv(self::ENV_CERT_PASSWORD);
        $password = $password === false ? null : $password;

        if ($password === null && $promptAllowed) {
            $password = PasswordPrompt::ask();
        }

        return [$path, $password, $arguments->value('key')];
    }

    /** @param list<CheckResult> $results */
    private function printResults(array $results, bool $json): void
    {
        if ($json) {
            $this->line((string) json_encode(array_map(static fn(CheckResult $r): array => $r->toArray(), $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($results as $result) {
            $this->line(sprintf('%s  %s', $result->nif, $result->aeatResult));
            $this->line('    ' . $result->result->description());
            $this->line('    Enviado:  ' . $result->sentName);
            if ($result->censusName !== null) {
                $this->line('    Censo:    ' . $result->censusName . ($result->nameWasCorrected() ? '   <- la AEAT ha corregido el nombre' : ''));
            }
            if ($result->fromCache) {
                $this->line('    (resultado de caché)');
            }
        }
    }

    private function line(string $text): int
    {
        fwrite($this->out, $text . PHP_EOL);

        return 0;
    }

    private function fail(string $message, int $code): int
    {
        fwrite($this->err, 'Error: ' . $message . PHP_EOL);

        return $code;
    }
}
