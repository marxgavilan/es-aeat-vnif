<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Cli\Application;
use Iberfacil\AeatVnif\Cli\CsvParser;
use Iberfacil\AeatVnif\Data\TaxpayerKind;
use Iberfacil\AeatVnif\Tests\TestCase;
use Iberfacil\AeatVnif\Transport\FakeTransport;
use InvalidArgumentException;

final class CliTest extends TestCase
{
    private ?string $certificatePath = null;

    protected function setUp(): void
    {
        $this->certificatePath = self::writeTemp(self::pkcs12(), '.p12');
        putenv(Application::ENV_CERT_PASSWORD . '=' . self::PASSWORD);
    }

    protected function tearDown(): void
    {
        if ($this->certificatePath !== null) {
            unlink($this->certificatePath);
        }
        putenv(Application::ENV_CERT_PASSWORD);
        putenv(Application::ENV_CERT);
    }

    /** @return array{int, string, string} */
    private function runCli(FakeTransport $transport, string ...$argv): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertNotFalse($out);
        self::assertNotFalse($err);

        $code = (new Application($out, $err, $transport))->run(array_values($argv));
        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    public function testParsesCsvFormats(): void
    {
        $taxpayers = CsvParser::parse("NIF;APELLIDO1;APELLIDO2;NOMBRE\n00000000T;Lumina;Peralvillo;Zéfira\n\n# comentario\n00000001R;Cristalina;Nebula\nB00000000;Brumalia Ficticia SL\n");

        self::assertCount(3, $taxpayers);
        self::assertSame(TaxpayerKind::NaturalPerson, $taxpayers[0]->name->kind);
        self::assertSame('Lumina Peralvillo Zéfira', $taxpayers[0]->name->censusName());
        self::assertSame('Cristalina Nebula', $taxpayers[1]->name->censusName());
        self::assertSame(TaxpayerKind::Entity, $taxpayers[2]->name->kind);
        self::assertSame('B00000000', $taxpayers[2]->nif);
    }

    public function testCsvErrorsNameTheLineNotTheContent(): void
    {
        try {
            CsvParser::parse("00000000T;Lumina;Zefira\n00000001R\n");
            self::fail('Se esperaba InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Línea 2', $e->getMessage());
            self::assertStringNotContainsString('00000001R', $e->getMessage());
        }
    }

    public function testCheckPrintsReadableResult(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('identified-corrected-name'));
        [$code, $out, $err] = $this->runCli($transport, 'check', '00000000T', '--nombre=Zefira', '--apellido1=Lumina', '--cert=' . $this->certificatePath);

        self::assertSame(0, $code, $err);
        self::assertStringContainsString('00000000T  IDENTIFICADO', $out);
        self::assertStringContainsString('Censo:    LUMINÁ PERALVILLO ZÉFIRA   <- la AEAT ha corregido el nombre', $out);
    }

    public function testCheckJson(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('identified'));
        [$code, $out] = $this->runCli($transport, 'check', '00000000T', '--razon-social=Lumina Peralvillo Zefira', '--json', '--cert=' . $this->certificatePath);

        self::assertSame(0, $code);
        $json = json_decode($out, true);
        self::assertIsArray($json);
        self::assertSame('IDENTIFICADO', $json[0]['aeat_result']);
        self::assertFalse($json[0]['name_was_corrected']);
    }

    public function testBatchFromCsvFile(): void
    {
        $csv = self::writeTemp("00000000T;Lumina;Peralvillo;Zefira\n00000001R;Nadie;Conocido\nB00000000;Brumalia Ficticia SL\n00000003A;Otra;Persona\n", '.csv');
        $transport = (new FakeTransport())->queue(self::fixture('mixed'));
        [$code, $out] = $this->runCli($transport, 'batch', $csv, '--json', '--cert=' . $this->certificatePath);
        unlink($csv);

        self::assertSame(0, $code);
        $json = json_decode($out, true);
        self::assertIsArray($json);
        self::assertCount(4, $json);
        self::assertSame('NO PROCESADO', $json[3]['aeat_result']);
    }

    public function testPasswordAsArgumentIsRefused(): void
    {
        [$code, , $err] = $this->runCli(new FakeTransport(), 'check', '00000000T', '--razon-social=X', '--cert=' . $this->certificatePath, '--password=abc');

        self::assertSame(1, $code);
        self::assertStringContainsString('No pase la contraseña como argumento', $err);
    }

    public function testMissingCertificateIsClear(): void
    {
        [$code, , $err] = $this->runCli(new FakeTransport(), 'doctor', '--no-probe');

        self::assertSame(1, $code);
        self::assertStringContainsString('No se ha indicado ningún certificado', $err);
    }

    public function testDoctorWithFakeTransport(): void
    {
        [$code, $out] = $this->runCli(new FakeTransport(), 'doctor', '--cert=' . $this->certificatePath, '--json');

        self::assertSame(0, $code, $out);
        $json = json_decode($out, true);
        self::assertIsArray($json);
        self::assertTrue($json['ok']);
        self::assertSame('Conectividad', end($json['checks'])['name']);
    }

    public function testWrongPasswordViaEnvironment(): void
    {
        putenv(Application::ENV_CERT_PASSWORD . '=incorrecta');
        [$code, $out] = $this->runCli(new FakeTransport(), 'doctor', '--cert=' . $this->certificatePath, '--no-probe');

        self::assertSame(1, $code);
        self::assertStringContainsString('[FALLO] Certificado', $out);
        self::assertStringContainsString('La contraseña del certificado', $out);
        self::assertStringNotContainsString('incorrecta', $out);
    }

    public function testTransientFailureExitCode(): void
    {
        $transport = (new FakeTransport())->queue(\Iberfacil\AeatVnif\Exceptions\TransientFailure::timeout(1));
        [$code, , $err] = $this->runCli($transport, 'check', '00000000T', '--razon-social=X', '--cert=' . $this->certificatePath, '--retries=0');

        self::assertSame(2, $code);
        self::assertStringContainsString('Fallo transitorio', $err);
    }
}
