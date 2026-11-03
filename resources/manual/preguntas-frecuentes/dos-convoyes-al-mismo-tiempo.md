---
titulo: ¿Se mezclan los datos si dos usuarios arman dos convoyes al mismo tiempo?
tema: preguntas-frecuentes
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [convoy, dos usuarios, simultáneo, mezclar, MIC/DTA, número de viaje]
relacionados:
  - viajes-y-cargas/armar-un-convoy
  - afip-argentina/registrar-micdta-convoy
estado: borrador
verificado:
  version: 9187972
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Responde qué pasa cuando dos usuarios de la misma empresa preparan y registran convoyes distintos a la vez. **No se mezclan:** cada convoy es un viaje distinto, y todo lo que se envía a AFIP (títulos, MIC/DTA de cada embarcación y registro del convoy) se arma solo con las cargas y los conocimientos de ese viaje.

## Antes de empezar

- Cada convoy tiene que ser un viaje propio: vea [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).
- Un mismo número de viaje no puede repetirse para la misma embarcación dentro de la empresa, así que dos convoyes con el mismo remolcador llevan números distintos.

## Pasos

1. Cada usuario arma su convoy en su propio viaje, con su remolcador, sus barcazas y sus conocimientos.
2. Cada uno abre su viaje en **Crear Viaje Completo › Enviar a AFIP/DNA** y registra el MIC/DTA desde la pantalla de ese viaje: vea [Cómo registrar el MIC/DTA de un convoy](manual:afip-argentina/registrar-micdta-convoy).
3. Antes de confirmar cada envío, compruebe en el encabezado de la pantalla que el número de viaje es el suyo.
4. No registre el mismo viaje desde dos pantallas o dos usuarios al mismo tiempo: espere el resultado de un envío antes de iniciar el siguiente sobre ese viaje.

## Cómo saber que salió bien

En **Últimas 10 transacciones MIC/DTA** de cada viaje figuran solo los envíos de ese viaje, y AFIP devuelve un número de viaje distinto para cada convoy.

## Si algo falla

- **Aparece un envío que no reconoce en el historial de su viaje:** compruebe que nadie más esté trabajando sobre ese mismo viaje y consulte antes de repetir el envío.
- **"El viaje … ya existe para esta embarcación en su empresa":** ese número ya se usó con ese remolcador. Use otro número de viaje.
