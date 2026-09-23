<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

use Iberfacil\AeatVnif\Data\CheckResult;

/**
 * Cache opcional de resultados por NIF + nombre normalizado. El cliente nunca guarda un
 * "NO PROCESADO". Implementala sobre tu almacen o usa {@see \Iberfacil\AeatVnif\Cache\Psr16ResultCache}.
 */
interface ResultCache
{
    public function get(string $key): ?CheckResult;

    public function put(string $key, CheckResult $result): void;
}
