<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Certificate;

use DateTimeImmutable;
use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Exceptions\CertificateException;
use OpenSSLAsymmetricKey;

/**
 * Certificado .p12/.pfx (lo que da la FNMT, Camerfirma, etc.). Se abre en memoria con
 * openssl_pkcs12_read; nunca se escribe descifrado salvo en los PEM temporales de open().
 */
final class Pkcs12Certificate implements Certificate
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
     * @throws CertificateException
     */
    public static function fromFile(string $path, #[\SensitiveParameter] string $password, ?TemporaryPemWriter $writer = null, ?DateTimeImmutable $now = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw CertificateException::fileNotFound($path);
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw CertificateException::fileNotFound($path);
        }

        return self::fromString($contents, $password, $path, $writer, $now);
    }

    /**
     * @param string $label Nombre para los mensajes de error (normalmente la ruta).
     *
     * @throws CertificateException
     */
    public static function fromString(#[\SensitiveParameter] string $pkcs12, #[\SensitiveParameter] string $password, string $label = 'pkcs12', ?TemporaryPemWriter $writer = null, ?DateTimeImmutable $now = null): self
    {
        // Limpiar la cola de errores para que el diagnostico sea de esta llamada y no de otra.
        while (openssl_error_string() !== false) {
        }

        $parts = [];
        if (! openssl_pkcs12_read($pkcs12, $parts, $password)) {
            $joined = strtolower(implode(' | ', self::drainOpensslErrors()));

            if (str_contains($joined, 'mac verify') || str_contains($joined, 'invalid password') || str_contains($joined, 'pkcs12_verify_mac')) {
                throw CertificateException::wrongPassword($label);
            }

            if (str_contains($joined, 'unsupported') || str_contains($joined, 'digital envelope')) {
                throw CertificateException::invalidFile($label, 'Parece usar algoritmos antiguos (RC2/3DES) que OpenSSL 3 no carga por defecto: reexporte el certificado con "openssl pkcs12 -export" moderno o active el proveedor legacy.');
            }

            throw CertificateException::invalidFile($label);
        }

        $certificatePem = (string) ($parts['cert'] ?? '');
        $keyPem = (string) ($parts['pkey'] ?? '');
        $chain = [];
        foreach ((array) ($parts['extracerts'] ?? []) as $extra) {
            if (is_string($extra) && $extra !== '') {
                $chain[] = $extra;
            }
        }

        if ($certificatePem === '' || $keyPem === '') {
            throw CertificateException::invalidFile($label, 'No contiene certificado y clave privada.');
        }

        $key = openssl_pkey_get_private($keyPem);
        if ($key === false) {
            throw CertificateException::invalidPrivateKey($label);
        }

        $certificate = new self($certificatePem, $key, $chain, $writer ?? new TemporaryPemWriter());
        $certificate->assertUsable($label, $now);

        return $certificate;
    }

    public function info(): CertificateInfo
    {
        if ($this->info === null) {
            $parsed = openssl_x509_parse($this->certificatePem);
            if ($parsed === false) {
                throw CertificateException::invalidPem('pkcs12');
            }
            $this->info = CertificateInfo::fromParsed($parsed);
        }

        return $this->info;
    }

    public function open(): PemCredential
    {
        return $this->writer->write($this->certificatePem, $this->privateKey, $this->chain);
    }

    /** @return list<string> */
    private static function drainOpensslErrors(): array
    {
        $errors = [];
        for ($i = 0; $i < 64; $i++) {
            $error = openssl_error_string();
            if (! is_string($error)) {
                break;
            }
            $errors[] = $error;
        }

        return $errors;
    }

    private function assertUsable(string $label, ?DateTimeImmutable $now): void
    {
        if (! openssl_x509_check_private_key($this->certificatePem, $this->privateKey)) {
            throw CertificateException::keyMismatch();
        }

        $info = $this->info();
        if ($info->isExpired($now)) {
            throw CertificateException::expired($info->validTo);
        }
        if ($info->isNotYetValid($now)) {
            throw CertificateException::notYetValid($info->validFrom);
        }
    }
}
