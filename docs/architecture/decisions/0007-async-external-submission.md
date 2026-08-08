# ADR 0007 — Envíos externos asíncronos

**Estado:** Accepted

## Decisión

La interacción con DIAN se procesa en jobs, con estados persistidos e idempotencia.

## Razón

Aísla latencia/indisponibilidad externa del request HTTP.

El primer corte persiste la factura en `queued` y devuelve `202`. El job DIAN aún no está implementado.
