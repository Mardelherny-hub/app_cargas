<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CargoType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CargoTypeController extends Controller
{
    /**
     * Columnas de sistema que no deberían mostrarse/buscarse.
     */
    protected array $systemColumns = [
        'id','created_at','updated_at','deleted_at',
        'created_by_user_id','updated_by_user_id',
    ];

    public function index(Request $request)
    {
        $model = new CargoType();
        $table = $model->getTable();

        // Columnas reales de la BD
        $columnsDb = Schema::getColumnListing($table);

        // Fillables del modelo (pueden ser subset/superset)
        $fillables = $model->getFillable();
        $all = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;

        // Columnas visibles (sin sistema)
        $visible = array_values(array_diff($all, $this->systemColumns));

        // Filtramos columnas NO buscables (ej. JSON) vía INFORMATION_SCHEMA
        $dbName = DB::getDatabaseName();
        $rows = DB::select(
            "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$dbName, $table]
        );

        $dataTypes = [];
        foreach ($rows as $r) {
            $dataTypes[$r->COLUMN_NAME] = strtolower($r->DATA_TYPE);
        }

        // Excluimos JSON de la búsqueda para evitar errores con LIKE
        $searchable = array_values(array_filter($visible, function ($col) use ($dataTypes) {
            return ($dataTypes[$col] ?? null) !== 'json';
        }));

        $q = trim((string) $request->get('q'));

        $query = CargoType::query();

        if ($q !== '' && !empty($searchable)) {
            $query->where(function ($sub) use ($searchable, $q) {
                foreach ($searchable as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        $items = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.cargo-types.index', [
            'items'    => $items,
            'q'        => $q,
            'columns'  => $visible,     // para pintar tabla de manera dinámica
        ]);
    }

    public function create()
    {
        $item = new CargoType();

        // Columnas visibles para construir el form o cargar Livewire
        $table = $item->getTable();
        $columnsDb = Schema::getColumnListing($table);
        $fillables = $item->getFillable();
        $all = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;
        $visible = array_values(array_diff($all, $this->systemColumns));

        return view('admin.cargo-types.create', [
            'item'    => $item,
            'columns' => $visible,
        ]);
    }

    public function store(Request $request)
    {
        // Si usás Livewire para crear, este método puede no usarse.
        // Lo dejamos operativo de todos modos, tomando sólo fillables.
        $model = new CargoType();
        $data = $request->only($model->getFillable());

        DB::transaction(function () use ($data) {
            // Limpiar null/'' para permitir defaults de BD
            $payload = collect($data)->reject(function ($v) {
                return $v === null || (is_string($v) && trim($v) === '');
            })->all();

            // created_by_user_id si existe en fillable
            if (array_key_exists('created_by_user_id', $payload)) {
                $payload['created_by_user_id'] = auth()->id();
            }

            CargoType::create($payload);
        });

        return redirect()
            ->route('admin.cargo-types.index')
            ->with('success', 'Tipo de carga creado correctamente.');
    }

    public function edit(CargoType $cargoType)
    {
        $item = $cargoType;

        $table = $item->getTable();
        $columnsDb = Schema::getColumnListing($table);
        $fillables = $item->getFillable();
        $all = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;
        $visible = array_values(array_diff($all, $this->systemColumns));

        return view('admin.cargo-types.edit', [
            'item'    => $item,
            'columns' => $visible,
        ]);
    }

    public function update(Request $request, CargoType $cargoType)
    {
        $data = $request->only($cargoType->getFillable());

        DB::transaction(function () use ($cargoType, $data) {
            // Limpiar null/'' para permitir defaults de BD
            $payload = collect($data)->reject(function ($v) {
                return $v === null || (is_string($v) && trim($v) === '');
            })->all();

            // updated_by_user_id si tu modelo/tabla lo soporta (no lo asumo)
            $cargoType->update($payload);
        });

        return redirect()
            ->route('admin.cargo-types.index')
            ->with('success', 'Tipo de carga actualizado correctamente.');
    }

    public function destroy(CargoType $cargoType)
    {
        DB::transaction(function () use ($cargoType) {
            $cargoType->delete();
        });

        return redirect()
            ->route('admin.cargo-types.index')
            ->with('success', 'Tipo de carga eliminado correctamente.');
    }
}
