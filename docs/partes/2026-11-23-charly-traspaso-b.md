# APP_CARGAS — Traspaso directo de datos de QA B ASUS02 → victor-red-byte (23/11/2026)

## Alcance y reglas

Transferencia **directa por red local cifrada HTTPS** de ASUS02 `192.168.100.201` a victor-red-byte `192.168.100.108`. No se utilizó Git para transferir archivos, muestras, dump ni configuración. No se hizo checkout, reset, restauración MySQL ni arranque de QA en el destino. SSH/rsync no estaban disponibles, porque ambas máquinas rechazaban conexiones al puerto 22; el servidor HTTPS temporal restringido a la IP destino fue **detenido** al finalizar.

## Estado de Git previo en ASUS02

- Repositorio `/var/www/html/app_cargas`: HEAD `f8ea3370` rama `fix/convoy-data-ownership`, con modificación local en `app/Http/Controllers/Company/Simple/SimpleManifestController.php` y `.env.testing` sin seguimiento. **No se tocaron ni incluyeron en la transferencia.**
- Rama `main` local `84d4ad0`, atrasada respecto de origin: **no modificada**.
- Rama `release/etapa-1` local `d08c3f2`, atrasada respecto de origin: **no modificada**.
- `git for-each-ref` sobre todas las ramas: **NINGUNA** rama local con `[ahead]`; por lo tanto no había commits locales de `fix/*`, `feature/*`, `manual/*`, `docs/*` pendientes de subir a origin. No se forzó push, no se actualizaron otras ramas ni se borró ningún worktree.
- Inventario completo de worktrees y ramas/SHAs (sin secretos): `/home/victor/app_cargas_B_transfer_20261123/inventario_git_asus02.txt` en destino. Se generó antes de copiar.
- Estado de instancia B en origen: worktree `/var/www/html/_worktrees/app_cargas/app_cargas_pruebas_b` limpio, HEAD detached `d5b82ea182f215c784d3bf6a7f1bb3f1fa85e246` en el momento del inventario. Este traspaso **no** reemplaza el código/checkout del destino: Code deberá preparar su instancia de ejecución conforme al inventario y la release de QA.

## Archivos copiados y rutas de destino verificadas

| Recurso | Ruta en victor-red-byte | Comprobación |
|---|---|---|
| Arneses y evidencias completos, incluidos `base_limpia.sql`, `t10_regresion.php` si existe, scripts, reportes y backup actual | `/var/www/html/pruebas_importacion/` | 265 archivos |
| Muestras originales | `/var/www/html/muestras_importacion/` | 5 archivos, incluidos AGEDELTA, CARE I, CONAY I y Don Toyo; otras muestras de los 10 formatos también están en `pruebas_importacion/muestras/` |
| Dump **actual de B**, sin restaurar | `/var/www/html/pruebas_importacion/app_cargas_pruebas_b_2026-11-23_actual.sql` | 7.552.708 bytes; `-- Dump completed on 2026-11-23 11:22:27` verificado |
| Configuración **privada**, fuera del repositorio | `/home/victor/app_cargas_B_transfer_20261123/instancia_b.env` | permisos `0600` |
| Inventario Git y listado SHA-256 | `/home/victor/app_cargas_B_transfer_20261123/inventario_git_asus02.txt` y `SHA256SUMS.txt` / `SHA256SUMS_delta.txt` | copiados sin alterar |

**Hash SHA-256 dump B:** `232bae7d77f07fd230310cd8ec750db859fa323b6ce1dff272a5214e0d3bf280`.

**Hash SHA-256 `base_limpia.sql`:** `801832826552cd7ab3d670c634079f43869fb870c9e0ae6ef596892c13c36d85`.

**Verificación total por archivo:** 270 archivos en lote inicial, más 2 informes de Work producidos mientras se copiaba (`documental-A-clasificacion-20261123.json` y `documental-A-destino-clasificacion-20261123.json`). **272/272 coincidencias SHA-256**, 0 ausentes y 0 distintos, comprobadas en destino sobre las rutas finales. La copia se hizo con archivos privados modo `0600` y directorios protegidos; no se escribieron certificados ni credenciales en el repositorio. Archivo de transferencia inicial también confirmado por SHA-256: `a373c92f2f7aae08f54f0c769b174c2d9c62d95f1445622ca4e0da5b52538c08`. Delta: `3981a6085140400d580599dbffd2c301d6fbac0f68b8b9a70c8bf06171ecdfc7`.

## Pendiente de Code

Los recursos están listos para reconstruir el entorno de QA en `victor-red-byte`. **No se creó ni restauró todavía `app_cargas_pruebas_b` en el destino**; Code puede hacerlo bajo su turno, comparar el dump actual contra la restauración de `base_limpia.sql` y verificar conteos. La transferencia no valida por sí misma la base restaurada ni garantiza sincronía de archivos generados después del snapshot. No se tocó Stage ni producción.

### Salvedad del arnés T10

Tras verificar los archivos recibidos en destino y buscar en ASUS02, **no se encontró `t10_regresion.php`** en `/var/www/html/pruebas_importacion`, en los worktrees de `/var/www/html/_worktrees/app_cargas`, ni en las rutas habituales consultadas de `/home/victor` y `/tmp`. El hecho de que la copia de los dos directorios sea completa no certifica la presencia de ese archivo expresamente solicitado. **Code debe localizar el arnés T10 por otra ruta o informar su ausencia; no se recreó ni se inventó un sustituto.** Los demás arneses y `base_limpia.sql` están recibidos y comparados por hash.
