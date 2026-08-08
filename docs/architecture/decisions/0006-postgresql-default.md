# ADR 0006 — PostgreSQL como persistencia por defecto

**Estado:** Accepted

## Decisión

PostgreSQL será el datastore de referencia de la API. El dominio no dependerá de él.

SQLite se usa únicamente para tests rápidos de la aplicación. El entorno Docker de referencia ejecuta PostgreSQL 18.
