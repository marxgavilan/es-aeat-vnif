<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Events;

use Iberfacil\AeatVnif\Data\VnifRequest;
use Throwable;

final readonly class VnifRequestFailed
{
    public function __construct(public VnifRequest $request, public Throwable $failure) {}
}
