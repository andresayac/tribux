# ADR 0012 — SaxonJ-HE para Schematron XSLT 3.0

**Estado:** Accepted

## Contexto

La caja FEV 1.9 compila sus reglas Schematron como XSLT 3.0. libxslt, usado por
la extensión XSL de PHP, implementa XSLT 1.0 y no puede ejecutar esas reglas.

## Decisión

Usar SaxonJ-HE 12.10 como runtime externo, fijado por URL, bytes y SHA-256. Es
la línea 12 estable publicada por Saxonica, implementa XSLT 3.0 en la edición
open source y usa licencia MPL-2.0. El ZIP no se versiona: se descarga y extrae
de forma reproducible, conservando `notices/LICENSE.txt`.

`SaxonSchematronValidator` invoca Java sin shell, desactiva DTD y extensiones,
fuerza UTF-8, impone timeout, usa archivos temporales privados y conserva nivel,
código y texto original de cada hallazgo DIAN. JAR, dependencias y binario Java
son configuración explícita.

## Consecuencias

- la instalación completa requiere Java y aproximadamente 7 MB de artefactos;
- la librería sigue siendo usable sin Saxon para CUFE, modelo, XML y XSD;
- CI y contenedores deberán instalar el runtime mediante el manifiesto;
- un proceso XSLT exitoso no equivale a documento válido: los mensajes `Fatal`
  determinan el resultado de compliance;
- discrepancias entre anexo y XSL se registran como preguntas abiertas, sin
  modificar reglas oficiales localmente.

## Fuentes

- https://www.saxonica.com/html/download/java.html
- https://www.saxonica.com/html/documentation12/using-xsl/xslt30.html
- https://www.saxonica.com/html/documentation12/about/installationjava/prerequisites.html
- https://www.saxonica.com/html/license/terms.html
