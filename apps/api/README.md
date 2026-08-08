# Tribux API

Aplicación HTTP de referencia construida con Laravel 13. Coordina casos de uso y adaptadores, pero el dominio reutilizable permanece en `packages/core` y la interoperabilidad DIAN en `packages/dian`.

## Rutas implementadas

| Método | Ruta | Estado |
|---|---|---|
| `GET` | `/health` | Disponible |
| `POST` | `/v1/invoices` | Persiste una intención en estado `queued` |
| `GET` | `/v1/invoices/{invoiceId}` | Disponible |
| `GET` | `/v1/invoices/{invoiceId}/status` | Disponible |

El contrato público está en `../../openapi/openapi.yaml`.

## Desarrollo local

Desde la raíz del monorepo:

```bash
make setup
make check
make up
```

La API queda disponible en `http://localhost:8080`. `make down` apaga los contenedores y conserva los volúmenes.

## Límites actuales

- API pre-alpha sin autenticación; no exponerla a redes no confiables.
- No genera UBL, CUFE ni firma digital.
- No transmite documentos a DIAN.
- `queued` representa una intención persistida, no una factura fiscal emitida.
