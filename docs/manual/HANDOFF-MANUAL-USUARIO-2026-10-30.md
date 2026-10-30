# HANDOFF — Manual de usuario en la app — 30/10/2026

El manual se hace **dentro de la app**, en formato **How-To**: Markdown para el contenido y Blade para mostrarlo. Todo el equipo escribe en paralelo, un tema cada uno, y **recorre cada pantalla en stage mientras escribe** para detectar bugs.

Cómo leer este handoff: todos leen las secciones 1 a 5. Después, cada responsable lee su consigna (6, 7 u 8).

Base de código: `main` en `c62042a`.

---

## 1. Decisiones tomadas

| Tema | Decisión |
| --- | --- |
| Formato | Contenido en Markdown, presentado con Blade dentro de la app. Sin dependencias nuevas: `league/commonmark` 2.7, `symfony/yaml` y `@tailwindcss/typography` ya están instalados. |
| Acceso | Enlace "Manual" en la barra superior común (`resources/views/components/navigation.blade.php`), visible para super-admin, company-admin y user. Cada usuario ve solo los artículos de su rol y de los roles de su empresa. |
| Buscador | Nivel 1: temas y palabras, sin distinguir tildes ni mayúsculas. Nivel 2: diccionario de sinónimos del rubro. La tolerancia a errores de tipeo (nivel 3) se decide después del piloto. |
| Tono | Usted, igual que la interfaz (127 formas en usted, ninguna en vos). |
| Recorrido | Cada redactor prueba en stage cada paso que escribe y reporta los bugs que encuentra. Los bugs **no** se corrigen dentro de las tareas del manual. |
| Ayudas en pantalla | Los botones "?" y las guías breves quedan para la fase 3 y enlazan a estos artículos. |

---

## 2. Reparto del índice

Son 11 temas y unos 30 artículos. Charly puede redistribuir los temas de su equipo entre Work y Codex como prefiera; la columna Responsable es una sugerencia.

| Tema (carpeta) | Artículos | Quién lo ve | Responsable |
| --- | --- | --- | --- |
| `primeros-pasos` | Ingresar y editar el perfil; qué ve cada rol; recorrido del menú | Todos | Charly · Work |
| `administracion` | Crear una empresa y asignar sus roles; usuarios; catálogos (puertos, países, tipos de carga, embalaje y contenedor, propietarios); configuración AFIP | Super-admin | Charly · Work |
| `mi-empresa` | Operadores; certificados .p12 y ambiente homologación/producción; configuración de la empresa | Company-admin | Charly · Work |
| `datos-maestros` | Clientes (CUIT/RUC); embarcaciones y propietarios; capitanes | Empresa | Charly · Work |
| `reportes` | Manifiesto, listado de conocimientos, MIC/DTA, avisos de arribo, MANE | Empresa | Charly · Work |
| `viajes-y-cargas` | Crear un viaje a mano (viaje → carga → conocimiento → ítems y contenedores); armar un convoy | Empresa | Charly · Codex |
| `paraguay-dna` | Secuencia XFFM → adjuntos → XFBL → XFBT → XFCT; conocimientos fraccionados; viaje cerrado | Cargas | Charly · Codex |
| `afip-otros-roles` | Desconsolidado; transbordos | Desconsolidador / Transbordos | Charly · Codex |
| `importar-manifiestos` | Cómo importar; formatos soportados; "el viaje ya existe"; revertir una importación; historial | Cargas | Code (después de la infraestructura) |
| `afip-argentina` | MIC/DTA de una embarcación; MIC/DTA de un convoy; salida y arribo a zona primaria; anular y rectificar; información anticipada | Cargas | Claudia |
| `preguntas-frecuentes` | Casos reales: dos convoyes en simultáneo, varios archivos en un mismo viaje, "el viaje ya existe" | Todos | Claudia |

"Empresa" significa company-admin y user. Los artículos de cada rol de empresa (Cargas, Desconsolidador, Transbordos) solo los ven las empresas que tienen ese rol.

---

## 3. Contrato común

Este contrato permite escribir en paralelo antes de que exista la infraestructura: todos producen archivos con la misma forma y la infraestructura los lee.

### Carpetas

Un archivo por artículo, en la carpeta de su tema. El nombre del archivo es el slug: minúsculas, guiones y empezando por un verbo (`registrar-micdta-convoy.md`).

