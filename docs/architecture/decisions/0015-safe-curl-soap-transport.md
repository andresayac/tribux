# ADR 0015 — Transporte SOAP cURL sin retries implícitos

**Estado:** Accepted

## Contexto

El envelope WS-Security debe llegar byte a byte al WCF. El transporte necesita
timeouts, verificación TLS y errores observables, pero no puede decidir que un
timeout de envío es seguro para repetir: DIAN pudo recibir y procesar la solicitud
aunque el cliente no haya obtenido respuesta.

## Decisión

`CurlDianSoapTransport` implementa el puerto `DianSoapTransport` con HTTPS como
único protocolo, TLS 1.2 mínimo, verificación de CA y hostname, cero redirects,
timeout de conexión, timeout total y límite de bytes de respuesta. No registra el
request, no expone credenciales y no hace retries.

Los fallos de cURL se convierten en `DianTransportException`, conservando código,
mensaje original y categoría normalizada. Respuestas HTTP, incluso 4xx/5xx, se
devuelven íntegras para que el parser posterior pueda reconocer SOAP Faults y
preservar evidencia.

## Consecuencias

- `tribux/dian` requiere `ext-curl`;
- la política de retry deberá considerar operación, idempotencia y consulta de
  estado, no solo el tipo de excepción;
- el tamaño máximo de respuesta evita consumo de memoria no acotado;
- una CA alternativa solo puede configurarse mediante un archivo existente; no
  existe opción para desactivar verificación TLS;
- la integración local usa un servidor y certificados efímeros, pero la
  interoperabilidad real sigue pendiente de habilitación.
