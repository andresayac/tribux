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
- UUIDv7 interno;
- `Idempotency-Key` con conflicto al cambiar el payload;
- request/correlation ID;
- errores RFC 9457 `application/problem+json` para validación, conflicto y no encontrado;
- paquetes `tribux/core` y `tribux/dian` enlazados por Composer path repositories;
- PHPUnit, PHPStan nivel máximo para paquetes, Pint y Redocly CLI;
- stack local API + worker + PostgreSQL 18 + Redis 8.

## No implementado todavía

- autenticación, scopes y resolución segura de tenant/issuer;
- worker de construcción y envío de factura;
- recursos técnicos oficiales registrados y verificados;
- modelo DIAN FEV 1.9, UBL, XSD, CUFE, XAdES y SOAP;
- object storage y evidencia de auditoría;
- pruebas en ambiente DIAN de habilitación;
- documentación web local y proceso de release.

La API actual es solo para desarrollo local. No debe exponerse públicamente hasta implementar autenticación y aislamiento multiempresa.

## Próximo corte recomendado

Completar investigación trazable de FEV 1.9 y, en paralelo, cerrar el modelo de dominio genérico pendiente (direcciones en el contrato, moneda explícita, descuentos/cargos y totales). La primera regla DIAN solo debe entrar después de registrar fuente oficial, versión y fixture según `AGENTS.md`.
