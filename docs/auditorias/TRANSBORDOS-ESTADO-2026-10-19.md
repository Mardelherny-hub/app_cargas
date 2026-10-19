# Transbordos Argentina / Paraguay — estado técnico comprobado

Fecha: 2026-10-19. Base revisada: `main` en `b899787`.

## Estado confirmado

- `routes/company.php`: existe únicamente `company.simple.transbordo.index`; las rutas de detalle y envío están comentadas.
- `SimpleManifestController::transbordoIndex()`: responde mediante `renderComingSoon`, sin gestionar operaciones.
- `SimpleManifestController::AVAILABLE_WEBSERVICES['transbordo']`: servicio no configurado (`service_class = null`, `status = coming_soon`).
- `resources/views/company/simple/dashboard.blade.php`: muestra Paso 4, «Próximamente», con botón deshabilitado.
- No se encontraron controladores/servicios específicos para registrar o transmitir transbordos en `app/Http/Controllers/Company/Simple` ni `app/Services/Simple`, ni tests de transbordos.
- Existen datos de apoyo en `Voyage` (puerto y aduana de transbordo, indicador `has_transshipment`), `BillOfLading` (puerto e indicadores), `CargoType` (`allows_transshipment`) y `CustomOffice` (`supports_transbordo`).
- Los enums de transacciones y estados de webservices contienen el tipo `transbordo`. Es infraestructura genérica, **no** una implementación funcional.

## Separación de alcance

- ATA-DESC es desconsolidación de títulos madre e hijos y no equivale a un cambio físico de embarcación.
- Indicadores de tránsito/transbordo en XML de otros webservices no prueban la existencia de un método para registrar transbordos.
- No habilitar un botón de transmisión hasta verificar los métodos SOAP/documentación oficial y obtener pruebas con respuestas simuladas.

## Próxima intervención de desarrollo

1. Obtener de documentación disponible en el proyecto los mensajes oficiales y el flujo que corresponde a transbordos AR/PY (sin inferir acciones a partir de nombres genéricos).
2. Precisar qué datos de barcaza origen/destino y distribución por conocimiento existen realmente en los modelos o archivos de la aplicación.
3. Definir primero contratos de validación y pruebas sin datos ficticios; implementar el circuito en archivos y commits separados.
4. QA aislado, control de pertenencia por empresa, idempotencia y persistencia de solicitud/respuesta antes de exposición HTTP.
5. Habilitar menú y pantalla únicamente cuando el flujo esté comprobado; pruebas aduaneras reales a cargo del cliente.

**Estado:** INVENTARIO REAL CONFIRMADO / FUNCIONALIDAD PENDIENTE. No se hicieron modificaciones productivas.
