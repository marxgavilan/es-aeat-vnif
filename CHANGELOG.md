# Changelog

Todos los cambios relevantes se anotan aquí. El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/) y el proyecto usa [versionado semántico](https://semver.org/lang/es/).

## [1.1.0] - 2026-09-23

### Added
- `CertificateKind`: el doctor indica si el certificado es de persona física, de representante o de sello.
- README: qué certificado sirve, con una sección para autónomos.

## [1.0.0] - 2026-09-23

### Añadido

- Cliente `VnifClient` con `check()` y `checkBatch()`: troceado en lotes de hasta 10 000, reintentos con espera creciente ante fallos transitorios, casado de respuestas por NIF.
- `TaxpayerName` con nombre y apellidos por separado (orden censal `APELLIDO1 APELLIDO2 NOMBRE`) o razón social; `Taxpayer` con normalización del NIF.
- `CheckResult` con el resultado literal de la AEAT, el enum `IdentificationResult`, el nombre censal devuelto y `nameWasCorrected()`.
- Excepciones tipadas: `TransientFailure`, `DefinitiveFailure`, `ConfigurationException`, `CertificateException`, `InvalidTaxpayer`, todas bajo `AeatVnifException`.
- Certificados `.p12`/`.pfx` y PEM: conversión en memoria a PEM temporales (`0700`/`0600`), clave reexportada cifrada, borrado garantizado, validación de caducidad y errores claros por contraseña incorrecta o fichero inválido.
- Transporte `CurlTransport` (mTLS, solo HTTPS, sin redirecciones, timeout, proxy y bundle CA opcionales) e interfaz `Transport` para inyectar otro. `FakeTransport` para pruebas.
- Ejecutable `bin/aeat-vnif` con `check`, `batch` (CSV), `doctor` y salida `--json`. Contraseña por `AEAT_VNIF_CERT_PASSWORD` o por teclado sin eco; se rechaza como argumento.
- Adaptador para Laravel: proveedor autodescubierto, `config/aeat-vnif.php`, fachada `AeatVnif`, comandos `aeat-vnif:check` y `aeat-vnif:doctor`, eventos por petición.
- Puntos de personalización: `CheckObserver` (con `CallbackObserver`), `ResultCache` (en memoria y PSR-16) y `NameNormalizer`.
- Documentación en español e inglés, guía para agentes (`AGENTS.md`), pruebas sin red, PHPStan nivel 8, Pint y CI para PHP 8.2, 8.3 y 8.4.

[1.1.0]: https://github.com/marxgavilan/es-aeat-vnif/releases/tag/v1.1.0
[1.0.0]: https://github.com/marxgavilan/es-aeat-vnif/releases/tag/v1.0.0
