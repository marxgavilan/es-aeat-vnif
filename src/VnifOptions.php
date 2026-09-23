<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif;

use Iberfacil\AeatVnif\Exceptions\ConfigurationException;

/**
 * Parametros del cliente con los valores de produccion por defecto.
 */
final readonly class VnifOptions
{
    public const DEFAULT_ENDPOINT = 'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP';

    /** Maximo de contribuyentes por peticion que admite el servicio segun su manual. */
    public const MAX_BATCH_SIZE = 10000;

    public const DEFAULT_TIMEOUT = 30;

    public const DEFAULT_RETRIES = 2;

    public const DEFAULT_RETRY_DELAY_MS = 500;

    /**
     * @param int $retries Reintentos ante fallo transitorio de la peticion (red, timeout, 5xx). 0 desactiva.
     * @param int $retryDelayMs Espera entre reintentos; se duplica en cada intento.
     */
    public function __construct(
        public string $endpoint = self::DEFAULT_ENDPOINT,
        public int $batchSize = self::MAX_BATCH_SIZE,
        public int $timeoutSeconds = self::DEFAULT_TIMEOUT,
        public int $retries = self::DEFAULT_RETRIES,
        public int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS,
    ) {
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = parse_url($endpoint, PHP_URL_HOST);
        $user = parse_url($endpoint, PHP_URL_USER);
        if ($scheme !== 'https' || ! is_string($host) || $host === '' || $user !== null) {
            throw ConfigurationException::invalidEndpoint($endpoint);
        }
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) {
            throw ConfigurationException::invalidBatchSize($batchSize, self::MAX_BATCH_SIZE);
        }
        if ($timeoutSeconds < 1) {
            throw ConfigurationException::invalidTimeout($timeoutSeconds);
        }
        if ($retries < 0 || $retryDelayMs < 0) {
            throw ConfigurationException::invalidRetries($retries);
        }
    }

    /**
     * @param array{endpoint?: mixed, batch_size?: mixed, timeout?: mixed, retries?: mixed, retry_delay_ms?: mixed} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            endpoint: self::string($config['endpoint'] ?? null, self::DEFAULT_ENDPOINT),
            batchSize: self::int($config['batch_size'] ?? null, self::MAX_BATCH_SIZE),
            timeoutSeconds: self::int($config['timeout'] ?? null, self::DEFAULT_TIMEOUT),
            retries: self::int($config['retries'] ?? null, self::DEFAULT_RETRIES),
            retryDelayMs: self::int($config['retry_delay_ms'] ?? null, self::DEFAULT_RETRY_DELAY_MS),
        );
    }

    private static function string(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private static function int(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