```
resources/manual/
  _sinonimos.yml                     (lo mantiene Claudia)
  primeros-pasos/ingresar-y-perfil.md
  importar-manifiestos/importar-un-manifiesto.md
  afip-argentina/registrar-micdta-convoy.md
  ...
```

### Cabecera

Todos los campos son obligatorios salvo `relacionados` y `roles_empresa`.

```markdown
---
titulo: Cómo importar un manifiesto
tema: importar-manifiestos
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [importar, archivo, Excel, XML, EDI, manifiesto]
relacionados: [importar-manifiestos/revertir-una-importacion]
estado: borrador
verificado:
  version: c62042a
  fecha: 2026-10-30
  entorno: stage
---
```

| Campo | Valores |
| --- | --- |
| `roles` | `super-admin`, `company-admin`, `user` |
| `roles_empresa` | `Cargas`, `Desconsolidador`, `Transbordos`. Vacío = cualquier empresa |
| `orden` | Número para ordenar dentro del tema (10, 20, 30…) |
| `estado` | `borrador` (oculto salvo que el entorno muestre borradores) o `publicado` |
| `verificado.entorno` | `stage` si se probó en pantalla; `codigo` si solo se verificó contra el código (no se publica así) |

### Enlaces dentro del texto

- A otro artículo: `[Revertir una importación](manual:importar-manifiestos/revertir-una-importacion)`.
- A una pantalla de la app: `[Ir a Importar](ruta:company.manifests.import.index)`. Solo rutas sin parámetros; si la ruta no existe se muestra el texto sin enlace.

### Sinónimos

`_sinonimos.yml` agrupa términos equivalentes; el buscador trata cada grupo como una sola palabra. Solo lo edita Claudia; los demás proponen términos en su reporte.

```yaml
- [barcaza, remolcada, embarcación remolcada]
- [conocimiento, título, BL]
- [cierre de viaje, XFCT]
- [MIC/DTA, micdta, mic dta]
```

---

## 4. Plantilla How-To y reglas de redacción

Cada artículo resuelve una sola tarea y usa siempre estas cinco secciones, en este orden. Los relacionados se muestran solos desde la cabecera.

```markdown
## Para qué sirve
Una o dos líneas: qué logra el usuario con esta tarea.

## Antes de empezar
- Requisitos, cada uno con enlace al artículo que lo explica.

## Pasos
1. Abra **Menú › Submenú**.
2. Haga clic en **Nombre exacto del botón**.
3. ...

## Cómo saber que salió bien
Qué mensaje, número o estado tiene que aparecer y dónde.

## Si algo falla
- **Mensaje o código que ve el usuario:** qué significa y qué hacer.
```

### Reglas

1. Usted en imperativo: "Abra", "Haga clic en", "Seleccione".
2. Menús, botones y campos en negrita, copiados tal como se ven en pantalla. Nunca desde el código ni de memoria.
3. Vocabulario de la interfaz, no de la base: "Carga", no shipment.
4. Más de 10 pasos = dividir en dos artículos.
5. Solo se documenta lo que funciona en stage. Si un paso falla, se reporta el bug y el artículo queda en `borrador`.
6. Nada interno: ni tablas, campos, clases o código; ni el equipo, agentes o forma de trabajo; ni deuda técnica; ni nombres de clientes o datos reales (CUIT, números de viaje). Ejemplos siempre genéricos. Cada artículo tiene que pasar el control de AGENTS.md §6 (`grep -rniE "Claude|Codex|AGENTS|TAREA|Co-Authored" resources/manual/` sin coincidencias), porque `resources/` sí va a producción.
7. Códigos AFIP/DNA en "Si algo falla": solo los observados en respuestas reales o en la traducción de errores que ya tiene la app.
8. Sin capturas de pantalla en esta fase.

---

## 5. Recorrido de la app y reporte de bugs

Cada redactor recorre en stage todas las pantallas de su tema, con cada rol que las ve, y prueba cada paso antes de escribirlo.

### Entorno

- Stage (`cargas.red-byte.com`), con una empresa de prueba y webservices en homologación.
- **Atención:** `AGENTS.md` (línea 9) todavía nombra a `cargas.red-byte.com` como producción. Desde octubre 2026 producción es el servidor del cliente y red-byte es stage. Para esta tarea vale lo que dice este handoff hasta que se actualice `AGENTS.md`.
- Nunca el servidor de producción, nunca las empresas reales del cliente, nunca envíos en ambiente de producción.
- Los datos se cargan solo desde la interfaz, sin escrituras por Tinker.

