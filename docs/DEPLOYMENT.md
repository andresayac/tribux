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

## Configuración de emisores y secretos

Tribux separa deliberadamente la configuración no secreta de los secretos.
Ninguna de las dos se versiona.

### Configuración no secreta

Un archivo JSON montado, indexado por `issuer_id`, con la configuración DIAN de
cada emisor: tercero emisor, resolución/rango, identidad del software, código de
proveedor, mapeos tributarios, unidades permitidas, política de redondeo, zona
horaria y `testSetId`. La forma está en `examples/issuer.habilitation.json`, que
es sintético.

```bash
TRIBUX_ISSUERS_FILE=/run/config/tribux/issuers.json
```

El archivo **no** contiene PIN, clave técnica, certificado ni contraseña: sólo
declara un `credential_reference` que el proveedor de secretos resuelve.

Todos los emisores del archivo se validan al cargarlo. Un error indica el
emisor y la ruta exacta del campo; nunca imprime el contenido del archivo, que
sí contiene datos del contribuyente.

### Secretos

Un secreto por archivo, la forma que ya producen Docker y Kubernetes:

```text
${TRIBUX_SECRETS_PATH}/<credential_reference>/software_pin
${TRIBUX_SECRETS_PATH}/<credential_reference>/technical_key
${TRIBUX_SECRETS_PATH}/<credential_reference>/certificate.p12
${TRIBUX_SECRETS_PATH}/<credential_reference>/certificate_password
```

Alternativa PEM cuando no hay PKCS#12:

```text
${TRIBUX_SECRETS_PATH}/<credential_reference>/certificate.pem
${TRIBUX_SECRETS_PATH}/<credential_reference>/private_key.pem
${TRIBUX_SECRETS_PATH}/<credential_reference>/private_key_passphrase   (opcional)
${TRIBUX_SECRETS_PATH}/<credential_reference>/chain.pem                (opcional)
```

Reglas de operación:

- montar el directorio de sólo lectura y con el permiso mínimo posible;
- los valores se leen en el momento de usarse y no se cachean, así que rotar un
  secreto no obliga a reiniciar un worker;
- el `credential_reference` se valida como un único segmento de ruta seguro;
- los secretos nunca se serializan en un job, un log ni una fila de evidencia.

Ejemplo de override de Compose, con rutas propias del operador:

```yaml
services:
  api: &tribux_mounts
    environment:
      TRIBUX_ISSUERS_FILE: /run/config/tribux/issuers.json
      TRIBUX_SECRETS_PATH: /run/secrets/tribux
    volumes:
      - /srv/tribux/config:/run/config/tribux:ro
      - /srv/tribux/secrets:/run/secrets/tribux:ro
  worker: *tribux_mounts
```

### Evidencia

```bash
TRIBUX_EVIDENCE_DISK=evidence
TRIBUX_EVIDENCE_PATH=/var/lib/tribux/evidence
TRIBUX_EVIDENCE_STORE_SOAP_REQUESTS=false
```

El disco por defecto es local y **sólo sirve para desarrollo**: un documento
legal no puede vivir únicamente en el filesystem efímero de un contenedor.
Antes de producción hay que apuntar el disco a almacenamiento de objetos
duradero y con copia de seguridad.

Guardar el request SOAP es opcional porque el envelope contiene el documento
firmado completo, y por lo tanto datos del contribuyente.

## Backups

Antes de producción definir RPO/RTO y probar restauración de:

- base de datos;
- object storage;
- configuración no secreta;
- claves/certificados según política organizacional.
