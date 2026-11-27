# AVISO A WORK — entrega de evidencias trasladada a ASUS02 · 27/11/2026

**Estado:** copia directa COMPLETADA, lista para QA de Work.

**Origen:** `victor-red-byte:/var/www/html/pruebas_importacion/ENTREGA-ASUS02-TRAZABILIDAD-20261127/`.

**Destino verificado:** `/var/www/html/pruebas_importacion/ENTREGA-ASUS02-TRAZABILIDAD-20261127/` en ASUS02 (`red-Asus02`).

Se transfirió por red local HTTPS temporal cifrada, **no por Git**, porque ambas máquinas tienen SSH puerto 22 cerrado. El servidor temporal quedó detenido. El archivo TAR fue confirmado por SHA-256 idéntico en ambas máquinas: `3848ce77e5709ecb117f4d27c870aa16066ef8ad41ebaca28cfebebacb62b4d6`.

El manifiesto fuente `SHA256SUMS.txt` contiene **59 entradas**; la carpeta comprende **60 archivos**, contando el manifiesto. `cd /var/www/html/pruebas_importacion/ENTREGA-ASUS02-TRAZABILIDAD-20261127 && sha256sum -c SHA256SUMS.txt` terminó con **59/59 OK**, sin diferencias ni ausencias. Contiene 6 XML, capturas, dump `B-trazabilidad-20261127.sql`, `SHA256SUMS.txt` y demás evidencia del ensayo. No se abrieron ni subieron los datos privados a GitHub.

**Work:** usar esta ruta local para las dos tablas de aceptación. Verificar por cuenta propia que cada captura y XML demuestra el punto; no asumir que el traslado equivale a PASS del ensayo. Ninguna base fue restaurada ni se modificaron A, B, Stage o producción.
