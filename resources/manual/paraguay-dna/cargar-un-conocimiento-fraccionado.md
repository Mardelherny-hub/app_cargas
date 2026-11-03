---
titulo: Cómo completar un conocimiento fraccionado para DNA Paraguay
tema: paraguay-dna
orden: 20
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [Paraguay, DNA, conocimiento, título, fraccionado, fracción, bultos, peso, XFBL]
relacionados: [viajes-y-cargas/cargar-conocimientos-e-items, paraguay-dna/enviar-un-manifiesto-a-dna, paraguay-dna/revisar-un-viaje-cerrado]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite identificar un conocimiento transportado en fracciones, indicar los bultos y el peso de la fracción actual y asociarla al manifiesto de la primera fracción cuando corresponda.

## Antes de empezar

- [Cargue el conocimiento y sus ítems](manual:viajes-y-cargas/cargar-conocimientos-e-items). Su empresa debe tener el rol **Cargas** y el conocimiento debe permitir edición.
- Tenga los bultos y el peso bruto del conocimiento completo, además de los correspondientes a la fracción de este transporte.
- Si corresponde asociar la fracción a la primera, tenga el número de manifiesto que DNA devolvió al procesar la primera fracción.
- Compruebe que el viaje todavía no esté cerrado en DNA. [Revise cómo reconocer un viaje cerrado](manual:paraguay-dna/revisar-un-viaje-cerrado).

## Pasos

1. Abra **Manifiestos › Conocimientos** e ingrese al detalle del conocimiento que corresponde al viaje y a la fracción que va a presentar.
2. Haga clic en **Editar**.
3. Revise **Total de Bultos** y **Peso Bruto (kg)**: deben corresponder al conocimiento completo. Si no coinciden con su documentación, solicite revisión antes de continuar.
4. Busque **Opciones de Consolidación** y marque **Fraccionado**.
5. Complete **Bultos Parciales** y **Peso Parcial (kg)** con las cantidades de esta fracción. Los bultos deben ser al menos 1 y el peso mayor que cero; ambos parciales deben ser menores que sus respectivos totales.
6. Cuando corresponda vincular esta fracción a la primera, complete **ID Manifiesto Fracción Primaria (DNA Paraguay)** con el número de manifiesto devuelto por DNA al procesar la primera fracción. El campo admite hasta 20 caracteres.
7. Haga clic en **Actualizar Conocimiento**.
8. Vuelva a **Editar** y compruebe que **Fraccionado**, los dos parciales y el identificador de la primera fracción hayan quedado guardados. Revise nuevamente los totales del conocimiento.
9. Con los datos completos y comprobados, continúe con [el envío del manifiesto a DNA Paraguay](manual:paraguay-dna/enviar-un-manifiesto-a-dna). Si agrega o modifica ítems antes de enviar, vuelva a comprobar los totales y los parciales.

## Cómo saber que salió bien

Al volver a editar el conocimiento, **Fraccionado** sigue marcado y aparecen los valores guardados en **Bultos Parciales**, **Peso Parcial (kg)** y, cuando corresponde, **ID Manifiesto Fracción Primaria (DNA Paraguay)**.

Los totales corresponden al conocimiento completo y los parciales a este transporte. El envío de XFBL permite presentar ambas cantidades a DNA.

## Si algo falla

- **Este conocimiento no puede ser editado en su estado actual.**: revise el estado del conocimiento y solicite asistencia antes de intentar cambiarlo.
- Si no aparecen los campos parciales, compruebe que haya marcado **Fraccionado** en **Opciones de Consolidación**.
- Si los parciales están vacíos, son cero o no son menores que los totales, corríjalos y vuelva a guardar antes de enviar.
- Si los totales no coinciden con el conocimiento completo o están bloqueados para edición, detenga el envío y solicite revisión de las cantidades. Compruébelos también después de agregar o modificar ítems.
- Si no conoce el identificador de la primera fracción, consulte el manifiesto procesado por DNA antes de completar ese dato.
- Si aparece **Viaje cerrado en DNA**, [revise el cierre](manual:paraguay-dna/revisar-un-viaje-cerrado); modificar el conocimiento no habilita nuevos envíos sobre un viaje cerrado.
