<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cache;

use Iberfacil\AeatVnif\Contracts\ResultCache;
use Iberfacil\AeatVnif\Data\CheckResult;
use Psr\SimpleCache\CacheInterface;

/**
 * Adaptador sobre cualquier cache PSR-16 (requiere psr/simple-cache en el proyecto).
 */
final class Psr16ResultCache implements ResultCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 86400,
        private readonly string $prefix = 'aeat_vnif_',
    ) {}

    public function get(string $key): ?CheckResult
    {
        $value = $this->cache->get($this->prefix . $key);

        return $value instanceof CheckResult ? $value : null;
    }

    public function put(string $key, CheckResult $result): void
    {
        $this->cache->set($this->prefix . $key, $result, $this->ttlSeconds);
    }
}
