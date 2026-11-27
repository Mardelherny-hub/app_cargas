# QA consolidado — MSC y trazabilidad aduanera · 27/11/2026

**Rama exacta:** `fix/msc-contenedor-ncm`.
**SHA remoto evaluado:** `7ac3737979a36eefa4dcb4b0695f240a2e68e25f`.
Incluye `188361f300aeda7aeb458ce369878ad25a1f69c8`, `47d9bbe4` y `7ac37379`. Un único dictamen sobre este SHA: **SIN APROBACIÓN GLOBAL**. Los dos commits adicionales cumplen sus verificaciones específicas, pero siguen abiertos los puntos de House histórico y persistencia de tres decimales. No autoriza integración ni deploy.

Se consultaron origin y los partes antes del ensayo; no había QA publicado para este SHA. Leídos diff, parsers, trait NCM, modelos/relaciones, migración y escritores antes de invocarlos. No se sustituyó esta rama por trazabilidad B.

## Matriz consolidada

| Criterio | Resultado real en A sobre 7ac37379 | Dictamen |
|---|---|---|
| MSC, nombre del cargador del archivo en XML | CARE I 39/39; CONAY I 55/55: **94/94** nodos nomRazSoc coinciden | FAVORABLE |
| Domicilios importados activos | Todos los contactos específicos creados están activos; no hay use_specific_data=false. Se conservan las fichas maestras | FAVORABLE para activación |
| CUSCAR sin H/P, House 91/91 | **91/91 siguen P**, tanto guardados como en el fragmento XML Contenedor | NO CUMPLE con los históricos de A |
| TFP con estado físico V, House | Los **50 V reales** de ASUNCIÓN B mapean a físico V / documental H; los 69 H siguen H. La importación completa vuelve a fallar por cantidad de bultos ausente; 9 BL/9 ítems de la muestra inventariada P importan y conservan los 20 P explícitos | Mapeo FAVORABLE; recorrido completo con V LIMITADO |
| Peso con tres decimales, 72/72 | A sigue decimal(12,2): **69/72** exactos; tres pesos redondeados | PENDIENTE migración y verificación en MySQL |
| Don Toyo, nombre del cargador del archivo en XML | **75/75** nomRazSoc coinciden; específicos activos solo de nombre, domicilio real de la ficha conservado | FAVORABLE |
| Embalaje MSC | AGEDELTA **131/131**, CARE I **72/72**, CONAY I **160/160**: **363/363 CONTENEDOR** | FAVORABLE |
| NCM MSC vacío | **0/363**, antes 2/363 | FAVORABLE |
| MEDUY9567239 | Línea 1: vacío → **852872** | FAVORABLE |
| MEDUYF794224 | Línea 1: vacío → **721720**; línea 2 ya era 721720 y permanece igual | FAVORABLE |
| Ningún otro NCM cambia | **361/361 restantes iguales**; descripciones de los 363 ítems idénticas | FAVORABLE |
| RegistrarMicDta frente al estado anterior a 097d9a00 | Tres escritores de carga producen fragmentos idénticos para las tres muestras; generador completo idéntico byte a byte | FAVORABLE en el alcance local indicado abajo; no se generó SOAP autenticado completo |

## Comparación de embalaje, NCM y MIC/DTA

Base exacta para «antes de 097d9a00»: **`8bf474e82ecd42cac08fc094efd05ee621cb9f80`**, padre directo de `097d9a001e3ede3a0b21f344184ad4e1289ea9ed`. No se usó 097d9a00 como si fuera su estado anterior.

Ambos códigos importaron las tres muestras originales en A, cada muestra dentro de transacción exterior:

- AGEDELTA 0256A: 37 BL / 131 ítems.
- CARE I TXT: 39 BL / 72 ítems.
- CONAY I TXT: 55 BL / 160 ítems.

