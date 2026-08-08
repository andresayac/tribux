# ADR 0014 — Envelope WS-Security explícito

**Estado:** Accepted

## Contexto

El WCF de DIAN declara SOAP 1.2, WS-Addressing y una policy WS-Security que exige
control sobre `Timestamp`, token X.509, cabecera `To`, canonicalización,
referencias y algoritmos. El cliente SOAP nativo de PHP no ofrece una frontera
clara para construir y probar ese perfil sin callbacks o manipulación posterior
del XML firmado.

## Decisión

`tribux/dian` construye el envelope con DOM/libxml y firma con las mismas
`SigningCredentials` basadas en OpenSSL. La construcción retorna un
`DianSoapMessage` inmutable y no realiza red, retries ni parsing de respuestas.
Así el transporte HTTP puede probarse y reemplazarse por separado.

El perfil por defecto usa `ThumbprintSHA1`, como exige
`RequireThumbprintReference` en el WSDL vigente. La referencia directa al
`BinarySecurityToken` mostrada en la guía oficial se conserva como opción
explícita mientras Q-010 siga abierta; no se selecciona silenciosamente.

## Consecuencias

- la firma SOAP y la firma XAdES comparten custodia de credenciales, no formato;
- el XML firmado puede verificarse sin conectarse a DIAN;
- el transporte deberá enviar el XML sin reformatearlo y usar el Content-Type
  SOAP 1.2 generado;
- los timeouts, errores HTTP, SOAP Faults y respuestas DIAN pertenecen al próximo
  adaptador;
- solo una prueba en habilitación resolverá Q-010 y permitirá retirar el modo que
  no corresponda.

## Fuentes

- WSDL `WcfDianCustomerServices`, policy
  `WSHttpBinding_IWcfDianCustomerServices_policy`, verificado 2026-08-08.
- Guía DIAN para consumo de Web Services, secciones 2.6 y 2.7.
- Anexo Técnico FEV 1.9, capítulo 7.
