<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

use DateTimeImmutable;
use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Exceptions\CertificateException;
use OpenSSLAsymmetricKey;

/**
 * Certificado ya en PEM: un fichero con cert + clave, o dos ficheros. La clave puede ir
 * cifrada. Se reescribe igualmente a temporales para tratarlo igual que el .p12.
 */
final class PemCertificate implements Certificate
{
    private ?CertificateInfo $info = null;

    /** @param list<string> $chain */
    private function __construct(
        private readonly string $certificatePem,
        private readonly OpenSSLAsymmetricKey $privateKey,
        private readonly array $chain,
        private readonly TemporaryPemWriter $writer,
    ) {}

    /**
     * @param string|null $keyPath Si la clave no va en el mismo fichero.
     *
     * @throws CertificateException
     */
    public static function fromFiles(string $certificatePath, ?string $keyPath = null, #[\SensitiveParameter] ?string $keyPassword = null, ?TemporaryPemWriter $writer = null, ?DateTimeImmutable $now = null): self
    {
        $certificatePem = self::read($certificatePath);
        $keyPem = $keyPath === null ? $certificatePem : self::read($keyPath);

        return self::fromStrings($certificatePem, $keyPem, $keyPassword, $keyPath ?? $certificatePath, $writer, $now);
    }

    /**
     * @throws CertificateException
     */
    public static function fromStrings(string $certificatePem, #[\SensitiveParameter] string $keyPem, #[\SensitiveParameter] ?string $keyPassword = null, string $label = 'pem', ?TemporaryPemWriter $writer = null, ?DateTimeImmutable $now = null): self
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $certificatePem, $matches);
        $certificates = $matches[0];
        if ($certificates === []) {
            throw CertificateException::invalidPem($label);
        }

        $leaf = array_shift($certificates);
        $key = openssl_pkey_get_private($keyPem, $keyPassword ?? '');
        if ($key === false) {
            throw CertificateException::invalidPrivateKey($label);
        }

        if (! openssl_x509_check_private_key($leaf, $key)) {
            throw CertificateException::keyMismatch();
        }

        $certificate = new self($leaf, $key, array_values($certificates), $writer ?? new TemporaryPemWriter());
        $info = $certificate->info();
        if ($info->isExpired($now)) {
            throw CertificateException::expired($info->validTo);
        }
        if ($info->isNotYetValid($now)) {
            throw CertificateException::notYetValid($info->validFrom);
        }

        return $certificate;
    }

    public function info(): CertificateInfo
    {
        if ($this->info === null) {
            $parsed = openssl_x509_parse($this->certificatePem);
            if ($parsed === false) {
                throw CertificateException::invalidPem('pem');
            }
            $this->info = CertificateInfo::fromParsed($parsed);
        }

        return $this->info;
    }

    public function open(): PemCredential
    {
        return $this->writer->write($this->certificatePem, $this->privateKey, $this->chain);
    }

    private static function read(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw CertificateException::fileNotFound($path);
        }
        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw CertificateException::fileNotFound($path);
        }

        return $contents;
    }
}
