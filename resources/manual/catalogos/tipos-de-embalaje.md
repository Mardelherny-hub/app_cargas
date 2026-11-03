---
titulo: Cómo administrar los tipos de embalaje
tema: catalogos
orden: 30
roles: [super-admin]
palabras_clave: [embalaje, packaging, empaquetamiento, bulto, código de embalaje, codTipEmbalaje, AFIP, catálogo]
relacionados: [catalogos/tipos-de-carga, catalogos/tipos-de-contenedor]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Mantiene la lista de tipos de embalaje (pallet, caja, bolsa, tambor, etc.) que se asignan a los conocimientos y a sus líneas de carga. El código AFIP de embalaje de cada tipo se informa en el MIC/DTA y en los desconsolidados cuando la línea de carga no trae su propio código.

## Antes de empezar

- Ingrese con un usuario **super-admin**.
- Tenga a mano el código de embalaje de AFIP del tipo, de 2 caracteres.
- Si el tipo se va a usar con DNA Paraguay, tenga a mano su código numérico.

## Pasos

1. En la barra superior, abra **Catálogos › Tipos de Packaging**. En el celular, abra el menú y elija **Tipos de Empaquetamiento**.
2. Para buscar un tipo, escriba en **Buscar por cualquier campo…** y haga clic en **Buscar**. Para volver a la lista completa, haga clic en **✕**.
3. Para crear un tipo, haga clic en **Nuevo**. Para ver solo los campos obligatorios, marque **Mostrar solo obligatorios**; para encontrar un campo, escriba su nombre en **Buscar campo por nombre…**.
4. Complete **Code**, **Name** y **Category**, que son obligatorios.
5. En **Argentina Ws Code**, escriba el código de embalaje de AFIP, de 2 caracteres. La sección de abajo explica cómo se usa.
6. Marque **Active**. Una casilla sin marcar se guarda como **No**, y un tipo inactivo no se puede elegir.
7. Haga clic en **Guardar**.
8. Para modificar un tipo, haga clic en **Editar** en su fila, cambie los datos y haga clic en **Actualizar Tipo de Packaging**. En esta pantalla, **Category** y **Material Type** se escriben: use el valor en inglés y en minúsculas, por ejemplo `pallet`, `box` o `wood`.
9. Para desactivar un tipo, edítelo, desmarque **Activo** y guarde. Para activarlo de nuevo, márquelo.
10. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

### El código AFIP de embalaje

AFIP identifica el embalaje de cada línea de carga con un código de 2 caracteres. El sistema lo arma así:

- **MIC/DTA:** si la línea de carga trae su propio código de embalaje de 2 caracteres (por ejemplo, porque vino en el manifiesto importado), se informa ese. Si no, se usa el **Argentina Ws Code** del tipo de embalaje y, si tampoco tiene 2 caracteres, el **Unece Code**. Para las líneas en contenedor cuyo tipo de embalaje es **CONTENEDOR**, se informa **CN**.
- **Información anticipada:** se informa el código de la línea de carga, o **05** si la línea va en contenedor. El catálogo no interviene.
- **Desconsolidados:** se informa el código de la línea de carga; si no tiene, **05** cuando va en contenedor; si no, el **Argentina Ws Code** del tipo, de hasta 2 caracteres.
- **DNA Paraguay:** se informa el **Code** del tipo si es numérico. Si no es numérico, se informa **99**.

Un **Argentina Ws Code** de una sola letra no sirve para el MIC/DTA: complete siempre los 2 caracteres.

Qué significa cada campo del formulario:

**General / Otros**

- **Code**: código único del tipo. Es el que se informa a DNA Paraguay cuando es numérico y el que figura en el archivo MANE.
- **Name** / **Short Name**: nombre completo y nombre corto que se ven en las listas.
- **Category**: familia del embalaje: Pallet, Box, Bag, Drum, Container, Bundle, Roll, Bale, Crate, Barrel, Tank, Tray, Bulk o Specialized.
- **Description**: texto libre.
- **Active**: solo los tipos activos se pueden elegir.

**Códigos / WS**

- **Argentina Ws Code**: código de embalaje de AFIP, de 2 caracteres.
- **Unece Code**: código UN/ECE del embalaje. Se usa en el MIC/DTA si **Argentina Ws Code** no tiene 2 caracteres. No puede repetirse entre tipos.
- **Iso Code**, **Imdg Code**, **Senasa Code**, **Paraguay Ws Code**: códigos de referencia.
- **Color Code**: color con que se muestra el tipo.

**Dimensiones / Capacidad**

- **Length Mm**, **Width Mm**, **Height Mm**, **Diameter Mm**: medidas en milímetros.
- **Volume Liters**, **Volume M3**: capacidad en litros o metros cúbicos.
- **Max Stack Height**: cuántos bultos se pueden apilar.

**Materiales**

- **Material Type**: material: Wood, Cardboard, Plastic, Metal, Fabric, Paper, Composite, Glass, Rubber u Other.
- **Is Weatherproof**: si resiste la intemperie.

**Operación & Manejo**

- **Is Stackable**, **Stacking Weight Limit Kg**: si se puede apilar y el peso máximo encima.
- **Requires Palletizing**: si necesita ir sobre pallet.
- **Handling Equipment**: equipo para moverlo: Manual, Forklift, Crane, Conveyor o Specialized.
- **Suitable For Liquids**, **Suitable For Powders**, **Suitable For Fragile Items**: para qué mercadería sirve.
- **Suitable For Bulk Cargo**: indica carga suelta. Se informa a DNA Paraguay.

**Regulación & Aduana**

- **Suitable For Dangerous Goods**: si sirve para mercadería peligrosa.
- **Customs Code**: código de referencia aduanera.

**Visual & Orden**

- **Display Order**: orden en las listas; un número menor aparece primero.
- **Icon**: ícono con que se muestra el tipo.

El resto de los campos son datos descriptivos opcionales; puede dejarlos vacíos.

## Cómo saber que salió bien

Al guardar, vuelve a la lista con el mensaje **Tipo de packaging creado correctamente.** o **Tipo de packaging actualizado correctamente.** Al eliminar, aparece **Tipo de packaging eliminado correctamente.** En la columna **Active**, un tipo habilitado muestra **Sí**.

## Si algo falla

- **Este campo es obligatorio.**: complete **Code**, **Name** y **Category**.
- **Este valor ya está en uso.**: ya existe otro tipo con ese **Code** o **Unece Code**; use uno distinto.
- **Error al actualizar el tipo de packaging:** seguido de un texto técnico: revise que **Code** tenga hasta 20 caracteres, **Name** hasta 100, **Unece Code** hasta 10, y que **Category** y **Material Type** sean valores de la lista, en inglés y en minúsculas.
- Si vacía un campo y guarda los cambios, el campo conserva su valor anterior.
- **RegistrarTitEnvios: BL …, línea …: falta codTipEmbalaje EDIFACT de 2 caracteres.** al enviar el MIC/DTA: esa línea de carga no tiene un código de embalaje de 2 caracteres. Complete el **Argentina Ws Code** del tipo de embalaje de la línea y vuelva a enviar.
- Si al eliminar aparece una pantalla de error, el tipo está en uso en algún conocimiento o línea de carga y no se puede borrar.
