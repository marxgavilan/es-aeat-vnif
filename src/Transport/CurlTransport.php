<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Transport;

use CurlHandle;
use Iberfacil\AeatVnif\Certificate\PemCredential;
use Iberfacil\AeatVnif\Contracts\ProbeableTransport;
use Iberfacil\AeatVnif\Data\ProbeResult;
use Iberfacil\AeatVnif\Exceptions\ConfigurationException;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;

/**
 * Transporte por defecto con ext-curl: mTLS, solo https, sin redirecciones, timeout de
 * conexion y total. Los cuerpos de error no salen de aqui.
 */
final class CurlTransport implements ProbeableTransport
{
    /**
     * @param string|null $caBundle Ruta a un bundle CA propio (por defecto el del sistema).
     * @param string|null $proxy Proxy en formato cURL ("http://host:puerto"), si hace falta.
     */
    public function __construct(
        private readonly ?string $caBundle = null,
        private readonly ?string $proxy = null,
    ) {
        if (! extension_loaded('curl')) {
            throw ConfigurationException::missingExtension('curl');
        }
    }

    public function send(string $endpoint, string $xml, PemCredential $credential, int $timeoutSeconds): string
    {
        $handle = $this->handle($endpoint, $credential, $timeoutSeconds);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Accept: text/xml',
                'Expect:',
            ],
        ]);

        try {
            $body = curl_exec($handle);
            $errno = curl_errno($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }

        if ($errno !== 0 || ! is_string($body)) {
            throw $this->curlFailure($errno, $timeoutSeconds);
        }

        if ($status === 408 || $status >= 500) {
            throw TransientFailure::serverUnavailable($status);
        }

        if ($status < 200 || $status >= 300) {
            throw DefinitiveFailure::httpRejected($status);
        }

        return $body;
    }

    public function probe(string $endpoint, PemCredential $credential, int $timeoutSeconds): ProbeResult
    {
        $handle = $this->handle($endpoint, $credential, $timeoutSeconds);
        curl_setopt_array($handle, [CURLOPT_NOBODY => true, CURLOPT_CUSTOMREQUEST => 'HEAD']);

        $start = hrtime(true);
        try {
            curl_exec($handle);
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
        $duration = (hrtime(true) - $start) / 1e6;

        if ($errno !== 0) {
            return new ProbeResult(false, null, sprintf('cURL error %d: %s', $errno, $error), $duration);
        }

        // Cualquier respuesta HTTP vale: el TLS con el certificado ya ha negociado.
        return new ProbeResult(true, $status, sprintf('El endpoint respondió HTTP %d a un HEAD (sin consultar ningún NIF).', $status), $duration);
    }

    private function handle(string $endpoint, PemCredential $credential, int $timeoutSeconds): CurlHandle
    {
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw ConfigurationException::invalidEndpoint($endpoint);
        }

        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw TransientFailure::connection('No se pudo inicializar cURL.');
        }

        /** @var array<int, mixed> $options */
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLCERT => $credential->certificatePath,
            CURLOPT_SSLCERTTYPE => 'PEM',
            CURLOPT_SSLKEY => $credential->privateKeyPath,
            CURLOPT_SSLKEYTYPE => 'PEM',
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'iberfacil/es-aeat-vnif',
        ];

        if ($credential->privateKeyPassphrase !== null) {
            $options[CURLOPT_SSLKEYPASSWD] = $credential->privateKeyPassphrase;
        }
        if ($this->caBundle !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        if ($this->proxy !== null) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
            $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($handle, $options);

        return $handle;
    }

    private function curlFailure(int $errno, int $timeoutSeconds): TransientFailure
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEOUTED => TransientFailure::timeout($timeoutSeconds),
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CACERT => TransientFailure::connection(sprintf('Fallo TLS (cURL %d). Compruebe el certificado y la cadena de confianza.', $errno)),
            default => TransientFailure::connection(sprintf('cURL error %d.', $errno)),
        };
    }
}
