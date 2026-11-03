---
titulo: Cómo anular un título en AFIP
tema: afip-argentina
orden: 50
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [anular, título, conocimiento, AnularTitulo, baja, AFIP]
relacionados:
  - afip-argentina/registrar-micdta-una-embarcacion
  - afip-argentina/solicitar-anulacion-micdta
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Anula en AFIP un título que ya fue registrado para el viaje. Se usa cuando un título se registró por error o con datos que no corresponden.

## Antes de empezar

- El título está registrado en AFIP: vea [Cómo registrar el MIC/DTA de una embarcación](manual:afip-argentina/registrar-micdta-una-embarcacion).
- Tiene definido el motivo de la anulación. Queda registrado junto con la operación.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA** y entre al viaje desde el recuadro **MIC/DTA** con **Ver Detalles**.
2. En **Gestión de Títulos**, haga clic en **9. AnularTitulo**.
3. En **Anular Título Individual**, seleccione el título que quiere anular. La lista muestra solo los títulos registrados en AFIP que siguen vigentes.
4. Complete **Motivo de anulación** (hasta 200 caracteres). El botón **Anular Título** se habilita cuando hay un título seleccionado y un motivo escrito.
5. Haga clic en **Anular Título** y confirme con **Aceptar**. La anulación se envía a AFIP en ese momento.

## Cómo saber que salió bien

- Aparece **AnularTitulo - Exitoso**, con el mensaje de que el título se anuló en AFIP, y la página se actualiza sola.
- En el recuadro **9. AnularTitulo** aparecen **Última anulación**, **Título anulado** y el estado **Anulado**.
- Si vuelve a abrir **9. AnularTitulo**, el título anulado ya no figura en la lista.

## Si algo falla

- **"No hay títulos registrados para anular":** el viaje no tiene títulos vigentes en AFIP. Revise que el título se haya registrado con éxito.
- **El mensaje de resultado dice "Error" y lista "Errores encontrados:":** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar. Para ver el detalle, abra **📥 Response** en **Últimas 10 transacciones MIC/DTA**.
