# Manual de usuario — Diseño de pantallas (UI/UX)

**Para:** Code (vistas, buscador y enlace en el menú del #44). **Revisa:** Codex (QA) y Claudia.
**Base:** `main` en `9187972`, rama `feature/manual-usuario`.
**Objetivo:** que el manual sea lo más fácil de usar de la app sin parecer otra aplicación. Todo lo de abajo usa piezas que la app ya tiene: Tailwind, Figtree, `@tailwindcss/typography`, Alpine y los componentes de `resources/views/components/`. No se agregan dependencias.

---

## 1. Principios

1. **Se encuentra en dos segundos.** El buscador es lo primero que se ve y tiene el foco al entrar.
2. **Se lee sin esfuerzo.** Texto a ancho de lectura, pasos numerados bien visibles y nombres de botones en negrita, tal como aparecen en pantalla.
3. **Parece parte de la app.** Mismas tarjetas, colores, encabezado y foco que el panel de empresa. Nada de estilos nuevos que no existan ya en la app.
4. **Funciona con teclado y en el celular.**

---

## 2. Lenguaje visual (lo que ya usa la app)

| Elemento | Clases de referencia (copiadas de la app) |
| --- | --- |
| Fondo de página | `bg-gray-100` (lo pone `layouts/app.blade.php`) |
| Encabezado de página | slot `header` con `<h2 class="font-semibold text-xl text-gray-800 leading-tight">` (122 vistas lo usan) |
| Tarjeta | `bg-white overflow-hidden shadow rounded-lg`, cuerpo `px-4 py-5 sm:p-6`, pie `bg-gray-50 px-5 py-3` (panel de empresa) |
| Título de tarjeta | `text-lg font-medium text-gray-900` |
| Texto secundario | `text-sm text-gray-500` / `text-gray-600` |
| Enlace | `text-sm font-medium text-indigo-600 hover:text-indigo-500` |
| Botón principal | `bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500` |
| Foco | `focus:ring-indigo-500` |
| Atajo de teclado | `<kbd class="inline-flex items-center px-2 py-1 border border-gray-200 rounded text-xs font-medium bg-gray-50 text-gray-500">` (buscador global) |
| Ítem de resultado | `w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group` (buscador global) |
| Íconos de sección | Emoji, como el menú lateral (🏠, 📋, 🏛️…) |

Color de acento del manual: **indigo**, el mismo del menú y del panel. Para "Borrador": `bg-yellow-100 text-yellow-800`, como los avisos de la app.

---

## 3. Estructura y navegación

- **Layout:** `<x-app-layout>`. La ruta `/manual` no es `company.*`, así que se muestra **sin menú lateral**, solo con la barra superior. Es lo buscado: más ancho para leer. La barra superior da el camino de vuelta a la app.
- **Ancho:** contenedor `max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8`.
- **Enlace en la barra superior** (`navigation.blade.php`): `<x-nav-link href="{{ route('manual.index') }}" :active="request()->routeIs('manual.*')">` con ícono de libro (SVG `w-4 h-4 mr-1`, mismo trazo que los demás) y el texto **Manual**. Ubicación: a la derecha de los menús, antes del menú de usuario. En el menú de celular, `<x-responsive-nav-link>` equivalente.
- **Título de pestaña:** "Manual — {título del artículo}".

---

## 4. Pantalla 1: índice y buscador (`manual/index.blade.php`)

### Encabezado
`<h2>`: **Manual de usuario**. Debajo, en `text-sm text-gray-500`: "Guías paso a paso para usar el sistema."

### Buscador (protagonista)
- Una tarjeta ancha arriba con un campo grande: `text-base` (no `text-sm`), `py-3 pl-11`, lupa a la izquierda (`w-5 h-5 text-gray-400`), `rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500`.
- `placeholder`: "Busque una tarea o palabra: MIC/DTA, anular, contraseña…".
- `autofocus` al entrar. Atajo **/** para enfocar desde cualquier parte de la página; se muestra con `<kbd>/</kbd>` a la derecha del campo (mismo estilo que el ⌘K del buscador global).
- **Esc** borra la búsqueda.
- `<label class="sr-only">Buscar en el manual</label>`.
- La búsqueda se guarda en la URL (`?q=`) con `history.replaceState`, así **Volver** desde un artículo recupera los resultados.

### Filtro por tema
- Debajo del campo, una fila de "chips" con los temas que el usuario puede ver, cada uno con su cantidad: `inline-flex items-center px-3 py-1 rounded-full text-sm`. Inactivo: `bg-gray-100 text-gray-700 hover:bg-gray-200`; activo: `bg-indigo-600 text-white`. Primer chip: **Todos**.
- En celular, la fila se desplaza en horizontal (`overflow-x-auto`, sin salto de línea).
- Solo aparecen los temas con al menos un artículo visible para ese usuario.

### Sin búsqueda: temas
Grilla `grid gap-6 sm:grid-cols-2 lg:grid-cols-3` de tarjetas, una por tema, en el orden de `config('manual.temas')`:
- Cabecera de la tarjeta: emoji del tema + nombre (`text-lg font-medium text-gray-900`) + cantidad de artículos (`text-sm text-gray-500`).
- Cuerpo: hasta 5 artículos como enlaces (`text-sm text-indigo-600 hover:text-indigo-500`), ordenados por `orden`.
- Pie (`bg-gray-50 px-5 py-3`): "Ver los N artículos →" si hay más de 5 (activa el filtro de ese tema).

Emojis por tema (en `config/manual.php` o en la vista): Primeros pasos 🚀 · Administración 🛠️ · Mi empresa 🏢 · Datos maestros 🗂️ · Viajes y cargas 🚢 · Importar manifiestos 📥 · AFIP Argentina 🇦🇷 · Paraguay DNA 🇵🇾 · Desconsolidado y transbordos 📦 · Reportes 📊 · Preguntas frecuentes ❓.

### Con búsqueda: resultados
- Encima de la lista, `text-sm text-gray-500` con `aria-live="polite"`: "**N** resultados para «texto»".
- Lista en una sola tarjeta, un ítem por artículo:
  - Título (`font-medium text-gray-900`) con las coincidencias resaltadas: `<mark class="bg-yellow-100 text-gray-900 rounded px-0.5">`.
  - Debajo, chip del tema (`text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700`) y un fragmento de 1–2 líneas del texto alrededor de la primera coincidencia, también resaltada (`text-sm text-gray-600 line-clamp-2`).
  - Flecha → que aparece al pasar el mouse (`opacity-0 group-hover:opacity-100`, como el buscador global).
- **Teclado:** ↑ y ↓ recorren los resultados (ítem activo `bg-gray-50 ring-1 ring-indigo-200`), **Enter** abre el artículo.
- Orden: título > palabras clave > texto (ya acordado). A igual puntaje, por tema y `orden`.

### Sin resultados
Estado vacío centrado (`text-center py-12`): ícono de lupa gris, "No encontramos artículos para «texto»", y debajo tres ayudas:
- "Revise la ortografía o use menos palabras."
- "Pruebe con otro nombre: por ejemplo, *título* en lugar de *conocimiento*."
- Botón secundario **Ver todos los temas** (limpia la búsqueda).

### Detalles del buscador
- Normaliza tildes y mayúsculas, y expande sinónimos de `_sinonimos.yml` (ya acordado).
- Para resaltar sin perder las tildes del texto original, normalizar **carácter por carácter** (cada carácter da uno), así las posiciones coinciden entre el texto normalizado y el original.
- Sin espera ni botón "Buscar": filtra mientras se escribe. Con ~40 artículos no hace falta *debounce*.
- Los datos van embebidos con `@json(...)` en el `x-data` de Alpine; no hay llamadas al servidor.

---

## 5. Pantalla 2: artículo (`manual/show.blade.php`)

### Encabezado
- Miga de pan encima del título, `text-sm text-gray-500`: **Manual** › **{Tema}** (cada parte es un enlace; el tema abre el índice con ese filtro).
- `<h2>` con el título del artículo.
- Si es borrador (solo visible con `MANUAL_BORRADORES=true`): chip **Borrador** amarillo al lado del título.

### Cuerpo: dos columnas en escritorio
`grid gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]`:

**Columna principal:** una tarjeta blanca (`bg-white shadow rounded-lg px-6 py-8 sm:px-10`) con el HTML del artículo dentro de:

```
prose prose-gray max-w-none
prose-headings:font-semibold prose-headings:text-gray-900
prose-h2:text-lg prose-h2:mb-3 prose-h2:pb-2 prose-h2:border-b prose-h2:border-gray-100
prose-a:text-indigo-600 prose-a:font-medium prose-a:no-underline hover:prose-a:underline
prose-strong:text-gray-900
prose-ol:pl-6 prose-li:my-1.5 prose-li:marker:font-semibold prose-li:marker:text-indigo-600
prose-code:before:content-none prose-code:after:content-none
```

- Versiones instaladas: Tailwind 3.4.17 (admite `line-clamp`, valores arbitrarios y variantes apiladas como `prose-li:marker:`), typography 0.5.16 y CommonMark 2.7.0. Verificar en el navegador que cada clase de esta lista se aplique; si alguna no, reemplazarla por otra de la app, no por CSS propio.
- Ancho de lectura: el texto queda en ~70 caracteres por línea gracias a la columna lateral; no estirar a todo el ancho.
- Los pasos numerados se ven con el número en indigo y en negrita: es lo que más se mira.
- Los nombres de botones y menús ya vienen en negrita desde el Markdown; no se les agrega otro estilo.

**Columna lateral** (`lg:sticky lg:top-6 self-start`, en celular pasa debajo del artículo):
1. **En esta página:** los cinco títulos (Para qué sirve, Antes de empezar, Pasos, Cómo saber que salió bien, Si algo falla) como enlaces de ancla. Requiere `id` en los `h2`: agregar `HeadingPermalinkExtension` de `league/commonmark` (viene incluida en el paquete instalado) con `symbol` vacío, en `ManualRepository::convertidor()`. Un commit aparte.
2. **Relacionados:** tarjeta con los artículos relacionados visibles, cada uno con su tema en `text-xs text-gray-500`.

### Pie del artículo
Debajo de la tarjeta, una línea con:
- **← Volver al buscador** (conserva `?q=` y el tema si venía de una búsqueda).
- A la derecha, el artículo anterior y siguiente del mismo tema (por `orden`): "← {título}" / "{título} →". Ayuda a seguir un flujo (por ejemplo, MIC/DTA → salida → arribo).

---

## 6. Accesibilidad

- Un solo `h1` lógico por página (el `h2` del header cumple ese rol en toda la app; mantener la convención) y los `h2` del artículo en orden.
- Foco visible en todo lo navegable (`focus:ring-2 focus:ring-indigo-500`).
- Contraste: `<mark>` amarillo claro con texto `gray-900`; nunca texto `gray-400` sobre blanco para contenido.
- Resultados con `role="listbox"` / `role="option"` y `aria-selected` en el activo, o una lista de enlaces normales si el teclado ya funciona sin roles; lo importante es que **Tab** y **↑ ↓** lleguen a todo.
- Respetar `prefers-reduced-motion`: sin animaciones salvo los `transition-colors` que ya usa la app.

---

## 7. Celular

- Una columna; la columna lateral del artículo va al final.
- Chips de temas desplazables en horizontal.
- El campo de búsqueda conserva `text-base` (evita el zoom automático en iOS).
- Áreas táctiles de al menos 40 px de alto en resultados y chips.

---

## 8. Atención: compilación de estilos

`public/build` no está versionado (`.gitignore`, línea 3) y Tailwind solo genera las clases que encuentra en `resources/views/**/*.blade.php`. Las clases nuevas del manual (`prose-*`, `marker:`, `lg:grid-cols-[...]`, etc.) **no existen** en stage ni en producción hasta que se corra `npm run build` y se suba `public/build`.

- Las clases se escriben **completas** en las vistas Blade (nunca armadas por concatenación), para que Tailwind las encuentre.
- El HTML que genera `ManualRepository` no lleva clases: todo el estilo sale del contenedor `prose` de la vista.
- El paquete de deploy (AGENTS.md §6) tiene que incluir `public/build` compilado. Sin eso, el manual se ve sin formato.

---

## 9. Criterio de aceptación (además de los del #44)

- [ ] Al entrar a `/manual`, el cursor queda en el buscador; **/** lo enfoca y **Esc** lo limpia.
- [ ] Escribir "barcaza" muestra resultados resaltados al instante, con su tema y un fragmento.
- [ ] ↑ ↓ y Enter abren un resultado sin usar el mouse; **Volver** recupera la búsqueda.
- [ ] Sin búsqueda se ven las tarjetas de temas; los chips filtran y muestran la cantidad.
- [ ] Una búsqueda sin coincidencias muestra el estado vacío con las ayudas.
- [ ] El artículo tiene miga de pan, índice "En esta página" que salta a cada sección, relacionados y anterior/siguiente.
- [ ] A 375 px de ancho no hay scroll horizontal de la página y todo se lee sin zoom.
- [ ] Comparado lado a lado con el panel de empresa, tarjetas, colores, tipografía y foco se ven iguales.
- [ ] Con `npm run build`, ningún estilo del manual queda sin aplicar.
