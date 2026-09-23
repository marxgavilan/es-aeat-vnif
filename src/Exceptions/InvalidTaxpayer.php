<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use InvalidArgumentException;

/**
 * El NIF o el nombre no se pueden enviar (vacio, mal formado...). El mensaje nunca
 * incluye el valor.
 */
class InvalidTaxpayer extends InvalidArgumentException implements AeatVnifException
{
    public static function emptyNif(): self
    {
        return new self('El NIF no puede estar vacío.');
    }

    public static function malformedNif(): self
    {
        return new self('El NIF debe tener 9 caracteres alfanuméricos (NIF, NIE o CIF).');
    }

    public static function emptyName(): self
    {
        return new self('El nombre no puede quedar vacío tras normalizarlo.');
    }

    public static function batchTooLarge(int $size, int $max): self
    {
        return new self(sprintf('El lote de %d contribuyentes supera el máximo de %d que admite el servicio.', $size, $max));
    }
}
