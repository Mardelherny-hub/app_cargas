# Etapa 2 — control de aceptación local A sobre `1109c11b` — PARCIAL / BLOQUEADO

**Fecha:** 27/11/2026. **Responsable del control:** QA independiente. **Sin envío a Aduana, sin deploy, sin correcciones de código.**

**Dictamen:** **NO APROBADO / ACEPTACIÓN ABIERTA**, por falta de evidencia funcional completa. Este parte no reemplaza la aceptación en navegador ni constituye un gate PASS.

## Preparación y trazabilidad comprobada

- Código comprobado: `release/etapa-2 @ 1109c11b` en `/var/www/html/_worktrees/app_cargas/app_cargas_work_etapa2_1109c11b`, worktree sin diferencias de Git antes de instalar dependencias.
- Respaldo del estado previo de A: `/home/victor/qa_stage_20261127/a_before_20261127.sql`; **`Dump completed` verificado**, SHA-256 `a6c167b4c78522c09a1ea9b12e48d7d984e8752ff5b0ec3312d47524a1ecfba8`.
- Copia actual de Stage extraída por SSH en **solo lectura**, sin dejar archivos en Stage y con `mysqldump --single-transaction`: `/home/victor/qa_stage_20261127/stage_cargas_20261127.sql`; 26.711.720 bytes, `Dump completed` verificado, SHA-256 `2a5cc7536c9d1034d3357a7ce3dc777acf829e85f8903f1c7d95fb3cf922e164`.
- Stage actual: base `cargas`. En A se restauró ese dump en `app_cargas_pruebas`, sin tocar B. Conteos posteriores: 7 viajes, 7 cargas, 276 conocimientos, 597 clientes, 53 embarcaciones.
- Empresa id 1008: `Nabsa SA`. Usuario real id 20: `Leandro` asociado a empresa 1008 (identidad comprobada, **no se inició sesión con sus credenciales**).
- **Bloqueo confirmado de datos de Stage:** ninguna embarcación del catálogo se llama `Observador`, `CARE I` ni `CONAY I` (consulta `vessels` en copia de Stage; Nabsa 1008 tiene 22 embarcaciones, ninguna de esas tres). No se crearon fichas ficticias para simular aceptación.
- Playwright/Chromium real probado en `http://127.0.0.1:8004/login` sobre ese SHA. Falló inicialmente con 500 por `Vite manifest not found` del entorno local; se ejecutó `npm ci` + `npm run build` **solo** para preparar assets locales. Nueva carga del login: HTTP 200, 1 formulario y 4 inputs. No había sesión autenticada como Leandro, por lo que **no hay capturas de los flujos protegidos**. Captura disponible: [acceso sin sesión](evidencias/2026-11-27-etapa2-login-sin-sesion.png).
- No se invocaron WSAA, métodos de envío ni generación de transacciones aduaneras.

## PARTE A — Alcance literal de etapa 2 (fuente: `docs/PLAN-IMPORTACION-VIAJES-EXISTENTES.md`, §1)

**Regla de este control:** sin captura del comportamiento en pantalla de la sesión correspondiente, estado **ABIERTO**. Un test de parser o una consulta SQL no equivalen a aprobación visual. No se encontraron los tres buques en el catálogo actual de Stage.

