# Tribux — plan de continuación y relevo técnico

**Última actualización:** 2026-08-08

**Repositorio:** `https://github.com/andresayac/tribux.git`

**Branch activa:** `main`

**HEAD al actualizar este documento:** `d1e5c0a`

**Estado del producto:** pre-alpha; componentes DIAN validados localmente, sin
evidencia de aceptación real en habilitación.

Este archivo es un punto de reanudación para humanos y agentes IA. Resume qué
existe, qué falta y en qué orden conviene continuar. No sustituye las fuentes
oficiales, `AGENTS.md`, los ADR ni la matriz de compliance. Si hay contradicción,
prevalecen las instrucciones del repositorio y la fuente oficial versionada.

---

## 1. Lectura obligatoria antes de continuar

Leer en este orden antes de modificar código:

1. `AGENTS.md`;
2. `docs/AI_AGENT_BRIEF.md`;
3. `docs/PROJECT_VISION.md`;
4. `docs/ARCHITECTURE.md`;
5. `docs/DIAN_RESEARCH_BASELINE.md`;
6. `docs/WORK_PLAN.md`;
7. todos los ADR de `docs/architecture/decisions/`;
8. `CONTRIBUTING.md`;
9. `SECURITY.md`;
10. este archivo;
11. `docs/IMPLEMENTATION_STATUS.md`;
12. `docs/research/DIAN_FEV_1_9.md`;
13. `docs/research/OPEN_QUESTIONS.md`;
14. `docs/compliance/fev-1.9.md`.

Reglas que no se pueden relajar:

- no inventar reglas, códigos, estados ni defaults DIAN;
- cada regla DIAN nueva necesita fuente, versión, fixture, test y trazabilidad;
- `packages/core` no puede depender de Laravel;
- dominio, firma XAdES, envelope WS-Security y transporte SOAP siguen separados;
- no guardar ni registrar certificados reales, claves privadas, contraseñas,
  PIN, clave técnica o payloads sensibles sin una política explícita;
- no convertir respuestas DIAN en un booleano o string opaco;
- no afirmar compliance por pasar XSD, Schematron local o criptografía local;
- no hacer retries automáticos de envíos con resultado remoto ambiguo;
- no acoplar la aplicación a filesystem local como almacenamiento definitivo;
- no introducir microservicios, Kubernetes ni brokers adicionales por anticipación;
- no hacer cambios silenciosos en `openapi/openapi.yaml`.

---

## 2. Estado exacto del repositorio al relevo

La rama estaba limpia y sincronizada con `origin/main` al crear este archivo.
Los últimos cortes verticales publicados son:

```text
d1e5c0a feat(api): enrich the invoice issuance contract
34f3998 docs: describe issuer, secret and evidence mounts
bda637f feat(api): store invoice evidence bytes
4f33201 feat(api): load signing credentials securely
dc7b34d feat(api): resolve versioned issuer profiles
8273cf1 refactor(dian): deprecate the flattening submission gateway
da43d85 feat(api): persist invoice processing attempts
5a71222 feat(core): guard invoice status transitions
506b732 docs(architecture): define invoice processing flow
4b7a190 docs: add detailed continuation plan
```

Último quality gate completo observado, con caja FEV 1.9 y Saxon disponibles:

```text
Paquetes: 106 tests, 498 assertions
API:       65 tests, 300 assertions
Total:    171 tests, 798 assertions
PHPStan:   sin errores
Pint:      sin errores
Lint PHP:  sin errores
OpenAPI:   válido
```

La suite de la API se ejecutó además contra PostgreSQL 18 real, y las
migraciones se verificaron hacia arriba y hacia abajo en PostgreSQL y SQLite.

Los números son una fotografía, no un objetivo fijo. Deben aumentar o cambiar al
añadir funcionalidad; lo importante es que `make check` permanezca verde.

### 2.1 Qué es usable como librería

`packages/dian` ya puede utilizarse sin Laravel para:

- calcular CUFE-SHA384 FEV 1.9;
- calcular el código de seguridad del software;
- generar URL QR por ambiente;
- mapear el perfil básico de `packages/core` al modelo FEV 1.9;
- generar UBL 2.1 determinista sin firma;
- validar XML contra XSD;
- ejecutar Schematron XSLT 3.0 mediante SaxonJ-HE 12.10;
- importar credenciales PEM o PKCS#12 en memoria;
- firmar factura con el perfil XAdES-EPES local implementado;
- construir nombres XML/ZIP FEV 1.9 con secuencia explícita;
- crear ZIP síncrono o asíncrono reproducible;
- construir envelopes SOAP 1.2 con WS-Addressing y WS-Security;
- enviar por cURL con TLS verificado y timeouts explícitos;
- ejecutar `SendTestSetAsync`;
- parsear `UploadDocumentResponse` y SOAP 1.2 Fault;
- consultar `GetStatus` para un documento;
- consultar `GetStatusZip` para el `ZipKey` de un paquete;
- conservar HTTP, XML crudo, códigos, mensajes, errores y binarios Base64.

