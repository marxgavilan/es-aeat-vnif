# es-aeat-vnif

Comprueba desde PHP que un NIF existe en el censo de la Agencia Tributaria y que corresponde al nombre que te han dado, usando tu certificado electrónico. Sin framework, con línea de comandos y con adaptador opcional para Laravel.

[English version](README.en.md)

Creado por Marco Gavilán, de IBERFÁCIL.

---

## Para qué sirve (si no eres técnico)

Cuando una gestoría, un despacho, una empresa o un departamento de cumplimiento recibe los datos de un cliente, un proveedor o un tercero, casi siempre le llega un NIF y un nombre escritos a mano, copiados de un correo o dictados por teléfono. Antes de facturar, contratar, dar de alta a ese cliente, presentar un escrito o incluirlo en una declaración informativa conviene saber dos cosas:

- que ese NIF **existe** en el censo de la Agencia Tributaria, y
- que **corresponde al nombre** que te han dado (o a qué nombre corresponde en realidad).

La AEAT ofrece un servicio para eso: el «Servicio de verificación de NIF de terceros» (VNifV2). Este paquete lo usa por ti. Le das el NIF y el nombre, y te devuelve una de estas respuestas:

| La AEAT responde | Qué significa en la práctica |
| --- | --- |
| **IDENTIFICADO** | El NIF existe y el nombre coincide. Si el nombre que enviaste era aproximado (sin tildes, una letra de menos, faltaba un apellido), la AEAT lo identifica igualmente y te devuelve el nombre **tal como figura en el censo**. |
| **NO IDENTIFICADO-SIMILAR** | El NIF existe pero el nombre solo se parece. La AEAT te devuelve el nombre censal para que lo revises. |
| **NO IDENTIFICADO** | El NIF no consta o el nombre no se corresponde. |
| **IDENTIFICADO-BAJA** / **IDENTIFICADO-REVOCADO** | El NIF existe y coincide, pero está dado de baja o revocado. |
| **NO PROCESADO** | La AEAT no ha podido procesar esa consulta ahora mismo. Se reintenta más tarde. |

Ejemplos de uso reales:

- Una gestoría recibe 300 clientes nuevos en un fichero y quiere saber cuáles tienen el NIF mal o el nombre incompleto antes de darlos de alta.
- Un despacho va a presentar un escrito y necesita el nombre exacto del interesado tal como consta en la AEAT.
- Un departamento de facturación quiere evitar emitir facturas con un NIF inexistente, que luego darán problemas en el 347 o en el SII.
- Un comercio online da de alta proveedores y quiere comprobar que la razón social corresponde al CIF.

**Qué necesitas:** un certificado electrónico (de sello de entidad, de representante de persona jurídica o personal) en formato `.p12` o `.pfx`, con su contraseña. Es el mismo que usas para entrar en la sede electrónica. El paquete no guarda la contraseña ni el certificado en ningún sitio.

**Qué NO hace:** no acredita que la persona que te da los datos sea quien dice ser. Solo te confirma que ese NIF y ese nombre existen juntos en el censo. La identificación de la persona es otra cosa (documento, vídeo-identificación, firma electrónica…).

---

## Para desarrolladores

### Requisitos

- PHP 8.2 o superior con las extensiones `openssl`, `curl`, `dom`, `libxml` y `mbstring` (todas habituales). `intl` es opcional pero recomendable.
- Un certificado electrónico admitido por la AEAT, en `.p12`/`.pfx` (o en PEM).
- Acceso HTTPS al endpoint `https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP`.

### Instalación

```bash
composer require iberfacil/es-aeat-vnif
```

### Puesta en marcha en cinco minutos

1. **Sube tu certificado** al servidor, fuera del repositorio y fuera de cualquier carpeta pública. Por ejemplo `/etc/aeat/certificado.p12` con permisos `0600`.

2. **Pon la contraseña en el entorno**, nunca en el código ni en el repositorio:

   ```bash
   export AEAT_VNIF_CERT=/etc/aeat/certificado.p12
   export AEAT_VNIF_CERT_PASSWORD='la-contraseña'
   ```

   Si no defines `AEAT_VNIF_CERT_PASSWORD`, el ejecutable la pedirá por teclado sin eco.

