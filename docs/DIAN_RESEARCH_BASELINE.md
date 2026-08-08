# Baseline de investigación DIAN

**Fecha de verificación inicial:** 2026-08-08

Este documento registra únicamente una línea base. Antes de implementar compliance, el equipo/agente debe volver a verificar fuentes oficiales y registrar hash/fecha de los artefactos usados.

## Hallazgos confirmados para orientar la arquitectura

1. La DIAN publica documentación técnica del Sistema de Facturación Electrónica y actualmente lista el **Anexo Técnico de Factura Electrónica de Venta versión 1.9**.
2. La normativa compilada contempla modalidades que incluyen desarrollo informático propio/adquirido, solución gratuita y proveedor tecnológico.
3. La integración técnica de validación previa utiliza servicios web y requiere tratar explícitamente habilitación/producción.
4. Tribux debe diferenciar claramente software distribuido/self-hosted de la operación como tercero para otras organizaciones.

## Fuentes oficiales iniciales

- Documentación técnica DIAN: https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/documentacion-tecnica/
- Micrositio SFE: https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/
- Resolución 227 de 2025 (compilación DIAN): https://normograma.dian.gov.co/dian/compilacion/docs/resolucion_dian_0227_2025.htm
- Concepto DIAN 13246 de 2025: https://normograma.dian.gov.co/dian/compilacion/docs/oficio_dian_13246_2025.htm
- Anexo Técnico FEV 1.9 publicado por DIAN: https://www.dian.gov.co/impuestos/factura-electronica/Documents/Anexo-Tecnico-Factura-Electronica-de-Venta-vr-1-9.pdf

## Política de fuentes

Prioridad:

1. DIAN / Normograma DIAN;
2. normativa oficial aplicable;
3. estándares técnicos primarios (OASIS, W3C, RFC);
4. documentación oficial de librerías;
5. fuentes secundarias solo como ayuda, nunca como autoridad de compliance.

## Registro de artefactos

El registro reproducible ya existe en
`resources/dian/fev/1.9/manifest.json`. Incluye URL oficial, fecha de
verificación, tamaño y SHA-256 del anexo, caja, guía y WSDL de ambos ambientes.

Los binarios no se versionan: sus condiciones de redistribución siguen sin
aclararse. `composer dian:fetch-fev19` los descarga a una ruta ignorada por Git
y rechaza cualquier diferencia de tamaño o hash.

Los hallazgos técnicos y límites de lo confirmado están en
`docs/research/DIAN_FEV_1_9.md`; la trazabilidad regla-código-fixture-test está en
`docs/compliance/fev-1.9.md`.

## Preguntas abiertas iniciales

Ver `docs/research/OPEN_QUESTIONS.md`.
