---
titulo: Cómo crear un viaje a mano y agregar su carga
tema: viajes-y-cargas
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [viaje, carga, crear, manual, embarcacion, ruta, salida, llegada]
relacionados: [viajes-y-cargas/cargar-conocimientos-e-items]
estado: borrador
verificado:
  version: 9187972
  fecha: 2026-11-02
  entorno: codigo
---

## Para qué sirve

Permite crear un viaje con una embarcación y agregar la carga a la que después asociará los conocimientos de embarque.

## Antes de empezar

- [Ingrese con su cuenta](manual:primeros-pasos/ingresar-y-perfil). Su empresa debe tener el rol **Cargas** y su usuario debe tener acceso a viajes y cargas.
- Verifique en [Embarcaciones](ruta:company.vessels.index) que la embarcación pertenezca a su empresa, esté activa y disponible para contratar, y tenga estado operativo activo.
- Si va a asignar un capitán, verifique en [Capitanes](ruta:company.captains.index) que esté activo, disponible y con licencia válida y vigente.
- Tenga definidos el número de viaje, los puertos de origen y destino, las fechas y el tipo de operación. Este recorrido corresponde a **Embarcación única**.

## Pasos

1. Abra **Manifiestos › Viajes** y haga clic en **Nuevo Viaje**.
2. Complete **Número de Viaje** y seleccione **Embarcación única** en **Tipo de Viaje**. El número no debe repetirse para la misma embarcación dentro de su empresa.
3. Seleccione **Embarcación Líder**. Si corresponde, seleccione **Capitán**; también puede dejar **Sin capitán asignado**.
4. Seleccione **País de Origen** y luego **Puerto de Origen**.
5. Seleccione **País de Destino** y luego **Puerto de Destino**. El puerto de destino debe ser distinto del puerto de origen.
6. Complete **Fecha y Hora de Salida** y **Fecha y Hora Estimada de Llegada**. La llegada debe ser posterior a la salida.
7. En **Estado de Carga**, seleccione **Con carga**. Elija **Tipo de Operación** según corresponda y haga clic en **Crear Viaje**.
8. En el detalle del viaje, busque **Cargas Asociadas** y haga clic en **Nueva Carga**.
9. Revise **Número de Carga**, conserve **Planificación** en **Estado Inicial** y compruebe **Embarcación y Capitán (heredados del viaje)**. Complete **Instrucciones Especiales** o **Notas de Manejo** si corresponde.
10. Haga clic en **Crear Shipment** para guardar la carga.

## Cómo saber que salió bien

Al crear el viaje, aparece **Viaje creado exitosamente.** y se abre su detalle. Al guardar la carga, aparece **Shipment creado exitosamente.** y se abre el detalle de esa carga, donde encontrará **Conocimientos de Embarque**.

Continúe con [Agregar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).

## Si algo falla

- **No hay embarcaciones disponibles para crear un viaje. Verifique el estado de sus embarcaciones.**: revise que la embarcación pertenezca a su empresa, esté activa, disponible para contratar y con estado operativo activo.
- **El número de viaje ya existe para esta embarcación.**: revise si el viaje ya fue creado antes de ingresar otro número.
- **La fecha de llegada debe ser posterior a la fecha de salida.**: corrija las fechas y horas antes de guardar.
- **El puerto de origen y destino no pueden ser el mismo.**: seleccione puertos diferentes.
- Si aparece un aviso debajo de un campo al guardar la carga, revise el dato indicado y vuelva a intentar.