3. **Ejecuta el diagnóstico.** Comprueba extensiones, lee el certificado, mira la caducidad y abre una conexión TLS con el endpoint. No consulta ningún NIF.

   ```bash
   vendor/bin/aeat-vnif doctor
   ```

4. **Haz tu primera consulta.**

   ```bash
   vendor/bin/aeat-vnif check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo
   ```

   ```
   00000000T  IDENTIFICADO
       El NIF y el nombre coinciden con el censo de la AEAT.
       Enviado:  LUMINA PERALVILLO ZEFIRA
       Censo:    LUMINÁ PERALVILLO ZÉFIRA
   ```

(Los NIF y nombres de esta documentación son inventados.)

### Uso manual con el ejecutable

```bash
# Persona física
aeat-vnif check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo

# Entidad
aeat-vnif check B00000000 --razon-social="Brumalia Ficticia SL"

# Lote desde CSV (separador ";")
aeat-vnif batch clientes.csv

# Salida JSON para encadenar con otras herramientas
aeat-vnif check 00000000T --razon-social="Brumalia Ficticia SL" --json

# Certificado explícito (la contraseña siempre por AEAT_VNIF_CERT_PASSWORD o por teclado)
aeat-vnif doctor --cert=/ruta/certificado.pfx
```

Formato del CSV, una línea por contribuyente:

```
NIF;APELLIDO1;APELLIDO2;NOMBRE     persona física
NIF;APELLIDO1;NOMBRE               persona física con un solo apellido
NIF;RAZON_SOCIAL                   entidad
```

Se admite una cabecera que empiece por `NIF`, líneas vacías y comentarios con `#`.

Códigos de salida: `0` consulta realizada (sea cual sea el resultado de la AEAT), `1` error de uso o de configuración, `2` fallo transitorio (red, timeout, NO PROCESADO), `3` fallo definitivo.

Opciones: `--endpoint=`, `--timeout=`, `--retries=`, `--batch-size=`, `--json`, `--no-probe` (en `doctor`). También se leen de `AEAT_VNIF_ENDPOINT`, `AEAT_VNIF_TIMEOUT`, `AEAT_VNIF_RETRIES` y `AEAT_VNIF_BATCH_SIZE`.

### Uso en código

```php
use Iberfacil\AeatVnif\VnifClient;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;

$client = VnifClient::create('/etc/aeat/certificado.p12', getenv('AEAT_VNIF_CERT_PASSWORD'));

// Una persona física: nombre y apellidos por separado
$result = $client->check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Lumina', 'Peralvillo'));

$result->result;              // IdentificationResult::Identified
$result->aeatResult;          // 'IDENTIFICADO' (literal de la AEAT)
$result->isIdentified();      // true
$result->sentName;            // 'LUMINA PERALVILLO ZEFIRA'
$result->censusName;          // 'LUMINÁ PERALVILLO ZÉFIRA' (como figura en el censo)
$result->nameWasCorrected();  // false: las letras coinciden, solo cambian las tildes

// Una entidad
$result = $client->check('B00000000', TaxpayerName::entity('Brumalia Ficticia, S.L.'));

// Un lote: los resultados llegan en el mismo orden que la entrada
$results = $client->checkBatch([
    Taxpayer::naturalPerson('00000000T', 'Zefira', 'Lumina', 'Peralvillo'),
    Taxpayer::entity('B00000000', 'Brumalia Ficticia SL'),
]);

foreach ($results as $r) {
    if ($r->failure !== null) {
        // NO PROCESADO: reintentar este contribuyente más tarde
    }
}
```

Las opciones se pasan con `VnifOptions`:

```php
use Iberfacil\AeatVnif\VnifOptions;

$options = new VnifOptions(
    endpoint: VnifOptions::DEFAULT_ENDPOINT,
    batchSize: 500,       // contribuyentes por petición (1..10000)
    timeoutSeconds: 30,
    retries: 2,           // reintentos ante fallo transitorio de red
    retryDelayMs: 500,    // espera inicial, se duplica en cada intento
);

$client = VnifClient::create($ruta, $password, $options);
```

Excepciones, todas bajo `Iberfacil\AeatVnif\Exceptions\AeatVnifException`:

