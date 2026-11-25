# Don Toyo · CUIT del cargador y bultos por vínculo · 25/11/2026

Rama `fix/don-toyo-cuit-bultos` desde `release/etapa-1` en `f6be99552e0912420613b19ba4e7503bce29ccdf`. HEAD publicado `3e6d04ef1ef04cbb21f5e2f21cbff66a0b689df8`.

| Commit | Regla |
|---|---|
| `7f27526e` | Resolver el cargador por CUIT SIM validado |
| `3e6d04ef` | Guardar cero explícito en vínculos de BL con varios contenedores |

Ambos commits modifican `DonToyoExcelParser.php` y `DonToyoExcelParserTest.php`. Se usa el validador argentino existente y se reutiliza el criterio compartido de coincidencia nominal. Un CUIT SIM vacío o inválido conserva la resolución anterior por nombre y deja una advertencia con el BL y el valor de origen. Los tests cubren coincidencia por CUIT y documento, ficha homónima sin tax_id, ficha con otro tax_id, CUIT vacío e inválido y un mismo nombre con dos CUIT distintos.

## Muestra real

Se importó `DON TOYO V.0626S - RESUMEN MANI.xls` en SQLite en memoria con las fichas de cargador y homónimos del respaldo local `base_limpia.sql` y catálogos aislados. Se llamaron por separado los métodos del generador `writeRemitente()` y `writeMicDtaBulto()` para medir ambos errores. La prueba cubrió 75 BL, 75 ítems y 532 vínculos.

| Medición | Base | Con ambos cambios |
|---|---:|---:|
| Remitente sin idFiscal válido | 75 | 0 |
| cantBultos inválida | 480 | 0 |
| Fichas completadas | 0 | 0 |
| Fichas creadas | 0 | 0 |
| Vínculos con cero explícito | 0 | 480 |
| Vínculos con cantidad nula | 480 | 0 |

La muestra reutiliza dos fichas argentinas existentes, una para 20 BL y otra para 55. Los tests aislados sí ejercitan el alta y el completado de una ficha. Los 52 vínculos de BL con un solo contenedor conservan su total y los 75 ítems mantienen la cantidad del archivo.

Una vez superado idFiscal, el arnés registró 75 observaciones de domicilio porque el catálogo aislado no incluyó contactos. La corrida verifica los dos errores de esta tarea y no verifica el XML completo.

Las tres regresiones fallaron antes de su corrección. La suite focalizada con la muestra pasó con 28 pruebas y 218 aserciones. `php -l` pasó en ambos archivos. Validación local aislada, sin envíos a Aduana.
