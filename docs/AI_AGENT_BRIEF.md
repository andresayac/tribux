# Brief para Agente IA

## Rol esperado

Actúas como **arquitecto/ingeniero principal open source** de Tribux. Tu objetivo no es producir la mayor cantidad de código, sino construir una base correcta, verificable y mantenible por una comunidad.

## Contexto del proyecto

Tribux pretende simplificar la integración con la facturación electrónica colombiana. La experiencia externa debe ser una API REST/JSON clara, mientras que internamente el proyecto abstrae dominio, construcción de documentos, validación, firma digital e integración con los servicios definidos por la DIAN.

El proyecto es comunitario. No está diseñado como un SaaS propietario ni como un producto que dependa de infraestructura del fundador.

## Usuarios objetivo

### Usuario pequeño

Quiere:

- ejecutar `docker compose up`;
- configurar credenciales/certificado;
- enviar JSON sencillo;
- recibir un estado entendible;
- no estudiar SOAP/UBL/XAdES para emitir una factura.

### Integrador

Quiere:

- OpenAPI estable;
- SDKs o HTTP estándar;
- idempotencia;
- webhooks;
- errores estructurados;
- sandbox/habilitación reproducible.

### Empresa grande

Quiere:

- despliegue stateless y horizontal;
- PostgreSQL/Redis/S3 externos;
- colas reemplazables;
- Secret Manager/HSM/KMS;
- OpenTelemetry;
- alta auditabilidad;
- ciclos de release predecibles.

## Arquitectura objetivo

```text
HTTP / CLI / SDK
       |
       v
Application use cases
       |
       v
Core domain (framework agnostic)
       |
       +------------------+
       |                  |
       v                  v
DIAN ports            Persistence/Event ports
       |
       v
DIAN adapter: UBL + CUFE + signature + validation + SOAP
       |
       v
DIAN
```

## Tecnología base

- PHP: objetivo 8.5, evitando features innecesarias que bloqueen compatibilidad futura.
- API: Laravel 13, únicamente en `apps/api`.
- Core: PHP puro + Composer + PSR donde aplique.
- Contrato HTTP: OpenAPI 3.1.x.
- Persistencia estándar: PostgreSQL.
- Cache/queue estándar inicial: Redis.
- Object storage: interfaz S3-compatible/local.
- Observabilidad: OpenTelemetry.
- Desarrollo/despliegue simple: Docker Compose.
- Enterprise: adaptadores; Kubernetes/Helm solo cuando exista demanda real.

## Restricciones de compliance

1. La versión de especificación DIAN debe ser explícita.
2. No mezclar versión de Tribux, versión API y versión del Anexo DIAN.
3. Conservar payloads/respuestas necesarios para auditoría sin exponer secretos.
4. Soportar ambiente de habilitación antes de producción.
5. El XML con valor legal y sus artefactos relacionados deben tratarse como documentos inmutables una vez emitidos, salvo procesos formales de nota/ajuste.
6. Las implementaciones criptográficas deben basarse en estándares/librerías maduras, no en criptografía casera.

## Calidad mínima

- PHPStan en nivel alto progresivo.
- Estilo consistente automatizado.
- Tests unitarios del core.
- Integration tests del adaptador.
- Compliance fixtures para documentos DIAN.
- Contract tests del OpenAPI.
- CI obligatoria antes de merge.
- Dependencias fijadas/revisadas y actualizaciones automatizadas.

## Primera misión sugerida

No empezar por UI. Completar, en orden:

1. estabilizar modelo de dominio mínimo de Invoice;
2. definir esquema OpenAPI `POST /v1/invoices`;
3. crear mapper JSON -> dominio;
4. modelar identificadores/catálogos requeridos;
5. incorporar recursos oficiales DIAN de forma legal/reproducible;
6. generar primer UBL sin firma;
7. validar contra XSD;
8. calcular CUFE con fixtures oficiales/validados;
9. firmar conforme al anexo vigente;
10. transmitir al ambiente de habilitación;
11. conservar la respuesta DIAN completa como evidencia y proyectarla a estado
    interno sin aplanarla;
12. automatizar un flujo end-to-end repetible.

## Qué NO hacer todavía

- panel administrativo completo;
- nómina electrónica;
- RADIAN;
- documento soporte;
- Kubernetes como camino obligatorio;
- múltiples brokers;
- arquitectura distribuida;
- SDKs para cinco lenguajes antes de estabilizar OpenAPI;
- optimizaciones sin benchmark.

## Criterio de éxito del primer milestone

Un contribuidor nuevo puede clonar el repositorio, levantar dependencias, configurar credenciales de habilitación y reproducir el envío de una factura de prueba con trazas y resultados verificables, siguiendo exclusivamente documentación del repositorio.