En ambos extremos el embalaje es CONTENEDOR para los 363 ítems. La comparación por muestra + BL + línea confirma únicamente los dos NCM nuevos enumerados; los demás códigos y todas las descripciones son iguales. Además se contrastó la extracción NCM directamente con 188361f3: las mismas dos diferencias, sin otros cambios. Los 363 valores calculados coinciden con los efectivamente guardados por la candidata en MySQL.

Se invocaron por reflexión los escritores reales leídos previamente, con el viaje importado y sin TRACKs ficticios:

- `writeCargasSueltasIdTrack`: no escribe el grupo, porque estos ítems tienen contenedores.
- `writeTitTransContVaciosIdTrack`: no escribe el grupo, porque estas muestras MSC no contienen contenedores físicos V.
- `writeContenedoresConCarga`: lista XML idéntica de números de contenedor en ambos extremos.

Los tres fragmentos de carga son iguales para AGEDELTA, CARE I y CONAY I. `app/Services/Simple/SimpleXmlGenerator.php` es idéntico en ambos SHAs, SHA-256 **8d508ef84ec6f1ad367b206301999f76d22daee451e7369f315b7f1d078233e0**. RegistrarMicDta no usa el packaging_type del ítem para estos grupos: distingue la presencia de contenedores y su condición física. Esto verifica la ausencia de regresión por el cambio de embalaje en esas secciones. **No se generó un SOAP completo con autenticación**: el método público solicita WSAA; no se llamó ni se inyectaron tokens de prueba. Tampoco se afirma equivalencia de un envío aceptado por AFIP.

## Puntos que impiden el favorable global

1. **CUSCAR histórico, confirmado:** `CmspEdiParser::createContainer()` retorna al encontrar el contenedor existente, sin aplicar el nuevo default H. Los 91 vínculos reutilizan registros P y el escritor declara P. El default nuevo sí está cubierto por el test de creación; no prueba la actualización de históricos. No se modificó ninguna condición ni se retiraron contenedores para hacer pasar el control.
2. **Peso/migración, pendiente de preparación:** las tres columnas siguen `decimal(12,2)`. La migración incluida amplía a `decimal(13,3)`, pero no se ejecutó. Continúa pendiente la solicitud de excepción para ejecutarla en A con backup verificado: DDL MySQL hace commit implícito, fuera del rollback exigido. No se interpreta silencio como aprobación. Los pesos del archivo 1893.748 / 5115.615 / 6241.431 quedan 1893.750 / 5115.620 / 6241.430. No se declara 72/72 validado.
3. **TFP, límite previo:** la muestra original ASUNCIÓN B con V falla igualmente por bultos ausentes; no se inventaron cantidades. El mapa de los 50 V está probado sobre los valores reales; la importación completa con V no queda aprobada.
4. Sigue vigente el hallazgo de secuencia XSD de `writeEventoProg` documentado en el parte de trazabilidad A: codCiu se emite antes de codLugOper. El generador no cambió en esta rama. No se repitió la auditoría de 343 filas ni se corrigió ese defecto.

La limpieza de TAX ID de `fix/trazabilidad-aduana-b @ c8692bb7` no está integrada aquí y no se atribuye a esta rama. No se reabrió su QA.

## Preparación, preservación y pruebas

