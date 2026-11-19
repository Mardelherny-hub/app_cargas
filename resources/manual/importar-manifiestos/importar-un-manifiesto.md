---
titulo: Cómo importar un manifiesto
tema: importar-manifiestos
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, manifiesto, archivo, Excel, XML, EDI, CUSCAR, MSC, Don Toyo, viaje, carga masiva]
relacionados: [importar-manifiestos/formatos-soportados, importar-manifiestos/historial-de-importaciones, importar-manifiestos/revertir-una-importacion]
estado: publicado
verificado:
  version: d08c3f2
  fecha: 2026-11-18
  entorno: stage
---

## Para qué sirve

Crea un viaje completo a partir del archivo de manifiesto que envía la línea o el agente: el viaje, sus conocimientos, las líneas de mercadería y los contenedores. Los clientes que no existen se dan de alta solos.

## Antes de empezar

- Tenga el archivo en uno de los [formatos soportados](manual:importar-manifiestos/formatos-soportados), de hasta 10 MB.
- La embarcación tiene que estar cargada para su empresa, activa y en estado operativo activo. Vea [Ir a Embarcaciones](ruta:company.vessels.index).
- Los puertos del archivo tienen que existir en el sistema: la importación no crea puertos. Si falta uno, pida el alta al administrador con el código y el nombre del puerto.
- Si es un archivo CUSCAR, sepa si la operación es una importación o una exportación.
- En MSC TXT, revise dentro del archivo VESSEL NAME y VOYAGE. El archivo contiene además puertos, conocimientos, bultos y contenedores; los números y tipos de contenedor deben ser válidos.
- En Don Toyo, use un archivo XLS antiguo o XLSX equivalente, con las hojas BUQUE, BLS y CONTENEDORES. La hoja RECARGOS no se importa. Los puertos y códigos de tipo de contenedor deben estar registrados; los datos deben permitir identificar al remitente y al destinatario.

## Pasos

1. En la barra superior, abra **Manifiestos › 📊 Importar Excel/CSV**. En el celular, abra el menú y elija **📊 Importar Excel/CSV**.
2. En **📎 Archivo de Manifiesto**, seleccione el archivo. El sistema reconoce el formato solo.
3. En **🚢 Embarcación**, elija la embarcación del viaje. Es obligatorio para todos los formatos. Para MSC, su nombre debe coincidir con VESSEL NAME dentro del archivo: elegirla aquí no reemplaza ese requisito. No se compara con el nombre con que guardó el archivo. Don Toyo no trae el nombre de la embarcación: elíjala del listado. Si el IMO de la hoja BUQUE difiere del de la embarcación elegida, verá una advertencia y la importación puede continuar.
4. Si el archivo es CUSCAR, elija **Importación** o **Exportación** en **↔️ Tipo de operación (requerido para CUSCAR)**. Para los demás formatos, déjelo sin elegir.
5. Revise el número de viaje. En MSC, VOYAGE debe estar informado dentro del archivo; el número escrito en el formulario no lo reemplaza. En Don Toyo se usa VIAJE de la hoja BUQUE, salvo que usted escriba uno en **🧭 Número de viaje (opcional)**, que tiene prioridad. Si faltan ambos, escríbalo allí. En CUSCAR y TFP, el número escrito también tiene prioridad; en los demás formatos se usa solo si el archivo no trae uno.
6. Las fechas del formulario son opcionales. Si completa la salida, ingrese fecha y hora:
    - **📅 Fecha y hora de salida (opcional)**.
    - **📅 Fecha de Carga (opcional)**.
    - **📅 Fecha de Descarga (opcional)**. Los archivos CUSCAR que no traen fecha estimada de llegada la necesitan.

    Cuando el archivo trae fechas, las que ingrese en el formulario tienen prioridad: la carga se aplica a los conocimientos y la descarga también a la llegada estimada del viaje.

    En MSC y Don Toyo, revise las fechas del viaje y de los conocimientos después de importar. Complete las que falten en los documentos antes de presentarlos, utilizando las fechas reales que correspondan.
7. Haga clic en **Importar Manifiesto**.
8. Espere en la pantalla **📥 Procesando Importación**: el estado pasa de **En cola para procesamiento…** a **Procesando archivo y creando viaje, conocimientos y contenedores…**. Al terminar se abre el reporte.

## Cómo saber que salió bien

Se abre **📊 Reporte de Importación** con la cantidad de conocimientos, contenedores e ítems procesados, y los datos del viaje en **📋 Información del Viaje**. Revise el recuadro **Advertencias de Importación**, si aparece: la importación se completó, pero conviene controlar lo que indica. Haga clic en **Ir al Viaje** para ver el viaje creado. La importación también queda en el [historial](manual:importar-manifiestos/historial-de-importaciones).

## Si algo falla

- **El archivo no puede ser mayor a 10MB.**: el archivo supera el tamaño permitido.
- **La fecha de descarga no puede ser anterior a la fecha de salida.**: corrija las fechas del formulario.
- **Para archivos CUSCAR debe seleccionar Importación o Exportación.**: complete **↔️ Tipo de operación (requerido para CUSCAR)**.
- Si el sistema no reconoce el archivo, puede estar dañado o no corresponder a un formato soportado. Revise los [formatos soportados](manual:importar-manifiestos/formatos-soportados). Los archivos CSV no son compatibles, aunque el menú diga **Importar Excel/CSV**.
- **El código de puerto '…' no está en el catálogo del sistema.**, **Código de puerto '…' no existe en el catálogo.** o **Puerto desconocido en el archivo …**: falta dar de alta ese puerto. Si el mensaje sugiere puertos parecidos, revise que el código del archivo sea correcto; si lo es, pida el alta al administrador y vuelva a importar.
- Si falta el número de viaje, escríbalo en **🧭 Número de viaje (opcional)** y vuelva a importar. Para MSC, pida que agreguen VOYAGE dentro del archivo.
- **Este archivo ya fue importado anteriormente.** o **El archivo ya fue importado. Revise el historial de importaciones.**: el mismo archivo ya se importó y no se revirtió. Revise el [historial](manual:importar-manifiestos/historial-de-importaciones). Si quiere importarlo de nuevo, primero [revierta la importación anterior](manual:importar-manifiestos/revertir-una-importacion).
- **El viaje … ya existe para esta embarcación en su empresa.**: revise el viaje existente y el número informado antes de volver a importar.
- Si MSC indica que la embarcación no coincide, compare el nombre de la embarcación elegida con VESSEL NAME dentro del archivo. Elija una embarcación activa de su empresa con ese nombre o pida que corrijan el archivo.
- Si Don Toyo indica que faltan tipos de contenedor, pida al administrador que registre los códigos indicados con sus dimensiones correctas. Luego vuelva a importar.
- **Archivo GUARAN inválido:** seguido de **Fila N: campo obligatorio faltante: …**: a esa fila del archivo le falta el dato indicado. Pida el archivo corregido.
- **TFP: …** (mercadería sin descripción, bultos o peso ausente, tipo de contenedor no soportado): el archivo TFP tiene incompleto el dato indicado. Pida el archivo corregido.
- Si la pantalla queda mucho tiempo en **En cola para procesamiento…**, avise al administrador del sistema.