Esto significa que Tribux tiene dos superficies previstas:

1. **librería PHP:** `tribux/core` + `tribux/dian` para integradores;
2. **API HTTP opcional:** `apps/api`, que debe orquestar esos paquetes.

No hay que convertir todo en HTTP. La librería debe seguir siendo independiente
y publicable por Composer cuando el contrato madure.

### 2.2 Qué hace hoy la API

Rutas existentes:

```text
GET  /health
POST /v1/invoices
GET  /v1/invoices/{invoiceId}
GET  /v1/invoices/{invoiceId}/status
```

`POST /v1/invoices` actualmente:

1. valida el JSON y `Idempotency-Key`;
2. mapea el perfil mínimo al dominio;
3. crea un UUIDv7;
4. persiste la factura y el payload;
5. reserva idempotencia por `issuer_id + operación + key + hash`;
6. devuelve `202` con estado `queued`.

Todavía **no** despacha un job. El contenedor `worker` existe y ejecuta
`queue:work`, pero no hay un job de emisión implementado.

### 2.3 Brecha real del flujo

```text
POST /v1/invoices
  -> validación
  -> dominio
  -> invoices.status = queued
  -> 202
  -> FIN ACTUAL

Componentes disponibles pero aún no orquestados:
  CoreInvoiceMapper
  -> UnsignedInvoiceXmlGenerator
  -> DianXsdValidator
  -> SaxonSchematronValidator
  -> Fev19XadesSigner
  -> validación firmada
  -> Fev19FileNameGenerator
  -> Fev19ZipPackageBuilder
  -> DianTestSetClient
  -> DianStatusZipClient

Ya disponible y probado, sin consumidor todavía:
  InvoiceProcessingRepository
  -> claimForBuilding / claimForSubmission / claimForPolling
  -> advance / recordRemoteExchange / recordEvidence
  -> succeed / fail / requeue
  IssuerProfileProvider
  IssuerSecretProvider / SigningCredentialsProvider
  EvidenceStore
```

El próximo milestone no consiste en crear otro algoritmo aislado. Consiste en
conectar de forma segura y auditable las piezas que ya existen.

---

## 3. Bloqueos y deudas que deben conocerse antes del worker

### 3.1 El payload HTTP ya alcanza, salvo el número — resuelto en P0.4

Desde P0.4, el request exige `issued_at` con desfase UTC explícito, `payment`
(`means_id`, `means_code`, `due_date`), `unit_code` por línea y los datos
tributarios y la dirección completa del adquirente.

Desde P0.3, el perfil de emisor ya resuelve:

- ambiente DIAN;
- resolución/autorización, prefijo y rango autorizado;
- clave técnica de la resolución (secreto);
- software ID y PIN (identidad pública + secreto);
- datos tributarios y dirección completa del emisor;
- `CustomizationID` e `InvoiceTypeCode`;
- mapeo de impuestos del core a códigos/nombres DIAN;
- unidades permitidas;
- política de escala/redondeo;
- zona horaria del emisor.

Sigue faltando una sola entrada: el **número de factura reservado**, que es
P0.5. Un test de `InvoiceIssuanceMapperTest` ya arma un `InvoiceGenerationContext`
real con perfil + detalles + secretos y lo mapea a FEV 1.9, con el número como
único valor simulado.

Deuda consciente: los valores de catálogo DIAN se validan sólo por forma y se
conservan literales. Validarlos contra las listas oficiales es Q-004. Cuando
exista el catálogo versionado, Tribux podrá derivar varios de ellos desde
`identification_type` y volverlos opcionales sin romper el contrato.

### 3.2 `issuer_id` aún no es un contexto de seguridad

El cliente envía `issuer_id` libremente y no existe autenticación. Por ello:

- no usar todavía ese valor como autorización para cargar certificados;
- no exponer la API en una red no confiable;
- un flujo de habilitación inicial debe estar restringido a CLI/entorno local o
  a un emisor configurado explícitamente;
- antes de producción se necesita auth, scopes y resolución de tenant/issuer
  desde el principal autenticado, no solo desde el JSON.

### 3.3 Numeración y secuencias no están implementadas

`CoreInvoiceMapper` exige un número reservado, pero el API lo trata como opcional.
También faltan reservas atómicas para:

- número de factura dentro de resolución/prefijo/rango;
- token anual del nombre XML;
- token anual del nombre ZIP.