- Solo A red-Asus02, deviceId `c546af04-a420-4f1c-bc7b-2667f27bf883`, MySQL `app_cargas_pruebas`; usuario existente #20, Nabsa 1008. .env privado de QA, nunca impreso.
- Worktrees detached propios `app_cargas_work_msc_7ac37379_20261127` y `app_cargas_work_msc_8bf474e8_20261127`, autoload independiente. Checkout principal y cambios ajenos preservados.
- CARE I y CONAY I no existen en esta copia: fixtures mínimos con nombre real y empresa, sin matrícula/IMO/propietario/bandera/capacidad inventados. AGEDELTA ya estaba importado: se comprobó primero el bloqueo por hash y luego por viaje existente. Para ensayar el mismo archivo se dejó temporalmente el historial de ese hash en status reverted y se creó una embarcación mínima del mismo nombre real, sin identificadores; no se borró ni revirtió el viaje histórico. Don Toyo usó el fixture transaccional de historial ya documentado. Todo dentro de rollback, sin cambiar bytes ni hash de los archivos.
- **57 tablas preservadas**, conteos y hashes idénticos después de cada muestra. Las 12 instantáneas globales coinciden; SHA-256 de cada archivo: **0d4a446bd57cb0d41d910f4d2daee6b2f6a11188a289bc04fc616df1e9cfa886**. AUTO_INCREMENT puede avanzar por rollback; no es una alteración de filas.
- **96 tests / 458 aserciones, cero fallos**, con deprecaciones PDO del entorno. Sintaxis de los PHP/Blade modificados y arneses comprobada; diff --check limpio.
- HTTP externo bloqueado en el arnés, wrappers de red deshabilitados, SoapClient deshabilitado. Sin WSAA, certificados, tokens inventados, TRACKs ficticios ni envíos AFIP/ARCA/DNA. Sin Stage, producción, B, restores, DDL ejecutado ni cambios de código de producto.

### Tests exactos

Desde el worktree candidato:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d allow_url_fopen=0 artisan test tests/Unit/Services/Parsers/ResolvesClientAddressesTest.php tests/Unit/Services/Parsers/ImportedContainerConditionTest.php tests/Unit/Services/Parsers/MscVesselNameParserTest.php tests/Unit/Services/Parsers/DonToyoExcelParserTest.php tests/Unit/Services/Webservice/HidroviaTransshipmentDistributionValidatorTest.php tests/Unit/Services/Webservice/HidroviaTransshipmentMultiEnvioXmlGeneratorTest.php tests/Unit/Services/Parsers/ParanaNotifyPartyImportContractTest.php tests/Unit/Services/Parsers/ParanaNotifyPartyPersistenceTest.php
```

Evidencia privada E: `/var/www/html/pruebas_importacion/WORK-A-msc-7ac37379-20261127/`. Arneses:

- `php -d allow_url_fopen=0 -d disable_classes=SoapClient E/qa.php ROOT LABEL [MUESTRA]`.
- Candidata, LABEL=candidate (todas); base pre097, LABEL=before097, MUESTRA=MSC. AGEDELTA inicial y posterior a habilitar el hash fallaron por las dos protecciones reales indicadas. Corridas finales con fixture mínimo: LABEL=candidate-agedelta-fixture y before097-agedelta-fixture, MUESTRA=AGEDELTA.
- `php -d allow_url_fopen=0 E/ncm_source.php ROOT before` con raíz 188361f3; mismo comando con raíz candidata y etiqueta candidate.
- `tfp-map.json`: 119 condiciones del archivo original SHA-256 a099456aff31f51a394e1f7a1c07af83a427c9ee74a5da3ad60d626c7795792d, mapeadas por el código candidato.
- `assertions.json`, `pre097-assertions.json`, `comparison.json`, resultados por muestra y `unit-tests.log`. Solo datos privados locales; no subir JSON/XML a GitHub.

ROOT: rutas absolutas bajo `/var/www/html/_worktrees/app_cargas/` indicadas arriba. E y ROOT son abreviaturas documentales, no variables existentes que deban asumirse en la terminal. Se preserva la historia del parte anterior; este dictamen corresponde únicamente al SHA consolidado solicitado.


## Anexo — migración autorizada y diagnóstico TFP · 27/11/2026

Actualiza los pendientes de peso/TFP de la tabla anterior; no borra el resultado histórico. Víctor autorizó expresamente ejecutar en A la migración `2026_11_27_120000_keep_three_decimals_on_gross_weights`, anotando la excepción al rollback. **Peso: FAVORABLE 72/72 tras migración. TFP: fallo previo confirmado en 9863e6f9, no regresión de estas ramas.** La condición CUSCAR histórica sigue pendiente del nuevo commit y de su QA; este anexo no otorga favorable global.

### Excepción al rollback y preservación de A

- Target comprobado por código: MySQL `app_cargas_pruebas` en red-Asus02 A. No se accedió a Stage, B ni producción.
- Backup `A-before-gross-weight-migration.sql`: **26.712.492 bytes**, footer **Dump completed** verificado, SHA-256 **015b917c64db59d6e224dfcd2309b976edd71b67a333a25fca883be55a280af2**. Archivo privado fuera de Git; credenciales en archivo temporal 0600 eliminado al terminar, nunca en línea de comandos ni logs.
- Se ejecutó exclusivamente la migración autorizada mediante `Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_27_120000_keep_three_decimals_on_gross_weights.php', '--force' => true])`. Salida: **DONE, exit 0**, 686,33 ms. No se ejecutó down ni ninguna otra migración.
- `bills_of_lading.gross_weight_kg` y `shipment_items.gross_weight_kg` quedan **decimal(13,3) NOT NULL**; `container_shipment_item.gross_weight_kg`, **decimal(13,3) NULL**. No se modificó `hidrovia_transshipment_allocations`.
- **Excepción permanente autorizada:** tres cambios de esquema y una nueva fila de `migrations`. MySQL confirma DDL implícitamente; no se presentó esa operación como reversible mediante rollback.
- Control de las **57 tablas** antes/después: conteos de todas las tablas de datos iguales. Hashes idénticos para las tablas no afectadas. En las tres columnas ampliadas se normalizó únicamente la representación decimal (p. ej. 1.20 y 1.200) para comparar valor exacto, sin floats: **cero diferencias numéricas ni de otras columnas**. Historial previo de migraciones intacto y exactamente una fila nueva.
- Las pruebas de importación posteriores sí quedaron dentro de transacciones con rollback. Ambas preservaron las **57 tablas** por conteos y hashes crudos, incluidos los ceros decimales del nuevo esquema. Hash de cada instantánea posterior: **cd644c1d2b0f727fb84b791c836beef508d8eacecd391cca563f3e78a4d486db**.

