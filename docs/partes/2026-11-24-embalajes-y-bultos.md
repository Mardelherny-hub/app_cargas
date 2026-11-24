# Embalajes documentales y bultos por contenedor · 24/11/2026

Rama `fix/embalajes-y-bultos`, base `release/etapa-1` en `179a03e821f6bcc61df34e7c6cef501ba3ba6074`, HEAD `e320243daacbc0c52a7462d6ef934b8efb0d459e`.

| Commit | Parser | Prueba de regresión |
|---|---|---|
| `dfd34a08` | `ParanaExcelParser.php` | `ParanaExcelParserCoreIntegrityTest.php` |
| `4df16c32` | `G2OceanXmlParser.php` | `G2OceanXmlParserCoreIntegrityTest.php` |
| `1e1bc76c` | `KlineDataParser.php` | `KlineDataParserMeasurementIntegrityTest.php` |
| `16abe4fb` | `DonToyoExcelParser.php` | `DonToyoExcelParserTest.php` |
| `25cfb697` | `ParanaExcelParser.php` | `ParanaExcelParserCoreIntegrityTest.php`, caso CTNS |
| `10811b24` | `KlineDataParser.php` | `KlineDataParserMeasurementIntegrityTest.php`, caso VEHICLES |
| `254fbc4d` | `ParanaExcelParser.php` | `ParanaExcelParserCoreIntegrityTest.php`, casos BIG BAGS y CAS |
| `e320243d` | `KlineDataParser.php` | `KlineDataParserMeasurementIntegrityTest.php`, advertencias persistidas |

## Muestras reales

| Parser | Archivo | Ítems | Sin `packaging_code` en la base | Sin `packaging_code` con el cambio |
|---|---|---:|---:|---:|
| Paraná | `b09b5a2f…xlsx` · EXPORT | 253 | 253 | 0 |
| Paraná | `PARANA_ABX_2620S (1).xlsx` | 11 | 11 | 11 |
| G2Ocean | `0192d43f…xml` · Raven Arrow | 12 | 12 | 0 |
| K-Line | `89830364…DAT` | 7 | 7 | 0 |
| Don Toyo | `DON TOYO V.0626S - RESUMEN MANI.xls` | 75 | 67 | 67 |

Se ejecutaron los extractores y la persistencia de ítems con los archivos completos en SQLite en memoria, primero con los parsers de la base y luego con las correcciones. Los arneses de Paraná, G2Ocean y K-Line aislaron la persistencia de ítems de la creación del viaje y de las partes. Don Toyo se probó a través de `parse()` con un catálogo aislado.

## Equivalencias verificadas

