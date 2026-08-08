# Tribux

> Facturación electrónica colombiana, abierta para todos.

**Tribux** es un proyecto comunitario y open source para construir una API y un motor de facturación electrónica para Colombia, orientado a integraciones con las especificaciones y servicios de la DIAN.

> [!IMPORTANT]
> Tribux **no es un producto oficial de la DIAN** ni constituye asesoría tributaria o legal.

## Objetivo

Crear una base técnica reutilizable que pueda ser usada por:

- una persona o pequeña empresa que quiera desplegarla con Docker;
- un software contable, POS, ecommerce o ERP;
- una empresa grande que necesite alta disponibilidad y componentes reemplazables;
- integradores que solo necesiten el motor PHP y no la API completa;
- la comunidad, para estudiar, mantener y extender el soporte de documentos electrónicos colombianos.

## Principios

1. **Open source primero.** El proyecto debe poder usarse sin depender de un servicio comercial centralizado.
2. **Self-hosted primero.** El usuario conserva control sobre credenciales, certificados y datos.
3. **API primero.** OpenAPI es un contrato público de primera clase.
4. **Core independiente del framework.** La lógica de dominio no depende de Laravel.
5. **DIAN como adaptador.** El modelo interno no debe ser una copia del XML DIAN.
6. **Compliance verificable.** Ninguna regla fiscal se implementa sin fuente, versión y pruebas.
7. **Extensible, no sobrearquitecturado.** Monolito modular primero; escalado horizontal y adaptadores cuando hagan falta.
8. **Observabilidad y seguridad por diseño.** No se añaden al final.
9. **Documentación como producto.** Una contribución puede ser código, documentación, fixture o conocimiento tributario verificable.
10. **Cero vendor lock-in.** Persistencia, colas, secretos y almacenamiento deben quedar detrás de contratos cuando tenga sentido.

## Estado

**Foundation / pre-alpha.** El repositorio ya incluye una API Laravel ejecutable, dominio inicial, persistencia idempotente, PostgreSQL, Redis, worker, OpenAPI y quality gates. **Todavía no genera ni transmite facturas válidas a la DIAN.**

## Inicio rápido

Requisitos locales: PHP 8.4/8.5, Composer, Node.js 24 y Docker. Después:

```bash
make setup
make check
make up
```

La API queda en `http://localhost:8080`. Prueba el flujo inicial:

```bash
curl -X POST http://localhost:8080/v1/invoices \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: invoice-demo-0001' \
  --data @examples/invoice.minimal.json
```

Este flujo solo persiste una intención en estado `queued`; no implica emisión ni aceptación DIAN.

## Inicio para humanos

1. Leer [`docs/PROJECT_VISION.md`](docs/PROJECT_VISION.md).
2. Leer [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).
3. Revisar [`docs/WORK_PLAN.md`](docs/WORK_PLAN.md).
4. Si vas a programar, leer [`CONTRIBUTING.md`](CONTRIBUTING.md).
5. Si eres un agente IA, empezar por [`AGENTS.md`](AGENTS.md) y [`docs/AI_AGENT_BRIEF.md`](docs/AI_AGENT_BRIEF.md).

## Estructura inicial

```text
tribux/
├── apps/                  # Aplicaciones desplegables
│   └── api/               # API HTTP Laravel 13
├── packages/
│   ├── core/              # Dominio agnóstico de framework
│   └── dian/              # Puertos/adaptadores DIAN
├── openapi/               # Contrato HTTP
├── examples/              # Payloads y ejemplos de integración
├── infra/                 # Docker e infraestructura de desarrollo
├── docs/                  # Arquitectura, plan, DIAN, seguridad, etc.
└── tests/                 # Fixtures/compliance cross-package (futuro)
```

## Licencia

Apache License 2.0. Consulta [`LICENSE`](LICENSE).

## Alcance jurídico

El proyecto distribuye software y documentación. No constituye asesoría tributaria o legal. Cada organización debe determinar sus obligaciones, su modalidad de habilitación y la vigencia de las especificaciones aplicables.
