# Manual de usuario — Pendientes al 03/11/2026

**Estado (03/11, 20:45):** el manual está en stage (`9d7ce2e`) con 50 artículos. Los que Work valida en stage se publican y salen a producción hoy; el resto queda en borrador.

## 1. Para llevarlo a producción

- **Validar cada artículo en stage** con las cuentas QA (`qatest01`, `qatest02` y `qatest03`), siguiendo los pasos en la pantalla real. En envíos a AFIP o DNA: siempre **Cancelar**.
- Al validarlo, cambiar en su cabecera `estado: publicado` y `verificado.entorno: stage`, con versión y fecha. Sin eso el artículo no se ve en producción.
- Deploy a producción por paquete (AGENTS.md §6), con `public/build` compilado y `composer install --no-dev`.
- Antes de cualquier deploy: probar en una copia limpia **sin dependencias de desarrollo** (lo que causó el 500 del 03/11).

## 2. Artículos que faltan

- `administracion/`: usuarios, empresas y roles, webservices y certificados de una empresa, clientes y propietarios desde el admin (en curso, sin entregar).
- `afip-argentina/`: anular el arribo, cuando se corrija el formulario.

## 3. Correcciones de la app detectadas (sin hacer)

| ID | Qué | Estado |
| --- | --- | --- |
| ANULAR-ARRIBO | El formulario envía `nro_viaje` y el servidor exige `arribo_id`; llama a una función de resultado que no existe; el campo admite 13 caracteres. | Arreglo preparado en local, sin subir (rama `fix/anular-arribo-zp`). |
| PARAGUAY-01 | En un conocimiento fraccionado, agregar un ítem puede pisar los totales globales con la suma de la fracción; XFBL enviaría el parcial como total. | Hipótesis fuerte; falta probarla en stage (la empresa QA necesita propietario y embarcación de prueba). |
| UI-ES | Formularios de catálogos en inglés y mensajes de validación en inglés. | **Resuelto** en `main` (`fix/ui-spanish-labels`). Requiere `APP_LOCALE=es` en cada `.env`. Siguen en inglés los textos de Jetstream (ingreso, perfil, botones). |
| PUERTO-ADUANA | El vínculo "Puertos ↔ Aduanas" (predeterminada) no lo usan los envíos actuales: el código de aduana sale de una tabla fija para los puertos de la hidrovía y del campo **Código AFIP (Argentina)** para el resto. El ejemplo de la pantalla muestra Buenos Aires con `001` y la tabla usa `033`. | Decisión de Víctor. |
| RESET-TOTAL | "17. AnularEnvios – RESET TOTAL" dice que anula en AFIP pero solo reinicia el viaje en la app. Fuera del manual. | Decisión de Víctor. |
| ANTICIPADA-CIERRE | "Cerrar viaje" está marcado obligatorio, pero falla si todos los conocimientos descargan en Argentina. | Confirmar regla de AFIP. |
| IMPORTACION | Mensaje "ya fue importado" también para otro archivo con el mismo viaje; número de viaje del formulario solo aplica a CUSCAR y TFP; revertir sin mensaje ni control de envíos previos; filtros del historial; tarjetas de formatos con números. | Listado completo en la devolución de Code del 03/11. |
| SOLICITAR-ANULACION | La app da el MIC/DTA por anulado al enviar la solicitud, antes de que la Aduana la apruebe. | Revisar. |
| CONVOY-DATOS | Los datos de convoy de un viaje se pueden leer sin controlar la empresa (`getDatosConvoy`). | Rama `fix/convoy-data-ownership` en curso. |
| CUSCAR-CONSIGNATARIO | Parte con solo RUC (sin razón social) dejaba el nombre vacío y la importación fallaba con error SQL; nombres con ":" inicial. | **Resuelto** en `main` (`fix/cuscar-party-without-name`), validado en stage con `CAREI-2506AHL.1`. |
| CLIENTE-A-LA-ORDEN | Fichas de cliente con nombre "TO THE ORDER OF …" (cláusula, no razón social). La importación reutiliza la ficha por RUC sin cambiarle el nombre. | Consultar a Roberto cómo guardarlas. |
| REPORTES-OPERADOR | Reportes con filtro por columna inexistente: error para operadores. | **Resuelto** en `main` (`fix/reports-creator-column`). |
| PAIS-ELIMINAR | Eliminar país desde el listado borraba en cascada puertos y aduanas. | **Resuelto** en `main` (`fix/country-delete-guard`). Queda el hueco previo: no controla tipos de documento ni lugares operativos AFIP. |
| BL-TRANSBORDO | La casilla Tránsito / Trasbordo se guardaba como `1` al crear. | **Resuelto** en `main` (`fix/bl-transshipment-flag`). Revisar registros existentes con `1`. |
| SEGURIDAD-REPORTES | PDF del Manifiesto Aduanero puede mezclar datos de otro viaje o empresa (`CustomsManifestReportService.php:191-194`); controles de permiso comentados en reportes (`ReportController.php:324-330, 359-361, 466-482, 833-835`); MANE valida propiedad por nombre de archivo (`ManeFileController.php:257, 313-315`). | **Prioridad alta** para la próxima vuelta. |
| REPORTES-VARIOS | Filtro de estado y contadores del listado de conocimientos; cartas de aviso solo con viajes completados y descarga de archivo roto ante error; MIC/DTA sin mensajes de error; casilla de MANE con error de JavaScript. | Para después. |
| USUARIOS | "Resetear Contraseña" (Detalles del Usuario) no hace nada; permisos "Puede Importar/Exportar" no cambian el menú; menú de celular distinto al de escritorio; el logo lleva a una pantalla de super-admin. | Para después. |
| DESCONSOLIDADO-TRANSBORDO | Mensaje engañoso cuando la acción no corresponde al estado; envío de homologación de transbordos bloqueado en stage (`HidroviaTransshipmentDraftController.php:296`). | Para después. |
| PAISES-PANTALLA | Columna "Alpha" vacía, "Ver Detalle" da error, sin mensajes de guardado. | Para después. |
| REGRESION-IMPORTADORES | Guardar los archivos reales de Roberto y reimportarlos antes de cada deploy; control de "sin error SQL" en los 8 importadores. | Propuesta; puede incluirse en las pruebas del presupuesto de convoy. |
| REPORTES-500 | Tres métodos de `ReportController` con `validate()` dentro de `try/catch (\Exception)`: los errores de validación no se muestran. | Para después. |
| DIAS-DECIMALES | "Días en el sistema" muestra decimales (Carbon 3) en 8 vistas. | Para después. |
| NPM | `npm ci` reporta 18 vulnerabilidades (2 críticas). | Revisar antes del próximo deploy a producción. |
| BUSCADOR-GLOBAL | Con nombres de usuario largos, el buscador de la barra se achica. | Mejora menor. |
