# Parte nocturno — equipo de desarrollo — 17/11/2026

**Alcance:** únicamente ramas de desarrollo y QA local. Sin merges a main, sin Stage ni producción.

## 1. Traducción del estado de licencia del capitán — HECHO

- Rama: `fix/ui-shipment-page`, HEAD `9294ece59839064d0721284fda0cc19d62ec328d`.
- La traducción ya estaba publicada antes de la orden nocturna y fue comprobada contra el archivo `show.blade.php` y el enum real de `database/migrations/2025_07_16_140957_create_captains_table.php`.
- Códigos del esquema: `valid` → Vigente, `expired` → Vencida, `suspended` → Suspendida, `revoked` → Revocada, `pending_renewal` → Renovación pendiente. Desconocido/sin dato: «—».
- No se modificó ni se publicó nuevamente el mismo archivo para evitar un commit redundante. El HEAD queda disponible para el QA corto de Code.
- La misma rama contiene el ajuste posterior del porcentaje de utilización de carga: valor numérico sin corte a 100 %, barra limitada a 100 %, cero real `0,0 t`; los cambios se validaron localmente con 13 pruebas/94 aserciones.

## 2. Manual de etapa 1 — EN EJECUCIÓN

- Se creó `manual/etapa-1` exclusivamente desde `origin/release/etapa-1` en `d08c3f2576b4236d1c0c9e405aa156c756f5ee47`.
- Archivos previstos, uno por commit: `resources/manual/importar-manifiestos/formatos-soportados.md` e `importar-un-manifiesto.md`.
- Validación de lectura inicial: la instancia B estaba en HEAD `390826a`; la ruta de importación existe y el selector de embarcación es obligatorio. El factory de B identifica las muestras reales MSC TXT y Don Toyo XLS por contenido. El servidor HTTP B no respondía al puerto 8002 durante esta comprobación: no se atribuye recorrido visual de navegador.
- Regla verificada: MSC exige igualdad con el campo **VESSEL NAME del contenido**; el nombre físico del archivo no determina la embarcación. Don Toyo no trae nombre de embarcación y usa la selección obligatoria del formulario; su hoja BUQUE contiene número de viaje e IMO.

## 3. T10 — DEPENDENCIA PENDIENTE

- No había parte `2026-11-17-noche-work.md` ni tabla T10 por formato publicada en `docs/partes` al iniciar este parte. Sin esa evidencia no se inicia una corrección especulativa sobre los diez parsers.
- Base prevista cuando haya informe: `origin/release/etapa-2`. Cualquier discrepancia y excepción se documentará antes de tocar código.

## 2. Manual de etapa 1 — HECHO (verificación parcial de interfaz)

- Rama nueva `manual/etapa-1`, nacida de `release/etapa-1` @ `d08c3f2`, HEAD final `7906e09`.
- `fc19162` — `resources/manual/importar-manifiestos/formatos-soportados.md`: incorpora MSC Vessel Name TXT y Don Toyo Excel XLS/XLSX, los datos y catálogos exigidos, el reconocimiento por contenido y los códigos de formato del historial.
- `7906e09` — `resources/manual/importar-manifiestos/importar-un-manifiesto.md`: instrucciones en trato de usted, sin términos de programación; MSC exige que el nombre seleccionado coincida con **VESSEL NAME dentro del archivo**, no con el nombre físico; Don Toyo no indica embarcación, se elige obligatoriamente en formulario y el número del usuario prevalece sobre VIAJE. Se explican las fechas/documentos pendientes y mensajes de error.
- QA de lectura sobre código de etapa 1 y copia local B: rutas del formulario, campos, método de detección y reconocimiento real de muestras MSC y Don Toyo confirmados; YAML, títulos, enlaces `manual:`, etiquetas y `git diff --check` correctos.
- **Limitación explícita:** la copia B tenía HEAD `390826a` y no escuchaba en 8002, por lo que no hubo recorrido visual ni importación real durante este trabajo. No se alteró la base B ni su checkout. El QA posterior deberá verificar las pantallas manualmente.
- Los dos commits quedaron publicados, sin merge ni despliegue.

## 3. T10 — relevamiento recibido; desarrollo habilitado

- Work publicó la tabla de seis campos en `docs/partes/2026-11-17-work.md` (sección T10), basada en `release/etapa-1` y las 16 muestras.
- Ocho importadores presentan diferencias: CMSP, TFP, Don Toyo, Login, Navsur y Paraná en contadores físicos; Guaraní también en pesos y MSC en `total_cargo_weight`, contenedores totales y un caso de redondeo de 0,01 kg. G2Ocean/K-Line son correctos en las muestras.
- CUSCAR 3 conserva 218.500 kg en BL pero 0 en ítems: los acumulados desde ítems deben permanecer en 0; no se atribuirá peso inventado.
- Base de desarrollo solicitada `origin/release/etapa-2`. Se inicia trabajo archivo por archivo con pruebas por parser y se reservará la regresión contra esa release para el entorno B, sin tocar Stage/producción.
- Work documentó un bloqueo independiente de MySQL al crear `app_cargas_pruebas_noche` (1044, permisos): no se elude con credenciales externas. Sigue el QA que no requiera esa base.
