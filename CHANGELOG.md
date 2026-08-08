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
