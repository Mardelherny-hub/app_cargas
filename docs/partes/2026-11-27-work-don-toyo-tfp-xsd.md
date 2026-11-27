# QA Don Toyo, TFP vacíos y orden XSD — 27/11/2026

**Dictamen: FAVORABLE para los tres deltas asignados**, probados en el orden solicitado. Sin integración ni deploy. Se consultaron origin y partes antes de probar y no había QA completado para estos SHA. La copia a Codex quedó descartada por instrucción posterior de Víctor; no se realizó ninguna transferencia del archivo.

| Entrega exacta | SHA completo | Resultado |
|---|---|---|
| fix/msc-contenedor-ncm, Don Toyo | c46c12d98a614180755754a42fb4345b5519e87d | 532/532 vínculos y XML en H; maestros P preservados |
| fix/tfp-vacios-sin-bultos | 0216302f3498239743550fcfc49b7737d35634c1 | 50 líneas de vacíos del BL 001PASU018626 en 0 bultos; carga sin cantidad rechazada |
| fix/msc-contenedor-ncm, orden XSD | 251c46852085c67f850b41a7d4509a595fd8b030 | Seis eventos con orden correcto; tres documentos de evidencia pasan XSD local |

Los contratos se fijaron por las instrucciones de Víctor, mensajes de commit y diff; no se sustituyeron ramas por nombres parecidos. Los partes publicados no tenían otra entrega detallada de estos deltas. El HEAD remoto de MSC ya era 251c4685, pero el primer QA se ejecutó sobre **c46c12d9 exacto**, no sobre el HEAD posterior.

## 1. Don Toyo: condición del vínculo

Comparación **f4f6c8144207c694cadc997a4f1a30de28672d67 → c46c12d98a614180755754a42fb4345b5519e87d**, con el XLS original Don Toyo del inventario, SHA-256 **5c00e50fca3c0f7c3a89bb58b5630bbf350728f41d5871287a4b72ea66981ffe**.

Cada importación real produjo **75 BL, 75 ítems, 532 vínculos**. Para reproducir el caso histórico se prepararon temporalmente las fichas de esos contenedores en P, exclusivamente dentro de la transacción exterior. Se conservaron sus números, CUIT, datos fiscales y resto de datos. El historial de hash duplicado y el número técnico de ensayo se prepararon como en el arnés previo, con rollback íntegro. No se revirtió ni borró el viaje histórico.

| Dato | Antes | Después |
|---|---:|---:|
| Ficha maestra P | 532 | 532 |
| Vínculo P | 532 | 0 |
| Vínculo H | 0 | 532 |
| XML Contenedor/condicion H | 0 | 532 |
| Vínculos con cantidad 0 | 480 | 480 |
| Vínculos restantes | 52 | 52 |

**Toda la tabla containers permanece idéntica durante cada importación respecto del fixture preparado**, no solo su condición. Antes/después son iguales los datos efectivos de las partes (incluidos nombre y tax_id), NCM, descripciones, embalaje, cantidades y pesos de ítems/vínculos de los 75 BL; solo cambia la condición de los vínculos y el XML que la declara. La identificación de fichas por CUIT, los nombres del archivo y la distribución de bultos no tienen diferencias en esa comparación.

Escritor directo real, con resolución de condiciones y precintos desde la colección de vínculos: no se solicitó WSAA, ni se construyó un SOAP con autenticación ficticia.

## 2. TFP: archivo original y control negativo

Archivo usado: **/var/www/html/pruebas_importacion/WORK-A-pesos-condicion-20261127/asuncion-original.txt**, SHA-256 **a099456aff31f51a394e1f7a1c07af83a427c9ee74a5da3ad60d626c7795792d**. Se comprobaron bytes/hash antes del ensayo. No se usó e2e-roberto-txt-bue ni se pidió copia a Codex.

Línea de base **9863e6f94b4935b5b5994f72e3c7851bfc1f67e2**: fallo previo ya publicado, «TFP: cantidad de bultos ausente o inválida.». No se repitió ese SHA completado. El nuevo commit agrega solo el tratamiento de cantidad ausente cuando hay contenedores y la descripción corresponde a vacío.

Candidata 0216302f: importación completa **52 BL / 119 ítems**. El BL **001PASU018626** tiene sus **50 ítems en 0 bultos y peso 0**, con vínculos en 0 y condición física V. No se omitieron líneas, no se asignó 1 bulto ni se repartieron pesos. Los campos originales CANTTOTALBULTOS/CANTPARCIALBULTOS/PESOTOTALBULTOS siguen ausentes en el archivo; los ceros son el comportamiento explícitamente autorizado para vacíos.

**Control negativo en MySQL A:** se seleccionó una línea real con carga del archivo, del BL **101CASU018626**. Solo en el array del ensayo se dejó vacía su cantidad; no se modificó el archivo ni se creó una descripción o peso ficticios. Se invocó createShipmentItem sobre un BL de la importación dentro de la transacción: rechazó con **«TFP: cantidad de bultos ausente o inválida.»**. El conteo de shipment_items no cambió. Luego rollback de todo el ensayo y control de las 57 tablas.

