---
titulo: Cómo revisar un viaje cerrado en DNA Paraguay
tema: paraguay-dna
orden: 30
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [Paraguay, DNA, viaje, cerrado, cierre, XFCT, historial, respuesta, comprobante]
relacionados: [paraguay-dna/enviar-un-manifiesto-a-dna, paraguay-dna/cargar-un-conocimiento-fraccionado]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite comprobar que DNA procesó el cierre de un viaje, consultar la transacción y su respuesta, y reconocer por qué ya no están disponibles nuevos envíos sobre ese viaje.

## Antes de empezar

- Tenga identificado el viaje cuya presentación quiere consultar. Su empresa debe tener el rol **Cargas**.
- Si todavía no completó la presentación, revise [el orden de envíos a DNA Paraguay](manual:paraguay-dna/enviar-un-manifiesto-a-dna).
- Para comprobar un cierre, el viaje debe tener una respuesta de XFCT procesada por DNA.

## Pasos

1. Abra **Enviar a AFIP/DNA** en el menú lateral. Busque el viaje y haga clic en **Enviar Ahora** en la tarjeta **Manifiesto**, bajo **Paraguay - DNA**, para abrir su pantalla.
2. Compruebe el viaje del encabezado y el **Nº Viaje Paraguay** en **Estado del Manifiesto**.
3. Busque **Viaje cerrado en DNA**. Anote la fecha y el número de transacción que aparecen en ese aviso.
4. En **Historial de Transacciones GDSF**, localice la transacción XFCT correspondiente y haga clic en **Ver Detalles**.
5. Revise **Información General**: compruebe **ID Transacción**, **Entorno**, **Fecha** y los datos del viaje que está consultando.
6. Si está disponible, haga clic en **Ver Response XML** para consultar la respuesta de DNA. Un cierre procesado corresponde a **PROCESSED** con código **00**.
7. Vuelva a la pantalla del manifiesto y compruebe que las acciones de envío y **Rectificar** estén deshabilitadas. Conserve los datos de la transacción para cualquier consulta sobre esa presentación.

## Cómo saber que salió bien

**Viaje cerrado en DNA** confirma que existe una respuesta de XFCT procesada correctamente. El aviso identifica la fecha y la transacción del cierre, que puede consultar en el historial.

Sobre ese viaje no se pueden enviar nuevos XFBL, XFBT, XISP, XRSP ni otro XFCT. Cambiar datos del viaje o de sus conocimientos no vuelve a habilitar esos envíos.

## Si algo falla

- Si solo ve **CERRADO** en la tarjeta de XFCT, revise también **Viaje cerrado en DNA** y la respuesta del historial para comprobar el cierre procesado.
- Si no aparece **Viaje cerrado en DNA**, revise la respuesta de XFCT antes de considerar terminada la presentación.
- **No se pudieron cargar los detalles**: vuelva a intentar la consulta y, si persiste, solicite asistencia indicando el viaje y la transacción.
- Si no está disponible **Ver Response XML**, revise la información del historial y solicite asistencia para comprobar el resultado antes de repetir un cierre.
- **Viaje cerrado en DNA. No se pueden enviar nuevos mensajes.**: el cierre ya fue procesado. Para otra operación, prepare un viaje nuevo; si necesita resolver algo de esta presentación, consulte indicando la transacción de cierre.
