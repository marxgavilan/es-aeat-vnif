<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Certificate\CertificateLoader;
use Iberfacil\AeatVnif\Certificate\PemCertificate;
use Iberfacil\AeatVnif\Certificate\Pkcs12Certificate;
use Iberfacil\AeatVnif\Exceptions\CertificateException;
use Iberfacil\AeatVnif\Tests\TestCase;

final class CertificateTest extends TestCase
{
    public function testReadsPkcs12AndExposesInfo(): void
    {
        $certificate = Pkcs12Certificate::fromString(self::pkcs12(), self::PASSWORD, 'prueba.p12');
        $info = $certificate->info();

        self::assertStringContainsString('CN=CERTIFICADO DE PRUEBA', $info->subject);
        self::assertFalse($info->isExpired());
        self::assertGreaterThan(300, $info->daysUntilExpiry());
    }

    public function testWrongPasswordGivesClearError(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('contraseña');

        Pkcs12Certificate::fromString(self::pkcs12(), 'otra', 'prueba.p12');
    }

    public function testErrorsNeverContainThePassword(): void
    {
        try {
            Pkcs12Certificate::fromString(self::pkcs12('secreto-muy-largo'), 'contraseña-erronea', 'prueba.p12');
            self::fail('Se esperaba CertificateException');
        } catch (CertificateException $e) {
            self::assertStringNotContainsString('secreto-muy-largo', $e->getMessage());
            self::assertStringNotContainsString('contraseña-erronea', $e->getMessage());
        }
    }

    public function testInvalidFileGivesClearError(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('no es un certificado PKCS#12');

        Pkcs12Certificate::fromString('esto no es un p12', self::PASSWORD, 'basura.p12');
    }

    public function testMissingFile(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('No se encuentra');

        Pkcs12Certificate::fromFile('/no/existe/cert.p12', self::PASSWORD);
    }

    public function testExpiredCertificateIsRejected(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('caducó');

        Pkcs12Certificate::fromString(self::pkcs12(days: 1), self::PASSWORD, 'prueba.p12', now: new \DateTimeImmutable('+10 days'));
    }

    public function testOpenWritesTemporaryPemWithRestrictivePermissionsAndReleasesThem(): void
    {
        $credential = self::certificate()->open();

        self::assertFileExists($credential->certificatePath);
        self::assertFileExists($credential->privateKeyPath);
        self::assertSame('0600', substr(sprintf('%o', fileperms($credential->certificatePath)), -4));
        self::assertSame('0600', substr(sprintf('%o', fileperms($credential->privateKeyPath)), -4));
        self::assertSame('0700', substr(sprintf('%o', fileperms(dirname($credential->privateKeyPath))), -4));
        self::assertStringContainsString('ENCRYPTED', (string) file_get_contents($credential->privateKeyPath));
        self::assertNotNull($credential->privateKeyPassphrase);

        $dir = dirname($credential->privateKeyPath);
        $credential->release();

        self::assertFileDoesNotExist($credential->certificatePath);
        self::assertFileDoesNotExist($credential->privateKeyPath);
        self::assertDirectoryDoesNotExist($dir);
        $credential->release();
    }

    public function testLoaderDetectsPemAndPkcs12(): void
    {
        $p12 = self::writeTemp(self::pkcs12(), '.pfx');
        $loaded = CertificateLoader::fromFile($p12, self::PASSWORD);
        self::assertInstanceOf(Pkcs12Certificate::class, $loaded);

        $parts = [];
        openssl_pkcs12_read(self::pkcs12(), $parts, self::PASSWORD);
        $pem = self::writeTemp($parts['cert'] . $parts['pkey'], '.pem');
        $loaded = CertificateLoader::fromFile($pem, null);
        self::assertInstanceOf(PemCertificate::class, $loaded);
        self::assertStringContainsString('CN=CERTIFICADO DE PRUEBA', $loaded->info()->subject);

        unlink($p12);
        unlink($pem);
    }

    public function testLoaderRequiresAPath(): void
    {
        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('No se ha indicado ningún certificado');

        CertificateLoader::fromFile(null, null);
    }

    public function testPemKeyMismatchIsDetected(): void
    {
        $a = [];
        $b = [];
        openssl_pkcs12_read(self::pkcs12(), $a, self::PASSWORD);
        openssl_pkcs12_read(self::pkcs12(), $b, self::PASSWORD);

        $this->expectException(CertificateException::class);
        $this->expectExceptionMessage('no corresponde');

        PemCertificate::fromStrings($a['cert'], $b['pkey']);
    }
}
