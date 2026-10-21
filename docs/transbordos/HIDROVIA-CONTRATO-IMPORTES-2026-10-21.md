# Hidrovía — Importes FOB, flete y seguro en envíos multibarcaza

**Revisión:** 21/10/2026. **Alcance:** `wgesregsintia2 / RegistrarTitEnvios`, un conocimiento de transporte con varios `Envio` en distintas barcazas. Sólo análisis y QA local, sin SOAP ni datos modificados.

## 1. Evidencia oficial: qué se puede afirmar

**Manual del Desarrollador de ARCA, versión 1.05 de 06/09/2023:**
https://arca.gob.ar/hidrovia-parana/documentos/Manual-del-Desarrollador-Hidrovia-RegistroATA.pdf

- Página impresa **9–10** (PDF índice 8–9): `RegistrarTitEnvios` registra un solo título con **un envío por medio de transporte/barcaza**, incluso cuando el mismo título se divide en cuatro barcazas. La respuesta devuelve un **TRACK por envío**.
- Página impresa **10** (PDF índice 9): para `montoFob`, `montoFlete` y `montoSeg`, el manual declara **carácter opcional**. Si se informan importes, deben informarse sus divisas, y las monedas deben coincidir con la declaración detallada original.
- En esa misma página se indica una excepción específica: **una sola destinación asociada al título, título no fraccionado y precumplido cerrado con unidades conforme «SI»**. Bajo ese conjunto de condiciones los importes no deben declararse y se toman de la declaración detallada. **No extrapolar la excepción a todos los transbordos:** el propio manual contempla excepciones al requisito de precumplido para transbordos de carga suelta.
- Página impresa **61** (PDF índice 60), estructura `Destinacion`: los tres montos y sus monedas aparecen como **no requeridos** en la descripción funcional.
- Historial, página impresa **2**: la versión 1.05 incorporó explícitamente la opcionalidad de los montos y de las monedas.

**Contrato técnico descargado el 21/10/2026, sólo lectura:**

- Homologación: `https://wsaduhomoext.afip.gob.ar/diav2/wgesregsintia2/wgesregsintia2.asmx?WSDL`.
- Producción: `https://webservicesadu.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx?WSDL`.
- Tipo XSD `Destinacion` en ambos WSDL: `montoFob`, `montoFlete`, `montoSeg` son `xsd:decimal`, **sin `minOccurs="0"` y sin `nillable="true"`**. Por lo tanto, **la estructura exige los tres elementos**, aunque la descripción funcional diga que son opcionales.
- Los elementos `codDivisaFob`, `codDivisaFle` y `codDivisaSeg` sí tienen `minOccurs="0"` en ambos WSDL.

**Conclusión documental:** existe una diferencia entre la opcionalidad *funcional* de los montos y su obligatoriedad *estructural* en el WSDL. **No es seguro omitir los nodos XML** sin homologar cómo interpreta ARCA ese caso. Tampoco es seguro repetir el monto total del título en cada barcaza.

## 2. Estado comprobado de `app_cargas`

- `app/Services/Simple/SimpleXmlGenerator.php`: el MIC/DTA ordinario escribe los tres montos por `Destinacion` a partir de `BillOfLading.declared_value`, `freight_amount` e `insurance_amount`; cuando faltan, la implementación existente escribe cero. Esa compatibilidad histórica **no demuestra** la regla comercial para dos o más envíos de un mismo título.
- `app/Services/Simple/HidroviaTransshipmentRegistrarTitEnviosXmlAssembler.php`: reproduce la `Destinacion` original en cada `Envio` pero **rechaza** el ensamblado si cualquiera de los tres importes originales no es cero. Esta protección evita hoy duplicar importes comerciales entre barcazas.
- `tests/Unit/Services/Webservice/HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest.php`: `test_nonzero_fob_freight_and_insurance_are_each_blocked` fija ese rechazo por separado para los tres campos.
- `HidroviaTransshipmentReadinessService` prepara pesos/bultos/contenedores por barcaza, **no importes comerciales por destinación**. El envío multibarcaza no está conectado al método SOAP productivo y sigue bloqueado.

## 3. Lo que NO está certificado

1. Si, cuando hay importes reales distintos de cero en un BL transbordado, ARCA exige importes específicos por `Envio`, permite utilizar cero con recuperación automática de la destinación o espera otra representación.
2. Qué condiciones de la excepción por **precumplido cerrado** son aplicables a los conocimientos de transbordo que importa realmente el cliente.
3. Si ARCA acepta el XML estructuralmente válido con ceros **sin atribuir erróneamente valor cero a la mercancía**. La validación XSD no demuestra aceptación de negocio.
4. Aceptación real de un título con varios `Envio`, TRACKs distintos y montos/monedas correctos. No contamos con respuesta de homologación que certifique ese conjunto.

## 4. Decisión de implementación y próximo control

- **Mantener el bloqueo** del envío convencional de los viajes con conocimientos marcados como Transbordos. No activar transmisión SOAP multibarcaza todavía.
- **No prorratear por peso, bultos o número de barcazas** ni repetir FOB/flete/seguro completos en cada envío: no existe una regla oficial verificada que autorice hacerlo.
- **No suprimir simplemente los tres nodos XML**: el WSDL de ambos ambientes los exige.
- Siguiente paso seguro: obtener un caso documental real de transbordo con `idDecla`, tipo/estado de destinación, condición de precumplido, valores comerciales y barcazas; consultar a ARCA/homologación cuál es la representación válida de los importes en cada `Envio` para ese caso. El manual identifica `hpp_consultas_informaticas@afip.gob.ar` como contacto funcional y `webservices-desa@afip.gob.ar` para homologación/autorización (página impresa 4).
- Una vez confirmada la regla, **un solo cambio por iteración** sobre el ensamblador, con test de cero/no cero, XSD y prueba controlada de homologación antes de habilitar la transmisión. No tocar el generador MIC/DTA ordinario sin una necesidad comprobada.

**Estado:** investigación documental realizada; contradicción documentada; regla comercial de montos no determinada. Sin cambios productivos, sin transmisión y sin deploy.
