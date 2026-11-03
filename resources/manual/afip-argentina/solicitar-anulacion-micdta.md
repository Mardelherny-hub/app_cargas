---
titulo: Cómo solicitar la anulación de un MIC/DTA
tema: afip-argentina
orden: 60
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [anular, anulación, MIC/DTA, SolicitarAnular, baja, AFIP]
relacionados:
  - afip-argentina/registrar-micdta-una-embarcacion
  - afip-argentina/registrar-micdta-convoy
  - afip-argentina/anular-un-titulo
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Pide a AFIP que anule un MIC/DTA ya registrado para el viaje. El servicio aduanero evalúa la solicitud y puede aprobarla o rechazarla.

## Antes de empezar

- El MIC/DTA está registrado: vea [Cómo registrar el MIC/DTA de una embarcación](manual:afip-argentina/registrar-micdta-una-embarcacion) o [Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).
- Tiene definido el motivo, en no más de 50 caracteres.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA** y entre al viaje desde el recuadro **MIC/DTA** con **Ver Detalles**.
2. En **Anulaciones y Testing**, haga clic en **16. SolicitarAnular**.
3. En **Solicitar Anulación MIC/DTA**, elija el MIC/DTA en **ID MIC/DTA**. La lista muestra los MIC/DTA registrados para este viaje. Si no aparece, escríbalo en el campo de abajo (hasta 16 caracteres); tiene que ser un MIC/DTA de este mismo viaje.
4. Complete **Motivo de anulación**.
5. Haga clic en **Solicitar Anulación** y confirme con **Aceptar**. La solicitud se envía a AFIP en ese momento.

## Cómo saber que salió bien

- Aparece **SolicitarAnularMicDta - Exitoso** y la página se actualiza sola.
- En el recuadro **16. SolicitarAnular** aparecen **Última solicitud**, **MIC/DTA** y **Estado**.

Esto confirma que AFIP recibió la solicitud, no que la anulación esté aprobada. Antes de volver a registrar el MIC/DTA, confirme con la Aduana que la aprobó.

## Si algo falla

- **"Debe seleccionar o ingresar un ID MIC/DTA":** elija un MIC/DTA de la lista o escríbalo.
- **"Debe ingresar un motivo de anulación":** complete el motivo.
- **"MIC/DTA no encontrado en transacciones previas del viaje":** el número escrito no corresponde a un MIC/DTA registrado en este viaje. Elíjalo de la lista.
- **Aparece SolicitarAnularMicDta - Error con otro mensaje:** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar. Para ver el detalle, abra **📥 Response** en **Últimas 10 transacciones MIC/DTA**.
