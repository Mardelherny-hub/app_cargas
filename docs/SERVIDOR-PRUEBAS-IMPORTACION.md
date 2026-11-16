# Servidor de pruebas de importación en asus02

Fecha: 16/11/2026 · Responsable: Work (equipo Charly) · Dirige: Claudia · Aprueba: Víctor

Arranca **después** de que Víctor dé el OK al traspaso a asus02 (`docs/TRASPASO-ASUS02.md`).

## Para qué

- Probar cualquier rama del plan (`docs/PLAN-IMPORTACION-VIAJES-EXISTENTES.md`) sin tocar la instancia de desarrollo ni su base.
- Volver la base a un estado conocido en segundos, antes de cada corrida.
- **Medir regresiones.** Las importaciones de hoy, sin viaje destino, tienen que dar exactamente lo mismo que en `main`. Hoy eso no se puede comprobar de forma objetiva, y con este servidor sí.
- Correr `php artisan test` completo sin riesgo. En desarrollo no se puede, porque los tests con `RefreshDatabase` vacían la base local.

## Diseño (simple, sin Docker)

Una segunda instancia de la app en la misma máquina:

| Pieza | Valor |
|---|---|
| Código | `/var/www/html/app_cargas_pruebas`, como `git worktree` del repo de desarrollo, en modo `--detach`. Cualquier rama se prueba con `git checkout --detach origin/<rama>` sin tocar el árbol de desarrollo. |
| Base | `app_cargas_pruebas`, cargada desde una **instantánea** (`base_inicial.sql`) tomada del dump del traspaso. |
| `.env` | copia del de desarrollo, con **el mismo `APP_KEY`** para poder leer los datos cifrados. Cambian `DB_DATABASE`, `APP_URL` y `APP_ENV=testing`. |
| `storage` | copia propia de `storage/app` de desarrollo. No comparte archivos con desarrollo. |
| Sitio | vhost propio de Apache, por ejemplo `http://pruebas-cargas.local`, para revisar en pantalla. |
| Cola | worker propio: `php artisan queue:work --stop-when-empty` dentro de cada corrida. |
| Muestras | `/var/www/html/muestras_importacion/`, de solo lectura. |
| Scripts e informes | `/var/www/html/pruebas_importacion/`, **fuera del repo**. El repo es público y las muestras son del cliente. |

## Lo que tiene que construir Work

1. **La instancia** descrita arriba, funcionando en el navegador con los usuarios de «Empresa QA Test».

2. **`restaurar_base.sh`:** borra la base de pruebas, la recrea desde `base_inicial.sql` y limpia los archivos importados de su `storage`. Tiene que tardar poco.

3. **`usar_rama.sh <rama>`:**
   - hace `git fetch` y deja el worktree en `origin/<rama>`;
   - corre `composer install` si cambió `composer.lock`;
   - corre `optimize:clear`;
   - corre `migrate` **solo sobre la base de pruebas**.

4. **`bateria_importacion.sh`:** importa cada muestra **por el mismo camino que usa la app**.
   - Ese camino es `ProcessManifestImportJob`: se loguea como el usuario y llama al parser elegido por `ManifestParserFactory` con las mismas opciones.
   - Antes de escribirlo, Work verifica en el código el constructor del job y el registro de `ImportTracking` que crea `ManifestImportController::store()`. No se inventan parámetros.
   - Por cada archivo registra:
     - el parser elegido;
     - el resultado (éxito, advertencias o error);
     - las cantidades de viajes, cargas, conocimientos, ítems y contenedores creados;
     - una **huella de los datos creados**: un volcado ordenado de los campos de negocio de esas filas, sin ids ni fechas de creación.
   - Al final **revierte** cada importación con la misma lógica de `ManifestImportController::revert()` y comprueba que los conteos de la base vuelven a los de antes.

5. **`comparar.sh <informe_A> <informe_B>`:** muestra las diferencias de huella entre dos corridas, por ejemplo `main` contra una rama de trabajo.

6. **Línea de base:** la batería corrida sobre `main` con todas las muestras disponibles. Queda guardada como referencia.

7. **Inventario de muestras:** qué archivos reales hay de cada uno de los 10 formatos. Se buscan en `muestras_importacion` y en los archivos conservados de importaciones anteriores, dentro de `storage` de desarrollo. El inventario dice qué formato tiene menos de 2 muestras, que es el mínimo del criterio de cierre.

Más adelante, cuando T1 publique el contrato, la batería suma el modo «agregar a viaje existente» con el caso de aceptación del cliente: convoy Observador + CARE I + CONAY I, los dos archivos de CARE I (CUSCAR y TXT), la reversión de un agregado y la repetición de una importación.

## Restricciones

- **Nada se envía a la Aduana.** La batería no llama a ninguna operación de envío de `app/Services/Simple/` ni de `app/Services/Webservice/`. Work verifica y documenta cómo elige la app el entorno de webservices por empresa (`WebserviceEnvironment::resolve`) y confirma que en la instancia de pruebas ninguna empresa puede enviar a producción.
- Sin acceso a stage ni a producción.
- **No modifica el código del repo.** Si para automatizar hace falta algo en la app, lo propone y no lo implementa.
- No toca la instancia ni la base de desarrollo, que solo se leen para copiar.
- Las muestras y los informes no se commitean.

## Criterio de aceptación

- Se ve `http://pruebas-cargas.local` con los usuarios de prueba.
- `restaurar_base.sh` deja la base idéntica a la instantánea: se comprueba con conteos.
- **La batería es determinista:** dos corridas seguidas sobre `main` dan huellas idénticas y `comparar.sh` no muestra diferencias.
- Cada reversión deja los conteos como estaban.
- `php artisan test` completo corre en la instancia de pruebas sin tocar la base de desarrollo.
- Están entregados el inventario de muestras y la línea de base.

## Devolución

- Rutas y uso de cada script.
- Resultado de la línea de base: formato por formato, con éxitos, advertencias y errores.
- Inventario de muestras y los faltantes.
- Cómo quedó resuelto el bloqueo de envíos.
- Limitaciones y lo que no se pudo verificar.

## Cómo se usa en el plan

- **QA cruzado:** cada rama de parsers (T2, T3, T4 y T5) se prueba con `usar_rama.sh` y `bateria_importacion.sh`, y se compara contra la línea de base. El resumen va como comentario en el PR de la rama.
- **Prueba integral final:** primero corre acá y después en stage, con el OK de Víctor para el deploy.
