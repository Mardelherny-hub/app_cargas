# Número de viaje y datos de destinación · 25/11/2026

Las tres ramas parten de `release/etapa-1` en `eef33945723142ba3d814c1e29cb45b3a07e488c`. Los SHA se verificaron en `origin` después del push.

| Rama | SHA |
|---|---|
| `fix/numero-viaje-operador` | `bf85cf1567a48d7f4d493068369dbb3a00d03b21` |
| `fix/login-destinacion` | `f9703a852f28a573c580c2b9d9f5805f888dfb7a` |
| `fix/guaran-permiso` | `1cc34fb6128ee76cf975118ea2eb9113d7865bb1` |

## Número de viaje

Los parsers toman el número ingresado cuando se informa. Con el formulario vacío usan el número extraído del archivo. K-Line conserva su normalización `KLINE-` y base y Compat comparten el método. En cada caso el control de unicidad recibe el mismo número que se persiste.

| Parser | Commit | Muestra (prefijo SHA-256) | Viaje extraído |
|---|---|---|---|
| Navsur | `fc3753cd` | `a815b47b…` | 0092B |
| Login | `68467cfc` | `a12323f4…` | 004N |
| K-Line | `0a2bca67` | `89830364…` | Ausente |
| G2Ocean | `64769311` | `0192d43f…` | 2501 |
| Guaraní | `9c5e246a` | `f776d4d1…` | ABX 2525S |
| Paraná | `763d63ed` | `b09b5a2f…` | V022NB |

15 pruebas y 42 aserciones en la suite focalizada. El arnés de muestras reales extrajo los números con cada parser y usó la creación de viaje con catálogos aislados. Pasaron 7 pruebas y 26 aserciones de prioridad, persistencia y unicidad. La muestra K-Line carece de número y se verificó la entrada del operador.

### Archivo sin número de viaje

Las validaciones previas de Navsur, Login, Guaraní y Paraná aceptan la ausencia del número en el archivo. La creación del viaje usa el número del formulario y controla su unicidad. Si ambas fuentes están vacías, la importación indica «El archivo no informa número de viaje. Escribilo en el formulario.».

| Parser | Commit |
|---|---|
| Navsur | `cb976437` |
| Login | `e9aa0dcd` |
| Guaraní | `1ecc92f7` |
| Paraná | `bf85cf15` |

Los cuatro tests fallaron antes del cambio y pasaron después. La suite focalizada, incluidos los números extraídos de muestras reales, pasó con 57 pruebas y 226 aserciones. Al quitar el número en memoria de las muestras reales, la extracción y la validación previas avanzaron en 48 BL de Navsur, 123 BL de Login, 235 filas de Guaraní y el encabezado de Paraná.

## Login

Prototipo de solo lectura previo a PHP. 123 BL, 94 candidatos en la descripción y un candidato repetido en ExportReferences para el mismo BL. El test de persistencia con el archivo real guardó `id_decla` en 94 BL, mantuvo las 123 descripciones y verificó `24001ET01000075P` en el BL `004N904406352`. La prueba aisló la persistencia de las partes y del catálogo de puertos. Pasó 1 prueba y 127 aserciones, además de 10 pruebas y 80 aserciones en la suite focalizada.

Los valores válidos distintos en un mismo BL dejan `id_decla` vacío y generan una advertencia persistida con el BL y ambos valores. El manejo previo de PE en `permiso_embarque` se conserva. Si hay conflicto con PE, RegistrarTitEnvios conserva su respaldo vigente a ese campo. RegistrarEnvios consume `id_decla` directamente.

## Guaraní

El parser no asignaba `booking_number`. Ahora lo usa para `MLO_BL_NR` al crear el BL. El test con la fila real del BL `RBLCCPMI004407-03` verificó que `746PY0065109` queda guardado como booking y que `permiso_embarque` e `id_decla` quedan vacíos. Pasó 1 prueba y 5 aserciones con la fila real y 17 pruebas y 33 aserciones en la suite focalizada. El cambio se aplica a importaciones nuevas.

Las regresiones fallaron antes de sus correcciones y pasaron después. `php -l` pasó en los archivos PHP modificados. Las pruebas usaron SQLite en memoria. Sin Stage, producción ni envíos a Aduana.