`GetNumberingRange` consulta rangos autorizados; no debe asumirse que asigna un
número por factura. La asignación interna debe ser transaccional y tolerar
concurrencia.

Q-008 sigue abierta: el anexo llama hexadecimal a la secuencia, pero el ejemplo
del envío once usa `00000011` y no `0000000B`. `Fev19FileSequence` acepta el token
exacto y deliberadamente no lo incrementa. No añadir un autoincremento hasta
resolver la regla o hacerla una política explícita y trazable.

### 3.4 El puerto `DianGateway` quedó deprecado — resuelto por ADR 0016

`DianGateway`, `SubmissionRequest` y `SubmissionResult` están marcados
`@deprecated` y no deben conectarse. Los puertos de envío y consulta viven en la
capa de aplicación y devuelven los DTO completos de `packages/dian`. Se eliminan
cuando esos puertos aterricen en P0.8.

Sigue vigente la regla: no aplanar `SendTestSetAsyncResponse`,
`GetStatusZipResponse` ni `DianResponse` a un booleano.

### 3.5 La persistencia de intentos existe; el almacenamiento de bytes no

Implementado en P0.2 (`da43d85`):

- `invoice_processing_attempts` con numeración, ambiente, etapa, resultado,
  operación, `ZipKey`, status HTTP, error estructurado y proyección de mensajes
  DIAN;
- `invoice_status_history` append-only con origen y referencia al intento;
- `invoice_evidence` con referencia de almacenamiento, SHA-256, tamaño y media
  type;
- un único intento abierto por factura mediante índice único parcial;
- tomas de posesión separadas por etapa: `claimForBuilding`,
  `claimForSubmission`, `claimForPolling`.

Pendiente todavía:

- elegir el almacenamiento de objetos concreto de producción y la política de
  retención (Q-005); el disco por defecto es local y sólo sirve para desarrollo;
- proyección del estado DIAN hacia la API.

El XML y ZIP no deben vivir únicamente en el filesystem efímero del contenedor.
No guardar secretos dentro de la evidencia.

### 3.6 No hay política segura de retry/polling

El transporte no reintenta por diseño. Un timeout puede ocurrir después de que
DIAN recibió el ZIP. Antes de reenviar se debe consultar estado cuando exista una
referencia conocida y clasificar el fallo.

`GetStatusZip` debe ejecutarse como job programado/reintentable, no mediante un
`sleep` bloqueante. Faltan intervalo, máximo de consultas y estados internos.
Q-007 impide inventar qué códigos DIAN son terminales o reintentables.

### 3.7 Q-009 bloquea declarar un Schematron limpio

El anexo exige el `ProfileID` completo, mientras el XSL compilado v2026 compara
exactamente `DIAN 2.1`. El ejemplo oficial también produce FAD03 con la caja
actual. No cambiar el generador para satisfacer silenciosamente el XSL ni ignorar
el hallazgo.

Hasta resolver Q-009:

- conservar el `ProfileID` basado en el anexo;
- almacenar el resultado Schematron estructurado;
- no presentar un documento con FAD03 como plenamente válido;
- no habilitar un envío automático de producción que omita el fallo;
- buscar aclaración oficial o evidencia controlada en habilitación.

### 3.8 La interoperabilidad criptográfica sigue pendiente

La firma XAdES y el envelope SOAP pasan validaciones locales con certificados
efímeros. Falta probar con credenciales reales de habilitación.

Q-010 permanece abierta:

- WSDL vigente: `ThumbprintSHA1`;
- guía histórica: referencia directa a `BinarySecurityToken`.

El default implementado sigue el WSDL. El perfil alternativo solo se selecciona
explícitamente. Conservar request, respuesta y perfil elegido en la evidencia de
la prueba real, sin guardar la clave o contraseña.

---

## 4. Objetivo inmediato

**Objetivo:** procesar una factura `queued` mediante un job reproducible hasta
obtener un resultado local completo y, después, habilitar un envío controlado a
DIAN habilitación con evidencia auditable.

El trabajo debe dividirse en cortes pequeños. No implementar todo el worker en
un único commit.

---

## 5. Plan prioritario por cortes verticales

### P0.1 — ADR de orquestación, evidencia y estados — **HECHO** (`506b732`)

`docs/architecture/decisions/0016-invoice-processing-orchestration.md` fija la
frontera librería/caso de uso/job/adaptador, la deprecación de `DianGateway`,
la máquina de estados con `awaiting_reconciliation`, el control de un solo
intento abierto, el puerto de evidencia, el reparto PostgreSQL/object storage,
la política de polling sin reenvío y la taxonomía local de errores.

Leerlo antes de cualquier corte de worker.

### P0.2 — Persistencia de intentos, estados y evidencia — **HECHO** (`5a71222`, `da43d85`)

