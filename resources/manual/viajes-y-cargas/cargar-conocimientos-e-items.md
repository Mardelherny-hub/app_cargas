---
titulo: Cómo cargar un conocimiento con ítems y contenedores
tema: viajes-y-cargas
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [conocimiento, titulo, BL, item, mercaderia, contenedor, bultos, peso, precinto]
relacionados: [viajes-y-cargas/crear-un-viaje-a-mano]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite agregar un conocimiento de embarque a una carga, detallar sus ítems de mercadería y distribuirlos entre contenedores cuando corresponda.

## Antes de empezar

- [Cree el viaje y su carga](manual:viajes-y-cargas/crear-un-viaje-a-mano). Su empresa debe tener el rol **Cargas** y su usuario debe tener acceso a cargas.
- Mantenga la carga en **Planificación** o **Cargando** para poder agregar ítems.
- Verifique que el cargador y el consignatario estén registrados en [Clientes](ruta:company.clients.index). Tenga disponibles los puertos, el tipo de carga, el tipo de embalaje y, si corresponde, el tipo de contenedor.
- Tenga a mano el conocimiento, las descripciones de mercadería, los bultos, los pesos y los datos de los contenedores y precintos que va a registrar.

## Pasos

1. Abra **Manifiestos › Cargas**, ingrese al detalle de la carga y haga clic en **Nuevo Conocimiento**, dentro de **Conocimientos de Embarque**.
2. Compruebe **Envío** y complete **Número de Conocimiento**, **Fecha del Conocimiento** y **Fecha de Carga**. La fecha del conocimiento no puede ser futura y la de carga no puede ser anterior a ella.
3. Seleccione **Cargador/Exportador** y **Consignatario/Importador**. Deben ser distintos, salvo que corresponda activar **Transporte propio**. Complete **Notificar a** y **Propietario de la Carga** si corresponde.
4. Revise **Puerto de Carga** y **Puerto de Descarga**, que deben ser distintos, y seleccione **Términos de Flete**, **Términos de Pago** y **Moneda**. Complete los demás datos de puertos y aduanas que correspondan a su operación.
5. Seleccione **Tipo Principal de Carga** y **Tipo Principal de Embalaje**. Complete **Descripción de Mercaderías**, **Total de Bultos** y **Peso Bruto (kg)**; agregue **Peso Neto (kg)** y **Volumen (m³)** si dispone de ellos. Revise los demás campos marcados con asterisco y haga clic en **Crear Conocimiento**.
6. En el detalle del conocimiento, haga clic en **+ Agregar Item**. Complete **Descripción de la Mercadería**, **Cantidad de Bultos**, **Peso Bruto (kg)**, **Tipo de Carga** y **Tipo de Embalaje** para ese ítem.
7. Seleccione **País de Origen** y complete los campos marcados con asterisco: **Posición Arancelaria**, **Razón Social Forwarder Exterior**, **Código Aduana Descarga** y **Código Lugar Operativo**. Revise **Operador Logístico Seguro**, **Tránsito Monitoreado** y **Sujeto a RENAR**, y complete los datos adicionales que correspondan a la mercadería.
8. Si la mercadería va en contenedores, seleccione el **Tipo de Carga** correspondiente a contenedores. En la sección de contenedores, complete **Número de Contenedor**, **Tipo de Contenedor**, **Condición** y **Estado**. Use **Agregar Contenedor** para registrar los demás y complete **Números de Precinto**, separados por comas, si corresponde.
9. Para cada contenedor, complete **Cantidad de Bultos** y **Peso Bruto (kg)**, y agregue **Peso Neto (kg)**, **Volumen (m³)** y **Peso de Tara (kg)** si corresponde. Revise **Validación de Totales**: la suma de bultos y de peso bruto de los contenedores debe coincidir con la del ítem. Si la carga no usa contenedores, continúe al paso siguiente.
10. Haga clic en **Guardar y terminar** para volver al detalle de la carga. Si necesita registrar más ítems, use **Guardar y agregar otro** y repita la carga de los datos de cada ítem antes de terminar.

## Cómo saber que salió bien

Después de **Crear Conocimiento**, se abre el detalle con su **Número de Conocimiento**. Allí, **Items de Mercadería** permite revisar los ítems agregados y sus bultos y pesos.

Con **Guardar y agregar otro**, aparece **Item agregado exitosamente.** o un mensaje que identifica los contenedores creados, y el formulario queda listo para otro ítem. Con **Guardar y terminar**, vuelve al detalle de la carga; revise el conocimiento y sus ítems para comprobar lo guardado.

## Si algo falla

- **El consignatario debe ser diferente al cargador (o active "Transporte propio").**: revise las partes del conocimiento; active **Transporte propio** únicamente si corresponde a su operación.
- Si un campo aparece marcado al crear el conocimiento, revise que estén completos los datos obligatorios, que la fecha del conocimiento no sea futura y que los puertos de carga y descarga sean distintos.
- **Número de contenedor es obligatorio** o **Tipo de contenedor es obligatorio**: complete esos datos en cada contenedor agregado.
- Si el aviso indica que la suma de bultos o de peso bruto de los contenedores no coincide con el total del ítem, corrija la distribución antes de guardar.
- **Peso bruto no puede ser menor al peso neto**: revise los pesos del contenedor indicado.
- Si no puede abrir el formulario para agregar un ítem, revise que la carga siga en **Planificación** o **Cargando**.
