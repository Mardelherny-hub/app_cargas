# QA cruzado independiente — trazabilidad aduanera B · 27/11/2026

**Objeto:** `fix/trazabilidad-aduana-b @ d96a04285bc9c7f324823ae6342f669aa89c4381`, contrastado con base `9863e6f9`; tres muestras **originales** `CAREI-2605ATXT..txt`, `CONAYI-2605ATXT..txt` y `CAREI-2506AHL.1`. **Dictamen sobre A con copia actual de Stage: CON OBSERVACIONES; NO emitir PASS final** por las diferencias puntuales indicadas abajo. No enviar a Aduana.

## Preparación y control de aislamiento

- Repositorio original no modificado. Se extrajeron **ambos SHAs mediante `git archive`** en directorios privados fuera del repositorio (`/home/victor/qa_trazabilidad_20261127` y `_base`). Composer autoload independiente, PHP 8.5.4.
- Ensayos reales contra **A `app_cargas_pruebas`** con usuario existente id 20 de Nabsa 1008, sin cambio de contraseña, con bloqueo de solicitudes HTTP externas. Cada muestra se procesó dentro de una **transacción exterior** de MySQL, con rollback y comprobación SHA-256 lógica de las **57 tablas antes/después**. Ambas corridas: `preserved=true`; **no se alteró A**, B, Stage o producción. La migración de tipo ISO `45R5` se ejecutó solo como inserción DML en la transacción candidata y se revirtió junto con el ensayo.
- Las 3 muestras pasaron en base y candidata, sin error de importación; se analizaron BL, ítems, partes, contenedores, `warnings` y el método **real** `ProcessManifestImportJob::warnMicDtaFieldLengths()` mediante reflexión, sin WSAA.
- `php -l`: los cuatro PHP modificados del SHA `d96a0428` sin error; **71 tests y 163 aserciones PASS**, cuatro avisos de deprecación del entorno. Un primer ensayo tuvo un error de *autoload* al apuntar al worktree de `vendor`; se descartó, se preparó `vendor` privado con `composer dump-autoload --no-scripts` y se repitió correctamente.

## Matriz por muestra

| Muestra real | BL (base → candidata) | Ítems (base → candidata) | RUC | País / ISO / advertencias | Estado |
|---|---:|---:|---|---|---|
| CARE I TXT | 39 → 39 | 72 → 72 | BL `MEDUXX077639`: `800133668` en consignatario/notificatario, antes sin tax_id | Advertencias de longitud: 7 → 17 (7 marca, 5 `descMercaderia`, 5 `obsDeclaAduInter`) | Importación OK |
| CONAY I TXT | 55 → 55 | 160 → 160 | No se alteran códigos fiscales fuera del alcance | Advertencias: 11 → 34 (11 marca, 17 descripción, 2 observaciones, **4** de razón social sobre **2 BL**) | Importación OK; diferencia de cantidad de avisos |
| CARE I CUSCAR | 31 → 31 | 91 → 91 | `EASTSUN PARAGUAY S.A.` BL `HLCUSHA2601HAXN8`: `801165989` en consignatario/notificatario, antes sin tax_id | **5** avisos «País no identificado»; `AGILEWAY` en AR; dos contenedores `45R5` de la fuente quedan como `40RH` preexistente | Importación OK; dos observaciones |
| **Total** | **125 → 125** | **323 → 323** | Dos RUC esperados comprobados | Ver detalle abajo | **CON OBSERVACIONES** |

### Integridad de los datos

- Las **125 claves de BL coinciden** entre base y candidata, y sus campos *no relativos a referencias de cliente* permanecen iguales. Los **323 ítems** son idénticos campo por campo, incluidos texto y cantidades. **Cero diferencias semánticas inesperadas** en BL/ítems fuera de las referencias a las fichas fiscales.
- Los IDs físicos de algunas fichas nuevas cambian entre corridas por las secuencias `AUTO_INCREMENT` de MySQL, incluso con rollback; no deben compararse esos IDs como si fueran identidad comercial. El cruce por nombre legal/código fiscal localiza los dos RUC previstos; la copia de Stage también tiene algunas fichas reutilizadas.
- No hay alteraciones a contenedores respecto de la base en esta copia de Stage: se reutilizan los existentes, por decisión expresa de `CmspEdiParser::createContainer()` de no sobrescribir datos físicos globales.

### País: cinco advertencias correctas, pero falta la segunda ficha HK

El archivo CUSCAR declara **AGILEWAY TECH LIMITED — «KWAI CHUNG HK»** y **UPL LIMITED — «HONG KONG»**. Con la copia real de Stage en A:

