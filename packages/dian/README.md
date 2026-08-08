# tribux/dian

Librería PHP independiente de Laravel para reglas y adaptadores DIAN versionados.
Puede usarse directamente desde un ERP/POS o a través de `apps/api`, la API HTTP
opcional de Tribux.

El paquete ya incluye el cálculo CUFE-SHA384 FEV 1.9 validado localmente contra
el ejemplo oficial, códigos de ambiente y contratos iniciales de endpoints y
operaciones. **Todavía no genera, firma ni transmite una factura válida.**

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
