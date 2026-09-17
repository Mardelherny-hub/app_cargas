# Importaciones — rama canónica y control de regresiones

Fecha de normalización: 16/09/2026.

## Fuente de verdad

Para importaciones, parsers, edición de datos importados y correcciones surgidas de las pruebas de Roberto, la única fuente de verdad es `main`.

Toda nueva corrección debe partir del `main` vigente y debe volver a `main` después de validar CI. No se debe recuperar código de una rama histórica directamente hacia Stage o producción.

## Regla de direcciones específicas

La regla común está centralizada en:

`app/Services/Parsers/Concerns/ResolvesClientAddresses.php`

Cuando una dirección importada difiere de la dirección principal del cliente:

- la dirección importada se conserva en el conocimiento;
- `use_specific_data` queda en `false`;
- el operador decide manualmente si quiere activar esa dirección específica.

La regresión está protegida por:

`tests/Unit/Services/Parsers/ResolvesClientAddressesTest.php`

Y el workflow de compatibilidad ejecuta ese test en cada push/PR contra `main`.

## Ramas históricas ya normalizadas

Las siguientes ramas ya no contienen trabajo pendiente propio y fueron adelantadas al mismo commit que `main` para evitar que vuelvan a utilizar código viejo:

- `fix/cmsp-import-20260916`
- `fix/guaran-20sd-container-type`
- `fix/guaran-final-import-observations`
- `fix/guaran-import-context`
- `fix/guaran-real-excel-importer`
- `fix/guaran-real-excel-importer-candidate-2`
- `fix/import-date-fallbacks-all-parsers`
- `fix/import-date-fallbacks-tests`
- `fix/import-operational-dates-and-voyage-operation`
- `fix/smoke-imports-2026-09-14`
- `noop`

`fix/smoke-imports-2026-09-14` fue normalizada después de verificar que su árbol final era exactamente el mismo árbol consolidado en `main` por el commit `304a7b3f0df76451ac9abd53eefe2b034bc0c279`.

## Ramas preservadas — NO canónicas

Estas ramas conservan commits que no son ancestros directos del `main` actual. Se mantienen únicamente para auditoría o trabajo específico y no deben utilizarse como base para corregir importaciones sin una revisión expresa contra `main`:

- `audit/importadores-pendientes-stage1-sep2026` — auditoría histórica.
- `auditoria-importaciones-afip-14ago` — auditoría histórica extensa.
- `fix/guaran-real-excel-importer-candidate` — candidato experimental histórico.
- `fix/import-dates-common-fallbacks` — propuesta histórica de normalización de fechas; no es la implementación canónica actual.
- `hotfix-pruebas-cliente-18ago` — hotfix histórico con commits propios; revisar antes de rescatar cualquier cambio.

## Rama funcional independiente

- `feat/desconsolidados-argentina-e2e` contiene el trabajo específico de Desconsolidados Argentina. No es una rama de importaciones y no debe usarse como fuente de fixes de parsers/importadores.

## Regla operativa desde esta normalización

1. Antes de tocar un parser o una regla de importación, revisar primero `main`.
2. Si Roberto indica “esto ya estaba corregido”, buscar el comportamiento en `main` y en los tests antes de volver a programarlo.
3. Las reglas transversales deben resolverse en la capa común correspondiente y no repetirse parser por parser.
4. Todo bug corregido que pueda reaparecer debe quedar acompañado por un test de regresión ejecutado por CI.
5. Ninguna rama histórica se mezcla completa en `main`. Si contiene algo aún útil, se identifica el cambio puntual, se contrasta con el código actual y se integra de forma explícita y comprobable.
6. Stage y producción deben desplegar únicamente commits descendientes de `main`.
