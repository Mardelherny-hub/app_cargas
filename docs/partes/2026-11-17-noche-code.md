# Parte de la noche — Code — 17/11/2026

Orden recibido: 1) usuario creador en las cargas de TFP y Navsur, en T4; 2) QA de la ficha de carga; 3) manual de la etapa 2; 4) tests viejos de la carpeta Parsers en main. Sin merges a main ni servidores.

## 1. T4: usuario creador en las cargas de TFP y Navsur — HECHO

Ya estaba terminado y publicado antes de recibir esta orden. El detalle está en la sección 20 de `2026-11-17-code.md`.

**SHA final de `feature/existing-voyage-parsers-a`: `2c906e7`.**
- `5480a3b`: TFP
- `4ea1776`: Navsur
- `2c906e7`: test

Por qué no se tocaron las clases Compat: no tienen código propio para crear la carga; usan el de la clase base. El test las ejercita, y con archivos reales en B la carga queda con el usuario que importó, tanto al crear un viaje nuevo como al agregar a uno existente.

Claudia integró T4 @ `2c906e7` en `release/etapa-2` (`390826a`).

## 2. QA de `fix/ui-shipment-page` @ `9294ece` — la licencia y la barra, OK; el 272,5% de la tarjeta superior no se ve en rojo

**Entorno.** Instancia B en `9294ece`, con importaciones reales:
- G2Ocean: 2.588.864 kg sobre 950 t de capacidad;
- MSC CARE I: 764.487,68 kg sobre 1.100 t.

A cada carga le asigné un capitán existente; a uno le cambié el estado de la licencia a `expired` para probar otra traducción. Al terminar, B quedó restaurada a la base limpia y el servidor detenido.

| Verificación | Resultado | Estado |
|---|---|---|
| Utilización de G2Ocean, tarjeta superior | muestra `272,5%`, sin el tope de 100 | OK |
| Utilización de G2Ocean, barra de capacidad | `272,5% utilizado` en rojo (`text-red-600`, rgb 220,38,38); la barra se llena al 100% | OK |
| Cargado / Capacidad | `2.588,9 t / 950,0 t` | OK |
| Utilización de MSC | `69,5%`, en gris (sin exceso) | OK |
| Licencia `valid` | «Vigente» | OK |
| Licencia `expired` | «Vencida» | OK |
| Los 5 valores del enum de la base (`valid`, `expired`, `suspended`, `revoked`, `pending_renewal`) | los 5 tienen traducción en la vista | OK |
| **Tarjeta superior en rojo** | **no se ve roja** | **DIFERENCIA** |

