# Plan 001: Retirar credenciales embebidas y rotarlas

> **Instrucciones para el ejecutor**: Sigue este plan paso a paso. Ejecuta cada
> verificación y confirma el resultado esperado antes de continuar. Si ocurre
> una condición de parada, detente y reporta; no improvises. Al terminar,
> actualiza la fila 001 en `plans/README.md`, salvo que un revisor mantenga el
> índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- Dockerfile entrypoint.sh seed/seed.sh .env.example README.md`
> y `git status --short -- Dockerfile entrypoint.sh seed/seed.sh .env.example README.md`.
> Este plan fue escrito con cambios locales ya presentes. No los descartes. Si
> los bloques de “Estado actual” no coinciden, detente y reporta.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: HIGH
- **Depende de**: none
- **Categoría**: security
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

La imagen y el script de seed contienen contraseñas de base de datos como
valores predeterminados. Una credencial versionada debe tratarse como expuesta,
aunque el repositorio sea privado. Al terminar, la imagen no podrá arrancar sin
secretos inyectados, los logs no los imprimirán y las credenciales de producción
habrán sido rotadas fuera del repositorio.

## Estado actual

- `Dockerfile:45-48` define nombre, usuario y dos contraseñas de MariaDB con
  `ENV`; las contraseñas quedan dentro de capas e historial de la imagen.
- `seed/seed.sh:5-8` permite un valor predeterminado embebido para la contraseña
  de WordPress/MariaDB.
- `entrypoint.sh:49-57` interpola nombre, usuario y contraseña directamente en
  un heredoc SQL.
- `.env.example:7-16` ya usa marcadores para las contraseñas, pero no documenta
  `MARIADB_ROOT_PASSWORD` ni la rotación.
- `README.md:38-47` indica copiar variables, sin exigir que todos los secretos
  estén definidos antes del arranque.

Extractos identificadores, sin reproducir valores secretos:

```text
Dockerfile:47  ENV MARIADB_PASSWORD=<valor embebido>
Dockerfile:48  ENV MARIADB_ROOT_PASSWORD=<valor embebido>
seed/seed.sh:7 DB_PASS="${WORDPRESS_DB_PASSWORD:-<valor embebido>}"
entrypoint.sh:52 CREATE USER ... IDENTIFIED BY '${MARIADB_PASSWORD}';
```

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis shell | `wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh && bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"` | exit 0 |
| Construcción | `docker build -t culturinfo-plan-001 .` | exit 0 |
| Buscar defaults secretos | `git grep -n -E 'ENV MARIADB_(ROOT_)?PASSWORD=|WORDPRESS_DB_PASSWORD:-' -- Dockerfile seed/seed.sh` | sin salida, exit 1 |
| Ver estado | `git status --short` | solo archivos previstos y cambios preexistentes |

## Alcance

**Dentro del alcance**:

- `Dockerfile`
- `entrypoint.sh`
- `seed/seed.sh`
- `.env.example`
- `README.md`

**Fuera del alcance**:

- No reescribir historial Git ni borrar commits.
- No guardar credenciales nuevas en archivos, comandos versionados o planes.
- No cambiar el proveedor de base de datos ni separar MariaDB del contenedor.
- No modificar tema, contenido editorial ni plugins.

## Flujo Git

- Rama: `codex/001-retirar-secretos`
- Preserva el árbol sucio; no uses `git reset`, `git checkout --` ni limpiezas.
- Commits sugeridos: `security: require runtime database secrets` y, si la
  documentación se separa, `docs: document secret rotation`.
- No hagas push ni abras PR sin orden del operador.

## Pasos

### Paso 1: Exigir secretos en tiempo de ejecución

En `Dockerfile`, elimina los `ENV` de contraseña de MariaDB. Conserva los
defaults no sensibles de base y usuario solamente si siguen siendo útiles.

En la parte inicial de `entrypoint.sh`, antes de iniciar MariaDB, valida con
expansión shell obligatoria que `MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD` y
`WP_ADMIN_PASSWORD` existan y no estén vacíos. El error debe nombrar la variable,
nunca su valor, y terminar con código distinto de cero. Acepta
`WORDPRESS_DB_PASSWORD` como alias solo si se normaliza una vez hacia
`MARIADB_PASSWORD` y nunca se imprime.

En `seed/seed.sh`, elimina el fallback de contraseña. Usa exclusivamente la
variable normalizada que le entrega el entrypoint y falla si está vacía.

**Verificar**:
`git grep -n -E 'ENV MARIADB_(ROOT_)?PASSWORD=|WORDPRESS_DB_PASSWORD:-' -- Dockerfile seed/seed.sh`
→ sin salida y exit 1.

### Paso 2: Evitar interpolación SQL insegura

