---
titulo: Cómo administrar los tipos de contenedor
tema: catalogos
orden: 10
roles: [super-admin]
palabras_clave: [contenedor, tipo de contenedor, ISO, 20DV, 40HC, catálogo, medidas, tara]
relacionados: [catalogos/tipos-de-embalaje, catalogos/tipos-de-carga]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
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
4. Complete los campos marcados con **\***: **Código**, **Nombre**, **Largo (pies)**, las medidas externas e internas en milímetros, los tres pesos y **Volumen interno (m³)**. El resto es opcional; la lista de abajo explica cada campo.
5. En **Estado y Visualización**, marque **Activo**. Si no lo marca, el tipo se guarda inactivo y no aparece al cargar contenedores.
6. Haga clic en **Guardar Tipo de Contenedor**.
7. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

Qué significa cada campo del formulario:

**Información Básica**

- **Código**: código corto y único del tipo, por ejemplo 20DV o 40HC. Se usa en las exportaciones de manifiestos.
- **Nombre** / **Nombre corto**: nombre completo y nombre corto que se ven en las listas.
- **Categoría**: familia del contenedor: Carga Seca, Refrigerado, Techo Abierto, Plataforma, Tanque, Granel o Especializado.
- **Código ISO**: código ISO 6346 de tamaño y tipo, por ejemplo 22G1. Es el código que se informa a AFIP y el que se imprime en el manifiesto y en el MIC/DTA.
- **Descripción**: texto libre.

**Dimensiones**

- **Largo (pies)**, **Ancho (pies)**, **Alto (pies)**: largo, ancho y alto nominales en pies (por ejemplo, 20', 8', 8.5').
- **Largo (mm)**, **Ancho (mm)**, **Alto (mm)**: medidas externas en milímetros.
- **Largo interno (mm)**, **Ancho interno (mm)**, **Alto interno (mm)**: medidas internas en milímetros.

**Pesos y Volúmenes**

- **Tara (kg)**: peso del contenedor vacío. Se copia al contenedor cuando se carga la mercadería.
- **Peso bruto máximo (kg)**: peso máximo del contenedor cargado. También se copia al contenedor.
- **Carga útil máxima (kg)**: carga máxima que admite.
- **Volumen interno (m³)**: volumen interno en metros cúbicos.

**Características Especiales**

- **Refrigerado**: marca los contenedores refrigerados.

**Configuración de Puertas**

- **Ancho de puerta (mm)**, **Alto de puerta (mm)**: ancho y alto de la puerta en milímetros.

**Control de Temperatura**

- **Temperatura mínima (°C)**, **Temperatura máxima (°C)**: rango de temperatura, para contenedores refrigerados.

**Compatibilidad de Carga**

- **Apto para mercancías peligrosas**, **Apto para alimentos**, **Apto para químicos**: indican si sirve para mercadería peligrosa, alimentos o químicos.

**Datos Operacionales**

- **Apilable**, **Altura máxima de apilamiento**: si se puede apilar y cuántos contenedores como máximo.
- **Vida útil típica (años)**, **Intervalo de inspección (meses)**: vida útil en años y cada cuántos meses se inspecciona.

**Datos Económicos**

- **Tarifa diaria de alquiler**: tarifa diaria de alquiler.

**Estado y Visualización**

- **Activo**: solo los tipos activos aparecen al cargar contenedores.
- **Estándar**, **Común**, **Especializado**: marcas para clasificar el tipo.
- **Orden de visualización**: orden en las listas: un número menor aparece primero.
- **Color**: color con el que se muestra el tipo.

**Otros Campos**

- **Código ISO tamaño/tipo**, **Grupo ISO**: datos ISO complementarios. **Código ISO tamaño/tipo** es el código ISO de 4 caracteres y se usa si falta **Código ISO**.
- **Código webservice Argentina**, **Código webservice Paraguay**, **Código aduanero**: códigos alternativos para aduana.
- El resto de **Otros Campos**: datos descriptivos opcionales; puede dejarlos vacíos.

## Cómo saber que salió bien

Al guardar o eliminar, vuelve a la lista con el mensaje **Tipo de contenedor creado correctamente.** o **Tipo de contenedor eliminado correctamente.** El tipo nuevo aparece primero en la lista, con **Sí** en la columna **Activo**.

## Si algo falla

- **El campo … es obligatorio.** debajo de un campo: complete ese campo antes de guardar.
- **El campo … debe ser un número.**: en ese campo escriba solo números, con punto decimal.
- **El campo … no puede tener más de … caracteres.**: acorte el texto.
- Si el tipo nuevo no aparece al cargar contenedores, revise que tenga **Sí** en la columna **Activo**.
- Si al eliminar aparece una pantalla de error, el tipo está en uso por algún contenedor y no se puede borrar.
