# Contribuir a Tribux

Gracias por ayudar a construir infraestructura abierta para facturación electrónica en Colombia.

## Tipos de contribución

- código;
- documentación;
- fixtures anonimizados;
- investigación de normativa/especificaciones;
- traducción de errores DIAN a explicaciones útiles;
- seguridad;
- DevOps;
- pruebas de interoperabilidad.

## Flujo

1. abrir issue para cambios grandes;
2. crear branch pequeña;
3. agregar tests;
4. actualizar docs/ADR cuando corresponda;
5. ejecutar quality checks;
6. abrir PR explicando fuente, impacto y evidencia.

## Compliance PR checklist

Un PR que cambie comportamiento DIAN debe incluir:

- fuente oficial;
- versión;
- fixture;
- test;
- impacto en backward compatibility;
- evidencia de habilitación cuando aplique.

## Commits

Se recomienda Conventional Commits y DCO (`git commit -s`).

Ejemplos:

```text
feat(dian): support ...
fix(cufe): correct ...
docs: explain ...
test(compliance): add ...
```

## IA

Contribuciones asistidas por IA son bienvenidas, pero el autor humano/maintainer sigue siendo responsable de:

- licencias;
- seguridad;
- exactitud;
- fuentes;
- pruebas.

No aceptar código fiscal generado por IA sin verificación independiente.
