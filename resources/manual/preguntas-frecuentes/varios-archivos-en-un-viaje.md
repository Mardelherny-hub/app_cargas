---
titulo: ¿Puedo sumar otro archivo importado a un viaje que ya existe?
tema: preguntas-frecuentes
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, archivo, varios archivos, mismo viaje, convoy, remolcador, barcaza, agregar]
relacionados:
  - viajes-y-cargas/armar-un-convoy
  - preguntas-frecuentes/el-viaje-ya-existe
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Responde si varios archivos (por ejemplo, uno por cada embarcación de un convoy) pueden quedar en un mismo viaje. **Hoy no:** cada archivo que se importa crea su propio viaje, y la importación no agrega datos a un viaje que ya existe.

## Antes de empezar

- Tenga en cuenta qué necesita presentar: un viaje de una sola embarcación o un convoy.
- Para un convoy, las embarcaciones y sus cargas tienen que estar en un mismo viaje: vea [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).

## Pasos

1. Si el archivo corresponde a un viaje de una sola embarcación, impórtelo desde [Importar manifiestos](ruta:company.manifests.import.index): se crea un viaje con sus conocimientos.
2. Si necesita reunir varias embarcaciones en un convoy, arme el viaje de convoy y cargue en él las cargas de cada embarcación, como indica [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).
3. Si importó un archivo por error o quiere rehacer la importación, reviértala desde [Historial de importaciones](ruta:company.manifests.import.history) antes de volver a importar.

## Cómo saber que salió bien

Cada importación aparece en el historial con su propio viaje. Un viaje de convoy muestra en **Cargas Asociadas** una carga por cada embarcación.

## Si algo falla

- **El mensaje dice que el archivo ya fue importado o que el viaje ya existe:** vea [¿Qué hago si al importar dice que el viaje ya existe?](manual:preguntas-frecuentes/el-viaje-ya-existe).
