<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Contracts;

use Iberfacil\AeatVnif\Certificate\PemCredential;
use Iberfacil\AeatVnif\Data\ProbeResult;

/**
 * Capacidad opcional que usa `doctor`: abrir TLS contra el endpoint con el certificado y
 * decir si el servicio contesta, sin enviar ningun contribuyente.
 */
interface ProbeableTransport extends Transport
{
    public function probe(string $endpoint, PemCredential $credential, int $timeoutSeconds): ProbeResult;
}
