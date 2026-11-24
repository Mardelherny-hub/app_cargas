# Embalajes documentales y bultos por contenedor · 24/11/2026

Rama `fix/embalajes-y-bultos`, base `release/etapa-1` en `179a03e821f6bcc61df34e7c6cef501ba3ba6074`, HEAD `16abe4fbfb152466858eae368ba44c52f741357c`.

| Commit | Parser | Prueba de regresión |
|---|---|---|
| `dfd34a08` | `ParanaExcelParser.php` | `ParanaExcelParserCoreIntegrityTest.php` |
| `4df16c32` | `G2OceanXmlParser.php` | `G2OceanXmlParserCoreIntegrityTest.php` |
| `1e1bc76c` | `KlineDataParser.php` | `KlineDataParserMeasurementIntegrityTest.php` |
| `16abe4fb` | `DonToyoExcelParser.php` | `DonToyoExcelParserTest.php` |

## Muestras reales

| Parser | Archivo | Ítems | Sin `packaging_code` en la base | Sin `packaging_code` con el cambio |
|---|---|---:|---:|---:|
| Paraná | `b09b5a2f…xlsx` · EXPORT | 253 | 253 | 40 |
| Paraná | `PARANA_ABX_2620S (1).xlsx` | 11 | 11 | 11 |
| G2Ocean | `0192d43f…xml` · Raven Arrow | 12 | 12 | 0 |
| K-Line | `89830364…DAT` | 7 | 7 | 3 |
| Don Toyo | `DON TOYO V.0626S - RESUMEN MANI.xls` | 75 | 67 | 67 |

Se ejecutaron los extractores y la persistencia de ítems con los archivos completos en SQLite en memoria, primero con los parsers de la base y luego con las correcciones. Los arneses de Paraná, G2Ocean y K-Line aislaron la persistencia de ítems de la creación del viaje y de las partes. Don Toyo se probó a través de `parse()` con un catálogo aislado.

## Equivalencias verificadas

Las tablas indican etiquetas tal como aparecen en los archivos. El código acepta las variantes exactas enumeradas en cada parser, incluidos los plurales con paréntesis. Las etiquetas desconocidas conservan el campo vacío y generan una advertencia. [Vocabulario UN/ECE Rec. 21](https://service.unece.org/trade/uncefact/vocabulary/rec21/).

### Paraná · columna BB, `PACK_TYPE`

| Etiqueta del archivo | Código | Muestra y cantidad |
|---|---|---|
| BAGS | BG | EXPORT, 55 |
| BOX / BOXES | BX | EXPORT, 3 / 12 |
| CARTONS | CT | EXPORT, 17 |
| CASES | CS | EXPORT, 1 |
| DRUMS | DR | EXPORT, 18 |
| JUMBO BAGS | JB | EXPORT, 4 |
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
| BIG BAGS / CAS / CTNS | Vacío, con advertencia | EXPORT, 23 / 14 / 3 |
| T | Vacío. MIC/DTA devuelve CN | ABX 2620S, 11 |

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
| VEHICLES | Vacío, con advertencia | `89830364…DAT`, 3 |

### Don Toyo · descripción de mercadería

| Etiqueta del archivo | Código existente | Muestra y cantidad |
|---|---|---|
| CARTONS | CT | Don Toyo V.0626S, 7 |
| FARDOS | BL | Don Toyo V.0626S, 1 |

En Don Toyo, `container_shipment_item.package_quantity` quedó completo en 52 de los 532 vínculos, todos de BL con un solo contenedor y con la cantidad exacta del BL. Los 480 vínculos de BL con varios contenedores quedaron vacíos. En la base los 532 estaban vacíos.

## Pruebas

- Los cuatro tests nuevos fallaron con sus parsers de la base y pasaron con la corrección correspondiente.
- Comparador de muestras en la base. 4 pruebas y 511 aserciones.
- Pruebas de los cuatro parsers, contrato MIC/DTA y comparador de muestras con los cambios. 64 pruebas y 756 aserciones.
- `php -l` pasó en los ocho archivos PHP. Cada parser y su prueba se publicaron en un commit propio con pathspec.
- Validación local aislada. Sin Stage, sin envíos a Aduana y sin acceso a producción.
