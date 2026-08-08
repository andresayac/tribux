# Visión del proyecto

## Problema

La facturación electrónica colombiana combina obligaciones tributarias, formatos XML, firma digital, catálogos, validaciones y transporte hacia servicios de la DIAN. Esta complejidad se repite en miles de integraciones privadas.

## Propuesta

Tribux busca convertir esa complejidad en componentes abiertos y reutilizables:

- un **core de dominio**;
- un **adaptador DIAN**;
- una **API REST/JSON**;
- documentación para desarrolladores y contribuyentes técnicos;
- un conjunto de **fixtures y pruebas de compliance**;
- herramientas de despliegue self-hosted.

## Lo que Tribux sí es

- software open source;
- infraestructura para desarrolladores;
- una implementación self-hosted;
- una base de conocimiento técnico verificable;
- un proyecto para colaboración entre desarrolladores, contadores, QA, DevOps y expertos tributarios.

## Lo que Tribux no es

- software oficial de la DIAN;
- una promesa de cumplimiento sin validación;
- asesoría legal/tributaria;
- un SaaS administrado por defecto;
- un intento de reemplazar la DIAN;
- un ERP, CRM o sistema contable completo.

## Norte de producto

La API debe esconder complejidad accidental, pero **no ocultar hechos fiscales relevantes**. Debe ser fácil enviar una factura y también posible auditar exactamente qué ocurrió.

## Experiencia deseada

```text
POST /v1/invoices
        |
        v
JSON simple y validado
        |
        v
Documento de dominio
        |
        v
Reglas Colombia / DIAN
        |
        v
XML + identificador + firma + validación
        |
        v
Transmisión DIAN
        |
        v
Estado normalizado + evidencia auditable
```

## Métricas de éxito comunitario

- tiempo de onboarding de un nuevo contribuidor;
- porcentaje de reglas con fuente y fixture;
- cobertura de escenarios reales, no solo porcentaje de líneas;
- tiempo medio para adaptar cambios DIAN;
- cantidad de integraciones que no requieren forks;
- documentación reproducible;
- releases sin breaking changes inesperados.
