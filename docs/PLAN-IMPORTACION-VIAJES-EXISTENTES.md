# Plan de trabajo — Importación a viajes existentes y formatos nuevos

Fecha: 16/11/2026 · Dirección: Claudia · Presupuesto aceptado y pagado por el cliente el 16/11/2026.

Documento interno del equipo. `docs/` no viaja en el paquete de producción.

## 1. Qué hay que entregar

**Etapa 1. Dos formatos nuevos** (plazo: 5 días hábiles desde la aprobación del alcance)
- TXT de MSC que empieza con `Vessel Name`. Muestras: CARE I, CONAY I y AGEDELTA (la misma estructura).
- Excel de Don Toyo. Muestra: DON TOYO V.0626S. Los recargos no se importan.

**Etapa 2. Agregar archivos a viajes existentes** (plazo: 10 días hábiles desde que el cliente acepta la etapa 1)
1. Al importar, se elige entre **crear viaje nuevo** o **agregar a un viaje existente**, sea manual o importado, con cualquiera de los 10 formatos.
2. El sistema **propone la embarcación** según el archivo y el usuario la confirma o la cambia.
3. Si se agrega otra embarcación a un viaje simple, se ofrece **convertirlo en convoy** y se elige el líder. El líder puede ir sin carga y sin archivo (por ejemplo, el remolcador Observador).
4. Si la embarcación ya tiene carga en el convoy, se suman los conocimientos. Si no tiene, se le crea su carga.
5. Los **conocimientos repetidos** se saltean y se informan al terminar.
6. **Revertir** solo lo agregado o el viaje completo.
7. **Eliminar un conocimiento en un paso**, con sus ítems y contenedores, de a uno o todos los de una embarcación.
8. **Regla de bloqueo:** nada de lo anterior se permite si la embarcación tiene títulos o MIC/DTA vigentes en AFIP.

**No incluye:** envíos automáticos a Aduana, cambios en DNA ni en Anticipada, correcciones después de registrar el convoy (para eso existe «Rectificar convoy») ni modificar los datos generales del viaje desde el archivo. Detalle completo en el documento de alcance que tiene Víctor.

**Criterio de aceptación del cliente**
- Etapa 1: las muestras de los formatos nuevos se importan correctamente.
- Etapa 2: se arma el convoy Observador + CARE I + CONAY I agregando sus archivos, incluidos los dos de CARE I (CUSCAR y TXT), y se revierte un agregado sin afectar el resto del viaje.

## 2. Lo que dice hoy el código (verificado en `main` 74f652a)

- Los 8 parsers crean el viaje dentro de un método propio, `createVoyage` o `findOrCreateVoyage` según el parser, llaman a `guardVoyageNumberIsFree()` y crean la carga con `Shipment::create`.
- `ProcessManifestImportJob::handle()` ya le pasa opciones a `parse($fullPath, [...])`: `vessel_id`, `departure_date`, `voyage_number`, `loading_date`, `discharge_date` y `operation_type`. **Por ahí entra el viaje destino.**
- La reversión ya existe en `ManifestImportController::revert()`: borra lo registrado por `recordCreatedObjects` o `recordExplicitlyCreatedObjects`, y solo cuando el viaje está en `planning`.
- `voyages.voyage_type` es un enum: `single_vessel`, `convoy`, `fleet`.
- Los conocimientos se identifican por `bill_number`, y también existen `master_bill_number` y `house_bill_number`.
- Hay control de archivo repetido por hash (`ManifestImport::isFileAlreadyImported`) en Navsur, TFP, CMSP y K-Line.

## 3. Contrato común (lo publica el equipo Claudia el día 1)

Todo lo que sigue es una **propuesta de diseño**. Code la contrasta con el código real antes de escribirla. Si algo no existe como se describe, lo informa y se ajusta acá.

- **Opción nueva de `parse()`:** `target_voyage_id`, junto con `target_vessel_id` (la embarcación confirmada). Si viene `null`, el parser hace exactamente lo de hoy. **Cero cambios de comportamiento en el flujo actual.**
- **Trait nuevo en `Concerns/`** con tres responsabilidades:
  - Resolver el viaje destino: misma empresa, estado `planning` y regla de bloqueo (sección 4, T0).
  - Resolver la carga destino: la carga existente de esa embarcación en el viaje, o una carga nueva con la siguiente `sequence_in_voyage`, respetando cómo la app modela hoy los convoyes (`vessel_role`, `is_lead_vessel`).
  - Saltear los conocimientos cuyo `bill_number` ya existe en el viaje y devolverlos como advertencia en el resultado.
