# tribux/dian

Librería PHP independiente de Laravel para reglas y adaptadores DIAN versionados.
Puede usarse directamente desde un ERP/POS o a través de `apps/api`, la API HTTP
opcional de Tribux.

El paquete incluye el cálculo CUFE-SHA384 FEV 1.9 validado localmente contra el
ejemplo oficial, códigos de ambiente, contratos iniciales SOAP, un modelo de
factura DIAN versionado y generación UBL 2.1 sin firma. **Todavía no firma,
ejecuta Schematron, transmite ni puede afirmar que produce una factura aceptada
por la DIAN.**

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

`Signing/DianSignaturePolicy` expone únicamente metadatos verificados de la
política. No maneja certificados ni claves privadas y no equivale a un firmador
XAdES.

`Documents/Fev19/Invoice/UnsignedInvoiceXmlGenerator` produce XML determinista
con `sts:DianExtensions`. Su contrato exige que numeración, software, CUFE,
terceros, impuestos y totales ya estén normalizados; no infiere reglas fiscales.
El fixture de construcción completo está en
`tests/Fixtures/fev-1.9/invoice/minimal-priced-line.json` y su uso se prueba en
`UnsignedInvoiceXmlGeneratorTest`.

`CoreInvoiceMapper` conecta el perfil básico de `tribux/core` con ese documento.
Requiere un `InvoiceGenerationContext` enriquecido por la aplicación: resolución,
software/PIN, clave técnica, datos DIAN de las partes, unidades y mapeos de
impuestos. Los secretos permanecen privados en memoria y no se infieren desde el
payload genérico.
