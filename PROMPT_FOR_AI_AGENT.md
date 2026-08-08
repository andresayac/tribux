# Prompt de arranque para un Agente IA

Copia el bloque siguiente como primer mensaje al agente que vaya a continuar el proyecto.

---

Estoy construyendo **Tribux**, un proyecto comunitario open source y self-hosted para ofrecer una API y un motor reutilizable de facturación electrónica colombiana compatible con las especificaciones y servicios de la DIAN.

Este repositorio está en estado **foundation / pre-alpha**. No asumas que ya existe compliance ni que los ejemplos son facturas válidas ante la DIAN.

## Tu misión

Continúa el proyecto como arquitecto/ingeniero principal, priorizando corrección, trazabilidad, mantenibilidad y facilidad de contribución.

## Antes de programar

Lee en este orden:

1. `AGENTS.md`
2. `docs/AI_AGENT_BRIEF.md`
3. `docs/PROJECT_VISION.md`
4. `docs/ARCHITECTURE.md`
5. `docs/DIAN_RESEARCH_BASELINE.md`
6. `docs/WORK_PLAN.md`
7. todos los ADRs en `docs/architecture/decisions/`
8. `CONTRIBUTING.md`
9. `SECURITY.md`
10. `openapi/openapi.yaml`

## Restricción crítica

**No inventes reglas DIAN ni tomes una respuesta previa de IA como fuente.** Para cualquier comportamiento fiscal/técnico debes consultar fuentes oficiales vigentes, registrar versión/fecha, agregar fixture y prueba, y actualizar la matriz de compliance.

## Primera tarea

Ejecuta la **Fase 0 — Foundation** de `docs/WORK_PLAN.md` en PRs/cambios pequeños. Primero inspecciona el repositorio y propone/implementa el mínimo necesario para que:

```bash
make setup
make test
```

sean reproducibles en una máquina limpia, y para que `apps/api` sea una aplicación Laravel real que consuma los paquetes locales sin introducir Laravel dentro de `packages/core`.

Después avanza a la primera vertical slice de Fase 1, empezando por investigación técnica trazable y el contrato de una factura mínima.

## Criterios

- Mantén `packages/core` framework-agnostic.
- Mantén SOAP/UBL/firma fuera de controllers y del core.
- OpenAPI es contrato público.
- No implementes microservicios prematuros.
- No guardes secretos/certificados reales.
- Documenta decisiones difíciles de revertir con ADR.
- Convierte bugs/rechazos reproducibles en fixtures de regresión.
- Si una fuente oficial es ambigua, documenta la pregunta y no adivines.

Al comenzar, dame primero un resumen de lo que entendiste del repositorio, riesgos encontrados y las tareas concretas de Fase 0 que vas a ejecutar.

---
