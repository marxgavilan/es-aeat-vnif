<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Console;

use Iberfacil\AeatVnif\Data\CheckResult;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Exceptions\AeatVnifException;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;
use Iberfacil\AeatVnif\VnifClient;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'aeat-vnif:check
        {nif : NIF, NIE o CIF a comprobar}
        {--nombre= : Nombre de pila (persona física)}
        {--apellido1= : Primer apellido}
        {--apellido2= : Segundo apellido}
        {--razon-social= : Razón social (entidad)}
        {--json : Salida en JSON}';

    protected $description = 'Comprueba un NIF y un nombre contra el censo de la AEAT (VNifV2)';

    public function handle(VnifClient $client): int
    {
        $legalName = $this->option('razon-social');
        $firstName = $this->option('nombre');
        $lastName1 = $this->option('apellido1');
        $lastName2 = $this->option('apellido2');

        if (is_string($legalName) && $legalName !== '') {
            $name = TaxpayerName::entity($legalName);
        } elseif (is_string($firstName) && is_string($lastName1)) {
            $name = TaxpayerName::naturalPerson($firstName, $lastName1, is_string($lastName2) ? $lastName2 : null);
        } else {
            $this->error('Indique --nombre y --apellido1 (y --apellido2 si lo tiene), o --razon-social para una entidad.');

            return self::INVALID;
        }

        $nif = $this->argument('nif');

        try {
            $result = $client->checkBatch([new Taxpayer(is_string($nif) ? $nif : '', $name)])[0];
        } catch (TransientFailure $e) {
            $this->error('Fallo transitorio: ' . $e->getMessage());

            return 2;
        } catch (DefinitiveFailure $e) {
            $this->error('Fallo definitivo: ' . $e->getMessage());

            return 3;
        } catch (AeatVnifException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->render($result);

        return self::SUCCESS;
    }

    private function render(CheckResult $result): void
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line(sprintf('%s  %s', $result->nif, $result->aeatResult));
        $this->line('    ' . $result->result->description());
        $this->line('    Enviado:  ' . $result->sentName);
        if ($result->censusName !== null) {
            $this->line('    Censo:    ' . $result->censusName . ($result->nameWasCorrected() ? '   <- la AEAT ha corregido el nombre' : ''));
        }
    }
}