- **En cada parser**, el cambio es mínimo:
  - en el método que crea el viaje: si hay destino, se devuelve ese viaje, sin `guardVoyageNumberIsFree()` y sin tocar sus datos;
  - en el que crea la carga: se usa la carga destino;
  - se agrega `detectVesselName(string $filePath): ?string`, para que la pantalla proponga la embarcación;
  - **el registro de objetos creados nunca incluye el viaje destino ni una carga que ya existía.** Así «revertir lo agregado» funciona con la reversión actual.
- **Test de regresión** del trait, con el mismo esquema aislado en sqlite que usa `tests/Feature/VoyageTransferTest.php`.

## 4. Equipos y frentes

### Equipo Claudia: Claudia dirige, Code desarrolla (puede trabajar en paralelo)

| Tarea | Qué | Rama | Archivos propios |
|---|---|---|---|
| **T1** | Contrato y núcleo: trait, opción en el job, pantalla de importación (crear o agregar, viaje destino, embarcación propuesta), conversión a convoy, informe de repetidos, revertir lo agregado o el viaje completo | `feature/import-to-existing-voyage` | trait nuevo, `ProcessManifestImportJob`, `ManifestImportController`, `company/manifests/import*.blade.php`, test del trait |
| **T4** | Adaptar al contrato los parsers de CMSP (CUSCAR), K-Line, Navsur y TFP | `feature/existing-voyage-parsers-a` | `CmspEdiParser`, `KlineDataParser`, `NavsurTextParser`, `TfpTextParser` y sus `*Compat` |

### Equipo Charly: Charly coordina, Codex implementa, Work investiga y valida

| Tarea | Qué | Rama | Archivos propios |
|---|---|---|---|
| **T0** (Work) | Investigación: cómo saber si una embarcación tiene títulos o MIC/DTA **vigentes** en AFIP (tablas, estados, anulaciones). Entrega: la consulta exacta y los casos borde. Solo lectura. | sin rama | ninguno |
| **T2** (Codex) | Parser TXT de MSC «Vessel Name» | `feature/parser-msc-vessel-name` | parser nuevo, su `*Compat`, alta en `ManifestParserFactory` |
| **T3** (Codex) | Parser Excel Don Toyo, sin recargos | `feature/parser-don-toyo` | parser nuevo, su `*Compat`, alta en `ManifestParserFactory` |
| **T5** (Codex) | Adaptar al contrato los parsers de Login, G2Ocean, Guaran, Paraná, MSC y Don Toyo | `feature/existing-voyage-parsers-b` | `LoginXmlParser`, `G2OceanXmlParser`, `GuaranExcelParser`, `ParanaExcelParser`, los dos nuevos y sus `*Compat` |
| **T6** (Codex) | Eliminar un conocimiento en un paso, con sus ítems y contenedores, de a uno o todos los de una embarcación, con la regla de bloqueo | `feature/bl-delete-one-step` | `BillOfLadingController` y sus vistas y rutas |

**Por qué este reparto:**
- **Archivos sin superposición:** cada archivo tiene un solo dueño y nadie toca lo de otro.
- **El único archivo compartido es `ManifestParserFactory`:** lo tocan T2 y T3, del mismo equipo, en secuencia.
- **El equipo Claudia se queda con el núcleo y con CMSP:** el CUSCAR de CARE I es parte de la prueba de aceptación.
- **Peso de los parsers:** el equipo Charly adapta los más livianos porque además lleva la etapa 1.

### QA cruzado

| Lo hace | Revisa |
|---|---|
| Equipo Charly (Work) | T1 y T4 |
| Equipo Claudia (Code) | T2, T3, T5 y T6 |

Las pruebas de importación del QA cruzado se corren en el servidor de pruebas de asus02 (`docs/SERVIDOR-PRUEBAS-IMPORTACION.md`), comparando contra la línea de base de `main`. Las observaciones se corrigen con un commit nuevo en la misma rama. **La prueba integral final** (convoy Observador + CARE I + CONAY I, reversión de un agregado y repetición de una importación) la ejecuta Work, la revisa Code y se hace en stage con «Empresa QA Test», previo OK de Víctor para el deploy.

## 5. Secuencia y fechas internas

Las fechas internas dejan margen sobre los plazos comprometidos con el cliente.

