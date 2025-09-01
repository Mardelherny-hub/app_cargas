<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Port;

class PortController extends Controller
{
    /**
     * Lista y búsqueda de Puertos (la vista incluirá el componente Livewire).
     */
    public function index()
    {
        return view('admin.ports.index');
    }

    /**
     * Formulario de creación (la vista incluirá el componente Livewire del form).
     */
    public function create()
    {
        return view('admin.ports.create');
    }

    /**
     * Detalle de un Puerto (opcional; útil si querés una vista dedicada).
     */
    public function show(Port $port)
    {
        return view('admin.ports.show', compact('port'));
    }

    /**
     * Formulario de edición (la vista incluirá el componente Livewire del form).
     */
    public function edit(Port $port)
    {
        return view('admin.ports.edit', compact('port'));
    }
}
