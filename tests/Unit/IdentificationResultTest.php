<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Data\IdentificationResult;
use Iberfacil\AeatVnif\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class IdentificationResultTest extends TestCase
{
    #[DataProvider('labels')]
    public function testMapsAeatLabels(string $label, IdentificationResult $expected): void
    {
        self::assertSame($expected, IdentificationResult::fromAeatLabel($label));
    }

    /** @return iterable<string, array{string, IdentificationResult}> */
    public static function labels(): iterable
    {
        yield 'identificado' => ['IDENTIFICADO', IdentificationResult::Identified];
        yield 'minusculas' => ['Identificado', IdentificationResult::Identified];
        yield 'similar' => ['NO IDENTIFICADO-SIMILAR', IdentificationResult::NotIdentifiedSimilar];
        yield 'similar con espacios' => ['No identificado - similar', IdentificationResult::NotIdentifiedSimilar];
        yield 'no identificado' => ['NO IDENTIFICADO', IdentificationResult::NotIdentified];
        yield 'no procesado' => [' NO PROCESADO ', IdentificationResult::NotProcessed];
        yield 'baja' => ['IDENTIFICADO-BAJA', IdentificationResult::IdentifiedDeregistered];
        yield 'revocado' => ['Identificado-Revocado', IdentificationResult::IdentifiedRevoked];
    }

    public function testRejectsUnknownLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IdentificationResult::fromAeatLabel('QUIZAS');
    }

    public function testIdentifiedVariants(): void
    {
        self::assertTrue(IdentificationResult::Identified->isIdentified());
        self::assertTrue(IdentificationResult::IdentifiedDeregistered->isIdentified());
        self::assertTrue(IdentificationResult::IdentifiedRevoked->isIdentified());
        self::assertFalse(IdentificationResult::NotIdentifiedSimilar->isIdentified());
        self::assertTrue(IdentificationResult::NotProcessed->isTransient());
        self::assertSame('NO IDENTIFICADO-SIMILAR', IdentificationResult::NotIdentifiedSimilar->aeatLabel());
    }
}
