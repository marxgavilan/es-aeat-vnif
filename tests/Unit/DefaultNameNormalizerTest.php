<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Name\DefaultNameNormalizer;
use Iberfacil\AeatVnif\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DefaultNameNormalizerTest extends TestCase
{
    #[DataProvider('names')]
    public function testNormalizes(string $input, string $expected): void
    {
        self::assertSame($expected, (new DefaultNameNormalizer())->normalize($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function names(): iterable
    {
        yield 'tildes' => ['Peralvillo Zéfira Luminá', 'PERALVILLO ZEFIRA LUMINA'];
        yield 'eñe' => ['Peña', 'PEÑA'];
        yield 'dieresis' => ['Güell', 'GUELL'];
        yield 'cedilla' => ['Çamora', 'CAMORA'];
        yield 'puntos' => ['Brumalia Ficticia, S.L.', 'BRUMALIA FICTICIA SL'];
        yield 'guion' => ['Lumina-Zéfira', 'LUMINA ZEFIRA'];
        yield 'espacios' => ["  Lumina \t  Peralvillo\n", 'LUMINA PERALVILLO'];
        yield 'ampersand' => ['Brumalia & Hijos', 'BRUMALIA HIJOS'];
        yield 'digitos' => ['Taller 3 SL', 'TALLER 3 SL'];
        yield 'nfd' => ["Ze\u{0301}fira", 'ZEFIRA'];
    }
}
