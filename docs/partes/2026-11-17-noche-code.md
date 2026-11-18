# Parte de la noche — Code — 17/11/2026

Orden recibido: 1) usuario creador en las cargas de TFP y Navsur, en T4; 2) QA de la ficha de carga; 3) manual de la etapa 2; 4) tests viejos de la carpeta Parsers en main. Sin merges a main ni servidores.

## 1. T4: usuario creador en las cargas de TFP y Navsur — HECHO

Ya estaba terminado y publicado antes de recibir esta orden. El detalle está en la sección 20 de `2026-11-17-code.md`.

**SHA final de `feature/existing-voyage-parsers-a`: `2c906e7`.**
- `5480a3b`: TFP
- `4ea1776`: Navsur
- `2c906e7`: test

Por qué no se tocaron las clases Compat: no tienen código propio para crear la carga; usan el de la clase base. El test las ejercita, y con archivos reales en B la carga queda con el usuario que importó, tanto al crear un viaje nuevo como al agregar a uno existente.

Claudia integró T4 @ `2c906e7` en `release/etapa-2` (`390826a`).

## 2. QA de `fix/ui-shipment-page` @ `9294ece` — la licencia y la barra, OK; el 272,5% de la tarjeta superior no se ve en rojo

**Entorno.** Instancia B en `9294ece`, con importaciones reales:
- G2Ocean: 2.588.864 kg sobre 950 t de capacidad;
- MSC CARE I: 764.487,68 kg sobre 1.100 t.

A cada carga le asigné un capitán existente; a uno le cambié el estado de la licencia a `expired` para probar otra traducción. Al terminar, B quedó restaurada a la base limpia y el servidor detenido.

| Verificación | Resultado | Estado |
|---|---|---|
| Utilización de G2Ocean, tarjeta superior | muestra `272,5%`, sin el tope de 100 | OK |
| Utilización de G2Ocean, barra de capacidad | `272,5% utilizado` en rojo (`text-red-600`, rgb 220,38,38); la barra se llena al 100% | OK |
| Cargado / Capacidad | `2.588,9 t / 950,0 t` | OK |
| Utilización de MSC | `69,5%`, en gris (sin exceso) | OK |
| Licencia `valid` | «Vigente» | OK |
| Licencia `expired` | «Vencida» | OK |
| Los 5 valores del enum de la base (`valid`, `expired`, `suspended`, `revoked`, `pending_renewal`) | los 5 tienen traducción en la vista | OK |
| **Tarjeta superior en rojo** | **no se ve roja** | **DIFERENCIA** |

**La diferencia.**
- Dónde: `resources/views/company/shipments/show.blade.php:146`.
- Escenario: carga G2Ocean al 272,5%.
- Esperado: el número de la tarjeta superior en rojo.
- Obtenido: se ve blanco. La vista le pone la clase `text-red-200`, pero el CSS compilado no la incluye, así que el color calculado es rgb(255,255,255).
- Aun con el CSS recompilado no alcanzaría: simulé la regla y `text-red-200` (#fecaca) es un rosa muy pálido sobre el fondo índigo, que apenas se distingue del blanco (captura `9294ece-g2ocean-tarjeta-con-text-red-200-simulado.png`).
- Propuesta: un color rojo legible sobre ese fondo, o una marca aparte. Solo la barra de capacidad cumple hoy con «en rojo».

**Observaciones menores, fuera de lo pedido:**
- La tarjeta «Peso Bruto» todavía usa el formato inglés (`2,588.9t`), mientras la barra ya usa el argentino (`2.588,9 t`).
- El porcentaje de contenedores también sigue en formato inglés (`163.6% utilizado`).

Evidencia: `/var/www/html/pruebas_importacion/UI-20261117-code/9294ece-*`.
