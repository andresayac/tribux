# Modelo de seguridad

## Activos sensibles

- certificados y claves privadas;
- contraseñas P12/PFX;
- credenciales/tokens;
- datos tributarios y personales;
- XML emitidos;
- respuestas DIAN;
- API keys/webhook secrets;
- logs/auditoría.

## Amenazas iniciales

- exfiltración de claves privadas;
- tenant breakout;
- duplicación de documentos por retry;
- modificación de documentos emitidos;
- SSRF/XXE en procesamiento XML;
- XML signature wrapping;
- dependency/supply-chain compromise;
- secretos en logs;
- webhooks falsificados/replay;
- deserialización o ZIP bombs.

## Controles mínimos

- XML parsers con network access deshabilitado salvo necesidad explícita;
- límites de tamaño;
- no usar `LIBXML_NOENT` con input no confiable;
- validación estricta de certificados y fechas;
- secrets fuera de Git;
- cifrado en tránsito;
- least privilege;
- audit trail append-oriented;
- rate limiting;
- idempotencia;
- webhook signatures + timestamp/replay window;
- SBOM y dependency scanning;
- releases firmadas (roadmap).

## Manejo de secretos implementado

- configuración no secreta y secretos viajan por caminos distintos: un archivo
  JSON montado para la primera, un secreto por archivo para los segundos;
- el `credential_reference` del emisor se valida como un único segmento de ruta
  seguro antes de tocar el filesystem;
- los secretos se leen en el momento de usarse y no se cachean;
- `IssuerSecrets` rechaza la serialización en vez de redactarla, de modo que un
  job en cola que intente llevar un PIN falla en desarrollo en lugar de filtrar
  en producción;
- `json_encode` de un objeto de secretos devuelve `{}` y `print_r` aparece
  redactado;
- los fallos de OpenSSL se reescriben: conservan la causa, no la contraseña ni
  el material de clave;
- el request SOAP sólo se almacena como evidencia con opción explícita, porque
  contiene el documento completo;
- la respuesta de `GetNumberingRange` contiene la clave técnica de la
  resolución: el valor queda encapsulado, redactado en depuración y no
  serializable, y **no existe un tipo de evidencia para esa consulta**, porque
  guardar su XML crudo guardaría un secreto.

## Logging

Nunca registrar:

- private key;
- password de certificado;
- secret completo;
- Authorization header;
- payload completo sin política de redacción.

Definir una taxonomía de campos PII/secret antes de observabilidad de producción.
