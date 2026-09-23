<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Certificate\CertificateKind;
use PHPUnit\Framework\TestCase;

final class CertificateKindTest extends TestCase
{
    public function test_detecta_persona_fisica(): void
    {
        $kind = CertificateKind::fromSubject(['CN' => 'PEREZ LOPEZ JUAN - 00000000T', 'GN' => 'JUAN', 'SN' => 'PEREZ LOPEZ']);

        self::assertSame(CertificateKind::Personal, $kind);
    }

    public function test_detecta_persona_fisica_solo_por_el_cn(): void
    {
        self::assertSame(CertificateKind::Personal, CertificateKind::fromSubject(['CN' => 'PEREZ LOPEZ JUAN - 00000000T']));
    }

    public function test_detecta_representante(): void
    {
        $kind = CertificateKind::fromSubject(['CN' => '00000000T JUAN PEREZ (R: B00000000)', 'O' => 'EMPRESA FICTICIA SL', 'GN' => 'JUAN']);

        self::assertSame(CertificateKind::Representative, $kind);
    }

    public function test_detecta_sello(): void
    {
        $kind = CertificateKind::fromSubject(['CN' => 'SELLO ELECTRONICO EMPRESA FICTICIA', 'O' => 'EMPRESA FICTICIA SL', 'organizationIdentifier' => 'VATES-B00000000']);

        self::assertSame(CertificateKind::Seal, $kind);
    }

    public function test_si_no_lo_reconoce_no_rompe(): void
    {
        self::assertSame(CertificateKind::Unknown, CertificateKind::fromSubject(['CN' => 'algo raro']));
    }
}
