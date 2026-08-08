# ADR 0013 — Firma XAdES con DOM/libxml y OpenSSL

**Estado:** Accepted

## Contexto

FEV 1.9 exige una firma XAdES-EPES enveloped cuya estructura, canonicalización,
referencias y propiedades son observables por DIAN. El dominio no debe conocer
certificados ni depender de Laravel. Las claves privadas y contraseñas tampoco
pueden convertirse en valores serializables o persistirse accidentalmente.

## Decisión

`tribux/dian` construye el perfil versionado con DOM/libxml y delega las
operaciones RSA/X.509 a la extensión nativa OpenSSL. `SigningCredentials`
importa PEM o PKCS#12, comprueba clave RSA, correspondencia con el certificado y
vigencia al firmar. Conserva el recurso de clave como estado privado y no expone
la contraseña ni el PEM privado.

`Fev19XadesSigner` recibe XML UBL sin firma, credenciales, rol y hora explícita;
añade la segunda extensión UBL, calcula las tres referencias y serializa una sola
vez. El firmador no carga archivos, no resuelve secretos, no almacena artefactos
y no se acopla al transporte SOAP.

## Consecuencias

- PHP requiere `ext-openssl`, `ext-dom` y `ext-libxml`;
- el paquete puede usarse directamente como librería sin Laravel;
- el proveedor de secretos y la persistencia de evidencias pertenecen a la
  aplicación;
- HSM/KMS requerirá introducir un puerto de operación de firma, sin cambiar el
  modelo de dominio;
- la validación local criptográfica y XSD no equivale a aceptación DIAN: el
  perfil seguirá pendiente hasta probarse en habilitación con credenciales reales;
- los tests solo generan material efímero y nunca versionan claves privadas.

## Fuentes

- Anexo Técnico FEV 1.9, sección 6.5.10, páginas 305-319.
- Política de firma DIAN v2, secciones 5.2, 7, 10.2 y 12.
- Caja FE V19 v2026, ejemplo `ejemplificacionIBUA-3.xml`.
- W3C XML Signature Syntax and Processing.
