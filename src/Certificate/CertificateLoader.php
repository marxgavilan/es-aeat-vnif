<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Exceptions\CertificateException;

/**
 * Punto unico de entrada: le das una ruta y una contraseña y decide si es .p12/.pfx o PEM.
 */
final class CertificateLoader
{
    /**
     * @param string|null $keyPath Solo para PEM con la clave en otro fichero.
     *
     * @throws CertificateException
     */
    public static function fromFile(?string $path, #[\SensitiveParameter] ?string $password, ?string $keyPath = null): Certificate
    {
        if ($path === null || trim($path) === '') {
            throw CertificateException::missing();
        }

        if (self::looksLikePem($path)) {
            return PemCertificate::fromFiles($path, $keyPath, $password);
        }

        return Pkcs12Certificate::fromFile($path, $password ?? '');
    }

    public static function looksLikePem(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['pem', 'crt', 'cer', 'key'], true)) {
            return true;
        }
        if (in_array($extension, ['p12', 'pfx'], true)) {
            return false;
        }

        // Sin extension reconocible: miramos el principio del fichero.
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = (string) fread($handle, 64);
        fclose($handle);

        return str_contains($head, '-----BEGIN');
    }
}
