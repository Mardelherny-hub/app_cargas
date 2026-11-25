---
titulo: Cómo revertir una importación
tema: importar-manifiestos
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [revertir, deshacer, anular importación, borrar viaje importado, reimportar, ya fue importado, revertir lo agregado, viaje completo]
relacionados: [importar-manifiestos/historial-de-importaciones, importar-manifiestos/importar-un-manifiesto, importar-manifiestos/agregar-un-archivo-a-un-viaje-existente]
estado: publicado
verificado:
  version: eb547c9c
  fecha: 2026-11-24
  entorno: stage
---

## Para qué sirve

Deshace una importación para corregirla o para volver a importar un archivo corregido. Según cómo se cargó el archivo, puede elegir:

- **Revertir importación**: elimina todo lo que creó el archivo, incluido el viaje.
- **Revertir lo agregado**: en un archivo [agregado a un viaje existente](manual:importar-manifiestos/agregar-un-archivo-a-un-viaje-existente), elimina solo lo que sumó ese archivo. El resto del viaje no cambia.
- **Revertir viaje completo**: en un viaje con archivos agregados, revierte todas sus importaciones. Si una importación creó el viaje, también lo elimina. Si el viaje se creó a mano, conserva el viaje y las cargas creadas a mano.

## Antes de empezar

- Solo se puede revertir mientras el viaje sigue en planificación.
- No se puede revertir si la embarcación tiene registros vigentes en AFIP para ese viaje, como un MIC/DTA registrado sin anulación aprobada.
- Si al viaje se le agregaron otros archivos, no use **Revertir importación** en la importación que creó el viaje. Use **Revertir viaje completo**, o revierta primero cada archivo agregado con **Revertir lo agregado**.
- La reversión no se puede deshacer. Los clientes y puertos no se borran, porque pueden usarlos otros viajes.

## Pasos

1. Abra [Historial de importaciones](ruta:company.manifests.import.history). También puede llegar desde **Manifiestos › 📊 Importar Excel/CSV** con **Ver historial completo →**.
2. Busque la importación por el nombre del archivo.
3. En la columna **Acciones** de esa fila, abra el menú y elija una opción:
    - **Revertir importación**, si el archivo creó el viaje y no se le agregaron otros archivos.
    - **Revertir lo agregado**, si el archivo se agregó a un viaje existente.
    - **Revertir viaje completo**, si quiere deshacer todos los archivos del viaje. Aparece en las importaciones de los viajes que tienen archivos agregados.
4. Lea la lista **Se eliminarán:** de la ventana de confirmación. Cambia según la opción elegida.
5. Haga clic en **Confirmar Reversión**.

## Cómo saber que salió bien

Vuelve al historial y, en la fila revertida, el estado pasa a **Revertido**. Abra **Acciones** en esa fila: dentro del menú aparece **Revertida el** seguido de la fecha y la hora.

- Con **Revertir importación**, se elimina el viaje que creó ese archivo. Con **Revertir viaje completo**, todas las importaciones del viaje quedan en **Revertido**: primero se revierten las agregadas, de la más nueva a la más vieja, y al final la que creó el viaje, si existe. El viaje desaparece de la lista solo si una de esas importaciones lo creó; un viaje creado a mano y sus cargas manuales se conservan. Si alguna importación no se puede revertir, no se revierte ninguna.
- Con **Revertir lo agregado**, el viaje sigue existiendo sin los conocimientos de ese archivo. Si el archivo había creado la carga de la embarcación, la carga también se elimina. Si había convertido el viaje en convoy, el viaje vuelve a ser como estaba.

Después puede volver a importar el archivo.

## Si algo falla

- **La opción no aparece en el menú de la fila:** el viaje ya no está en planificación, se borró o la importación ya fue revertida. Abra **Ver Detalles** para revisar la importación.
- **Después de confirmar, la fila sigue igual:** la reversión no se hizo. Las causas más comunes son que el viaje avanzó de estado o que la embarcación tiene registros vigentes en AFIP para ese viaje. Para ver el motivo del bloqueo de AFIP, abra **Manifiestos › 📊 Importar Excel/CSV**, elija **Agregar a un viaje existente** y seleccione el viaje y la embarcación: el aviso indica qué registro está vigente. Anúlelo en AFIP antes de volver a revertir.
- **Revertir lo agregado no se hace en un archivo que convirtió el viaje en convoy:** si después de esa conversión se agregaron otros archivos, revierta primero los más nuevos.
