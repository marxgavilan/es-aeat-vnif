<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

/**
 * Una peticion HTTP al servicio, vista por los observadores: que contribuyentes van, a que
 * endpoint, que intento es y el SHA-256 del XML como evidencia (el XML no se expone,
 * lleva datos personales).
 *
 * @phpstan-type Row array{nif: string, name: string}
 */
final readonly class VnifRequest
{
    /**
     * @param list<Row> $rows Pares NIF y nombre normalizado, en el orden enviado.
     */
    public function __construct(
        public string $endpoint,
        public array $rows,
        public string $xmlSha256,
        public int $attempt,
    ) {}

    public function size(): int
    {
        return count($this->rows);
    }
}
