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

## Logging

Nunca registrar:

- private key;
- password de certificado;
- secret completo;
- Authorization header;
- payload completo sin política de redacción.

Definir una taxonomía de campos PII/secret antes de observabilidad de producción.
