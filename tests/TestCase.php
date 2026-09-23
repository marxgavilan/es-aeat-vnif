<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests;

use Iberfacil\AeatVnif\Certificate\Pkcs12Certificate;
use Iberfacil\AeatVnif\Contracts\Certificate;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const PASSWORD = 'clave-de-prueba';

    protected static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/vnif/' . $name . '.xml');
    }

    /**
     * Genera un certificado autofirmado en memoria y lo empaqueta como PKCS#12. No hay
     * ningun certificado en el repositorio.
     *
     * @param array<string, mixed> $dn
     */
    protected static function pkcs12(string $password = self::PASSWORD, int $days = 365, array $dn = []): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);

        $dn = $dn + ['commonName' => 'CERTIFICADO DE PRUEBA', 'organizationName' => 'ENTIDAD FICTICIA SL', 'countryName' => 'ES'];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        self::assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $csr);
        $x509 = openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256']);
        self::assertNotFalse($x509);

        $out = '';
        self::assertTrue(openssl_pkcs12_export($x509, $out, $key, $password));

        return $out;
    }

    protected static function certificate(): Certificate
    {
        return Pkcs12Certificate::fromString(self::pkcs12(), self::PASSWORD, 'prueba.p12');
    }

    protected static function writeTemp(string $contents, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aeat-vnif-test-') . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }
}