### Pesos en 7ac37379 después de migrar

Muestra original CARE I TXT, mismo hash inventariado. Importación real 39 BL / 72 ítems: **72/72 pesos de ítem y 72/72 vínculos de contenedor iguales al archivo**, sin redondear el tercer decimal.

| BL | Archivo | Ítem guardado | Vínculo guardado |
|---|---:|---:|---:|
| MEDUB2534379 | 1893.748 | 1893.748 | 1893.748 |
| MEDUB2874197 | 5115.615 | 5115.615 | 5115.615 |
| MEDUXX020381 | 6241.431 | 6241.431 | 6241.431 |

Los bloqueos previos del escritor Bulto por descripciones largas se mantuvieron: el resultado 72/72 acredita persistencia de peso en MySQL, no generación de 72 Bultos sin otros datos pendientes. No se modificaron descripciones ni se llamó a WSAA.

### TFP contra la versión que hay en Stage

Se importó **en A**, con el código exacto `9863e6f94b4935b5b5994f72e3c7851bfc1f67e2`, el mismo archivo original ASUNCIÓN B de SHA-256 **a099456aff31f51a394e1f7a1c07af83a427c9ee74a5da3ad60d626c7795792d** y la embarcación existente ASUNCION B #62. Resultado: **«TFP: cantidad de bultos ausente o inválida.»**, igual que en 7ac37379. Transacción revertida y 57 tablas preservadas.

**Clasificación: fallo previo confirmado, no regresión introducida por trazabilidad/MSC.** No corresponde buscar un commit causante dentro de estas ramas cuando la línea de base ya falla.

| BL | Alcance | Dato que pide el código | ¿El archivo lo trae? |
|---|---|---|---|
| **001PASU018626** | Sus **50 líneas** de contenedores vacíos, estado físico V | `CANTTOTALBULTOS` numérico >0 | **No:** campo presente pero vacío (`/**/`) |
| Mismo BL, primera línea / contenedor **BMOU4805279** | Primer rechazo de cantidad según el orden de lectura | `CANTPARCIALBULTOS` / cantidad del contenedor como posible fuente | **No:** parcial vacío y el bloque CONTENEDORES no informa CANTIDAD |
| Mismo BL / primera línea | `PESOTOTALBULTOS` | También está vacío; es otro dato ausente, posterior al primer rechazo |

