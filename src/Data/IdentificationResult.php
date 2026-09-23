<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

use InvalidArgumentException;

/**
 * Lo que puede venir en `Contribuyente/Resultado` para cada contribuyente.
 */
enum IdentificationResult: string
{
    /** NIF y nombre coinciden con el censo (aunque el nombre enviado fuera aproximado). */
    case Identified = 'identified';

    /** El NIF existe pero el nombre solo se parece: revisar el nombre censal devuelto. */
    case NotIdentifiedSimilar = 'not_identified_similar';

    /** El NIF no existe o el nombre no se parece. */
    case NotIdentified = 'not_identified';

    /** La AEAT no pudo procesar este contribuyente en esta petición; reintentar. */
    case NotProcessed = 'not_processed';

    /** Identificado, pero el NIF está dado de baja en el censo. */
    case IdentifiedDeregistered = 'identified_deregistered';

    /** Identificado, pero el NIF está revocado. */
    case IdentifiedRevoked = 'identified_revoked';

    /**
     * Del texto literal de `Resultado` ("IDENTIFICADO", "NO IDENTIFICADO-SIMILAR"...) al
     * enum. Da igual mayusculas, espacios alrededor o espacios junto al guion.
     */
    public static function fromAeatLabel(string $label): self
    {
        $normalized = mb_strtoupper(trim($label), 'UTF-8');
        $normalized = preg_replace('/\s*-\s*/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return match ($normalized) {
            'IDENTIFICADO' => self::Identified,
            'NO IDENTIFICADO-SIMILAR' => self::NotIdentifiedSimilar,
            'NO IDENTIFICADO' => self::NotIdentified,
            'NO PROCESADO' => self::NotProcessed,
            'IDENTIFICADO-BAJA' => self::IdentifiedDeregistered,
            'IDENTIFICADO-REVOCADO' => self::IdentifiedRevoked,
            default => throw new InvalidArgumentException('Unknown AEAT identification result label.'),
        };
    }

    /** La etiqueta tal como la escribe la AEAT. */
    public function aeatLabel(): string
    {
        return match ($this) {
            self::Identified => 'IDENTIFICADO',
            self::NotIdentifiedSimilar => 'NO IDENTIFICADO-SIMILAR',
            self::NotIdentified => 'NO IDENTIFICADO',
            self::NotProcessed => 'NO PROCESADO',
            self::IdentifiedDeregistered => 'IDENTIFICADO-BAJA',
            self::IdentifiedRevoked => 'IDENTIFICADO-REVOCADO',
        };
    }

    /** Explicacion para pantallas y consola. */
    public function description(): string
    {
        return match ($this) {
            self::Identified => 'El NIF y el nombre coinciden con el censo de la AEAT.',
            self::NotIdentifiedSimilar => 'El NIF existe pero el nombre solo se parece al censal; revise el nombre devuelto.',
            self::NotIdentified => 'El NIF no consta en el censo o el nombre no se corresponde.',
            self::NotProcessed => 'La AEAT no pudo procesar la consulta; vuelva a intentarlo.',
            self::IdentifiedDeregistered => 'Identificado, pero el NIF figura dado de baja en el censo.',
            self::IdentifiedRevoked => 'Identificado, pero el NIF figura revocado.',
        };
    }

    /** IDENTIFICADO y sus variantes -BAJA y -REVOCADO. */
    public function isIdentified(): bool
    {
        return in_array($this, [self::Identified, self::IdentifiedDeregistered, self::IdentifiedRevoked], true);
    }

    public function isTransient(): bool
    {
        return $this === self::NotProcessed;
    }
}