| Excepción | Cuándo | Qué hacer |
| --- | --- | --- |
| `TransientFailure` | Red, timeout, HTTP 408/5xx, o `NO PROCESADO` en `check()` | Reintentar más tarde |
| `DefinitiveFailure` | HTTP 4xx, SOAP Fault, XML inválido, respuesta que no cuadra con lo enviado | Revisar; reintentar no ayuda |
| `ConfigurationException` | Endpoint, lote, timeout o reintentos fuera de rango | Corregir la configuración |
| `CertificateException` (hija de la anterior) | Fichero no encontrado, contraseña incorrecta, caducado, clave que no corresponde | Corregir el certificado |
| `InvalidTaxpayer` | NIF vacío o mal formado, nombre vacío tras normalizar | Corregir la entrada |

Ninguna excepción incluye la contraseña, el contenido del certificado, el XML ni el texto que devuelva la AEAT.

### Cómo interpretar cada resultado

`CheckResult` expone `result` (enum `IdentificationResult`), `aeatResult` (el literal), `sentName`, `censusName`, `nameWasCorrected()`, `censusNameHasAccents()`, `raw` y `failure`.

- **IDENTIFICADO con el nombre corregido.** Comprobado contra el servicio real: si envías «LUMINA ZEFIRA» y en el censo consta «LUMINÁ PERALVILLO ZÉFIRA», la AEAT responde `IDENTIFICADO` y devuelve el nombre censal completo, con tildes y a veces con espacios de relleno. Por eso el paquete **casa la respuesta por NIF, nunca por nombre**, y `nameWasCorrected()` devuelve `true` cuando las letras difieren (tildes y relleno solos no cuentan; para eso está `censusNameHasAccents()`). Guarda `censusName` si necesitas el nombre exacto.
- **NO IDENTIFICADO-SIMILAR.** El NIF existe; el nombre se parece pero no lo bastante. `censusName` trae el nombre censal y `nameWasCorrected()` es `true`. Suele ser un apellido mal escrito o un nombre compuesto a medias.
- **NO IDENTIFICADO.** `censusName` es `null`. O el NIF no existe o el nombre no se parece en nada.
- **IDENTIFICADO-BAJA / IDENTIFICADO-REVOCADO.** `isIdentified()` es `true`, pero mira `result` antes de dar el NIF por operativo.
- **NO PROCESADO.** En `checkBatch()` llega como resultado con `failure` (no tira el lote); en `check()` se lanza `TransientFailure`.

### Uso en Laravel

El proveedor se autodescubre. Publica la configuración si quieres tocarla:

```bash
php artisan vendor:publish --tag=aeat-vnif-config
```

`.env`:

```dotenv
AEAT_VNIF_CERT=/etc/aeat/certificado.p12
AEAT_VNIF_CERT_PASSWORD=la-contraseña
# Opcionales
AEAT_VNIF_ENDPOINT=https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
AEAT_VNIF_TIMEOUT=30
AEAT_VNIF_BATCH_SIZE=10000
AEAT_VNIF_RETRIES=2
AEAT_VNIF_CACHE_STORE=redis      # vacío = sin caché
AEAT_VNIF_CACHE_TTL=86400
```

```php
use Iberfacil\AeatVnif\Laravel\Facades\AeatVnif;
use Iberfacil\AeatVnif\Data\TaxpayerName;

$result = AeatVnif::check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Lumina', 'Peralvillo'));
```

O inyecta `Iberfacil\AeatVnif\VnifClient` donde lo necesites. Comandos:

```bash
php artisan aeat-vnif:doctor
php artisan aeat-vnif:check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo
php artisan aeat-vnif:check B00000000 --razon-social="Brumalia Ficticia SL" --json
```

Eventos (`Iberfacil\AeatVnif\Laravel\Events\*`): `VnifRequestStarting`, `VnifRequestCompleted` y `VnifRequestFailed`, uno por petición HTTP, con el hash del XML, los NIF enviados y el recuento de resultados. Desactívalos con `AEAT_VNIF_EVENTS=false`.

Para sustituir el transporte, el normalizador o la caché, haz `bind` de `Contracts\Transport`, `Contracts\NameNormalizer` o `Contracts\ResultCache` en tu proveedor antes de que se resuelva el cliente.

### Personalización

**Antes y después de cada consulta** (auditoría a tu manera):

