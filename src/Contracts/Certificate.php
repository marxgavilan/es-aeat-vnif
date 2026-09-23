<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

use Iberfacil\AeatVnif\Certificate\CertificateInfo;
use Iberfacil\AeatVnif\Certificate\PemCredential;
use Iberfacil\AeatVnif\Exceptions\CertificateException;

/**
 * Un certificado electronico (sello, representante o personal) valido para mTLS.
 */
interface Certificate
{
    /** Titular, emisor y vigencia, para `doctor` y para logs (sin claves). */
    public function info(): CertificateInfo;

    /**
     * Vuelca certificado y clave a PEM temporales (0600, directorio privado). Quien lo
     * abre TIENE que llamar a {@see PemCredential::release()} en un `finally`.
     *
     * @throws CertificateException
     */
    public function open(): PemCredential;
}
