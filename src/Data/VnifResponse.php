<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

/**
 * Lo que salio de una peticion HTTP, ya parseado, para los observadores.
 */
final readonly class VnifResponse
{
    /**
     * @param list<CheckResult> $results En el orden en que los devolvio la AEAT.
     */
    public function __construct(
        public array $results,
        public float $durationMs,
    ) {}

    /** @return array<string, int> Recuento por etiqueta AEAT, p. ej. ['IDENTIFICADO' => 3]. */
    public function countsByResult(): array
    {
        $counts = [];

        foreach ($this->results as $result) {
            $label = $result->result->aeatLabel();
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
