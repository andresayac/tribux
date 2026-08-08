# Matriz de compliance — FEV 1.9

**Corte:** 2026-08-08

| Requirement ID | Fuente oficial | Sección/página | Regla resumida | Código | Fixture | Test | Estado |
|---|---|---|---|---|---|---|---|
| FEV19-ENV-001 | Caja FE V19, `TipoAmbiente-2.1.gc` | filas 1/2 | Producción=`1`, pruebas=`2` | `DianEnvironment` | catálogo oficial no redistribuido | `DianEndpointTest` | `locally_validated` |
| FEV19-CUFE-001 | Anexo FEV 1.9, SHA `1b4022…` | 11.1-11.2, pp. 654-659 | Concatenación CUFE en orden y códigos tributarios fijos | `CufeInput`, `CufeCalculator` | `invoice-sale-positive.json` | `CufeCalculatorTest` | `locally_validated` |
| FEV19-CUFE-002 | Anexo FEV 1.9, SHA `1b4022…` | 11.2, pp. 655-659 | Resultado SHA-384 en hexadecimal | `CufeCalculator` | positivo oficial | `CufeCalculatorTest` | `locally_validated` |
| FEV19-CUFE-003 | Anexo FEV 1.9, SHA `1b4022…` | 11.1-11.2, pp. 654-659 | Importes canónicos con dos decimales, sin separadores | `CufeInput` | `invoice-sale-invalid-amount.json` | `CufeCalculatorTest` | `locally_validated` |
| FEV19-SW-001 | Anexo FEV 1.9, SHA `1b4022…` | 11.8 | SHA-384 de ID de software + PIN + número del documento, sin separadores | `SoftwareSecurityCodeCalculator` | `minimal-priced-line.json` | `SoftwareSecurityCodeCalculatorTest` | `locally_validated` |
| FEV19-QR-001 | Anexo FEV 1.9, SHA `1b4022…` | 11.7.1 | URL de consulta QR distinta para habilitación y producción | `DianQrUrl` | CUFE sintético del fixture mínimo | `DianQrUrlTest` | `locally_validated` |
| FEV19-UBL-001 | Anexo FEV 1.9 + Caja FE V19, SHA `2d6002…` | modelo Invoice + `XSD/maindoc` | Serializar modelo versionado a UBL 2.1 con `sts:DianExtensions`, sin inventar firma | `Documents/Fev19/Invoice` | `minimal-priced-line.json` | `UnsignedInvoiceXmlGeneratorTest` | `locally_validated` |
| FEV19-XSD-001 | Caja FE V19, SHA `2d6002…` | `XSD/maindoc` | Validar estructura contra el XSD del tipo documental | `Fev19ArtifactSet`, `DianXsdValidator` | fixtures de motor + ejemplo oficial local | `DianXsdValidatorTest` | `locally_validated` |
| FEV19-SCH-001 | Caja FE V19, SHA `2d6002…` | `Schemes/UBL21`, XSL compilado | Ejecutar reglas Schematron con XSLT 3.0 y conservar severidad/código/texto | `SaxonSchematronValidator` | ejemplo oficial local | `SaxonSchematronValidatorTest` | `locally_validated` |
| FEV19-SOAP-001 | WSDL habilitación/producción | `IWcfDianCustomerServices` | URLs observadas y acciones de operaciones iniciales | `DianEndpoint`, `DianSoapOperation` | hashes WSDL en manifiesto | `DianEndpointTest` | `specified` |
| FEV19-SOAP-002 | Anexo FEV 1.9 + WSDL + guía WS, SHA `1b4022…` / `da576f…` / `0f7862…` | capítulo 7 + policy WSDL + guía 2.6-2.7 | SOAP 1.2, WS-Addressing, Timestamp, token X.509 y firma RSA-SHA256 del header `To` | `WsSecuritySoapEnvelopeBuilder` | `ws-security-profile.json` + certificado efímero | `WsSecuritySoapEnvelopeBuilderTest` | `locally_validated` |
| FEV19-SOAP-003 | WSDL habilitación, SHA `da576f…` | `SendTestSetAsync` | Body document/literal con `fileName`, ZIP Base64 y `testSetId`; action también en Content-Type SOAP 1.2 | `SendTestSetAsyncRequest`, `DianSoapMessage` | ZIP sintético en memoria | `WsSecuritySoapEnvelopeBuilderTest` | `locally_validated` |
| FEV19-SOAP-004 | Anexo FEV 1.9 + WSDL | capítulo 7 + binding HTTPS | TLS >=1.2, verificación de CA/hostname, timeouts y sin redirects/retries implícitos | `CurlDianSoapTransport` | servidor/certificado TLS efímeros | `CurlDianSoapTransportTest` | `locally_validated` |
| FEV19-FILE-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.5.7, pp. 303-304 | Nombre XML por tipo/NIT/PT/año/consecutivo | pendiente | pendiente | pendiente | `research` |
| FEV19-ZIP-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.5.8, pp. 304-305 | Nombre ZIP y cardinalidad sync/async | pendiente | pendiente | pendiente | `research` |
| FEV19-ATT-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.4, pp. 263-270 | Contenedor de documento y eventos | pendiente | pendiente | pendiente | `research` |
| FEV19-SIGN-001 | Anexo FEV 1.9 + Caja FE V19, SHA `1b4022…` / `2d6002…` | 6.5.10, pp. 305-319 + `ejemplificacionIBUA-3.xml` | XAdES-EPES, C14N inclusivo, RSA-SHA256 y tres referencias SHA-384 | `Fev19XadesSigner`, `SigningCredentials` | certificado RSA efímero en memoria | `Fev19XadesSignerTest`, `UnsignedInvoiceXmlGeneratorTest` | `locally_validated` |
| FEV19-SIGN-002 | Política DIAN v2, SHA `74ca0c…` | 5.2, 7, 10.2, 12 | URL/digest SHA-384 de política y roles | `DianSignaturePolicy`, `DianSignerRole` | `policy-v2-sha384.json` | `DianSignaturePolicyTest` | `locally_validated` |

`locally_validated` describe únicamente pruebas locales contra el fixture
publicado. No implica aceptación en habilitación ni certificación de la DIAN.
La contradicción `ProfileID` observada al ejecutar el XSL oficial está registrada
como Q-009 y evita afirmar que el UBL generado pasa Schematron.