Implementado:

- `packages/core/src/Invoice/InvoiceStatusTransition.php`: tabla de
  transiciones legales, estados terminales y guarda contra saltos de etapa;
- migración `2026_08_08_000001_create_invoice_processing_tables`, reversible y
  verificada en PostgreSQL 18 y SQLite;
- puerto `App\Application\Invoices\Processing\Contracts\InvoiceProcessingRepository`
  y adaptador Eloquent con lock transaccional;
- índice único parcial `invoice_processing_attempts_active_unique` como garantía
  real de un solo intento abierto por factura;
- historial append-only iniciado ya en la creación de la factura (`source: api`);
- metadatos de evidencia con SHA-256, tamaño, media type y referencia;
- `recordRemoteExchange` que nunca borra un `ZipKey` conocido;
- cierre de intento sin cambio de estado para consultas no concluyentes.

Queda fuera de este corte, a propósito:

- el adaptador que escribe los bytes (`EvidenceStore`, ver P0.3);
- cualquier job que use el repositorio (ver P0.9);
- exponer intentos o evidencia por HTTP.

### P0.3 — Configuración del emisor, secretos y evidencia — **HECHO** (`dc7b34d`, `4f33201`, `bda637f`)

Implementado:

- `IssuerProfileProvider` con `JsonFileIssuerProfileProvider` sobre un archivo
  montado (`TRIBUX_ISSUERS_FILE`) y `ArrayIssuerProfileProvider` como doble;
- `IssuerProfile` con tercero emisor, resolución/rango, identidad de software,
  código de proveedor `ppp`, `customizationId`, `invoiceTypeCode`, mapeos
  tributarios, unidades permitidas, política de cálculo, zona horaria,
  `testSetId` y `credential_reference`;
- `IssuerSecretProvider` (PIN + clave técnica) y `SigningCredentialsProvider`
  (P12/PFX o PEM), separados para que la etapa de construcción no cargue una
  clave privada, sobre montajes de un secreto por archivo
  (`TRIBUX_SECRETS_PATH`), con dobles en memoria;
- `EvidenceStore` con `DiskEvidenceStore` sobre disco configurable
  (`TRIBUX_EVIDENCE_DISK`) e `InMemoryEvidenceStore`, referencia derivada del
  digest y opción explícita para el request SOAP;
- `examples/issuer.habilitation.json` sintético, cargado por un test para que
  no se pudra, y verificado como libre de campos secretos;
- documentación de montajes y variables en `docs/DEPLOYMENT.md`, sin valores
  reales.

Queda fuera de este corte, a propósito:

- reserva de numeración y de secuencias XML/ZIP (es P0.5, no P0.3);
- cualquier diseño de HSM o secret manager: los puertos ya permiten añadirlo.

Nota de seguridad: `IssuerSecrets` rechaza la serialización en vez de
redactarla. Un job Laravel debe seguir serializando sólo `invoice_id` y
resolver los secretos al ejecutarse.

### P0.4 — Contrato mínimo de entrada para generar UBL — **HECHO** (`d1e5c0a`)

Decisiones tomadas, en `openapi/openapi.yaml` primero:

- **adquirente:** datos tributarios y dirección viajan en el request. No existe
  todavía un registro de terceros y crearlo aquí habría sido inventar alcance;
- **`unit_code`:** obligatorio por línea. No hay unidad por defecto segura;
- **pago:** `payment.means_id`, `payment.means_code` y `payment.due_date`
  obligatorios;
- **número:** exactamente dos modos. Si viene, Tribux lo usa y sólo lo valida
  contra el rango autorizado; si no viene, Tribux reserva el siguiente. No hay
  un tercer modo implícito;
- **códigos tributarios:** se aceptan los valores oficiales DIAN, validados por
  forma y conservados literales, y cada campo declara en el schema la lista de
  la que proviene. Construir un vocabulario estable de Tribux por encima exige
  el inventario de catálogos de Q-004; hacerlo antes habría sido inventar un
  mapeo;
- **líneas sin impuesto:** permitidas. Tribux no inventa un código de exento o
  excluido, porque eso requiere fuente;
- **zona horaria:** `issued_at` exige desfase UTC explícito y lo aporta el
  cliente, porque el momento de emisión es un hecho de negocio, no el instante
  en que corrió un worker;
- **descuentos y cargos:** fuera de este perfil, siguen en P1.

Los datos DIAN del adquirente llegan al mapper FEV 1.9 por
`App\Application\Invoices\Issuance\InvoiceIssuanceDetails`, no por
`packages/core`: el dominio sólo gana la dirección genérica que ya modelaba.

Los tests se construyen sobre `examples/invoice.minimal.json`, de modo que el
ejemplo publicado no puede desviarse del contrato.

