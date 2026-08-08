# Estado de implementación

**Última actualización:** 2026-08-08

**Versión de producto:** pre-alpha / `0.1.0` de contrato HTTP

Este documento separa lo implementado de lo diseñado y evita confundir infraestructura funcional con compliance DIAN.

## Corte vertical disponible

```text
POST /v1/invoices
  -> validación de request
  -> JSON a dominio Tribux
  -> invariantes genéricas de factura
  -> persistencia PostgreSQL/SQLite
  -> reserva idempotente por issuer + operación + clave + hash
  -> 202 queued
```

También están disponibles `GET /v1/invoices/{id}`, `GET /v1/invoices/{id}/status` y `GET /health`.

## Mapa de código

| Responsabilidad | Ubicación |
|---|---|
| Contrato HTTP | `openapi/openapi.yaml` |
| Dominio PHP puro | `packages/core/src` |
| Puerto de transporte DIAN | `packages/dian/src/Contracts/DianGateway.php` |
| CUFE FEV 1.9 | `packages/dian/src/Cufe` |
| Modelo y UBL de factura FEV 1.9 | `packages/dian/src/Documents/Fev19/Invoice` |
| Código de software y URL QR DIAN | `packages/dian/src/Software` y `packages/dian/src/Qr` |
| Schematron XSLT 3.0 | `packages/dian/src/Validation/Schematron` |
| Perfil de endpoints, mensajes y WS-Security | `packages/dian/src/Soap` |
| Credenciales y firma XAdES-EPES | `packages/dian/src/Signing` |
| Descubrimiento/validación XSD | `packages/dian/src/Artifacts` y `packages/dian/src/Validation` |
| Artefactos oficiales verificables | `resources/dian/fev/1.9/manifest.json` |
| Matriz FEV 1.9 | `docs/compliance/fev-1.9.md` |
| Casos de uso API | `apps/api/app/Application` |
| Adaptadores Laravel/Eloquent | `apps/api/app/Infrastructure` |
| Entrada HTTP | `apps/api/app/Http` y `apps/api/routes/api.php` |
| Persistencia | `apps/api/database/migrations` |
| Tests de dominio/DIAN | `packages/*/tests` |
| Tests HTTP | `apps/api/tests/Feature` |
| Contenedores | `compose.yaml` e `infra/docker/api/Dockerfile` |
| CI | `.github/workflows/seed-quality.yml` |

## Controles implementados

- decimales transportados y almacenados como strings, sin floats;
- suma, multiplicación, porcentajes y cuantización decimal de precisión arbitraria,
  con escala/redondeo explícitos y protección de moneda;
- cálculo básico de importes por línea, impuestos porcentuales agrupados y
  totales de factura para precios antes de impuestos;
- mapper del perfil básico de dominio al modelo FEV 1.9, con contexto enriquecido,
  CUFE, código de software y mapeos tributarios explícitos;
- UUIDv7 interno;
- `Idempotency-Key` con conflicto al cambiar el payload;
- request/correlation ID;
- errores RFC 9457 `application/problem+json` para validación, conflicto y no encontrado;
- paquetes `tribux/core` y `tribux/dian` enlazados por Composer path repositories;
- PHPUnit, PHPStan nivel máximo para paquetes, Pint y Redocly CLI;
- stack local API + worker + PostgreSQL 18 + Redis 8;
- CUFE-SHA384 FEV 1.9 contra el ejemplo oficial positivo y un fixture negativo;
- códigos de ambiente, defaults de endpoints y acciones SOAP iniciales
  documentados y cubiertos por tests;
- manifiesto reproducible con hashes de anexo, caja, política y WSDL oficiales;
- política de firma DIAN v2 verificada por SHA-256/SHA-384 y roles tipados;
- extracción segura de la caja y validación XSD con errores libxml estructurados.
- modelo de factura DIAN FEV 1.9 separado del dominio genérico;
- generación UBL 2.1 determinista sin firma, con `sts:DianExtensions`, código de
  seguridad del software y URL QR por ambiente;
- fixture sintético trazable y prueba local satisfactoria contra el XSD
  `UBL-Invoice-2.1.xsd` de la caja oficial FEV 1.9.
- ejecución Schematron XSLT 3.0 con SaxonJ-HE 12.10 verificable, timeout y
  hallazgos DIAN estructurados;
- firma XAdES-EPES local con credenciales PEM/PKCS#12 encapsuladas, cadena X.509,
  política v2 y validación criptográfica/XSD con certificados efímeros;
- construcción SOAP 1.2 de `SendTestSetAsync` con WS-Addressing, Timestamp,
  BinarySecurityToken y firma RSA-SHA256 del header `To`;
- transporte cURL HTTPS con verificación TLS, timeouts explícitos, límite de
  respuesta, cero redirects/retries implícitos y errores estructurados;
- parser de `UploadDocumentResponse` y SOAP 1.2 Fault que conserva campos DIAN,
  detalle, status HTTP, XML original y errores libxml;
- cliente de librería `DianTestSetClient` que compone firma SOAP, transporte y
  parser mediante dependencias reemplazables;
- cliente `DianStatusClient` y parser de `DianResponse` para `GetStatus`, con
  errores, campos opcionales, Base64 original/decodificado, HTTP y XML crudo;

## No implementado todavía

- autenticación, scopes y resolución segura de tenant/issuer;
- worker de construcción y envío de factura;
- mapeo de descuentos/cargos/retenciones y cierre de hallazgos Schematron;
- requests/parsers/clientes para `SendBillSync`, `GetStatusZip`,
  `GetNumberingRange` y demás operaciones;
- normalización monetaria de descuentos/cargos/retenciones;
- object storage y evidencia de auditoría;
- pruebas en ambiente DIAN de habilitación;
- documentación web local y proceso de release.

La API actual es solo para desarrollo local. No debe exponerse públicamente hasta implementar autenticación y aislamiento multiempresa.

## Próximo corte recomendado

Implementar naming y empaquetado ZIP FEV 1.9 con fixtures trazables, conectar el
worker de factura al pipeline de construcción/firma/envío y preparar un script
reproducible para el primer envío controlado en habilitación. En paralelo,
completar descuentos, cargos y retenciones del dominio.
