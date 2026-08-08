# ADR 0004 — Laravel para la aplicación API

**Estado:** Accepted

## Decisión

Usar Laravel 13 en `apps/api` para HTTP, auth, jobs, migrations y operación.

## Evidencia de adopción

El scaffold Laravel 13 vive en `apps/api` y ejecuta el primer corte vertical de facturas. Los controllers coordinan casos de uso; dominio y DIAN permanecen en paquetes independientes.
