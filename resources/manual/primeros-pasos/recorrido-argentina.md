---
titulo: "De punta a punta: un viaje con presentación en AFIP"
tema: primeros-pasos
orden: 6
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [recorrido, flujo, empezar, AFIP, MIC/DTA, viaje completo, guía, Argentina]
relacionados:
  - primeros-pasos/recorrido-paraguay
  - preguntas-frecuentes/glosario
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Muestra el orden completo de un viaje que se presenta ante AFIP, desde los datos básicos hasta la llegada, con el artículo que explica cada etapa. Úselo como mapa: cada paso remite a su guía.

## Antes de empezar

- Su empresa tiene el rol **Cargas**, su certificado digital cargado y el ambiente correcto: vea [Cómo cargar un certificado digital de la empresa](manual:mi-empresa/cargar-certificado-digital).
- Si no conoce algún término, consulte el [Glosario](manual:preguntas-frecuentes/glosario).

## Pasos

1. **Datos básicos.** Revise que estén cargados los clientes, las embarcaciones y los capitanes: [clientes](manual:datos-maestros/registrar-un-cliente), [embarcaciones](manual:datos-maestros/registrar-una-embarcacion) y [capitanes](manual:datos-maestros/gestionar-capitanes).
2. **El viaje.** Impórtelo desde el archivo de la naviera ([Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto)) o créelo a mano ([Cómo crear un viaje a mano](manual:viajes-y-cargas/crear-un-viaje-a-mano)). Si viaja en convoy, vea [Cómo armar un viaje de convoy](manual:viajes-y-cargas/armar-un-convoy).
3. **Conocimientos.** Complete o revise cada conocimiento con sus ítems y contenedores: [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).
4. **Información anticipada**, si el viaje trae mercadería a Argentina: [Cómo enviar la información anticipada a AFIP](manual:afip-argentina/enviar-informacion-anticipada).
5. **MIC/DTA.** Regístrelo para una embarcación ([guía](manual:afip-argentina/registrar-micdta-una-embarcacion)) o para un convoy ([guía](manual:afip-argentina/registrar-micdta-convoy)).
6. **Salida de zona primaria**, con el número de viaje que devolvió AFIP: [Cómo registrar la salida de zona primaria](manual:afip-argentina/registrar-salida-zona-primaria).
7. **Arribo a zona primaria**, al llegar: [Cómo registrar el arribo a zona primaria](manual:afip-argentina/registrar-arribo-zona-primaria).
8. **Si hay que corregir algo** después de enviar: [anular un título](manual:afip-argentina/anular-un-titulo), [solicitar la anulación del MIC/DTA](manual:afip-argentina/solicitar-anulacion-micdta) o [rectificar un convoy](manual:afip-argentina/rectificar-un-convoy).

## Cómo saber que salió bien

En **Crear Viaje Completo › Enviar a AFIP/DNA**, la tarjeta del viaje muestra cada etapa enviada, y en la pantalla de MIC/DTA del viaje cada recuadro indica su resultado. Las respuestas de AFIP quedan en **Últimas 10 transacciones MIC/DTA**.

## Si algo falla

- **Un paso pide algo de una etapa anterior** (por ejemplo, "Ejecutar RegistrarMicDta primero"): vuelva al paso correspondiente de esta lista.
- **AFIP rechaza un envío por un código de puerto, aduana o lugar operativo:** el administrador del sistema revisa esos catálogos.
- **No ve Crear Viaje Completo en el menú:** su empresa no tiene el rol **Cargas**. Consulte al administrador.
