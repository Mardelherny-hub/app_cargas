# AGENTS.md — app_cargas

Fuente única de reglas para los agentes que trabajan en este repo (Codex, Claude Code y cualquier otro). `CLAUDE.md` importa este archivo. Cualquier cambio de reglas se hace aquí.

## 1. Contexto del proyecto

- Sistema Laravel/PHP de cargas marítimas para la Hidrovía Paraguay-Paraná: manifiestos, conocimientos (BL) y presentación aduanera ante AFIP/ARCA (Argentina) y DNA (Paraguay).
- Entorno local: `/var/www/html/app_cargas`.
- Producción: `/var/www/cargas` en `cargas.red-byte.com`.
- Repositorio: `Mardelherny-hub/app_cargas`, rama `main`.
- Commits: Conventional Commits en inglés, con scope. Ejemplos: `fix(auth): ...`, `fix(security): ...`, `fix(ui): ...`.

## 2. Reglas de trabajo

### Investigar primero, codear después
- Antes de escribir código, verificar en el código real:
  - el modelo, sus `$fillable` y sus casts;
  - las relaciones;
  - la migración o estructura de la tabla.
- **Nunca** asumir nombres de campos, métodos, variables, relaciones ni reglas de negocio. Si algo no se puede verificar, preguntar.
- No escribir código "que parezca lógico". Si no existe en la app, no se usa.
- Hacer un diagnóstico de solo lectura antes de proponer cualquier cambio.

### Un cambio por vez
- Proponer el cambio indicando archivo, ubicación exacta y motivo, y esperar confirmación antes de aplicarlo.
- Trabajar de a un archivo por vez y esperar confirmación antes de pasar al siguiente.
- Un commit corresponde a una sola funcionalidad o fix (commits atómicos).
- Antes de cada commit:
  1. Correr `php -l` sobre cada archivo PHP modificado.
  2. Hacer `git add` solo de los archivos del cambio.
  3. Correr `git diff --cached` y mostrar el diff para revisión.
- Las soluciones deben ser simples. Nada de refactors no pedidos.

### Repo compartido
- Puede haber otros frentes de trabajo modificando este repo local al mismo tiempo.
- Al empezar una tarea, correr `git status` e identificar los cambios locales existentes. Esos cambios se **preservan**: no se leen como propios, no se stagean, no se revierten y no se commitean.
- **Prohibido** usar `git add .`, `git add -A` y `git commit -a`. Siempre se stagean archivos explícitos.
- Los commits se hacen SIEMPRE con pathspec (`git commit -m '...' -- <archivos>`), porque el índice es compartido entre agentes.

### Regex y parseo
- Prototipar las regex contra muestras reales de archivos (por ejemplo, con un one-liner de Python) antes de escribirlas en PHP.

## 3. Prohibiciones

- ❌ No operar sobre el servidor ni la base de datos de producción, salvo que la tarea defina expresamente el entorno PRODUCCIÓN y el alcance. Fuera de esa definición explícita, ningún acceso a producción está permitido.
- ❌ No hacer `git push`, merge ni deploy sin autorización expresa en la tarea. Nunca se deducen de una orden de investigar o corregir.
- ❌ No correr migraciones destructivas, `migrate:fresh`, `db:wipe` ni seeders sobre datos existentes sin OK explícito.
- ❌ No borrar archivos sin confirmar antes que son código muerto: sin referencias vivas, fuera del factory y sin tests.
- ❌ Los parsers **nunca** crean puertos.
- ❌ No ejecutar pruebas que envíen información a Aduana (AFIP/ARCA, DNA) ni que modifiquen datos compartidos sin que la tarea defina expresamente el entorno y el alcance.
- ❌ No agregar rutas closure públicas que ejecuten comandos Artisan o escriban datos. En octubre de 2026 se eliminaron `/clear`, `/symlink` y `diagnostic.webservices` por este motivo.

Las operaciones de escritura sobre datos en producción siguen este protocolo:
1. `mysqldump`.
2. Verificar que la salida diga "Dump completed".
3. Script Tinker transaccional con commit manual.
4. Verificación de solo lectura.

## 4. Reglas de dominio consolidadas

### Parsers (`app/Services/Parsers/`)
- Parsers existentes: `CmspEdi`, `GuaranExcel`, `LoginXml`, `G2OceanXmlParser`, `KlineData`, `NavsurText`, `ParanaExcel` y `TfpText`. Cada uno tiene su versión `*Compat`, y todos se crean desde `ManifestParserFactory`.
- Los traits viven en `Concerns/`:
  - `EnsuresUniqueVoyageNumber`
  - `ExtractsEmbeddedTaxId`
  - `ResolvesClientAddresses`
  - `ResolvesPorts`
  - `ResolvesVoyageCargoType`

  Los dos últimos no están documentados todavía; antes de usarlos, leer su código.
- **Criterio de cierre de un parser** (vigente desde el 15/05/2026). Un parser se cierra solo si cumple las tres condiciones:
  1. Valida los campos críticos antes de tocar la base de datos y no expone errores SQL crudos al usuario.
  2. Tolera variantes de formato: fechas como texto o como serial de Excel, encoding ISO-8859-1, campos vacíos y espacios extra.
  3. Fue probado con al menos 2 archivos reales distintos. Si hay una sola muestra, se documenta como "pendiente validar variantes".
