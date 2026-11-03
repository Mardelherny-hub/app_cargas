---
titulo: Cómo recuperar su contraseña
tema: primeros-pasos
orden: 30
roles: [super-admin, company-admin, user]
palabras_clave: [contraseña, olvidé, recuperar, restablecer, clave, correo, acceso, ingresar]
relacionados: [primeros-pasos/ingresar-y-perfil]
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Permite elegir una contraseña nueva cuando no recuerda la actual y no puede ingresar al sistema.

## Antes de empezar

- Tenga acceso al correo electrónico registrado en su cuenta: el enlace para restablecer la contraseña se envía solo a esa dirección.
- La contraseña nueva debe tener al menos 8 caracteres.
- Si recuerda su contraseña actual y solo quiere cambiarla, vea [Cómo cambiar su contraseña](manual:primeros-pasos/cambiar-la-contrasena).

## Pasos

1. En la pantalla **Iniciar Sesión**, haga clic en **¿Olvidaste tu contraseña?**, junto a **Remember me**.
2. Haga clic en el enlace **Forgot your password?**, que aparece en el mismo lugar.
3. En la pantalla que se abre, complete **Email** con el correo de su cuenta.
4. Haga clic en **Email Password Reset Link**.
5. Abra el correo que recibió en la dirección de su cuenta y haga clic en el enlace para restablecer la contraseña. El enlace vence a los 60 minutos.
6. En la pantalla que se abre, revise que **Email** muestre el correo de su cuenta.
7. Escriba la contraseña nueva en **Password** y repítala en **Confirm Password**.
8. Haga clic en **Reset Password**.
9. En **Iniciar Sesión**, ingrese con su correo y la contraseña nueva.

## Cómo saber que salió bien

Después del paso 4 aparece un mensaje en verde, encima del formulario, que confirma el envío del enlace.

Después del paso 8, el sistema lo lleva a la pantalla **Iniciar Sesión**. Si puede ingresar con la contraseña nueva, el cambio quedó hecho.

## Si algo falla

- **No veo Forgot your password?:** haga clic primero en **¿Olvidaste tu contraseña?**; el enlace aparece en su lugar.
- **Whoops! Something went wrong.**: debajo se indica el motivo; por ejemplo, que el correo no corresponde a una cuenta, que la contraseña nueva es demasiado corta o que la confirmación no coincide. Corrija el dato y vuelva a intentarlo. Si acaba de pedir un enlace, espere un minuto antes de pedir otro.
- **El correo no llega:** revise la carpeta de correo no deseado. Recuerde que el enlace se envía solo al correo registrado en su cuenta. Si aun así no llega, consulte a su administrador:
  - El administrador de su empresa puede asignarle una contraseña nueva al editar su operador en **Gestión › Operadores**, con **Nueva Contraseña** y **Confirmar Nueva Contraseña**, y luego **Guardar Cambios**.
  - El super administrador puede asignarle una contraseña nueva a cualquier usuario desde **Administración › Usuarios**, con **Editar**: completa **Nueva contraseña** y **Confirmar nueva contraseña**, y guarda con **Actualizar Usuario**.
- **El enlace del correo ya no funciona:** venció o ya se utilizó. Vuelva a pedir uno desde el paso 1.
- **Estas credenciales no coinciden con nuestros registros.**: si aparece al ingresar con la contraseña nueva, revise el correo y la contraseña. Si el problema continúa, su cuenta puede estar desactivada; consulte a su administrador.
