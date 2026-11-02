---
titulo: Cómo registrar el MIC/DTA de un convoy
tema: afip-argentina
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [MIC/DTA, convoy, remolcador, barcaza, AFIP, RegistrarMicDta, RegistrarConvoy, RegistrarTitEnvios]
relacionados:
  - viajes-y-cargas/armar-un-convoy
  - afip-argentina/registrar-micdta-una-embarcacion
  - afip-argentina/registrar-salida-zona-primaria
  - afip-argentina/anular-y-rectificar
  - preguntas-frecuentes/dos-convoyes-al-mismo-tiempo
estado: borrador
verificado:
  version: c62042a
  fecha: 2026-10-30
  entorno: codigo
---

## Para qué sirve

Registra ante AFIP un MIC/DTA por cada embarcación del convoy y después los agrupa en un convoy. Al terminar, AFIP devuelve el número de viaje que se usa para la salida de zona primaria.

## Antes de empezar

- El viaje tiene que estar armado como convoy: una carga para el remolcador o empujador y una carga por cada barcaza. Vea [Cómo armar un convoy](manual:viajes-y-cargas/armar-un-convoy).
- El remolcador va sin conocimientos (en lastre). Cada barcaza lleva sus conocimientos, con cargador y consignatario completos.
- El remolcador tiene matrícula y capitán con número de documento. Las barcazas no llevan capitán.
- El viaje tiene cargados el puerto de origen y el de destino.
- La empresa tiene su certificado AFIP cargado y el ambiente correcto (homologación o producción).

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA**.
2. En **Webservices Aduaneros**, busque la tarjeta del viaje y, en el recuadro **MIC/DTA**, haga clic en **Enviar Ahora** (o **Ver Detalles**, si el viaje ya tiene envíos).
3. Arriba, en **Embarcaciones del Viaje**, verifique que aparezcan todas las embarcaciones: el remolcador como **AUT** y cada barcaza como **BAR**.
4. En **Métodos Principales (Flujo Básico)**, haga clic en **1. RegistrarTitEnvios** y confirme con **Aceptar**. El remolcador en lastre se omite solo.
5. Cuando aparezca **RegistrarTitEnvios - Exitoso**, haga clic en **Cerrar** y actualice la página.
6. Haga clic en **3. RegistrarMicDta** y confirme con **Aceptar**. El sistema registra un MIC/DTA por cada embarcación del viaje y saltea las que ya lo tienen.
7. Cuando aparezca **RegistrarMicDta - Exitoso**, haga clic en **Cerrar** y actualice la página. Cada embarcación tiene que mostrar ✅ en **Embarcaciones del Viaje**.
8. En **Gestión de Convoy**, haga clic en **4. RegistrarConvoy** y confirme con **Aceptar**. El sistema arma el convoy solo con los MIC/DTA de este viaje: el remolcador como cabecera y las barcazas como remolcadas.
9. Cuando aparezca **RegistrarConvoy - Exitoso**, haga clic en **Cerrar** y actualice la página.

Mientras un botón muestra el indicador de carga, no vuelva a hacer clic: espere el mensaje de resultado.

## Cómo saber que salió bien

- En **Embarcaciones del Viaje**, todas las embarcaciones muestran ✅.
- En el recuadro **4. RegistrarConvoy**, **Estado** dice **Success**.
- En **Zona Primaria**, el recuadro **4. RegistrarSalidaZP** muestra el **Nro. Viaje** que devolvió AFIP. Con ese número sigue la salida: vea [Cómo registrar la salida de zona primaria](manual:afip-argentina/registrar-salida-zona-primaria).
- En **Últimas 10 transacciones MIC/DTA** puede abrir lo que se envió y lo que respondió AFIP con **📤 Request** y **📥 Response**.

## Si algo falla

- **Una embarcación muestra ❌:** apoye el mouse sobre ❌ para ver el motivo. Corrija el dato y vuelva a hacer clic en **3. RegistrarMicDta**; las embarcaciones ya registradas no se reenvían.
- **Debajo de RegistrarMicDta aparece "Faltan:"** seguido de TRACKs, Embarcación, Capitán o Puertos: complete ese dato. TRACKs significa que todavía no se ejecutó **1. RegistrarTitEnvios** con éxito.
- **"Se necesitan al menos 2 MIC/DTA distintos para formar convoy":** falta el MIC/DTA de alguna embarcación. Repita el paso 6.
- **"No se encontró MIC/DTA del remolcador/autopropulsado"** o **"No se encontraron MIC/DTA de barcazas":** revise en la ficha de cada embarcación que el tipo sea el correcto (remolcador o empujador, y barcaza).
- **"RegistrarConvoy no es aplicable para viajes de una sola embarcación":** el viaje tiene una sola carga. Use [Cómo registrar el MIC/DTA de una embarcación](manual:afip-argentina/registrar-micdta-una-embarcacion).
- **Debe declarar capitán para la embarcación cabecera del convoy:** cargue el capitán, con tipo y número de documento, en la carga del remolcador.
- **Todos los MIC/DTA del convoy deben tener la misma ruta:** todas las cargas tienen que salir del mismo puerto y llegar al mismo destino.
- **El MIC/DTA fue registrado sin indicar que integra convoy:** hay que anular ese MIC/DTA y registrarlo de nuevo. Vea [Cómo anular o rectificar](manual:afip-argentina/anular-y-rectificar).
- **Ya existe un convoy registrado para este MIC/DTA:** el convoy ya está en AFIP. No lo registre de nuevo: rectifíquelo o consulte con ARCA.
