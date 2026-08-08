# Artefactos DIAN FEV 1.9

El repositorio conserva solamente metadatos, URLs oficiales y hashes. Los
artefactos descargados no se redistribuyen porque sus condiciones de
redistribución aún no han sido revisadas.

Para obtener una copia local verificada:

```bash
composer dian:fetch-fev19
```

Los archivos se guardan en `var/dian/fev/1.9`, una ruta excluida de Git. Un
cambio de contenido en la fuente oficial hace fallar la descarga por hash; no
se debe actualizar el manifiesto sin revisar el nuevo artefacto y documentar
el cambio.
