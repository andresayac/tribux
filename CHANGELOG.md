# Changelog

Todos los cambios notables se documentarán aquí siguiendo Keep a Changelog y Semantic Versioning cuando el proyecto empiece a publicar releases.

## [Unreleased]

### Added

- Repositorio semilla: arquitectura, gobernanza, documentación, OpenAPI y tipos base.
- Renombre definitivo del producto, paquetes Composer y namespaces a Tribux.
- API Laravel 13 con creación/consulta de facturas, estado, idempotencia, request ID y Problem Details.
- Dominio inicial para factura, líneas, terceros, dinero, cantidades e impuestos genéricos.
- Persistencia PostgreSQL, entorno Docker Compose, worker y quality gate automatizado.
- Investigación trazable FEV 1.9, manifiesto reproducible de artefactos y matriz de compliance inicial.
- Cálculo CUFE-SHA384 con fixtures oficiales y perfiles versionados de ambiente/endpoints SOAP.
- Política de firma DIAN v2 y roles XAdES registrados con hashes verificables.
- Descubrimiento de artefactos FEV 1.9, extracción segura y validación XSD local estructurada.
- Modelo de factura FEV 1.9 y generación UBL 2.1 unsigned validada contra el XSD oficial.
- Código de seguridad de software SHA-384 y URL QR DIAN diferenciada por ambiente.
- Aritmética decimal exacta en el core con escala y redondeo explícitos.
- Calculador básico de líneas, impuestos porcentuales y totales de factura.
- Totales tributarios UBL con múltiples subtotales y tarifas.
- Mapper del perfil básico de factura core al documento FEV 1.9 enriquecido.
- Validador Schematron XSLT 3.0 con SaxonJ-HE reproducible y mensajes DIAN estructurados.
- Firmador XAdES-EPES FEV 1.9 con credenciales PEM/PKCS#12, política v2 y pruebas criptográficas/XSD sin claves versionadas.
- Constructor SOAP 1.2 para `SendTestSetAsync` con WS-Addressing, WS-Security X.509 y firma verificable del header `To`.
- Transporte cURL DIAN sobre TLS verificado, con timeouts, límite de respuesta y fallos estructurados sin retries implícitos.
- Parser de `UploadDocumentResponse` y SOAP 1.2 Fault con preservación de mensajes, detalle, HTTP y XML original.
- Cliente de habilitación `DianTestSetClient` que compone envelope, transporte y parser en una llamada reemplazable.
- Cliente `DianStatusClient` para `GetStatus` y parser completo de `DianResponse` sin perder errores, Base64, HTTP ni XML original.
- Naming XML/ZIP FEV 1.9 y empaquetador reproducible con cardinalidad síncrona/asíncrona y secuencia explícita por Q-008.
- Cliente `DianStatusZipClient` para consultar `ZipKey` y conservar íntegro `ArrayOfDianResponse`.
- ADR 0016 con la frontera de orquestación, el modelo de evidencia y la máquina de estados del procesamiento de factura.
- Máquina de estados interna explícita en el core, con transiciones legales, estados terminales y guardas contra saltos de etapa.
- Persistencia de intentos de procesamiento, historial append-only de estados y metadatos de evidencia con hash SHA-256, tamaño y referencia de almacenamiento.
- Posesión exclusiva por factura mediante índice único parcial e intentos numerados, con tomas de posesión separadas para construcción, envío y consulta.
- Perfiles de emisor versionados detrás de un puerto, cargados desde un archivo JSON montado, con validación por campo y ejemplo sintético publicable.
- Proveedores de secretos separados para PIN/clave técnica y credenciales de firma, sobre montajes de un secreto por archivo, con secretos no serializables.
- Almacenamiento de evidencia detrás de un puerto, con referencia derivada del digest, disco configurable y almacenamiento opcional del request SOAP.
- Modelo de autorización de numeración en el core, con rango, vigencia por día calendario y número reservado.
- Codificación explícita del consecutivo de nombre FEV 1.9 (`decimal` o `hexadecimal`), sin valor por defecto mientras Q-008 siga abierta.
- Reserva atómica de números de factura y de secuencias anuales XML/ZIP mediante libros de asientos con índices únicos, idempotente por factura y por dueño.
- Pipeline local de construcción y validación de facturas encoladas, sin red: reserva o valida el número, arma el `InvoiceGenerationContext`, genera el UBL sin firma, valida XSD y ejecuta Schematron, y conserva cada artefacto y cada hallazgo como evidencia.
- Puerto `Fev19DocumentValidator` con adaptador sobre la caja oficial y SaxonJ-HE descubiertos por configuración.
- Cliente `DianNumberingRangeClient` y parser de `GetNumberingRange` con `NumberRangeResponseList` completo, miembros nulos, Fault, HTTP y XML crudo. La clave técnica que devuelve la respuesta queda encapsulada, redactada y no serializable.

### Changed

- El estado interno `awaiting_reconciliation` se añade al enum público de estado de factura para representar un envío de resultado desconocido que nunca debe reenviarse automáticamente.
- **Cambio de contrato pre-alpha en `POST /v1/invoices`.** El request ahora exige `issued_at` con desfase UTC explícito, `payment` (`means_id`, `means_code`, `due_date`), `unit_code` por línea y los datos tributarios y la dirección completa del adquirente. Sin ellos no se puede construir un documento FEV 1.9 sin inventar valores fiscales. `examples/invoice.minimal.json` refleja el contrato nuevo.
- `number` sigue siendo opcional, ahora con regla explícita: si viene, Tribux lo usa y sólo lo valida contra el rango autorizado; si no viene, Tribux reserva el siguiente número de la resolución. No hay un tercer modo implícito.

### Deprecated

- `Tribux\Dian\Contracts\DianGateway`, `SubmissionRequest` y `SubmissionResult`: aplanan la respuesta DIAN y pierden XML crudo, HTTP, Fault y campos opcionales. Se eliminarán cuando aterricen los puertos de envío y consulta de la capa de aplicación.