La muestra tiene 119 líneas en total; las 50 cantidades inválidas pertenecen **todas al mismo BL**. Se contrastaron el texto original y los resultados de `extractBlBlocks`, `parseHeader`, `parseLines` y `parseContainers` del código base. `TfpTextParser::createShipmentItem()`, líneas **928–937** de 9863e6f9, rechaza antes de insertar si `cant_total_bultos` está vacío o no es >0. El comentario posterior menciona el antiguo criterio de un bulto por contenedor, pero ese fallback **no está ejecutándose**: el guard lanza antes. No se inventó cantidad 1 ni peso ni se corrigió el parser.

### Evidencia y comandos del anexo

Directorio privado: `/var/www/html/pruebas_importacion/WORK-A-pesos-condicion-20261127/`.

- `php -l migrate_a.php` y `php -l tfp_diagnostic.php`: sin errores.
- `php -d allow_url_fopen=0 -d disable_classes=SoapClient migrate_a.php`: backup, verificación, migración única y comparación antes/después.
- `php -d allow_url_fopen=0 -d disable_classes=SoapClient qa.php /var/www/html/_worktrees/app_cargas/app_cargas_work_msc_7ac37379_20261127 weight-after-migration CAREI_TXT`.
- `php -d allow_url_fopen=0 -d disable_classes=SoapClient qa.php /var/www/html/_worktrees/app_cargas/app_cargas_work_tracea_9863e6f9_20261127 tfp-stage-baseline TFP_PHYSICAL`.
- `php -d allow_url_fopen=0 tfp_diagnostic.php`: contraste de 119 líneas, 50 cantidades inválidas y fragmentos literales del archivo.
- `migration-before.json` SHA-256 **a783ced5a07b65a749b74db5036ca5f18f94572a71669f581b09d76b7e661ea9**; `migration-after.json` SHA-256 **0e274e52f6283bd594d1f9107e81a7e9b13d441417d4a87b2080fda9c8964298**. Se conservan también dump, logs, hashes y resultados privados de los ensayos.


## Anexo — QA del nuevo commit de condición f4f6c814 · 27/11/2026

**Entrega identificada en origin durante el trabajo:** `fix/msc-contenedor-ncm @ f4f6c8144207c694cadc997a4f1a30de28672d67`, hijo directo de `7ac3737979a36eefa4dcb4b0695f240a2e68e25f`. Contrato del commit: CUSCAR guarda House en el vínculo del ítem de este viaje, los escritores de títulos/envíos lo prefieren al valor maestro, conservan la ficha del contenedor y rechazan condiciones H/P incompatibles para un mismo número. Leídos los tres archivos del delta y sus invocaciones antes del ensayo.

**Dictamen del delta de condición: FAVORABLE.** El punto CUSCAR histórico antes NO CUMPLE queda **RESUELTO en este SHA**; peso queda **RESUELTO tras la migración autorizada**. El rechazo TFP por datos ausentes se confirma como **previo a las ramas**, no una regresión de estas correcciones. Esto actualiza esos puntos del dictamen anterior, sin convertir en aprobación de un envío completo ni cerrar el hallazgo XSD independiente de EventoProg.

### Muestra real CUSCAR y conservación de históricos

| Control | Resultado |
|---|---|
| Archivo | CAREI-2506AHL.1, SHA-256 669ec80d42b3ef578e49d0e51448bb320492ec5569fdcfa33204a6c941216d63 |
| Importación MySQL A | 31 BL / 91 ítems |
| Ficha maestra del contenedor | **91/91 P**, iguales a los valores históricos |
| Vínculo de este viaje | **91/91 H** |
| XML del escritor real | **91/91 condicion H**, cero errores de escritor |
| Integridad de contenedores maestros durante la importación | **Toda la tabla containers idéntica**, no solo el campo condición |
| Tras rollback | **57/57 tablas preservadas por conteos/hashes** |

