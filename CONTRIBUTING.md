# Contribuir

Gracias por querer mejorar este paquete. Estas son las reglas.

## Cómo proponer cambios

1. Abre una incidencia describiendo el problema o la mejora antes de ponerte a programar, salvo que sea algo trivial (una errata, un mensaje).
2. Crea una rama desde `main` con un nombre descriptivo (`fix/parser-nombre-vacio`, `feature/transporte-psr18`).
3. Haz commits pequeños con mensajes claros, en español o en inglés. Sin firmas de herramientas ni coautorías automáticas.
4. Abre un pull request contra `main` explicando qué cambia y por qué. Si cambia el comportamiento público, actualiza el README (español e inglés) y el `CHANGELOG.md`.

## Estilo de código

- PHP 8.2+, `declare(strict_types=1)` en todos los ficheros.
- Clases `final` y `readonly` salvo que haya un motivo para extenderlas.
- El formateador es Laravel Pint con la configuración de `pint.json`: `composer format` antes de enviar.
- PHPStan nivel 8 sin errores: `composer analyse`.
- Comentarios de código en español, breves, solo cuando aportan algo. No expliques lo obvio.
- Nada de dependencias nuevas en el núcleo sin discutirlo antes. El adaptador de Laravel puede depender de `illuminate/*`; el resto, no.

## Pruebas obligatorias

- Todo cambio de comportamiento lleva su test en `tests/Unit`. Todo arreglo de un fallo lleva un test que lo reproduzca.
- Las pruebas **no tocan la red**. Usa `Transport\FakeTransport` y las respuestas de `tests/Fixtures/vnif`.
- Los certificados de prueba se generan en memoria durante el test (`tests/TestCase.php`). No se añade ningún `.p12`, `.pfx`, `.pem` ni `.key` al repositorio, ni siquiera de prueba.
- `composer check` (formateador, PHPStan y tests) tiene que pasar en PHP 8.2, 8.3 y 8.4; la integración continua lo comprueba.

## Datos ficticios, siempre

- En fixtures, tests, documentación y ejemplos solo se usan NIF y nombres inventados: `00000000T`, `00000001R`, `B00000000`, «LUMINA PERALVILLO ZEFIRA», «BRUMALIA FICTICIA SL» o similares claramente falsos.
- Nunca se suben capturas de respuestas reales de la AEAT ni datos de personas o empresas reales.
- Nunca se suben certificados, contraseñas ni ficheros `.env`.

Un pull request que incumpla esto se cierra sin más revisión.

## Versionado

Versionado semántico:

- **Parche** (`1.0.x`): correcciones sin cambio de API.
- **Menor** (`1.x.0`): funcionalidad nueva compatible.
- **Mayor** (`x.0.0`): cambios incompatibles en la API pública, con guía de migración en el `CHANGELOG.md`.

Cada versión se anota en `CHANGELOG.md` antes de etiquetarla.

## Código de conducta

Trato respetuoso y directo. Se discuten ideas, no personas. No se toleran descalificaciones, acoso ni discriminación de ningún tipo. Quien lo incumpla queda fuera del proyecto.
