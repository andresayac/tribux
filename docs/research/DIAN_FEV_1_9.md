# Investigación técnica DIAN FEV 1.9

**Corte de verificación:** 2026-08-08

**Alcance:** primera factura de venta en habilitación con construcción, firma y
validación local; no cubre todavía envío SOAP ni aceptación real en DIAN.

## Autoridad y versión

La página oficial de [documentación técnica de la DIAN](https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/documentacion-tecnica/)
publica actualmente el **Anexo Técnico de Factura Electrónica de Venta 1.9** y
la **Caja de herramientas FE V19 (v2026)**. La Resolución 000165 del 1 de
noviembre de 2023 adoptó esta versión; las modificaciones normativas posteriores
deben revisarse por separado antes de afirmar vigencia jurídica de una regla.

Los artefactos, tamaños y SHA-256 verificados están en
[`resources/dian/fev/1.9/manifest.json`](../../resources/dian/fev/1.9/manifest.json).
La revisión se hizo mediante descarga desde los dominios oficiales, extracción
textual de las secciones citadas y análisis del WSDL. Los binarios no se
redistribuyen mientras sus condiciones de redistribución sigan sin aclararse.

## Inventario de la caja de herramientas

El ZIP verificado contiene 177 archivos. Entre ellos:

| Tipo | Cantidad | Uso previsto |
|---|---:|---|
| XSD | 23 | Validación estructural UBL, DIAN, XAdES y XMLDSIG |
| Schematron (`.sch`) | 7 | Reglas de estructura y modelo DIAN |
| XSL | 8 | Transformaciones/validaciones compiladas |
| Genericode (`.gc`) | 36 | Listas de valores |
| XML | 44 | Ejemplos oficiales y recursos XML |
| XLSX | 39 | Tablas referenciadas y catálogos legibles |
| PDF | 6 | Documentación auxiliar |

Se observaron esquemas principales para `Invoice`, `CreditNote`, `DebitNote`,
`AttachedDocument`, `ApplicationResponse` y `DIAN_UBL_Structures`. Este
inventario no significa que Tribux ya ejecute todas esas validaciones.

Tribux ya puede descubrir los cinco XSD de documento y ejecutar validación
estructural local con DOM/libxml. El ejemplo oficial
`ejemplificacionIBUA-3.xml` pasó esa validación en la fecha de corte. El
Schematron `DIAN-UBL21-model.sch` declara `queryBinding="xslt3"` y su XSL
compilado usa XSLT 3.0: la extensión XSL de PHP usa libxslt 1.x y no es un motor
compatible. Tribux fijó SaxonJ-HE 12.10 como runtime XSLT 3.0 reproducible y ya
conserva hallazgos estructurados. La ejecución local del ejemplo oficial reveló
que el XSL v2026 exige `ProfileID = 'DIAN 2.1'`, mientras FAD03 del anexo exige
`DIAN 2.1: Factura Electrónica de Venta`; la contradicción Q-009 permanece
abierta y bloquea declarar un resultado Schematron limpio.

## Primer UBL de factura

`Documents/Fev19/Invoice` define un modelo DIAN versionado separado del dominio
genérico y genera un UBL 2.1 determinista con la primera
`ext:UBLExtension`/`sts:DianExtensions`. Incluye control de numeración, proveedor
de software, código de seguridad SHA-384, proveedor de autorización, URL QR,
terceros, medio de pago, impuestos, totales y líneas con precio.

El fixture `minimal-priced-line.json` es sintético y trazable; no contiene
credenciales ni pretende ser un documento aceptado por la DIAN. El XML resultante
pasó localmente `UBL-Invoice-2.1.xsd` de la caja FEV 1.9 verificada. El generador
no añade una firma vacía: `Fev19XadesSigner` incorpora la segunda extensión solo
durante la firma. Tanto la versión sin firma como la firmada con un certificado
efímero pasan el XSD oficial. Pasar XSD no implica pasar Schematron ni
habilitación.

## Perfil de servicio observado

| Ambiente | Servicio observado | WSDL |
|---|---|---|
| Habilitación | `https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc` | `?singleWsdl` |
| Producción | `https://vpfe.dian.gov.co/WcfDianCustomerServices.svc` | `?singleWsdl` |

Ambos servicios oficiales respondieron en la fecha de corte. La guía oficial
indica que la URL operativa se expone en el catálogo de participantes; por eso
estos valores son defaults reemplazables, no una configuración inmutable.

Los WSDL observados usan:

- SOAP 1.2 document/literal;
- WS-Addressing;
- TLS y WS-Security 1.0;
- token X.509 y referencia por thumbprint;
- timestamp y cabecera `To` firmada;
- política criptográfica `Basic256Sha256Rsa15` declarada por WCF.

