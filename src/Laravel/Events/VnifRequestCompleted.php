<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Events;

use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;

final readonly class VnifRequestCompleted
{
    public function __construct(public VnifRequest $request, public VnifResponse $response) {}
}
