---
titulo: Cómo rectificar un convoy en AFIP
tema: afip-argentina
orden: 25
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [rectificar, convoy, barcaza, remolcador, RectifConvoyMicDta, AFIP]
relacionados:
  - afip-argentina/registrar-micdta-convoy
  - afip-argentina/solicitar-anulacion-micdta
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Corrige ante AFIP qué barcazas integran un convoy que ya está registrado. Se envían el remolcador y las barcazas que el viaje tiene en ese momento con su MIC/DTA registrado.

## Antes de empezar

- El convoy está registrado: vea [Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).
- Cada barcaza que debe quedar en el convoy tiene su MIC/DTA registrado en este viaje. La rectificación toma esa lista; no se eligen barcazas en la ventana.
- Tiene definido el motivo, en no más de 50 caracteres.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA** y entre al viaje desde el recuadro **MIC/DTA** con **Ver Detalles**.
2. En **Gestión de Convoy**, haga clic en **6. RectifConvoyMicDta**.
3. En **Rectificar Convoy**, revise **Convoy Actual en AFIP**: **Nro Viaje**, **Remolcador MIC/DTA** y **Barcazas MIC/DTA**. Eso es exactamente lo que se va a enviar. Si falta o sobra una barcaza, cierre la ventana y corrija eso antes de seguir.
4. Complete **Motivo de rectificación**. El botón se habilita cuando hay un motivo escrito.
5. Haga clic en **Rectificar Convoy en AFIP** y confirme con **Aceptar**. La rectificación se envía a AFIP en ese momento.

## Cómo saber que salió bien

- Aparece **RectifConvoyMicDta - Exitoso** y la página se actualiza sola.
- En el recuadro **6. RectifConvoyMicDta** aparecen **Última rectificación** y **Estado**.

## Si algo falla

- **El botón 6. RectifConvoyMicDta está deshabilitado y Gestión de Convoy dice "No Aplicable":** el viaje tiene una sola embarcación. La rectificación es solo para convoyes.
- **"No se encontró un convoy registrado exitosamente para este viaje.":** el convoy todavía no se registró en AFIP. Complete primero ese paso.
- **"Faltan datos del convoy. Cierre y reintente.":** la ventana no terminó de cargar el número de viaje o el MIC/DTA del remolcador. Ciérrela y vuelva a abrirla; si el aviso sigue, revise que el MIC/DTA del remolcador esté registrado.
- **El mensaje de resultado dice "Error" y lista "Errores encontrados:":** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar. Para ver el detalle, abra **📥 Response** en **Últimas 10 transacciones MIC/DTA**.
