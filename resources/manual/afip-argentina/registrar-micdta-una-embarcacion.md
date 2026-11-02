---
titulo: Cómo registrar el MIC/DTA de una embarcación
tema: afip-argentina
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [MIC/DTA, AFIP, autopropulsado, embarcación, RegistrarMicDta, RegistrarTitEnvios, número de viaje]
relacionados:
  - afip-argentina/registrar-micdta-convoy
  - afip-argentina/registrar-salida-zona-primaria
  - afip-argentina/anular-y-rectificar
estado: borrador
verificado:
  version: c62042a
  fecha: 2026-10-30
  entorno: codigo
---

## Para qué sirve

Registra ante AFIP los títulos de transporte y el MIC/DTA de un viaje con una sola embarcación autopropulsada. AFIP devuelve el MIC/DTA y el número de viaje que se usa para la salida de zona primaria.

## Antes de empezar

- El viaje tiene una sola carga, con sus conocimientos completos: cargador y consignatario en cada uno.
- La embarcación tiene cargado **Número de Registro/Matrícula**.
- El viaje tiene asignado un **Capitán** con **Número Documento** cargado.
- El viaje tiene cargados el puerto de origen y el de destino.
- La empresa tiene su certificado AFIP cargado y el ambiente correcto (homologación o producción).

Si el viaje tiene un remolcador y barcazas, siga [Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA**.
2. En **Webservices Aduaneros**, busque la tarjeta del viaje y, en el recuadro **MIC/DTA**, haga clic en **Enviar Ahora** (o **Ver Detalles**, si el viaje ya tiene envíos).
3. En **Métodos Principales (Flujo Básico)**, revise el recuadro **1. RegistrarTitEnvios**: **Embarcación** y **Puertos** tienen que mostrar ✓, y **BLs c/Shipper** y **BLs c/Consignee** tienen que coincidir con el total de conocimientos.
4. Haga clic en **1. RegistrarTitEnvios** y confirme con **Aceptar**.
5. Cuando aparezca **RegistrarTitEnvios - Exitoso**, haga clic en **Cerrar** y actualice la página.
6. Revise el recuadro **3. RegistrarMicDta**: no tiene que aparecer el aviso **Faltan:**.
7. Haga clic en **3. RegistrarMicDta** y confirme con **Aceptar**.
8. Cuando aparezca **RegistrarMicDta - Exitoso**, haga clic en **Cerrar** y actualice la página.

Mientras un botón muestra el indicador de carga, no vuelva a hacer clic: espere el mensaje de resultado.

## Cómo saber que salió bien

- El mensaje de resultado dice **RegistrarMicDta - Exitoso** (o **Exitoso con observaciones**: lea las observaciones antes de seguir).
- En el recuadro **3. RegistrarMicDta** aparecen **idMicDta** y **Nro Viaje**.
- En **Zona Primaria**, el recuadro **4. RegistrarSalidaZP** muestra el mismo **Nro. Viaje**. Con ese número sigue la salida: vea [Cómo registrar la salida de zona primaria](manual:afip-argentina/registrar-salida-zona-primaria).
- En **Últimas 10 transacciones MIC/DTA** puede abrir lo que se envió y lo que respondió AFIP con **📤 Request** y **📥 Response**.

## Si algo falla

- **Debajo de RegistrarTitEnvios aparece "Verifique:"** seguido de Shipments, BLs, Embarcación o Puertos: falta ese dato en el viaje. Complételo y vuelva al paso 3.
- **Debajo de RegistrarMicDta aparece "Faltan:"**:
    - **TRACKs:** todavía no se ejecutó **1. RegistrarTitEnvios** con éxito.
    - **Embarcación:** falta la embarcación o su **Número de Registro/Matrícula**.
    - **Capitán:** falta el capitán del viaje o su **Número Documento**.
    - **Puertos:** falta el puerto de origen o el de destino.
- **El mensaje de resultado dice "Error" y lista "Errores encontrados:":** es la respuesta de AFIP. Corrija el dato que indica y vuelva a ejecutar el mismo paso. Si no queda claro, abra **📥 Response** en **Últimas 10 transacciones MIC/DTA**.
- **Lugar operativo incorrecto:** la aduana o el lugar operativo informados para el puerto no corresponden entre sí. Revise esa configuración del puerto antes de reintentar.
- **El MIC/DTA quedó registrado con un dato mal:** no lo registre de nuevo. Vea [Cómo anular o rectificar](manual:afip-argentina/anular-y-rectificar).