| Día hábil | Equipo Claudia | Equipo Charly |
|---|---|---|
| 1 | T1: contrato y trait publicados en su rama | T0 (Work) y arranque de T2 (Codex) |
| 2 | T1: job, controlador y pantalla | T2 terminado, arranca T3 |
| 3 | QA de T2 y T3 · T1: convoy y repetidos | T3 terminado · QA de T1 (contrato) |
| 4 | **Etapa 1:** merge y deploy a stage (con OK de Víctor) | Validación de las muestras en stage |
| 5 | **Etapa 1 en producción** (con OK de Víctor) · T4 | T5 |
| 6–8 | T4 terminado · QA de T5 y T6 | T5 y T6 · QA de T4 |
| 9 | Correcciones de QA | Prueba integral en stage |
| 10 | Etapa 2 lista para el cliente (con OK de Víctor) | |

**Dependencias:**
- T4 y T5 arrancan cuando el contrato de T1 está publicado. Los parsers nuevos nacen creando viajes como los demás y se adaptan en T5.
- La regla de bloqueo de T1 y T6 usa la consulta que entrega T0.

## 6. Reglas que aplican (de AGENTS.md)

- Commit y push por archivo en la rama asignada. Merge a `main` y deploys, solo con OK de Víctor.
- `php -l` antes de cada commit, commits con pathspec y nunca `git add .`.
- Los parsers nunca crean puertos.
- Ningún parser cierra sin el criterio de cierre. **Don Toyo tiene una sola muestra:** se documenta como «pendiente validar variantes».
- **Muestras del cliente:** el repo es público, así que los archivos de muestra **no se commitean**. Víctor los deja en una carpeta local fuera del repo, `/var/www/html/muestras_importacion/`, y los tests usan fragmentos anonimizados.
- Nada de referencias internas en código, comentarios ni mensajes visibles.
- Ninguna prueba envía datos a la Aduana.

## 7. Tarjetas de tarea (para pasar a cada agente)

Cada tarea arranca **solo con la orden explícita** de quien dirige su equipo.

**T0 · Work · Investigación de la regla de bloqueo**
- Objetivo: definir cómo se determina que una embarcación de un viaje tiene títulos o MIC/DTA vigentes en AFIP, es decir, enviados y no anulados.
- Alcance: lectura de código (`app/Services/Simple/`, modelos y tablas de transacciones de webservices) y de la base local o stage, solo lectura.
- Restricciones: sin escritura y sin producción.
- Aceptación: consulta o método propuesto, con los estados que cuentan como vigentes y anulados, y tres casos verificados (nunca enviado, enviado vigente, enviado y anulado).

**T1 · Code · Contrato y núcleo**
- Objetivo: secciones 3 y 4 (T1).
- Alcance: rama `feature/import-to-existing-voyage` y los archivos listados. Entorno local.
- Restricciones: con `target_voyage_id` en `null`, el flujo actual no cambia. No toca parsers.
- Aceptación: test del trait en verde. Con un parser adaptado de prueba (Navsur, coordinado con T4), un archivo se agrega a un viaje existente y su reversión deja el viaje como estaba.

**T2 · Codex · Parser MSC «Vessel Name»**
- Objetivo: importar las tres muestras creando el viaje como los demás parsers.
- Alcance: rama `feature/parser-msc-vessel-name`, entorno local.
- Restricciones: prototipar la regex con las muestras antes de escribir PHP. Criterio de cierre de parsers.
- Aceptación: las 3 muestras se importan con todos los conocimientos, ítems y contenedores, sin errores SQL crudos.

**T3 · Codex · Parser Don Toyo**
- Igual que T2, en la rama `feature/parser-don-toyo`, con la muestra DON TOYO V.0626S y sin recargos.

**T4 · Code y T5 · Codex · Adaptación de parsers**
- Objetivo: aplicar el contrato de la sección 3 a los parsers asignados.
- Restricciones: cambio mínimo, sin refactors y sin tocar parsers del otro equipo.
- Aceptación, para cada parser:
  - sin destino, la importación queda idéntica a la actual;
  - con destino, se agrega al viaje y saltea los repetidos;
  - `detectVesselName` devuelve la embarcación de la muestra;
  - revertir quita solo lo agregado.

**T6 · Codex · Eliminar conocimientos en un paso**
- Objetivo: punto 7 de la sección 1, con la regla de bloqueo de T0.
- Alcance: rama `feature/bl-delete-one-step`, entorno local.
- Aceptación: al eliminar un conocimiento no quedan ítems, contenedores ni filas de pivote huérfanas. Eliminar todos los de una embarcación funciona. Con la embarcación bloqueada, la acción no se ofrece.
