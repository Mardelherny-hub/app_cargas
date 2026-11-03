---
titulo: Cómo administrar los tipos de embarcación
tema: catalogos
orden: 40
roles: [super-admin]
palabras_clave: [embarcación, tipo de embarcación, barcaza, remolcador, empujador, buque, catálogo, tipEmb]
relacionados: [catalogos/tipos-de-contenedor, afip-argentina/registrar-micdta-una-embarcacion]
estado: borrador
verificado:
  version: d462c7f
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Mantiene la lista de tipos de embarcación (barcaza, remolcador, empujador, buque autopropulsado) que se asigna a cada embarcación. El código del tipo es el que se informa a AFIP en el MIC/DTA, y la categoría es la que se informa a DNA Paraguay.

## Antes de empezar

- Ingrese con un usuario **super-admin**.
- Esta pantalla no figura en el menú: se abre escribiendo la dirección del sistema seguida de `/admin/vessel-types`.
- Si el tipo se va a usar en el MIC/DTA, su **Código** tiene que ser uno de los que el sistema traduce para AFIP:
    - Empujador: EMP, EMPUJE, EMPUJADOR, PUSHER_STD_001 o PUSHER_HEAVY_001.
    - Remolcador: REM, REMOLCADOR, TUG_HARBOR_001 o TUG_RIVER_001.
    - Buque autopropulsado: BUM, BUQUE, BUQUE_MOTOR o SELF_CARGO_001.
    - Barcaza: BAR, BARCAZA, BARGE_STD_001, BARGE_BULK_001, BARGE_TANK_001 o BARGE_MULTI_001.

## Pasos

1. Abra la dirección del sistema seguida de `/admin/vessel-types`.
2. Para buscar o filtrar, haga clic en **Filtros**, complete **Búsqueda**, **Categoría**, **Propulsión** o **Estado** y haga clic en **Filtrar**. Para volver a la lista completa, haga clic en **Limpiar**.
3. Para crear un tipo, haga clic en **Nuevo Tipo**.
4. En **Información Básica**, elija **Categoría** y **Tipo de Propulsión** y escriba el **Nombre**. El sistema propone un **Código** a partir de ellos: reemplácelo por uno de la lista de **Antes de empezar** si el tipo se va a usar en el MIC/DTA.
5. Complete los demás campos que necesite; la lista de abajo explica los que se guardan.
6. En **Configuración y Estado**, deje marcado **Activo** para que el tipo se pueda asignar a embarcaciones.
7. Haga clic en **Crear Tipo de Embarcación**.
8. Para modificar un tipo, haga clic en el ícono **Editar** de su fila (lápiz), cambie los datos y haga clic en **Actualizar Tipo de Embarcación**.
9. Para desactivar o activar un tipo, haga clic en el ícono de estado de su fila: su leyenda dice **Desactivar** o **Activar**. También puede hacerlo con el botón **Desactivar** o **Activar** de la página de detalle.
10. Para borrar un tipo, haga clic en el ícono **Eliminar** de su fila y confirme.

Qué significa cada campo que se guarda:

**Información Básica**

- **Código**: código único del tipo, de hasta 20 caracteres. Es el que se informa a AFIP en el MIC/DTA.
- **Nombre**: nombre completo, de hasta 100 caracteres.
- **Nombre Corto**: nombre para listas compactas, de hasta 30 caracteres.
- **Categoría**: Barcaza, Remolcador, Empujador, Autopropulsado o Mixto. Es lo que se informa a DNA Paraguay: Empujador y Remolcador se informan como empujador, Autopropulsado como buque autopropulsado, y el resto como barcaza.
- **Tipo de Propulsión**: Autopropulsado, Remolcado, Empujado o Híbrido.
- **Orden de Visualización**: orden en las listas; un número menor aparece primero.
- **Descripción**: texto libre, de hasta 1000 caracteres.

**Especificaciones Físicas**

- **Longitud (metros)**, **Manga (metros)**, **Calado (metros)**: valores mínimo y máximo de largo, ancho y calado. El máximo no puede ser menor que el mínimo.

**Mantenimiento y Ciclo de Vida**

- **Vida Útil Típica (años)**: entre 1 y 100.
- **Intervalo Dique Seco (meses)**: entre 6 y 120.

**Configuración y Estado**

- **Activo**: solo los tipos activos se pueden asignar.
- **Tipo Común**, **Tipo Especializado**: marcas para clasificar el tipo.
- **Icono**, **Color (Hex)**: ícono y color con que se muestra el tipo.

El resto de los campos del formulario son opcionales; puede dejarlos vacíos.

## Cómo saber que salió bien

Al crear o actualizar, se abre el detalle del tipo con el mensaje **Tipo de embarcación creado exitosamente.** o **Tipo de embarcación actualizado exitosamente.** Al cambiar el estado aparece **Tipo de embarcación activado exitosamente.** o **Tipo de embarcación desactivado exitosamente.**, y la columna **Estado** muestra **Activo** o **Inactivo**. Al eliminar aparece **Tipo de embarcación eliminado exitosamente.**

## Si algo falla

- **Por favor, complete todos los campos obligatorios marcados con \***: complete **Código**, **Nombre**, **Categoría** y **Tipo de Propulsión**.
- **El valor mínimo de … no puede ser mayor al máximo**: corrija el par de valores indicado.
- **Error al crear el tipo de embarcación:** o **Error al actualizar el tipo de embarcación:** seguido de un texto técnico: revise que **Código** tenga hasta 20 caracteres, **Nombre** hasta 100 y **Nombre Corto** hasta 30.
- **No se puede eliminar un tipo de embarcación que tiene embarcaciones asociadas.**: el tipo está en uso. Desactívelo en lugar de borrarlo.
- **RegistrarMicDta: tipo de embarcación '…' no soportado.** al enviar el MIC/DTA: el **Código** del tipo de esa embarcación no está en la lista de **Antes de empezar**. Corríjalo y vuelva a enviar.