Las tablas indican etiquetas tal como aparecen en los archivos. El código acepta las variantes exactas enumeradas en cada parser, incluidos los plurales con paréntesis. Las etiquetas desconocidas conservan el campo vacío y generan una advertencia. [Vocabulario UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

### Paraná · columna BB, `PACK_TYPE`

| Etiqueta del archivo | Código | Muestra y cantidad |
|---|---|---|
| BAGS | BG | EXPORT, 55 |
| BOX / BOXES | BX | EXPORT, 3 / 12 |
| CARTONS / CTNS | CT | EXPORT, 17 / 3 |
| CASES / CAS | CS | EXPORT, 1 / 14 |
| DRUMS | DR | EXPORT, 18 |
| JUMBO BAGS / BIG BAGS | JB | EXPORT, 4 / 23 |
| PACKAGE / PACKAGES | PK | EXPORT, 20 / 27 |
| PALLETS | PX | EXPORT, 9 |
| PIECES | PP | EXPORT, 11 |
| RACKS | RK | EXPORT, 1 |
| REELS | RL | EXPORT, 9 |
| ROLLS | RO | EXPORT, 8 |
| SKIDS | SI | EXPORT, 1 |
| TANK CYLINDRICAL | TY | EXPORT, 2 |
| TANK RECTANGULAR | TK | EXPORT, 10 |
| UNITS | UN | EXPORT, 5 |
| T | Vacío. MIC/DTA devuelve CN | ABX 2620S, 11 |

La documentación de manifiestos de U.S. Census identifica CTNS como abreviatura de cartons. Rec. 21 asigna CT a Carton. [Documentación AES](https://www2.census.gov/programs-surveys/trade/aes/documentlibrary/aesparticipantsdata.html) · [UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

La documentación de fabricantes de FIBC identifica BIG BAGS y JUMBO BAGS como nombres del mismo envase flexible. Rec. 21 asigna JB a Bag, jumbo. [SafeFlex](https://safeflex.org/resources/) · [UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

El documento AES usado para CTNS no enumera CAS. La guía oficial de manifiestos de U.S. Customs and Border Protection confirma CAS como Case en el tipo de embalaje PAC/C202/7065. Rec. 21 asigna CS a Case. [Guía CBP, pág. 82](https://www.cbp.gov/sites/default/files/2024-10/ImportTruck_IG_Standard_CUSCAR_Mfst_2024_Oct_30_508.pdf) · [UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

Los 11 ítems de ABX 2620S conservaron el tipo local CONTENEDOR y su vínculo real al contenedor en SQLite. La invocación directa de `SimpleXmlGenerator::micDtaPackagingCode()` devolvió `CN` en los 11 casos.

### G2Ocean · `pkgType`

| Etiqueta del archivo | Código | Muestra y cantidad |
|---|---|---|
| BUNDLES | BE | Raven Arrow, 1 |
| COILS | CL | Raven Arrow, 3 |
| PACKAGES | PK | Raven Arrow, 6 |
| PIECES | PP | Raven Arrow, 2 |

El caso sin `pkgType` se verificó con XML de prueba. Conserva la clasificación interna que usa PACKAGES y guarda `packaging_code` vacío.

### K-Line · `CMMDREC0`

| Etiqueta del archivo | Código | Muestra y cantidad |
|---|---|---|
| UNITS | UN | `89830364…DAT`, 4 |
| VEHICLES | VN | `89830364…DAT`, 3 |

### Don Toyo · descripción de mercadería

| Etiqueta del archivo | Código existente | Muestra y cantidad |
|---|---|---|
| CARTONS | CT | Don Toyo V.0626S, 7 |
| FARDOS | BL | Don Toyo V.0626S, 1 |

En Don Toyo, `container_shipment_item.package_quantity` quedó completo en 52 de los 532 vínculos, todos de BL con un solo contenedor y con la cantidad exacta del BL. Los 480 vínculos de BL con varios contenedores quedaron vacíos. En la base los 532 estaban vacíos.

## Resultado de MIC/DTA para los ítems originalmente sin código

Se invocó `SimpleXmlGenerator::micDtaPackagingCode()` con los ítems persistidos y el contenedor recuperado de su vínculo, cuando correspondía. SQLite reprodujo el catálogo de embalajes del respaldo local `base_limpia.sql`, incluidos los códigos de webservice y UN/ECE. La comparación usa el SHA previo a los ajustes `16abe4fb` y el HEAD publicado.

| Muestra | Ítems verificados | Tipo y vínculo | Resultado previo | Resultado actual |
|---|---:|---|---|---|
| Paraná EXPORT | 40 | A / A GRANEL, con vínculo | 40 errores | 3 CT, 23 JB y 14 CS |
| Paraná ABX 2620S | 11 | T / CONTENEDOR, con vínculo | 11 CN | 11 CN |
| K-Line | 3 VEHICLES | N / NO RETORNABLE, sin vínculo | 3 errores | 3 VN |
| Don Toyo | 67 | T / CONTENEDOR, con vínculo | 67 CN | 67 CN |

En `10811b24` faltaba código en 37 ítems de Paraná EXPORT y los 37 daban `falta codTipEmbalaje EDIFACT de 2 caracteres`. Con el ajuste, los 253 ítems de EXPORT devuelven su código explícito sin excepciones. El respaldo CN exige CONTENEDOR y un vínculo. VEHICLES es el plural de Vehicle, que Rec. 21 codifica como VN. [UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

## Historial de K-Line

La pantalla inmediata podía mostrar «con advertencias» porque leía el resultado del parser. Al cerrar el registro, K-Line guardaba `completed` y omitía el texto y el conteo. La prueba de regresión reprodujo `warnings = null` al recargar el registro en SQLite. Ahora el cierre guarda el texto y el conteo en la misma actualización que `completed_with_warnings`. La prueba también comprueba el caso `completed` con lista vacía y conteo cero.

## Pruebas

- Los cuatro tests nuevos fallaron con sus parsers de la base y pasaron con la corrección correspondiente.
- Comparador de muestras en la base. 4 pruebas y 511 aserciones.
- Los casos CTNS y VEHICLES fallaron contra `16abe4fb` y pasaron con los cambios.
- Generador MIC/DTA con el SHA anterior a los ajustes y muestras reales. 3 pruebas y 864 aserciones.
- La regresión con BIG BAGS y CAS falla contra `10811b24` y pasa con la corrección. La prueba de persistencia de advertencias de K-Line también falla con el cierre anterior y pasa con el nuevo.
- Pruebas de los cuatro parsers, contrato MIC/DTA, comparador de muestras y verificación del generador con los ajustes finales. 65 pruebas y 1027 aserciones.
- `php -l` pasó en los ocho archivos PHP. Cada parser y su prueba se publicaron en un commit propio con pathspec.
- Validación local aislada. Sin Stage, sin envíos a Aduana y sin acceso a producción.
