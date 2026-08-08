# Matriz de compliance — FEV 1.9

**Corte:** 2026-08-08

| Requirement ID | Fuente oficial | Sección/página | Regla resumida | Código | Fixture | Test | Estado |
|---|---|---|---|---|---|---|---|
| FEV19-ENV-001 | Caja FE V19, `TipoAmbiente-2.1.gc` | filas 1/2 | Producción=`1`, pruebas=`2` | `DianEnvironment` | catálogo oficial no redistribuido | `DianEndpointTest` | `locally_validated` |
| FEV19-CUFE-001 | Anexo FEV 1.9, SHA `1b4022…` | 11.1-11.2, pp. 654-659 | Concatenación CUFE en orden y códigos tributarios fijos | `CufeInput`, `CufeCalculator` | `invoice-sale-positive.json` | `CufeCalculatorTest` | `locally_validated` |
| FEV19-CUFE-002 | Anexo FEV 1.9, SHA `1b4022…` | 11.2, pp. 655-659 | Resultado SHA-384 en hexadecimal | `CufeCalculator` | positivo oficial | `CufeCalculatorTest` | `locally_validated` |
| FEV19-CUFE-003 | Anexo FEV 1.9, SHA `1b4022…` | 11.1-11.2, pp. 654-659 | Importes canónicos con dos decimales, sin separadores | `CufeInput` | `invoice-sale-invalid-amount.json` | `CufeCalculatorTest` | `locally_validated` |
| FEV19-XSD-001 | Caja FE V19, SHA `2d6002…` | `XSD/maindoc` | Validar estructura contra el XSD del tipo documental | `Fev19ArtifactSet`, `DianXsdValidator` | fixtures de motor + ejemplo oficial local | `DianXsdValidatorTest` | `locally_validated` |
| FEV19-SCH-001 | Caja FE V19, SHA `2d6002…` | `Schemes/UBL21`, XSL compilado | Ejecutar reglas Schematron con XSLT 3.0 | pendiente | pendiente | pendiente | `research` |
| FEV19-SOAP-001 | WSDL habilitación/producción | `IWcfDianCustomerServices` | URLs observadas y acciones de operaciones iniciales | `DianEndpoint`, `DianSoapOperation` | hashes WSDL en manifiesto | `DianEndpointTest` | `specified` |
| FEV19-SOAP-002 | Anexo FEV 1.9 + WSDL | capítulo 7 | SOAP 1.2, WS-Addressing, TLS/WS-Security/X.509 | pendiente | WSDL verificado | pendiente | `research` |
| FEV19-FILE-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.5.7, pp. 303-304 | Nombre XML por tipo/NIT/PT/año/consecutivo | pendiente | pendiente | pendiente | `research` |
| FEV19-ZIP-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.5.8, pp. 304-305 | Nombre ZIP y cardinalidad sync/async | pendiente | pendiente | pendiente | `research` |
| FEV19-ATT-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.4, pp. 263-270 | Contenedor de documento y eventos | pendiente | pendiente | pendiente | `research` |
| FEV19-SIGN-001 | Anexo FEV 1.9, SHA `1b4022…` | 6.5.10, pp. 305-319 | XAdES-EPES, C14N, referencias y propiedades | pendiente | metadata de política | `DianSignaturePolicyTest` | `specified` |
| FEV19-SIGN-002 | Política DIAN v2, SHA `74ca0c…` | 5.2, 7, 10.2, 12 | URL/digest SHA-384 de política y roles | `DianSignaturePolicy`, `DianSignerRole` | `policy-v2-sha384.json` | `DianSignaturePolicyTest` | `locally_validated` |

`locally_validated` describe únicamente pruebas locales contra el fixture
publicado. No implica aceptación en habilitación ni certificación de la DIAN.
