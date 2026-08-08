# Preguntas abiertas

No convertir una hipótesis en código de compliance hasta cerrarla con fuente/prueba.

| ID | Pregunta / por qué importa | Fuente consultada | Hipótesis explícita | Validación pendiente | Estado |
|---|---|---|---|---|---|
| Q-001 | ¿Qué artefactos FEV 1.9 pueden redistribuirse? Define el packaging. | Página DIAN, ZIP y avisos internos | **Hipótesis:** descargar localmente es más seguro que redistribuir. | Revisión legal/licencia oficial. | Abierta |
| Q-002 | ¿Qué operaciones SOAP exige cada flujo? Define el puerto. | Anexo 1.9 cap. 7, guía y WSDL | **Hipótesis:** la primera factura necesita las cinco operaciones documentadas; notas/eventos ampliarán el contrato. | Capturas reales de habilitación y matriz para notas/eventos. | Parcial |
| Q-003 | ¿El perfil XAdES local es aceptado sin diferencias por habilitación? Un detalle criptográfico puede causar rechazo. | Anexo 1.9, política v2, `ejemplificacionIBUA-3.xml`, XSD oficial y verificación OpenSSL | **Hipótesis:** el perfil local C14N/RSA-SHA256/SHA-384 coincide con el validador remoto. | Firmar con certificado de habilitación, enviar y conservar respuesta; no modificar XML tras canonicalizar. | Parcial: estructura y criptografía validadas localmente |
| Q-004 | ¿Qué listas deben versionarse y cómo se actualizan? Evita códigos obsoletos. | Caja FE V19 (36 genericode, 39 XLSX) | **Hipótesis:** catálogo versionado detrás de un puerto. | Fecha/vigencia y dependencias por lista. | Abierta |
| Q-005 | ¿Qué evidencia conservar y por cuánto tiempo? Impacta storage/auditoría. | Sin fuente concluyente revisada | **Hipótesis:** conservar XML y respuesta íntegra con política configurable. | Norma oficial por actor y documento. | Abierta |
| Q-006 | ¿Cómo describir jurídicamente el uso open source? Impacta gobernanza. | Modalidades DIAN revisadas de forma inicial | **Hipótesis:** Tribux distribuye software, no opera como PT por defecto. | Revisión jurídica independiente. | Abierta |
| Q-007 | ¿Qué estados son terminales o reintentables? Evita duplicados. | WSDL y mensajes generales | **Hipótesis:** solo fallos de transporte demostrablemente seguros se reintentan. | Taxonomía oficial + pruebas de habilitación. | Abierta |
| Q-008 | ¿El consecutivo del nombre se incrementa como hexadecimal real o como decimal dentro de un campo hexadecimal? Cambia todos los nombres desde el envío 10. | Anexo 1.9, 6.5.7-6.5.8 | **Hipótesis:** la frase “hexadecimal” manda, pero el ejemplo `00000011` la contradice. | Aclaración DIAN o prueba controlada en habilitación. | Abierta |
| Q-009 | ¿Qué `ProfileID` prevalece para FEV 1.9? El anexo exige el literal completo, pero el XSL compilado v2026 compara exactamente `DIAN 2.1`. | Anexo 1.9 FAD03, p. 28; Caja v2026 `DIAN-UBL21-model-compiled.xsl`, plantilla `cbc:ProfileID`; ejecución Saxon 12.10 | **Hipótesis:** el anexo normativo prevalece y la caja contiene una regresión, pero enviar así produce FAD03 local. | Aclaración DIAN o evidencia de habilitación con ambos valores; no cambiar el generador por ahora. | Abierta / bloquea Schematron limpio |
| Q-010 | ¿Qué referencia X.509 acepta el servicio WS-Security? Cambia `SecurityTokenReference`. | WSDL vigente exige `RequireThumbprintReference`; guía oficial 2.6 muestra `Binary Security Token` | **Hipótesis:** el WSDL vigente prevalece; la guía captura una configuración histórica. | Enviar en habilitación con `ThumbprintSHA1`; usar referencia directa solo como alternativa explícita y conservar la respuesta. | Abierta / ambos perfiles implementados localmente |
| Q-011 | ¿La consulta `GetNumberingRange` es la vía prevista para obtener la clave técnica, o sólo para consultar rangos? El WSDL devuelve `TechnicalKey` dentro de cada `NumberRangeResponse`, es decir, un secreto por un canal de consulta. | WSDL habilitación SHA-256 `da576f42…`, tipo `NumberRangeResponse` | **Hipótesis:** es informativo y no sustituye la configuración del emisor; en Tribux la clave técnica se sigue montando como secreto. | Confirmar en habilitación con credenciales reales y decidir si se usa como fuente o sólo como verificación. | Abierta |

## Observaciones reproducibles

Hallazgos obtenidos ejecutando la caja oficial v2026 con SaxonJ-HE 12.10 sobre
el documento **sin firmar** generado por el pipeline
(`BuildInvoiceDocumentWithOfficialArtifactsTest`, 2026-08-08):

- **FAC03 — «No se encuentra el grupo ds:Signature».** El Schematron oficial
  exige la firma, así que un documento sin firmar nunca puede pasarlo. Por eso
  la ejecución sin firma es informativa en Tribux y la comprobación bloqueante
  pertenece a la etapa de firma. No es una pregunta abierta: es una consecuencia
  del propio Schematron.
- **FAD03 — confirma Q-009 con el texto exacto:** `(R) ProfileID : 'DIAN 2.1:
  Factura Electrónica de Venta' no contiene el literal “DIAN 2.1”`. El valor sí
  contiene esa subcadena, de modo que el XSL compilado compara de forma exacta,
  no por contenido. La contradicción con el anexo sigue sin resolverse.
- **FAJ26/FAJ27 y FAY10** con los valores del fixture sintético
  (`TaxLevelCode 'O-48'`, lista `48`, tarifa `19`). Confirman que el fixture
  publicado es sintético y **no** un ejemplo fiscalmente válido, y que validar
  contra las listas oficiales (Q-004) hace falta antes de habilitación.