### Formato del reporte

Una fila por bug; se entrega a Claudia junto con los artículos.

| ID | Pantalla (menú) | Rol | Pasos | Esperado | Obtenido | Severidad | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TEMA-01 | Manifiestos › Importar | user | 1. … 2. … | … | … | Bloquea / Error / Cosmético | Mensaje o captura |

### Ya conocidos (no reportar de nuevo)

- Asignar ATA Remolcador: el formulario y el servicio usan nombres de parámetros distintos (`remolcador_id`/`ata_remolcador` contra `id_micdta`/`cuit_ata_remolcador`); no puede completarse.
- Datos del convoy (modal de rectificación): ya reportado por canal interno.
- Salida de zona primaria: el número de viaje se tipea y no se valida contra el viaje.
- RegistrarMicDta sobre el mismo viaje: dos ejecuciones casi simultáneas pueden duplicar el envío (no hay bloqueo).

---

## 6. Consigna para Code: infraestructura (fase 1)

**Responsable:** Code.
**Objetivo:** que el manual se pueda leer y buscar dentro de la app, con el contrato común de este handoff.

### Alcance

Un archivo por vez, con confirmación de Víctor:

1. `routes/web.php`: dentro del grupo `Route::middleware(['auth', 'verified'])` (línea 30, fuera de los subgrupos admin y company): `GET /manual` → `manual.index` y `GET /manual/{tema}/{slug}` → `manual.show`, con `tema` y `slug` restringidos a `[a-z0-9-]+`.
2. `config/manual.php`: carpeta de contenido, nombre y orden de los 11 temas, y `mostrar_borradores` (env `MANUAL_BORRADORES`, por defecto `false`).
3. `app/Services/Manual/ManualRepository.php`:
    - lee `resources/manual/` e interpreta la cabecera con la extensión de front matter de CommonMark;
    - convierte con `html_input` en `strip` y sin enlaces inseguros;
    - resuelve los enlaces `manual:` y `ruta:`;
    - filtra por `roles`, `roles_empresa` y `estado`, con la misma lógica de empresa que `navigation.blade.php` (líneas 4-14);
    - arma el índice de búsqueda (título, tema, palabras clave, texto plano, URL) y lo cachea; se limpia con `optimize:clear`.
4. `app/Http/Controllers/ManualController.php`: `index` y `show`; 404 si el artículo no existe o el usuario no puede verlo. Solo se sirven archivos que estén en el índice.
5. `resources/views/manual/index.blade.php` y `show.blade.php` con `<x-app-layout>` y clases `prose`. En el artículo: miga de pan, relacionados y volver al buscador.
6. Buscador en Alpine dentro de `index`: normaliza tildes y mayúsculas, expande los grupos de `_sinonimos.yml`, pondera título > palabras clave > texto, filtra por tema y muestra un fragmento del texto. Sin Fuse.js por ahora.
7. `resources/views/components/navigation.blade.php`: enlace "Manual" en escritorio (junto al menú de usuario) y en el menú de celular, para todo usuario autenticado.
8. `tests/Feature/ManualTest.php`, con artículos de prueba propios (no los reales).

### Restricciones

- Sin dependencias nuevas de Composer ni npm.
- `php -l` antes de cada commit; `git diff --cached` revisado por Víctor.
- Rama `feature/manual-usuario`. Commit y push autorizados a esa rama; sin merge a main ni deploy.
- Sin `Co-Authored-By` ni referencias al equipo o a la forma de trabajo en código, comentarios o commits.

### Criterio de aceptación

- [ ] El enlace aparece para super-admin, company-admin y user, en escritorio y celular.
- [ ] Cada rol ve solo sus artículos; una empresa sin rol Cargas no ve los de Cargas.
- [ ] Un borrador no aparece con `MANUAL_BORRADORES=false` y aparece con `true`.
- [ ] Buscar "importacion" encuentra "importación"; buscar "barcaza" encuentra un artículo que solo dice "remolcada".
- [ ] Los enlaces `manual:` y `ruta:` funcionan; una ruta inexistente queda como texto.
- [ ] Un slug inexistente o un artículo de otro rol devuelve 404.
- [ ] Los tests pasan.

