<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Console;

use Iberfacil\AeatVnif\Contracts\Transport;
use Iberfacil\AeatVnif\Doctor;
use Iberfacil\AeatVnif\VnifOptions;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

final class DoctorCommand extends Command
{
    protected $signature = 'aeat-vnif:doctor {--no-probe : No comprobar la conectividad con el endpoint}';

    protected $description = 'Comprueba extensiones, certificado, caducidad y conectividad con la AEAT sin consultar ningún NIF';

    public function handle(Repository $config, VnifOptions $options, Transport $transport): int
    {
        $path = $config->get('aeat-vnif.certificate.path');
        $password = $config->get('aeat-vnif.certificate.password');
        $keyPath = $config->get('aeat-vnif.certificate.key_path');

        $checks = (new Doctor($options, $transport))->run(
            is_string($path) ? $path : null,
            is_string($password) ? $password : null,
            is_string($keyPath) && $keyPath !== '' ? $keyPath : null,
            probe: ! (bool) $this->option('no-probe'),
        );

        foreach ($checks as $check) {
            $mark = $check['ok'] ? (($check['warning'] ?? false) ? 'AVISO' : 'OK   ') : 'FALLO';
            $this->line(sprintf('  [%s] %-28s %s', $mark, $check['name'], $check['detail']));
        }

        return Doctor::allOk($checks) ? self::SUCCESS : self::FAILURE;
    }
}