```php
use Iberfacil\AeatVnif\Observers\CallbackObserver;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;

$client = $client->withObserver(
    CallbackObserver::make()
        ->before(fn (VnifRequest $r) => $log->info('AEAT VNIF', ['hash' => $r->xmlSha256, 'n' => $r->size(), 'intento' => $r->attempt]))
        ->after(fn (VnifRequest $r, VnifResponse $s) => $log->info('AEAT VNIF ok', $s->countsByResult()))
        ->failure(fn (VnifRequest $r, \Throwable $e) => $log->warning('AEAT VNIF fallo', ['tipo' => $e::class]))
);
```

O implementa `Contracts\CheckObserver` en una clase. `VnifRequest` expone los NIF y nombres enviados por si tu auditoría los necesita; decide tú qué guardas.

**Caché** (interfaz `Contracts\ResultCache`): `Cache\InMemoryResultCache` para una ejecución, `Cache\Psr16ResultCache` sobre cualquier PSR-16, o la tuya. Nunca se guarda un `NO PROCESADO`.

```php
$client = new VnifClient($certificate, $options, cache: new Psr16ResultCache($miCache, ttlSeconds: 86400));
```

**Normalizador de nombres** (interfaz `Contracts\NameNormalizer`): el de serie pasa a mayúsculas, quita tildes y diéresis (conserva la Ñ), convierte guiones en espacios, elimina puntuación y compacta espacios. Si necesitas otras reglas, inyecta el tuyo.

**Transporte** (interfaz `Contracts\Transport`): el de serie es `Transport\CurlTransport` (mTLS, solo HTTPS, sin redirecciones, timeout de conexión y total, proxy y bundle CA opcionales). Implementa la interfaz para usar tu cliente PSR-18 o Guzzle. Para tests, `Transport\FakeTransport` encola respuestas y registra lo enviado.

**Certificado** (interfaz `Contracts\Certificate`): `Certificate\Pkcs12Certificate` y `Certificate\PemCertificate`, o `CertificateLoader::fromFile()` que decide por ti. El certificado se convierte en memoria a PEM temporales en un directorio `0700` con ficheros `0600`, la clave se reexporta cifrada con una frase aleatoria, y todo se borra en `finally` (y en el destructor, por si acaso).

### Límites y buenas prácticas

- El servicio admite hasta 10 000 contribuyentes por petición; el paquete trocea automáticamente. En un mismo lote, un NIF repetido con nombre distinto va en peticiones separadas (la respuesta se casa por NIF).
- No hagas consultas desde una petición web síncrona: ponlas en una cola o un proceso de fondo. El endpoint puede tardar.
- **Base legitimadora.** Consultar el censo trata datos personales del tercero. Necesitas una base legítima (normalmente el cumplimiento de obligaciones tributarias o contractuales) y un fin concreto. No lo uses para curiosear.
- **Minimización.** Consulta solo los NIF que necesitas, y guarda solo lo que necesites del resultado.
- **Evidencia.** Conserva fecha, NIF, resultado, nombre censal si lo usas y el hash SHA-256 del XML enviado (lo tienes en `VnifRequest`). Sirve para justificar por qué diste un NIF por bueno.
- **Nunca** guardes la contraseña del certificado en el código, en el repositorio ni en logs; nunca la pases como argumento en la línea de comandos.
- El paquete no registra nada por sí mismo. Lo que se audita lo decides tú con los observadores.

### Cómo actualizar

```bash
composer update iberfacil/es-aeat-vnif
```

El paquete sigue versionado semántico: las versiones `1.x` no rompen la API pública; los cambios están en [CHANGELOG.md](CHANGELOG.md). Tras actualizar, vuelve a ejecutar `aeat-vnif doctor`.

### Desarrollo

```bash
composer install
composer check        # formateador + PHPStan + tests
```

Las pruebas no tocan la red: usan `FakeTransport`, respuestas de ejemplo con NIF y nombres inventados, y un certificado autofirmado que se genera en memoria durante los tests. Ver [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) y, si integras el paquete con ayuda de agentes de IA, [AGENTS.md](AGENTS.md).

## Licencia

MIT. Copyright (c) 2026 Marco Gavilán — IBERFÁCIL. Ver [LICENSE](LICENSE).