| Punto | Frase textual del alcance | Captura de cumplimiento | Dictamen | Motivo comprobado |
|---|---|---|---|---|
| 1 | Al importar, se elige entre **crear viaje nuevo** o **agregar a un viaje existente**, sea manual o importado, con cualquiera de los 10 formatos. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 2 | El sistema **propone la embarcación** según el archivo y el usuario la confirma o la cambia. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 3 | Si se agrega otra embarcación a un viaje simple, se ofrece **convertirlo en convoy** y se elige el líder. El líder puede ir sin carga y sin archivo (por ejemplo, el remolcador Observador). | — (no obtenida) | **ABIERTO** | No están Observador, CARE I ni CONAY I en el catálogo copiado de Stage; tampoco hay captura de convoy. |
| 4 | Si la embarcación ya tiene carga en el convoy, se suman los conocimientos. Si no tiene, se le crea su carga. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 5 | Los **conocimientos repetidos** se saltean y se informan al terminar. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 6 | **Revertir** solo lo agregado o el viaje completo. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 7 | **Eliminar un conocimiento en un paso**, con sus ítems y contenedores, de a uno o todos los de una embarcación. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| 8 | **Regla de bloqueo:** nada de lo anterior se permite si la embarcación tiene títulos o MIC/DTA vigentes en AFIP. | — (no obtenida) | **ABIERTO** | No hay sesión de Leandro ni captura del circuito en pantalla. |
| Criterio de aceptación | se arma el convoy Observador + CARE I + CONAY I agregando sus archivos, incluidos los dos de CARE I (CUSCAR y TXT), y se revierte un agregado sin afectar el resto del viaje. | — (no obtenida) | **ABIERTO** | No están Observador, CARE I ni CONAY I en el catálogo copiado de Stage; tampoco hay captura de convoy. |

### Observaciones bloqueantes diferenciadas de defectos de producto

1. **Datos de aceptación no disponibles en Stage:** faltan las tres embarcaciones exactas. No se presume que sea defecto del parser ni de la release; requiere aclarar si deben existir previamente o crearse desde un flujo autorizado.
2. **No hay sesión Playwright autorizada del usuario Leandro:** la pantalla de login local funciona, pero no se probó el recorrido autenticado ni se obtuvieron capturas de las operaciones de etapa 2.
3. **No se evaluaron campos de los BL agregados**, ni textos de pantalla, español, vista angosta, bloqueo AFIP, lista de BL antes/después, reversión, duplicados o borrado múltiple. Siguen **ABIERTOS** por falta de evidencia, no por fallo reproducido.

## PARTE B — Contrato AFIP y disponibilidad en el convoy

**Fuente técnica revisada:** `wgesregsintia2.wsdl` del mismo SHA y `Manual-del-Usuario-para-Registro-ATA.pdf` (manual del desarrollador, secciones de estructuras de negocio). El WSDL declara `minOccurs` XML, que **no es equivalente** al campo «Requerido (S/N)» del manual; por ejemplo, `idTransaccion` tiene `minOccurs=0` en el WSDL y es **S, C(15)** en el manual.

El árbol de tipos contiene **150 entradas** para RegistrarTitEnvios, **74** para RegistrarMicDta y **4** para RegistrarConvoy, contando estructuras y campos anidados: **228 entradas**. La lista siguiente conserva todos los campos del WSDL; donde no se concilió la regla del manual se consigna expresamente. **No hay valores del convoy:** el convoy literal no pudo construirse ni completarse en pantalla. Por tanto ningún formato puede declararse OK ni incorrecto sin dato real; todos figuran **NO VERIFICADO**.

El método `RegistrarConvoy` usa `idMicDtaRemol` e identificadores `idMicDta`, que AFIP asigna al registrar: sin transmisión no se obtienen esos identificadores reales. La salida XML local no acredita aceptación externa ni reemplaza el registro.

