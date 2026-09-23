<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Laravel;

use Iberfacil\AeatVnif\Cache\Psr16ResultCache;
use Iberfacil\AeatVnif\Certificate\CertificateLoader;
use Iberfacil\AeatVnif\Contracts\Certificate;
use Iberfacil\AeatVnif\Contracts\NameNormalizer;
use Iberfacil\AeatVnif\Contracts\ResultCache;
use Iberfacil\AeatVnif\Contracts\Transport;
use Iberfacil\AeatVnif\Laravel\Console\CheckCommand;
use Iberfacil\AeatVnif\Laravel\Console\DoctorCommand;
use Iberfacil\AeatVnif\Name\DefaultNameNormalizer;
use Iberfacil\AeatVnif\Transport\CurlTransport;
use Iberfacil\AeatVnif\VnifClient;
use Iberfacil\AeatVnif\VnifOptions;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Registra VnifClient como singleton a partir de config/aeat-vnif.php. Para sustituir el
 * transporte, el normalizador o la cache basta con hacer bind de la interfaz
 * correspondiente antes de que se resuelva el cliente.
 */
final class AeatVnifServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/aeat-vnif.php', 'aeat-vnif');

        $this->app->singleton(VnifOptions::class, static function (Application $app): VnifOptions {
            /** @var array{endpoint?: mixed, batch_size?: mixed, timeout?: mixed, retries?: mixed, retry_delay_ms?: mixed} $config */
            $config = $app->make('config')->get('aeat-vnif', []);

            return VnifOptions::fromArray($config);
        });

        $this->app->bindIf(NameNormalizer::class, DefaultNameNormalizer::class);

        $this->app->bindIf(Transport::class, static function (Application $app): Transport {
            $config = $app->make('config');
            $proxy = $config->get('aeat-vnif.proxy');
            $caBundle = $config->get('aeat-vnif.ca_bundle');

            return new CurlTransport(
                is_string($caBundle) && $caBundle !== '' ? $caBundle : null,
                is_string($proxy) && $proxy !== '' ? $proxy : null,
            );
        });

        // El certificado se lee al resolverlo, no al arrancar: asi un .env incompleto no
        // rompe artisan entero, solo la consulta.
        $this->app->bindIf(Certificate::class, static function (Application $app): Certificate {
            $config = $app->make('config');
            $path = $config->get('aeat-vnif.certificate.path');
            $password = $config->get('aeat-vnif.certificate.password');
            $keyPath = $config->get('aeat-vnif.certificate.key_path');

            return CertificateLoader::fromFile(
                is_string($path) ? $path : null,
                is_string($password) ? $password : null,
                is_string($keyPath) && $keyPath !== '' ? $keyPath : null,
            );
        });

        $this->app->singleton(VnifClient::class, static function (Application $app): VnifClient {
            $config = $app->make('config');
            $observers = [];
            if ((bool) $config->get('aeat-vnif.events', true)) {
                $observers[] = new EventDispatchingObserver($app->make(Dispatcher::class));
            }

            return new VnifClient(
                certificate: $app->make(Certificate::class),
                options: $app->make(VnifOptions::class),
                transport: $app->make(Transport::class),
                normalizer: $app->make(NameNormalizer::class),
                cache: self::cache($app),
                observers: $observers,
            );
        });

        $this->app->alias(VnifClient::class, 'aeat-vnif');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../../config/aeat-vnif.php' => $this->app->configPath('aeat-vnif.php')], 'aeat-vnif-config');
            $this->commands([CheckCommand::class, DoctorCommand::class]);
        }
    }

    private static function cache(Application $app): ?ResultCache
    {
        if ($app->bound(ResultCache::class)) {
            return $app->make(ResultCache::class);
        }

        $config = $app->make('config');
        $store = $config->get('aeat-vnif.cache.store');
        if (! is_string($store) || $store === '') {
            return null;
        }

        $ttl = (int) $config->get('aeat-vnif.cache.ttl', 86400);

        return new Psr16ResultCache($app->make(CacheFactory::class)->store($store), $ttl);
    }
}
