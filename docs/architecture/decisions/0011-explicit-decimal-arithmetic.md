# ADR 0011 — Aritmética decimal y redondeo explícito

**Estado:** Accepted

## Contexto

Facturas, impuestos y CUFE no pueden depender de `float`: su representación
binaria introduce diferencias observables. Tampoco existe una única política de
redondeo que deba ocultarse en todo el dominio; cada transformación fiscal debe
seguir su fuente y versión.

## Decisión

El core operará sobre strings decimales mediante aritmética entera de precisión
arbitraria, sin requerir extensiones PHP. Toda operación que pueda descartar
decimales exige escala y `DecimalRoundingMode` explícitos. Inicialmente se
soportan truncamiento hacia cero (`Down`) y redondeo aritmético (`HalfUp`).

`Money` conserva moneda y rechaza operaciones entre monedas distintas. El core
no selecciona por defecto una política fiscal DIAN; el adaptador versionado debe
elegirla y probarla contra la regla oficial correspondiente.

## Consecuencias

- los resultados son deterministas en CLI, API y workers;
- no se introduce una dependencia nativa o de Composer para matemáticas;
- cada cálculo fiscal deberá declarar escala y redondeo;
- nuevos modos requieren tests de límites positivos y negativos.
