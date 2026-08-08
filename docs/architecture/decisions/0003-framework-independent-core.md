# ADR 0003 — Core independiente de Laravel

**Estado:** Accepted (seed)

## Decisión

`packages/core` será PHP puro y no importará Laravel/Eloquent/Facades.

## Consecuencia

Laravel acelera la API sin convertirse en el modelo del dominio.
