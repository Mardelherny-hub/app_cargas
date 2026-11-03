---
titulo: Cómo crear y editar un puerto
tema: catalogos
orden: 10
roles: [super-admin]
palabras_clave: [puerto, puertos, UN/LOCODE, código de puerto, crear puerto, editar puerto, aduana AFIP, aduana DNA, catálogo]
relacionados:
  - catalogos/codigos-de-puertos-aduanas-y-lugares
  - catalogos/aduanas-y-lugares-operativos
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Mantiene el catálogo de puertos que usan todas las empresas en viajes, conocimientos e importaciones. Las importaciones nunca crean puertos: si un archivo trae un puerto que no está en el catálogo, la importación se rechaza hasta que el puerto se cargue acá.

## Antes de empezar

- Tenga el código UN/LOCODE oficial del puerto (5 caracteres) y su país.
- Si el puerto es argentino, tenga su código de aduana AFIP; si es paraguayo, su código de aduana DNA. Vea [Qué es cada código](manual:catalogos/codigos-de-puertos-aduanas-y-lugares).

## Pasos

1. Abra **Catálogos › Puertos**. Use el buscador para comprobar que el puerto no exista: puede buscar por nombre, código, ciudad o país.
2. Haga clic en **Nuevo Puerto**.
3. En la pestaña **Información Básica**, complete **Código del Puerto** (el UN/LOCODE), **País**, **Nombre del Puerto**, **Tipo de Puerto** y **Categoría**. Los campos con asterisco son obligatorios.
4. En **Códigos de Aduana (Webservices)**, complete **Código AFIP (Argentina)** o **Código DNA (Paraguay)** según el país del puerto.
5. En la pestaña **Ubicación**, complete **Ciudad** (obligatoria) y, si los tiene, la provincia, la dirección y las coordenadas.
6. Las pestañas **Capacidades** y **Contacto** son opcionales. En **Capacidades** está **Puerto Activo**, marcado por defecto.
7. Haga clic en **Guardar Puerto**.
8. Para corregir un puerto existente, búsquelo en el listado y use el ícono **Editar puerto**.

## Cómo saber que salió bien

Aparece **Puerto creado exitosamente.** y el puerto figura en el listado de **Catálogos › Puertos**. Desde ese momento se puede elegir en viajes y conocimientos, y las importaciones que lo traen lo reconocen.

## Si algo falla

- **Al guardar queda marcado Código del Puerto por estar repetido:** el puerto ya está cargado. Búsquelo en el listado y edítelo en lugar de crear otro.
- **Un campo con asterisco queda marcado al guardar:** complete el dato indicado. **Ciudad** está en la pestaña **Ubicación**.
- **Eliminar un puerto:** el ícono **Eliminar puerto** pide confirmación y la acción no se puede deshacer. Antes de eliminar, compruebe que ningún viaje ni conocimiento lo use; si dejó de operar, es preferible desmarcar **Puerto Activo** en la pestaña **Capacidades**.
