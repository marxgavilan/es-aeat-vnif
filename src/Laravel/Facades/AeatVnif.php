<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel\Facades;

use Iberfacil\AeatVnif\VnifClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Iberfacil\AeatVnif\Data\CheckResult check(string $nif, \Iberfacil\AeatVnif\Data\TaxpayerName|string $name)
 * @method static list<\Iberfacil\AeatVnif\Data\CheckResult> checkBatch(list<\Iberfacil\AeatVnif\Data\Taxpayer> $taxpayers)
 * @method static \Iberfacil\AeatVnif\VnifOptions options()
 *
 * @see VnifClient
 */
final class AeatVnif extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VnifClient::class;
    }
}
