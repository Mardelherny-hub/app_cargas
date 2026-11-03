---
titulo: Cómo registrar y consultar un cliente
tema: datos-maestros
orden: 10
roles: [company-admin, user]
palabras_clave: [cliente, CUIT, RUC, documento, país, buscar, consignatario]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite localizar clientes existentes y, si su cuenta está autorizada, registrar uno nuevo.

## Antes de empezar

- Ingrese con una cuenta de su empresa.
- Antes de crear un cliente, compruebe si ya está registrado.
- Para registrar o editar clientes, debe tener rol de administrador de empresa.

## Pasos

1. [Abra Clientes](ruta:company.clients.index).
2. En **Filtros de Búsqueda**, utilice **Buscar Cliente**, **País** o **Tipo de Documento** para comprobar si existe.
3. Si lo encuentra, abra su ficha y revise los datos antes de continuar.
4. Si no existe y es administrador de empresa, acceda a la opción para crear un cliente.
5. En **Información del Cliente**, complete los datos solicitados.
6. Seleccione **País** y **Tipo de Documento**, y escriba el identificador tributario correspondiente.
7. Revise los datos y guarde el cliente.
8. Regrese al listado y búsquelo nuevamente.

## Cómo saber que salió bien

El cliente aparece al buscarlo y puede abrir su ficha.

## Si algo falla

- **Identificador tributario rechazado:** revise país, tipo de documento y número ingresado.
- **El cliente ya existe:** utilice el registro existente; no cree uno duplicado.
- **No aparece la opción de crear:** consulte sus permisos con el administrador.
