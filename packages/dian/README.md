# tribux/dian

Librería PHP independiente de Laravel para reglas y adaptadores DIAN versionados.
Puede usarse directamente desde un ERP/POS o a través de `apps/api`, la API HTTP
opcional de Tribux.

El paquete incluye el cálculo CUFE-SHA384 FEV 1.9 validado localmente contra el
ejemplo oficial, códigos de ambiente, contratos iniciales SOAP, un modelo de
factura DIAN versionado, generación UBL 2.1, firma XAdES-EPES y validación XSD y
Schematron. **Todavía no transmite ni puede afirmar que produce una factura
aceptada por la DIAN.**

`Artifacts/Fev19ArtifactSet` descubre los XSD en la caja descargada y
`Validation/DianXsdValidator` valida XML sin habilitar acceso de red para el
documento. Los errores de libxml conservan nivel, código, línea, columna,
mensaje y archivo. El XSL oficial exige XSLT 3.0 y no se ejecuta con libxslt.

Ejemplo del motor como librería:

```php
use Tribux\Dian\Cufe\CufeCalculator;
use Tribux\Dian\Cufe\CufeInput;
use Tribux\Dian\DianEnvironment;

$cufe = (new CufeCalculator())->calculate(new CufeInput(
    invoiceNumber: '323200000129',
    issueDate: '2019-01-16',
    issueTime: '10:53:10-05:00',
    lineExtensionAmount: '1500000.00',
    vatAmount: '285000.00',
    incAmount: '0.00',
    icaAmount: '0.00',
    payableAmount: '1785000.00',
    issuerTaxId: '700085371',
    buyerIdentification: '800199436',
    technicalKey: '693ff6f2a553c3646a063436fd4dd9ded0311471',
    environment: DianEnvironment::Production,
));
```

Submódulos previstos:

```text
Catalogs/
Cufe/
Documents/
Signing/
Soap/
Validation/
Submission/
```

Cualquier implementación futura debe seguir `AGENTS.md` y la matriz de compliance.

`Signing/Fev19XadesSigner` añade la segunda extensión UBL y firma con el perfil
observado en el anexo FEV 1.9 y los ejemplos oficiales recientes: C14N inclusivo,
RSA-SHA256 y tres referencias SHA-384. `SigningCredentials` importa PEM o
PKCS#12/P12 en memoria, comprueba que certificado y clave RSA correspondan y no
expone la clave privada ni conserva la contraseña.

```php
use DateTimeImmutable;
use Tribux\Dian\Signing\DianSignerRole;
use Tribux\Dian\Signing\Fev19XadesSigner;
use Tribux\Dian\Signing\SigningCredentials;

$credentials = SigningCredentials::fromPkcs12($p12Contents, $password);
$signedXml = (new Fev19XadesSigner())->sign(
    unsignedXml: $unsignedXml,
    credentials: $credentials,
    role: DianSignerRole::Supplier,
    signingTime: new DateTimeImmutable('now'),
);
```

La aplicación es responsable de obtener `$p12Contents` y `$password` desde un
proveedor de secretos, no desde el payload HTTP ni desde el repositorio. El test
criptográfico genera certificado y clave efímeros en cada ejecución; no hay
material privado versionado. La firma pasa localmente el XSD oficial, pero falta
probarla con un certificado real de habilitación y obtener respuesta DIAN.

`Submission/Fev19` compone los nombres y el ZIP de transporte sin acoplarlos a
Laravel ni a almacenamiento permanente:

```php
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Submission\Fev19\Fev19FileNameGenerator;
use Tribux\Dian\Submission\Fev19\Fev19FileSequence;
use Tribux\Dian\Submission\Fev19\Fev19SubmissionDocument;
use Tribux\Dian\Submission\Fev19\Fev19SubmissionMode;
use Tribux\Dian\Submission\Fev19\Fev19ZipPackageBuilder;

$names = new Fev19FileNameGenerator('800197268', '000', 2026);
$documentName = $names->documentName(
    DianDocumentType::Invoice,
    new Fev19FileSequence('00000001'),
);
$zipName = $names->zipName(new Fev19FileSequence('00000001'));

$package = (new Fev19ZipPackageBuilder())->build(
    $zipName,
    Fev19SubmissionMode::Synchronous,
    new Fev19SubmissionDocument(DianDocumentType::Invoice, $documentName, $signedXml),
);
```

La secuencia se recibe ya codificada en ocho caracteres hexadecimales. Tribux no
la incrementa porque Q-008 documenta una contradicción en el ejemplo oficial.
El empaquetador no reemplaza la validación XSD/Schematron previa.

