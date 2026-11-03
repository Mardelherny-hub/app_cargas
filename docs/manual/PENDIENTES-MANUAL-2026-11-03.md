# Manual de usuario — Pendientes al 03/11/2026

**Estado:** el manual está en stage con 37 artículos en borrador (visibles con `MANUAL_BORRADORES=true`). Víctor lo revisa en stage. El equipo pasa a otras tareas; esto queda como trabajo de fondo.

## 1. Para llevarlo a producción

- **Validar cada artículo en stage** con las cuentas QA (`qatest01`, `qatest02` y `qatest03`), siguiendo los pasos en la pantalla real. En envíos a AFIP o DNA: siempre **Cancelar**.
- Al validarlo, cambiar en su cabecera `estado: publicado` y `verificado.entorno: stage`, con versión y fecha. Sin eso el artículo no se ve en producción.
- Deploy a producción por paquete (AGENTS.md §6), con `public/build` compilado y `composer install --no-dev`.
- Antes de cualquier deploy: probar en una copia limpia **sin dependencias de desarrollo** (lo que causó el 500 del 03/11).

## 2. Artículos que faltan

- `administracion/`: usuarios, empresas y roles, webservices y certificados de una empresa, clientes y propietarios desde el admin (en curso, sin entregar).
- `reportes/`: manifiesto, conocimientos, MIC/DTA, avisos de arribo y MANE.
- `afip-otros-roles/`: desconsolidado y transbordos.
- `catalogos/`: países.
- `primeros-pasos/`: qué ve cada rol, recorrido del menú y recuperar la contraseña.
- `afip-argentina/`: anular el arribo, cuando se corrija el formulario.

## 3. Correcciones de la app detectadas (sin hacer)

| ID | Qué | Estado |
| --- | --- | --- |
| ANULAR-ARRIBO | El formulario envía `nro_viaje` y el servidor exige `arribo_id`; llama a una función de resultado que no existe; el campo admite 13 caracteres. | Arreglo preparado en local, sin subir (rama `fix/anular-arribo-zp`). |
| PARAGUAY-01 | En un conocimiento fraccionado, agregar un ítem puede pisar los totales globales con la suma de la fracción; XFBL enviaría el parcial como total. | Hipótesis fuerte; falta probarla en stage (la empresa QA necesita propietario y embarcación de prueba). |
| UI-ES | Formularios de tipos de contenedor, carga y embalaje muestran los nombres internos en inglés; la app no tiene `lang/es` y los mensajes de validación salen en inglés. | Tarea definida, sin empezar (`fix/ui-spanish-labels`). |
| PUERTO-ADUANA | El vínculo "Puertos ↔ Aduanas" (predeterminada) no lo usan los envíos actuales: el código de aduana sale de una tabla fija para los puertos de la hidrovía y del campo **Código AFIP (Argentina)** para el resto. El ejemplo de la pantalla muestra Buenos Aires con `001` y la tabla usa `033`. | Decisión de Víctor. |
| RESET-TOTAL | "17. AnularEnvios – RESET TOTAL" dice que anula en AFIP pero solo reinicia el viaje en la app. Fuera del manual. | Decisión de Víctor. |
| ANTICIPADA-CIERRE | "Cerrar viaje" está marcado obligatorio, pero falla si todos los conocimientos descargan en Argentina. | Confirmar regla de AFIP. |
| IMPORTACION | Mensaje "ya fue importado" también para otro archivo con el mismo viaje; número de viaje del formulario solo aplica a CUSCAR y TFP; revertir sin mensaje ni control de envíos previos; filtros del historial; tarjetas de formatos con números. | Listado completo en la devolución de Code del 03/11. |
| SOLICITAR-ANULACION | La app da el MIC/DTA por anulado al enviar la solicitud, antes de que la Aduana la apruebe. | Revisar. |
| CONVOY-DATOS | Los datos de convoy de un viaje se pueden leer sin controlar la empresa (`getDatosConvoy`). | Rama `fix/convoy-data-ownership` en curso. |
| REPORTES-500 | Tres métodos de `ReportController` con `validate()` dentro de `try/catch (\Exception)`: los errores de validación no se muestran. | Para después. |
| DIAS-DECIMALES | "Días en el sistema" muestra decimales (Carbon 3) en 8 vistas. | Para después. |
| NPM | `npm ci` reporta 18 vulnerabilidades (2 críticas). | Revisar antes del próximo deploy a producción. |
| BUSCADOR-GLOBAL | Con nombres de usuario largos, el buscador de la barra se achica. | Mejora menor. |
