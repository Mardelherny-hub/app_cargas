---
titulo: Cómo importar un manifiesto
tema: importar-manifiestos
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, manifiesto, archivo, Excel, XML, EDI, CUSCAR, viaje, carga masiva]
relacionados: [importar-manifiestos/formatos-soportados, importar-manifiestos/historial-de-importaciones, importar-manifiestos/revertir-una-importacion]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Crea un viaje completo a partir del archivo de manifiesto que envía la línea o el agente: el viaje, sus conocimientos, las líneas de mercadería y los contenedores. Los clientes que no existen se dan de alta solos.

## Antes de empezar

- Tenga el archivo en uno de los [formatos soportados](manual:importar-manifiestos/formatos-soportados), de hasta 10 MB.
- La embarcación tiene que estar cargada, activa y en estado operativo activo. Vea [Ir a Embarcaciones](ruta:company.vessels.index).
- Los puertos del archivo tienen que existir en el sistema: la importación no crea puertos. Si falta uno, pida el alta al administrador con el código y el nombre del puerto.
- Si es un archivo CUSCAR, sepa si la operación es una importación o una exportación.

## Pasos

1. En la barra superior, abra **Manifiestos › 📊 Importar Excel/CSV**. En el celular, abra el menú y elija **📊 Importar Excel/CSV**.
2. En **📎 Archivo de Manifiesto**, seleccione el archivo. El sistema reconoce el formato solo.
3. En **🚢 Embarcación**, elija la embarcación del viaje. Tiene prioridad sobre la que diga el archivo.
4. Si el archivo es CUSCAR, elija **Importación** o **Exportación** en **↔️ Tipo de operación (requerido para CUSCAR)**. Para los demás formatos, déjelo sin elegir.
5. Si el archivo no trae número de viaje, escríbalo en **🧭 Número de viaje (opcional)**. En los archivos CUSCAR y TFP, el número que escriba reemplaza al del archivo; en los demás formatos se usa solo si el archivo no trae uno.
6. Complete las fechas solo si quiere reemplazar las del archivo:
    - **📅 Fecha y hora de salida (opcional)**: si la completa, ingrese fecha y hora.
    - **📅 Fecha de Carga (opcional)**: se aplica a todos los conocimientos.
    - **📅 Fecha de Descarga (opcional)**: se aplica a todos los conocimientos y como llegada estimada del viaje. Los archivos CUSCAR que no traen fecha estimada de llegada la necesitan.
7. Haga clic en **Importar Manifiesto**.
8. Espere en la pantalla **📥 Procesando Importación**: el estado pasa de **En cola para procesamiento…** a **Procesando archivo y creando viaje, conocimientos y contenedores…**. Al terminar se abre el reporte.

## Cómo saber que salió bien

Se abre **📊 Reporte de Importación** con la cantidad de conocimientos, contenedores e ítems procesados, y los datos del viaje en **📋 Información del Viaje**. Revise el recuadro **Advertencias de Importación**, si aparece: la importación se completó, pero conviene controlar lo que indica. Haga clic en **Ir al Viaje** para ver el viaje creado. La importación también queda en el [historial](manual:importar-manifiestos/historial-de-importaciones).

## Si algo falla

- **El archivo no puede ser mayor a 10MB.**: el archivo supera el tamaño permitido.
- **La fecha de descarga no puede ser anterior a la fecha de salida.**: corrija las fechas del formulario.
- **Para archivos CUSCAR debe seleccionar Importación o Exportación.**: complete **↔️ Tipo de operación (requerido para CUSCAR)**.
- **No se pudo encontrar un parser compatible para el archivo**: el archivo no está en un formato soportado o está dañado. Revise los [formatos soportados](manual:importar-manifiestos/formatos-soportados).
- **El código de puerto '…' no está en el catálogo del sistema.**, **Código de puerto '…' no existe en el catálogo.** o **Puerto desconocido en el archivo …**: falta dar de alta ese puerto. Si el mensaje sugiere puertos parecidos, revise que el código del archivo sea correcto; si lo es, pida el alta al administrador y vuelva a importar.
- **… no informa número de viaje …**: escriba el número en **🧭 Número de viaje (opcional)** y vuelva a importar.
- **Este archivo ya fue importado anteriormente.** o **El viaje … ya existe para esta embarcación en su empresa.**: ya hay un viaje con ese número para esa embarcación, ya sea por este mismo archivo o por otro. Si lo quiere importar de nuevo, primero [revierta la importación anterior](manual:importar-manifiestos/revertir-una-importacion).
- **Archivo GUARAN inválido:** seguido de **Fila N: campo obligatorio faltante: …**: a esa fila del archivo le falta el dato indicado. Pida el archivo corregido.
- **TFP: …** (mercadería sin descripción, bultos o peso ausente, tipo de contenedor no soportado): el archivo TFP tiene incompleto el dato indicado. Pida el archivo corregido.
- Si la pantalla queda mucho tiempo en **En cola para procesamiento…**, avise al administrador del sistema.
