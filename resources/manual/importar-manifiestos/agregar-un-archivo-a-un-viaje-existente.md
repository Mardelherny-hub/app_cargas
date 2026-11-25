---
titulo: Cómo agregar un archivo a un viaje existente
tema: importar-manifiestos
orden: 15
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, agregar, sumar archivo, viaje existente, mismo viaje, varios archivos, convoy, embarcación propuesta, repetidos, salteados, AFIP]
relacionados: [importar-manifiestos/importar-un-manifiesto, importar-manifiestos/revertir-una-importacion, viajes-y-cargas/armar-un-convoy, preguntas-frecuentes/varios-archivos-en-un-viaje]
estado: publicado
verificado:
  version: eb547c9c
  fecha: 2026-11-24
  entorno: stage
---

## Para qué sirve

Suma los conocimientos de un archivo a un viaje que ya existe, en lugar de crear un viaje nuevo. Sirve, por ejemplo, cuando la mercadería de un mismo viaje llega en varios archivos o cuando una barcaza se suma a un viaje.

## Antes de empezar

- El viaje tiene que estar en planificación. Si no hay ninguno, la opción **Agregar a un viaje existente** aparece deshabilitada con el aviso **No hay viajes en planificación para agregar archivos.**
- Tenga el archivo en uno de los [formatos soportados](manual:importar-manifiestos/formatos-soportados). Los puertos del archivo tienen que existir en el sistema, igual que al [importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto).
- Si el archivo es de una embarcación que todavía no está en el viaje y el viaje es de una sola embarcación, el viaje se convierte en convoy. Tenga decidida cuál será la embarcación líder.
- El mismo archivo no se puede importar dos veces, ni siquiera en viajes distintos. Si necesita volver a cargarlo, primero [revierta la importación anterior](manual:importar-manifiestos/revertir-una-importacion).

## Pasos

1. Abra **Manifiestos › 📊 Importar Excel/CSV**.
2. En **📎 Archivo de Manifiesto**, seleccione el archivo. Si el archivo informa una embarcación registrada en su empresa, aparece **Embarcación propuesta según el archivo: …. Confírmela o elija otra.** y queda elegida en **🚢 Embarcación**.
3. Revise **🚢 Embarcación**. Si aparece **El archivo informa la embarcación «…», que no está registrada en su empresa. Elija la embarcación.**, elíjala usted.
4. En **🧭 Destino**, seleccione **Agregar a un viaje existente**.
5. En **Viaje en planificación**, elija el viaje. La lista muestra el número de viaje, la embarcación líder y, si es un convoy, cuántas embarcaciones tiene.
6. Si aparece el recuadro **La embarcación no está en este viaje y el viaje no es un convoy…**, elija la embarcación que conducirá el convoy en **Embarcación líder del convoy**. Puede ser una embarcación sin carga.
7. Si el archivo es CUSCAR, elija **Importación** o **Exportación** en **↔️ Tipo de operación (requerido para CUSCAR)**. Las fechas de carga y de descarga son opcionales, igual que al crear un viaje.
8. Haga clic en **Importar Manifiesto** y espere el reporte.

## Cómo saber que salió bien

El reporte muestra el recuadro **Archivo agregado a un viaje existente**, con una de estas líneas:

- **Se creó la carga de la embarcación en el viaje.**: la embarcación no tenía carga en el viaje.
- **Los conocimientos se sumaron a la carga existente de la embarcación.**: la embarcación ya tenía carga y los conocimientos se agregaron a ella.
- **El viaje se convirtió en convoy. Si revierte esta importación, vuelve a su configuración anterior.**: aparece solo si hubo conversión.

Debajo, el reporte indica qué conocimientos se saltearon. Si un conocimiento del archivo ya estaba en el viaje, no se carga de nuevo: aparece en **Conocimientos salteados porque ya existían en el viaje**. Si no hubo repetidos, se lee **No se salteó ningún conocimiento por repetido.** Salvo la conversión en convoy indicada arriba, los datos generales del viaje no cambian. El mismo recuadro se ve después en **Ver Detalles** de esa importación, en el [historial](manual:importar-manifiestos/historial-de-importaciones).

## Si algo falla

- **La embarcación … tiene registros vigentes en AFIP para este viaje: …**: el viaje ya tiene un MIC/DTA o títulos registrados en AFIP para esa embarcación, sin anulación aprobada. El mensaje termina con el motivo. El botón **Importar Manifiesto** queda deshabilitado. Para agregar el archivo, primero tiene que anular en AFIP lo que indica el motivo.
- **Este formato todavía no admite agregar archivos a un viaje existente.**: importe el archivo creando un viaje nuevo.
- **El viaje … no es un convoy. Para agregar otra embarcación, conviértalo en convoy y elija el líder.**: elija la embarcación en **Embarcación líder del convoy** (paso 6).
- **Solo se pueden agregar archivos a viajes en planificación. El viaje … no lo está.**: el viaje avanzó de estado. Elija otro viaje o cree uno nuevo.
- **El archivo ya fue importado…** o **Este archivo ya fue importado anteriormente…**: ese archivo ya está cargado, en este u otro viaje. Si lo quiere cargar de nuevo, primero revierta la importación anterior.
- **No se pudo verificar el viaje elegido. Intente nuevamente.**: vuelva a elegir el viaje. Si el aviso sigue, actualice la página.
