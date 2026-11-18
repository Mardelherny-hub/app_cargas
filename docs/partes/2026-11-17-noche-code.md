# Parte de la noche — Code — 17/11/2026

Orden recibido: 1) usuario creador en las cargas de TFP y Navsur, en T4; 2) QA de la ficha de carga; 3) manual de la etapa 2; 4) tests viejos de la carpeta Parsers en main. Sin merges a main ni servidores.

## 1. T4: usuario creador en las cargas de TFP y Navsur — HECHO

Ya estaba terminado y publicado antes de recibir esta orden. El detalle está en la sección 20 de `2026-11-17-code.md`.

**SHA final de `feature/existing-voyage-parsers-a`: `2c906e7`.**
- `5480a3b`: TFP
- `4ea1776`: Navsur
- `2c906e7`: test

Por qué no se tocaron las clases Compat: no tienen código propio para crear la carga; usan el de la clase base. El test las ejercita, y con archivos reales en B la carga queda con el usuario que importó, tanto al crear un viaje nuevo como al agregar a uno existente.
