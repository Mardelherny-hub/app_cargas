---
titulo: Cómo armar un viaje de convoy
tema: viajes-y-cargas
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [convoy, remolcador, empujador, autopropulsado, líder, barcaza, embarcación, carga, conocimiento, MIC/DTA]
relacionados: [viajes-y-cargas/crear-un-viaje-a-mano, afip-argentina/registrar-micdta-convoy]
estado: publicado
verificado:
  version: dbd4721
  fecha: 2026-11-04
  entorno: stage
---

## Para qué sirve

Permite reunir en un mismo viaje la embarcación que conduce el convoy (un remolcador, un empujador o un buque autopropulsado) y sus barcazas, con una carga por embarcación y los conocimientos correspondientes, para continuar con el MIC/DTA del convoy.

## Antes de empezar

- Revise [cómo crear un viaje y su carga](manual:viajes-y-cargas/crear-un-viaje-a-mano). Su empresa debe tener el rol **Cargas** y su usuario debe tener acceso a viajes y cargas.
- Verifique en [Embarcaciones](ruta:company.vessels.index) que la embarcación líder y las barcazas pertenezcan a su empresa, estén activos, disponibles para contratar y con estado operativo activo. Compruebe el tipo de embarcación, la matrícula, el país de bandera y el propietario de cada uno.
- Revise el tipo de embarcación en la ficha de cada una: la líder no puede ser de categoría **Barcaza**, y cada barcaza sí. Debe haber una sola embarcación que no sea barcaza. El sistema usa el tipo para saber cuál conduce el convoy al enviar a AFIP.
- Verifique que el propietario de cada embarcación tenga nombre, domicilio, país y número tributario completos. El capitán de la embarcación líder debe tener nombre y documento completos, estar disponible y tener licencia válida.
- Tenga definidos los puertos, las fechas, el orden de las embarcaciones y qué conocimientos transportará cada barcaza. Revise [cómo cargar conocimientos, ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).

## Pasos

1. Abra **Manifiestos › Viajes** y haga clic en **Nuevo Viaje**. También puede usar **Crear Viaje Completo › Planificar Viaje** en el menú lateral.
2. Complete **Número de Viaje**, seleccione **Convoy** en **Tipo de Viaje** y la embarcación que conduce el convoy en **Embarcación Líder**. Seleccione **Capitán** y complete los países, puertos, fechas y **Tipo de Operación**, como en la creación de un viaje. En **Estado de Carga**, seleccione **Con carga** si el convoy transporta mercadería y haga clic en **Crear Viaje**.
3. En **Cargas Asociadas**, haga clic en **Nueva Carga**. Revise **Número de Carga**, seleccione la embarcación líder en **Embarcación** y conserve **Planificación** en **Estado Inicial**. Compruebe **Capitán del Convoy**, que se toma del viaje.
4. Abra **Configuración de Convoy**, seleccione **Líder de Convoy** en **Rol en el Convoy** e indique su **Posición en Convoy**. Haga clic en **Crear Shipment**. Al crear las cargas, utilice posiciones distintas entre 1 y 10.
5. Vuelva a **Manifiestos › Viajes**, abra el mismo viaje y haga clic en **Nueva Carga**.
6. Revise **Número de Carga** y seleccione una barcaza en **Embarcación**. En **Configuración de Convoy**, elija **Empujada** o **Remolcada** en **Rol en el Convoy**, según corresponda, y una **Posición en Convoy** distinta de las ya usadas. Conserve **Planificación** y haga clic en **Crear Shipment**. Repita la creación de una carga por cada barcaza del convoy.
7. Abra cada carga que transportará mercadería y use **Nuevo Conocimiento**. [Cargue los conocimientos, sus ítems y sus contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items) en la carga de la embarcación que los llevará. Si la embarcación líder también lleva mercadería, como un buque autopropulsado, cargue sus conocimientos en su propia carga. Si viaja en lastre, conserve su carga sin conocimientos.
8. En el viaje, revise **Cargas Asociadas**: debe haber una carga para la embarcación líder y una por cada barcaza, sin repetir embarcaciones. Abra cada carga y compruebe sus **Conocimientos de Embarque**; para la mercadería, revise que los ítems tengan bultos y peso, y que los totales de los contenedores coincidan con los de sus ítems.
9. Cuando la formación y la documentación estén completas, continúe con [Registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).

## Cómo saber que salió bien

El viaje conserva **Convoy** en **Tipo de Viaje** y en **Cargas Asociadas** figuran la embarcación líder y todas las barcazas. Cada carga muestra la embarcación correcta y los conocimientos que transportará; la embarcación líder queda sin conocimientos solo si va en lastre.

Al crear cada carga, aparece **Shipment creado exitosamente.**. La documentación queda preparada para continuar con el registro del MIC/DTA del convoy.

## Si algo falla

- Si aparece un aviso en **Embarcación**, compruebe que esa embarcación no tenga ya una carga en el mismo viaje. Abra la carga existente para agregar sus conocimientos.
- Si el aviso indica que ya existe otra embarcación en esa posición del convoy, asigne una posición diferente. Al crear cargas, utilice valores entre 1 y 10.
- Si no aparece **Embarcación** para seleccionar una barcaza, revise que el viaje sea de tipo **Convoy** y que la primera carga tenga el rol **Líder de Convoy**.
- Si no puede agregar ítems, revise que la carga siga en **Planificación** o **Cargando**.
- Si faltan datos de matrícula, bandera, propietario o capitán al continuar con el MIC/DTA, complete la ficha correspondiente antes de seguir.