En `entrypoint.sh`, valida `MARIADB_DATABASE` y `MARIADB_USER` contra
`^[A-Za-z0-9_]+$`; detén el arranque si no cumplen. Para la contraseña, no la
concatentes sin escape en SQL: genera el literal con una función que duplique
comillas simples y barras invertidas antes de usarlo en el heredoc. Conecta a
MariaDB como root usando `MARIADB_ROOT_PASSWORD` después de la inicialización y
asegura que el root local reciba esa contraseña en la primera creación.

Mantén idempotencia: un volumen existente debe poder arrancar con las mismas
credenciales y un volumen nuevo debe crear base/usuarios. No imprimas los SQL ni
actives `set -x`.

**Verificar**:
`wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh && bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"`
→ exit 0.

### Paso 3: Probar fallo cerrado y arranque correcto

Construye la imagen. Ejecuta primero un contenedor sin secretos con nombre
exacto `culturinfo-plan-001-missing`; debe detenerse y el log debe listar los
nombres de variables faltantes, sin valores. Después elimínalo.

Para la prueba positiva, crea en PowerShell valores efímeros en variables
locales (`$planDb`, `$planRoot`, `$planAdmin`) usando GUID, sin imprimirlos.
Arranca `culturinfo-plan-001-ok` en el puerto 8091 con las tres variables y
`WP_SITEURL=http://localhost:8091`. Espera a que `/wp-login.php` responda 200.

**Verificar**:
`docker inspect -f '{{.State.ExitCode}}' culturinfo-plan-001-missing`
→ código distinto de 0.

**Verificar**:
`(Invoke-WebRequest -UseBasicParsing http://localhost:8091/wp-login.php).StatusCode`
→ `200`.

### Paso 4: Documentar y ejecutar la rotación operativa

Actualiza `.env.example` para listar los cuatro secretos requeridos sin valores
reales: contraseña de usuario DB, contraseña root DB, contraseña admin WP y las
salts/keys si se externalizan. En `README.md`, documenta:

1. Generar secretos aleatorios de al menos 32 bytes.
2. Cargarlos en el gestor de secretos de Coolify, no en Git.
3. Hacer respaldo de base y volumen antes de rotar.
4. Rotar el usuario y root de MariaDB, actualizar variables y redesplegar.
5. Cambiar la contraseña del administrador de WordPress.
6. Invalidar sesiones/salts si existe sospecha de exposición.

La rotación real de producción es una acción manual del operador. No la simules
ni la omitas silenciosamente: deja la fila del plan en `BLOCKED` hasta que el
operador confirme que se completó, o `DONE` cuando exista confirmación.

**Verificar**:
`git grep -n -E 'MARIADB_ROOT_PASSWORD|MARIADB_PASSWORD|WP_ADMIN_PASSWORD' -- .env.example README.md`
→ aparecen los nombres y documentación, nunca valores reales.

## Plan de pruebas

- Caso negativo: el contenedor sin secretos falla antes de crear WordPress.
- Caso feliz: volumen nuevo, secretos efímeros, instalación completa y login 200.
- Idempotencia: reiniciar `culturinfo-plan-001-ok`; `/wp-login.php` continúa 200.
- Inyección: probar un nombre DB con guion o comilla; el arranque debe fallar
  con error de validación antes de ejecutar SQL.
- Inspeccionar `docker logs culturinfo-plan-001-ok`; ningún valor efímero debe
  estar presente.

## Criterios de finalización

- [ ] No existen defaults de contraseñas en `Dockerfile` ni `seed/seed.sh`.
- [ ] El arranque falla cerrado cuando falta cualquier secreto obligatorio.
- [ ] Los identificadores DB se validan y la contraseña se escapa antes del SQL.
- [ ] La imagen arranca desde un volumen vacío con secretos efímeros.
- [ ] Los logs no contienen valores secretos.
- [ ] `.env.example` y `README.md` documentan el contrato y la rotación.
- [ ] El operador confirmó la rotación de producción.
- [ ] No se modificó ningún archivo fuera del alcance, salvo `plans/README.md`.
- [ ] La fila 001 de `plans/README.md` está actualizada.

## Condiciones de parada

Detente y reporta si:

- Los extractos actuales no coinciden o hay cambios nuevos no entendidos.
- No hay acceso autorizado al gestor de secretos/DB de producción para rotar.
- El volumen existente usa credenciales distintas y cambiarlas impediría el
  arranque sin una migración explícita.
- La solución exige imprimir, registrar o versionar un secreto.
- Una verificación falla dos veces después de una corrección razonable.

## Notas de mantenimiento

- Revisar con especial cuidado el escape SQL y el camino de volúmenes existentes.
- Una contraseña borrada del estado actual sigue existiendo en el historial; la
  rotación es obligatoria aunque no se reescriba Git.
- Coolify debe ser la fuente de verdad de secretos; el Dockerfile no debe volver
  a introducir defaults por conveniencia.
