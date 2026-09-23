<?php

declare(strict_types=1);

return [

    /*
    | Ruta al certificado electronico (.p12/.pfx o .pem). Fuera del repositorio y fuera de
    | public/. La contraseña solo en el .env, nunca aqui.
    */
    'certificate' => [
        'path' => env('AEAT_VNIF_CERT'),
        'password' => env('AEAT_VNIF_CERT_PASSWORD'),
        // Solo para PEM con la clave privada en otro fichero.
        'key_path' => env('AEAT_VNIF_CERT_KEY'),
    ],

    // Endpoint SOAP del servicio VNifV2 (por defecto, produccion).
    'endpoint' => env('AEAT_VNIF_ENDPOINT', 'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP'),

    // Contribuyentes por peticion (1..10000).
    'batch_size' => (int) env('AEAT_VNIF_BATCH_SIZE', 10000),

    // Segundos de espera por peticion.
    'timeout' => (int) env('AEAT_VNIF_TIMEOUT', 30),

    // Reintentos ante fallo transitorio de red y espera inicial entre ellos.
    'retries' => (int) env('AEAT_VNIF_RETRIES', 2),
    'retry_delay_ms' => (int) env('AEAT_VNIF_RETRY_DELAY_MS', 500),

    /*
    | Cache de resultados: null para desactivarla, o el nombre de un store de Laravel
    | ("file", "redis"...). Se guardan solo resultados no transitorios, durante cache_ttl segundos.
    */
    'cache' => [
        'store' => env('AEAT_VNIF_CACHE_STORE'),
        'ttl' => (int) env('AEAT_VNIF_CACHE_TTL', 86400),
    ],

    // Disparar los eventos VnifRequestStarting / VnifRequestCompleted / VnifRequestFailed.
    'events' => (bool) env('AEAT_VNIF_EVENTS', true),

    // Proxy y bundle CA propios para el transporte cURL, si hacen falta.
    'proxy' => env('AEAT_VNIF_PROXY'),
    'ca_bundle' => env('AEAT_VNIF_CA_BUNDLE'),
];
