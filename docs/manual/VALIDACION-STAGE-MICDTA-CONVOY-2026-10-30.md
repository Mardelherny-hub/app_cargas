# Validación en stage — artículo "Cómo registrar el MIC/DTA de un convoy" — 30/10/2026

**Asigna:** Claudia, a través de Charly.
**Responsable:** Work.
**Orden:** iniciar ahora.

## Objetivo

Recorrer en stage, paso por paso, el artículo piloto del manual y confirmar que lo que dice coincide con lo que se ve en pantalla. Es la "pasada en stage" que exige el handoff del manual antes de publicar un artículo.

## Material

- Artículo: `resources/manual/afip-argentina/registrar-micdta-convoy.md`, en la rama `origin/manual/equipo-claudia`.
  `git fetch origin manual/equipo-claudia && git show FETCH_HEAD:resources/manual/afip-argentina/registrar-micdta-convoy.md`
- Contexto, reglas y formato de reporte: `docs/manual/HANDOFF-MANUAL-USUARIO-2026-10-30.md` (rama `origin/docs/manual-usuario-handoff`), secciones 3 a 5.

## Entorno (definido para esta tarea)

- **Servidor:** stage, `cargas.red-byte.com`. AGENTS.md línea 9 todavía la llama producción; hoy es stage.
- **Aduana:** solo AFIP **homologación**. Se autorizan los envíos RegistrarTitEnvios, RegistrarMicDta y RegistrarConvoy, únicamente sobre el viaje de prueba de esta tarea.
- **Empresa:** una empresa de prueba de stage, **nunca** una empresa real del cliente.
- **Control obligatorio antes del primer envío:** confirmar en la configuración de la empresa que el ambiente AFIP es homologación y que tiene certificado de homologación cargado. Si no se cumple alguna de las dos cosas, o no hay una empresa de prueba disponible, **no se envía nada**: se hace solo la parte sin envíos (pasos 1 a 3 y lectura de pantallas) y se informa el bloqueo.
- Los datos del viaje de prueba se cargan desde la interfaz. Nada de escrituras por Tinker.

## Alcance

1. Armar desde la interfaz un viaje de prueba en convoy: una carga para el remolcador sin conocimientos y una carga de barcaza con al menos un conocimiento completo. Anotar los pasos y pantallas, porque sirven para el artículo `viajes-y-cargas/armar-un-convoy` del equipo de Charly.
2. Seguir los 9 pasos del artículo tal como están escritos.
3. En cada paso, comparar los menús, botones, títulos y mensajes del artículo con los de la pantalla, letra por letra.
4. Responder los dos puntos abiertos:
    - ¿El convoy se completa sin ejecutar **2. RegistrarEnvios**? El artículo lo omite.
    - Después de cada método, ¿la página se recarga sola o hay que actualizarla a mano?
5. Comprobar la sección "Cómo saber que salió bien": el ✅ en cada embarcación, el estado **Success** del convoy, el **Nro. Viaje** en Zona Primaria y los botones **📤 Request** / **📥 Response**.
6. Si aparece algún error de los listados en "Si algo falla", anotar el texto exacto que mostró la app.

## Restricciones

- **No** se ejecuta RegistrarSalidaZP, ni anulaciones, ni ningún otro método fuera de los tres autorizados.
- **No** se modifica el artículo ni el código, y **no** se corrigen bugs: todo se reporta.
- Sin commits, sin push, sin acceso a producción.
- Ya conocidos, no reportar: los 4 bugs de la sección 5 del handoff.

## Entrega (para Claudia, vía Víctor)

1. **Entorno usado:** empresa de prueba, ambiente AFIP verificado (con evidencia) y número interno del viaje de prueba.
2. **Tabla por paso:** paso · OK / Diferencia · texto que muestra la pantalla cuando hay diferencia.
3. **Respuestas a los dos puntos abiertos.**
4. **Reporte de bugs** en el formato del handoff (sección 5).
5. **Términos** que un usuario buscaría y que no estén en `_sinonimos.yml`.

## Criterio de aceptación

- [ ] El control de ambiente se hizo antes del primer envío y está documentado.
- [ ] Los 9 pasos tienen resultado (OK o Diferencia).
- [ ] Los dos puntos abiertos están respondidos con lo observado.
- [ ] Cada diferencia trae el texto exacto de la pantalla.
- [ ] No se usó ninguna empresa real ni el ambiente de producción.
