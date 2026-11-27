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
