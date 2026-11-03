---
titulo: Cómo generar las cartas de aviso de arribo
tema: reportes
orden: 40
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [cartas de aviso, aviso de arribo, aviso de llegada, consignatario, PDF, ZIP, viaje]
relacionados: [reportes/listado-de-conocimientos, datos-maestros/registrar-un-cliente]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Genera las cartas que avisan a cada consignatario el arribo de su mercadería. Arma una carta por consignatario con todos sus conocimientos del viaje. Permite descargar la carta de un solo consignatario en PDF o todas juntas en un archivo ZIP.

## Antes de empezar

- El viaje está en estado **Completado** y tiene conocimientos con consignatario asignado. La lista muestra los 50 viajes más recientes que cumplen esas condiciones.
- El viaje tiene fecha estimada de llegada, embarcación y puerto de destino.
- Los datos de contacto del consignatario (dirección, correo y teléfono) salen de su ficha de cliente: vea [Cómo registrar y consultar un cliente](manual:datos-maestros/registrar-un-cliente).

## Pasos

1. En el menú lateral, haga clic en **Reportes** para desplegarlo y elija **Cartas de Aviso**. Se abre [Cartas de Aviso de Llegada](ruta:company.reports.arrival-notices).
2. En **Seleccionar Viaje**, elija el **Viaje**. Aparece **Información del viaje** con **Total BLs:**, **Consignatarios únicos:** y **Cartas a generar:**.
3. En **Consignatario (opcional)**, deje **-- Todos los consignatarios (ZIP) --** para generar todas las cartas, o elija un consignatario para generar solo la suya.
4. Haga clic en el botón de generación. Según lo elegido dice **Generar Cartas PDF**, **Generar Todas las Cartas (ZIP)** o **Generar Carta Individual PDF**. Mientras trabaja muestra **Generando...**.

## Cómo saber que salió bien

Se descarga **carta_aviso.pdf** si eligió un consignatario, o **cartas_aviso.zip** con un PDF por consignatario si dejó todos. El botón vuelve a mostrar su texto. Abra las cartas y verifique el consignatario, sus conocimientos y el lugar de retiro.

## Si algo falla

- Si el viaje no aparece en la lista, revise que esté en estado **Completado** y que tenga conocimientos con consignatario.
- **Error cargando datos del viaje**: no se pudieron leer los consignatarios del viaje. Recargue la página y vuelva a elegir el viaje.
- **Error generando las cartas de aviso**: las cartas no se pudieron generar. Vuelva a intentar; si se repite, comunique el viaje elegido a soporte.
- Si el archivo descargado no abre, al viaje le falta algún dato necesario (fecha estimada de llegada, embarcación, puerto de destino o consignatario en sus conocimientos). Complételo y vuelva a generar.
- Si la carta dice **Dirección no disponible**, el consignatario no tiene dirección en su contacto principal. Complétela en su ficha de cliente y vuelva a generar.
