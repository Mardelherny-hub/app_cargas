<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;

class CountryController extends Controller
{
    /**
     * Lista/búsqueda (la vista montará el componente Livewire).
     */
    public function index()
    {
        return view('admin.countries.index');
    }

    /**
     * Formulario de creación (la vista montará el componente Livewire del form).
     */
    public function create()
    {
        return view('admin.countries.create');
    }

    /**
     * Detalle (opcional).
     */
    public function show(Country $country)
    {
        return view('admin.countries.show', compact('country'));
    }

    /**
     * Formulario de edición (la vista montará el componente Livewire del form).
     */
    public function edit(Country $country)
    {
        return view('admin.countries.edit', compact('country'));
    }
}
