# Transbordo fluvial — Hidrovía Paraná / MIC-DTA — REV0

Fecha: 2026-10-19. Base inspeccionada en main local: 513abad. Estimación técnica preliminar, no presupuesto cerrado.

## Contrato de ARCA verificado

Fuente: https://arca.gob.ar/hidrovia-parana/documentos/Manual-del-Desarrollador-Hidrovia-RegistroATA.pdf

- `RegistrarTitEnvios` registra el título con sus envíos; un título repartido entre cuatro barcazas requiere cuatro envíos, uno por medio de transporte (manual, p. 9).
- `RegistrarEnvios` agrega envíos a un título previamente registrado (p. 14). No reemplaza el alta inicial del título.
- `TitTransEnvio` admite las referencias optativas, exclusivamente de transbordo: `idManiCargaArrPaisPart` C(20) e `idDocTranspArrPaisPart` C(39) (p. 57).
- Un envío produce TRACK y se relaciona con el ATA encargado del MIC/DTA; `AnularEnvios` opera por TRACK (pp. 9 y 17).
- Para graneles y cargas en contenedor de transbordo existen reglas de acondicionamiento y fraccionamiento distintas de la carga común (pp. 9-10).

## Infraestructura real que reutilizamos

- `Shipment` tiene `voyage_id`, `vessel_id`, `origin_manifest_id` y `origin_transport_doc`. Los dos últimos ya tienen campos en vistas de alta/edición y columnas de 20/39 caracteres.
- `BillOfLading` pertenece a un `shipment_id` y tiene `shipmentItems`, `is_transit_transshipment`, `is_fractional` y puerto de transbordo.
- `ShipmentItem` se vincula a `Container` mediante `container_shipment_item`, con pesos y cantidades de pivote ya definidos.
- `ArgentinaMicDtaService` implementa `RegistrarTitEnvios`, `RegistrarEnvios`, `AnularEnvios` y consulta de títulos/envíos, además de los TRACK existentes.
- `SimpleXmlGenerator::createRegistrarTitEnviosXml` hoy genera un único `<Envio>` por título dentro del shipment: todavía NO soporta distribución completa de un mismo título entre varias barcazas.
- Commits 2ecceb3/513abad: empiezan a emitir las dos referencias optativas en `TitTransEnvio`; QA local con 28 pruebas/157 verificaciones. No publicados en producción.

## Diseño necesario antes de habilitar Paso 4

1. Definir, con datos reales existentes, la asignación por conocimiento/línea/contenedor a cada shipment/barcaza, sin duplicar el título global.
2. Diseñar un registro persistente para esta asignación, conservando el esquema actual para importaciones y MIC/DTA ordinarios.
3. Validar que los envíos no dupliquen contenedores o pesos ni excedan totales del título; contemplar fraccionamiento, granel y carga suelta.
4. Emitir un `<TitTransEnvio>` con N `<Envio>` para una distribución inicial; generar `RegistrarEnvios` exclusivamente para nuevos envíos de títulos ya registrados.
5. Persistir TRACK por envío/barcaza, estados, respuestas, reintentos inciertos y anulaciones con idempotencia.
6. Implementar UI clara, autorizaciones por empresa, edición antes del envío, validaciones previas, simulaciones SOAP, documentación y deploy por etapas.

No reutilizar sin comprobación los identificadores de documentos de arribo a nivel de shipment cuando correspondan a distintos títulos de origen.

## Estimación preliminar (horas efectivas de desarrollo + QA)

| Bloque | Horas |
|---|---:|
| Contrato/reglas y escenarios reales | 10–16 |
| Persistencia de asignaciones por título/barcaza | 18–30 |
| UI de reparto y revisión | 22–36 |
| XML/WS existente, embarques parciales y reglas de embalaje | 18–30 |
| TRACK, estados, anulaciones, idempotencia | 14–24 |
| QA local/Stage, pruebas de regresión | 22–36 |
| Despliegue controlado y guía de operación | 8–12 |
| **Subtotal primer circuito** | **112–184** |

Reserva recomendada por integración aduanera y datos heterogéneos: 15% a 20%; primer circuito con contingencia **130–220 h** (aprox. 4–7 semanas a 32 h efectivas/semana).

## Conjunto ampliado (orientativo y condicionado)

- Circuito Hidrovía/MIC-DTA: 130–220 h.
- Procedimiento TRB del SIM argentino: 70–130 h de análisis funcional, acompañamiento y desarrollo factible; **no afirmar automatización SOAP sin contrato público habilitado**.
- Transbordos Paraguay: 80–140 h **condicionadas a revisión del contrato DNA, homologación y permisos disponibles**.
- Total condicional de las tres líneas: **280–490 h**; NO incluye eventual integración COEM (relevamiento adicional).

Los rangos NO incluyen la espera de aprobaciones, acceso externo a homologación o demoras en respuestas reales de Aduana. Reestimar cuando estén confirmados documentos reales de un transbordo y alcance PY/SIM.

## Avance de backend — 2026-10-19

Implementación local, aún sin publicar:

- Referencias documentales optativas en `SimpleXmlGenerator` (`2ecceb3`) y test (`513abad`).
- `HidroviaTransshipmentDistributionValidator`: verifica propiedad del viaje/título/línea, pesos, cantidades y asignación física de contenedores; no genera valores ficticios.
- Migración `2026_10_19_123000_create_hidrovia_transshipment_allocations_table`: guarda proporciones por línea/barcaza sin duplicar `BillOfLading`.
- `HidroviaTransshipmentDraftService`: reemplaza borradores transaccionalmente, con bloqueo del viaje y prevención de conflicto de un contenedor entre barcazas.
- `HidroviaTransshipmentReadinessService`: verifica distribución completa, barcazas reales por categoría `barge` y agrupa por embarcación para una futura serialización.
- API de borradores `GET/PUT .../transbordo/{voyage}/bills/{bill}/distribution` y `POST .../preflight`; controla la empresa y rol `Transbordos` sin acción SOAP.
- 52 pruebas seleccionadas aprobadas, 244 verificaciones: incluye regresiones MIC/DTA y tests de SQLite en memoria sin base productiva.

No habilitar interfaz de envío hasta resolver el contrato XML de N `Envio` dentro de un `TitTransEnvio`, persistencia TRACK por envío y gestión de respuestas AFIP. Los envíos parciales/fraccionados, reglas por tipo de carga y sincronización aduanera siguen pendientes.

## Estado de salida

BACKEND DE BORRADORES Y PREFLIGHT IMPLEMENTADO EN LOCAL. Integración SOAP multibarcaza, TRACK y frontend pendientes. Sin push, deploy ni transmisión aduanera.
