<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carpeta de contenido
    |--------------------------------------------------------------------------
    |
    | Ubicación de los artículos en Markdown y del archivo de sinónimos.
    |
    */

    'carpeta' => resource_path('manual'),

    /*
    |--------------------------------------------------------------------------
    | Temas
    |--------------------------------------------------------------------------
    |
    | Carpeta => nombre visible. El orden de esta lista es el orden en que
    | se muestran los temas.
    |
    */

    'temas' => [
        'primeros-pasos' => 'Primeros pasos',
        'administracion' => 'Administración',
        'mi-empresa' => 'Mi empresa',
        'datos-maestros' => 'Datos maestros',
        'viajes-y-cargas' => 'Viajes y cargas',
        'importar-manifiestos' => 'Importar manifiestos',
        'afip-argentina' => 'AFIP Argentina',
        'paraguay-dna' => 'Paraguay DNA',
        'afip-otros-roles' => 'AFIP: desconsolidado y transbordos',
        'reportes' => 'Reportes',
        'preguntas-frecuentes' => 'Preguntas frecuentes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Borradores
    |--------------------------------------------------------------------------
    |
    | Con true, los artículos en estado "borrador" también se muestran.
    |
    */

    'mostrar_borradores' => (bool) env('MANUAL_BORRADORES', false),

];
