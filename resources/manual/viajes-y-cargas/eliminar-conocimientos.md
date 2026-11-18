---
titulo: Cómo eliminar conocimientos
tema: viajes-y-cargas
orden: 40
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [eliminar, borrar, quitar, conocimiento, BL, ítems, contenedores, embarcación, todos los conocimientos]
relacionados: [viajes-y-cargas/cargar-conocimientos-e-items, importar-manifiestos/revertir-una-importacion]
estado: publicado
verificado:
  version: 390826a
  fecha: 2026-11-17
  entorno: codigo
---

## Para qué sirve

Elimina un conocimiento junto con sus ítems en un solo paso, o todos los conocimientos de una embarcación dentro de un viaje. Sirve para quitar conocimientos cargados por error sin borrar el viaje ni la carga.

## Antes de empezar

- El viaje tiene que estar en planificación y el conocimiento, en borrador y sin verificar.
- El conocimiento no puede haberse enviado a AFIP ni a DNA, ni tener documentos enviados a DNA o distribuciones de transbordo.
- La embarcación no puede tener registros vigentes en AFIP para ese viaje, como un MIC/DTA registrado sin anulación aprobada.
- Si el conocimiento no cumple alguna de estas condiciones, no aparece el botón **Eliminar**.
- La eliminación no se puede deshacer. Si los conocimientos vinieron de una importación y quiere quitar todo lo que cargó ese archivo, es mejor [revertir la importación](manual:importar-manifiestos/revertir-una-importacion).

## Pasos

**Un conocimiento:**

1. Abra el conocimiento desde el [listado de conocimientos](ruta:company.bills-of-lading.index) o desde la carga.
2. Haga clic en **Eliminar**. En el listado, el botón **Eliminar** está en la fila de cada conocimiento.
3. Lea el aviso **¿Eliminar el conocimiento … y sus ítems?** y confirme.

**Todos los conocimientos de una embarcación:**

1. Abra la carga de la embarcación dentro del viaje y haga clic en **📋 Ver BLs**.
2. Arriba del listado aparece el recuadro con el nombre de la embarcación y la cantidad de conocimientos para eliminar. Haga clic en **Eliminar todos los conocimientos de esta embarcación**.
3. Lea el aviso **¿Eliminar los … conocimientos de la embarcación … y sus ítems?** y confirme.

## Cómo saber que salió bien

El conocimiento, o los conocimientos de la embarcación, ya no aparecen en el listado ni en la carga. Sus ítems se eliminan con ellos. De los contenedores, solo se eliminan los que no usa ningún otro conocimiento; un contenedor compartido con otro conocimiento o con otro viaje se conserva. El viaje y la carga se conservan, y los totales de peso, bultos y contenedores se recalculan con lo que queda.

## Si algo falla

- **No aparece el botón Eliminar en el conocimiento:** el conocimiento no está en borrador, ya está verificado, ya se envió, el viaje no está en planificación o la embarcación tiene registros vigentes en AFIP. Revise las condiciones de **Antes de empezar**.
- **No aparece el recuadro Eliminar todos los conocimientos de esta embarcación:** al menos uno de los conocimientos de esa embarcación no se puede eliminar. Elimine de a uno los que sí se pueden, o revise el que lo impide.
- **El conocimiento sigue en el listado después de confirmar:** la eliminación no se hizo. Antes de eliminar, el sistema vuelve a revisar todas las condiciones y, si alguna dejó de cumplirse, no elimina nada. Abra el conocimiento y revise si sigue apareciendo **Eliminar**.
