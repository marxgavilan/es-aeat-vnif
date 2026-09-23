<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Cli;

use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Exceptions\InvalidTaxpayer;
use InvalidArgumentException;

/**
 * Lee el CSV de lotes, una linea por contribuyente, separador ";":
 *
 *   NIF;APELLIDO1;APELLIDO2;NOMBRE   persona fisica
 *   NIF;APELLIDO1;NOMBRE             persona fisica con un apellido
 *   NIF;RAZON_SOCIAL                 entidad
 *
 * Se admite una cabecera que empiece por "NIF" y lineas vacias o con "#".
 */
final class CsvParser
{
    /**
     * @return list<Taxpayer>
     *
     * @throws InvalidArgumentException con el numero de linea (nunca su contenido)
     */
    public static function parse(string $csv): array
    {
        $taxpayers = [];
        $lines = preg_split('/\R/u', $csv) ?: [];

        foreach ($lines as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $fields = array_map(trim(...), explode(';', $line));
            if ($number === 0 && strcasecmp($fields[0], 'NIF') === 0) {
                continue;
            }

            $nif = (string) array_shift($fields);
            $fields = array_values(array_filter($fields, static fn(string $field): bool => $field !== ''));

            $name = match (count($fields)) {
                1 => TaxpayerName::entity($fields[0]),
                2 => TaxpayerName::naturalPerson(firstName: $fields[1], lastName1: $fields[0]),
                3 => TaxpayerName::naturalPerson(firstName: $fields[2], lastName1: $fields[0], lastName2: $fields[1]),
                default => null,
            };

            if ($name === null || $name->isEmpty()) {
                throw new InvalidArgumentException(sprintf('Línea %d: formato no válido (se esperaba NIF;APELLIDO1;APELLIDO2;NOMBRE o NIF;RAZON_SOCIAL).', $number + 1));
            }

            try {
                $taxpayers[] = new Taxpayer($nif, $name);
            } catch (InvalidTaxpayer $e) {
                throw new InvalidArgumentException(sprintf('Línea %d: %s', $number + 1, $e->getMessage()), 0, $e);
            }
        }

        return $taxpayers;
    }
}
