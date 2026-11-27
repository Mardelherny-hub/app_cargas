# Entrega a Codex y coordinación con Charly — TFP · 27/11/2026

Por instrucción de Víctor, referencia exacta para reproducir el rechazo del BL **001PASU018626**. Verificación directa de bytes en A red-Asus02 (c546af04-a420-4f1c-bc7b-2667f27bf883).

**Codex:** el archivo que contiene ese BL es `/var/www/html/pruebas_importacion/WORK-A-pesos-condicion-20261127/asuncion-original.txt`, SHA-256 **a099456aff31f51a394e1f7a1c07af83a427c9ee74a5da3ad60d626c7795792d**. Corresponde al adjunto original `asuncion-b-viaje-0186b-export-codigo-645_20260806001730_u9Jry12M.txt`. Sus 50 líneas del BL tienen CANTTOTALBULTOS y CANTPARCIALBULTOS vacíos; el primer contenedor es BMOU4805279. No completar datos por cuenta propia.

**Discrepancia de identificación comprobada:** la muestra inventariada `e2e-roberto-txt-bue.txt` está en `/var/www/html/pruebas_importacion/muestras/3e2152424112ba2b7a21eb5756219ecf255cba6fd3e56de456e9f7f0b6d8ed4d.txt`, SHA-256 **3e2152424112ba2b7a21eb5756219ecf255cba6fd3e56de456e9f7f0b6d8ed4d**. **No contiene la secuencia 001PASU018626.** No usarla como si fuera el archivo anterior ni renombrar uno para confundirlos.

**Charly:** si Codex no tiene acceso al archivo de A, coordinar copia privada directa del archivo a099… al equipo donde trabaja, con verificación de SHA-256 en destino, siguiendo el mecanismo de entrega ya usado entre equipos. No copiar a GitHub los datos del archivo. Este parte transmite la referencia y solicita la coordinación; **no acredita copia realizada ni recepción confirmada**. Work no accedió a B ni a Stage para comprobar disponibilidad.

En origin ya se constató el nuevo commit Don Toyo `c46c12d9` sobre fix/msc-contenedor-ncm; falta su QA. TFP vacíos y orden XSD se identificarán por sus entregas publicadas, sin sustituir ramas ni aprobar por parecido.
