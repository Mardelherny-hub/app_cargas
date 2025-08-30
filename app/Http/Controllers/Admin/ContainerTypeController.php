<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContainerType; // Ajusta el namespace del modelo si difiere
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContainerTypeController extends Controller
{
    public function index(Request $request)
    {
        $model   = new ContainerType();
        $table   = $model->getTable();

        // Columnas reales en la BD
        $columnsDb = Schema::getColumnListing($table);

        // Fillables del modelo (pueden contener extras no existentes)
        $columnsFillable = $model->getFillable();

        // Usamos solo las columnas que EXISTEN en la tabla.
        // Si el fillable está vacío, usamos todas las de la BD.
        $columns = $columnsFillable
            ? array_values(array_intersect($columnsFillable, $columnsDb))
            : $columnsDb;

        $q = trim((string) $request->get('q'));
        $query = ContainerType::query();

        if ($q !== '' && !empty($columns)) {
            $query->where(function ($sub) use ($columns, $q) {
                foreach ($columns as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        $items = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.container-types.index', compact('items', 'q', 'columns'));
    }


    public function create()
    {
        $item = new ContainerType();
        $columns = $item->getFillable();

        return view('admin.container-types.create', compact('item', 'columns'));
    }

    public function store(Request $request)
    {
        $model   = new ContainerType();
        $columns = $model->getFillable();

        // No asumimos reglas; solo permitimos guardar columnas declaradas en $fillable.
        $data = $request->only($columns);

        DB::transaction(function () use ($data) {
            ContainerType::create($data);
        });

        return redirect()
            ->route('admin.container-types.index')
            ->with('success', 'Tipo de contenedor creado correctamente.');
    }

    public function edit(ContainerType $containerType)
    {
        $item = $containerType;
        $columns = $item->getFillable();

        return view('admin.container-types.edit', compact('item', 'columns'));
    }

    public function update(Request $request, ContainerType $containerType)
    {
        $columns = $containerType->getFillable();
        $data    = $request->only($columns);

        DB::transaction(function () use ($containerType, $data) {
            $containerType->update($data);
        });

        return redirect()
            ->route('admin.container-types.index')
            ->with('success', 'Tipo de contenedor actualizado correctamente.');
    }

    public function destroy(ContainerType $containerType)
    {
        DB::transaction(function () use ($containerType) {
            $containerType->delete();
        });

        return redirect()
            ->route('admin.container-types.index')
            ->with('success', 'Tipo de contenedor eliminado correctamente.');
    }
}
