<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerKind;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Exceptions\InvalidTaxpayer;
use Iberfacil\AeatVnif\Tests\TestCase;

final class TaxpayerNameTest extends TestCase
{
    public function testNaturalPersonUsesCensusOrder(): void
    {
        $name = TaxpayerName::naturalPerson('Zéfira', 'Peralvillo', 'Lumina');

        self::assertSame(TaxpayerKind::NaturalPerson, $name->kind);
        self::assertSame('Peralvillo Lumina Zéfira', $name->censusName());
        self::assertSame('Zéfira Peralvillo Lumina', $name->displayName());
    }

    public function testSingleSurname(): void
    {
        self::assertSame('Peralvillo Zéfira', TaxpayerName::naturalPerson('Zéfira', 'Peralvillo')->censusName());
        self::assertSame('Peralvillo Zéfira', TaxpayerName::naturalPerson('Zéfira', 'Peralvillo', '  ')->censusName());
    }

    public function testEntityAndComposedTravelVerbatim(): void
    {
        self::assertSame('Brumalia Ficticia, S.L.', TaxpayerName::entity(' Brumalia Ficticia, S.L. ')->censusName());
        self::assertSame('PERALVILLO LUMINA ZEFIRA', TaxpayerName::composed('PERALVILLO LUMINA ZEFIRA')->censusName());
        self::assertTrue(TaxpayerName::entity('   ')->isEmpty());
    }

    public function testNifIsNormalized(): void
    {
        self::assertSame('00000000T', (new Taxpayer(' 0000-0000 t ', TaxpayerName::entity('X')))->nif);
        self::assertSame('B00000000', Taxpayer::entity('b.00000000', 'X')->nif);
    }

    public function testRejectsEmptyNif(): void
    {
        $this->expectException(InvalidTaxpayer::class);
        new Taxpayer('   ', TaxpayerName::entity('X'));
    }

    public function testRejectsMalformedNif(): void
    {
        $this->expectException(InvalidTaxpayer::class);
        new Taxpayer('1234', TaxpayerName::entity('X'));
    }
}
