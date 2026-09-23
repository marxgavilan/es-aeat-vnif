<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use RuntimeException;

/**
 * Fallo que no se arregla reintentando: peticion rechazada (HTTP 4xx), respuesta que no es
 * SOAP valido, SOAP Fault, resultado desconocido o NIFs que no cuadran con lo enviado.
 *
 * El texto del Fault y los cuerpos HTTP pueden repetir datos personales, asi que no van
 * en el mensaje.
 */
class DefinitiveFailure extends RuntimeException implements AeatVnifException
{
    public static function httpRejected(int $status): self
    {
        return new self(sprintf('El servicio VNifV2 rechazó la petición con HTTP %d.', $status));
    }

    public static function invalidXml(): self
    {
        return new self('La respuesta del servicio VNifV2 no es un XML SOAP válido.');
    }

    public static function soapFault(): self
    {
        return new self('El servicio VNifV2 devolvió un SOAP Fault (petición rechazada).');
    }

    public static function missingBody(): self
    {
        return new self('La respuesta del servicio VNifV2 no contiene VNifV2Sal.');
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('Falta el campo %s en un contribuyente de la respuesta VNifV2.', $field));
    }

    public static function unknownResult(): self
    {
        return new self('La AEAT devolvió un resultado que este paquete no reconoce.');
    }

    public static function batchMismatch(): self
    {
        return new self('La respuesta VNifV2 no coincide con los NIF enviados en el lote.');
    }
}
