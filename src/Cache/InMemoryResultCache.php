<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cache;

use Iberfacil\AeatVnif\Contracts\ResultCache;
use Iberfacil\AeatVnif\Data\CheckResult;

/**
 * Cache de proceso: evita repetir consultas identicas dentro de una misma ejecucion.
 */
final class InMemoryResultCache implements ResultCache
{
    /** @var array<string, CheckResult> */
    private array $items = [];

    public function get(string $key): ?CheckResult
    {
        return $this->items[$key] ?? null;
    }

    public function put(string $key, CheckResult $result): void
    {
        $this->items[$key] = $result;
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
