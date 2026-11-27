# QA cruzado y auditoría de trazabilidad aduanera — 27/11/2026

## Dictamen y alcance

**SIN APROBACIÓN GLOBAL.** Código exacto `origin/fix/trazabilidad-aduana-a @ 188361f300aeda7aeb458ce369878ad25a1f69c8`, comparado con `9863e6f94b4935b5b5994f72e3c7851bfc1f67e2`. No sustituir esta rama por la B ni dar por integrada la limpieza posterior c869. Lectura previa del contrato/matriz dentro de la rama, diff, parsers, modelos, relaciones, campos y escritores reales. La matriz entregada corresponde al ensayo B de `release/etapa-2 @ 1109c11b`, no al código candidato ni a una aceptación de pantalla realizada en A.

| Criterio pedido | Base → candidata en A | Resultado |
|---|---|---|
| Nombre del remitente MSC del archivo en XML | CARE I 0/39 → 39/39; CONAY I 0/55 → 55/55. Total **0/94 → 94/94** | FAVORABLE, texto del nodo nomRazSoc comprobado |
| Domicilios específicos del archivo ACTIVOS | CARE I 0/92 → 92/92; CONAY I 0/147 → 147/147; CUSCAR 0/44 → 44/44; TFP 0/7 → 7/7 | FAVORABLE para activación; todos los contactos específicos creados quedan activos |
| Nombres/domicilios difieren de la ficha y llegan al escritor | MSC 94/94 remitentes con nombre y domicilio del archivo; campos específicos y salida XML contrastados. Fichas maestras sin sobrescritura | FAVORABLE para ese alcance; ver límites de otros roles |
| CUSCAR sin H/P, 91/91 House | **0/91 H antes y después; 91/91 P** en persistencia y en el XML de Contenedor | NO CUMPLE en A con contenedores históricos |
| TFP, estado físico V implica House | 50 registros V reales: mapa V/P → V/H. 69 H/H conservados. Muestra adicional con P explícito: 20/20 P conservados | Mapeo FAVORABLE; importación completa de ASUNCIÓN B bloqueada por dato ausente, igual en base |
| Pesos 72/72 con tres decimales | **69/72 → 69/72** con el esquema actual decimal(12,2) | PENDIENTE ejecutar/verificar migración en MySQL A; no se aprueba 72/72 |
| Don Toyo, nombre de cargador del archivo en XML | **0/75 → 75/75**, con 75 contactos específicos activos de nombre. Los domicilios siguen siendo los de la ficha | FAVORABLE |

Los 290 domicilios específicos de las cuatro muestras quedan activos. Hay además contactos específicos solo de nombre: por ejemplo, los 75 de Don Toyo no inventan una dirección. La igualdad de domicilio se normaliza por mayúsculas y espacios; se retiran etiquetas fiscales/contactos con las reglas del parser. Diferencias de mayúsculas frente a una ficha equivalente no se cuentan como pérdida del dato. En consignatarios/notificados se conserva la extracción fiscal; no se declara igualdad literal del rótulo RUC dentro del nombre. Existen bloqueos previos por identificaciones fiscales ausentes y razones sociales largas: no se alteraron para conseguir XML exitosos. Se guardaron los errores exactos por BL y rol.

## Hallazgos y límites del QA

### CUSCAR: contenedores existentes siguen en Pier

**Incumplimiento confirmado del criterio 91/91 House en esta base.** `CmspEdiParser::createContainer()` (desde línea 3035; retorno por existente en 3047 y siguientes) busca por número y retorna tras asociar el contenedor, sin aplicar el nuevo default H. Los 91 vínculos de la muestra reutilizan contenedores históricos P. Los 91 fragmentos XML se generaron con `writeMicDtaContainer`, usando los precintos reales del vínculo mediante `linkSealsByContainer`: todos declaran P. No se confundió condición física L/V con condición documental H/P.

El test nuevo `test_existing_container_keeps_its_condition` conserva expresamente P. Esto muestra una contradicción de alcance entre la preservación global existente y el resultado literal solicitado, no una regresión nueva de creación. El test aislado de contenedor nuevo sin condición pasa en H; no reemplaza la prueba con los históricos. No se corrigieron catálogos ni condiciones.

### Peso y migración pendiente

A todavía tiene `gross_weight_kg decimal(12,2)` en `bills_of_lading`, `shipment_items` y `container_shipment_item`. La nueva migración amplía las tres a **decimal(13,3)**, mantiene la misma cantidad de dígitos enteros y conserva nullable solo en el pivote. Se leyó y se ejecutó **solo --pretend**; los tres ALTER generados coinciden. No se ejecutó up ni down. El down redondearía a dos decimales: no debe usarse como procedimiento de restauración de este QA.

| BL de CARE I TXT | Archivo | Guardado con esquema actual | XML de B entregado |
|---|---:|---:|---:|
| MEDUB2534379 | 1893.748 | 1893.750 | 1893.7500 |
| MEDUB2874197 | 5115.615 | 5115.620 | 5115.6200 |
| MEDUXX020381 | 6241.431 | 6241.430 | 6241.4300 |

La candidata ya tiene casts y redondeo a tres, pero la columna redondea al guardar. Solicitud de excepción pendiente a Víctor: ampliar únicamente esas tres columnas, con backup verificado y control de preservación, porque DDL MySQL hace commit implícito y no cabe en la transacción de fixtures exigida. Hasta obtener respuesta y ejecutar el control, **no afirmar migración validada ni 72/72 persistidos**. El WSDL usa `s:decimal` sin restricción de escala; los escritores emiten cuatro decimales. En el escritor de Bulto, dos de los tres BL tienen además bloqueos previos por descripción >500: se conservaron, no se recortaron para forzar XML.

### TFP con estado físico real

Muestra adjunta original `asuncion-b-viaje-0186b-export-codigo-645_20260806001730_u9Jry12M.txt`, SHA-256 **a099456aff31f51a394e1f7a1c07af83a427c9ee74a5da3ad60d626c7795792d**: 119 registros de contenedor, 50 V y 69 H. Transferida a evidencia privada preservando bytes y comprobando hash. Los 50 V pasan de documental P a H en `mapTfpCondition` de la candidata; el estado físico sigue V. Los 69 H permanecen H.

Se intentó importación completa original, con embarcación existente ASUNCION B #62, en ambos SHAs: falla en ambos con **«TFP: cantidad de bultos ausente o inválida.»**. No se completaron cantidades, ni se consideró ese fallo previo una regresión. La muestra TFP inventariada adicional, con P explícito, sí importa 9 BL/9 ítems y conserva P en 20 contenedores. El recorrido completo de la muestra con V queda limitado por la ausencia de cantidades.

### Don Toyo y preparación reversible

