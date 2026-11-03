---
titulo: Cómo cargar aduanas y lugares operativos de AFIP
tema: catalogos
orden: 20
roles: [super-admin]
palabras_clave: [aduana, aduanas AFIP, lugar operativo, codAdu, codLugOper, terminal, vincular puerto, Config. AFIP]
relacionados:
  - catalogos/codigos-de-puertos-aduanas-y-lugares
  - catalogos/gestionar-puertos
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Mantiene los códigos propios de AFIP: las aduanas (3 dígitos) y los lugares operativos de cada aduana (5 caracteres), y vincula cada lugar operativo con el puerto donde está. Los envíos a AFIP toman de acá el lugar operativo de cada puerto.

## Antes de empezar

- Tenga los códigos oficiales de AFIP de la aduana y del lugar operativo.
- El puerto que va a vincular tiene que existir en [el catálogo de puertos](manual:catalogos/gestionar-puertos).

## Pasos

1. Abra **Catálogos › 🏛️ Config. AFIP**.
2. **Aduana:** en la pestaña **🏛️ Aduanas AFIP**, en **Agregar Nueva Aduana**, complete **Código** y **Nombre** y haga clic en **Agregar**.
3. **Lugar operativo:** en la pestaña **📍 Lugares Operativos**, en **Agregar Nuevo Lugar Operativo**, complete **País**, **Cód. Aduana**, **Cód. Lugar** y **Descripción**. En **Puerto**, elija el puerto donde está ese lugar. Haga clic en **Agregar**.
4. **Vincular un lugar existente a su puerto:** en el listado de lugares operativos, haga clic en **Vincular** (o en el lápiz, si ya tiene puerto). Elija el puerto y haga clic en **Vincular**.
5. Use **Filtrar por Aduana** y **Filtrar por País** para encontrar un lugar.
6. Mantenga activo un solo lugar operativo por puerto, salvo que los conocimientos de ese puerto indiquen siempre su lugar operativo. Desactive con el botón de pausa los que no se usan.

## Cómo saber que salió bien

- Aparece **Aduana creada correctamente.** o **Lugar operativo creado correctamente.**
- Al vincular, aparece **Lugar … vinculado a puerto …** y el código del puerto se ve en la columna **Puerto**.

## Si algo falla

- **"No se puede eliminar: tiene … lugares operativos asociados.":** la aduana tiene lugares cargados. Elimínelos o desactívelos primero.
- **Un envío a AFIP dice que falta el lugar operativo:** el puerto del viaje no tiene un lugar operativo activo vinculado. Vincúlelo en el paso 4.
- **Un envío dice que el puerto tiene más de un lugar operativo activo:** desactive los que no se usan o indique el lugar operativo en cada conocimiento.
- **La pestaña 🔗 Puertos ↔ Aduanas:** registra con qué aduanas trabaja cada puerto. El código de aduana que viaja en los envíos es el del campo **Código AFIP (Argentina)** del puerto: manténgalo cargado y coherente con este vínculo.
