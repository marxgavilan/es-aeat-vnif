<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;
use Throwable;

/**
 * Ganchos alrededor de cada peticion HTTP a la AEAT, para que cada proyecto audite sus
 * consultas a su manera (guardar hash y fecha, escribir una fila, emitir un evento...).
 *
 * Mejor que no lancen: una excepcion dentro de un hook sube tal cual a quien llama.
 */
interface CheckObserver
{
    public function beforeRequest(VnifRequest $request): void;

    public function afterRequest(VnifRequest $request, VnifResponse $response): void;

    public function onFailure(VnifRequest $request, Throwable $failure): void;
}
