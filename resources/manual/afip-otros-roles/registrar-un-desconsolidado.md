---
titulo: Cómo registrar un desconsolidado en AFIP
tema: afip-otros-roles
orden: 10
roles: [company-admin, user]
roles_empresa: [Desconsolidador]
palabras_clave: [desconsolidado, desconsolidación, título madre, conocimiento hijo, ATA-DESC, contenedor, AFIP, Aduana]
relacionados:
  - datos-maestros/registrar-un-cliente
  - catalogos/gestionar-puertos
  - mi-empresa/cargar-certificado-digital
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite a una empresa desconsolidadora abrir un expediente sobre un conocimiento madre, cargar sus conocimientos hijos con la mercadería y los contenedores, revisarlos y registrarlos ante AFIP. El expediente no depende de un viaje cargado en la aplicación: se identifica con las referencias que informa la Aduana.

## Antes de empezar

- Tenga a mano el identificador de viaje AFIP (hasta 16 caracteres), el código del puerto de embarque del conocimiento madre (5 caracteres) y el número del conocimiento madre (hasta 18 caracteres).
- El cargador y el consignatario de cada conocimiento hijo están registrados como clientes: vea [Cómo registrar y consultar un cliente](manual:datos-maestros/registrar-un-cliente).
- Los puertos de embarque y de descarga existen en el catálogo, y el de descarga es argentino: vea [Cómo crear y editar un puerto](manual:catalogos/gestionar-puertos).
- La empresa tiene cargado su certificado digital y el ambiente correcto: vea [Cómo cargar un certificado digital de la empresa](manual:mi-empresa/cargar-certificado-digital).

## Pasos

1. En el menú lateral, abra **📦 Desconsolidados Argentina**. Para retomar un expediente, use **Continuar** en **Operaciones guardadas**; para uno nuevo, haga clic en **Iniciar desconsolidación**.
2. Complete **Identificador de viaje AFIP**, **Código del puerto de embarque** y **Número del conocimiento madre**, y haga clic en **Guardar y continuar**. Si el expediente ya existía, la aplicación abre el existente.
3. Si algún contenedor no tiene vencimiento CSC ni código ACEP, complete **Fecha de arribo documentada** y haga clic en **Guardar fecha de arribo**.
4. Haga clic en **Agregar conocimiento hijo**. En **Datos del conocimiento**, complete **Número del conocimiento hijo**, las fechas (**Fecha de embarque del conocimiento hijo**, **Fecha de carga en el lugar de origen**, **Fecha de descarga**) y busque **Cargador / remitente** y **Consignatario** escribiendo al menos 2 caracteres y eligiendo de la lista.
5. En **Puertos del conocimiento hijo**, elija **Puerto de embarque del conocimiento hijo** y **Puerto de descarga en Argentina**. En **Datos generales de la carga**, complete **Tipo de carga**, **Peso bruto declarado (kg)**, **Descripción de la mercadería**, **Marcas de los bultos**, **¿El conocimiento es consolidado?** y **¿Corresponde tránsito o transbordo?**. Haga clic en **Guardar conocimiento hijo**.
6. En el conocimiento, haga clic en **Agregar línea de mercadería**. Complete **Número de línea**, **Tipo de carga**, **Código de embalaje ATA-DESC**, **Condición H / P**, **Cantidad de bultos declarada**, **Peso bruto de esta línea (kg)**, **Descripción de la mercadería**, **Marcas o números de los bultos**, **Posición arancelaria declarada (NCM)** y responda **¿Operador logístico seguro?**, **¿Tránsito monitoreado?** y **¿Corresponde RENAR?**. Haga clic en **Guardar línea como borrador**. Repita por cada línea.
7. Si el embalaje es 05 (carga contenedorizada), haga clic en **Vincular contenedor existente** en la línea e ingrese el **Número de contenedor**. Si no está en el catálogo, use **Registrar contenedor nuevo**. Para cargar la documentación de un contenedor ya vinculado, use **Completar CSC / ACEP** y **Guardar documentación**.
8. Haga clic en **Validar datos sin transmitir** y corrija lo que se indique. Esta revisión no envía nada a la Aduana.
9. Haga clic en **Ir a Enviar a Aduana**. En **Acción**, elija **Registrar títulos**; en **Conocimientos a procesar**, marque los conocimientos hijos; marque **Confirmo que revisé la información y comprendo que esta operación puede registrar, modificar o eliminar datos en Aduana.** y haga clic en **Enviar a Aduana**. La información sale a AFIP en ese momento.
10. Para dar de baja en la Aduana un conocimiento ya registrado, repita el paso anterior con **Eliminar títulos registrados en Aduana**.

## Cómo saber que salió bien

- Al guardar cada parte aparece la confirmación correspondiente, por ejemplo **Conocimiento hijo guardado como borrador.** Guardar no envía nada a la Aduana.
- La validación termina con **La revisión local de datos terminó sin observaciones. No se transmitió información a Aduana.**
- Al enviar aparece **Aduana aceptó la operación. Referencia de transacción:** seguido de un número. Desde ese momento los conocimientos enviados muestran **Este conocimiento no está disponible para agregar líneas en su estado actual.**

## Si algo falla

- **"La empresa no está habilitada para operar Desconsolidados. Consultá al administrador":** la empresa no tiene el rol de desconsolidador. Pida al administrador que lo habilite.
- **"El puerto de descarga de ATA-DESC debe estar ubicado en Argentina":** elija un puerto argentino en **Puerto de descarga en Argentina**.
- **"El puerto seleccionado no tiene un código de país AFIP válido configurado":** falta el código AFIP del país de ese puerto. Vea [Cómo crear y editar un país](manual:catalogos/gestionar-paises).
- **"No hay tipos de carga habilitados para desconsolidación":** el botón de guardar queda deshabilitado hasta que el administrador habilite al menos un tipo de carga.
- **"No se encontró un contenedor activo y habilitado con ese número":** revise el número o use **Registrar contenedor nuevo**.
- **"Informá la fecha CSC o el código ACEP, pero no ambos simultáneamente":** deje solo uno de los dos datos.
- **"Agregá al menos un conocimiento hijo para validar el expediente":** cargue un conocimiento hijo antes de validar.
- **"Revisá estos datos":** con una lista de faltantes al validar o enviar: complete cada dato indicado en el conocimiento, la línea o el contenedor y vuelva a validar.
- Aparece un aviso de que la transacción **tiene un resultado pendiente o no confirmado** o **tiene un rechazo pendiente de revisión**: no reenvíe. Verifique el estado con la Aduana antes de volver a intentar.
- **"No fue posible confirmar el resultado de la operación":** no repita el envío hasta confirmar con la Aduana qué recibió.
- No aparece **Corregir referencias del expediente**: esa opción solo está disponible mientras el expediente no tiene conocimientos hijos.
