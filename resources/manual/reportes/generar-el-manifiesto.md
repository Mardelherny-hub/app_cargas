---
titulo: Cómo generar el manifiesto de carga o el manifiesto aduanero
tema: reportes
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [manifiesto, manifiesto de carga, Cargo Manifest, manifiesto aduanero, PDF, Excel, viaje, imprimir]
relacionados: [reportes/listado-de-conocimientos, reportes/imprimir-el-micdta]
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Genera el manifiesto de un viaje con sus conocimientos de embarque. Ofrece tres salidas: el **Formato actual**, en PDF o Excel; el **Cargo Manifest**, en PDF A4 apaisado, que permite filtrar por puertos y elegir los conocimientos; y el **Manifiesto Aduanero**, en PDF apaisado, para la presentación física ante la Aduana.

## Antes de empezar

- El viaje tiene al menos un conocimiento de embarque. Vea [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items) o [Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto).
- Para el **Manifiesto Aduanero**, el viaje también necesita embarcación principal, puerto de origen y puerto de destino.
- En el **Manifiesto Aduanero**, si su usuario no es administrador de la empresa, la lista muestra solo los viajes que usted creó.

## Pasos

1. En el menú lateral, haga clic en **Reportes** para desplegarlo y elija **Manifiesto de Carga**. Se abre [Manifiestos de Carga](ruta:company.reports.manifests).
2. En **Formato de reporte**, elija **Formato actual** o **Cargo Manifest**.
3. Seleccione el **Viaje**. La lista solo muestra viajes con conocimientos. Debajo aparecen los **Detalles del viaje:** para que confirme que es el correcto.
4. Si eligió **Formato actual**, seleccione el **Tipo de archivo** (**PDF (Recomendado)** o **Excel**), filtre por **Estado BL (opcional)** si lo necesita y haga clic en **Generar Reporte**.
5. Si eligió **Cargo Manifest**, haga clic en **Cargar conocimientos**. La página se recarga y muestra el recuadro **Cargo Manifest** con el viaje, la embarcación y la cantidad de conocimientos.
6. Si corresponde, limite el **Cargo Manifest** con **Puerto de carga**, **Puerto de descarga** y **Destino final**. Deje **Todos** para no filtrar.
7. En **Conocimientos a incluir**, desmarque los que no quiera incluir. **Seleccionar todos** marca o desmarca la lista completa. Solo se incluyen los conocimientos marcados que además coincidan con los puertos elegidos.
8. Haga clic en **Generar Cargo Manifest**.
9. Para el **Manifiesto Aduanero**, abra **Reportes › Dashboard Reportes** y, en la tarjeta **Manifiesto Aduanero**, haga clic en **Generar Manifiesto**. Seleccione el **Viaje** y, si corresponde, **Puerto de Descarga (Opcional)** y **Consignatario (Opcional)**.
10. Haga clic en **Generar Manifiesto Aduanero (PDF)**.

## Cómo saber que salió bien

El navegador descarga el archivo: el **Formato actual** como PDF o Excel, el **Cargo Manifest** como PDF A4 apaisado y el **Manifiesto Aduanero** como PDF apaisado. El nombre del archivo incluye el número de viaje y la fecha de generación. Abra el archivo y verifique el viaje, la embarcación y los conocimientos incluidos.

## Si algo falla

- **No hay viajes disponibles**: ninguno de sus viajes tiene conocimientos de embarque. Cárguelos y vuelva a la pantalla.
- **Seleccione al menos un conocimiento.**: marque al menos un conocimiento en **Conocimientos a incluir** antes de generar el **Cargo Manifest**.
- **El viaje seleccionado no está disponible para esta empresa o no tiene conocimientos.**: vuelva a elegir el viaje en la lista y haga clic en **Cargar conocimientos**.
- **El viaje no tiene conocimientos de embarque asociados.**: agregue los conocimientos al viaje antes de generar el manifiesto.
- **El viaje no tiene una embarcación principal asignada.**, **El viaje no tiene puerto de origen definido.** o **El viaje no tiene puerto de destino definido.**: complete ese dato en el viaje y vuelva a generar el **Manifiesto Aduanero**.
- **Error al generar el reporte:** seguido de un detalle: el archivo no se pudo armar. Vuelva a intentar; si se repite, comunique el detalle que muestra la pantalla a soporte.
