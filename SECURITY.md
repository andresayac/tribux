# Security Policy

## Reporte responsable

No publiques vulnerabilidades explotables ni secretos en issues públicos. Antes de abrir el repositorio al público, el equipo debe configurar GitHub Private Vulnerability Reporting o un canal equivalente y actualizar aquí el procedimiento.

## En alcance

Especial atención a:

- gestión de claves/certificados;
- separación multiempresa;
- validación XML/firma;
- SSRF/XXE;
- autenticación/autorización;
- idempotencia/replay;
- webhooks;
- supply chain;
- exposición de PII/secrets.

## Secretos

Nunca incluir certificados reales, contraseñas, API keys o dumps con información de clientes en issues, fixtures o PRs.