| Campo WSDL (ruta completa) | Tipo XSD | `minOccurs` / `maxOccurs` | Obligatoriedad, largo/formato del manual *verificado cuando disponible* | Valor del convoy / origen | Disponibilidad y dictamen |
|---|---|---|---|---|---|
| `RegistrarTitEnviosParam.idTransaccion` | `string` | 0/1 | S; C(15) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios` | `ArrayOfTitTransEnvio` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio` | `TitTransEnvio` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.codViaTrans` | `int` | 1/1 | S; N(2); Hidrovía=8 | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.idTitTrans` | `string` | 0/1 | S; C(36) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.obsDeclaAduInter` | `string` | 0/1 | N; C(500) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente` | `Remitente` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.codPais` | `string` | 0/1 | S; C(2) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.nomRazSoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.idFiscal` | `string` | 0/1 | S; C(14) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.tipDocIdent` | `string` | 0/1 | N; C(3) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.remitente.nroDocIdent` | `string` | 0/1 | N; C(16) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario` | `Consignatario` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.nomRazSoc` | `string` | 0/1 | S; C(50) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.consignatario.idFiscal` | `string` | 0/1 | S; C(14) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario` | `Destinatario` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.nomRazSoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destinatario.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado` | `Notificado` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.nomRazSoc` | `string` | 0/1 | S; C(50) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.notificado.idFiscal` | `string` | 0/1 | N; C(14) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.indFinCom` | `string` | 0/1 | S; C(1); S/N | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.nroFact` | `ArrayOfString` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.nroFact.nroFact` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.indFraccTransp` | `string` | 0/1 | S; C(1); S/N | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.indConsol` | `string` | 0/1 | S; C(1); S/N | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.idManiCargaArrPaisPart` | `string` | 0/1 | N; C(20) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.idDocTranspArrPaisPart` | `string` | 0/1 | N; C(39) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.origen` | `Origen` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.origen.codAdu` | `string` | 0/1 | S; C(3) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destino` | `Destino` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destino.codPais` | `string` | 0/1 | S; C(2) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.destino.codAdu` | `string` | 0/1 | S; C(9) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios` | `ArrayOfEnvio` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio` | `Envio` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones` | `ArrayOfDestinacion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion` | `Destinacion` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.idDecla` | `string` | 0/1 | S; C(16) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.montoFob` | `decimal` | 1/1 | S; N(18,2) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.montoFlete` | `decimal` | 1/1 | S; N(18,2) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.montoSeg` | `decimal` | 1/1 | S; N(18,2) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.codDivisaFob` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.codDivisaFle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.codDivisaSeg` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items` | `ArrayOfItem` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items.Item` | `Item` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items.Item.nroItem` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items.Item.peso` | `decimal` | 1/1 | S; N(12,4) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items.Item.indNCM` | `string` | 0/1 | N; C(10); transbordos | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.items.Item.codIMO` | `string` | 0/1 | N; C(3); condicional | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.docAnexos` | `ArrayOfDocAnexo` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.docAnexos.DocAnexo` | `DocAnexo` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.docAnexos.DocAnexo.codTipDoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.docAnexos.DocAnexo.documento` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos` | `ArrayOfBulto` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto` | `Bulto` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.cantBultos` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.cantBultosTotFrac` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.pesoBruto` | `decimal` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.pesoBrutoTotFrac` | `decimal` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.codTipEmbalaje` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.descMercaderia` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.marcaNro` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.indCargSuelt` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.destinaciones.Destinacion.bultos.Bulto.idContenedor` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.indUltFra` | `string` | 0/1 | S; C(1); S/N | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.idFiscalATAMIC` | `string` | 0/1 | S; C(14) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperOrigen` | `LugOperOrigen` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperOrigen.codLugOper` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperOrigen.codCiu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperDestino` | `LugOperDestino` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperDestino.codLugOper` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.lugOperDestino.codCiu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransEnvios.TitTransEnvio.envios.Envio.idEnvio` | `int` | 1/1 | S; N(3) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios` | `ArrayOfTitTransContVacio` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio` | `TitTransContVacio` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.codViaTrans` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.idTitTrans` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.idContenedores` | `ArrayOfString1` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.idContenedores.idCont` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente` | `Remitente` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.nomRazSoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.idFiscal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.tipDocIdent` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.remitente.nroDocIdent` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario` | `Consignatario` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.nomRazSoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.consignatario.idFiscal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario` | `Destinatario` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.nomRazSoc` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destinatario.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.origen` | `OrigenContVacio` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.origen.codAdu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.origen.codLugOper` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.origen.codCiu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destino` | `DestinoContVacio` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destino.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destino.codAdu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destino.codLugOper` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.destino.codCiu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.titulosTransContVacios.TitTransContVacio.idFiscalATAMIC` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores` | `ArrayOfContenedor` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor` | `Contenedor` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.id` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.codMedida` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.condicion` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.accesorio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.precintos` | `ArrayOfString2` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarTitEnviosParam.contenedores.Contenedor.precintos.precinto` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.idTransaccion` | `string` | 0/1 | S; C(15) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta` | `MicDta` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.codViaTrans` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista` | `Transportista` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.nombre` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.idFiscal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.transportista.tipTrans` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo` | `PropVehiculo` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.nombre` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio` | `Direccion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio.barrio` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio.ciudad` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio.codPostal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio.estado` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.domicilio.nombreCalle` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.propVehiculo.idFiscal` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.indEnLastre` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo` | `Vehiculo` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.patente` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.patentesRemol` | `ArrayOfPatenteRemol` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.patentesRemol.PatenteRemol` | `PatenteRemol` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.patentesRemol.PatenteRemol.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.patentesRemol.PatenteRemol.id` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.marca` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.nroChasis` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.modChasis` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.anioFab` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.capTraccion` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.acceTipNum` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.precintos` | `ArrayOfString4` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.vehiculo.precintos.nroPrecin` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.conductores` | `ArrayOfConductor` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.conductores.Conductor` | `Conductor` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.conductores.Conductor.nombre` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.conductores.Conductor.tipDocIdent` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.conductores.Conductor.nroDocIdent` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.cargasSueltasIdTrack` | `ArrayOfString5` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.cargasSueltasIdTrack.cargaSueltaIdTrack` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.titTransContVaciosIdTrack` | `ArrayOfString6` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.titTransContVaciosIdTrack.titTransContVacioIdTrack` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.contenedoresConCarga` | `ArrayOfString1` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.contenedoresConCarga.idCont` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf` | `ArrayOfRutInf` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf` | `RutInf` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.idRefUniTrs` | `ArrayOfString7` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.idRefUniTrs.idRefUniTr` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.descRutItinerarios` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.plazo` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg` | `ArrayOfEventoProg` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg` | `EventoProg` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.codAdu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.codLugOper` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.codCiu` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.fecha` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.id` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.rutasInf.RutInf.eventosProg.EventoProg.orden` | `int` | 1/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion` | `Embarcacion` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.codPais` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.id` | `string` | 0/1 | S; C(10) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.nombre` | `string` | 0/1 | S; C(50) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.tipEmb` | `string` | 0/1 | S; C(3); EMP/REM/BUM/BAR | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.indIntegraConvoy` | `string` | 0/1 | S; C(1); S/N | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.indSolicitaPres` | `string` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarMicDtaParam.micDta.embarcacion.idFiscalATARemol` | `string` | 0/1 | S; C(14); condicional | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarConvoyParam.idTransaccion` | `string` | 0/1 | S; C(15) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarConvoyParam.idMicDtaRemol` | `string` | 0/1 | S; C(16) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarConvoyParam.idMicDta` | `ArrayOfString8` | 0/1 | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |
| `RegistrarConvoyParam.idMicDta.idMicDta` | `string` | 0/unbounded | Pendiente conciliar con manual (WSDL no fija largo) | No obtenido; origen no determinable sin convoy | **NO VERIFICADO** |

### Datos solicitados al operador y defectos

No se pudieron completar en pantalla las fechas, destinaciones o CUIT de consignatarios: **no se abrió el recorrido autenticado y faltan los buques del convoy**. No se agregan valores supuestos, fechas ficticias, CUIT inventados ni XML simulados como evidencia. Un campo marcado «FALTA» exige haber comprobado su fuente real; un «FORMATO INCORRECTO» exige un valor y el límite específico verificado. Ninguna de ambas clasificaciones puede atribuirse en esta etapa.

### Recomendación de gate

**HOLD etapa 2 para Stage:** volver a correr el ensayo con las tres embarcaciones cargadas legítimamente o con un criterio autorizado de alta, sesión real de Leandro y capturas de cada paso; luego completar por campo la matriz AFIP con datos persistidos y XML offline. No corregir ni desplegar en base a esta revisión parcial.

## Archivos locales privados (no se comittean)

- `/home/victor/qa_stage_20261127/a_before_20261127.sql` (backup de A).
- `/home/victor/qa_stage_20261127/stage_cargas_20261127.sql` (copia de datos de Stage).
- `/home/victor/qa_stage_20261127/wsdl_fields.tsv` (inventario derivado del WSDL).
- `/home/victor/qa_stage_20261127/00_acceso_sin_sesion.png` (captura sin datos privados).

*No se publican dumps de Stage, secretos ni credenciales en el repositorio.*
