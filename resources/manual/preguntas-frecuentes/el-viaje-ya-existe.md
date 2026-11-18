---
titulo: ¿Qué hago si al importar dice que el viaje ya existe?
tema: preguntas-frecuentes
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, viaje ya existe, duplicado, ya fue importado, revertir, número de viaje, agregar a un viaje existente]
relacionados:
  - preguntas-frecuentes/varios-archivos-en-un-viaje
  - importar-manifiestos/agregar-un-archivo-a-un-viaje-existente
estado: publicado
verificado:
  version: 390826a
  fecha: 2026-11-17
  entorno: codigo
---

## Para qué sirve

Explica por qué una importación se rechaza con el aviso de que el archivo ya fue importado o de que el viaje ya existe, y cómo seguir. El sistema no duplica datos:

- un mismo archivo se importa una sola vez, aunque se elija otro viaje;
- un número de viaje no puede repetirse para la misma embarcación dentro de su empresa.

## Antes de empezar

- Tenga a mano el número de viaje y la embarcación del archivo.
- Confirme si ese archivo ya se importó antes, por usted o por otro usuario de su empresa.

## Pasos

1. Abra [Historial de importaciones](ruta:company.manifests.import.history) y busque el archivo o el número de viaje.
2. Si la importación anterior es correcta, no hace falta volver a importar: trabaje sobre ese viaje.
3. Si la importación anterior estaba mal y quiere rehacerla, reviértala desde esa fila y confirme. Use **Revertir lo agregado** si el archivo se había agregado a otro viaje. Después vuelva a importar el archivo.
4. Si es otro archivo que trae el mismo número de viaje y la misma embarcación, y su mercadería va en ese viaje, impórtelo eligiendo **Agregar a un viaje existente**. Vea [Cómo agregar un archivo a un viaje existente](manual:importar-manifiestos/agregar-un-archivo-a-un-viaje-existente).
5. Si se trata de otro viaje que por error trae el mismo número y la misma embarcación, corrija el número de viaje en el archivo de origen antes de importarlo.

## Cómo saber que salió bien

La nueva importación termina sin el aviso y aparece en el historial. Si la agregó a un viaje, el reporte muestra **Archivo agregado a un viaje existente**.

## Si algo falla

- **"Este archivo ya fue importado anteriormente…"** o **"El archivo ya fue importado. Revise el historial de importaciones."**: ese mismo archivo ya está cargado, en este o en otro viaje. Siga el paso 2 o el paso 3.
- **"El viaje … ya existe para esta embarcación en su empresa."**: ya hay un viaje con ese número para esa embarcación. Siga el paso 4 o el paso 5.
