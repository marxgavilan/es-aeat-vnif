<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

use Iberfacil\AeatVnif\Exceptions\TransientFailure;

/**
 * Resultado de comprobar un contribuyente.
 *
 * La respuesta se casa por NIF, nunca por nombre: si el nombre enviado es aproximado (sin
 * tildes, una letra de menos, falta un apellido) la AEAT responde igualmente IDENTIFICADO
 * y devuelve el nombre tal como esta en el censo (con tildes y espacios de relleno).
 * `nameWasCorrected()` dice si ha pasado eso y `censusName` guarda lo que devolvio.
 */
final readonly class CheckResult
{
    /**
     * @param string $nif NIF tal como se envio (normalizado).
     * @param IdentificationResult $result Resultado tipado.
     * @param string $aeatResult Texto literal de `Resultado` que devolvio la AEAT.
     * @param string $sentName El `Nombre` que se envio de verdad, ya normalizado.
     * @param string|null $censusName El `Nombre` que devolvio la AEAT (sin relleno), o null si vino vacio.
     * @param string|null $normalizedCensusName El censal pasado por el mismo normalizador, para comparar.
     * @param array<string, string> $raw `Nif`, `Nombre` y `Resultado` en crudo de esa fila.
     * @param TransientFailure|null $failure Solo cuando la fila es "NO PROCESADO" (reintentar).
     */
    public function __construct(
        public string $nif,
        public IdentificationResult $result,
        public string $aeatResult,
        public string $sentName,
        public ?string $censusName,
        public ?string $normalizedCensusName,
        public array $raw = [],
        public ?TransientFailure $failure = null,
        public bool $fromCache = false,
    ) {}

    public function isIdentified(): bool
    {
        return $this->result->isIdentified();
    }

    public function isTransient(): bool
    {
        return $this->result->isTransient();
    }

    /**
     * True si el nombre que tiene la AEAT no es el que enviamos (se comparan los dos
     * normalizados, asi que tildes y relleno solos no cuentan; letras distintas si).
     * Tambien true en NO IDENTIFICADO-SIMILAR, donde el censal viene justo para revisarlo.
     */
    public function nameWasCorrected(): bool
    {
        if ($this->censusName === null || $this->normalizedCensusName === null) {
            return false;
        }

        return $this->normalizedCensusName !== $this->sentName;
    }

    /**
     * True si el censal trae caracteres que nuestra normalizacion quita (tildes,
     * puntuacion): mismas letras, pero la grafia del censo es mas rica.
     */
    public function censusNameHasAccents(): bool
    {
        return $this->censusName !== null
            && $this->normalizedCensusName !== null
            && preg_replace('/\s+/u', ' ', trim($this->censusName)) !== $this->normalizedCensusName;
    }

    /** @return array<string, mixed> Listo para json_encode. */
    public function toArray(): array
    {
        return [
            'nif' => $this->nif,
            'result' => $this->result->value,
            'aeat_result' => $this->aeatResult,
            'identified' => $this->isIdentified(),
            'sent_name' => $this->sentName,
            'census_name' => $this->censusName,
            'name_was_corrected' => $this->nameWasCorrected(),
            'transient' => $this->isTransient(),
            'from_cache' => $this->fromCache,
        ];
    }

    public function withFromCache(bool $fromCache): self
    {
        return new self(
            $this->nif,
            $this->result,
            $this->aeatResult,
            $this->sentName,
            $this->censusName,
            $this->normalizedCensusName,
            $this->raw,
            $this->failure,
            $fromCache,
        );
    }
}
