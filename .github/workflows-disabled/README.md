# Workflows desactivados

Los workflows y la configuración de Dependabot se conservan aquí para evitar
ejecuciones automatizadas. GitHub solo ejecuta Actions desde
`.github/workflows/` y solo activa Dependabot desde `.github/dependabot.yml`.

Para reactivar el quality gate, mover `seed-quality.yml` nuevamente a
`.github/workflows/` y revisar antes la configuración de billing, límites de uso
y matriz de versiones de PHP.

Para reactivar Dependabot, mover `dependabot.yml` nuevamente a `.github/`.
