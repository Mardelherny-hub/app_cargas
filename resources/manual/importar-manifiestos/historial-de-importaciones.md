---
titulo: Cómo consultar el historial de importaciones
tema: importar-manifiestos
orden: 40
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [historial, importaciones, resultado, errores, advertencias, detalle de importación]
relacionados: [importar-manifiestos/importar-un-manifiesto, importar-manifiestos/revertir-una-importacion]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Muestra todas las importaciones de manifiestos de su empresa, con su resultado, el viaje que crearon y los errores o advertencias de cada una.

## Antes de empezar

- El historial no tiene una opción propia en el menú: se abre desde la pantalla de importación o desde el reporte de una importación.

## Pasos

1. Abra **Manifiestos › 📊 Importar Excel/CSV** y haga clic en **Ver historial completo →**. Desde el reporte de una importación, haga clic en **Ver Historial**.
2. En **📋 Historial de Importaciones**, cada fila muestra:
    - **Archivo**: nombre con que se guardó el archivo, su tamaño y el tiempo de proceso.
    - **Formato**: formato que reconoció el sistema.
    - **Estado**: **Procesando**, **Completado**, **Con Advertencias**, **Fallido** o **Revertido**, con la cantidad de advertencias o errores.
    - **Fecha** y **Usuario**: cuándo y quién importó.
    - **Resultados**: cantidad de conocimientos (BLs), contenedores, ítems y clientes creados.
    - **Viaje**: número del viaje creado, con los puertos de origen y destino, o **Sin viaje**.
3. Para encontrar una importación, escriba parte del nombre del archivo en **Buscar por nombre de archivo...**. La búsqueda se aplica a la página que está viendo; si no la encuentra, pase a la página siguiente.
4. Para ver el detalle, abra el menú de **Acciones** y elija **Ver Detalles**. Para abrir el viaje, elija **Ver viaje**.
5. En **🔍 Detalle de Importación**, revise:
    - **Resumen general**: estado, archivo, formato, usuario y horarios del proceso.
    - **Resultados de la importación**: cantidades creadas.
    - **Viaje asociado**: número y estado del viaje, con el botón **Ver viaje**.
    - **Advertencias** y **Errores**: el detalle de cada problema encontrado.
    - **Estado de reversión**: si la importación fue revertida, cuándo y por quién, o por qué no se puede revertir.
6. Para volver a la lista, haga clic en **Volver al historial**.

## Cómo saber que salió bien

Una importación correcta figura como **Completado**, con las cantidades de **Resultados** y el número de viaje en **Viaje**. Si figura como **Con Advertencias**, el viaje se creó: revise las advertencias en **Ver Detalles**.

## Si algo falla

- **No hay importaciones aún**: su empresa todavía no importó archivos. Use **Importar primer archivo**.
- Una importación que figura como **Fallido** no creó ningún viaje. Abra **Ver Detalles**, lea **Errores**, corrija lo que indica y vuelva a importar.
- Si una importación fallida no aparece en el historial, el error se mostró en la pantalla **📥 Procesando Importación** al importar. Vuelva a importar el archivo para ver el mensaje.
- Si una importación queda mucho tiempo en **Procesando**, avise al administrador del sistema.
- El archivo original no se puede descargar desde el historial: guarde una copia propia.