Primer intento sin fixture: el archivo idéntico ya importado se rechaza correctamente con «El archivo ya fue importado. Revise el historial de importaciones.». Para ensayar sin borrar/revertir el viaje existente se preparó un fixture **solo dentro de la transacción exterior**: status de la importación del mismo hash pasó temporalmente a reverted y se usó el número de operador técnico `0626S-QA-TRACEA`. No se afirmó que esa importación histórica estuviera realmente revertida. No se modificó el archivo ni su hash. Ambos cambios y las importaciones nuevas desaparecieron con rollback, verificado sobre todas las tablas. Usuario real #20 de Nabsa 1008, DON TOYO #60. Ningún CUIT, código fiscal, TRACK ni matrícula fue inventado.

## Aislamiento, pruebas y evidencia

- Dos worktrees detached propios limpios en `/var/www/html/_worktrees/app_cargas/app_cargas_work_tracea_{9863e6f9,188361f3}_20261127`, `.env` privado de QA, autoload independiente. Checkout principal y cambios ajenos preservados.
- Todas las escrituras de muestras/fixtures en **MySQL A app_cargas_pruebas**, transacción exterior y rollback. CARE I/CONAY I no existían en esta copia: fixture mínimo con nombre real del archivo, empresa 1008 y activo; sin matrícula, IMO, bandera, propietario, capacidad ni códigos aduaneros inventados. Todo revertido.
- **57 tablas** con conteos y hashes idénticos en las 16 instantáneas de las ocho corridas. Hash de cada archivo de instantánea: `0d4a446bd57cb0d41d910f4d2daee6b2f6a11188a289bc04fc616df1e9cfa886`. Las secuencias AUTO_INCREMENT pueden avanzar con rollback; no se confunden con diferencias de datos.
- Candidata: **56 tests / 324 aserciones**, más **39 tests / 129 aserciones** relacionados; cero fallos. Base: **45 tests / 271 aserciones** comunes; cero fallos. Avisos de deprecación PDO del entorno en las tres ejecuciones.
- `php -l` en los 28 archivos PHP/Blade modificados y arnés, sin errores; `git diff --check 9863e6f9..188361f3`, sin errores.
- Fragmentos XML locales mediante reflexión de **escritores reales leídos previamente**, sin construir SOAP autenticado: `writeRemitente`, `writeConsignatario`, `writeNotificado`, `writeMicDtaBulto`, `writeMicDtaContainer`, y `linkSealsByContainer`. Sin tokens falsos, WSAA, lectura de certificados ni envíos. `SoapClient` deshabilitado, wrappers HTTP/HTTPS/FTP retirados y solicitudes HTTP inesperadas bloqueadas por el arnés.
- Sin acceso a Stage, producción o B; sin restore, DDL ejecutado, migraciones destructivas, cambios de producto, merge ni deploy. Auditoría de la entrega B solo sobre archivos recibidos en A.

### Comandos reproducibles

