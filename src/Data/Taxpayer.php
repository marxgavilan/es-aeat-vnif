<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

use Iberfacil\AeatVnif\Exceptions\InvalidTaxpayer;

/**
 * Un contribuyente a comprobar: NIF y nombre. El NIF se normaliza al construir (mayusculas,
 * sin espacios, puntos ni guiones) y tiene que ser de nueve caracteres (NIF, NIE o CIF).
 */
final readonly class Taxpayer
{
    public string $nif;

    public function __construct(string $nif, public TaxpayerName $name)
    {
        $this->nif = self::normalizeNif($nif);
    }

    public static function naturalPerson(string $nif, string $firstName, string $lastName1, ?string $lastName2 = null): self
    {
        return new self($nif, TaxpayerName::naturalPerson($firstName, $lastName1, $lastName2));
    }

    public static function entity(string $nif, string $legalName): self
    {
        return new self($nif, TaxpayerName::entity($legalName));
    }

    public static function composed(string $nif, string $name): self
    {
        return new self($nif, TaxpayerName::composed($name));
    }

    /** @throws InvalidTaxpayer */
    public static function normalizeNif(string $nif): string
    {
        $nif = mb_strtoupper(preg_replace('/[\s.\-_\/]+/u', '', trim($nif)) ?? '', 'UTF-8');

        if ($nif === '') {
            throw InvalidTaxpayer::emptyNif();
        }

        if (preg_match('/^[A-Z0-9]{9}$/', $nif) !== 1) {
            throw InvalidTaxpayer::malformedNif();
        }

        return $nif;
    }
}
