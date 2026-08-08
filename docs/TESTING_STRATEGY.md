# Estrategia de pruebas

## Pirámide específica del proyecto

### Unit tests

- value objects;
- cálculos monetarios;
- impuestos;
- identificadores técnicos;
- serialización determinista.

### Schema/fixture tests

Cada escenario fiscal importante debe tener un fixture versionado:

```text
input.json
expected.xml (cuando sea estable y legalmente redistribuible)
metadata.yaml
```

`metadata.yaml` registra fuente, versión DIAN, propósito y resultado esperado.

### Integration tests

- firma real con certificados de prueba;
- validación XSD;
- cliente SOAP contra mocks fieles;
- PostgreSQL/Redis/object storage.

### E2E habilitación

Tests contra DIAN no deben correr en cada PR. Deben existir como suite explícita, con secretos fuera del repositorio, trazabilidad y límites de uso.

## Golden files

Son útiles para XML determinista, pero no deben impedir refactors semánticamente equivalentes. Comparar XML canonicalizado/estructural cuando corresponda.

## Property-based tests

Evaluar para:

- redondeos;
- sumas de líneas/totales;
- invariantes monetarios;
- serialización de valores límite.

## Bug policy

Todo bug reproducible que haya generado rechazo DIAN debe producir un test de regresión antes del fix.
