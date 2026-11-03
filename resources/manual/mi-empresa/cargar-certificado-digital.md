---
titulo: Cómo cargar un certificado digital de la empresa
tema: mi-empresa
orden: 20
roles: [company-admin]
palabras_clave: [certificado, digital, p12, contraseña, argentina, paraguay, renovar]
relacionados: [mi-empresa/configurar-la-empresa]
estado: publicado
verificado:
  version: 9d7ce2e
  fecha: 2026-11-03
  entorno: stage
---

## Para qué sirve

Permite configurar los certificados digitales utilizados por los webservices de la empresa.

## Antes de empezar

- Ingrese como administrador de empresa.
- Tenga los archivos del certificado y sus datos de acceso correspondientes al país.
- Confirme el ambiente de operación de la empresa.

## Pasos

1. [Abra Certificados Digitales](ruta:company.certificates.index).
2. Revise **Certificados por País** para comprobar los existentes.
3. Seleccione la opción para cargar un certificado del país correspondiente.
4. En **Configurar Certificado Digital**, seleccione el país.
5. Adjunte los archivos solicitados. Los campos pueden variar según el país.
6. Complete contraseña, alias y demás información requerida.
7. Envíe el formulario de carga.
8. Regrese a [Certificados Digitales](ruta:company.certificates.index) y revise el estado informado.

## Cómo saber que salió bien

El certificado queda identificado en el apartado del país correspondiente.

## Si algo falla

- **Archivo o contraseña incorrectos:** compruebe que correspondan al mismo certificado.
- **Certificado vencido:** utilice la opción de renovación con la documentación correspondiente.
- **Prueba de conexión:** no la ejecute en producción como parte de una capacitación.
