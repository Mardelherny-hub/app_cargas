<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackagingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PackagingTypeController extends Controller
{
    /**
     * Columnas de sistema que no deben listarse/buscarse/editarse en formularios.
     */
    protected array $systemColumns = [
        'id','created_at','updated_at','deleted_at',
        'created_by_user_id','updated_by_user_id',
    ];

    public function index(Request $request)
    {
        $model = new PackagingType();
        $table = $model->getTable();

        // Columnas reales de la BD y fillables del modelo
        $columnsDb = Schema::getColumnListing($table);
        $fillables = $model->getFillable();
        $all       = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;

        // Visibles (sin columnas de sistema)
        $visible = array_values(array_diff($all, $this->systemColumns));

        // Tipos de datos para excluir JSON de la búsqueda (LIKE rompe en JSON)
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
        $searchable = array_values(array_filter($visible, fn($c) => ($dataTypes[$c] ?? null) !== 'json'));

        $q = trim((string) $request->get('q'));
        $query = PackagingType::query();

        if ($q !== '' && !empty($searchable)) {
            $query->where(function ($sub) use ($searchable, $q) {
                foreach ($searchable as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        $items = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.packaging-types.index', [
            'items'   => $items,
            'q'       => $q,
            'columns' => $visible,
        ]);
    }

    public function create()
    {
        $item   = new PackagingType();
        $table  = $item->getTable();
        $dbCols = Schema::getColumnListing($table);
        $fills  = $item->getFillable();
        $all    = $fills ? array_values(array_intersect($fills, $dbCols)) : $dbCols;
        $visible = array_values(array_diff($all, $this->systemColumns));

        return view('admin.packaging-types.create', [
            'item'    => $item,
            'columns' => $visible,
        ]);
    }

    public function store(Request $request)
    {
        $model = new PackagingType();
        $data  = $request->only($model->getFillable());

        DB::transaction(function () use ($data) {
            // created_by_user_id si aplica
            if (array_key_exists('created_by_user_id', $data)) {
                $data['created_by_user_id'] = Auth::id();
            }

            // Quitar null/'' para permitir defaults de BD (preserva 0/false)
            $payload = collect($data)->reject(fn($v) => $v === null || (is_string($v) && trim($v) === ''))->all();

            PackagingType::create($payload);
        });

        return redirect()
            ->route('admin.packaging-types.index')
            ->with('success', 'Tipo de packaging creado correctamente.');
    }

    public function edit(PackagingType $packagingType)
    {
        $item   = $packagingType;
        $table  = $item->getTable();
        $dbCols = Schema::getColumnListing($table);
        $fills  = $item->getFillable();
        $all    = $fills ? array_values(array_intersect($fills, $dbCols)) : $dbCols;
        $visible = array_values(array_diff($all, $this->systemColumns));

        return view('admin.packaging-types.edit', [
            'item'    => $item,
            'columns' => $visible,
        ]);
    }

    public function update(Request $request, PackagingType $packagingType)
    {
        $data = $request->only($packagingType->getFillable());

        DB::transaction(function () use ($packagingType, $data) {
            // updated_by_user_id si aplica (no asumimos, sólo seteamos si existe)
            if (array_key_exists('updated_by_user_id', $data)) {
                $data['updated_by_user_id'] = Auth::id();
            }

            $payload = collect($data)->reject(fn($v) => $v === null || (is_string($v) && trim($v) === ''))->all();

            $packagingType->update($payload);
        });

        return redirect()
            ->route('admin.packaging-types.index')
            ->with('success', 'Tipo de packaging actualizado correctamente.');
    }

    public function destroy(PackagingType $packagingType)
    {
        DB::transaction(fn() => $packagingType->delete());

        return redirect()
            ->route('admin.packaging-types.index')
            ->with('success', 'Tipo de packaging eliminado correctamente.');
    }
}