El anexo, sección 7, exige además TLS 1.2, WS-Security 1.0 y el perfil X.509
Certificate Token 1.1. No se implementará el cliente con SOAP nativo de PHP
hasta probar que permite controlar exactamente estas cabeceras y firmas.

La policy del WSDL vigente exige `IncludeTimestamp`, layout `Strict`, token X.509
V3 incluido siempre para el receptor, referencia `ThumbprintSHA1` y firma de la
cabecera WS-Addressing `To`. La guía oficial configura RSA-SHA256, C14N exclusivo,
digest SHA-256, certificado único, la misma cabecera `To` y un Timestamp de
60.000 ms. Su captura selecciona `Binary Security Token` como identificador, en
contradicción con `RequireThumbprintReference` del WSDL; la diferencia queda como
Q-010 hasta observar una respuesta del ambiente de habilitación.

`WsSecuritySoapEnvelopeBuilder` construye y verifica localmente ambos perfiles de
referencia. El default sigue el WSDL (`ThumbprintSHA1`); el modo de referencia
directa al `BinarySecurityToken` exige selección explícita. La prueba vuelve a
cargar el XML, verifica la firma RSA y el digest de `To`, además del body y
Content-Type de `SendTestSetAsync`.

`CurlDianSoapTransport` envía el mensaje sin modificarlo, restringe el protocolo
a HTTPS/TLS 1.2 o superior, verifica CA y hostname, no sigue redirects, separa
timeout de conexión y total, y limita bytes de respuesta. Los fallos conservan
código/mensaje cURL y una categoría normalizada, pero no se reintentan
automáticamente porque un timeout de envío puede tener resultado remoto ambiguo.
La integración se prueba contra un servidor TLS local con CA, certificado y clave
efímeros. Falta parsear respuestas/faults y probar el servicio de habilitación;
por eso no se afirma aceptación remota.

## Operaciones del primer corte

El namespace del contrato es `http://wcf.dian.colombia` y las acciones siguen:

```text
http://wcf.dian.colombia/IWcfDianCustomerServices/{Operation}
```

| Operación | Parámetros del WSDL | Resultado del WSDL | Uso inicial |
|---|---|---|---|
| `SendTestSetAsync` | `fileName`, `contentFile`, `testSetId` | `UploadDocumentResponse` | Set de pruebas de habilitación |
| `SendBillSync` | `fileName`, `contentFile` | `DianResponse` | Envío individual síncrono |
| `GetStatus` | `trackId` | `DianResponse` | Consultar documento |
| `GetStatusZip` | `trackId` | lista de `DianResponse` | Consultar paquete |
| `GetNumberingRange` | `accountCode`, `accountCodeT`, `softwareCode` | rangos de numeración | Consultar numeración autorizada |

El anexo escribe `GetStatusZIP` en algunos títulos; el nombre contractual del
WSDL es `GetStatusZip`. Que un campo figure `minOccurs=0` en el XSD del servicio
no permite concluir que sea opcional para las reglas de negocio.

`DianResponse` conserva, entre otros, lista de errores, validez, código,
descripción, mensaje, XML y clave/nombre del documento. Tribux debe normalizarlo
sin perder la respuesta original ni convertir los errores DIAN en un único
string.

## CUFE-SHA384 confirmado

Fuente: Anexo Técnico FEV 1.9, secciones 11.1 y 11.2, páginas 654-659.

```text
SHA-384(
  NumFac + FecFac + HorFac + ValFac +
  "01" + ValImp1 +
  "04" + ValImp2 +
  "03" + ValImp3 +
  ValTot + NitOFE + NumAdq + ClTec + TipoAmbie
)
```

Los códigos y el orden son fijos: `01` IVA, `04` INC y `03` ICA. Un impuesto
ausente usa `0.00`. Los valores monetarios se truncan a dos decimales y se
representan sin separadores; NIT/identificación se usan sin puntuación ni dígito
de verificación. `TipoAmbie` es `1` para producción y `2` para pruebas según
`TipoAmbiente-2.1.gc`.

La implementación actual recibe valores ya normalizados con dos decimales. No
acepta `float` ni decide por sí sola una política de redondeo. El fixture positivo
reproduce el ejemplo y hash publicados por la DIAN; el negativo protege el
formato decimal canónico.

## Nombres y paquetes

Fuente: Anexo Técnico FEV 1.9, secciones 6.5.7 y 6.5.8, páginas 303-305.

- XML: `{tipo}{nit10}{pt3}{año2}{consecutivoHex8}.xml`;
- ZIP: `z{nit10}{pt3}{año2}{consecutivoHex8}.zip`;
- tipos iniciales: `fv`, `nc`, `nd`, `ar`, `ad`;
- NIT sin DV, alineado a diez dígitos con ceros a la izquierda;
- `000` corresponde a software propio y `001` a facturación gratuita DIAN;
- el consecutivo hexadecimal inicia en `00000001` cada 1 de enero;
- síncrono: exactamente un documento XML;
- asíncrono: menos de 51 documentos, combinables según el anexo.