### P0.5 — Numeración y secuencias atómicas

Implementar un slice independiente:

- modelo versionado de autorización/resolución;
- validar prefijo, rango y vigencia contra fuente/configuración;
- reserva transaccional del siguiente número;
- reserva anual separada para XML y ZIP;
- no reutilizar números tras un fallo ambiguo;
- concurrencia probada con dos reservas simultáneas;
- política de secuencia FEV explícita mientras Q-008 siga abierta.

Después implementar `GetNumberingRange` con el WSDL oficial:

- request document/literal;
- modelos de `NumberRangeResponseList` y elementos;
- parser que conserve campos opcionales, HTTP, Fault y XML crudo;
- fixture positivo, nulo, Fault y negativo;
- cliente de librería reemplazable;
- no convertir una consulta de rangos en asignación automática sin validación.

Commits sugeridos:

```text
feat(core): model numbering authorizations
feat(api): reserve invoice numbers atomically
feat(dian): query authorized numbering ranges
```

### P0.6 — Pipeline local de construcción y validación

Crear un caso de uso independiente de Laravel, coordinado después por el job.
Entrada recomendada: ID de factura + perfil de emisor resuelto + hora explícita.

Etapas:

1. cargar factura y payload inmutable;
2. reservar número/secuencias si todavía no existen;
3. construir `InvoiceGenerationContext`;
4. ejecutar `CoreInvoiceMapper`;
5. conservar CUFE;
6. generar XML unsigned;
7. calcular SHA-256 y persistir evidencia;
8. validar XSD unsigned;
9. ejecutar Schematron y persistir todos los mensajes;
10. detener el flujo con error estructurado ante validación bloqueante;
11. no llamar a red.

Tests:

- happy path con fixture sintético;
- falta de número/configuración;
- XSD inválido;
- tax mapping ausente;
- cantidad de unidades distinta a líneas;
- moneda incompatible;
- error Schematron conservado sin aplanar;
- reejecución idempotente de una etapa ya persistida.

Q-009 debe permanecer visible. Un test no puede marcar como válido un documento
con FAD03 simplemente para desbloquear el worker.

Commit sugerido:

```text
feat(api): build and validate queued invoices
```

### P0.7 — Firma, validación firmada y empaquetado

Una vez verde el pipeline unsigned:

1. resolver `SigningCredentials` solo dentro del worker;
2. firmar con hora explícita y rol correcto;
3. validar vigencia y correspondencia certificado/clave;
4. persistir XML firmado + hash;
5. volver a validar XSD firmado;
6. ejecutar validación adicional requerida sin modificar el XML firmado;
7. construir nombres con `Fev19FileNameGenerator`;
8. empaquetar con `Fev19ZipPackageBuilder`;
9. persistir ZIP + hash;
10. transición controlada `building -> signed`.

Nunca reformatear el XML después de firmarlo. Nunca guardar P12/PFX o contraseña
como evidencia.

Tests:

- certificado efímero;
- certificado vencido/no vigente;
- clave no correspondiente;
- XML firmado XSD-valid con caja oficial cuando esté disponible;
- ZIP contiene exactamente el XML firmado;
- nombres comparten emisor/proveedor/año;
- repetición produce evidencia coherente o detecta intento previo.

Commit sugerido:

```text
feat(api): sign and package queued invoices
```

### P0.8 — Adaptador de envío de test set sin red real

Antes de habilitación real, crear un fake fiel de la frontera externa:

- puerto específico de envío de paquete de habilitación;
- adaptador sobre `DianTestSetClient`;
- fake que devuelve `UploadDocumentResponse`, Fault y errores de transporte;
- persistir `rawXml`, HTTP, Fault, mensajes y `ZipKey`;
- transición `signed -> submitted` solo cuando la evidencia de envío lo permita;
- nunca interpretar `ZipKey` como aceptación;
- job de consulta separado sobre `DianStatusZipClient`;
- no usar `sleep` dentro del worker;
- no reenviar automáticamente tras timeout ambiguo.

Casos mínimos:

- respuesta con `ZipKey`;
- respuesta sin `ZipKey` y mensajes;
- SOAP Fault HTTP 500;
- XML malformado;
- timeout de conexión;
- timeout total ambiguo;
- respuesta demasiado grande;
- consulta con lista de `DianResponse` y miembros nulos.

Commits sugeridos:

```text
feat(api): submit signed test-set packages
feat(api): poll test-set package status
```

### P0.9 — Job Laravel y despacho post-commit

Cuando el caso de uso sea comprobable con fakes:

