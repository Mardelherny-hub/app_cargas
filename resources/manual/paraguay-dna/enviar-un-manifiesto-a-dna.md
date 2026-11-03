---
titulo: Cómo enviar un manifiesto fluvial a DNA Paraguay
tema: paraguay-dna
orden: 10
roles: [company-admin, user]
roles_empresa: [Cargas]
palabras_clave: [Paraguay, DNA, manifiesto, XFFM, adjunto, PDF, XFBL, XFBT, XFCT]
relacionados: [viajes-y-cargas/crear-un-viaje-a-mano, paraguay-dna/cargar-un-conocimiento-fraccionado, paraguay-dna/revisar-un-viaje-cerrado]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite presentar un viaje a DNA Paraguay en el orden XFFM → documentos adjuntos → XFBL → XFBT, cuando hay contenedores → XFCT.

## Antes de empezar

- [Cree el viaje y su carga](manual:viajes-y-cargas/crear-un-viaje-a-mano) y [complete sus conocimientos, ítems y contenedores](manual:viajes-y-cargas/cargar-conocimientos-e-items). Su empresa debe tener el rol **Cargas**.
- Solicite al administrador que compruebe el RUC de la empresa, el certificado de Paraguay y el ambiente de envío previsto para su operación.
- Prepare los documentos que deba adjuntar: cada archivo debe ser PDF, de hasta 5 MB, y debe tener conocimiento asociado, tipo y número de documento.
- Compruebe que el viaje todavía no esté cerrado en DNA y que la documentación esté completa antes de iniciar los envíos.

## Pasos

1. Abra **Enviar a AFIP/DNA** desde el menú lateral. Busque el viaje y haga clic en **Enviar Ahora** en la tarjeta **Manifiesto**, bajo **Paraguay - DNA**.
2. En **Paraguay - Manifiesto Fluvial DNA**, revise el viaje y resuelva los **Errores de Validación** antes de continuar. Revise también las **Advertencias**.
3. Haga clic en **Enviar XFFM (Carátula)** y confirme el envío a DNA Paraguay.
4. Revise la ventana de respuesta y compruebe que DNA haya aceptado el envío. Verifique **Nº Viaje Paraguay** en **Estado del Manifiesto** antes de continuar.
5. En **Documentos Adjuntos (DocAnexo)**, seleccione **Conocimiento (BL)** y **Tipo Documento**, complete **Nº Documento**, seleccione el PDF y haga clic en **Subir**. Repita para cada documento que corresponda adjuntar.
6. En la lista de adjuntos, haga clic en **DNA** junto a cada documento y confirme su envío. Compruebe que aparezca la marca **✓ DNA**. Si su presentación no requiere adjuntos, continúe al paso siguiente.
7. Haga clic en **Enviar XFBL (Conocimientos)** y confirme. Revise la respuesta de DNA y continúe solo si el envío fue aceptado.
8. Si el viaje tiene contenedores, haga clic en **Enviar XFBT (Contenedores)**, confirme y revise la respuesta. Si aparece **Viaje sin contenedores - XFBT no requerido**, omita ese envío.
9. Cuando los envíos anteriores estén completos y no necesite agregar o corregir documentación, haga clic en **Cerrar Viaje (XFCT)** y confirme. Revise la respuesta de DNA.
10. Compruebe que aparezca **Viaje cerrado en DNA**. Si necesita revisar el resultado, busque XFCT en **Historial de Transacciones GDSF** y abra **Ver Detalles**.

## Cómo saber que salió bien

XFFM devuelve el **Nº Viaje Paraguay**. Los documentos enviados muestran **✓ DNA** y las tarjetas registran los envíos de XFBL y, cuando corresponde, XFBT.

El cierre queda confirmado cuando aparece **Viaje cerrado en DNA**, con la fecha y la transacción de XFCT procesado. Puede [revisar el cierre y sus comprobantes](manual:paraguay-dna/revisar-un-viaje-cerrado).

## Si algo falla

- **Certificado digital Paraguay requerido**: solicite al administrador que revise la configuración del certificado de Paraguay para el ambiente correspondiente.
- **Empresa sin RUC/Tax ID configurado**: complete la identificación de la empresa antes de enviar.
- **Requiere XFFM Primero**: complete XFFM y compruebe que tenga número de viaje antes de seguir.
- Si la respuesta indica **Rechazado por DNA**, lea **Estado**, **Código** y **Mensaje** y corrija el dato señalado antes de reintentar.
- Si la ventana indica **Enviado (sin nroViaje)**, revise la respuesta y el historial; no continúe sin comprobar el número de viaje.
- Si **Cerrar Viaje (XFCT)** no está disponible, compruebe XFFM y XFBL, y también XFBT si el viaje tiene contenedores.
- Si aparece **Viaje cerrado en DNA**, [revise el cierre](manual:paraguay-dna/revisar-un-viaje-cerrado) antes de intentar nuevos envíos.