**Después:** el tema `importar-manifiestos`, con el contrato y las reglas de las secciones 3 a 5.

---

## 7. Consigna para Charly y su equipo

**Responsable:** Charly, que reparte entre Work y Codex.
**Objetivo:** redactar los artículos de 8 temas recorriendo cada pantalla en stage, y reportar los bugs que aparezcan en el camino.

**Alcance:** carpetas `primeros-pasos`, `administracion`, `mi-empresa`, `datos-maestros`, `reportes`, `viajes-y-cargas`, `paraguay-dna` y `afip-otros-roles`, con los artículos de la tabla de reparto.

### Restricciones

- Contrato común, plantilla y reglas de redacción de este handoff.
- Solo archivos dentro de esas carpetas. `_sinonimos.yml` no se toca: los términos se proponen en el reporte.
- Los bugs se reportan, no se corrigen en esta tarea.
- Rama `manual/equipo-charly`. Commit y push autorizados a esa rama; sin merge a main ni deploy.
- Piloto primero: un artículo terminado (sugerido: `primeros-pasos/ingresar-y-perfil.md`) para que Claudia calibre la plantilla antes de escribir el resto.

**Entrega:** artículos en `borrador`, reporte de bugs y lista de términos para sinónimos.

### Criterio de aceptación

- [ ] Cada artículo tiene la cabecera completa y las cinco secciones.
- [ ] `verificado.entorno: stage` en todos; los que no pudieron probarse quedan señalados en el reporte.
- [ ] Nombres de menús y botones idénticos a la pantalla.
- [ ] Ninguna referencia interna (regla 6).

---

## 8. Consigna para Claudia

**Responsable:** Claudia.
**Objetivo:** contenido de AFIP Argentina y preguntas frecuentes, más la coherencia de todo el manual.

### Alcance

- Temas `afip-argentina` y `preguntas-frecuentes`, verificados contra el código de main y partiendo de la guía Word de convoy de marzo.
- `_sinonimos.yml` inicial y sus actualizaciones con los términos que propongan los equipos.
- Revisión de redacción y consistencia de todos los artículos antes del merge.
- Triage de los bugs reportados: prioridad y tarea de corrección aparte, una por vez.

**Límite:** Claudia no opera stage. Sus artículos salen con `verificado.entorno: codigo` y pasan a `stage` cuando Work (o Víctor) los recorre en pantalla.

**Criterio de aceptación:** los mismos que los demás artículos; ningún artículo de AFIP se publica sin la pasada en stage.

---

## 9. Flujo de entrega, revisión y publicación

La infraestructura y la redacción corren en paralelo: los artículos son Markdown y no dependen de que la infraestructura esté lista.

1. **Piloto:** Code arranca la infraestructura; Charly y Claudia entregan un artículo cada uno. Claudia ajusta la plantilla si hace falta.
2. **Redacción:** cada equipo escribe sus temas en su rama y recorre las pantallas en stage.
3. **Entrega:** artículos en `borrador` + reporte de bugs + términos para sinónimos.
4. **Revisión:** Claudia revisa redacción y consistencia; los bugs pasan a tareas de corrección aparte.
5. **Aprobación:** Víctor aprueba y se hace el merge a main.
6. **Stage:** deploy con `MANUAL_BORRADORES=true` para leerlo en pantalla; lo aprobado pasa a `publicado`.
7. **Producción:** deploy por paquete (AGENTS.md §6) con `MANUAL_BORRADORES=false` y `optimize:clear`.

Este handoff, las consignas y los reportes viven en `docs/`, que no entra en el paquete de producción. A producción solo va `resources/manual/` y el código de la infraestructura.

---

## 10. Pendientes que define Víctor

- [ ] Qué empresa de prueba se usa en stage y si tiene certificado de homologación AFIP y DNA. Sin certificado, los temas de AFIP y Paraguay no pueden recorrerse en pantalla.
- [ ] La guía Word de convoy de marzo (`Guia-Convoy-AFIP-Roberto.docx`), base del artículo de convoy.
- [ ] Quién hace la pasada en stage de los artículos de Claudia: Work o Víctor.
- [ ] Actualizar `AGENTS.md` línea 9 (producción y stage).
- [ ] Después del piloto: si se agrega la tolerancia a errores de tipeo (nivel 3).