Directorio privado de evidencia, en adelante E: `/var/www/html/pruebas_importacion/WORK-A-tracea-188361f3-20261127/`.

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d allow_url_fopen=0 artisan test tests/Unit/Services/Parsers/ResolvesClientAddressesTest.php tests/Unit/Services/Parsers/ImportedContainerConditionTest.php tests/Unit/Services/Parsers/MscVesselNameParserTest.php tests/Unit/Services/Parsers/DonToyoExcelParserTest.php
DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d allow_url_fopen=0 artisan test tests/Unit/Services/Webservice/HidroviaTransshipmentDistributionValidatorTest.php tests/Unit/Services/Webservice/HidroviaTransshipmentMultiEnvioXmlGeneratorTest.php tests/Unit/Services/Parsers/ParanaNotifyPartyImportContractTest.php tests/Unit/Services/Parsers/ParanaNotifyPartyPersistenceTest.php
# Base: mismo primer comando, sin ImportedContainerConditionTest.php (archivo nuevo).
php -d allow_url_fopen=0 artisan migrate --pretend --path=database/migrations/2026_11_27_120000_keep_three_decimals_on_gross_weights.php --force
```

Arnés: `php -d allow_url_fopen=0 -d disable_classes=SoapClient E/qa.php ROOT LABEL [MUESTRA]`, donde E y ROOT se sustituyen por las rutas absolutas anteriores. Corridas: raíz base / etiqueta base; candidata / candidate; base / base-don / DON_TOYO; candidata / candidate-don / DON_TOYO; base / base-cuscar / CUSCAR; candidata / candidate-cuscar / CUSCAR; base / base-tfp-physical / TFP_PHYSICAL; candidata / candidate-tfp-physical / TFP_PHYSICAL. Las corridas CUSCAR específicas usan los precintos del vínculo; las iniciales no los habían pasado al escritor y se descartaron sus errores de precinto como defecto del arnés.

Los JSON guardan fuente, datos persistidos, contacto activo, XML real o error exacto por BL/rol/ítem; permanecen privados fuera del repositorio. `unit-tests.log`, `related-tests.log`, `base-tests.log`, `migration-pretend.log`, `qa.php`, `tfp-map-*.json` permiten reproducir el alcance indicado.

## Auditoría de la carpeta entregada por Charly

Aviso confirmado en `docs/partes/2026-11-27-charly-traspaso-trazabilidad-work.md @ 5fedbbeb2ae6a2b9b30a0d87e9a92b32ee6c6d1c`. Carpeta local `/var/www/html/pruebas_importacion/ENTREGA-ASUS02-TRAZABILIDAD-20261127/`. Verificación independiente `sha256sum -c SHA256SUMS.txt`: **59/59 OK**. No se restauró el dump.

Matriz fijada desde `188361f3:docs/partes/2026-11-27-trazabilidad-aduana-etapa-2.md`: **343 filas**, 117 rótulos de campo distintos, algunos agrupados. Se expandieron campos separados por punto medio y se recorrieron tipos XSD, secuencias y herencia de autenticación. Todas las rutas contrastadas tienen correspondencia en el WSDL; ello no confirma la semántica de sus valores ni que sus límites provengan de AFIP. Los límites del generador y los del WSDL son fuentes distintas.

Se contrastó cada fila con sus XML correspondientes; en el XML CARE I combinado se separaron los BL de TXT/CUSCAR por los números del archivo. Resultado de presencia y ruta por fila (categorías excluyentes, **no son nuevos PASS/FAIL funcionales**): 193 presentes con ruta WSDL; 87 no presentes; 33 presentes con valores PRUEBA; 30 presentes en ramas de EventoProg de XML que no validan secuencia. Una ausencia de un grupo opcional/no aplicable no se convierte automáticamente en defecto.

### Validación XSD independiente y defecto confirmado

Se extrajo el `xsd:schema` embebido de `wgesregsintia2.wsdl` del proyecto conservando namespaces. Se validó el elemento de operación de cada SOAP Body mediante DOMDocument::schemaValidateSource, con `LIBXML_NONET`, sin red. Python lxml no estaba instalado; se usó el validador DOM/libxml disponible, sin instalar dependencias.

| XML entregado | XSD local | Detalle |
|---|---|---|
| 01-OBSERVADOR-RegistrarMicDta.xml | **FALLA** | Dos eventos con codLugOper fuera de secuencia |
| 02-CONAYI-RegistrarMicDta.xml | **FALLA** | Dos eventos con codLugOper fuera de secuencia |
| 03-CAREI-RegistrarMicDta.xml | **FALLA** | Dos eventos con codLugOper fuera de secuencia |
| 02-CONAYI-RegistrarTitEnvios.xml | PASA | Validez estructural/tipos, no aceptación de negocio |
| 03-CAREI-RegistrarTitEnvios.xml | PASA | Validez estructural/tipos, no aceptación de negocio |
| 09-CONVOY-RegistrarConvoy.xml | PASA | Referencias de prueba; no demuestra referencias emitidas por AFIP |

**Bug confirmado contra el WSDL local:** `EventoProg` sí admite codLugOper, pero exige **codPais → codAdu → codLugOper → codCiu → fecha → id → orden**. El escritor `SimpleXmlGenerator::writeEventoProg` emite codCiu antes de codLugOper (líneas 2349 y 2377 de la candidata). Los tres MIC/DTA fallan por esa secuencia; seis errores de esquema en total. Filas de matriz directamente afectadas: **289–291 y 304–306**. No se corrigieron ni el XML ni el generador. No se extrapola este resultado a un rechazo real de AFIP: se informa la contradicción con el WSDL exacto local.

Libxml también advierte que el URI histórico de namespace no es absoluto; se separó ese warning de los errores XSD. Los tres XML que pasan tienen cero errores de esquema. La matriz debe distinguir presencia de un campo de validez estructural del documento completo.

### Datos de prueba y alcance de la evidencia

Los seis XML contienen valores PRUEBA, coherentes con la advertencia inicial de la entrega. **No son datos reales aceptados ni respuestas de Aduana.** Las filas 334/336/338 clasifican autenticación como OK, pero sus Token/Sign son de prueba: solo acreditan estructura para el ensayo sin WSAA. Campos previamente faltantes rellenados con PRUEBA mantienen FALTA del origen; no se convierten en OK de aceptación del convoy real. Las ausencias opcionales (vehículo en hidrovía, capitanes de barcazas, solicitud de presentación, referencias no aplicables) no se etiquetan como defecto por mera ausencia.

La comparación independiente de los 72 Bultos de CARE I TXT contra el archivo original confirma en el XML entregado **69/72 pesos exactos**, con los tres redondeos de N5 ya listados. La carpeta prueba el estado anterior 1109c11b: no acredita las correcciones de 188361f3 ni una aceptación final en pantalla. No se abrió sesión de navegador ni se rehízo en este encargo el ensayo de aceptación del convoy.

Comandos: `php -d allow_url_fopen=0 E/validate_wsdl.php` y `python3 E/audit_matrix.py`. Evidencia: `xml-schema-audit.json`, `wsdl-schema.xsd`, `wsdl-field-catalog.json`, `matrix-audit-private.json`, `matrix-audit-public.csv`, `matriz-entregada.md`, `delivery-checksums.log`. El JSON privado conserva ocurrencias/valores por fila, sin publicarlos. La tabla siguiente publica solo correspondencias y presencia, nunca domicilios, identificaciones fiscales, tokens ni XML completos.

## Matriz de auditoría de las 343 filas

Leyenda: **PRESENTE** = nodo encontrado y ruta declarada en WSDL; **AUSENTE** = no aparece en la evidencia seleccionada, requiere leer obligatoriedad/aplicabilidad original; **PRUEBA** = contiene un valor PRUEBA; **SECUENCIA** = pertenece a los eventos de un MIC/DTA que falla XSD. Los estados originales del desarrollador no se sobrescriben.

| Fila | Método | Campo | Estado original | Auditoría XML/WSDL |
|---:|---|---|---|---|
| 1 | RegistrarTitEnvios | TitTransEnvio/remitente/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (39 de 39) | PRESENTE |
| 2 | RegistrarTitEnvios | TitTransEnvio/remitente/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (55 de 55) | PRESENTE |
| 3 | RegistrarTitEnvios | TitTransEnvio/remitente/nomRazSoc | OK | PRESENTE |
| 4 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (39 de 39) | PRESENTE |
| 5 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (55 de 55) | PRESENTE |
| 6 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/nombreCalle | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (4 de 31; 27 OK) | PRESENTE |
| 7 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/ciudad | FALTA | AUSENTE |
| 8 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/ciudad | FALTA | AUSENTE |
| 9 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/ciudad | FALTA | AUSENTE |
| 10 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/codPostal | FALTA | AUSENTE |
| 11 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/codPostal | FALTA | AUSENTE |
| 12 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/codPostal | FALTA | AUSENTE |
| 13 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/estado | FALTA | AUSENTE |
| 14 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/estado | FALTA | AUSENTE |
| 15 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/estado | FALTA | AUSENTE |
| 16 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/barrio | FALTA | AUSENTE |
| 17 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/barrio | FALTA | AUSENTE |
| 18 | RegistrarTitEnvios | TitTransEnvio/remitente/domicilio/barrio | FALTA | AUSENTE |
| 19 | RegistrarTitEnvios | TitTransEnvio/remitente/idFiscal | OK | PRESENTE |
| 20 | RegistrarTitEnvios | TitTransEnvio/remitente/idFiscal | OK | PRESENTE |
| 21 | RegistrarTitEnvios | TitTransEnvio/remitente/idFiscal | NO LLEGA (3 de 31; 4 OK) | PRESENTE, PRUEBA |
| 22 | RegistrarTitEnvios | TitTransEnvio/remitente/codPais | OK | PRESENTE |
| 23 | RegistrarTitEnvios | TitTransEnvio/remitente/codPais | OK | PRESENTE |
| 24 | RegistrarTitEnvios | TitTransEnvio/remitente/codPais | NO LLEGA (2 de 31; 23 OK) | PRESENTE |
| 25 | RegistrarTitEnvios | TitTransEnvio/remitente/tipDocIdent · nroDocIdent | FALTA | AUSENTE |
| 26 | RegistrarTitEnvios | TitTransEnvio/remitente/tipDocIdent · nroDocIdent | FALTA | AUSENTE |
| 27 | RegistrarTitEnvios | TitTransEnvio/remitente/tipDocIdent · nroDocIdent | FALTA | AUSENTE |
| 28 | RegistrarTitEnvios | TitTransEnvio/consignatario/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (1 de 39; 38 OK) | PRESENTE |
| 29 | RegistrarTitEnvios | TitTransEnvio/consignatario/nomRazSoc | FORMATO INCORRECTO / NO LLEGA / NO LLEGA (se usa la ficha preexistente del cliente) (4 de 55; 51 OK) | PRESENTE, PRUEBA |
| 30 | RegistrarTitEnvios | TitTransEnvio/consignatario/nomRazSoc | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (4 de 31; 27 OK) | PRESENTE |
| 31 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (10 de 39; 29 OK) | PRESENTE |
| 32 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (4 de 55; 51 OK) | PRESENTE |
| 33 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (8 de 31; 23 OK) | PRESENTE |
| 34 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/ciudad | FALTA | AUSENTE |
| 35 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/ciudad | FALTA | AUSENTE |
| 36 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/ciudad | FALTA | AUSENTE |
| 37 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/codPostal | FALTA | AUSENTE |
| 38 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/codPostal | FALTA | AUSENTE |
| 39 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/codPostal | FALTA | AUSENTE |
| 40 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/estado | FALTA | AUSENTE |
| 41 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/estado | FALTA | AUSENTE |
| 42 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/estado | FALTA | AUSENTE |
| 43 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/barrio | FALTA | AUSENTE |
| 44 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/barrio | FALTA | AUSENTE |
| 45 | RegistrarTitEnvios | TitTransEnvio/consignatario/domicilio/barrio | FALTA | AUSENTE |
| 46 | RegistrarTitEnvios | TitTransEnvio/consignatario/idFiscal | NO LLEGA (1 de 39; 29 OK) | PRESENTE, PRUEBA |
| 47 | RegistrarTitEnvios | TitTransEnvio/consignatario/idFiscal | OK (53) / FALTA (2) | PRESENTE |
| 48 | RegistrarTitEnvios | TitTransEnvio/consignatario/idFiscal | NO LLEGA (1 de 31; 27 OK) | PRESENTE, PRUEBA |
| 49 | RegistrarTitEnvios | TitTransEnvio/destinatario/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (1 de 39; 38 OK) | PRESENTE |
| 50 | RegistrarTitEnvios | TitTransEnvio/destinatario/nomRazSoc | FORMATO INCORRECTO / NO LLEGA / NO LLEGA (se usa la ficha preexistente del cliente) (4 de 55; 51 OK) | PRESENTE, PRUEBA |
| 51 | RegistrarTitEnvios | TitTransEnvio/destinatario/nomRazSoc | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (4 de 31; 27 OK) | PRESENTE |
| 52 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (10 de 39; 29 OK) | PRESENTE |
| 53 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (4 de 55; 51 OK) | PRESENTE |
| 54 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/nombreCalle | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (8 de 31; 23 OK) | PRESENTE |
| 55 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/ciudad | FALTA | AUSENTE |
| 56 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/ciudad | FALTA | AUSENTE |
| 57 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/ciudad | FALTA | AUSENTE |
| 58 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/codPostal | FALTA | AUSENTE |
| 59 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/codPostal | FALTA | AUSENTE |
| 60 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/codPostal | FALTA | AUSENTE |
| 61 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/estado | FALTA | AUSENTE |
| 62 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/estado | FALTA | AUSENTE |
| 63 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/estado | FALTA | AUSENTE |
| 64 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/barrio | FALTA | AUSENTE |
| 65 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/barrio | FALTA | AUSENTE |
| 66 | RegistrarTitEnvios | TitTransEnvio/destinatario/domicilio/barrio | FALTA | AUSENTE |
| 67 | RegistrarTitEnvios | TitTransEnvio/notificado/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (2 de 39; 37 OK) | PRESENTE |
| 68 | RegistrarTitEnvios | TitTransEnvio/notificado/nomRazSoc | FORMATO INCORRECTO / NO LLEGA / NO LLEGA (se usa la ficha preexistente del cliente) (6 de 55; 49 OK) | PRESENTE, PRUEBA |
| 69 | RegistrarTitEnvios | TitTransEnvio/notificado/nomRazSoc | NO LLEGA (se usa la ficha preexistente del cliente) (3 de 31; 28 OK) | PRESENTE |
| 70 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (10 de 39; 29 OK) | PRESENTE |
| 71 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/nombreCalle | NO LLEGA (se usa la ficha preexistente del cliente) (6 de 55; 49 OK) | PRESENTE |
| 72 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/nombreCalle | NO LLEGA (se usa la ficha creada por otro conocimiento del viaje) / NO LLEGA (se usa la ficha preexistente del cliente) (8 de 31; 23 OK) | PRESENTE |
| 73 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/ciudad | FALTA | AUSENTE |
| 74 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/ciudad | FALTA | AUSENTE |
| 75 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/ciudad | FALTA | AUSENTE |
| 76 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/codPostal | FALTA | AUSENTE |
| 77 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/codPostal | FALTA | AUSENTE |
| 78 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/codPostal | FALTA | AUSENTE |
| 79 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/estado | FALTA | AUSENTE |
| 80 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/estado | FALTA | AUSENTE |
| 81 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/estado | FALTA | AUSENTE |
| 82 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/barrio | FALTA | AUSENTE |
| 83 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/barrio | FALTA | AUSENTE |
| 84 | RegistrarTitEnvios | TitTransEnvio/notificado/domicilio/barrio | FALTA | AUSENTE |
| 85 | RegistrarTitEnvios | TitTransEnvio/notificado/idFiscal | NO LLEGA (1 de 39; 27 OK) | PRESENTE, PRUEBA |
| 86 | RegistrarTitEnvios | TitTransEnvio/notificado/idFiscal | OK (53) / FALTA (2) | PRESENTE |
| 87 | RegistrarTitEnvios | TitTransEnvio/notificado/idFiscal | NO LLEGA (1 de 31; 26 OK) | PRESENTE, PRUEBA |
| 88 | RegistrarTitEnvios | TitTransEnvio/codViaTrans | OK | PRESENTE |
| 89 | RegistrarTitEnvios | TitTransEnvio/codViaTrans | OK | PRESENTE |
| 90 | RegistrarTitEnvios | TitTransEnvio/codViaTrans | OK | PRESENTE |
| 91 | RegistrarTitEnvios | TitTransEnvio/idTitTrans | OK | PRESENTE |
| 92 | RegistrarTitEnvios | TitTransEnvio/idTitTrans | OK | PRESENTE |
| 93 | RegistrarTitEnvios | TitTransEnvio/idTitTrans | OK | PRESENTE |
| 94 | RegistrarTitEnvios | TitTransEnvio/obsDeclaAduInter | FORMATO INCORRECTO (5 de 39; 34 OK) | PRESENTE, PRUEBA |
| 95 | RegistrarTitEnvios | TitTransEnvio/obsDeclaAduInter | FORMATO INCORRECTO (2 de 55; 53 OK) | PRESENTE, PRUEBA |
| 96 | RegistrarTitEnvios | TitTransEnvio/obsDeclaAduInter | FORMATO INCORRECTO (1 de 31; 30 OK) | PRESENTE, PRUEBA |
| 97 | RegistrarTitEnvios | TitTransEnvio/indFinCom | OK | PRESENTE |
| 98 | RegistrarTitEnvios | TitTransEnvio/indFinCom | OK | PRESENTE |
| 99 | RegistrarTitEnvios | TitTransEnvio/indFinCom | OK | PRESENTE |
| 100 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/indUltFra | OK | PRESENTE |
| 101 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/indUltFra | OK | PRESENTE |
| 102 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/indUltFra | OK | PRESENTE |
| 103 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/idEnvio | OK | PRESENTE |
| 104 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/idEnvio | OK | PRESENTE |
| 105 | RegistrarTitEnvios | TitTransEnvio/envios/Envio/idEnvio | OK | PRESENTE |
| 106 | RegistrarTitEnvios | TitTransEnvio/indFraccTransp | OK | PRESENTE |
| 107 | RegistrarTitEnvios | TitTransEnvio/indFraccTransp | OK | PRESENTE |
| 108 | RegistrarTitEnvios | TitTransEnvio/indFraccTransp | FALTA | PRESENTE |
| 109 | RegistrarTitEnvios | TitTransEnvio/indConsol | FALTA | PRESENTE |
| 110 | RegistrarTitEnvios | TitTransEnvio/indConsol | FALTA | PRESENTE |
| 111 | RegistrarTitEnvios | TitTransEnvio/indConsol | FALTA | PRESENTE |
| 112 | RegistrarTitEnvios | TitTransEnvio/nroFact/nroFact | FALTA | AUSENTE |
| 113 | RegistrarTitEnvios | TitTransEnvio/nroFact/nroFact | FALTA | AUSENTE |
| 114 | RegistrarTitEnvios | TitTransEnvio/nroFact/nroFact | NO LLEGA (1 de 31) | AUSENTE |
| 115 | RegistrarTitEnvios | TitTransEnvio/idManiCargaArrPaisPart · idDocTranspArrPaisPart | FALTA | AUSENTE |
| 116 | RegistrarTitEnvios | TitTransEnvio/idManiCargaArrPaisPart · idDocTranspArrPaisPart | FALTA | AUSENTE |
| 117 | RegistrarTitEnvios | TitTransEnvio/idManiCargaArrPaisPart · idDocTranspArrPaisPart | FALTA | AUSENTE |
| 118 | RegistrarTitEnvios | TitTransEnvio/origen/codAdu | OK | PRESENTE |
| 119 | RegistrarTitEnvios | TitTransEnvio/origen/codAdu | OK | PRESENTE |
| 120 | RegistrarTitEnvios | TitTransEnvio/origen/codAdu | OK | PRESENTE |
| 121 | RegistrarTitEnvios | TitTransEnvio/destino/codPais | OK | PRESENTE |
| 122 | RegistrarTitEnvios | TitTransEnvio/destino/codPais | OK | PRESENTE |
| 123 | RegistrarTitEnvios | TitTransEnvio/destino/codPais | OK | PRESENTE |
| 124 | RegistrarTitEnvios | TitTransEnvio/destino/codAdu | OK | PRESENTE |
| 125 | RegistrarTitEnvios | TitTransEnvio/destino/codAdu | OK | PRESENTE |
| 126 | RegistrarTitEnvios | TitTransEnvio/destino/codAdu | OK | PRESENTE |
| 127 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/idDecla | FALTA | PRESENTE, PRUEBA |
| 128 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/idDecla | FALTA | PRESENTE, PRUEBA |
| 129 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/idDecla | FALTA | PRESENTE, PRUEBA |
| 130 | RegistrarTitEnvios | Destinacion/montoFob | FALTA | PRESENTE |
| 131 | RegistrarTitEnvios | Destinacion/montoFob | FALTA | PRESENTE |
| 132 | RegistrarTitEnvios | Destinacion/montoFob | FALTA | PRESENTE |
| 133 | RegistrarTitEnvios | Destinacion/montoFlete | FALTA | PRESENTE |
| 134 | RegistrarTitEnvios | Destinacion/montoFlete | FALTA | PRESENTE |
| 135 | RegistrarTitEnvios | Destinacion/montoFlete | NO LLEGA (31 de 31) | PRESENTE |
| 136 | RegistrarTitEnvios | Destinacion/montoSeg | FALTA | PRESENTE |
| 137 | RegistrarTitEnvios | Destinacion/montoSeg | FALTA | PRESENTE |
| 138 | RegistrarTitEnvios | Destinacion/montoSeg | FALTA | PRESENTE |
| 139 | RegistrarTitEnvios | Destinacion/codDivisaFob · codDivisaFle · codDivisaSeg | FALTA | PRESENTE |
| 140 | RegistrarTitEnvios | Destinacion/codDivisaFob · codDivisaFle · codDivisaSeg | FALTA | PRESENTE |
| 141 | RegistrarTitEnvios | Destinacion/codDivisaFob · codDivisaFle · codDivisaSeg | OK | PRESENTE |
| 142 | RegistrarTitEnvios | Destinacion/docAnexos/DocAnexo (codTipDoc 380, documento) | FALTA | AUSENTE |
| 143 | RegistrarTitEnvios | Destinacion/docAnexos/DocAnexo (codTipDoc 380, documento) | FALTA | AUSENTE |
| 144 | RegistrarTitEnvios | Destinacion/docAnexos/DocAnexo (codTipDoc 380, documento) | FALTA | AUSENTE |
| 145 | RegistrarTitEnvios | Envio/idFiscalATAMIC | OK | PRESENTE |
| 146 | RegistrarTitEnvios | Envio/idFiscalATAMIC | OK | PRESENTE |
| 147 | RegistrarTitEnvios | Envio/idFiscalATAMIC | OK | PRESENTE |
| 148 | RegistrarTitEnvios | Envio/lugOperOrigen/codLugOper | OK | PRESENTE |
| 149 | RegistrarTitEnvios | Envio/lugOperOrigen/codLugOper | OK | PRESENTE |
| 150 | RegistrarTitEnvios | Envio/lugOperOrigen/codLugOper | OK | PRESENTE |
| 151 | RegistrarTitEnvios | Envio/lugOperOrigen/codCiu | OK | PRESENTE |
| 152 | RegistrarTitEnvios | Envio/lugOperOrigen/codCiu | OK | PRESENTE |
| 153 | RegistrarTitEnvios | Envio/lugOperOrigen/codCiu | OK | PRESENTE |
| 154 | RegistrarTitEnvios | Envio/lugOperDestino/codCiu | OK | PRESENTE |
| 155 | RegistrarTitEnvios | Envio/lugOperDestino/codCiu | OK | PRESENTE |
| 156 | RegistrarTitEnvios | Envio/lugOperDestino/codCiu | OK | PRESENTE |
| 157 | RegistrarTitEnvios | Envio/lugOperDestino/codLugOper | FALTA | PRESENTE |
| 158 | RegistrarTitEnvios | Envio/lugOperDestino/codLugOper | FALTA | PRESENTE |
| 159 | RegistrarTitEnvios | Envio/lugOperDestino/codLugOper | FALTA | PRESENTE |
| 160 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/cantBultos · cantBultosTotFrac | OK | PRESENTE |
| 161 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/cantBultos · cantBultosTotFrac | OK | PRESENTE |
| 162 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/cantBultos · cantBultosTotFrac | OK | PRESENTE |
| 163 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/pesoBruto · pesoBrutoTotFrac | NO LLEGA (3 de 72; 69 OK) | PRESENTE |
| 164 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/pesoBruto · pesoBrutoTotFrac | OK | PRESENTE |
| 165 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/pesoBruto · pesoBrutoTotFrac | OK | PRESENTE |
| 166 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/codTipEmbalaje | OK | PRESENTE |
| 167 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/codTipEmbalaje | OK | PRESENTE |
| 168 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/codTipEmbalaje | OK | PRESENTE |
| 169 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/descMercaderia | FORMATO INCORRECTO (5 de 72; 67 OK) | PRESENTE, PRUEBA |
| 170 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/descMercaderia | FORMATO INCORRECTO (17 de 160; 143 OK) | PRESENTE, PRUEBA |
| 171 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/descMercaderia | OK | PRESENTE |
| 172 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/marcaNro | FORMATO INCORRECTO (7 de 72; 55 OK) | PRESENTE, PRUEBA |
| 173 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/marcaNro | FORMATO INCORRECTO (11 de 160; 147 OK) | PRESENTE, PRUEBA |
| 174 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/marcaNro | FORMATO INCORRECTO (11 de 91; 80 OK) | PRESENTE, PRUEBA |
| 175 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/indCargSuelt | OK | PRESENTE |
| 176 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/indCargSuelt | OK | PRESENTE |
| 177 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/indCargSuelt | OK | PRESENTE |
| 178 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/idContenedor | OK | PRESENTE |
| 179 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/idContenedor | OK | PRESENTE |
| 180 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/bultos/Bulto/idContenedor | OK | PRESENTE |
| 181 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/nroItem | OK | PRESENTE |
| 182 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/nroItem | OK | PRESENTE |
| 183 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/nroItem | OK | PRESENTE |
| 184 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/peso | NO LLEGA (3 de 72; 69 OK) | PRESENTE |
| 185 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/peso | OK | PRESENTE |
| 186 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/peso | OK | PRESENTE |
| 187 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/codIMO | FALTA | PRESENTE |
| 188 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/codIMO | FALTA | AUSENTE |
| 189 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/codIMO | OK (28) / FALTA (63) | PRESENTE |
| 190 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/indNCM | NO LLEGA (por decisión documentada) (66 de 72) | AUSENTE |
| 191 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/indNCM | NO LLEGA (por decisión documentada) (151 de 160) | AUSENTE |
| 192 | RegistrarTitEnvios | Envio/destinaciones/Destinacion/items/Item/indNCM | NO LLEGA (por decisión documentada) (60 de 91) | AUSENTE |
| 193 | RegistrarTitEnvios | contenedores/Contenedor/id | OK | PRESENTE |
| 194 | RegistrarTitEnvios | contenedores/Contenedor/id | OK | PRESENTE |
| 195 | RegistrarTitEnvios | contenedores/Contenedor/id | OK | PRESENTE |
| 196 | RegistrarTitEnvios | contenedores/Contenedor/codMedida | OK | PRESENTE |
| 197 | RegistrarTitEnvios | contenedores/Contenedor/codMedida | OK | PRESENTE |
| 198 | RegistrarTitEnvios | contenedores/Contenedor/codMedida | NO LLEGA (2 de 79; 77 OK) | PRESENTE |
| 199 | RegistrarTitEnvios | contenedores/Contenedor/condicion | OK | PRESENTE |
| 200 | RegistrarTitEnvios | contenedores/Contenedor/condicion | OK | PRESENTE |
| 201 | RegistrarTitEnvios | contenedores/Contenedor/condicion | FALTA | PRESENTE |
| 202 | RegistrarTitEnvios | contenedores/Contenedor/precintos/precinto | OK | PRESENTE |
| 203 | RegistrarTitEnvios | contenedores/Contenedor/precintos/precinto | OK | PRESENTE |
| 204 | RegistrarTitEnvios | contenedores/Contenedor/precintos/precinto | OK | PRESENTE |
| 205 | RegistrarTitEnvios | contenedores/Contenedor/accesorio | FALTA | AUSENTE |
| 206 | RegistrarTitEnvios | contenedores/Contenedor/accesorio | FALTA | AUSENTE |
| 207 | RegistrarTitEnvios | contenedores/Contenedor/accesorio | FALTA | AUSENTE |
| 208 | RegistrarMicDta | argRegistrarMicDtaParam/idTransaccion | OK | PRESENTE |
| 209 | RegistrarMicDta | argRegistrarMicDtaParam/idTransaccion | OK | PRESENTE |
| 210 | RegistrarMicDta | argRegistrarMicDtaParam/idTransaccion | OK | PRESENTE |
| 211 | RegistrarMicDta | micDta/codViaTrans | OK | PRESENTE |
| 212 | RegistrarMicDta | micDta/codViaTrans | OK | PRESENTE |
| 213 | RegistrarMicDta | micDta/codViaTrans | OK | PRESENTE |
| 214 | RegistrarMicDta | micDta/transportista/nombre | FALTA | PRESENTE |
| 215 | RegistrarMicDta | micDta/transportista/nombre | FALTA | PRESENTE |
| 216 | RegistrarMicDta | micDta/transportista/nombre | FALTA | PRESENTE |
| 217 | RegistrarMicDta | micDta/transportista/domicilio/nombreCalle | FALTA | PRESENTE |
| 218 | RegistrarMicDta | micDta/transportista/domicilio/nombreCalle | FALTA | PRESENTE |
| 219 | RegistrarMicDta | micDta/transportista/domicilio/nombreCalle | FALTA | PRESENTE |
| 220 | RegistrarMicDta | micDta/transportista/domicilio/ciudad | FALTA | PRESENTE |
| 221 | RegistrarMicDta | micDta/transportista/domicilio/ciudad | FALTA | PRESENTE |
| 222 | RegistrarMicDta | micDta/transportista/domicilio/ciudad | FALTA | PRESENTE |
| 223 | RegistrarMicDta | micDta/transportista/domicilio/codPostal | FALTA | PRESENTE |
| 224 | RegistrarMicDta | micDta/transportista/domicilio/codPostal | FALTA | PRESENTE |
| 225 | RegistrarMicDta | micDta/transportista/domicilio/codPostal | FALTA | PRESENTE |
| 226 | RegistrarMicDta | micDta/transportista/domicilio/estado | FALTA | PRESENTE |
| 227 | RegistrarMicDta | micDta/transportista/domicilio/estado | FALTA | PRESENTE |
| 228 | RegistrarMicDta | micDta/transportista/domicilio/estado | FALTA | PRESENTE |
| 229 | RegistrarMicDta | micDta/transportista/codPais | FALTA | PRESENTE |
| 230 | RegistrarMicDta | micDta/transportista/codPais | FALTA | PRESENTE |
| 231 | RegistrarMicDta | micDta/transportista/codPais | FALTA | PRESENTE |
| 232 | RegistrarMicDta | micDta/transportista/idFiscal | FALTA | PRESENTE |
| 233 | RegistrarMicDta | micDta/transportista/idFiscal | FALTA | PRESENTE |
| 234 | RegistrarMicDta | micDta/transportista/idFiscal | FALTA | PRESENTE |
| 235 | RegistrarMicDta | micDta/transportista/tipTrans | OK | PRESENTE |
| 236 | RegistrarMicDta | micDta/transportista/tipTrans | OK | PRESENTE |
| 237 | RegistrarMicDta | micDta/transportista/tipTrans | OK | PRESENTE |
| 238 | RegistrarMicDta | micDta/propVehiculo/nombre | FALTA | PRESENTE |
| 239 | RegistrarMicDta | micDta/propVehiculo/nombre | FALTA | PRESENTE |
| 240 | RegistrarMicDta | micDta/propVehiculo/nombre | FALTA | PRESENTE |
| 241 | RegistrarMicDta | micDta/propVehiculo/domicilio/nombreCalle | FALTA | PRESENTE, PRUEBA |
| 242 | RegistrarMicDta | micDta/propVehiculo/domicilio/nombreCalle | FALTA | PRESENTE, PRUEBA |
| 243 | RegistrarMicDta | micDta/propVehiculo/domicilio/nombreCalle | FALTA | PRESENTE, PRUEBA |
| 244 | RegistrarMicDta | micDta/propVehiculo/domicilio/ciudad | FALTA | PRESENTE, PRUEBA |
| 245 | RegistrarMicDta | micDta/propVehiculo/domicilio/ciudad | FALTA | PRESENTE, PRUEBA |
| 246 | RegistrarMicDta | micDta/propVehiculo/domicilio/ciudad | FALTA | PRESENTE, PRUEBA |
| 247 | RegistrarMicDta | micDta/propVehiculo/domicilio/codPostal | FALTA | AUSENTE |
| 248 | RegistrarMicDta | micDta/propVehiculo/domicilio/codPostal | FALTA | AUSENTE |
| 249 | RegistrarMicDta | micDta/propVehiculo/domicilio/codPostal | FALTA | AUSENTE |
| 250 | RegistrarMicDta | micDta/propVehiculo/codPais | FALTA | PRESENTE |
| 251 | RegistrarMicDta | micDta/propVehiculo/codPais | FALTA | PRESENTE |
| 252 | RegistrarMicDta | micDta/propVehiculo/codPais | FALTA | PRESENTE |
| 253 | RegistrarMicDta | micDta/propVehiculo/idFiscal | FALTA | PRESENTE |
| 254 | RegistrarMicDta | micDta/propVehiculo/idFiscal | FALTA | PRESENTE |
| 255 | RegistrarMicDta | micDta/propVehiculo/idFiscal | FALTA | PRESENTE |
| 256 | RegistrarMicDta | micDta/indEnLastre | OK | PRESENTE |
| 257 | RegistrarMicDta | micDta/indEnLastre | OK | PRESENTE |
| 258 | RegistrarMicDta | micDta/indEnLastre | OK | PRESENTE |
| 259 | RegistrarMicDta | micDta/vehiculo (codPais, patente, patentesRemol, marca, nroChasis, modChasis, anioFab, capTraccion, acceTipNum, precintos) | OK | AUSENTE |
| 260 | RegistrarMicDta | micDta/vehiculo (codPais, patente, patentesRemol, marca, nroChasis, modChasis, anioFab, capTraccion, acceTipNum, precintos) | OK | AUSENTE |
| 261 | RegistrarMicDta | micDta/vehiculo (codPais, patente, patentesRemol, marca, nroChasis, modChasis, anioFab, capTraccion, acceTipNum, precintos) | OK | AUSENTE |
| 262 | RegistrarMicDta | micDta/conductores/Conductor (nombre, tipDocIdent, nroDocIdent) | OK | AUSENTE |
| 263 | RegistrarMicDta | micDta/conductores/Conductor (nombre, tipDocIdent, nroDocIdent) | OK | AUSENTE |
| 264 | RegistrarMicDta | micDta/conductores/Conductor (nombre, tipDocIdent, nroDocIdent) | FALTA | PRESENTE |
| 265 | RegistrarMicDta | micDta/cargasSueltasIdTrack/cargaSueltaIdTrack | OK | AUSENTE |
| 266 | RegistrarMicDta | micDta/cargasSueltasIdTrack/cargaSueltaIdTrack | OK | AUSENTE |
| 267 | RegistrarMicDta | micDta/cargasSueltasIdTrack/cargaSueltaIdTrack | OK | AUSENTE |
| 268 | RegistrarMicDta | micDta/titTransContVaciosIdTrack/titTransContVacioIdTrack | OK | AUSENTE |
| 269 | RegistrarMicDta | micDta/titTransContVaciosIdTrack/titTransContVacioIdTrack | OK | AUSENTE |
| 270 | RegistrarMicDta | micDta/titTransContVaciosIdTrack/titTransContVacioIdTrack | OK | AUSENTE |
| 271 | RegistrarMicDta | micDta/contenedoresConCarga/idCont | OK | PRESENTE |
| 272 | RegistrarMicDta | micDta/contenedoresConCarga/idCont | OK | PRESENTE |
| 273 | RegistrarMicDta | micDta/contenedoresConCarga/idCont | OK | AUSENTE |
| 274 | RegistrarMicDta | micDta/rutasInf/RutInf/idRefUniTrs/idRefUniTr | OK | PRESENTE |
| 275 | RegistrarMicDta | micDta/rutasInf/RutInf/idRefUniTrs/idRefUniTr | OK | PRESENTE |
| 276 | RegistrarMicDta | micDta/rutasInf/RutInf/idRefUniTrs/idRefUniTr | OK | PRESENTE |
| 277 | RegistrarMicDta | micDta/rutasInf/RutInf/descRutItinerarios | OK | PRESENTE |
| 278 | RegistrarMicDta | micDta/rutasInf/RutInf/descRutItinerarios | OK | PRESENTE |
| 279 | RegistrarMicDta | micDta/rutasInf/RutInf/descRutItinerarios | OK | PRESENTE |
| 280 | RegistrarMicDta | micDta/rutasInf/RutInf/plazo | FALTA | PRESENTE |
| 281 | RegistrarMicDta | micDta/rutasInf/RutInf/plazo | FALTA | PRESENTE |
| 282 | RegistrarMicDta | micDta/rutasInf/RutInf/plazo | FALTA | PRESENTE |
| 283 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 284 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 285 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 286 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codAdu | OK | PRESENTE, SECUENCIA |
| 287 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codAdu | OK | PRESENTE, SECUENCIA |
| 288 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codAdu | OK | PRESENTE, SECUENCIA |
| 289 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codLugOper | OK | PRESENTE, SECUENCIA |
| 290 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codLugOper | OK | PRESENTE, SECUENCIA |
| 291 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/codLugOper | OK | PRESENTE, SECUENCIA |
| 292 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/fecha | FALTA | PRESENTE, SECUENCIA |
| 293 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/fecha | FALTA | PRESENTE, SECUENCIA |
| 294 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/fecha | FALTA | PRESENTE, SECUENCIA |
| 295 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/id · orden | OK | PRESENTE, SECUENCIA |
| 296 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/id · orden | OK | PRESENTE, SECUENCIA |
| 297 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[1]/id · orden | OK | PRESENTE, SECUENCIA |
| 298 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 299 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 300 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codPais · codCiu | OK | PRESENTE, SECUENCIA |
| 301 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codAdu | OK | PRESENTE, SECUENCIA |
| 302 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codAdu | OK | PRESENTE, SECUENCIA |
| 303 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codAdu | OK | PRESENTE, SECUENCIA |
| 304 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codLugOper | OK | PRESENTE, SECUENCIA |
| 305 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codLugOper | OK | PRESENTE, SECUENCIA |
| 306 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/codLugOper | OK | PRESENTE, SECUENCIA |
| 307 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/fecha | FALTA | PRESENTE, SECUENCIA |
| 308 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/fecha | FALTA | PRESENTE, SECUENCIA |
| 309 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/fecha | FALTA | PRESENTE, SECUENCIA |
| 310 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/id · orden | OK | PRESENTE, SECUENCIA |
| 311 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/id · orden | OK | PRESENTE, SECUENCIA |
| 312 | RegistrarMicDta | micDta/rutasInf/RutInf/eventosProg/EventoProg[2]/id · orden | OK | PRESENTE, SECUENCIA |
| 313 | RegistrarMicDta | micDta/embarcacion/codPais | OK | PRESENTE |
| 314 | RegistrarMicDta | micDta/embarcacion/codPais | FALTA | PRESENTE |
| 315 | RegistrarMicDta | micDta/embarcacion/codPais | FALTA | PRESENTE |
| 316 | RegistrarMicDta | micDta/embarcacion/id | FALTA | PRESENTE, PRUEBA |
| 317 | RegistrarMicDta | micDta/embarcacion/id | FALTA | PRESENTE, PRUEBA |
| 318 | RegistrarMicDta | micDta/embarcacion/id | FALTA | PRESENTE, PRUEBA |
| 319 | RegistrarMicDta | micDta/embarcacion/nombre | OK | PRESENTE |
| 320 | RegistrarMicDta | micDta/embarcacion/nombre | OK | PRESENTE |
| 321 | RegistrarMicDta | micDta/embarcacion/nombre | FALTA | PRESENTE |
| 322 | RegistrarMicDta | micDta/embarcacion/tipEmb | FALTA | PRESENTE |
| 323 | RegistrarMicDta | micDta/embarcacion/tipEmb | FALTA | PRESENTE |
| 324 | RegistrarMicDta | micDta/embarcacion/tipEmb | FALTA | PRESENTE |
| 325 | RegistrarMicDta | micDta/embarcacion/indIntegraConvoy | OK | PRESENTE |
| 326 | RegistrarMicDta | micDta/embarcacion/indIntegraConvoy | OK | PRESENTE |
| 327 | RegistrarMicDta | micDta/embarcacion/indIntegraConvoy | OK | PRESENTE |
| 328 | RegistrarMicDta | micDta/embarcacion/indSolicitaPres | OK | AUSENTE |
| 329 | RegistrarMicDta | micDta/embarcacion/indSolicitaPres | OK | AUSENTE |
| 330 | RegistrarMicDta | micDta/embarcacion/indSolicitaPres | OK | AUSENTE |
| 331 | RegistrarMicDta | micDta/embarcacion/idFiscalATARemol | OK | PRESENTE |
| 332 | RegistrarMicDta | micDta/embarcacion/idFiscalATARemol | OK | PRESENTE |
| 333 | RegistrarMicDta | micDta/embarcacion/idFiscalATARemol | OK | AUSENTE |
| 334 | RegistrarTitEnvios | argWSAutenticacionEmpresa/Token · Sign | OK | PRESENTE, PRUEBA |
| 335 | RegistrarTitEnvios | argWSAutenticacionEmpresa/CuitEmpresaConectada · TipoAgente · Rol | OK | PRESENTE |
| 336 | RegistrarMicDta | argWSAutenticacionEmpresa/Token · Sign | OK | PRESENTE, PRUEBA |
| 337 | RegistrarMicDta | argWSAutenticacionEmpresa/CuitEmpresaConectada · TipoAgente · Rol | OK | PRESENTE |
| 338 | RegistrarConvoy | argWSAutenticacionEmpresa/Token · Sign | OK | PRESENTE, PRUEBA |
| 339 | RegistrarConvoy | argWSAutenticacionEmpresa/CuitEmpresaConectada · TipoAgente · Rol | OK | PRESENTE |
| 340 | RegistrarConvoy | argRegistrarConvoyParam/idTransaccion | OK | PRESENTE |
| 341 | RegistrarConvoy | argRegistrarConvoyParam/idMicDtaRemol | FALTA | PRESENTE, PRUEBA |
| 342 | RegistrarConvoy | argRegistrarConvoyParam/idMicDta/idMicDta | FALTA | PRESENTE, PRUEBA |
| 343 | RegistrarTitEnvios | titulosTransContVacios/TitTransContVacio (todos los campos) | OK | AUSENTE |

## Integridad de evidencia privada

- `candidate-result.json`: SHA-256 `6cb7ab14d087de859dd1bb0c50b0a4caff346573dfa6bf6a7cd8775d48fcf42d`.
- `candidate-don-result.json`: SHA-256 `0d8e0858f5d78e8dfac86b434fbfcbfabb4efb975577872703f5cbebab9385f6`.
- `candidate-cuscar-result.json`: SHA-256 `7a8db402300aee5ff87c07c9f8404733403b39f7424fe3f0c01009e46bb2ae30`.
- `xml-schema-audit.json`: SHA-256 `a8574d12769e0c23ae646d2b6fdaf97d9c942ed7e460159b8d4e8e8d0a02163a`.
- `matrix-audit-private.json`: SHA-256 `d6819662755199c0d51dcddaa47dee56d384c19d9c88c26b8c41c4637311d22c`.
- `wsdl-schema.xsd`: SHA-256 `76346ca4dcd6802b1d713532c42e6180778fc6ebbabdaa61affe91cca175b339`.
