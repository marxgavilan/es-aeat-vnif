<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

/**
 * Tipo de certificado, deducido del titular. Es orientativo: solo sirve para que el
 * doctor te diga con que estas trabajando. A la AEAT le vale cualquiera de los tres.
 */
enum CertificateKind: string
{
    case Personal = 'personal';
    case Representative = 'representative';
    case Seal = 'seal';
    case Unknown = 'unknown';

    /** @param array<string, mixed> $subject Campo subject de openssl_x509_parse(). */
    public static function fromSubject(array $subject): self
    {
        $cn = strtoupper(self::first($subject, 'CN'));
        $hasOrg = self::first($subject, 'O') !== '' || self::first($subject, 'organizationIdentifier') !== '';
        $hasPerson = self::first($subject, 'GN') !== '' || self::first($subject, 'givenName') !== ''
            || self::first($subject, 'SN') !== '' || self::first($subject, 'surname') !== '';
        $ou = strtoupper(self::first($subject, 'OU'));

        // Representante de persona juridica: la FNMT pone "(R: CIF)" en el CN
        if (str_contains($cn, '(R:') || ($hasOrg && $hasPerson)) {
            return self::Representative;
        }

        if ($hasOrg && (str_contains($cn, 'SELLO') || str_contains($ou, 'SELLO') || ! $hasPerson)) {
            return self::Seal;
        }

        if ($hasPerson || preg_match('/^[A-ZÁÉÍÓÚÑ ]+ - [0-9XYZ][0-9]{7}[A-Z]$/u', $cn) === 1) {
            return self::Personal;
        }

        return self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'persona fisica (autonomo o particular)',
            self::Representative => 'representante de persona juridica',
            self::Seal => 'sello de entidad',
            self::Unknown => 'no reconocido (seguramente sirve igual)',
        };
    }

    /** @param array<string, mixed> $subject */
    private static function first(array $subject, string $key): string
    {
        $value = $subject[$key] ?? '';
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
