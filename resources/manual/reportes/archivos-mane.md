---
titulo: Cómo generar un archivo MANE para Malvina
tema: reportes
orden: 50
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [MANE, Malvina, ID María, archivo, Aduana, exportar, viaje]
relacionados: [viajes-y-cargas/crear-un-viaje-a-mano, viajes-y-cargas/cargar-conocimientos-e-items]
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Genera el archivo de texto MANE de un viaje para importarlo en el sistema Malvina de la Aduana. Cada archivo lleva en la primera línea el ID María de la empresa, que la identifica ante la Aduana.

## Antes de empezar

- Su empresa tiene un ID María cargado. Lo carga el administrador del sistema; sin ese dato, la opción no aparece en el menú.
- El viaje tiene al menos una carga con sus conocimientos completos: vea [Cómo crear un viaje a mano y agregar su carga](manual:viajes-y-cargas/crear-un-viaje-a-mano) y [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).
- Cada archivo corresponde a un solo viaje.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Archivos MANE/Malvina**. Arriba a la derecha aparece **ID María:** con el identificador de su empresa.
2. En **Viajes Disponibles para MANE**, busque el viaje por su número, su ruta y la fecha de **Salida:**. Si hay muchos viajes, use la paginación al pie de la lista.
3. Haga clic en **Generar** en la fila del viaje. No hace falta marcar la casilla del viaje.
4. Espere la descarga. Aparece el mensaje **Archivo MANE generado exitosamente** con el enlace **Descargar**, y a los pocos segundos la página se recarga.
5. Para volver a bajar un archivo, búsquelo en **Archivos Generados Recientemente** y haga clic en **Descargar**.

## Cómo saber que salió bien

Se descarga un archivo con extensión `.mar` cuyo nombre empieza con `MANE_` y sigue con el ID María, el número de viaje, la fecha y la hora. Después de la recarga, el archivo figura en **Archivos Generados Recientemente**, que muestra los últimos 10.

## Si algo falla

- **No hay viajes disponibles**: ninguno de sus viajes tiene cargas. Agregue la carga al viaje y vuelva a la pantalla.
- **Error generando archivo MANE:** seguido del motivo: algún dato del viaje no permite armar el archivo. Si el motivo empieza con el número de un conocimiento, corrija ese conocimiento (por ejemplo, **sin NCM ni descripción.** o **cargador sin CUIT ni nombre.**) y vuelva a generar.
- **La empresa debe estar activa para generar archivos MANE.**: la empresa está inactiva. Consulte con el administrador del sistema.
- **Error de conexión:** seguido de un detalle: la página no pudo comunicarse con el servidor. Revise su conexión y vuelva a hacer clic en **Generar**.
