---
titulo: Cómo generar el listado de conocimientos
tema: reportes
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [listado, conocimientos, BL, filtros, PDF, Excel, Oficio, cargador, consignatario]
relacionados: [reportes/generar-el-manifiesto, reportes/avisos-de-arribo]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Genera listados de los conocimientos de embarque de la empresa. El **Formato actual** sale en PDF vertical o en Excel y se filtra por fechas, estado, viaje, puertos, cargador o consignatario. El **Formato según muestra** sale en PDF hoja Oficio para un viaje, con filtros de puertos y cliente, y permite elegir uno, varios o todos los conocimientos.

## Antes de empezar

- Hay conocimientos cargados en sus viajes. Vea [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items) o [Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto).
- Los filtros solo ofrecen viajes, puertos y clientes que aparecen en conocimientos de su empresa.

## Pasos

1. En el menú lateral, haga clic en **Reportes** para desplegarlo y elija **Listado de Conocimientos**. Se abre [Listado de Conocimientos de Embarque](ruta:company.reports.bills-of-lading).
2. Para el **Formato actual**, complete los filtros que necesite: **Desde (Fecha BL)**, **Hasta (Fecha BL)**, **Estado**, **Viaje**, **Puerto de carga**, **Puerto de descarga**, **Puerto de destino**, **Cargador (opcional)** y **Consignatario (opcional)**. Un filtro vacío incluye todo.
3. En **Formato**, elija **PDF (Recomendado)** o **Excel** y haga clic en **Generar Reporte**.
4. Para el **Formato según muestra**, elija el **Viaje** en ese recuadro y haga clic en **Cargar conocimientos**. La página se recarga y muestra el viaje, la embarcación y la cantidad de conocimientos.
5. Si corresponde, filtre por **Puerto de carga**, **Puerto de descarga**, **Destino final** o **Cliente**. **Cliente** incluye los conocimientos donde ese cliente figura como cargador, consignatario, notificado o dueño de la carga. Deje **Todos** para no filtrar.
6. En **Conocimientos a incluir**, desmarque los que no quiera incluir. **Seleccionar todos** marca o desmarca la lista completa.
7. Haga clic en **Generar formato según muestra (Oficio)**.

## Cómo saber que salió bien

El navegador descarga el archivo: el **Formato actual** como PDF vertical o como Excel, y el **Formato según muestra** como PDF en hoja Oficio. El nombre del archivo incluye la fecha y la hora de generación. Abra el archivo y verifique que estén los conocimientos esperados.

## Si algo falla

- **No hay conocimientos**: su empresa todavía no tiene conocimientos de embarque. Cárguelos en sus viajes y vuelva a la pantalla.
- Si el listado sale sin conocimientos, ningún conocimiento cumple todos los filtros a la vez. Quite filtros (por ejemplo, deje **Todos los estados**) y vuelva a generar.
- **Seleccione al menos un conocimiento.**: marque al menos un conocimiento en **Conocimientos a incluir**.
- **El viaje seleccionado no está disponible para esta empresa o no tiene conocimientos.**: elija otro viaje de la lista y haga clic en **Cargar conocimientos**.
- **Debe seleccionar un viaje para generar el formato de conocimientos.**: elija el viaje y haga clic en **Cargar conocimientos** antes de generar.
- **Error al generar el reporte:** seguido de un detalle: el archivo no se pudo armar. Vuelva a intentar; si se repite, comunique el detalle que muestra la pantalla a soporte.