- Puertos: solo búsqueda estricta. Si el puerto es desconocido, devolver un error claro con sugerencias calculadas con `similar_text`.
- **El número de viaje es único por empresa + embarcación + número**: la terna `company_id + lead_vessel_id + voyage_number`. Distintas empresas pueden usar el mismo número, y una empresa puede repetirlo en embarcaciones distintas. La migración `2026_09_25_173000_scope_voyage_number_by_company_and_vessel.php` define el índice compuesto con el nombre `voyages_voyage_number_unique`; ese nombre se conserva, pero no implica unicidad global. `guardVoyageNumberIsFree()` (trait `EnsuresUniqueVoyageNumber`) valida esa misma terna. Los parsers que capturan `voyages_voyage_number_unique` traducen la colisión a un mensaje amable y registran la importación fallida.
- Los inserts masivos usan `Model::withoutEvents()`. Por eso hay que setear `verified_at` explícitamente en `Client::create`.
- `normalizeDecimal()` maneja separadores decimales mezclados dentro de un mismo archivo.
- Pesos VGM: usar `MEA+AAE+G+KGM` cuando el dato es correcto. Recurrir a VGM solo si el valor G se repite idéntico en todos los ítems del BL.

### Clientes y direcciones
- `ExtractsEmbeddedTaxId` resuelve el CUIT, RUC, CNPJ o NIT cuando viene incrustado en el nombre o la dirección. Orden de prioridad: campo estructurado, después nombre, después dirección.
- `ResolvesClientAddresses`:
  - `persistClientAddress` agrega la dirección a la ficha del cliente si falta.
  - `resolveSpecificAddress` guarda la dirección propia del BL si difiere de la ficha.
- Las direcciones viven en `client_contact_data`; la principal es la que tiene `is_primary=1`. Datos que **no existen**:
  - la columna `address_line_1` en `clients`;
  - la FK `primary_contact_data_id` en producción.
- El país se infiere por la longitud del tax_id:
  - 11 dígitos: Argentina (country id 11, tipo de documento CUIT 1).
  - 7 a 9 dígitos: Paraguay (country id 174, tipo de documento RUC 3).

### Webservices
- `app/Services/Simple/` es el módulo vigente para MIC/DTA, Anticipada, Desconsolidado, Transbordo Hidrovía y DNA Paraguay.
- `app/Services/Webservice/` es el módulo anterior. Todavía tiene consumidores (comandos de consola, servicios compartidos), así que **no se borra** sin una tarea específica.
- Desconsolidados: las rutas `company.simple.desconsolidado.show` y `.send` resuelven a `ArgentinaDeconsolidatedController`. Hay una declaración duplicada que es código muerto pendiente de limpieza.
- AFIP MIC/DTA: AFIP usa códigos de lugar propios, distintos del UN/LOCODE. Para el puerto de Buenos Aires, `codLugOper` = `10073` (Terminal Sur).
- DNA Paraguay:
  - Secuencia: XFFM → adjuntar documentos → XFBL → XFBT → XFCT.
  - `indTransaccion` tiene como máximo 14 caracteres y lleva la fecha en formato YYYYMMDD.
- Entorno DNA: `resolveDnaEnvironment()` (`app/Services/Simple/ParaguayDnaService.php`) es la fuente canónica y delega en `WebserviceEnvironment::resolve($company)`. Las rutas de certificado se resuelven por entorno, sin fallback cruzado.

## 5. Equipo y dinámica

### Organización
- **Claudia** (chat): dirige el trabajo, define prioridades y asigna frentes.
  - **Claude Code (Code)**: equipo de Claudia; desarrolla código.
- **Charly** (chat): coordina su equipo, analiza hallazgos, prepara tareas y consolida resultados.
  - **Work**: investigaciones, diagnósticos complejos y validaciones.
  - **Codex local**: implementaciones y pruebas locales asignadas.

### Cómo se recibe una tarea
Cada tarea llega con:
- responsable;
- objetivo concreto;
- alcance permitido (archivos o áreas, y entorno);
- restricciones;
- criterio de aceptación.

Si falta alguno de esos datos, se pregunta antes de empezar. Ningún agente toma una tarea que no le fue asignada explícitamente.

### Dinámica
1. Registrar el comportamiento observado y el esperado.
2. Reproducir el problema y buscar evidencia (código, logs, datos) antes de corregir.
3. Clasificar cada hallazgo como **bug confirmado**, **hipótesis** o **mejora**.
4. No modificar archivos asignados a otro integrante ni cambios locales ajenos.

### Devolución obligatoria (breve y basada en evidencia)
- Causa encontrada.
- Archivos modificados.
- Pruebas ejecutadas y sus resultados.
- Limitaciones y lo que no se pudo verificar.

### Estados de una implementación
- Toda implementación queda **pendiente de validación** hasta comprobar el comportamiento.
- Se distinguen tres niveles: validación local, validación en Stage (solo si la tarea define ese entorno) y publicación en producción.
- Commit, push, integración y deploy se realizan solo si la tarea lo autoriza expresamente.
