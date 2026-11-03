---
titulo: Cómo crear y editar un país
tema: catalogos
orden: 30
roles: [super-admin]
palabras_clave: [país, países, código ISO, Alpha2, código numérico, código AFIP, MANE, catálogo]
relacionados:
  - catalogos/gestionar-puertos
  - catalogos/codigos-de-puertos-aduanas-y-lugares
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Mantiene el catálogo de países que usan puertos, clientes, embarcaciones y viajes. Además de los códigos internacionales, guarda el código de país propio de AFIP, que se informa en el archivo MANE y en los envíos a AFIP, por ejemplo los desconsolidados.

## Antes de empezar

- Tenga el código ISO de 3 letras, el de 2 letras y, si lo tiene, el código numérico ISO del país.
- Tenga el código de país de AFIP (3 caracteres) si el país interviene en envíos a AFIP.

## Pasos

1. Abra **Catálogos › Países**. En **Búsqueda inteligente**, compruebe que el país no exista: puede buscar por nombre o por código ISO, de 2 letras o numérico.
2. Haga clic en **Nuevo País**.
3. En **Códigos Internacionales**, complete **Código ISO (3)** (3 letras, por ejemplo ARG) y **Código Alpha2** (2 letras, por ejemplo AR). Si lo tiene, complete **Código Numérico** (por ejemplo 032).
4. En **Información Básica**, complete **Nombre** y **Orden de Visualización** (por defecto, 999). **Nombre Oficial** y **Nacionalidad** son opcionales.
5. En **Configuración Aduanera**, complete **Código AFIP de país (MANE)** con el código de 3 caracteres que asigna AFIP al país. Los demás datos de esta sección (**Código Aduanero**, **Código SENASA**, **Formato Documento**) son opcionales.
6. En **Configuración Regional** puede indicar **Código Moneda**, **Zona Horaria** e **Idioma Principal**.
7. En **Configuración Operacional**, **Permite Importación**, **Permite Exportación** y **Permite Tránsito** vienen marcados; marque **Requiere Visa** si corresponde. En **Estado**, deje marcado **Activo**. Marque **País Principal (AR/PY)** solo para Argentina y Paraguay.
8. Haga clic en **Crear País**.
9. Para corregir un país, búsquelo en el listado, haga clic en **Editar**, cambie los datos y haga clic en **Actualizar País**.

## Cómo saber que salió bien

La aplicación vuelve al listado de **Países**. Busque el país con **Búsqueda inteligente**: debe aparecer con su **Nombre**, **Nombre oficial**, **ISO** y **Numérico**. Desde ese momento se puede elegir al cargar puertos y clientes.

## Si algo falla

- **"Errores encontrados"** al crear, o **"Hay errores en el formulario"** al editar: complete los datos marcados. Si el error está en el código ISO o en el de 2 letras, el país ya está cargado: búsquelo y edítelo en lugar de crear otro.
- **"El puerto seleccionado no tiene un código de país AFIP válido configurado":** este aviso aparece al cargar un desconsolidado. Complete **Código AFIP de país (MANE)** en el país de ese puerto.
- **"No se puede eliminar: el país está referenciado por otros registros":** el país está en uso. Si dejó de usarse, desmarque **Activo** en lugar de eliminarlo.
- Para eliminar, **Eliminar** pide confirmación en **Confirmar eliminación** y la acción no se puede deshacer. No elimine países cargados: desmarque **Activo**.
