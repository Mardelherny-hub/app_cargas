---
titulo: Cómo registrar la salida de zona primaria
tema: afip-argentina
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [salida, zona primaria, SalidaZP, número de viaje, número de salida, AFIP]
relacionados:
  - afip-argentina/registrar-micdta-una-embarcacion
  - afip-argentina/registrar-micdta-convoy
  - afip-argentina/registrar-arribo-zona-primaria
estado: borrador
verificado:
  version: c62042a
  fecha: 2026-10-30
  entorno: codigo
---

## Para qué sirve

Informa a AFIP que el viaje sale de la zona primaria aduanera. AFIP devuelve el número de salida.

## Antes de empezar

- El MIC/DTA del viaje está registrado: vea [Cómo registrar el MIC/DTA de una embarcación](manual:afip-argentina/registrar-micdta-una-embarcacion).
- En un convoy, además, el convoy está registrado ([Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy)) y la Aduana ya lo presentó.
- Tiene a mano el número de viaje que devolvió AFIP.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA** y entre al viaje desde el recuadro **MIC/DTA** con **Ver Detalles**.
2. En **Zona Primaria**, revise el recuadro **4. RegistrarSalidaZP**: no tiene que aparecer **Requisitos faltantes**, y en **Datos para envío** tiene que figurar el **Nro. Viaje**.
3. Haga clic en **4. RegistrarSalidaZP**.
4. En **Registrar Salida Zona Primaria**, compruebe que **Nro. Viaje** sea el que devolvió AFIP para este viaje. El campo viene completo, pero se puede editar: un número equivocado declara la salida de otro viaje.
5. Haga clic en **Registrar Salida** y confirme con **Aceptar**.

## Cómo saber que salió bien

- Aparece **RegistrarSalidaZonaPrimaria - Exitoso** y la página se actualiza sola.
- En el recuadro **4. RegistrarSalidaZP** aparecen **Nro. Salida** y **Estado**.

## Si algo falla

- **Requisitos faltantes: "Ejecutar RegistrarMicDta primero":** el MIC/DTA todavía no está registrado.
- **Requisitos faltantes: "No se obtuvo Nro. Viaje del MIC/DTA":** AFIP no devolvió número de viaje. En un convoy, el número llega al registrar el convoy: complete ese paso primero.
- **AFIP rechaza la salida porque el convoy no está presentado:** la Aduana tiene que presentar el convoy antes de la salida. Consulte con la Aduana.
- **El mensaje de resultado dice "Error" y lista "Errores encontrados:":** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar. Para ver el detalle, abra **📥 Response** en **Últimas 10 transacciones MIC/DTA**.
