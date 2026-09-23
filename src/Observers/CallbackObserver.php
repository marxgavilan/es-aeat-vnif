<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Observers;

use Closure;
use Iberfacil\AeatVnif\Contracts\CheckObserver;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;
use Throwable;

/**
 * Observador a base de closures, para no tener que crear una clase por cada hook.
 *
 *   CallbackObserver::make()
 *       ->before(fn (VnifRequest $r) => ...)
 *       ->after(fn (VnifRequest $r, VnifResponse $s) => ...)
 *       ->failure(fn (VnifRequest $r, Throwable $e) => ...);
 */
final class CallbackObserver implements CheckObserver
{
    /** @var (Closure(VnifRequest): void)|null */
    private ?Closure $before = null;

    /** @var (Closure(VnifRequest, VnifResponse): void)|null */
    private ?Closure $after = null;

    /** @var (Closure(VnifRequest, Throwable): void)|null */
    private ?Closure $failure = null;

    public static function make(): self
    {
        return new self();
    }

    /** @param Closure(VnifRequest): void $callback */
    public function before(Closure $callback): self
    {
        $this->before = $callback;

        return $this;
    }

    /** @param Closure(VnifRequest, VnifResponse): void $callback */
    public function after(Closure $callback): self
    {
        $this->after = $callback;

        return $this;
    }

    /** @param Closure(VnifRequest, Throwable): void $callback */
    public function failure(Closure $callback): self
    {
        $this->failure = $callback;

        return $this;
    }

    public function beforeRequest(VnifRequest $request): void
    {
        if ($this->before !== null) {
            ($this->before)($request);
        }
    }

    public function afterRequest(VnifRequest $request, VnifResponse $response): void
    {
        if ($this->after !== null) {
            ($this->after)($request, $response);
        }
    }

    public function onFailure(VnifRequest $request, Throwable $failure): void
    {
        if ($this->failure !== null) {
            ($this->failure)($request, $failure);
        }
    }
}
