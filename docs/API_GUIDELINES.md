# Guía de diseño de API

## Contrato

`openapi/openapi.yaml` es fuente de verdad del contrato HTTP. Los cambios deben revisarse como cambios de producto.

## Recursos

Preferir recursos sobre RPC:

- `/v1/invoices`
- `/v1/invoices/{invoice_id}`
- `/v1/credit-notes`
- `/v1/submissions/{submission_id}`
- `/v1/issuers/{issuer_id}/config`

## HTTP

- `201` para recursos creados completamente cuando no dependen de proceso externo.
- `202` para procesamiento asíncrono.
- `409` para conflicto de idempotencia/estado.
- `422` para request semánticamente inválido.
- `503` para dependencia externa temporalmente indisponible cuando la operación no fue aceptada para procesamiento.

## Errores

Usar Problem Details (`application/problem+json`) y conservar un `code` estable.

Ejemplo:

```json
{
  "type": "https://docs.tribux.dev/problems/dian-rejected",
  "title": "Documento rechazado por DIAN",
  "status": 422,
  "code": "DIAN_REJECTED",
  "trace_id": "...",
  "errors": [
    {
      "source": "DIAN",
      "code": "...",
      "message": "...",
      "path": "customer.tax_id"
    }
  ]
}
```

## Identificadores

Los IDs internos deben ser opacos (UUIDv7/ULID: decidir en ADR). No usar el número fiscal como primary key técnico.

## Fechas

- ISO 8601.
- Timezone explícita.
- La lógica fiscal usa reloj inyectable en tests.

## Dinero

No usar floats. Transportar decimales como strings cuando se necesite preservar precisión:

```json
{ "amount": "100000.00", "currency": "COP" }
```

## Idempotencia

Operaciones de emisión aceptan `Idempotency-Key`.

## Paginación

Preferir cursor pagination para colecciones grandes.

## Versionado

`/v1` para major version. No versionar por fecha salvo decisión posterior documentada.
