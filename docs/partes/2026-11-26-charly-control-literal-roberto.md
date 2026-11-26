# App Cargas — control literal: palabras de Roberto (20, 25 y 26/11/2026)

**Origen:** transcripción de mensajes de WhatsApp aportada por Víctor a Charly el 26/11/2026. Se conserva el texto citado tal como fue provisto. **No** se presenta como revisión independiente de capturas o exportación original del chat. Esta fuente es la referencia textual para el QA de `fix/msc-condicion-ncm`, Don Toyo y la revisión transversal de los diez formatos. No contiene resultados de tests ni comprobación de implementación.

## 20/11/2026 — Roberto, citas literales

> «En los items no completa el tipo de embalaje»

> «no toma la ncm que esta en la descripción de la mercadería»

> «La condición del contenedor es siempre House salvo que especifique lo contrario»

> «En el caso del Don Toyo no pone el notificatario, como el archivo no lo informa hay que repetir el consignatario en el notificatario»

> «ej conocimiento DT0626SMSCCAA22 no completo el cargador, consignatario y notificatario»

## 25/11/2026 — Roberto, citas literales

> «El cuit sim corresponde al Shipper»

> «Las cantidades de bultos en el don toyo vienen por el total del conocimiento, pero en la aduana hay que declarar la cantidad de contenedores porque el envase son contenedores.»

> «No recuerdo como hacíamos cuando los archivos no detallaban las cantidades de bultos por contenedor, pero se podria poner el total de bultos por conocimiento y en los intems de cada contenedor dejar la cantidad en cero, ya que no se sabe la cantidad por contenedor.»

> «TPR - Terminal Puerto de Rosario»

## 26/11/2026 — Roberto, citas literales

> «en el caso de Agedelta 056A sigue sin tomar la ncm que esta en la descripción de la mercadería»

> «sigue poniendo Pier cuando por default tiene que poner House»

> «en el don toyo conocimiento de vacios sale esto al querer guardar el item»

**Error textual informado:** «La suma de bultos en contenedores (0) debe coincidir con el total del ítem (80).»

**Capturas de hoy, según la transcripción recibida:** «Tipo de Embalaje» vacío (carga CONTENEDORES); «Código NCM/HS» vacío; «Condición Contenedor: P - Pier»; «Notificar a» vacío con AGENCIA MARITIMA INTERNACIONAL S.A. como cargador y consignatario; «Partes Involucradas» con «Transporte propio» marcado y los cuatro selects vacíos.

## Regla existente indicada expresamente por Víctor (no cita literal de Roberto)

- «Tipo de Embalaje: NO RETORNABLE para los ítems (pedido de Roberto en Guaraní, doc AFIP-PACKAGING-CATALOG-001, actualización 23/05; K-Line ya lo aplica).»

**Precaución de alcance:** distinguir el tipo de embalaje del ítem, el código EDIFACT requerido en el MIC/DTA, el total documental de contenedores y la cantidad de bultos por contenedor. La afirmación sobre NO RETORNABLE se registra como regla vigente indicada por Víctor, y debe contrastarse contra el catálogo y los flujos reales antes de modificar parsers o generadores; no sustituirla por decisiones previas de Don Toyo sin verificar el contrato. La regla textual de condición exige **House por defecto**, salvo declaración contraria; un `P` escrito por el parser no demuestra por sí solo que lo declare la muestra. Para NCM, el hallazgo de campo vacío en el archivo real debe revisarse también contra su descripción. No inventar un código si la identificación no es inequívoca.

## Verificación solicitada

- Comprobar condición y NCM de MSC, en particular AGEDELTA 056A, con la fuente real y el valor persistido; separar `P` procedente del archivo de `P` asignado por defecto.
- Verificar los diez formatos, no únicamente los nuevos, conforme al criterio de aceptación de etapa 2.
- En Don Toyo, comparar partes guardadas en base con opciones del HTML renderizado y reproducción visual; comprobar notificatario y transporte propio, además de la regla de bultos del conocimiento frente a ítems de contenedor.
- Registrar resultados como evidencia aparte; **esta transcripción no equivale a QA aprobado**.

Sin intervenciones en código productivo, base de datos, Stage, producción ni Aduana para confeccionar este parte.
