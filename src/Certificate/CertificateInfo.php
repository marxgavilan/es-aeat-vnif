<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Datos publicos del certificado (titular, emisor, vigencia). Nada de claves.
 */
final readonly class CertificateInfo
{
    public function __construct(
        public string $subject,
        public string $issuer,
        public string $serialNumber,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
        public CertificateKind $kind = CertificateKind::Unknown,
    ) {}

    /** @param array<string, mixed> $parsed Salida de openssl_x509_parse(). */
    public static function fromParsed(array $parsed): self
    {
        $utc = new DateTimeZone('UTC');
        $subject = is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
        $issuer = is_array($parsed['issuer'] ?? null) ? $parsed['issuer'] : [];

        return new self(
            self::dn($subject),
            self::dn($issuer),
            (string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''),
            (new DateTimeImmutable('@' . (int) ($parsed['validFrom_time_t'] ?? 0)))->setTimezone($utc),
            (new DateTimeImmutable('@' . (int) ($parsed['validTo_time_t'] ?? 0)))->setTimezone($utc),
            CertificateKind::fromSubject($subject),
        );
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return ($now ?? new DateTimeImmutable()) > $this->validTo;
    }

    public function isNotYetValid(?DateTimeImmutable $now = null): bool
    {
        return ($now ?? new DateTimeImmutable()) < $this->validFrom;
    }

    public function daysUntilExpiry(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $diff = $now->diff($this->validTo);

        return (int) ($diff->invert === 1 ? -$diff->days : $diff->days);
    }

    /** @param array<string, mixed> $parts */
    private static function dn(array $parts): string
    {
        $out = [];
        foreach ($parts as $key => $value) {
            foreach ((array) $value as $item) {
                $out[] = $key . '=' . (is_scalar($item) ? (string) $item : '');
            }
        }

        return implode(', ', $out);
    }
}
