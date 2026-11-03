---
titulo: Cómo administrar los tipos de carga
tema: catalogos
orden: 20
roles: [super-admin]
palabras_clave: [tipo de carga, carga, catálogo, TipoCarga, desconsolidado, granel, contenedorizada]
relacionados: [catalogos/tipos-de-contenedor, catalogos/tipos-de-embalaje]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Mantiene la lista de tipos de carga que las empresas eligen en los conocimientos, en sus líneas y en los desconsolidados. El código de webservice de cada tipo es el que se informa a AFIP como tipo de carga.

## Antes de empezar

- Ingrese con un usuario **super-admin**.
- Defina un código corto y único para el tipo nuevo.
- Si el tipo se va a informar a AFIP, tenga a mano su código de tipo de carga.

## Pasos

1. En la barra superior, abra **Catálogos › Tipos de Carga**. En el celular, abra el menú y elija **Tipos de Carga**.
2. Para buscar un tipo, escriba en **Buscar por cualquier campo…** y haga clic en **Buscar**. Para volver a la lista completa, haga clic en **✕**.
3. Para crear un tipo, haga clic en **Nuevo**. Para modificar uno, haga clic en **Editar** en su fila.
4. Complete **Code** y **Name**, que son obligatorios. El resto es opcional; la lista de abajo explica cada campo.
5. Marque las casillas que correspondan. Una casilla sin marcar se guarda como **No**: para que el tipo se pueda usar, marque **Active**.
6. Deje vacíos los campos que muestran `{"key":"value"}`.
7. Haga clic en **Guardar** si es un tipo nuevo, o en **Guardar cambios** si lo está modificando.
8. Para desactivar un tipo, edítelo, desmarque **Active** y guarde. Para activarlo de nuevo, márquelo.
9. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

Qué significa cada campo del formulario:

**Identificación**

- **Code**: código corto y único del tipo, por ejemplo CONT, BULK o GENE.
- **Name** / **Short Name**: nombre completo y nombre corto que se ven en las listas.
- **Description**: texto libre.
- **Parent Id**, **Level**, **Full Path**: permiten ordenar los tipos en niveles. **Parent Id** es el número de ID del tipo principal; **Level** 1 es el nivel principal. Puede dejarlos vacíos.

**Códigos**

- **Webservice Code**: código de tipo de carga que se informa a AFIP. En los desconsolidados admite hasta 3 caracteres.
- **Unece Code**: código UN/ECE del tipo de carga. Se informa a AFIP si **Webservice Code** está vacío.
- **Imdg Class**, **Hs Code Prefix**, **Tariff Classification**: clase IMDG de mercadería peligrosa, prefijo del código arancelario y clasificación arancelaria. Son datos de referencia.

**Naturaleza y manejo**

- **Cargo Nature**: estado de la mercadería: Solid, Liquid, Gas, Mixed u Other.
- **Packaging Type**: forma de transporte: Containerized, Bulk, Break Bulk, Ro Ro o Neo Bulk.
- **Requires Refrigeration**, **Requires Special Handling**, **Is Dangerous Goods**, **Requires Permits**, **Is Perishable**, **Is Fragile**, **Requires Fumigation**: características de la mercadería.
- **Can Be Mixed**: si se puede transportar junto con otras cargas.

**Documentación e inspección**

- **Requires Certificate Origin**, **Requires Health Certificate**, **Requires Fumigation Certificate**, **Requires Insurance**: certificados que suele requerir.
- **Subject To Inspection**, **Inspection Percentage**: si está sujeta a inspección y qué porcentaje de envíos se inspecciona.

**Datos de referencia**

- **Typical Density**: densidad típica, en kg/m³.
- **Max Weight Per Container**: peso máximo por contenedor.
- **Insurance Rate**: tasa de seguro típica.
- **Typical Loading Time**: tiempo típico de carga, en horas.

**Operaciones**

- **Allows Consolidation**, **Allows Transshipment**: si admite consolidación y transbordo.
- **Allows Deconsolidation**: en las pantallas de desconsolidado solo aparecen los tipos activos que tienen marcada esta casilla.

**Estado y visualización**

- **Active**: solo los tipos activos aparecen al cargar conocimientos y sus líneas.
- **Is Common**: marca los tipos de uso frecuente.
- **Display Order**: orden en las listas; un número menor aparece primero.
- **Icon**, **Color Code**: ícono y color con que se muestra el tipo.
- **Created Date**: fecha de alta, opcional.

## Cómo saber que salió bien

Al guardar, vuelve a la lista con el mensaje **Tipo de carga creado correctamente.** o **Tipo de carga actualizado correctamente.** Al eliminar, aparece **Tipo de carga eliminado correctamente.** En la columna **Active**, un tipo habilitado muestra **Sí**.

## Si algo falla

- **Este campo es obligatorio.**: complete **Code** y **Name**.
- **Este valor ya está en uso.** debajo de **Code**: ya existe otro tipo con ese código; use uno distinto.
- **Debe ser un número válido.**: en ese campo escriba solo números, con punto decimal.
- **Debe ser un JSON válido.**: deje vacío el campo marcado.
- Si vacía un campo y guarda los cambios, el campo conserva su valor anterior.
- Si el tipo no aparece al cargar un conocimiento, revise que tenga **Sí** en **Active**. Si no aparece en desconsolidados, revise también **Allows Deconsolidation**.
- Si al eliminar aparece una pantalla de error, el tipo está en uso en algún conocimiento o línea de carga y no se puede borrar.
