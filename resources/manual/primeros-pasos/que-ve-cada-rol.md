---
titulo: Qué puede hacer cada usuario
tema: primeros-pasos
orden: 5
roles: [super-admin, company-admin, user]
palabras_clave: [rol, permisos, menú, administrador, operador, super administrador, Cargas, Desconsolidador, Transbordos]
relacionados: [primeros-pasos/ingresar-y-perfil]
estado: borrador
verificado:
  version: a30a001
  fecha: 2026-11-03
  entorno: codigo
---

## Para qué sirve

Explica por qué dos personas pueden ver menús distintos en el sistema. Lo que usted ve depende de dos cosas: su tipo de usuario y los roles de negocio de su empresa.

## Antes de empezar

- Ingrese al sistema con su cuenta. Si necesita ayuda, vea [Cómo ingresar y editar su perfil](manual:primeros-pasos/ingresar-y-perfil).
- Hay tres tipos de usuario:
  - **Super Administrador**: administra el sistema completo (usuarios, empresas y catálogos). No trabaja dentro de una empresa.
  - **Administrador**: administra su empresa, sus operadores, la configuración y los certificados digitales.
  - **Operador**: trabaja dentro de su empresa con los menús que habilitan los roles de esa empresa.
- Cada empresa tiene uno o más roles de negocio: **Cargas**, **Desconsolidador** y **Transbordos**. El super administrador los asigna al dar de alta o editar la empresa.

## Pasos

1. Ingrese al sistema y haga clic en **Dashboard**, en la barra superior.
2. Revise el encabezado de la pantalla. Si ve **Panel de Administración** y la leyenda **Super Administrador**, usted es super administrador. Continúe en el paso 6.
3. Si trabaja en una empresa, el encabezado muestra el nombre de la empresa y, debajo, su tipo de usuario: **Administrador** u **Operador**.
4. Junto al tipo de usuario, revise los roles de su empresa; por ejemplo, **Cargas**. Si no aparece ningún rol, su empresa todavía no tiene roles asignados.
5. Observe el **Panel Empresa**, a la izquierda de las pantallas de su empresa. Sus secciones también dependen de los roles de la empresa.
6. Abra los menús de la barra superior y compare lo que ve con la lista de la sección siguiente.

## Cómo saber que salió bien

Los menús que ve coinciden con su tipo de usuario y con los roles de su empresa:

- Super administrador: en la barra superior ve **Administración** (**Usuarios**, **Empresas**, **Clientes**, **Propietarios**) y **Catálogos** (**Tipos de Contenedor**, **Tipos de Carga**, **Tipos de Packaging**, **Puertos**, **Países**, **Config. AFIP**). Los roles de cada empresa se asignan en **Administración › Empresas**, en la sección **Roles de Empresa**.
- Cualquier usuario de una empresa: ve **Dashboard**, **Gestión › Clientes**, **Webservices › Envíos a Aduanas** y el buscador de la barra superior. El operador puede consultar los clientes; darlos de alta, editarlos o eliminarlos le corresponde al administrador de la empresa.
- Solo el administrador de la empresa: ve además **Gestión › Operadores**, **Gestión › Configuración**, **Webservices › Certificados .p12** y el botón **Nuevo Operador** en el **Dashboard**.
- Empresas con rol **Cargas**: aparece el menú **Manifiestos**, con **Dashboard Manifiestos**, **Viajes**, **Cargas**, **Conocimientos**, **Importar Excel/CSV**, **Exportar Manifiestos** y **Enviar a AFIP/DNA**. **Gestión** suma **Propietarios**, **Capitanes** y **Embarcaciones**, y **Webservices** suma **Importar y Enviar** e **Historial**. En el **Panel Empresa** aparecen **Crear Viaje Completo**, **Manifiestos** y **Reportes**.
- Empresas con rol **Desconsolidador**: en el **Panel Empresa** aparece **Desconsolidados Argentina**.
- Empresas con rol **Transbordos**: en **Webservices › Envíos a Aduanas**, la pantalla **Webservices Aduaneros** muestra el botón **Ingresar a Transbordos**.
- Empresas con rol **Desconsolidador** o **Transbordos**, sin rol **Cargas**: el **Panel Empresa** muestra **Enviar a AFIP/DNA**. No aparecen **Manifiestos**, **Reportes**, **Propietarios**, **Capitanes** ni **Embarcaciones**.
- Permisos del operador: el administrador de la empresa le asigna a cada operador **Puede Importar**, **Puede Exportar** o **Puede Transferir**. Estos permisos no agregan ni quitan menús. Con **Puede Transferir**, el operador ve el botón **Transferir a otra empresa** en los viajes que él creó, si la empresa tiene el rol Cargas.

El menú **Manual** está disponible para todos los usuarios.

## Si algo falla

- **No veo el menú Manifiestos:** su empresa no tiene el rol Cargas. Consulte al administrador de su empresa; los roles de la empresa los asigna el super administrador.
- **No veo Operadores, Configuración o Certificados .p12:** esas opciones son solo para el administrador de la empresa. Si las necesita, consulte con él.
- **No veo Desconsolidados Argentina o Ingresar a Transbordos:** su empresa no tiene el rol Desconsolidador o Transbordos. Consulte al administrador de su empresa.
- **No veo Transferir a otra empresa en un viaje:** el botón aparece solo si usted tiene el permiso **Puede Transferir** y creó ese viaje. Pida al administrador de su empresa que revise sus **Permisos Operativos**.
- **No veo el Panel Empresa:** el panel aparece solo en las pantallas de su empresa. Haga clic en **Dashboard** para volver a ellas.
