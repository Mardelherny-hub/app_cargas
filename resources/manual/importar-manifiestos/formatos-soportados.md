---
titulo: Qué formatos de manifiesto se pueden importar
tema: importar-manifiestos
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [formato, archivo, Excel, XML, EDI, CUSCAR, KLine, PARANA, Guaran, Login, TFP, Navsur, G2Ocean, CMSP]
relacionados: [importar-manifiestos/importar-un-manifiesto]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Indica qué archivos de manifiesto reconoce el sistema y qué necesita cada uno. No hace falta elegir el formato al importar: el sistema lo detecta por el contenido del archivo.

## Antes de empezar

- El archivo tiene que pesar hasta 10 MB.
- Los archivos CSV no se pueden importar, aunque el menú diga **Importar Excel/CSV**. Si recibe un CSV, pida el archivo en uno de los formatos de esta página.

## Pasos

1. Identifique el formato de su archivo en esta lista:
    - **KLine Data Format** (`.dat` o `.txt`): archivo de datos de K-Line. Al número de viaje se le agrega delante `KLINE-`.
    - **PARANA Excel** (`.xlsx` o `.xls`): planilla de MAERSK de 73 columnas.
    - **Guaran Excel** (`.xlsx` o `.xls`): manifiesto consolidado de Guaran Feeder. Los encabezados van en la fila 6 y los datos desde la fila 7.
    - **Login XML** (`.xml`): manifiesto de Login.
    - **TFP** (`.txt`): archivo de texto con los bloques de conocimiento, contenedores y líneas. No trae número de viaje: si no lo escribe al importar, el sistema genera una clave propia.
    - **CMSP EDI CUSCAR** (`.edi` u otra extensión, por ejemplo `.1`): archivo CUSCAR de CMSP Paraguay o de otras líneas, como Hapag-Lloyd. Exige elegir **Importación** o **Exportación** al importar.
    - **Navsur Text Format** (`.txt`): archivo de texto de Navsur.
    - **G2Ocean XML Format** (`.xml`): archivo XML de StarPort.
2. Revise de dónde sale el número de viaje:
    - En CMSP EDI CUSCAR y TFP, si escribe un número al importar, ese reemplaza al del archivo.
    - En los demás formatos se usa el número del archivo; el que escriba se usa solo si el archivo no trae uno.
3. Importe el archivo como se explica en [Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto).

## Cómo saber que salió bien

Al terminar la importación se abre el reporte. La columna **Formato** del historial muestra el formato que reconoció el sistema: KLINE, PARANA, GUARAN_EXCEL, LOGIN_XML, TFP, CMSP, NAVSUR o G2OCEAN_XML.

## Si algo falla

- **No se pudo encontrar un parser compatible para el archivo**: el archivo no corresponde a ninguno de estos formatos o está dañado. Pida el archivo original a quien lo envió.
- **Para archivos CUSCAR debe seleccionar Importación o Exportación.**: el archivo es CUSCAR; elija el tipo de operación.
- **El archivo CUSCAR no informa la fecha estimada de llegada DTM+132 y no se ingresó una fecha de descarga.**: complete **📅 Fecha de Descarga (opcional)** y vuelva a importar.
- Si un archivo Excel de Guaran no se reconoce, revise que sea `.xlsx` o `.xls`, y no CSV.
