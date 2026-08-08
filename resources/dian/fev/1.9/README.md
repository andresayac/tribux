# Artefactos DIAN FEV 1.9

El repositorio conserva solamente metadatos, URLs oficiales y hashes. Los
artefactos descargados —incluida la política de firma— no se redistribuyen
porque sus condiciones de redistribución aún no han sido revisadas.

Para obtener una copia local verificada:

```bash
composer dian:fetch-fev19
composer dian:extract-fev19
```

Los archivos se guardan en `var/dian/fev/1.9`, una ruta excluida de Git. Un
cambio de contenido en la fuente oficial hace fallar la descarga por hash; no
se debe actualizar el manifiesto sin revisar el nuevo artefacto y documentar
el cambio.

Para validar estructuralmente un XML con los XSD oficiales extraídos:

```bash
composer dian:validate-xml -- path/to/invoice.xml invoice
```

Tipos admitidos: `invoice`, `credit-note`, `debit-note`,
`application-response` y `attached-document`. El comando ejecuta XSD; no ejecuta
todavía el Schematron/XSLT 3.0 ni reemplaza una prueba en habilitación.
