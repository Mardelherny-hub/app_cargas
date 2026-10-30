# Revisión del piloto — `primeros-pasos/ingresar-y-perfil.md` — 30/10/2026

**Revisa:** Claudia. **Para:** Charly y su equipo.
**Artículo revisado:** rama `manual/equipo-charly`, commit `cdb1592`.

**Veredicto:** la plantilla se aplicó bien y el control de términos internos pasa. El artículo queda aprobado con los ajustes de abajo. Antes de seguir con el resto de los temas, hay que resolver los tres puntos de "Frenar antes de seguir".

---

## 1. Frenar antes de seguir

1. **Cuentas de prueba.** La revisión automática frenó una prueba por tratarse de una cuenta real. Los recorridos se hacen solo con usuarios de prueba de la empresa de prueba, uno por rol (super-admin, company-admin y user). Si no existen, se crean desde la interfaz de stage. Nunca se usan cuentas de personas reales, aunque sea solo para leer. Por favor, informen qué cuenta usaron en este piloto.
2. **Versión de stage.** Informaron que stage corre `c7e9cf9`, pero ese commit no existe en GitHub: `main` está en `c62042a` y ninguna rama remota lo contiene. Si stage corre código que no está en el repo, lo que validamos ahí puede no coincidir con lo que se publica desde `main`. Hay que averiguar qué es `c7e9cf9` y avisar a Víctor antes de seguir validando.
3. **Hipótesis de seguridad (para triage, no tocar).** El registro público de Fortify está activo (`Features::registration()` en `config/fortify.php`, vista `auth/register.blade.php`), y `CreateNewUser` crea usuarios sin rol. Si `/register` responde en stage, cualquiera podría crearse una cuenta. Solo verificar si la página carga: **no** crear cuentas. Reportarlo con severidad "Bloquea" si se confirma.

## 2. Ajustes al artículo

1. **Para qué sirve:** describe la tarea, sin imperativo. Propuesta: "Permite ingresar al sistema con su cuenta y actualizar el nombre que aparece en su perfil."
2. **Paso 6, efecto del cambio de correo:** agregar "Si cambia **Email**, desde ese momento ingresa con el correo nuevo." Lo verifiqué en el código: el sistema no pide confirmar el correo nuevo, así que el cambio vale de inmediato para el próximo ingreso.
3. **Paso 8 y "Cómo saber que salió bien":** usar primero la confirmación que muestra la pantalla. Al guardar, aparece **Saved.** junto al botón **SAVE** (`profile/update-profile-information-form.blade.php`, línea 88). Volver a abrir **Profile** queda como comprobación opcional.
4. **Botones en mayúsculas (LOG IN, SAVE):** correcto, se escriben como se ven en pantalla.
5. **Recorrido con super-admin y user:** sigue pendiente. El artículo pasa a `publicado` recién cuando se complete con usuarios de prueba.

## 3. Calibración de la plantilla (aplica a todos los artículos)

- **"Para qué sirve"** describe la tarea en tercera persona ("Permite…", "Registra…"). El imperativo queda para los pasos.
- **"Cómo saber que salió bien"** empieza por el mensaje o el cambio que muestra la pantalla al terminar. Las comprobaciones extra van después.
- **Pasos con consecuencias:** si un paso cambia algo que afecta el uso posterior (correo de ingreso, ambiente, estado del viaje), se avisa en el mismo paso.
- **Interfaz en inglés:** se documenta como se ve hoy. Si más adelante se traduce, hay que actualizar los artículos.

## 4. Hallazgos

| ID | Pantalla | Clasificación | Detalle | Decisión |
| --- | --- | --- | --- | --- |
| PRIMEROS-01 | Ingreso y perfil | Mejora (cosmético) | Interfaz mezclada en español e inglés: `Profile`, `Name`, `SAVE`, mensaje de credenciales. La app corre con `APP_LOCALE=en` y no hay archivos de idioma. | Se documenta como está; la traducción queda para triage |
| PRIMEROS-02 | Registro (`/register`) | Hipótesis de seguridad | Ver punto 1.3 | Verificar en stage sin crear cuentas |

## 5. Índice: dos artículos más para `primeros-pasos`

El perfil tiene más tareas habilitadas que vale la pena documentar por separado, después del piloto:

- `primeros-pasos/cambiar-la-contrasena.md` (sección **Update Password**).
- `primeros-pasos/recuperar-la-contrasena.md` (enlace **Forgot your password?** del ingreso). Antes de escribirlo, hay que confirmar en stage que el correo de recuperación llega, usando una cuenta de prueba.

## 6. Sinónimos

Agregados a `_sinonimos.yml` en `manual/equipo-claudia` (commit `9340c4d`):

- ingresar / acceder / iniciar sesión / login
- perfil / datos personales
- correo / email / e-mail

## 7. Cómo seguir

1. Aplicar los ajustes de la sección 2 en el mismo artículo y en la misma rama.
2. Resolver los puntos 1.1 y 1.2 e informarlos.
3. Con eso resuelto, seguir con los temas habilitados: `primeros-pasos`, `administracion`, `mi-empresa`, `datos-maestros` y `reportes`, aplicando la calibración de la sección 3.
