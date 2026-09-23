<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

final readonly class ProbeResult
{
    public function __construct(
        public bool $reachable,
        public ?int $httpStatus,
        public string $detail,
        public float $durationMs,
    ) {}
}
