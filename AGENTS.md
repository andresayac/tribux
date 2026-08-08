# AGENTS.md — instrucciones para agentes IA

Este archivo es la puerta de entrada para cualquier agente de programación que trabaje en Tribux.

## Misión

Construir una implementación open source, auditable y fácil de desplegar para facturación electrónica colombiana, manteniendo el dominio desacoplado de Laravel y la integración DIAN detrás de puertos explícitos.

## Antes de tocar código

Leer, en este orden:

1. `docs/AI_AGENT_BRIEF.md`
2. `docs/PROJECT_VISION.md`
3. `docs/ARCHITECTURE.md`
4. `docs/DIAN_RESEARCH_BASELINE.md`
5. `docs/WORK_PLAN.md`
6. ADRs en `docs/architecture/decisions/`
7. `CONTRIBUTING.md`
8. `SECURITY.md`

## Reglas no negociables

- **No inventar reglas DIAN.** Toda regla fiscal/técnica debe indicar fuente oficial y versión del anexo o norma.
- **No afirmar compliance sin pruebas.** Una clase que genera XML no se considera compatible hasta tener fixtures, validación local y pruebas en ambiente DIAN de habilitación.
- **No introducir Laravel en `packages/core`.**
- **No mezclar transporte SOAP con dominio.**
- **No guardar claves privadas o certificados reales en el repositorio.**
- **No loguear secretos, contraseñas de P12/PFX ni material criptográfico.**
- **No acoplar almacenamiento a filesystem local.**
- **No hacer breaking changes silenciosos en OpenAPI.**
- **No convertir errores DIAN en strings opacos.** Conservar código, mensaje original y contexto normalizado.
- **No implementar microservicios por anticipación.** Priorizar monolito modular y jobs/colas.

## Definition of Done para una regla DIAN

Una regla o transformación nueva requiere, como mínimo:

1. referencia oficial trazable;
2. versión del estándar/anexo;
3. fixture positivo;
4. fixture negativo cuando aplique;
5. test unitario o de compliance;
6. mensaje de error entendible;
7. documentación si cambia el contrato público;
8. entrada en changelog si afecta comportamiento observable.

## Estrategia de trabajo recomendada

- Implementar vertical slices pequeñas.
- Primero contrato/test, después implementación.
- Mantener adaptadores reemplazables.
- Crear ADR cuando una decisión sea difícil de revertir.
- Convertir cada bug DIAN reproducible en fixture de regresión.

## Prioridad actual

Seguir `docs/WORK_PLAN.md`, comenzando por **Fase 0 — Foundation** y después **Fase 1 — primera factura de habilitación**.

## Cuando falte información

No adivinar. Registrar la duda en `docs/research/OPEN_QUESTIONS.md` con:

- pregunta;
- por qué importa;
- fuente consultada;
- hipótesis (marcada expresamente como hipótesis);
- validación pendiente.
