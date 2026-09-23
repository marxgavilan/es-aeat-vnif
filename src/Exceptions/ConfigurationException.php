<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use InvalidArgumentException;

/**
 * Con esta configuracion (endpoint, lote, timeout, certificado...) el cliente no puede
 * arrancar. Hay que corregirla; reintentar no sirve.
 */
class ConfigurationException extends InvalidArgumentException implements AeatVnifException
{
    public static function invalidEndpoint(string $endpoint): self
    {
        return new self(sprintf('El endpoint "%s" no es válido: debe ser una URL https:// sin credenciales.', $endpoint));
    }

    public static function invalidBatchSize(int $size, int $max): self
    {
        return new self(sprintf('El tamaño de lote %d no es válido: debe estar entre 1 y %d.', $size, $max));
    }

    public static function invalidTimeout(int $seconds): self
    {
        return new self(sprintf('El timeout %d no es válido: debe ser un número de segundos mayor que cero.', $seconds));
    }

    public static function invalidRetries(int $retries): self
    {
        return new self(sprintf('El número de reintentos %d no es válido: debe ser cero o mayor.', $retries));
    }

    public static function missingExtension(string $extension): self
    {
        return new self(sprintf('Falta la extensión PHP "%s", necesaria para este paquete.', $extension));
    }
}
