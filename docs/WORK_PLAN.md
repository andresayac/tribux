# Plan de trabajo

Este plan está pensado para ser ejecutado por humanos y agentes IA en PRs pequeños y verificables.

## Fase 0 — Foundation

**Objetivo:** convertir este seed en un repositorio ejecutable y con calidad automatizada.

### Entregables

- [x] Confirmar nombre definitivo y namespace: Tribux / `Tribux\\*` / `tribux/*`.
- [x] Inicializar repositorio GitHub: `andresayac/tribux`.
- [ ] Configurar branch protection; `CODEOWNERS` ya está versionado.
- [x] Añadir CI: lint, static analysis, unit tests, OpenAPI validation.
- [x] Crear entorno reproducible con Docker Compose.
- [x] Scaffold real de `apps/api` en Laravel 13.
- [x] Configurar paquetes locales `packages/core` y `packages/dian` por Composer path repositories.
- [x] Añadir PHPUnit y PHPStan.
- [x] Crear Makefile estable.
- [ ] Publicar documentación local (MkDocs/Docusaurus/VitePress: decidir vía ADR).
- [x] Establecer actualización de dependencias y auditoría básica con Dependabot/Composer audit.

### Salida

`make setup && make test` funciona en una máquina limpia.

---

## Fase 1 — Dominio mínimo + primera factura de habilitación

**Objetivo:** enviar una factura mínima reproducible al ambiente DIAN de habilitación.

### 1.1 Investigación técnica trazable

- [x] Descargar/registrar Anexo Técnico FEV vigente.
- [x] Inventariar XSD, XSLT/Schematron, catálogos y ejemplos oficiales.
- [x] Identificar WSDL/endpoints de habilitación y producción.
- [x] Documentar métodos SOAP necesarios para la primera factura.
- [x] Confirmar algoritmo y composición de CUFE.
- [ ] Confirmar perfil completo de firma y canonicalización (XAdES-EPES/C14N confirmados; política/hash pendientes).
- [x] Documentar ZIP/naming/AttachedDocument si aplica al flujo.
- [x] Crear matriz `requisito -> fuente -> implementación -> test`.

### 1.2 Modelo de dominio

- [ ] `Invoice` inmutable por etapas relevantes.
- [ ] `Party`, `TaxIdentifier`, `Address`.
- [ ] `Money`, `Currency`, `Quantity`.
- [ ] `InvoiceLine`, descuentos/cargos mínimos.
- [ ] `Tax`, `TaxRate` y totales.
- [ ] `Numbering`/prefijo.
- [ ] Fechas y zona horaria explícitas.

### 1.3 API v1 mínima

- [x] `POST /v1/invoices` (persistencia de intención `queued`, sin DIAN todavía).
- [x] `GET /v1/invoices/{id}`.
- [x] `GET /v1/invoices/{id}/status`.
- [x] Header `Idempotency-Key`.
- [x] RFC 9457 / Problem Details para errores implementados.
- [x] Correlation/request ID.

### 1.4 Generación y validación

- [x] JSON -> Domain para el contrato mínimo actual.
- [ ] Domain -> DIAN document model.
- [ ] DIAN model -> UBL XML.
- [ ] Validación XSD local.
- [ ] Validaciones adicionales documentadas.
- [x] CUFE con fixtures oficiales positivo/negativo.
- [ ] Firma digital con certificado de pruebas.

### 1.5 Transporte DIAN

- [ ] Cliente SOAP aislado.
- [ ] Timeouts explícitos.
- [ ] Retries solo para fallos seguros/reintentables.
- [ ] Circuit breaker opcional, medido antes de introducirlo.
- [ ] Normalización de respuestas.
- [ ] Persistencia de evidencia técnica necesaria.

### 1.6 E2E

- [ ] Script reproducible de habilitación.
- [ ] Fixture mínimo aceptado.
- [ ] Fixture rechazado controlado.
- [ ] Documentación paso a paso.

### Salida

Un contribuidor puede obtener una aceptación/rechazo real del ambiente de habilitación y entender por qué.

---

## Fase 2 — Flujo operativo base

- [ ] Nota crédito.
- [ ] Nota débito.
- [ ] AttachedDocument / artefactos requeridos.
- [ ] Representación gráfica PDF como componente separado.
- [ ] QR.
- [ ] Webhooks firmados.
- [ ] Retry policy + dead-letter strategy.
- [ ] Estados de documento formalizados.
- [ ] Multiempresa seguro.
- [ ] API keys/scopes.
- [ ] auditoría y retención configurables.

---

## Fase 3 — Operación comunitaria y enterprise

- [ ] Object storage S3-compatible.
- [ ] Secret provider abstraction.
- [ ] OpenTelemetry traces/metrics.
- [ ] Docker image multi-arch.
- [ ] SBOM y firma de releases.
- [ ] Helm chart opcional.
- [ ] Performance/load tests.
- [ ] Guía HA y disaster recovery.
- [ ] SDKs generados después de estabilizar OpenAPI.

---

## Fase 4 — Extensiones del ecosistema

Priorizar según demanda y contribuidores:

- [ ] Documento soporte.
- [ ] Documento equivalente electrónico.
- [ ] Nómina electrónica.
- [ ] RADIAN/eventos de recepción.
- [ ] Conectores ERP/POS/ecommerce.

Cada extensión debe tener su propio compliance matrix y versión técnica.

---

## Regla de priorización

Cuando compitan features, priorizar en este orden:

1. corrección/compliance;
2. seguridad;
3. reproducibilidad;
4. simplicidad de uso;
5. observabilidad;
6. rendimiento medido;
7. nuevas features.
