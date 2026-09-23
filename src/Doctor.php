<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif;

use Iberfacil\AeatVnif\Certificate\CertificateLoader;
use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Contracts\ProbeableTransport;
use Iberfacil\AeatVnif\Contracts\Transport;
use Iberfacil\AeatVnif\Exceptions\AeatVnifException;
use Iberfacil\AeatVnif\Transport\CurlTransport;
use Throwable;

/**
 * Diagnostico previo: extensiones, certificado, caducidad y conectividad. No consulta
 * ningun NIF.
 *
 * @phpstan-type Check array{name: string, ok: bool, detail: string, warning?: bool}
 */
final class Doctor
{
    public function __construct(
        private readonly ?VnifOptions $options = null,
        private readonly ?Transport $transport = null,
    ) {}

    /**
     * @return list<Check>
     */
    public function run(?string $certificatePath, #[\SensitiveParameter] ?string $password, ?string $keyPath = null, bool $probe = true): array
    {
        $checks = [];

        foreach (['openssl', 'curl', 'dom', 'libxml', 'mbstring'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = ['name' => 'Extensión ' . $extension, 'ok' => $loaded, 'detail' => $loaded ? 'cargada' : 'no cargada: instálela (php-' . $extension . ')'];
        }
        $checks[] = ['name' => 'Extensión intl (opcional)', 'ok' => true, 'warning' => ! extension_loaded('intl'), 'detail' => extension_loaded('intl') ? 'cargada' : 'no cargada; los nombres no se normalizarán en NFC (recomendable instalarla)'];

        $options = $this->options ?? new VnifOptions();
        $checks[] = ['name' => 'Endpoint', 'ok' => true, 'detail' => $options->endpoint];

        $certificate = null;
        try {
            $certificate = CertificateLoader::fromFile($certificatePath, $password, $keyPath);
            $checks[] = ['name' => 'Certificado', 'ok' => true, 'detail' => 'leído correctamente (' . $certificate->info()->subject . ')'];
        } catch (AeatVnifException $e) {
            $checks[] = ['name' => 'Certificado', 'ok' => false, 'detail' => $e->getMessage()];
        }

        if ($certificate !== null) {
            $checks[] = $this->expiry($certificate);
        }

        if ($certificate !== null && $probe) {
            $checks[] = $this->connectivity($certificate, $options);
        } elseif ($probe) {
            $checks[] = ['name' => 'Conectividad', 'ok' => false, 'detail' => 'no comprobada: hace falta un certificado válido'];
        }

        return $checks;
    }

    /** @param list<Check> $checks */
    public static function allOk(array $checks): bool
    {
        foreach ($checks as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return Check */
    private function expiry(Certificate $certificate): array
    {
        $info = $certificate->info();
        $days = $info->daysUntilExpiry();
        $detail = sprintf('válido hasta %s (%d días)', $info->validTo->format('Y-m-d'), $days);

        if ($days < 0) {
            return ['name' => 'Caducidad', 'ok' => false, 'detail' => 'caducado el ' . $info->validTo->format('Y-m-d')];
        }

        return ['name' => 'Caducidad', 'ok' => true, 'warning' => $days < 30, 'detail' => $days < 30 ? $detail . ': renuévelo pronto' : $detail];
    }

    /** @return Check */
    private function connectivity(Certificate $certificate, VnifOptions $options): array
    {
        $transport = $this->transport ?? new CurlTransport();
        if (! $transport instanceof ProbeableTransport) {
            return ['name' => 'Conectividad', 'ok' => true, 'warning' => true, 'detail' => 'omitida: el transporte inyectado no implementa ProbeableTransport'];
        }

        $credential = $certificate->open();
        try {
            $result = $transport->probe($options->endpoint, $credential, $options->timeoutSeconds);
        } catch (Throwable $e) {
            return ['name' => 'Conectividad', 'ok' => false, 'detail' => $e->getMessage()];
        } finally {
            $credential->release();
        }

        return [
            'name' => 'Conectividad',
            'ok' => $result->reachable,
            'detail' => sprintf('%s (%.0f ms)', $result->detail, $result->durationMs),
        ];
    }
}
