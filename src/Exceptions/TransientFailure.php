<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use RuntimeException;

/**
 * Fallo que puede arreglarse reintentando: red, timeout, HTTP 408/5xx o un "NO PROCESADO"
 * de un contribuyente. Nunca lleva cuerpos de respuesta ni datos personales.
 */
class TransientFailure extends RuntimeException implements AeatVnifException
{
    public static function connection(string $reason = ''): self
    {
        return new self('No se pudo conectar con el servicio VNifV2 de la AEAT.' . ($reason !== '' ? ' ' . $reason : ''));
    }

    public static function timeout(int $seconds): self
    {
        return new self(sprintf('El servicio VNifV2 no respondió en %d segundos.', $seconds));
    }

    public static function serverUnavailable(int $status): self
    {
        return new self(sprintf('El servicio VNifV2 respondió con HTTP %d (no disponible temporalmente).', $status));
    }

    public static function notProcessed(): self
    {
        return new self('La AEAT respondió "NO PROCESADO" para este contribuyente; reintente más tarde.');
    }
}