- UPL LIMITED está vinculada a una ficha **HK** (reutilizada en varios BL).
- AGILEWAY TECH LIMITED, BL `HLCUNG1260496485`, **sigue vinculada a la ficha existente #1985 registrada en Argentina**, no HK. El nuevo parser **detecta el conflicto y advierte** «se reutilizó el cliente #1985, registrado con otro país. La ficha no se modificó». Es coherente con la regla de no modificar fichas reutilizadas, pero **no satisface literalmente «dos partes con Hong Kong en sus fichas»** en esta base.
- Se reprodujeron **exactamente cinco** advertencias de país indeterminado, con BL y rol: SHENZHEN CHINA PROGRESS, ASIA SHIPPING INTERNATIONAL, OVERSEAS RICHEST INTERNATIONAL y BLU LOGISTICS (CHINA) CO., LTD. en dos BL. Se conserva el texto completo con contexto.

### Contenedores ISO 45R5: contrato y resultado real no coinciden con lo pedido

Fuente: `CAREI-2506AHL.1`, EQD de `HLBU9748451` y `HLBU9819750` declara **45R5**. La migración y el test aislado nuevo admiten 45R5 y el test de `SimpleXmlGenerator::writeMicDtaContainer()` con un objeto nuevo sí pasa. **Pero** en A ambos números ya existen físicamente con tipo `40RH` (`iso_code=45R1`). Durante la importación real, el parser deliberadamente **reutiliza el contenedor** y no sobrescribe su tipo. Invocando el escritor local con esos contenedores reales, sin WSAA ni transmisiones, se obtiene:

| Contenedor | EQD de la fuente | Tipo preexistente en A | XML local `codMedida` | Literal pedido |
|---|---|---|---|---|
| `HLBU9748451` | `45R5` | `40RH` | **45R1** | **NO CUMPLE** |
| `HLBU9819750` | `45R5` | `40RH` | **45R1** | **NO CUMPLE** |

Esto no demuestra que falle la creación de un contenedor **nuevo** (el test unitario la cubre); demuestra que el dato de la fuente **no prevalece sobre un contenedor histórico existente**. Se requiere decidir si conservar el catálogo físico previo, registrar advertencia de conflicto o adoptar otra política; **no corregí ni muté los contenedores**.

### Longitud: valores íntegros, conteo diferente de lo solicitado

| Categoría | Base | SHA `d96a0428` en A | Solicitado | Dictamen |
|---|---:|---:|---:|---|
| Marca `marcaNro` | 29 | 29 | Conservación | OK, marcas sin recorte |
| Descripción `descMercaderia` | 0 | **22** | 22 | **OK** |
| Observaciones `obsDeclaAduInter` | 0 | **8** | 8 | **OK** |
| Razón social | 0 | **4 avisos, 2 BL** | 2 avisos | **DIFERENCIA EN A** |

Los cuatro avisos de razón social en A corresponden a consignatario y notificatario de **dos BL** de CONAY I: `MEDUHW767998` (63 caracteres) y `MEDUWU746584` (52 caracteres). El parte del desarrollador sobre SQLite aislada registra **dos** avisos para el segundo BL; la copia de Stage contiene adicionalmente una ficha larga vinculada al primero. **El código no recorta** ninguno de los textos. Las 22 descripciones y 8 observaciones permanecieron iguales antes y después.

### Línea de base y hallazgos de auditoría

- Parte del desarrollador: `docs/partes/2026-11-27-trazabilidad-aduana-b.md`, SHA `d96a0428`, informa resultado conforme en **base SQLite limpia**; este QA usa una **copia de Stage** con fichas y contenedores históricos, de ahí las diferencias verificables. No sustituir una base por la otra ni borrar datos para hacer pasar el resultado.
- Mientras se realizaba este QA, `origin/fix/trazabilidad-aduana-b` avanzó a **`c8692bb7`** (extracción de identificación fiscal alfanumérica en domicilios). **Este informe evalúa exactamente `d96a0428`**; el commit posterior requiere evaluación independiente antes de aprobar el HEAD más reciente.
- **Dictamen final de este SHA para el escenario exigido:** cuatro de los cinco controles de alcance de datos se cubren total o parcialmente; permanecen **ABIERTOS** el segundo país HK en una ficha existente, los dos `codMedida=45R5` con contenedores previamente cargados y la discrepancia literal de dos frente a cuatro advertencias de razón social. **No se propone merge ni despliegue en base a esta aprobación parcial.**

## Evidencia privada, fuera del repositorio

- `/home/victor/qa_trazabilidad_20261127/base-reales.json` — SHA-256 `756dbd36214840adc7d7576774deb2dffcd6d92ab7229f49b0fe352c73e79fcd`.
- `/home/victor/qa_trazabilidad_20261127/candidate-reales.json` — SHA-256 `a3bf51b3a7f231d801ca0ec2c0532d6a3806f4df8e5a61a99ef38ec25e53f1d6`.
- `/home/victor/qa_trazabilidad_20261127/comparacion-a-9863-vs-d96.json` — SHA-256 `256f3718655701c1bf41cf6df8e523d9cfaa17b034e8b9bed94516d79737a772`.

Estos JSON contienen datos de clientes y BL de la copia de Stage, **no se suben a GitHub**; las cifras y casos concretos aquí consignados son los respaldos auditables. No se publican credenciales ni XML con información privada.
