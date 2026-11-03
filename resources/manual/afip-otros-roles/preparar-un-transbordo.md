---
titulo: Cómo preparar un transbordo entre barcazas
tema: afip-otros-roles
orden: 20
roles: [company-admin, user]
roles_empresa: [Transbordos]
palabras_clave: [transbordo, tránsito, barcaza, distribución, reparto, homologación, ARCA, convoy]
relacionados:
  - viajes-y-cargas/armar-un-convoy
  - viajes-y-cargas/cargar-conocimientos-e-items
  - mi-empresa/configurar-la-empresa
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Permite repartir la mercadería de un conocimiento de tránsito o transbordo entre las barcazas de un mismo viaje, indicando bultos y peso por barcaza, guardar ese reparto como borrador y validarlo. Esta preparación no envía información a ARCA. Solo en ambiente de pruebas, un administrador de la empresa puede además enviar el reparto a homologación.

## Antes de empezar

- El viaje tiene al menos dos barcazas: vea [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).
- El conocimiento tiene sus líneas de mercadería y, si corresponde, sus contenedores: vea [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).
- El conocimiento está marcado como **Tránsito / Trasbordo** desde la edición del conocimiento, sigue editable y no es fraccionado.
- Para el envío a homologación: usted es administrador de la empresa y la empresa está en **Testing (Pruebas)**. Vea [Cómo configurar los datos y webservices de la empresa](manual:mi-empresa/configurar-la-empresa).

## Pasos

1. En el menú lateral, abra **🏛️ Enviar a AFIP/DNA**. En el recuadro **Transbordos — Preparación de cargas**, haga clic en **Ingresar a Transbordos**.
2. Si hace falta, escriba en **Buscar viaje o conocimiento** y haga clic en **Buscar**. Cada conocimiento muestra si está **Disponible para preparar distribución**; haga clic en **Preparar distribución**.
3. En **Distribución de Transbordos**, revise **Mercadería del conocimiento**: ahí figuran las líneas con sus bultos, peso y contenedores.
4. En **Distribución por barcaza**, haga clic en **+ Agregar asignación** y elija **Barcaza** y **Línea de mercadería**. Si la línea tiene contenedores, elija el **Contenedor**. Complete **Bultos** y **Peso kg**. Use **Quitar** para borrar una fila.
5. Agregue las filas necesarias hasta que cada línea quede repartida completa: la suma de bultos y de peso debe ser igual a lo declarado, y cada contenedor debe asignarse a una sola barcaza.
6. Haga clic en **Guardar borrador**.
7. Haga clic en **Validar distribución guardada**. Si cambia algo, vuelva a guardar antes de validar.
8. Solo administradores en ambiente de pruebas y con carga suelta: en **Homologación ARCA · RegistrarTitEnvios**, complete por cada barcaza **CUIT ATA MIC de esta barcaza**, **Origen · Código lugar operativo**, **Origen · Código ciudad (UN/LOCODE)**, **Destino · Código lugar operativo** y **Destino · Código ciudad (UN/LOCODE)**. Haga clic en **Preparar reserva (sin enviar)**. A partir de la reserva, el reparto ya no se puede modificar.
9. Revise la reserva, marque **Confirmo que revisé este conocimiento y autorizo su envío a homologación.**, haga clic en **Confirmar y enviar a homologación** y acepte el aviso. El envío sale en ese momento al servidor de homologación de ARCA y se hace una sola vez.

## Cómo saber que salió bien

- Al guardar aparece **Borrador guardado. Ahora podés validar la distribución.**
- Al validar aparece **Distribución válida en la aplicación. Sin envío a ARCA.** y, en **Resultado del preflight**, una fila por barcaza con el número de envío y la cantidad de asignaciones.
- Si hizo la reserva, aparece **Reserva creada:** con su número. Tras el envío a homologación aparece **Homologación aceptada.** y un **TRACK ARCA:** por barcaza.

## Si algo falla

- **"No se encontraron viajes con conocimientos de transbordo":** ningún conocimiento de la empresa está marcado como **Tránsito / Trasbordo**. Edite el conocimiento, márquelo y vuelva a buscar.
- **"Se necesitan al menos dos barcazas en este viaje":** agregue otra barcaza al viaje.
- **"Fraccionado (pendiente de implementación)"** o **"No editable":** ese conocimiento no se puede preparar desde esta pantalla.
- **"No hay líneas de mercadería cargadas en este conocimiento":** cargue las líneas del conocimiento antes de repartirlas.
- **"Completá barcaza, línea, contenedor (si aplica), bultos y peso":** falta un dato en alguna fila.
- **"Guardá los cambios antes de validar":** haga clic en **Guardar borrador** y luego valide.
- El mensaje termina en **la distribución debe completar exactamente bultos y peso** o **la distribución supera los bultos o el peso declarado**: ajuste las filas de esa línea para que sumen lo declarado.
- **"Un contenedor ya está asignado a otra barcaza del mismo viaje":** deje cada contenedor en una sola barcaza.
- **"El viaje ya tiene una operación MIC/DTA iniciada o de resultado incierto":** el reparto quedó fijo y no se puede modificar. Consulte antes de operar de nuevo.
- **"El generador MIC/DTA requiere un único conocimiento en el embarque de origen":** el envío a homologación solo admite un conocimiento en la carga de origen.
- **"Resultado aduanero incierto. No se permite reenviar":** no repita el envío ni cree otra reserva. Pida una revisión técnica con el número de reserva.
- **"La transmisión no está disponible para esta operación o usuario":** el envío a homologación requiere usuario administrador, empresa en pruebas y carga sin contenedores. La preparación y la validación siguen disponibles.
