<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Events;

use Iberfacil\AeatVnif\Data\VnifRequest;

final readonly class VnifRequestStarting
{
    public function __construct(public VnifRequest $request) {}
}