- crear `ProcessInvoiceJob` con solo `invoiceId` serializado;
- despachar después del commit que crea la factura;
- definir cola específica y timeout basado en medición;
- evitar doble procesamiento mediante lock/attempt activo;
- mantener etapas idempotentes;
- registrar correlation ID sin guardar secretos;
- actualizar estado e historial en cada transición;
- configurar manejo de job fallido sin convertir automáticamente todo en retry;
- revisar `--tries=3` del `compose.yaml`: hoy sería peligroso para envíos
  ambiguos si el job completo se repite. Separar jobs por etapa o controlar
  explícitamente qué excepción puede repetirse.

Tests:

- `POST` crea factura y despacha exactamente un job after-commit;
- replay de idempotencia no despacha otra factura;
- job duplicado no realiza un segundo envío;
- fallo local seguro puede reintentarse;
- fallo remoto ambiguo queda pendiente de reconciliación/consulta;
- endpoint de estado refleja transiciones internas.

Commit sugerido:

```text
feat(api): process invoices asynchronously
```

### P0.10 — Comando reproducible de habilitación

Crear una herramienta explícita, no un test que corra en cada PR. Puede ser un
comando Artisan o script pequeño que use los casos de uso existentes.

Requisitos:

- ambiente fijo a habilitación salvo opción explícita futura;
- `--dry-run` por defecto;
- `--send` explícito para red real;
- carga de P12/PFX desde secret mount o stdin seguro, no desde Git;
- contraseña fuera de argumentos visibles del proceso cuando sea posible;
- `testSetId`, software ID/PIN, resolución y clave técnica provistos por el
  usuario habilitado;
- imprimir IDs, hashes y rutas/referencias de evidencia, no secretos/XML completo;
- elegir `ThumbprintSHA1` por default y registrar el perfil;
- permitir `BinarySecurityToken` solo como prueba explícita para Q-010;
- conservar request/response SOAP de forma segura;
- nunca actualizar el manifiesto oficial automáticamente.

Definition of Done:

- documentación paso a paso desde un clone limpio;
- dry-run genera, valida, firma y empaqueta sin red;
- envío real conserva HTTP/raw XML/Fault/ZipKey;
- consulta posterior usa `GetStatusZip`;
- cualquier dato real en fixtures públicos está anonimizado o reemplazado;
- la prueba no se ejecuta en CI general.

Commit sugerido:

```text
feat(cli): add controlled DIAN habilitation flow
```

### P0.11 — Primera evidencia real de habilitación

Esta tarea necesita coordinación humana y credenciales DIAN. No se puede cerrar
solo con código local.

Ejecutar y documentar al menos:

1. un envío mínimo controlado;
2. una respuesta aceptada o su estado real documentado;
3. un rechazo controlado que no exponga datos sensibles;
4. consulta por `ZipKey`;
5. perfil WS-Security utilizado;
6. hashes de XML/ZIP/request/response;
7. códigos y mensajes originales;
8. tiempos y endpoint;
9. resultado de Q-003, Q-009 y Q-010;
10. fixture de regresión anonimizado cuando sea legal publicarlo.

Solo después se puede cambiar una fila relevante de “localmente validado” a un
estado que refleje interoperabilidad real. No usar la palabra “certificado por
DIAN” salvo que exista base oficial para hacerlo.

Commit sugerido:

```text
test(compliance): record habilitation evidence
```

---

## 6. Trabajo posterior al primer envío de habilitación

### P1 — Dominio y factura completa

- descuentos por línea/documento;
- cargos por línea/documento;
- retenciones;
- líneas gratuitas y precios de referencia;
- impuestos excluidos/exentos según fuente;
- anticipos y redondeos cuando apliquen;
- múltiples medios/condiciones de pago si el perfil lo exige;
- fechas y zonas horarias como conceptos explícitos;
- direcciones y terceros completos en core sin copiar UBL;
- estados y transiciones formalizados mediante ADR;
- property-based tests para aritmética/límites.

Cada regla monetaria debe declarar escala y `DecimalRoundingMode`. No usar
`float`.

### P1 — Operaciones DIAN restantes del primer flujo

- `SendBillSync` usando el `DianResponse` ya normalizado;
- `GetNumberingRange` y sus tipos de respuesta completos;
- evaluar `SendBillAsync` solo si el flujo/documentación lo requiere;
- taxonomía de fallos/reconciliación basada en evidencia;
- política de retry por operación, no genérica;
- circuit breaker solo después de medir necesidad.

### P1 — Seguridad de API y multiempresa

Antes de exposición pública:

- autenticación;
- API keys o tokens con scopes;
- tenant/issuer derivado del principal;
- autorización en todas las consultas por ID;
- rotación/revocación de credenciales;
- rate limiting;
- auditoría de acciones administrativas;
- pruebas de aislamiento entre dos emisores;
- canal privado para vulnerabilidades en GitHub;
- revisión de PII en errores/logs.

