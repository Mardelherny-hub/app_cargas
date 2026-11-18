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
