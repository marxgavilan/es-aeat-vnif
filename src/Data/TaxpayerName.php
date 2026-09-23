<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

/**
 * Nombre de un contribuyente por partes, mas el `Nombre` unico que espera VNifV2.
 *
 * Por partes y no en una cadena: "BRUMOSO DE LA NIEBLA ANA ZEFIRA" ya no se puede
 * volver a separar (nadie distingue un apellido compuesto de un nombre compuesto). El
 * formulario de la AEAT pide los tres campos por separado; aqui igual.
 *
 * Orden censal para persona fisica: "APELLIDO1 APELLIDO2 NOMBRE" (primero apellidos),
 * que es lo que documenta el esquema para `Contribuyente/Nombre`. Una entidad manda su
 * razon social tal cual. Mayusculas y tildes no se tocan aqui: eso lo hace el
 * NameNormalizer una sola vez, al enviar.
 */
final readonly class TaxpayerName
{
    private function __construct(
        public TaxpayerKind $kind,
        public ?string $firstName,
        public ?string $lastName1,
        public ?string $lastName2,
        public ?string $legalName,
        public ?string $composedName,
    ) {}

    public static function naturalPerson(string $firstName, string $lastName1, ?string $lastName2 = null): self
    {
        return new self(
            TaxpayerKind::NaturalPerson,
            self::clean($firstName),
            self::clean($lastName1),
            self::clean($lastName2),
            null,
            null,
        );
    }

    public static function entity(string $legalName): self
    {
        return new self(TaxpayerKind::Entity, null, null, null, self::clean($legalName), null);
    }

    /**
     * Nombre del que no se conocen las partes. Viaja tal cual, asi que el orden censal
     * (apellidos primero si es persona fisica) corre a cargo de quien llama.
     */
    public static function composed(string $name): self
    {
        return new self(TaxpayerKind::Composed, null, null, null, null, self::clean($name));
    }

    /** El `Nombre` en orden censal, antes de normalizar. */
    public function censusName(): string
    {
        return match ($this->kind) {
            TaxpayerKind::NaturalPerson => self::join([$this->lastName1, $this->lastName2, $this->firstName]),
            TaxpayerKind::Entity => (string) $this->legalName,
            TaxpayerKind::Composed => (string) $this->composedName,
        };
    }

    /** Como se dirige uno a la persona: "NOMBRE APELLIDO1 APELLIDO2". */
    public function displayName(): string
    {
        return match ($this->kind) {
            TaxpayerKind::NaturalPerson => self::join([$this->firstName, $this->lastName1, $this->lastName2]),
            default => $this->censusName(),
        };
    }

    public function isEmpty(): bool
    {
        return $this->censusName() === '';
    }

    /** @param list<?string> $parts */
    private static function join(array $parts): string
    {
        return implode(' ', array_filter(
            array_map(static fn(?string $part): string => trim((string) $part), $parts),
            static fn(string $part): bool => $part !== '',
        ));
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
