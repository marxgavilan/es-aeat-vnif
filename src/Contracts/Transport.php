<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

use Iberfacil\AeatVnif\Certificate\PemCredential;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;

/**
 * Envia una peticion SOAP 1.1 por HTTPS con mTLS y devuelve el cuerpo de la respuesta.
 *
 * Implementalo para pasar por un proxy, usar tu propio cliente HTTP (PSR-18, Guzzle...)
 * o simular el servicio en tests. Contrato:
 *
 * - POST de `$xml` a `$endpoint` con `Content-Type: text/xml; charset=utf-8` y
 *   `SOAPAction: ""`, autenticando con los PEM de `$credential`.
 * - Nunca seguir redirecciones; rechazar lo que no sea https://.
 * - Lanzar {@see TransientFailure} en errores de conexion, timeout, HTTP 408 y 5xx.
 * - Lanzar {@see DefinitiveFailure} en cualquier otro estado que no sea 2xx.
 * - Devolver el cuerpo tal cual en 2xx (lo valida el parser).
 * - Nada de cuerpos, XML ni credenciales en mensajes de excepcion o logs.
 */
interface Transport
{
    /**
     * @throws TransientFailure
     * @throws DefinitiveFailure
     */
    public function send(string $endpoint, string $xml, PemCredential $credential, int $timeoutSeconds): string;
}
