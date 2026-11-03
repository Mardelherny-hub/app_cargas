---
titulo: "Qué es cada código: UN/LOCODE, aduana AFIP y lugar operativo"
tema: catalogos
orden: 5
roles: [super-admin]
palabras_clave: [UN/LOCODE, código de puerto, aduana, código de aduana, codAdu, lugar operativo, codLugOper, AFIP, DNA, catálogo]
relacionados:
  - catalogos/gestionar-puertos
  - catalogos/aduanas-y-lugares-operativos
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Explica los tres códigos que identifican dónde opera un viaje y por qué no son intercambiables. Los envíos a AFIP necesitan los tres, y cada uno se carga en un catálogo distinto.

## Antes de empezar

- Estos catálogos los mantiene solo el super-admin. Un cambio afecta a todas las empresas.
- Tenga a mano los códigos oficiales: la lista UN/LOCODE para puertos y los códigos de AFIP para aduanas y lugares operativos.

## Pasos

1. **Código del puerto (UN/LOCODE).** Es el código internacional del puerto: dos letras del país y tres del lugar (por ejemplo, un puerto argentino empieza con `AR` y uno paraguayo con `PY`). Se carga en **Catálogos › Puertos**, en **Código del Puerto**. Los envíos a AFIP lo piden de 5 caracteres exactos.
2. **Código de aduana AFIP.** Identifica la aduana argentina que controla la operación: 3 dígitos. Las aduanas se cargan en **Catálogos › 🏛️ Config. AFIP › 🏛️ Aduanas AFIP**. Cada puerto argentino guarda además su aduana en **Código AFIP (Argentina)**.
3. **Lugar operativo AFIP.** Es un punto concreto dentro de una aduana, como una terminal: 5 caracteres. Se carga en **Catálogos › 🏛️ Config. AFIP › 📍 Lugares Operativos**, siempre dentro de una aduana, y se vincula al puerto donde está.
4. **Para Paraguay**, el puerto guarda su código de aduana DNA en **Código DNA (Paraguay)**.
5. Un puerto puede tener varios lugares operativos. Si tiene más de uno activo, los conocimientos que lo usan tienen que indicar su lugar operativo: el sistema no elige uno por su cuenta en la información anticipada.

## Cómo saber que salió bien

Para cada puerto argentino con el que se opera:
- el puerto tiene **Código del Puerto** de 5 caracteres y **Código AFIP (Argentina)** cargado;
- en **📍 Lugares Operativos** hay al menos un lugar activo vinculado a ese puerto, en la columna **Puerto**.

## Si algo falla

- **"RegistrarTitEnvios requiere códigos UN/LOCODE de 5 caracteres.":** el puerto de origen o de destino del viaje tiene un **Código del Puerto** con otra longitud. Corríjalo en el catálogo.
- **"RegistrarMicDta: no se pudo determinar codAdu para el puerto …":** al puerto le falta **Código AFIP (Argentina)**.
- **"… no tiene codLugOper de origen válido.":** el puerto de origen no tiene un lugar operativo activo vinculado y el conocimiento tampoco lo indica.
- **"… tiene más de un lugar operativo activo; debe informarse el lugar operativo del viaje.":** indique el lugar operativo en los conocimientos, o desactive los lugares que no se usan.
