# Guía para agentes de IA que integren este paquete

Este fichero está pensado para quien use un agente de programación para integrar `iberfacil/es-aeat-vnif` en un proyecto. Léelo entero antes de tocar código.

## Qué es

Cliente PHP del servicio VNifV2 de la AEAT: comprueba que un NIF y un nombre coinciden con el censo, con certificado electrónico (mTLS). Namespace `Iberfacil\AeatVnif`. Núcleo sin framework; adaptador opcional para Laravel en `src/Laravel`.

## Pasos exactos de integración

1. `composer require iberfacil/es-aeat-vnif`.
2. Pide al usuario la **ruta** de su certificado `.p12`/`.pfx`. No la inventes, no la busques en el disco, no copies el fichero al repositorio.
3. Define en el entorno (`.env`, variables del servicio, secretos del despliegue) `AEAT_VNIF_CERT` con la ruta y `AEAT_VNIF_CERT_PASSWORD` con la contraseña. Añade las claves a `.env.example` **sin valor**.
4. Comprueba que `.gitignore` excluye `.env`, `*.p12`, `*.pfx`, `*.pem` y `*.key`.
5. Ejecuta `vendor/bin/aeat-vnif doctor` (o `php artisan aeat-vnif:doctor`). No consulta ningún NIF. Si falla, corrige lo que diga antes de seguir.
6. Integra en código:
   - Sin framework: `VnifClient::create($ruta, $password)` y `check()` / `checkBatch()`.
   - Laravel: inyecta `Iberfacil\AeatVnif\VnifClient` o usa la fachada `AeatVnif`. El proveedor se autodescubre; `php artisan vendor:publish --tag=aeat-vnif-config` solo si hace falta tocar la configuración.
7. Ejecuta las consultas desde una cola o proceso de fondo, no desde una petición web síncrona.
8. Si el proyecto necesita auditoría, añade un `CheckObserver` (o escucha los eventos de Laravel). Decide con el usuario qué se guarda.

## Qué NO hacer

- **Nunca** metas el certificado, su contenido ni la contraseña en el repositorio, en código fuente, en fixtures, en tests, en logs, en mensajes de excepción ni en la salida de consola.
- **Nunca** pases la contraseña como argumento de línea de comandos (`--password=...`): queda en el historial del shell. El ejecutable la rechaza a propósito. Usa `AEAT_VNIF_CERT_PASSWORD` o deja que la pida por teclado.
- **Nunca** hagas consultas reales a la AEAT durante el desarrollo o en tests sin que el usuario lo pida expresamente. Cada consulta trata datos personales de un tercero.
- **Nunca** uses NIF o nombres reales en tests, fixtures, documentación o ejemplos. Usa los ficticios del paquete (`00000000T`, `00000001R`, `B00000000`, «LUMINA PERALVILLO ZEFIRA», «BRUMALIA FICTICIA SL») o inventa otros claramente falsos.
- No cases la respuesta por nombre. La AEAT devuelve el nombre censal (con tildes, completo, con relleno); el paquete casa por NIF. No «arregles» eso.
- No desactives la verificación TLS, no cambies el endpoint a `http://`, no sigas redirecciones.
- No registres el XML enviado ni el recibido: llevan NIF y nombres. Registra el hash (`VnifRequest::$xmlSha256`) y los recuentos.

## Cómo probar sin red

- Usa `Iberfacil\AeatVnif\Transport\FakeTransport`: `->queue($xml)` encola respuestas, `->queueRows([...])` las fabrica a partir de filas, `->sent` guarda lo enviado.
- Genera el certificado en memoria como hace `tests/TestCase.php` (`openssl_pkey_new` + `openssl_csr_sign` + `openssl_pkcs12_export`). No añadas ficheros de certificado al repositorio, ni siquiera de prueba.
- Respuestas de ejemplo en `tests/Fixtures/vnif/*.xml`, incluida `identified-corrected-name.xml` (nombre corregido por la AEAT).
- Inyecta un `sleep` nulo en `VnifClient` para que los reintentos no esperen.

```php
$transport = (new FakeTransport())->queueRows([
    ['nif' => '00000000T', 'name' => 'LUMINÁ PERALVILLO ZÉFIRA', 'result' => 'IDENTIFICADO'],
]);
$client = new VnifClient($certificate, new VnifOptions(retries: 0), $transport);
```

En Laravel, haz `bind(Transport::class, fn () => $fakeTransport)` en el test y `bind(Certificate::class, ...)` con el certificado generado.

## Cómo verificar la integración

1. `composer check` en el paquete (formateador, PHPStan nivel 8, tests) sigue en verde.
2. `aeat-vnif doctor` en el entorno del usuario devuelve `Todo listo`.
3. Una consulta de prueba **con un NIF que el usuario elija** devuelve un resultado y, si el nombre venía aproximado, `nameWasCorrected()` es `true` y `censusName` trae el nombre censal.
4. `git status` no muestra ningún `.p12`, `.pfx`, `.pem`, `.key` ni `.env`.
5. `grep -r AEAT_VNIF_CERT_PASSWORD` en el repositorio solo encuentra `.env.example` (sin valor) y la documentación.

## Resultados y cómo tratarlos

| `IdentificationResult` | `isIdentified()` | Acción habitual |
| --- | --- | --- |
| `Identified` | sí | Aceptar. Si `nameWasCorrected()`, guardar `censusName`. |
| `NotIdentifiedSimilar` | no | Revisión manual con `censusName`. |
| `NotIdentified` | no | Rechazar o pedir datos de nuevo. |
| `IdentifiedDeregistered` / `IdentifiedRevoked` | sí | Depende del caso de uso; avisar. |
| `NotProcessed` | no | Reintentar más tarde (`failure` en lote, `TransientFailure` en `check()`). |

## Convenciones del código

- PHP 8.2+, `declare(strict_types=1)`, clases `final` y `readonly` donde procede.
- Comentarios en español. Documentación pública (README, CHANGELOG) con ortografía cuidada.
- Formateador: `composer format`. Análisis: `composer analyse`. Tests: `composer test`.
