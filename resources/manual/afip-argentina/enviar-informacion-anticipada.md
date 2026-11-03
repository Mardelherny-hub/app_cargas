---
titulo: Cómo enviar la información anticipada a AFIP
tema: afip-argentina
orden: 70
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [información anticipada, anticipada, importación, RegistrarViaje, RectificarViaje, RegistrarTitulosCbc, CerrarViaje, ATA CBC, AFIP]
relacionados:
  - afip-argentina/registrar-micdta-una-embarcacion
estado: borrador
verificado:
  version: 9187972
  fecha: 2026-11-02
  entorno: codigo
---

## Para qué sirve

Informa a AFIP, antes del arribo, el viaje de importación y sus conocimientos. Se hace en etapas: registrar el viaje, registrar los títulos que descargan en Argentina y cerrar el viaje. AFIP devuelve el identificador del viaje.

## Antes de empezar

- El viaje tiene cargados sus conocimientos, con puertos de carga y descarga, fechas, bultos y pesos.
- Tiene a mano los CUIT de los ATA CBC que intervienen en el viaje, si hay otros además de su empresa. Si su empresa tiene CUIT argentino y el viaje lleva mercadería, su CUIT se informa solo.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA**.
2. En la tarjeta del viaje, en el recuadro **Anticipada** (marcado **Importación**), haga clic en **Enviar Ahora** (o **Ver Detalles**, si ya hubo envíos).
3. Revise **Estado de Validación**. Tiene que decir **Viaje válido para envío**. Si aparece **Errores de validación**, corrija cada punto en el viaje o en sus conocimientos y vuelva a esta pantalla.
4. En **Métodos de Información Anticipada**, en **RegistrarViaje**, haga clic en **Enviar**.
5. En la ventana de confirmación, complete **CUIT ATA CBC que intervienen en el viaje** si corresponde (11 dígitos cada uno, separados por coma) y haga clic en **Confirmar Envío**. El envío sale a AFIP en ese momento.
6. Si el viaje tiene conocimientos que descargan en Argentina, en **RegistrarTitulosCbc** haga clic en **Registrar** y confirme con **Confirmar Envío**.
7. Para terminar, en **CerrarViaje** haga clic en **Cerrar Viaje** y confirme con **Confirmar Envío**. El cierre informa los conocimientos que no descargan en Argentina.

Si después de registrar el viaje cambió algún dato, use **RectificarViaje › Rectificar** antes de cerrarlo.

## Cómo saber que salió bien

- Después de cada envío aparece **¡Envío Exitoso!** con **ID Transacción** e **IdentificadorViaje AFIP**, y la página se actualiza al hacer clic en **Cerrar**.
- **RegistrarViaje** y **CerrarViaje** muestran **✓ Completado**.
- Cada envío queda en **Historial de Transacciones**, con su estado.

## Si algo falla

- **El botón Enviar de RegistrarViaje está deshabilitado:** hay errores en **Estado de Validación**. Corríjalos primero.
- **RectificarViaje, RegistrarTitulosCbc o CerrarViaje dicen "Requiere Paso 1":** todavía no se registró el viaje con éxito.
- **"Cada CUIT de ATA CBC debe contener 11 dígitos":** revise los CUIT escritos en la confirmación.
- **"No hay conocimientos con descarga en Argentina para RegistrarTitulosCbc":** el viaje no tiene títulos que descarguen en Argentina; ese paso no corresponde.
- **"No hay conocimientos con descarga fuera de Argentina para CerrarViaje":** el cierre no encontró conocimientos para informar. Revise los puertos de descarga del viaje.
- **Aparece "Error en el Envío" con otro mensaje:** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar. El detalle queda en **Historial de Transacciones**.