**La diferencia.**
- Dónde: `resources/views/company/shipments/show.blade.php:146`.
- Escenario: carga G2Ocean al 272,5%.
- Esperado: el número de la tarjeta superior en rojo.
- Obtenido: se ve blanco. La vista le pone la clase `text-red-200`, pero el CSS compilado no la incluye, así que el color calculado es rgb(255,255,255).
- Aun con el CSS recompilado no alcanzaría: simulé la regla y `text-red-200` (#fecaca) es un rosa muy pálido sobre el fondo índigo, que apenas se distingue del blanco (captura `9294ece-g2ocean-tarjeta-con-text-red-200-simulado.png`).
- Propuesta: un color rojo legible sobre ese fondo, o una marca aparte. Solo la barra de capacidad cumple hoy con «en rojo».

**Observaciones menores, fuera de lo pedido:**
- La tarjeta «Peso Bruto» todavía usa el formato inglés (`2,588.9t`), mientras la barra ya usa el argentino (`2.588,9 t`).
- El porcentaje de contenedores también sigue en formato inglés (`163.6% utilizado`).

Evidencia: `/var/www/html/pruebas_importacion/UI-20261117-code/9294ece-*`.

## 3. Manual de la etapa 2 — HECHO en `manual/etapa-2` (`f8fb503`), con 2 bugs encontrados al verificar

**Rama.** `manual/etapa-2`, creada desde `origin/release/etapa-2` @ `390826a` y publicada. Un commit por archivo:

| Commit | Archivo | Cambio |
|---|---|---|
| `7f4167d` | `importar-manifiestos/agregar-un-archivo-a-un-viaje-existente.md` | nuevo |
| `a22f944` | `viajes-y-cargas/eliminar-conocimientos.md` | nuevo |
| `4e02515` | `importar-manifiestos/revertir-una-importacion.md` | revertir importación, lo agregado o el viaje completo |
| `10da53d` | `viajes-y-cargas/armar-un-convoy.md` | conversión a convoy al agregar otra embarcación |
| `944e4b6` | `preguntas-frecuentes/varios-archivos-en-un-viaje.md` | ahora responde que sí |
| `37886a4` | `preguntas-frecuentes/el-viaje-ya-existe.md` | regla del archivo único y opción de agregar |
| `f8fb503` | `_sinonimos.yml` | 4 grupos nuevos: revertir, agregar, repetido/salteado, eliminar |

**Verificación en la instancia B @ `390826a`, con el navegador y datos reales.** Todos los textos de pantalla citados en los artículos se copiaron de lo que se vio. Lo que se probó:
- **Importar archivos:**
  - CUSCAR CARE I crea el viaje y el TXT de MSC se agrega al mismo viaje;
  - el mismo TXT con otro contenido saltea los 39 BL con aviso;
  - G2Ocean crea un segundo viaje y TFP de otra embarcación se le agrega, convirtiéndolo en convoy (líder `lead`, la otra `towed`).
- **Formulario de importación:**
  - con **Agregar a un viaje existente** se ocultan el número de viaje y la fecha de salida;
  - la lista de viajes muestra si es convoy y cuántas embarcaciones tiene;
  - el recuadro de convoy aparece solo con otra embarcación en un viaje de una sola embarcación, y el botón queda deshabilitado hasta elegir el líder;
  - con una transacción `RegistrarMicDta` en `success` insertada solo en B, aparece el aviso de AFIP con su motivo y el botón queda deshabilitado, también en el caso de conversión.
- **Embarcación propuesta:** probada con 4 archivos. Hay 3 casos: la propone, avisa que no está registrada o no propone nada. Los 10 formatos admiten agregar.
- **Historial:**
  - aparecen **Revertir lo agregado** y **Revertir viaje completo**, y se comprobó el texto de las 3 ventanas;
  - revertir lo agregado del TFP devuelve el viaje a `single_vessel`;
  - revertir el viaje completo revierte las 3 importaciones restantes.
- **Eliminación de conocimientos:**
  - **Eliminar** en el detalle y en el listado, con su aviso;
  - un BL verificado no muestra el botón;
  - **📋 Ver BLs** de la carga lleva al listado filtrado, donde aparece **Eliminar todos los conocimientos de esta embarcación** solo si todos se pueden eliminar;
  - borrar los 11 de una embarcación funciona.
- **Tests y control:**
  - `ManualTest`: 10 tests y 69 aserciones, pasan, con los artículos copiados sobre `390826a`;
  - los 6 artículos cargan con borradores visibles y no quedan enlaces `manual:` ni `ruta:` sin resolver;
  - el control de referencias internas no tiene coincidencias.
- B quedó restaurada a la base limpia.

**Cabecera de los artículos: `verificado.entorno: codigo`.**
- Se verificaron en local (instancia B), no en stage, y la regla del manual reserva `stage` para lo recorrido en stage.
- Consecuencia: con `codigo`, el manual los trata como borradores y no los muestra. Eso incluye los 4 artículos existentes que se actualizaron, que hoy son visibles.
- Antes de publicar la etapa 2, alguien tiene que recorrerlos en stage y pasar la cabecera a `stage`. Si no, esos 4 artículos desaparecen del manual. **Decisión tuya.**

**Bugs encontrados al verificar.** No se tocó código.
1. **Bug confirmado: revertir la importación que creó un viaje con archivos agregados borra también lo agregado y deja esas importaciones como «completadas».**
   - Escenario: viaje creado por CUSCAR y TXT de MSC agregado. En el historial, en la fila del CUSCAR, se usa **Revertir importación**, que sigue ofrecida.
   - Esperado: que se bloquee o que se pida **Revertir viaje completo**.
   - Obtenido (probado en una transacción revertida):
     - «Importación revertida exitosamente. Se eliminaron: 31 conocimientos…, 1 envíos, 1 viajes»;
     - los 39 BL y 72 ítems del MSC también desaparecen, por el borrado en cascada de la carga;
     - la importación del MSC sigue `completed`, así que su archivo queda bloqueado para reimportar sin datos detrás.
   - Dónde:
     - `import-history.blade.php` muestra **Revertir importación** aunque haya agregadas;
     - `ManifestImportRevertService::revertImport()` no verifica que el viaje tenga importaciones agregadas vigentes cuando revierte la importación que lo creó.
   - En el manual se indica usar **Revertir viaje completo** o revertir primero lo agregado.
2. **Bug confirmado, ya existente (también en `main`): el historial de importaciones no muestra los mensajes de resultado.**
   - El controlador devuelve los avisos de éxito y de error (`with('success'|'error')`), pero ni `import-history.blade.php` ni el layout los muestran.
   - Si una reversión se bloquea por AFIP, la fila queda igual, sin ningún motivo; el motivo existe y lo confirmé por consola.
   - Lo mismo pasa con la eliminación de conocimientos: el listado no muestra los mensajes.
   - En el manual se explica cómo ver el motivo de AFIP desde la pantalla de importación.

**Observación.** En esta rama la ventana de revertir todavía tiene los dos botones **Cancelar**: la corrección de `fix/ui-shipment-page` no está en `release/etapa-2`. Los artículos solo mencionan **Confirmar Reversión**.

## 4. Tests que fallaban en `main`, carpeta Parsers — CORREGIDOS en `fix/stale-parser-tests` (`eb84038`)

**Rama.** `fix/stale-parser-tests`, creada desde `origin/main` @ `3992e16` y publicada. No se tocó código de la app: los 4 tests habían quedado viejos respecto del código.

**Diagnóstico** (en la instancia B, checkout de `3992e16`: 274 tests, 1 error y 3 fallas):

| Test | Causa | Clasificación |
|---|---|---|
| `VoyageNumberCompanyScopeContractTest` | Lee `VoyageWizardController.php`, eliminado a propósito en `0749ff3` (eliminación del wizard incompleto). | test viejo |
| `G2OceanXmlParserCoreIntegrityTest::test_g2ocean_enriches_unique_unidentified_client…` | Desde `a2683a3`, completar la ficha sin identificación fiscal se hace en el trait compartido `ResolvesExistingClient` (regla 3). El test buscaba el mensaje y la asignación viejos dentro del parser. | test viejo |
| `TfpTextParserCoreIntegrityTest::test_tfp_enriches_unique_unidentified_client…` | Lo mismo, desde `d7ddb26`. | test viejo |
| `LoginXmlParserStage1IntegrityTest::test_duplicate_warning_identifies_client_name_and_record_id` | Desde `196a287`, Login usa el trait: las advertencias con nombre y número de ficha salen de `ResolvesExistingClient` y el parser las agrega con `addWarningOnce($warning)`. | test viejo |

**Corrección: un commit por archivo, solo en tests.** Se conserva la intención de cada test y se apunta al código vigente.
- `fd92e54`: el contrato del número de viaje revisa solo `VoyageController`.
- `9e97994` y `19b8bb3`: G2Ocean y TFP usan `ResolvesExistingClient` y llaman a `resolveExistingClient(`. En el trait se verifica que se complete `tax_id` y que se registre el mensaje de ficha completada.
- `eb84038`: Login llama a `resolveExistingClient(` y agrega las advertencias con `addWarningOnce($warning)`. En el trait se verifica que las advertencias incluyan el nombre `({$name})` y la ficha `#{$client->id}`.

**Pruebas.**
- `php -l`: limpio.
- Carpeta `tests/Unit/Services/Parsers` sobre `3992e16`: 274 tests y 809 aserciones, cero fallas.
- B quedó restaurada a la base limpia.

**Hallazgo, sin tocar.** Código muerto en los parsers:
- `G2OceanXmlParser`: `findClientByNameIdentity()` (línea 1362) y `findUnidentifiedClientByNameIdentity()` (línea 1440);
- `TfpTextParser`: `findClientByNameIdentity()` (línea 1272) y `findUnidentifiedClientByNameIdentity()` (línea 1355).

Ninguno de estos métodos se llama desde `app/` ni desde `tests/`, porque los reemplazó el trait. Se pueden borrar en una tarea aparte.

## Cierre de la noche

| # | Tarea | Estado | SHA |
|---|---|---|---|
| 1 | Usuario creador en las cargas de TFP y Navsur (T4) | hecho; integrado por Claudia en `release/etapa-2` (`390826a`) | `2c906e7` |
| 2 | QA de la ficha de carga @ `9294ece` | licencia y barra OK; la tarjeta del 272,5% no se ve en rojo | — |
| 3 | Manual de la etapa 2 | hecho en `manual/etapa-2`; quedan 2 bugs y una decisión sobre `verificado.entorno` | `f8fb503` |
| 4 | Tests viejos de la carpeta Parsers en `main` | hecho en `fix/stale-parser-tests` | `eb84038` |

Nada se mergeó a `main` ni se publicó en servidores. La instancia B quedó en la base limpia y los servidores locales, detenidos.
