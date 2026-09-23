<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Data;

enum TaxpayerKind: string
{
    /** Persona fisica: nombre y hasta dos apellidos. */
    case NaturalPerson = 'natural_person';

    /** Entidad (persona juridica): razon social. */
    case Entity = 'entity';

    /** Solo se conoce el nombre ya compuesto; viaja tal cual. */
    case Composed = 'composed';
}
