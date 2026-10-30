---
titulo: Cómo registrar el arribo a zona primaria
tema: afip-argentina
orden: 40
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [arribo, llegada, zona primaria, aduana, lugar operativo, amarre, AFIP]
relacionados:
  - afip-argentina/registrar-salida-zona-primaria
  - afip-argentina/anular-y-rectificar
estado: borrador
verificado:
  version: c62042a
  fecha: 2026-10-30
  entorno: codigo
---

## Para qué sirve

Informa a AFIP que el viaje llegó a una zona primaria aduanera, indicando la aduana y el lugar operativo de arribo.

## Antes de empezar

- El viaje tiene número de viaje de AFIP (MIC/DTA registrado y, en un convoy, convoy registrado).
- Tiene a mano los códigos AFIP de la aduana (3 dígitos) y del lugar operativo (hasta 5 dígitos) del arribo. Son códigos propios de AFIP, distintos del código internacional del puerto.

## Pasos

1. En el menú lateral, abra **Crear Viaje Completo › Enviar a AFIP/DNA** y entre al viaje desde el recuadro **MIC/DTA** con **Ver Detalles**.
2. En **Zona Primaria**, haga clic en **11. RegistrarArribo**.
3. En **Registrar Arribo Zona Primaria**, compruebe **Nro. Viaje**.
4. Complete **Código Aduana** y **Código Lugar Operativo**.
5. Si corresponde, complete **Descripción Amarre** (opcional, hasta 50 caracteres).
6. Haga clic en **Registrar Arribo** y confirme con **Aceptar**.

## Cómo saber que salió bien

- Aparece **RegistrarArriboZonaPrimaria - Exitoso** y la página se actualiza sola.
- En el recuadro **11. RegistrarArribo** aparecen **Nro. Viaje** y **Estado**.

## Si algo falla

- **Lugar operativo incorrecto:** el lugar operativo no corresponde a la aduana informada. Revise ambos códigos.
- **El mensaje de resultado dice "Error" y lista "Errores encontrados:":** es la respuesta de AFIP. Corrija lo que indica y vuelva a intentar.
- **El arribo quedó registrado con un dato mal:** anúlelo y regístrelo de nuevo. Vea [Cómo anular o rectificar](manual:afip-argentina/anular-y-rectificar).
