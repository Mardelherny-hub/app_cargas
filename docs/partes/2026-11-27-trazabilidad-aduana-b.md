# Correcciones de trazabilidad MIC/DTA · 27/11/2026

Rama de trabajo `fix/trazabilidad-aduana-b`, con base `9863e6f9` y SHA final `d96a04285bc9c7f324823ae6342f669aa89c4381`.

| Commit | Cambio |
| --- | --- |
| `aa2864c3` | Lee los rótulos RUC de MSC TXT y CUSCAR, incluido el tipo documental explícito. |
| `41723bb3` | Resuelve el país de las partes CUSCAR sin identificación fiscal y avisa cuando recurre a Argentina. |
| `f8e7abb7` | Incorpora 45R5 al catálogo y retira su equivalencia con el alias 40RH en CUSCAR. |
| `d96a0428` | Guarda en el historial los avisos por longitud de descripción, observaciones y razón social. |

## Antes y después con muestras completas

Se importó cada archivo en una SQLite nueva tanto con la base como con la rama, con los catálogos de la copia local de QA. Los conteos de BL e ítems coincidieron. Se ejecutó la comprobación de longitudes del job sobre cada importación y se compararon las descripciones y razones sociales almacenadas antes y después de esa comprobación.

| Muestra | BL | Ítems | Avisos de marcas | Avisos `descMercaderia` | Avisos `obsDeclaAduInter` | Avisos por razón social |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `CAREI-2605ATXT..txt` | 39 | 72 | 7 → 7 | 0 → 5 | 0 → 5 | 0 → 0 |
| `CONAYI-2605ATXT..txt` | 55 | 160 | 11 → 11 | 0 → 17 | 0 → 2 | 0 → 2 |
| `CAREI-2506AHL.1` | 31 | 91 | 11 → 11 | 0 → 0 | 0 → 1 | 0 → 0 |
| Total | 125 | 323 | 29 → 29 | 0 → 22 | 0 → 8 | 0 → 2 |

Los dos avisos de razón social corresponden a los roles consignatario y notificatario del BL `MEDUWU746584`, de 52 caracteres cada uno. Las advertencias quedaron en el historial de importación y los textos conservaron todos sus caracteres.

- **RUC.** Se prototipó la búsqueda sobre los archivos antes de editar PHP. En CAREI TXT, el RUC de `MEDUXX077639` pasó de vacío a `800133668`. En CAREI CUSCAR, el RUC de EASTSUN PARAGUAY S.A. en los NAD de `HLCUSHA2601HAXN8` pasó de vacío a `801165989`. Los tests cubren extracción, limpieza y detección del tipo documental.
- **País.** En CAREI CUSCAR, AGILEWAY TECH LIMITED (`HLCUNG1260496485`, `KWAI CHUNG HK`) y EASTSUN CHEMICAL CO.,LTD. (`HLCUSHA2601HAXN8`, `HONG-KONG`) pasaron de Argentina a Hong Kong. Otras cinco resoluciones de parte recurrieron a Argentina y guardaron el aviso correspondiente. Las fichas existentes conservan el país registrado bajo las reglas de reutilización de clientes.
- **ISO.** Para `HLBU9748451` y `HLBU9819750` del BL `HLCUSHA2604AYDA1`, el generador local escribió `codMedida=45R1` con la base y `codMedida=45R5` con la rama. La migración inserta una sola fila. Las medidas internas, pesos y volumen obligatorios usan las referencias de catálogo de 40RH. La migración conserva los tipos y contenedores existentes. [BIC, designaciones ISO 6346](https://www.bic-code.org/type-code-designation/)

## Pruebas

- `php -l` en todos los archivos PHP modificados y `git diff --check` contra la base.
- PHPUnit de parsers, jobs y extracción fiscal con catálogos en SQLite local: base 384 tests y 1.278 aserciones, rama 389 tests y 1.304 aserciones. Ambas ejecuciones pasaron.
- Importaciones completas y generación local del fragmento de contenedor con los precintos de las muestras. Las pruebas usaron bases aisladas y se ejecutaron sin envíos aduaneros.
