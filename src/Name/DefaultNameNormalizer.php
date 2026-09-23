<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Name;

use Iberfacil\AeatVnif\Contracts\NameNormalizer;
use Normalizer;

/**
 * Normalizacion por defecto del `Nombre` que viaja a VNifV2:
 *
 * - NFC (si hay ext-intl) y mayusculas.
 * - Vocales con tilde, dieresis o circunflejo -> vocal ASCII. Se conserva la Ñ; Ç -> C.
 * - Los guiones separan palabras; el resto de puntuacion y simbolos se elimina.
 * - Quedan A-Z, Ñ, digitos y espacios; los espacios se compactan.
 *
 * "ZÉFIRA" -> "ZEFIRA", "Peña" -> "PEÑA", "S.L." -> "SL", "Lumina-Zéfira" -> "LUMINA ZEFIRA".
 * El nombre censal que devuelve la AEAT no pasa por aqui: se guarda tal cual llega.
 */
final class DefaultNameNormalizer implements NameNormalizer
{
    private const FOLD = [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O', 'Ø' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C',
        '-' => ' ', '–' => ' ', '—' => ' ',
        'ª' => 'A', 'º' => 'O',
    ];

    public function normalize(string $name): string
    {
        if (class_exists(Normalizer::class)) {
            $name = Normalizer::normalize($name, Normalizer::FORM_C) ?: $name;
        }

        $name = mb_strtoupper($name, 'UTF-8');
        $name = strtr($name, self::FOLD);
        $name = preg_replace('/[^A-ZÑ0-9\s]/u', '', $name) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }
}