### P1 — Entrega y ciclo operativo

- AttachedDocument basado en sección 6.4;
- entrega al adquirente;
- representación gráfica PDF como componente separado;
- QR visible en representación;
- webhooks firmados e idempotentes;
- dead-letter/reconciliación;
- política de retención configurable;
- observabilidad con métricas/trazas sin secretos.

### P2 — Más tipos documentales

En orden sugerido después de estabilizar factura:

1. nota crédito;
2. nota débito;
3. AttachedDocument/eventos necesarios;
4. documento soporte;
5. documento equivalente;
6. nómina;
7. RADIAN.

Cada familia necesita su propia matriz de compliance, fixtures y versión.

### P2 — Comunidad y release

- documentación local publicable y ADR de herramienta;
- branch protection de GitHub;
- proceso de release/SemVer;
- publicación de paquetes Composer cuando el API PHP sea estable;
- changelog por comportamiento observable;
- imágenes multi-arch;
- SBOM y firma de releases;
- guía de backups/restauración;
- SDKs solo después de estabilizar OpenAPI.

---

## 7. Preguntas abiertas que pueden bloquear decisiones

Fuente completa: `docs/research/OPEN_QUESTIONS.md`.

| ID | Impacto inmediato | Acción segura |
|---|---|---|
| Q-001 | Redistribución de caja/anexo | Seguir descargando por URL+hash; no versionar binarios |
| Q-002 | Operaciones exactas por flujo | Implementar solo operaciones confirmadas por WSDL/anexo y evidencia |
| Q-003 | Firma XAdES aceptada remotamente | Probar con certificado real en habilitación y conservar evidencia |
| Q-004 | Catálogos/versionado | Inventariar dependencias por campo antes de crear un catálogo genérico |
| Q-005 | Evidencia y retención | Diseñar política configurable y buscar fuente jurídica/técnica |
| Q-006 | Posición jurídica open source | Mantener disclaimer; solicitar revisión independiente |
| Q-007 | Estados/retries | No inferir códigos terminales; preservar respuesta y reconciliar |
| Q-008 | Incremento de nombres | Mantener token explícito; no autoincrementar silenciosamente |
| Q-009 | `ProfileID` vs XSL v2026 | No ignorar FAD03 ni cambiar el anexo; buscar aclaración/evidencia |
| Q-010 | Referencia X.509 SOAP | Default WSDL Thumbprint; probar ambos explícitamente en habilitación |

Cuando aparezca una duda nueva, añadir Q-011 en adelante con:

- pregunta;
- por qué importa;
- fuente consultada;
- hipótesis marcada como hipótesis;
- validación pendiente;
- estado.

---

## 8. Definition of Done del milestone “primera factura de habilitación”

No marcar la Fase 1 terminada hasta cumplir todo:

- [ ] clone limpio y setup documentado;
- [ ] artefactos DIAN/Saxon descargados y verificados por hash;
- [ ] emisor de habilitación configurado sin secretos en Git;
- [ ] resolución, software, PIN, clave técnica, certificado y `testSetId`
      resueltos mediante proveedores seguros;
- [ ] número y secuencias reservados atómicamente;
- [ ] payload API suficiente o comando con fixture completo trazable;
- [ ] XML unsigned generado de forma determinista;
- [ ] XSD unsigned válido;
- [ ] Schematron ejecutado y Q-009 resuelta o documentada sin falsear validez;
- [ ] CUFE reproducible;
- [ ] firma XAdES generada con certificado de habilitación;
- [ ] XML firmado validado y no modificado después;
- [ ] nombre XML y ZIP correctos;
- [ ] ZIP contiene el documento esperado;
- [ ] `SendTestSetAsync` enviado una sola vez por intento;
- [ ] HTTP, XML crudo, Fault/mensajes y `ZipKey` preservados;
- [ ] `GetStatusZip` consultado sin polling bloqueante;
- [ ] estado interno separado de estado DIAN;
- [ ] evidencia con hashes y timestamps;
- [ ] fixture aceptado y rechazo controlado cuando sea publicable;
- [ ] pruebas unitarias/integración verdes;
- [ ] guía reproducible para otro contribuidor;
- [ ] compliance matrix y changelog actualizados;
- [ ] ninguna clave, contraseña o PII sensible en commit/log/output.

---

## 9. Comandos para reanudar en una máquina limpia

### 9.1 Código y dependencias

```bash
git pull --ff-only origin main
git status --short --branch
make setup
```

Requisitos conocidos:

- PHP 8.4 o 8.5;
- Composer;
- Node.js 24;
- Docker;
- Java para Schematron/Saxon;
- extensiones PHP declaradas por Composer, incluida `ext-zip`.

