# Seguridad

## Cómo reportar una vulnerabilidad

Si encuentras un problema de seguridad en este paquete, **no abras una incidencia pública**. Escribe a través del formulario de contacto de <https://iberfacil.es> indicando «Seguridad es-aeat-vnif» en el asunto, o usa la función de aviso privado de vulnerabilidades del repositorio en GitHub si está activada.

Incluye, si puedes:

- versión del paquete y de PHP;
- descripción del problema y cómo reproducirlo;
- impacto que estimas.

Se confirma la recepción en un plazo razonable y se coordina la publicación de la corrección contigo. No incluyas nunca certificados, contraseñas ni datos reales de terceros en el reporte.

## Qué cubre

- Manejo del certificado y de su contraseña (conversión a PEM temporal, permisos, borrado).
- Transporte HTTPS con mTLS (verificación de certificado del servidor, sin redirecciones, solo HTTPS).
- Tratamiento del XML recibido (sin DTD, sin entidades externas, sin red).
- Fugas de datos personales o de credenciales en excepciones, logs o salida de consola.

## Qué no cubre

- Vulnerabilidades del propio servicio de la AEAT.
- Configuraciones del proyecto que integra el paquete (por ejemplo, guardar la contraseña en el repositorio).

## Buenas prácticas para quien lo usa

- Guarda el certificado fuera del repositorio y de cualquier carpeta pública, con permisos `0600`.
- La contraseña solo en variables de entorno o en el gestor de secretos del despliegue.
- No pases la contraseña como argumento de línea de comandos.
- No registres el XML enviado ni recibido; usa el hash que expone `VnifRequest`.
- Mantén el paquete actualizado (`composer update iberfacil/es-aeat-vnif`).
