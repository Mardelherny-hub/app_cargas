# Diagnóstico de codMedida en MIC/DTA · 24/11/2026

Rama `fix/cod-medida`, base `release/etapa-1` en `5bf70e1c0581f45ff56f0060e047b15103d6ad67`. La evidencia inicial corresponde a la [sección 7 del parte del 24/11](https://github.com/Mardelherny-hub/app_cargas/blob/2a70acd6/docs/partes/2026-11-24-code.md#7-fixprecintos-por-viaje-desde-45df364c).

## Qué requiere el generador

`SimpleXmlGenerator::writeMicDtaContainer()` toma `Container::argentina_container_code` o, si está vacío, `Container::containerType->iso_code`. Escribe ese valor como `codMedida` y lo rechaza si no tiene cuatro caracteres. El dato documental esperado es el código ISO 6346 de tamaño y tipo del equipo.

## CMSP · 72 contenedores

Archivo `cmsp_20261026201721_tkCm1a4G.EDI`, SHA-256 `fd25e441b6ab0faba3529cb0bfe81267ae1f4eadff6212b9939bf13cfec2cb92`. El inventario incluye una segunda copia de idéntico contenido.

| BL | Contenedores observados | Ejemplo de origen |
|---|---:|---|
| FNXCAM24P100075 | 49 | Segmento 14 `RFF+BM:FNXCAM24P100075` y segmento 33 `SGP+CAIU2037741+` |
| FNXCAM24P100076 | 23 | Segmento 470 `RFF+BM:FNXCAM24P100076` y segmento 489 `SGP+AMFU8823159+` |

Se cotejaron los 72 pares BL/contenedor con los segmentos SGP del archivo. El EDI tiene 72 SGP y cero EQD. El SGP identifica el número de contenedor y el texto `VACIO - REPOSICIÓN CMSP` documenta la condición de vacío. Los segmentos MEA informan mediciones de carga como peso y volumen. Falta el código de tipo y tamaño que CMSP lee del tercer elemento de EQD. Para un vacío sin EQD y sin un contenedor preexistente, `CmspEdiParser::createContainer()` guarda `container_type_id = null` y no asigna un código argentino. El generador queda sin valor de medida.

## Paraná · 4 contenedores

Archivo `parana_20261026165117_ewBJrLRO.xlsx`, hoja `EXPORT`, SHA-256 `b09b5a2f2c0f4669e7fb790944ba08474a5ec1a0446369cddcfd82a4fcac2fac`. El inventario incluye una segunda copia de idéntico contenido.

| Fila | BL, columna O | Contenedor, columna AX | CONTAINER TYPE, columna AY |
|---:|---|---|---|
| 59 | 251631578 | MAEU3444639 | 40FR |
| 60 | 251668247 | TLLU1550688 | 40FR |
| 143 | 252120135 | MAEU3516512 | 40FR |
| 144 | 252120135 | MAEU3437692 | 40FR |

`40FR` designa un flat rack de 40 pies. La designación de origen no aporta el tamaño detallado y la variante de estructura requeridos para identificar un código ISO único. Las cuatro filas carecen de un código ISO de flat rack explícito. `ParanaExcelParser::findExistingContainerType()` devuelve `null` para `40FR` y `createContainer()` preserva la etiqueta en `webservice_data.parana_data.container_type`. La etiqueta del archivo queda disponible para consulta y el generador sólo consume el código argentino o la relación de catálogo. [Tabla de tipo ISO 6346 del BIC](https://www.bic-code.org/type-code-designation/) · [Equipos especiales de Maersk](https://www.maersk.com/industry-sectors/special-cargo/out-of-gauge-cargo).

## Verificación

Se cruzó la evidencia guardada del QA con las muestras. Se reprodujeron en memoria los 76 mensajes exactos llamando al generador de la rama base con los números de contenedor y los campos de medida ausentes. El resultado fue 72 rechazos en CMSP y 4 en Paraná. La reproducción invocó el método del generador y no repitió las importaciones en la instancia B.

Los 76 casos requieren que el origen aporte el código exacto del equipo o que pueda verificarse en su ficha documental. El diagnóstico no produjo cambios de parser ni envíos a Aduana.

El operador completa `codMedida` con el código exacto del equipo, verificado en la documentación de origen o en la ficha del contenedor.
