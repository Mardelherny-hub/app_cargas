---
titulo: ¿Puedo sumar otro archivo importado a un viaje que ya existe?
tema: preguntas-frecuentes
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, archivo, varios archivos, mismo viaje, convoy, remolcador, barcaza, agregar, viaje existente]
relacionados:
  - importar-manifiestos/agregar-un-archivo-a-un-viaje-existente
  - viajes-y-cargas/armar-un-convoy
  - preguntas-frecuentes/el-viaje-ya-existe
estado: publicado
verificado:
  version: 390826a
  fecha: 2026-11-17
  entorno: codigo
---

## Para qué sirve

Responde si varios archivos (por ejemplo, uno por cada embarcación de un convoy, o varios de una misma embarcación) pueden quedar en un mismo viaje. **Sí:** al importar puede elegir **Agregar a un viaje existente** en lugar de crear un viaje nuevo.

## Antes de empezar

- El viaje tiene que estar en planificación.
- La embarcación no puede tener registros vigentes en AFIP para ese viaje.
- Cada archivo se puede importar una sola vez. Un archivo con otro contenido, aunque repita conocimientos que ya están en el viaje, sí se puede agregar.

## Pasos

1. Importe el primer archivo creando el viaje, como indica [Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto).
2. Para cada archivo siguiente, siga [Cómo agregar un archivo a un viaje existente](manual:importar-manifiestos/agregar-un-archivo-a-un-viaje-existente) y elija ese viaje.
3. Si el archivo es de la misma embarcación, sus conocimientos se suman a la carga que ya tiene.
4. Si el archivo es de otra embarcación y el viaje es de una sola embarcación, elija la **Embarcación líder del convoy**: el viaje se convierte en convoy. Vea [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).
5. Si agregó un archivo por error, revierta solo ese archivo con **Revertir lo agregado** desde el [Historial de importaciones](ruta:company.manifests.import.history).

## Cómo saber que salió bien

El reporte de cada archivo agregado muestra **Archivo agregado a un viaje existente** y la lista de los conocimientos salteados por repetidos, si los hubo. En el viaje, **Cargas Asociadas** muestra una carga por cada embarcación.

## Si algo falla

- **El mensaje dice que el archivo ya fue importado o que el viaje ya existe:** vea [¿Qué hago si al importar dice que el viaje ya existe?](manual:preguntas-frecuentes/el-viaje-ya-existe).
- **La embarcación … tiene registros vigentes en AFIP para este viaje: …**: el viaje ya tiene registros en AFIP para esa embarcación. El mensaje indica el motivo. No se pueden agregar archivos hasta anular ese registro.
- **La opción Agregar a un viaje existente está deshabilitada:** no hay viajes en planificación. Cree el viaje importando el primer archivo.
