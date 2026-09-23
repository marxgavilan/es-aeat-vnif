<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Exceptions;

use DateTimeImmutable;

/**
 * El certificado no sirve. Los mensajes dicen la ruta del fichero (util para quien lo
 * configura) pero nunca la contraseña ni nada de su contenido.
 */
class CertificateException extends ConfigurationException
{
    public static function missing(): self
    {
        return new self('No se ha indicado ningún certificado. Indique la ruta de su fichero .p12/.pfx (o .pem) y su contraseña.');
    }

    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('No se encuentra el fichero de certificado "%s" o no se puede leer.', $path));
    }

    public static function wrongPassword(string $path): self
    {
        return new self(sprintf('La contraseña del certificado "%s" no es correcta.', $path));
    }

    public static function invalidFile(string $path, string $hint = ''): self
    {
        return new self(sprintf('El fichero "%s" no es un certificado PKCS#12 (.p12/.pfx) válido.', $path) . ($hint !== '' ? ' ' . $hint : ''));
    }

    public static function invalidPem(string $path): self
    {
        return new self(sprintf('El fichero "%s" no contiene un certificado PEM válido.', $path));
    }

    public static function invalidPrivateKey(string $path): self
    {
        return new self(sprintf('No se pudo leer la clave privada de "%s" (¿contraseña incorrecta o fichero sin clave?).', $path));
    }

    public static function keyMismatch(): self
    {
        return new self('La clave privada no corresponde al certificado.');
    }

    public static function expired(DateTimeImmutable $expiredAt): self
    {
        return new self(sprintf('El certificado caducó el %s. Renueve el certificado antes de consultar.', $expiredAt->format('Y-m-d H:i:s T')));
    }

    public static function notYetValid(DateTimeImmutable $validFrom): self
    {
        return new self(sprintf('El certificado no es válido hasta el %s.', $validFrom->format('Y-m-d H:i:s T')));
    }

    public static function temporaryFiles(string $reason): self
    {
        return new self('No se pudieron crear los ficheros temporales del certificado: ' . $reason);
    }
}
