# ADR 0016 — Orquestación, evidencia y estados del procesamiento de factura

**Estado:** Accepted

## Contexto

`packages/dian` ya construye, valida, firma, empaqueta y transporta documentos
FEV 1.9, y conserva respuestas SOAP completas. `POST /v1/invoices` persiste la
factura en `queued` y devuelve `202`, pero nada consume esa cola: no existe job
de emisión, ni persistencia de intentos, ni almacenamiento de evidencia.

Conectar esas piezas fija contratos difíciles de revertir:

- qué capa decide y qué capa sólo transporta;
- qué se persiste, dónde y con qué garantías de integridad;
- qué transiciones de estado son legales y cómo se evita el doble envío;
- qué hacer cuando la respuesta remota es ambigua.

El puerto histórico `Tribux\Dian\Contracts\DianGateway` devuelve
`SubmissionResult`, que aplana el resultado a `bool $accepted`, una referencia y
mensajes simplificados. Es anterior a los parsers SOAP actuales y pierde el XML
crudo, el status HTTP, el SOAP Fault y los campos opcionales de `DianResponse`.
Conectarlo tal cual destruiría la evidencia que el proyecto necesita.

Q-007 sigue abierta: no existe una taxonomía oficial de códigos terminales o
reintentables. Q-009 impide declarar un documento plenamente válido mientras la
caja v2026 produzca FAD03 con el `ProfileID` del anexo.

## Decisión

### 1. Frontera entre librería, caso de uso, job y adaptador

```text
packages/core        dominio + máquina de estados de factura
packages/dian        construir, validar, firmar, empaquetar, hablar SOAP
apps/api Application casos de uso puros: coordinan puertos, sin facades Laravel
apps/api Infrastructure adaptadores Eloquent, storage y DIAN
apps/api Jobs        envoltorios delgados que sólo resuelven y ejecutan un caso de uso
```

Un job Laravel no contiene lógica de emisión. Serializa únicamente el
`invoiceId` (y, cuando aplique, el `attemptId`); todo lo demás se resuelve del
contenedor al ejecutarse. Ningún DTO serializado puede contener certificados,
contraseñas, PIN ni clave técnica.

### 2. `DianGateway` queda deprecado; los puertos nuevos viven en la aplicación

`DianGateway`, `SubmissionRequest` y `SubmissionResult` se marcan `@deprecated` y
se eliminan cuando aterricen los puertos nuevos. No se conectan al worker.

Los puertos de envío y consulta pertenecen a la capa de aplicación, no a
`packages/dian`: la librería expone clientes concretos y DTO completos, y es la
aplicación quien define la frontera que quiere poder falsear en tests.

```text
TestSetPackageSubmitter  -> Tribux\Dian\Soap\Responses\SendTestSetAsyncResponse
PackageStatusReader      -> Tribux\Dian\Soap\Responses\GetStatusZipResponse
```

Ambos devuelven el DTO completo de la librería. Está prohibido reducirlos a un
booleano, a un string de estado o a un enum interno antes de persistir la
evidencia. La normalización a estado interno ocurre después, en el caso de uso,
y siempre junto al artefacto original.

### 3. Máquina de estados interna explícita

`InvoiceStatus` gana `awaiting_reconciliation` para el caso en que Tribux no
puede afirmar si DIAN recibió el paquete. Las transiciones legales son:

```text
draft                   -> queued
queued                  -> building | permanent_failure
building                -> signed | retryable_failure | permanent_failure
signed                  -> submitted | awaiting_reconciliation | retryable_failure
submitted               -> accepted | rejected | awaiting_reconciliation
awaiting_reconciliation -> submitted | accepted | rejected | permanent_failure
retryable_failure       -> queued | permanent_failure
accepted                -> (terminal)
rejected                -> (terminal)
permanent_failure       -> (terminal)
```

Reglas asociadas:

- `signed -> retryable_failure` sólo cuando el fallo es demostrablemente previo
  a la escritura del request (resolución DNS, handshake TLS, timeout de
  conexión). Cualquier otro fallo de transporte es ambiguo y va a
  `awaiting_reconciliation`;
- `awaiting_reconciliation` nunca dispara un reenvío automático;
- `accepted` y `rejected` requieren evidencia DIAN concluyente. Mientras Q-007
  siga abierta, ninguna clasificación automática de códigos puede producirlas;
- `retryable_failure -> queued` abre siempre un intento nuevo; no reutiliza el
  anterior ni su numeración de documento.

La tabla de transiciones vive en `packages/core` junto al enum, es pura y está
cubierta por tests. La aplicación no puede escribir un estado sin pasar por ella.

### 4. Un intento activo por factura y control de concurrencia

- `invoice_processing_attempts` numera intentos por factura
  (`unique(invoice_id, attempt_number)`);
- un índice único parcial sobre `invoice_id where finished_at is null` garantiza
  como máximo un intento abierto por factura;
- abrir un intento y ejecutar `queued -> building` ocurren en la misma
  transacción, tomando primero `select ... for update` sobre la fila de
  `invoices`;
- un segundo worker que llegue después observa el estado ya movido y termina sin
  trabajo, en vez de duplicar el envío.