Alcance: esta muestra real y el control negativo solicitado. No se declara cierre general del parser ni validación de todas las variantes TFP.

## 3. Orden XSD: validación con el escritor nuevo

Comparación **c46c12d9 → 251c4685**. Cambio leído: en writeEventoProg se mueve codCiu después de codLugOper; los valores y las validaciones no cambian.

Además del test nuevo se usaron los **seis eventos de los tres MIC/DTA de la carpeta de trazabilidad recibida**, ya verificada por sus hashes. Se leyeron sus valores, se generaron solo los fragmentos EventoProg con el escritor de cada SHA y los puertos existentes de A, y se sustituyeron esos fragmentos en una copia DOM en memoria para validar contra el esquema embebido del WSDL local, con LIBXML_NONET.

| Documento de evidencia | Escritor anterior | Escritor 251c4685 | Valores de los dos eventos |
|---|---|---|---|
| OBSERVADOR RegistrarMicDta | No valida | Valida | Idénticos |
| CONAY I RegistrarMicDta | No valida | Valida | Idénticos |
| CARE I RegistrarMicDta | No valida | Valida | Idénticos |

En los **6/6 eventos** el orden nuevo es **codPais → codAdu → codLugOper → codCiu → fecha → id → orden**, como wgesregsintia2.wsdl 620–626. Se conservaron exactamente los valores por nombre de campo. El hallazgo de orden XSD queda **RESUELTO en 251c4685**.

Límite: se validó estructura y tipos del WSDL local, **no un envío aceptado por AFIP**. La evidencia original contiene campos PRUEBA ya documentados; no se generaron tokens, TRACKs ni respuestas aduaneras, ni se reetiquetaron esos valores como reales. No se ejecutó el método público que pide WSAA. Los documentos originales no se sobrescribieron.

## Entorno, preservación y pruebas

Solo **A red-Asus02**, deviceId c546af04-a420-4f1c-bc7b-2667f27bf883, MySQL app_cargas_pruebas; .env QA privado y usuario existente #20 de Nabsa 1008. AGENTS.md leído; checkout principal y cambios ajenos preservados. Tres worktrees detached propios bajo /var/www/html/_worktrees/app_cargas/app_cargas_work_pending_{SHA}_20261127, vendor/autoload independiente.

Todas las importaciones y fixtures dentro de transacción con rollback. **Las 57 tablas conservan conteos/hashes**; las seis instantáneas antes/después coinciden con SHA-256 **cd644c1d2b0f727fb84b791c836beef508d8eacecd391cca563f3e78a4d486db**. El fixture P desapareció. La migración autorizada de pesos ya aplicada en A no se repitió ni revirtió.

Pruebas por PATH con DB_CONNECTION=sqlite DB_DATABASE=:memory:, sin usar A para fixtures unitarios:

- Don Toyo: `php -d allow_url_fopen=0 artisan test tests/Unit/Services/Parsers/DonToyoExcelParserTest.php tests/Unit/Services/Parsers/ImportedContainerConditionTest.php tests/Unit/Services/Webservice/ArgentinaMicDtaContainerBultoCountTest.php`: **33 tests / 190 aserciones**.
- TFP: `php -d allow_url_fopen=0 artisan test tests/Unit/Services/Parsers/TfpTextParserMissingPackagesTest.php`: **2 tests / 8 aserciones**.
- XSD: `php -d allow_url_fopen=0 artisan test tests/Unit/Services/Webservice/ArgentinaMicDtaRouteEventXsdTest.php`: **1 test / 8 aserciones**.
- Total **36 tests / 206 aserciones, cero fallos**, con deprecaciones PDO del entorno.
- php -l sobre los seis PHP modificados de los tres commits y arneses; git diff --check contra cada padre: sin errores.

Evidencia privada **/var/www/html/pruebas_importacion/WORK-A-pending-20261127/** (0700): qa.php, event_audit.php, logs y resultados. Comandos del arnés `php -d allow_url_fopen=0 -d disable_classes=SoapClient qa.php ROOT LABEL MUESTRA`: f4f6c814/don-before/DON_TOYO, c46c12d9/don-after/DON_TOYO, 0216302f/tfp-after/TFP_PHYSICAL. ROOT son los worktrees exactos documentados; no variables de configuración supuestas. `event_audit.php ROOT before|after` se ejecutó con c46c12d9 y 251c4685.

Hashes de resultados privados:
- don-after-result.json: **fe971663885b1fcc2e7584eab8f747e5166f295cc4e698e6ae2a9cfd265defe4**.
- tfp-after-result.json: **74eb2acf08438dd27d7f53247a22f8e35cc898b249c84e382213f187893dac9e**.
- event-after.json: **51dd981e26d3693b9ef04cb697963ff20f45a847ff640ab2b85b99efd9f7aebc**.

Sin Stage, producción, B, WSAA, certificados, red aduanera, restores, nuevas migraciones o código de producto. Solo este parte se publica en docs/partes.