### 9.2 Artefactos oficiales locales

```bash
composer dian:fetch-fev19
composer dian:extract-fev19
composer tools:fetch-saxon
composer tools:extract-saxon
```

Rutas estándar resultantes:

```text
var/dian/fev/1.9/toolbox
var/tools/saxon/12.10/dist
```

No actualizar hashes del manifiesto automáticamente si una descarga cambia.
Detenerse, comparar la fuente y registrar el nuevo corte.

### 9.3 Quality gate completo

Linux/macOS:

```bash
export TRIBUX_FEV19_TOOLBOX="$PWD/var/dian/fev/1.9/toolbox"
export TRIBUX_SAXON_HOME="$PWD/var/tools/saxon/12.10/dist"
make check
```

PowerShell:

```powershell
$env:TRIBUX_FEV19_TOOLBOX = (Resolve-Path 'var/dian/fev/1.9/toolbox').Path
$env:TRIBUX_SAXON_HOME = (Resolve-Path 'var/tools/saxon/12.10/dist').Path
make check
```

Sin esas variables algunos tests de integración oficial se omiten. Para una
revisión de compliance local se deben definir y verificar que no haya skips
inesperados.

### 9.4 Infraestructura local

```bash
make infra-up
make up
docker compose ps
docker compose logs -f api worker
```

La API queda por defecto en `http://localhost:8080`.

### 9.5 Flujo HTTP actual

```bash
curl -X POST http://localhost:8080/v1/invoices \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: invoice-demo-0001' \
  --data @examples/invoice.minimal.json
```

Esperar `202 queued`; actualmente no habrá envío DIAN hasta implementar el job.

---

## 10. Estrategia de commits y documentación

El usuario pidió avanzar mediante commits pequeños. Mantener Conventional
Commits, ejecutar pruebas proporcionales antes de cada commit y `make check`
antes de publicar un corte completo.

Para una regla DIAN, el mismo commit o una secuencia atómica debe incluir:

```text
fuente oficial/versionada
-> fixture positivo
-> fixture negativo cuando aplique
-> implementación
-> test
-> error entendible
-> docs públicas si cambia contrato
-> compliance matrix
-> CHANGELOG si es observable
```

No mezclar en un commit:

- refactor amplio no relacionado;
- cambios de API y migraciones sin tests;
- actualización de artefactos oficiales sin investigación;
- secretos o fixtures reales no anonimizados.

Al finalizar cada corte:

1. `git diff --check`;
2. tests focalizados;
3. PHPStan/lint;
4. `make check` si el corte está completo;
5. revisar staged diff;
6. commit convencional;
7. push autorizado a `origin/main` según el flujo actual;
8. actualizar este archivo si cambia la prioridad o se cierra un bloqueo.

---

## 11. Orden recomendado para la próxima sesión

Empezar exactamente así:

1. verificar que `HEAD` incluye `d1e5c0a` o commits posteriores;
2. ejecutar el quality gate completo con artefactos oficiales;
3. leer ADR 0016 antes de tocar el flujo de procesamiento;
4. abordar numeración/secuencias (P0.5);
5. conectar pipeline local sin red (P0.6);
6. firmar/empaquetar (P0.7);
7. integrar envío/consulta con fakes (P0.8);
8. habilitar job Laravel (P0.9);
9. crear comando controlado de habilitación (P0.10);
10. coordinar credenciales humanas y primera evidencia real (P0.11).

No comenzar la siguiente sesión implementando directamente un POST a DIAN desde
el controller ni desde un job.

Intentos, perfiles, secretos, evidencia y contrato de entrada ya existen. La
única entrada que falta para construir un `InvoiceGenerationContext` real es el
número reservado, y con él las secuencias anuales de los nombres XML y ZIP. Eso
es P0.5, y hasta que esté no tiene sentido empezar P0.6.

Recordatorio para P0.5: Q-008 sigue abierta. `Fev19FileSequence` acepta el token
exacto y no lo incrementa a propósito. No añadir autoincremento sin resolver la
regla o convertirla en política explícita y trazable.

---

## 12. Resultado esperado a medio plazo

La experiencia objetivo es:

```text
Usuario de librería PHP
  -> configura perfil/credenciales mediante puertos
  -> construye/valida/firma/empaqueta
  -> usa clientes DIAN explícitos
  -> conserva respuestas completas

Usuario de API
  -> POST /v1/invoices
  -> 202 + recurso consultable
  -> worker seguro y auditable
  -> GET status con estado interno + referencia DIAN normalizada
  -> evidencia protegida
```

Tribux debe ser usable como librería **y** como API self-hosted. La API es una
capa de conveniencia y operación; no debe apropiarse del dominio ni hacer que la
librería dependa de Laravel.
