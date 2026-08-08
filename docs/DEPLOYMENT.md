# Estrategia de despliegue

## Nivel 1 — desarrollo

Docker Compose con PostgreSQL y Redis. La app puede ejecutarse localmente o en contenedor.

## Nivel 2 — self-hosted simple

Un VPS/servidor con:

- reverse proxy/TLS;
- API + workers separados;
- PostgreSQL persistente;
- Redis;
- backups;
- secret mount;
- object storage local o externo.

## Nivel 3 — enterprise

- múltiples replicas stateless;
- workers autoscalables;
- PostgreSQL administrado/HA;
- Redis administrado;
- object storage externo;
- secret manager/HSM según requerimientos;
- OpenTelemetry Collector;
- ingress/load balancer;
- despliegue rolling/blue-green.

## Regla

Kubernetes es una opción de operación, **no un requisito del software**.

## Backups

Antes de producción definir RPO/RTO y probar restauración de:

- base de datos;
- object storage;
- configuración no secreta;
- claves/certificados según política organizacional.