Se reprodujo la resolución de condiciones con la colección completa de contenedores vinculados de la importación, antes de deduplicar, como hace el generador. Se pasaron al escritor las condiciones y los precintos resueltos desde esos vínculos. No se confundió «la ficha sigue P» con un fallo: ahora el valor declarado de **este viaje** es H y la ficha histórica permanece intacta, tal como exige la entrega.

Control de conflicto, solo en memoria y usando un número real de la muestra: se presentaron al resolvedor dos vínculos H/P del mismo contenedor. Se rechazaron con **«QA local: el contenedor HAMU2965603 tiene condición H/P distinta entre sus conocimientos (H, P).»**. No eligió silenciosamente una condición ni escribió datos en A. Los tests también cubren el fallback P de la ficha cuando no hay condición en el vínculo.

### No regresión en el SHA nuevo

Se repitieron importaciones reales dentro de transacción sobre f4f6c814:

| Muestra | BL | Ítems | Resultado |
|---|---:|---:|---|
| AGEDELTA 0256A | 37 | 131 | CONTENEDOR en 131/131; NCM completos; XML Contenedor H |
| CARE I TXT | 39 | 72 | CONTENEDOR en 72/72; NCM completos; XML Contenedor H |
| CONAY I TXT | 55 | 160 | CONTENEDOR en 160/160; NCM completos; XML Contenedor H |
| Don Toyo | 75 | 75 | Nombre del cargador del archivo en nomRazSoc **75/75** |

MSC conserva **363/363 CONTENEDOR**, **0 NCM vacíos** y **363/363 fragmentos XML de contenedor en H, sin errores**. El delta no cambia el parser MSC ni la extracción NCM respecto de 7ac37379. El control de nombres/domicilios activos y las dos correcciones NCM previas conserva el alcance ya publicado; no se repitió la auditoría de la matriz entregada de 1109c11b.

### Tests y evidencia del SHA f4f6c814

**63 tests / 358 aserciones, cero fallos**, con deprecaciones PDO del entorno:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d allow_url_fopen=0 artisan test tests/Unit/Services/Parsers/ImportedContainerConditionTest.php tests/Unit/Services/Parsers/ResolvesClientAddressesTest.php tests/Unit/Services/Parsers/MscVesselNameParserTest.php tests/Unit/Services/Parsers/DonToyoExcelParserTest.php tests/Unit/Services/Webservice/ArgentinaMicDtaContainerBultoCountTest.php
```

Los tres PHP del commit y el arnés pasan php -l; `git diff --check 7ac37379..f4f6c814`, sin errores. Worktree limpio propio: `/var/www/html/_worktrees/app_cargas/app_cargas_work_condition_f4f6c814_20261127`, .env QA privado y autoload independiente.

En el mismo directorio privado E del anexo anterior:

- `php -d allow_url_fopen=0 -d disable_classes=SoapClient E/qa.php ROOT condition-f4f6c814 CUSCAR`.
- Mismo comando, LABEL=`condition-regression-msc`, MUESTRA=`MSC`; después LABEL=`condition-regression-don`, MUESTRA=`DON_TOYO`.
- ROOT es el worktree f4f6c814 anterior; `condition-tests.log` y resultados JSON privados conservan la evidencia.
- `condition-f4f6c814-result.json`, SHA-256 **d5437fbc21f8af61ba2d5f96d4597ceb0bceb297523d83924f67fd00653bf199**.
- Las **10 instantáneas antes/después** de los cinco procesos de QA posteriores a migrar coinciden con SHA-256 **cd644c1d2b0f727fb84b791c836beef508d8eacecd391cca563f3e78a4d486db**. Cada muestra se comprobó además al terminar su rollback.

No se construyó SOAP autenticado ni se solicitaron tokens WSAA; solo se invocaron escritores locales reales. Sin envíos ni certificados, Stage/B/producción, restore o cambios de producto. La única escritura persistente en A es la migración de pesos expresamente autorizada y documentada arriba.