Estas reglas permanecen en estado `research` hasta añadir generador de nombres,
límites, fixtures positivos/negativos y pruebas.

## AttachedDocument

La sección 6.4, páginas 263-270, define `AttachedDocument` como el contenedor que
transmite en un XML el documento electrónico y los eventos registrados hasta la
fecha. Contiene el documento original como `text/xml`/`UTF-8` y referencias a
respuestas/eventos. Es parte del flujo de entrega al adquirente, pero no es el
ZIP de transporte a DIAN y no bloquea la primera transmisión de habilitación.

## Firma XAdES-EPES local

La sección 6.5.10 confirma firma **XAdES-EPES**, canonicalización XML C14N
`http://www.w3.org/TR/2001/REC-xml-c14n-20010315`, firma enveloped y grupos como
`SignedProperties`, `SigningTime`, `SigningCertificate`,
`SignaturePolicyIdentifier` y `SignerRole`. La tabla admite RSA con SHA-256,
SHA-384 o SHA-512.

La [política de firma DIAN v2](https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf)
fue descargada directamente y registrada con 1.272.898 bytes y SHA-256
`74ca0cbed706e5a233818a34b48b1241e5490439d49df48e7c1a715eb9a8af46`.
Su SHA-384 en Base64 es
`EQC0kiWPaAME6IsEZ7WuaTWJ97Zmf6hIO69rMCVURmQxBB9ebgLrjhL5BArQ0a0l`,
idéntico al publicado en los ejemplos recientes de la caja. El identificador es
la URL anterior y el método es
`http://www.w3.org/2001/04/xmldsig-more#sha384`.

La política define los roles `supplier` para el obligado a facturar y
`third party` para un proveedor tecnológico autorizado. También advierte que el
XML firmado no debe alterarse luego con pretty-print, indentación o cambios de
espacios/control que invaliden el canon.

La caja mezcla ejemplos históricos con identificadores `/v1/`, SHA-256 y
ejemplos posteriores `/v2/` con SHA-384. El ejemplo reciente
`ejemplificacionIBUA-3.xml` confirmó para el perfil `/v2/`:

- `SignedInfo` con C14N inclusivo y RSA-SHA256;
- referencias al documento, `KeyInfo` y `SignedProperties`, las tres con
  digest SHA-384;
- certificado público del firmante en `KeyInfo` y ruta de certificados en
  `SigningCertificate`;
- política v2 SHA-384 y rol `supplier`;
- nombres del emisor X.509 en forma distinguida abreviada (`C`, `O`, `CN`, etc.).

`Fev19XadesSigner` implementa ese perfil con DOM/libxml y OpenSSL. Las pruebas
generan una clave RSA y un certificado efímeros, verifican de manera independiente
la firma y los tres digest después de serializar y volver a cargar el XML, prueban
errores de credenciales y validan la factura firmada contra el XSD oficial. No se
versiona ninguna clave privada. Este resultado es validación local; falta usar un
certificado real de habilitación y obtener una respuesta DIAN antes de afirmar
compatibilidad operativa o aceptación.

## Inconsistencia pendiente en el consecutivo de archivos

Las secciones 6.5.7/6.5.8 llaman hexadecimal al consecutivo de ocho caracteres,
pero el ejemplo del “décimo primer” envío termina en `00000011`; una conversión
decimal-a-hexadecimal produciría `0000000B`. No se implementará el generador
hasta contrastar esta diferencia con la validación real o una aclaración oficial.

## Fuentes oficiales consultadas

- [Documentación técnica DIAN](https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/documentacion-tecnica/)
- [Anexo Técnico FEV 1.9](https://www.dian.gov.co/impuestos/factura-electronica/Documents/Anexo-Tecnico-Factura-Electronica-de-Venta-vr-1-9.pdf)
- [Caja de herramientas FE V19 (v2026)](https://www.dian.gov.co/impuestos/factura-electronica/Documents/Caja-de-herramientas-FE-V19-V2026.zip)
- [Guía para consumo de Web Services](https://www.dian.gov.co/impuestos/factura-electronica/Documents/Guia-Herramienta-para-el-Consumo-de-Web-Services.pdf)
- [Política de firma DIAN v2](https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf)
- [WSDL de habilitación](https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?singleWsdl)
- [WSDL de producción](https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?singleWsdl)

La guía de consumo publicada es material histórico y no sustituye el anexo 1.9,
los WSDL vigentes ni la configuración entregada a cada participante.
