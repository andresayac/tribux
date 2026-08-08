# Arquitectura

## Estilo

Tribux usa **arquitectura hexagonal / ports and adapters** con DDD pragmático. El objetivo es proteger el dominio y permitir que infraestructura y DIAN evolucionen sin contaminarlo.

## Capas

### Core Domain — `packages/core`

Responsable de conceptos estables:

- dinero y monedas;
- partes/terceros;
- líneas de documento;
- impuestos como conceptos de dominio;
- estados y eventos del ciclo de vida;
- identificadores internos.

No puede depender de Laravel, Eloquent, Redis, HTTP, SOAP o SDK DIAN.

### DIAN — `packages/dian`

Responsable de interoperabilidad:

- modelos de borde requeridos por DIAN;
- UBL;
- CUFE y otros identificadores técnicos;
- firma;
- validación local;
- catálogos DIAN;
- SOAP/WCF;
- normalización de respuestas.

### Application — `apps/api`

Responsable de casos de uso:

- `IssueInvoice`;
- `GetInvoice`;
- `IssueCreditNote`;
- `RetrySubmission`;
- `ConfigureIssuer`;
- webhooks/jobs.

Coordina transacciones y puertos. No implementa reglas XML en controllers.

## Puertos clave

```text
DianGateway
DocumentRepository
IdempotencyStore
ObjectStorage
SigningKeyProvider
Clock
EventBus
AuditLog
```

No todos deben existir el primer día. Introducir un puerto cuando exista una frontera real o al menos dos implementaciones previsibles con valor.

## Flujo de emisión

```text
HTTP request
  -> validate request shape
  -> resolve issuer
  -> reserve idempotency key
  -> map to domain command
  -> domain validation
  -> persist intent/document
  -> enqueue processing

worker
  -> build DIAN representation
  -> calculate technical identifiers
  -> render XML
  -> validate locally
  -> sign
  -> validate signed document
  -> submit through DianGateway
  -> normalize DIAN response
  -> update immutable audit trail/state
  -> emit domain/integration event
  -> deliver webhook
```

## Sincronía

La creación debe ser asíncrona por defecto cuando exista comunicación externa. La API puede devolver `202 Accepted` con recurso consultable. Si más adelante se ofrece modo síncrono, debe ser una capa de conveniencia con timeout acotado, no un modelo diferente.

## Idempotencia

`Idempotency-Key` debe estar asociado a:

- tenant/issuer;
- operación;
- hash del request normalizado;
- respuesta/recurso resultante;
- expiración configurable.

Reutilizar una clave con payload distinto debe producir conflicto, no una segunda factura.

## Estados

Propuesta inicial; debe refinarse mediante ADR antes de producción:

```text
draft
queued
building
signed
submitted
accepted
rejected
retryable_failure
permanent_failure
cancelled_by_business_process (si aplica conceptualmente, no como borrado DIAN)
```

Separar **estado interno** de **estado reportado por DIAN**.

## Persistencia

- PostgreSQL para metadatos/transacciones.
- Object storage para XML, ZIP, representaciones y evidencias grandes.
- Redis inicialmente para cache/queue/idempotencia cuando corresponda.

No almacenar documentos legales solo en filesystem efímero del contenedor.

## Multiempresa

Todo recurso sensible debe estar aislado por `tenant_id`/`issuer_id`. Nunca inferir tenant únicamente desde parámetros del cliente. Auth + scopes determinan contexto.

## Seguridad de certificado

El core nunca recibe una contraseña global. `SigningKeyProvider` abstrae material criptográfico. Implementaciones posibles:

- archivo/secret montado para PyME;
- secret manager;
- HSM/KMS si el mecanismo de firma requerido lo permite.

## Versiones independientes

Mantener por separado:

- versión Tribux (`0.x` durante pre-alpha);
- versión de API (`/v1`);
- versión de cada especificación DIAN (`FEV 1.9`, futura, etc.).

Una actualización DIAN no implica automáticamente un breaking change público.