`Soap/WsSecuritySoapEnvelopeBuilder` prepara un mensaje SOAP 1.2 firmado para el
WCF de DIAN sin realizar I/O. `Requests/SendTestSetAsyncRequest` encapsula el ZIP
en Base64 y `DianSoapMessage` expone el Content-Type con su action. El default
usa la referencia `ThumbprintSHA1` exigida por el WSDL vigente; la referencia
directa a `BinarySecurityToken` mostrada por la guía histórica solo se activa de
forma explícita. Ninguno de los dos modos se considera aceptado hasta probarlo en
habilitación.

`Soap/Transport/CurlDianSoapTransport` envía ese mensaje por HTTPS sin
reformatearlo. Verifica certificado/hostname, exige TLS 1.2 como mínimo, no sigue
redirects y requiere timeouts y límite de respuesta explícitos o sus defaults
seguros. No hace retries: después de un timeout no se puede asumir que DIAN no
recibió el documento. Los errores de cURL conservan código, mensaje original y
categoría normalizada.

`Soap/Responses/DianSoapResponseParser` interpreta `SendTestSetAsync` y
`GetStatus`/`GetStatusZip` sin perder el XML recibido. Expone `ZipKey`, cada
`XmlParamsResponseTrackId`, los campos completos de `DianResponse` y la lista de
respuestas de un paquete; un SOAP 1.2 Fault conserva código, subcódigo, razones
por idioma y detalle aun con HTTP 500. Valores opcionales ausentes permanecen
`null` en lugar de inventar defaults.

Para habilitación, `DianTestSetClient` compone las tres etapas sin ocultarlas:

```php
use DateTimeImmutable;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Soap\DianEndpoint;
use Tribux\Dian\Soap\DianStatusClient;
use Tribux\Dian\Soap\DianStatusZipClient;
use Tribux\Dian\Soap\DianTestSetClient;
use Tribux\Dian\Soap\Requests\SendTestSetAsyncRequest;

$result = (new DianTestSetClient(
    DianEndpoint::defaultFor(DianEnvironment::Habilitation),
))->send(
    new SendTestSetAsyncRequest($zipFileName, $zipContents, $testSetId),
    $credentials,
    new DateTimeImmutable('now'),
);

$status = (new DianStatusClient(
    DianEndpoint::defaultFor(DianEnvironment::Habilitation),
))->get($documentTrackId, $credentials, new DateTimeImmutable('now'));

if ($result->zipKey !== null) {
    $packageStatus = (new DianStatusZipClient(
        DianEndpoint::defaultFor(DianEnvironment::Habilitation),
    ))->get($result->zipKey, $credentials, new DateTimeImmutable('now'));
}
```

El cliente no reintenta ni persiste evidencia. El nombre/ZIP deben haberse
construido según el perfil DIAN y la aplicación debe conservar `rawXml` en su
almacenamiento seguro. `DianStatusClient` conserva los códigos y mensajes DIAN,
pero no decide por sí mismo si un código representa aceptación o rechazo. En el
ejemplo, `$documentTrackId` es el identificador de seguimiento del documento;
el `zipKey` de `SendTestSetAsync` se consulta con `DianStatusZipClient`.

`Documents/Fev19/Invoice/UnsignedInvoiceXmlGenerator` produce XML determinista
sin firma con `sts:DianExtensions`. Su contrato exige que numeración, software, CUFE,
terceros, impuestos y totales ya estén normalizados; no infiere reglas fiscales.
El fixture de construcción completo está en
`tests/Fixtures/fev-1.9/invoice/minimal-priced-line.json` y su uso se prueba en
`UnsignedInvoiceXmlGeneratorTest`.

`CoreInvoiceMapper` conecta el perfil básico de `tribux/core` con ese documento.
Requiere un `InvoiceGenerationContext` enriquecido por la aplicación: resolución,
software/PIN, clave técnica, datos DIAN de las partes, unidades y mapeos de
impuestos. Los secretos permanecen privados en memoria y no se infieren desde el
payload genérico.

Schematron requiere un runtime XSLT 3.0 opcional. `SaxonSchematronValidator`
soporta SaxonJ-HE 12.10 y devuelve severidad, código DIAN, mensaje normalizado y
texto original. El runtime se obtiene con `composer tools:fetch-saxon` y
`composer tools:extract-saxon`; luego puede ejecutarse
`composer dian:validate-schematron -- documento.xml`.
