---
titulo: Cómo administrar los tipos de embalaje
tema: catalogos
orden: 30
roles: [super-admin]
palabras_clave: [embalaje, packaging, empaquetamiento, bulto, código de embalaje, codTipEmbalaje, AFIP, catálogo]
relacionados: [catalogos/tipos-de-carga, catalogos/tipos-de-contenedor]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
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
4. Complete **Código**, **Nombre** y **Categoría**, que son obligatorios.
5. En **Código webservice Argentina**, escriba el código de embalaje de AFIP, de 2 caracteres. La sección de abajo explica cómo se usa.
6. Marque **Activo**. Una casilla sin marcar se guarda como **No**, y un tipo inactivo no se puede elegir.
7. Haga clic en **Guardar**.
8. Para modificar un tipo, haga clic en **Editar** en su fila, cambie los datos y haga clic en **Actualizar Tipo de Packaging**. En esta pantalla, **Categoría** y **Material** se escriben a mano: use el nombre en inglés y en minúsculas, por ejemplo `pallet` (Pallet), `box` (Caja) o `wood` (Madera).
9. Para desactivar un tipo, edítelo, desmarque **Activo** y guarde. Para activarlo de nuevo, márquelo.
10. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

### El código AFIP de embalaje

AFIP identifica el embalaje de cada línea de carga con un código de 2 caracteres. El sistema lo arma así:

- **MIC/DTA:** si la línea de carga trae su propio código de embalaje de 2 caracteres (por ejemplo, porque vino en el manifiesto importado), se informa ese. Si no, se usa el **Código webservice Argentina** del tipo de embalaje y, si tampoco tiene 2 caracteres, el **Código UN/ECE**. Para las líneas en contenedor cuyo tipo de embalaje es **CONTENEDOR**, se informa **CN**.
- **Información anticipada:** se informa el código de la línea de carga, o **05** si la línea va en contenedor. El catálogo no interviene.
- **Desconsolidados:** se informa el código de la línea de carga; si no tiene, **05** cuando va en contenedor; si no, el **Código webservice Argentina** del tipo, de hasta 2 caracteres.
- **DNA Paraguay:** se informa el **Código** del tipo si es numérico. Si no es numérico, se informa **99**.

Un **Código webservice Argentina** de una sola letra no sirve para el MIC/DTA: complete siempre los 2 caracteres.

Qué significa cada campo del formulario:

**General / Otros**

- **Código**: código único del tipo. Es el que se informa a DNA Paraguay cuando es numérico y el que figura en el archivo MANE.
- **Nombre** / **Nombre corto**: nombre completo y nombre corto que se ven en las listas.
- **Categoría**: familia del embalaje: Pallet, Caja, Bolsa, Tambor, Contenedor, Atado, Rollo, Fardo, Jaula, Barril, Tanque, Bandeja, Granel o Especializado.
- **Descripción**: texto libre.
- **Activo**: solo los tipos activos se pueden elegir.

**Códigos / WS**

- **Código webservice Argentina**: código de embalaje de AFIP, de 2 caracteres.
- **Código UN/ECE**: código UN/ECE del embalaje. Se usa en el MIC/DTA si **Código webservice Argentina** no tiene 2 caracteres. No puede repetirse entre tipos.
- **Código ISO**, **Código IMDG**, **Código SENASA**, **Código webservice Paraguay**: códigos de referencia.
- **Color**: color con que se muestra el tipo.

**Dimensiones / Capacidad**

- **Largo (mm)**, **Ancho (mm)**, **Alto (mm)**, **Diámetro (mm)**: medidas en milímetros.
- **Volumen (litros)**, **Volumen (m³)**: capacidad en litros o metros cúbicos.
- **Altura máxima de apilamiento**: cuántos bultos se pueden apilar.

**Materiales**

- **Material**: Madera, Cartón, Plástico, Metal, Tela, Papel, Compuesto, Vidrio, Caucho u Otro.
- **Resistente a la intemperie**: si resiste la intemperie.

**Operación & Manejo**

- **Apilable**, **Límite de peso para apilar (kg)**: si se puede apilar y el peso máximo encima.
- **Requiere paletizado**: si necesita ir sobre pallet.
- **Equipo de manejo**: equipo para moverlo: Manual, Autoelevador, Grúa, Cinta transportadora o Especializado.
- **Apto para líquidos**, **Apto para polvos**, **Apto para artículos frágiles**: para qué mercadería sirve.
- **Apto para carga a granel**: indica carga suelta. Se informa a DNA Paraguay.

**Regulación & Aduana**

- **Apto para mercancías peligrosas**: si sirve para mercadería peligrosa.
- **Código aduanero**: código de referencia aduanera.

**Visual & Orden**

- **Orden de visualización**: orden en las listas; un número menor aparece primero.
- **Ícono**: ícono con que se muestra el tipo.

El resto de los campos son datos descriptivos opcionales; puede dejarlos vacíos.

## Cómo saber que salió bien

Al guardar, vuelve a la lista con el mensaje **Tipo de packaging creado correctamente.** o **Tipo de packaging actualizado correctamente.** Al eliminar, aparece **Tipo de packaging eliminado correctamente.** En la columna **Activo**, un tipo habilitado muestra **Sí**.

## Si algo falla

- **El campo Código es obligatorio.**, **El campo Nombre es obligatorio.** o **El campo Categoría es obligatorio.**: complete ese campo.
- **El valor del campo Código ya está en uso.** o **El valor del campo Código UN/ECE ya está en uso.**: ya existe otro tipo con ese código; use uno distinto.
- **El campo … no puede tener más de … caracteres.**: acorte el texto.
- **Error al actualizar el tipo de packaging:** seguido de un texto técnico: revise que **Código** tenga hasta 20 caracteres, **Nombre** hasta 100, **Código UN/ECE** hasta 10, y que **Categoría** y **Material** sean valores de la lista, en inglés y en minúsculas.
- Si vacía un campo y guarda los cambios, el campo conserva su valor anterior.
- **RegistrarTitEnvios: BL …, línea …: falta codTipEmbalaje EDIFACT de 2 caracteres.** al enviar el MIC/DTA: esa línea de carga no tiene un código de embalaje de 2 caracteres. Complete el **Código webservice Argentina** del tipo de embalaje de la línea y vuelva a enviar.
- Si al eliminar aparece una pantalla de error, el tipo está en uso en algún conocimiento o línea de carga y no se puede borrar.
