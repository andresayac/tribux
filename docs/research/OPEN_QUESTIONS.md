# Preguntas abiertas

No convertir una hipótesis en código de compliance hasta cerrarla con fuente/prueba.

| ID | Pregunta / por qué importa | Fuente consultada | Hipótesis explícita | Validación pendiente | Estado |
|---|---|---|---|---|---|
| Q-001 | ¿Qué artefactos FEV 1.9 pueden redistribuirse? Define el packaging. | Página DIAN, ZIP y avisos internos | **Hipótesis:** descargar localmente es más seguro que redistribuir. | Revisión legal/licencia oficial. | Abierta |
| Q-002 | ¿Qué operaciones SOAP exige cada flujo? Define el puerto. | Anexo 1.9 cap. 7, guía y WSDL | **Hipótesis:** la primera factura necesita las cinco operaciones documentadas; notas/eventos ampliarán el contrato. | Capturas reales de habilitación y matriz para notas/eventos. | Parcial |
| Q-003 | ¿Cuál es el perfil completo XAdES? Un error causa rechazo. | Anexo 1.9, 6.5.10; OASIS/W3C enlazados | **Hipótesis:** un firmador dedicado podrá emitir XAdES-EPES sin acoplar dominio. | Política/hash exactos y fixture firmado aceptado. | Parcial |
| Q-004 | ¿Qué listas deben versionarse y cómo se actualizan? Evita códigos obsoletos. | Caja FE V19 (36 genericode, 39 XLSX) | **Hipótesis:** catálogo versionado detrás de un puerto. | Fecha/vigencia y dependencias por lista. | Abierta |
| Q-005 | ¿Qué evidencia conservar y por cuánto tiempo? Impacta storage/auditoría. | Sin fuente concluyente revisada | **Hipótesis:** conservar XML y respuesta íntegra con política configurable. | Norma oficial por actor y documento. | Abierta |
| Q-006 | ¿Cómo describir jurídicamente el uso open source? Impacta gobernanza. | Modalidades DIAN revisadas de forma inicial | **Hipótesis:** Tribux distribuye software, no opera como PT por defecto. | Revisión jurídica independiente. | Abierta |
| Q-007 | ¿Qué estados son terminales o reintentables? Evita duplicados. | WSDL y mensajes generales | **Hipótesis:** solo fallos de transporte demostrablemente seguros se reintentan. | Taxonomía oficial + pruebas de habilitación. | Abierta |
