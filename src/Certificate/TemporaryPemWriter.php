<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

use Iberfacil\AeatVnif\Exceptions\CertificateException;
use OpenSSLAsymmetricKey;

/**
 * Escribe certificado (con su cadena) y clave en un directorio temporal 0700 con ficheros
 * 0600. La clave se reexporta cifrada con una frase aleatoria que solo vive en memoria,
 * asi el fichero temporal no vale nada por si solo.
 */
final class TemporaryPemWriter
{
    public function __construct(private readonly ?string $baseDirectory = null) {}

    /**
     * @param list<string> $chain Certificados intermedios en PEM, si los hay.
     */
    public function write(string $certificatePem, OpenSSLAsymmetricKey $privateKey, array $chain = []): PemCredential
    {
        $base = $this->baseDirectory ?? sys_get_temp_dir();
        $dir = null;

        for ($i = 0; $i < 5; $i++) {
            $candidate = $base . DIRECTORY_SEPARATOR . 'aeat-vnif-' . bin2hex(random_bytes(8));
            if (@mkdir($candidate, 0700)) {
                $dir = $candidate;
                break;
            }
        }

        if ($dir === null) {
            throw CertificateException::temporaryFiles('no se pudo crear un directorio en ' . $base);
        }

        $passphrase = bin2hex(random_bytes(24));
        $keyPem = '';

        if (! openssl_pkey_export($privateKey, $keyPem, $passphrase, ['encrypt_key' => true, 'encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC])) {
            @rmdir($dir);

            throw CertificateException::temporaryFiles('no se pudo exportar la clave privada');
        }

        $certPath = $dir . DIRECTORY_SEPARATOR . 'cert.pem';
        $keyPath = $dir . DIRECTORY_SEPARATOR . 'key.pem';
        $bundle = rtrim($certificatePem) . "\n" . implode('', array_map(static fn(string $c): string => rtrim($c) . "\n", $chain));

        $previousUmask = umask(0077);
        try {
            $ok = file_put_contents($certPath, $bundle, LOCK_EX) !== false
                && chmod($certPath, 0600)
                && file_put_contents($keyPath, $keyPem, LOCK_EX) !== false
                && chmod($keyPath, 0600);
        } finally {
            umask($previousUmask);
        }

        if (! $ok) {
            $credential = new PemCredential($certPath, $keyPath, null, $dir);
            $credential->release();

            throw CertificateException::temporaryFiles('no se pudieron escribir los ficheros en ' . $dir);
        }

        return new PemCredential($certPath, $keyPath, $passphrase, $dir);
    }
}
