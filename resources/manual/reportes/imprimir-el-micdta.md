---
titulo: Cómo imprimir el MIC/DTA de un viaje
tema: reportes
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [MIC/DTA, imprimir, PDF, AFIP, formato según muestra, viaje, conocimientos]
relacionados:
  - reportes/generar-el-manifiesto
  - afip-argentina/registrar-micdta-una-embarcacion
  - afip-argentina/registrar-micdta-convoy
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Genera el MIC/DTA de un viaje en PDF para imprimir. El **Reporte actual** muestra los datos del viaje que tiene el MIC/DTA registrado ante AFIP. El **Formato MIC/DTA** según muestra se arma con los conocimientos del viaje y permite filtrar por puertos y elegir conocimientos. Ninguna de las dos opciones envía información a AFIP.

## Antes de empezar

- Para el **Reporte actual**, el MIC/DTA del viaje está registrado con éxito: vea [Cómo registrar el MIC/DTA de una embarcación](manual:afip-argentina/registrar-micdta-una-embarcacion) o [Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).
- Para el **Formato MIC/DTA**, alcanza con que el viaje tenga conocimientos de embarque.
- En los dos casos, el viaje necesita número, embarcación, puerto de origen, puerto de destino y al menos una carga.

## Pasos

1. En el menú lateral, haga clic en **Reportes** para desplegarlo y elija **MIC/DTA**. Se abre [Reportes MIC/DTA - AFIP](ruta:company.reports.micdta).
2. Para el reporte con los datos registrados, busque el viaje en **Viajes con MIC/DTA Registrado** y haga clic en **Reporte actual**.
3. Para el formato según muestra, busque el viaje en **Formato MIC/DTA según muestra - Viajes con conocimientos**.
4. Si corresponde, elija **Puerto de carga**, **Puerto de descarga** y **Destino final** en la fila del viaje. Deje **Todos** para no filtrar.
5. Para elegir conocimientos, despliegue **Conocimientos** en la misma fila y desmarque los que no quiera incluir. **Seleccionar todos** marca o desmarca la lista completa.
6. Haga clic en **Formato MIC/DTA**.

## Cómo saber que salió bien

El navegador descarga un PDF. El **Reporte actual** sale en A4 apaisado. El nombre del archivo incluye el número de viaje y la fecha. Abra el archivo y verifique el viaje, la embarcación y los conocimientos.

## Si algo falla

- **No hay viajes con MIC/DTA registrado y enlazado**: ningún viaje tiene un MIC/DTA exitoso asociado. Regístrelo primero o use **Formato MIC/DTA** en la sección inferior.
- **No hay viajes con conocimientos disponibles para esta salida.**: cargue conocimientos en el viaje y vuelva a la pantalla.
- **Seleccione al menos un conocimiento.**: marque al menos un conocimiento en **Conocimientos** antes de hacer clic en **Formato MIC/DTA**.
- Si la página se recarga y no se descarga ningún archivo, el viaje no tiene los datos mínimos. Revise que tenga número, embarcación, puertos de origen y destino y al menos una carga, y vuelva a intentar.
