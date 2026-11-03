---
titulo: ¿Qué hago si al importar dice que el viaje ya existe?
tema: preguntas-frecuentes
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, viaje ya existe, duplicado, ya fue importado, revertir, número de viaje]
relacionados:
  - preguntas-frecuentes/varios-archivos-en-un-viaje
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Explica por qué una importación se rechaza con el aviso de que el archivo ya fue importado o de que el viaje ya existe, y cómo seguir. El sistema no duplica datos: un número de viaje no puede repetirse para la misma embarcación dentro de su empresa.

## Antes de empezar

- Tenga a mano el número de viaje y la embarcación del archivo.
- Confirme si ese archivo ya se importó antes, por usted o por otro usuario de su empresa.

## Pasos

1. Abra [Historial de importaciones](ruta:company.manifests.import.history) y busque el archivo o el número de viaje.
2. Si la importación anterior es correcta, no hace falta volver a importar: trabaje sobre ese viaje.
3. Si la importación anterior estaba mal y quiere rehacerla, use **Revertir importación** en esa fila y confirme. Después vuelva a importar el archivo.
4. Si se trata de otro viaje que por error trae el mismo número y la misma embarcación, corrija el número de viaje en el archivo de origen antes de importarlo.

## Cómo saber que salió bien

La nueva importación termina sin el aviso y aparece en el historial con su viaje.

## Si algo falla

- **"Este archivo ya fue importado anteriormente. El viaje ya existe en el sistema y no se duplicó ningún dato.":** el viaje del archivo ya está cargado. Siga el paso 2 o el paso 3.
- **"El viaje … ya existe para esta embarcación en su empresa.":** ya hay un viaje con ese número para esa embarcación. Siga el paso 3 o el paso 4.
