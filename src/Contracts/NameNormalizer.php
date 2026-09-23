<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

/**
 * Convierte el nombre tecleado en el `Nombre` que se envia. Sustituyelo si necesitas
 * otras reglas (el de serie quita tildes, conserva la Ñ y pasa a mayusculas).
 */
interface NameNormalizer
{
    public function normalize(string $name): string;
}