El índice parcial es el control real de duplicados; el lock de fila sólo evita
la carrera de lectura previa. PostgreSQL y SQLite soportan ambos, de modo que
los tests rápidos comparten el mismo invariante que producción.

### 5. Puerto de evidencia y separación de almacenamiento

```text
EvidenceStore::put(kind, invoiceId, attemptId, bytes, mediaType): StoredEvidence
StoredEvidence { reference, sha256, bytes, mediaType }
```

El primer adaptador escribe en un disco Laravel dedicado (`evidence`),
configurable y sustituible por almacenamiento compatible con S3 sin tocar el
caso de uso. El filesystem local es aceptable en desarrollo, nunca como
almacenamiento definitivo.

Reparto:

| PostgreSQL | Object storage |
|---|---|
| intentos, etapas, timestamps | XML unsigned y firmado |
| historial append-only de estados | ZIP de envío |
| referencia, `sha256`, tamaño y media type de cada artefacto | resultado XSD y Schematron completos |
| `ZipKey`, status HTTP, categoría/código/mensaje de error local | XML crudo de request y response SOAP |
| proyección consultable de códigos y mensajes DIAN | detalle de SOAP Fault |

La proyección JSON de mensajes DIAN en PostgreSQL existe para consultar y
reconciliar; **no** es la fuente de verdad. La fuente de verdad es siempre el
XML crudo almacenado como evidencia.

Tipos de evidencia iniciales:

```text
unsigned_xml
xsd_unsigned_result
schematron_result
signed_xml
xsd_signed_result
submission_zip
send_test_set_request_xml
send_test_set_response_xml
get_status_zip_response_xml
soap_fault_detail
```

Restricciones de seguridad:

- nunca se almacena como evidencia un P12/PFX, una clave privada, una
  contraseña, el PIN de software ni la clave técnica;
- el request SOAP contiene el documento completo y el certificado público, por
  lo tanto contiene PII: se almacena sólo bajo opción explícita de configuración;
- la política de retención queda pendiente de Q-005 y debe ser configurable.

### 6. Estado interno separado del estado reportado por DIAN

`invoices.status` es exclusivamente estado interno de Tribux. El estado remoto
vive en el intento y en la evidencia (`zip_key`, códigos, mensajes, XML crudo).
No se sobrescribe uno con otro ni se inventa un estado mixto.

`GET /v1/invoices/{id}/status` sigue devolviendo estado interno. Exponer el
estado DIAN normalizado será un corte posterior con su propio cambio de
contrato en `openapi/openapi.yaml`.

### 7. Política inicial de polling

- la consulta de `GetStatusZip` es un job propio, programado y reintentable,
  disparado tras el envío; nunca un `sleep` dentro del worker de emisión;
- intervalo inicial, backoff y número máximo de consultas son política interna
  configurable de Tribux, no reglas DIAN, y se documentan como tales;
- agotar las consultas sin resultado concluyente deja la factura en
  `awaiting_reconciliation` y requiere decisión operativa, no reenvío;
- cada consulta persiste su evidencia aunque no cambie el estado.

### 8. Taxonomía de errores

Cada intento guarda categoría, código y mensaje seguro:

```text
input_validation      payload o contrato insuficiente
configuration         falta emisor, resolución, software, credencial o mapeo
local_validation      XSD o Schematron bloqueante
signing               certificado vencido, clave no correspondiente, fallo de firma
packaging             nombre o ZIP no construibles
transport_safe        fallo previo a escribir el request
transport_ambiguous   fallo posterior a escribir el request
soap_protocol         SOAP Fault o XML de respuesta ilegible
dian_business         la respuesta se parseó y contiene rechazo o errores DIAN
internal              defecto de Tribux
```

Sólo `transport_safe` e `internal` son candidatas a reintento automático.
`configuration` y `input_validation` requieren intervención antes de reintentar,
y `transport_ambiguous` nunca es reintentable: obliga a consultar.

Tomar posesión de una factura es una operación distinta por etapa
(`claimForBuilding`, `claimForSubmission`, `claimForPolling`), de modo que el
job de construcción no puede enviar y el job de consulta no puede reenviar.
Cerrar un intento sin cambiar el estado es legítimo y necesario: una consulta
no concluyente produce evidencia, no un veredicto.

## Consecuencias

- se añaden `invoice_processing_attempts`, `invoice_status_history` e
  `invoice_evidence`; las migraciones publicadas no se reescriben;
- `InvoiceStatus` gana `awaiting_reconciliation`, lo que cambia el enum de
  `openapi/openapi.yaml`. Es un cambio de contrato pre-alpha y se registra en
  `CHANGELOG.md`;
- `--tries=3` de `compose.yaml` deja de ser aceptable para un job que envía:
  hay que separar jobs por etapa y declarar explícitamente qué excepción puede
  repetirse;
- `packages/dian` no adquiere dependencias nuevas; la evidencia y los intentos
  son responsabilidad de la aplicación, así que la librería sigue usable sin
  Laravel;
- la evidencia contiene PII y obliga a resolver retención (Q-005) y aislamiento
  multiempresa antes de exponer la API;
- mientras Q-007 y Q-009 sigan abiertas, el pipeline puede llegar hasta
  `submitted` pero no puede declarar automáticamente `accepted`.
