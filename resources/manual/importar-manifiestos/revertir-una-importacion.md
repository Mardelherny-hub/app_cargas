---
titulo: Cómo revertir una importación
tema: importar-manifiestos
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [revertir, deshacer, anular importación, borrar viaje importado, reimportar, ya fue importado]
relacionados: [importar-manifiestos/historial-de-importaciones, importar-manifiestos/importar-un-manifiesto]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Elimina todo lo que creó una importación: el viaje, sus conocimientos, las líneas de mercadería y los contenedores. Sirve para corregir una importación equivocada o para volver a importar un archivo corregido.

## Antes de empezar

- Solo se puede revertir mientras el viaje creado sigue en planificación.
- Revierta solo si todavía no envió el viaje a AFIP ni a DNA.
- La reversión no se puede deshacer. Los clientes y puertos no se borran, porque pueden usarlos otros viajes.

## Pasos

1. Abra **Manifiestos › 📊 Importar Excel/CSV** y haga clic en **Ver historial completo →**. También puede llegar desde el botón **Ver Historial** del reporte de una importación.
2. En **📋 Historial de Importaciones**, busque la importación por el nombre del archivo.
3. En la columna **Acciones** de esa fila, abra el menú y elija **Revertir importación**. Si la opción no aparece, la importación no se puede revertir; vea **Si algo falla**.
4. Lea la lista **Se eliminarán:** de la ventana de confirmación.
5. Haga clic en **Confirmar Reversión**.

## Cómo saber que salió bien

Vuelve al historial y, en la fila de esa importación, el estado pasa a **Revertido** y la columna **Acciones** muestra **Revertida el** seguido de la fecha y la hora. El viaje ya no aparece en la lista de viajes. Ahora puede volver a importar el archivo.

## Si algo falla

- **Revertir importación** no aparece en el menú de la fila: el viaje ya no está en planificación, se borró o la importación ya fue revertida. Abra **Ver Detalles** y revise **Estado de reversión**.
- **Solo se pueden revertir importaciones con viajes en estado de planificación.**: el viaje avanzó de estado y no se puede revertir.
- **No tiene permisos para revertir esta importación.**: la importación es de otra empresa.
- Si después de confirmar la fila no cambia a **Revertido**, actualice la página. Si sigue igual, abra **Ver Detalles** y revise **Estado de reversión**.
