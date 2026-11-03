---
titulo: Cómo administrar los tipos de contenedor
tema: catalogos
orden: 10
roles: [super-admin]
palabras_clave: [contenedor, tipo de contenedor, ISO, 20DV, 40HC, catálogo, medidas, tara]
relacionados: [catalogos/tipos-de-embalaje, catalogos/tipos-de-carga]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Mantiene la lista de tipos de contenedor (por ejemplo, 20 pies estándar o 40 pies High Cube) que las empresas eligen al cargar contenedores. El código ISO de cada tipo es el que se informa a AFIP.

## Antes de empezar

- Ingrese con un usuario **super-admin**.
- Tenga a mano las medidas y pesos del contenedor: largo, ancho y alto externos e internos en milímetros, tara, peso bruto máximo, carga útil máxima y volumen interno.
- Tenga a mano el código ISO 6346 del contenedor (por ejemplo, 22G1 o 45G1).

## Pasos

1. En la barra superior, abra **Catálogos › Tipos de Contenedor**. En el celular, abra el menú y elija **Tipos de Contenedor**.
2. Para buscar un tipo, escriba en **Buscar por cualquier campo…** y haga clic en **Buscar**. Para volver a la lista completa, haga clic en **✕**.
3. Para crear un tipo, haga clic en **Nuevo**.
4. Complete los campos marcados con **\***: **Code**, **Name**, **Length Feet**, las medidas externas e internas en milímetros, los tres pesos y **Internal Volume M3**. El resto es opcional; la tabla de abajo explica cada campo.
5. En **Estado y Visualización**, marque **Active**. Si no lo marca, el tipo se guarda inactivo y no aparece al cargar contenedores.
6. Haga clic en **Guardar Tipo de Contenedor**.
7. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

Qué significa cada campo del formulario:

**Información Básica**

- **Code**: código corto y único del tipo, por ejemplo 20DV o 40HC. Se usa en las exportaciones de manifiestos.
- **Name** / **Short Name**: nombre completo y nombre corto que se ven en las listas.
- **Category**: familia del contenedor: Carga Seca, Refrigerado, Techo Abierto, Plataforma, Tanque, Granel o Especializado.
- **Iso Code**: código ISO 6346 de tamaño y tipo, por ejemplo 22G1. Es el código que se informa a AFIP y el que se imprime en el manifiesto y en el MIC/DTA.
- **Description**: texto libre.

**Dimensiones**

- **Length Feet**, **Width Feet**, **Height Feet**: largo, ancho y alto nominales en pies (por ejemplo, 20', 8', 8.5').
- **Length Mm**, **Width Mm**, **Height Mm**: medidas externas en milímetros.
- **Internal Length Mm**, **Internal Width Mm**, **Internal Height Mm**: medidas internas en milímetros.

**Pesos y Volúmenes**

- **Tare Weight Kg**: peso del contenedor vacío. Se copia al contenedor cuando se carga la mercadería.
- **Max Gross Weight Kg**: peso máximo del contenedor cargado. También se copia al contenedor.
- **Max Payload Kg**: carga máxima que admite.
- **Internal Volume M3**: volumen interno en metros cúbicos.

**Características Especiales**

- **Is Refrigerated**: marca los contenedores refrigerados.

**Configuración de Puertas**

- **Door Width Mm**, **Door Height Mm**: ancho y alto de la puerta en milímetros.

**Control de Temperatura**

- **Min Temperature Celsius**, **Max Temperature Celsius**: rango de temperatura, para contenedores refrigerados.

**Compatibilidad de Carga**

- **Suitable For Dangerous Goods**, **Suitable For Food**, **Suitable For Chemicals**: indican si sirve para mercadería peligrosa, alimentos o químicos.

**Datos Operacionales**

- **Stackable**, **Max Stack Height**: si se puede apilar y cuántos contenedores como máximo.
- **Typical Lifespan Years**, **Inspection Interval Months**: vida útil en años y cada cuántos meses se inspecciona.

**Datos Económicos**

- **Daily Rental Rate**: tarifa diaria de alquiler.

**Estado y Visualización**

- **Active**: solo los tipos activos aparecen al cargar contenedores.
- **Is Standard**, **Is Common**, **Is Specialized**: marcas para clasificar el tipo.
- **Display Order**: orden en las listas: un número menor aparece primero.
- **Color Code**: color con el que se muestra el tipo.

**Otros Campos**

- **Iso Size Type**, **Iso Group**: datos ISO complementarios. **Iso Size Type** es el código ISO de 4 caracteres y se usa si falta **Iso Code**.
- **Argentina Ws Code**, **Paraguay Ws Code**, **Customs Code**: códigos alternativos para aduana.
- El resto de **Otros Campos**: datos descriptivos opcionales; puede dejarlos vacíos.

## Cómo saber que salió bien

Al guardar o eliminar, vuelve a la lista con el mensaje **Tipo de contenedor creado correctamente.** o **Tipo de contenedor eliminado correctamente.** El tipo nuevo aparece primero en la lista, con **Sí** en la columna **Active**.

## Si algo falla

- **Este campo es obligatorio.** debajo de un campo: complete ese campo antes de guardar.
- **Debe ser un número válido.**: en ese campo escriba solo números, con punto decimal.
- **No puede superar … caracteres.**: acorte el texto.
- Si el tipo nuevo no aparece al cargar contenedores, revise que tenga **Sí** en la columna **Active**.
- Si al eliminar aparece una pantalla de error, el tipo está en uso por algún contenedor y no se puede borrar.
