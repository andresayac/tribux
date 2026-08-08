# Mantenimiento y cambios DIAN

## Objetivo

Poder reaccionar a un nuevo anexo o resolución sin reescribir la API.

## Proceso sugerido

1. detectar publicación oficial;
2. abrir issue `dian-change`;
3. guardar referencias y hashes;
4. producir diff semántico del anexo/catálogos;
5. clasificar: breaking fiscal, additive, clarificación, transporte;
6. añadir fixtures de nueva versión;
7. implementar detrás de versión de especificación;
8. probar en habilitación;
9. publicar release notes/migration guide;
10. mantener ventana de coexistencia si DIAN la permite.

## No hacer

- reemplazar catálogos sin versionado;
- borrar soporte anterior antes de conocer fechas obligatorias;
- asumir que una nueva resolución siempre cambia el XML;
- modificar API pública si un adapter interno resuelve el cambio.
