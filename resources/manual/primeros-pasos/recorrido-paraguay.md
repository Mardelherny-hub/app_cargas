---
titulo: "De punta a punta: un viaje con presentación en DNA Paraguay"
tema: primeros-pasos
orden: 7
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [recorrido, flujo, empezar, DNA, Paraguay, manifiesto, XFFM, cierre, guía]
relacionados:
  - primeros-pasos/recorrido-argentina
  - preguntas-frecuentes/glosario
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Muestra el orden completo de un viaje que se presenta ante DNA Paraguay, desde los datos básicos hasta el cierre, con el artículo que explica cada etapa.

## Antes de empezar

- Su empresa tiene el rol **Cargas**, su RUC, el certificado de Paraguay y el ambiente correcto: vea [Cómo configurar los datos y webservices de la empresa](manual:mi-empresa/configurar-la-empresa).
- Si no conoce algún término, consulte el [Glosario](manual:preguntas-frecuentes/glosario).

## Pasos

1. **Datos básicos.** Clientes, embarcaciones y capitanes cargados: [clientes](manual:datos-maestros/registrar-un-cliente), [embarcaciones](manual:datos-maestros/registrar-una-embarcacion) y [capitanes](manual:datos-maestros/gestionar-capitanes).
2. **El viaje.** Impórtelo ([Cómo importar un manifiesto](manual:importar-manifiestos/importar-un-manifiesto)) o créelo a mano ([Cómo crear un viaje a mano](manual:viajes-y-cargas/crear-un-viaje-a-mano)).
3. **Conocimientos**, con sus ítems y contenedores: [Cómo cargar un conocimiento con ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items).
4. **Conocimientos fraccionados**, si una carga viaja en partes: [Cómo completar un conocimiento fraccionado](manual:paraguay-dna/cargar-un-conocimiento-fraccionado). Revise los totales antes de enviar.
5. **Envíos a DNA, en orden:** carátula (XFFM), documentos adjuntos, conocimientos (XFBL), contenedores (XFBT, si los hay) y cierre (XFCT): [Cómo enviar un manifiesto fluvial a DNA Paraguay](manual:paraguay-dna/enviar-un-manifiesto-a-dna).
6. **Después del cierre**, para consultar la presentación: [Cómo revisar un viaje cerrado en DNA Paraguay](manual:paraguay-dna/revisar-un-viaje-cerrado).

## Cómo saber que salió bien

La pantalla del manifiesto DNA muestra el **Nº Viaje Paraguay** después de la carátula y **Viaje cerrado en DNA** después del cierre.

## Si algo falla

- **"Requiere XFFM Primero":** envíe la carátula antes que el resto.
- **El viaje ya está cerrado en DNA:** no admite nuevos envíos; vea [cómo revisar un viaje cerrado](manual:paraguay-dna/revisar-un-viaje-cerrado).
- **No ve Crear Viaje Completo en el menú:** su empresa no tiene el rol **Cargas**. Consulte al administrador.
