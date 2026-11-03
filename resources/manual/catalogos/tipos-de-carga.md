---
titulo: Cómo administrar los tipos de carga
tema: catalogos
orden: 20
roles: [super-admin]
palabras_clave: [tipo de carga, carga, catálogo, TipoCarga, desconsolidado, granel, contenedorizada]
relacionados: [catalogos/tipos-de-contenedor, catalogos/tipos-de-embalaje]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
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
4. Complete **Código** y **Nombre**, que son obligatorios. El resto es opcional; la lista de abajo explica cada campo.
5. Marque las casillas que correspondan. Una casilla sin marcar se guarda como **No**: para que el tipo se pueda usar, marque **Activo**.
6. Deje vacíos los campos que muestran `{"key":"value"}`.
7. Haga clic en **Guardar** si es un tipo nuevo, o en **Guardar cambios** si lo está modificando.
8. Para desactivar un tipo, edítelo, desmarque **Activo** y guarde. Para activarlo de nuevo, márquelo.
9. Para borrar un tipo que no se usa, haga clic en **Eliminar** en su fila y confirme.

Qué significa cada campo del formulario:

**Identificación**

- **Código**: código corto y único del tipo, por ejemplo CONT, BULK o GENE.
- **Nombre** / **Nombre corto**: nombre completo y nombre corto que se ven en las listas.
- **Descripción**: texto libre.
- **Tipo padre**, **Nivel**, **Ruta completa**: permiten ordenar los tipos en niveles. **Tipo padre** es el número de ID del tipo principal; el **Nivel** 1 es el nivel principal. Puede dejarlos vacíos.

**Códigos**

- **Código para webservices**: código de tipo de carga que se informa a AFIP. En los desconsolidados admite hasta 3 caracteres.
- **Código UN/ECE**: código UN/ECE del tipo de carga. Se informa a AFIP si **Código para webservices** está vacío.
- **Clase IMDG**, **Prefijo de código SA**, **Clasificación arancelaria**: clase IMDG de mercadería peligrosa, prefijo del código arancelario y clasificación arancelaria. Son datos de referencia.

**Naturaleza y manejo**

- **Naturaleza de la carga**: estado de la mercadería: Sólida, Líquida, Gaseosa, Mixta u Otro.
- **Forma de transporte**: En contenedor, Granel, Carga general, Ro-Ro o Neo granel.
- **Requiere refrigeración**, **Requiere manejo especial**, **Mercancía peligrosa**, **Requiere permisos**, **Perecedera**, **Frágil**, **Requiere fumigación**: características de la mercadería.
- **Se puede mezclar**: si se puede transportar junto con otras cargas.

**Documentación e inspección**

- **Requiere certificado de origen**, **Requiere certificado sanitario**, **Requiere certificado de fumigación**, **Requiere seguro**: certificados que suele requerir.
- **Sujeto a inspección**, **Porcentaje de inspección**: si está sujeta a inspección y qué porcentaje de envíos se inspecciona.

**Datos de referencia**

- **Densidad típica (kg/m³)**: densidad típica, en kg/m³.
- **Peso máximo por contenedor**: peso máximo por contenedor.
- **Tasa de seguro**: tasa de seguro típica.
- **Tiempo típico de carga (horas)**: tiempo típico de carga, en horas.

**Operaciones**

- **Permite consolidación**, **Permite transbordo**: si admite consolidación y transbordo.
- **Permite desconsolidación**: en las pantallas de desconsolidado solo aparecen los tipos activos que tienen marcada esta casilla.

**Estado y visualización**

- **Activo**: solo los tipos activos aparecen al cargar conocimientos y sus líneas.
- **Común**: marca los tipos de uso frecuente.
- **Orden de visualización**: orden en las listas; un número menor aparece primero.
- **Ícono**, **Color**: ícono y color con que se muestra el tipo.
- **Fecha de alta**: fecha de alta, opcional.

## Cómo saber que salió bien

Al guardar, vuelve a la lista con el mensaje **Tipo de carga creado correctamente.** o **Tipo de carga actualizado correctamente.** Al eliminar, aparece **Tipo de carga eliminado correctamente.** En la columna **Activo**, un tipo habilitado muestra **Sí**.

## Si algo falla

- **El campo Código es obligatorio.** o **El campo Nombre es obligatorio.**: complete ese campo.
- **El valor del campo Código ya está en uso.**: ya existe otro tipo con ese código; use uno distinto.
- **El campo … debe ser un número.**: en ese campo escriba solo números, con punto decimal.
- **El campo … no puede tener más de … caracteres.**: acorte el texto.
- **El campo … debe ser un texto JSON válido.**: deje vacío el campo marcado.
- Si vacía un campo y guarda los cambios, el campo conserva su valor anterior.
- Si el tipo no aparece al cargar un conocimiento, revise que tenga **Sí** en **Activo**. Si no aparece en desconsolidados, revise también **Permite desconsolidación**.
- Si al eliminar aparece una pantalla de error, el tipo está en uso en algún conocimiento o línea de carga y no se puede borrar.
