<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel;

use Iberfacil\AeatVnif\Contracts\CheckObserver;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;
use Iberfacil\AeatVnif\Laravel\Events\VnifRequestCompleted;
use Iberfacil\AeatVnif\Laravel\Events\VnifRequestFailed;
use Iberfacil\AeatVnif\Laravel\Events\VnifRequestStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final class EventDispatchingObserver implements CheckObserver
{
    public function __construct(private readonly Dispatcher $events) {}

    public function beforeRequest(VnifRequest $request): void
    {
        $this->events->dispatch(new VnifRequestStarting($request));
    }

    public function afterRequest(VnifRequest $request, VnifResponse $response): void
    {
        $this->events->dispatch(new VnifRequestCompleted($request, $response));
    }

    public function onFailure(VnifRequest $request, Throwable $failure): void
    {
        $this->events->dispatch(new VnifRequestFailed($request, $failure));
    }
}
