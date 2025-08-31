<?php

namespace App\Livewire\Admin\CargoTypes;

use App\Models\CargoType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EditCargoType extends Component
{
    /** ID del registro a editar */
    public int $cargoTypeId;

    /** Modelo cargado */
    public CargoType $cargoType;

    /** Columnas visibles (sin sistema) */
    public array $columns = [];

    /** Datos del form */
    public array $form = [];

    /** Tipos/ayudas para inputs y validación */
    public array $isBoolean = [];
    public array $isDate    = [];
    public array $isNumeric = [];
    public array $isJson    = [];
    public array $isEnum    = [];
    public array $enumOptions = [];

    /** Reglas dinámicas derivadas del esquema */
    protected array $systemColumns = [
        'id','created_at','updated_at','deleted_at',
        'created_by_user_id','updated_by_user_id',
    ];
    public array $requiredColumns = [];
    public array $stringMaxLengths = [];
    public array $uniqueColumns = [];

    public function mount(int $cargoTypeId): void
    {
        $this->cargoTypeId = $cargoTypeId;
        $this->cargoType   = CargoType::findOrFail($cargoTypeId);

        $model = new CargoType();
        $table = $model->getTable();

        // Columnas reales
        $columnsDb = Schema::getColumnListing($table);
        $fillables = $model->getFillable();
        $all       = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;

        // Visibles (sin sistema)
        $this->columns = array_values(array_diff($all, $this->systemColumns));

        // === Metadatos INFORMATION_SCHEMA ===
        $db = DB::getDatabaseName();

        $cols = DB::select(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_TYPE, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$db, $table]
        );

        $idx = DB::select(
            "SELECT DISTINCT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$db, $table]
        );
        $uniqueByCol = [];
        foreach ($idx as $r) {
            if ((int) $r->NON_UNIQUE === 0 && $r->INDEX_NAME !== 'PRIMARY') {
                $uniqueByCol[$r->COLUMN_NAME] = true;
            }
        }
        $this->uniqueColumns = array_keys($uniqueByCol);

        $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];

        foreach ($cols as $c) {
            $name = $c->COLUMN_NAME;

            // Required = NOT NULL sin default y no autoincrement
            $isNotNullNoDefault = ($c->IS_NULLABLE === 'NO')
                && is_null($c->COLUMN_DEFAULT)
                && stripos((string) $c->EXTRA, 'auto_increment') === false;

            if (!in_array($name, $this->systemColumns, true) && $isNotNullNoDefault) {
                $this->requiredColumns[] = $name;
            }

            if (!is_null($c->CHARACTER_MAXIMUM_LENGTH)) {
                $this->stringMaxLengths[$name] = (int) $c->CHARACTER_MAXIMUM_LENGTH;
            }

            $dataType   = strtolower((string) $c->DATA_TYPE);
            $columnType = strtolower((string) $c->COLUMN_TYPE);

            // JSON
            $this->isJson[$name] = ($dataType === 'json') || (($casts[$name] ?? null) === 'array');

            // ENUM
            if ($dataType === 'enum' && preg_match("/enum\\((.*)\\)/i", $columnType, $m)) {
                $raw  = $m[1];
                $opts = array_map(
                    fn($s) => trim(stripslashes(trim($s, " '"))),
                    explode(',', $raw)
                );
                $this->isEnum[$name]      = true;
                $this->enumOptions[$name] = $opts;
            } else {
                $this->isEnum[$name] = false;
            }

            // Boolean
            $this->isBoolean[$name] =
                (($casts[$name] ?? null) === 'boolean')
                || preg_match('/^(is_|has_|requires_|allows_)/i', $name)
                || $dataType === 'tinyint';

            // Date
            $this->isDate[$name] = preg_match('/_date$/i', $name) > 0;

            // Numeric
            $this->isNumeric[$name] = in_array($dataType, ['int','integer','bigint','smallint','mediumint','decimal','double','float'], true)
                || (!$this->isBoolean[$name] && preg_match('/(rate|percent|density|weight|time|order|level)/i', $name));
        }

        // === Inicializar form con valores del modelo ===
        foreach ($this->columns as $col) {
            $value = $this->cargoType->getAttribute($col);

            if (($this->isJson[$col] ?? false) && (is_array($value) || is_object($value))) {
                $this->form[$col] = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            } else {
                $this->form[$col] = $value;
            }
        }
    }

    protected function rules(): array
    {
        $rules = [];

        foreach ($this->columns as $col) {
            $r = [];
            $r[] = in_array($col, $this->requiredColumns, true) ? 'required' : 'nullable';

            if ($this->isEnum[$col] ?? false) {
                $r[] = 'in:' . implode(',', $this->enumOptions[$col] ?? []);
            } elseif ($this->isJson[$col] ?? false) {
                $r[] = 'json';
            } elseif ($this->isBoolean[$col] ?? false) {
                $r[] = 'boolean';
            } elseif ($this->isDate[$col] ?? false) {
                $r[] = 'date';
            } elseif ($this->isNumeric[$col] ?? false) {
                $r[] = 'numeric';
            } else {
                $r[] = 'string';
                if (isset($this->stringMaxLengths[$col]) && $this->stringMaxLengths[$col] > 0) {
                    $r[] = 'max:' . $this->stringMaxLengths[$col];
                } else {
                    $r[] = 'max:65535';
                }
            }

            // unique con "ignore" al propio ID
            if (in_array($col, $this->uniqueColumns, true)) {
                // unique:table,column,except,idColumn
                $r[] = 'unique:cargo_types,' . $col . ',' . $this->cargoTypeId . ',id';
            }

            $rules["form.$col"] = implode('|', $r);
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            '*.required' => 'Este campo es obligatorio.',
            '*.numeric'  => 'Debe ser un número válido.',
            '*.date'     => 'Debe ser una fecha válida.',
            '*.boolean'  => 'Valor inválido.',
            '*.json'     => 'Debe ser un JSON válido.',
            '*.in'       => 'Seleccione un valor válido.',
            '*.max'      => 'No puede superar :max caracteres.',
            '*.unique'   => 'Este valor ya está en uso.',
        ];
    }

    public function validationAttributes(): array
    {
        $labels = [];
        foreach ($this->columns as $col) {
            $labels["form.$col"] = \Illuminate\Support\Str::of($col)->replace('_',' ')->title();
        }
        return $labels;
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->validateOnly($property);
        }
    }

    public function save()
    {
        // Normalizar booleans
        foreach ($this->columns as $col) {
            if ($this->isBoolean[$col] ?? false) {
                $this->form[$col] = $this->truthy($this->form[$col]) ? 1 : 0;
            }
        }

        // Parsear JSON a array si corresponde
        foreach ($this->columns as $col) {
            if (($this->isJson[$col] ?? false) && is_string($this->form[$col]) && $this->form[$col] !== '') {
                $decoded = json_decode($this->form[$col], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->form[$col] = $decoded;
                }
            }
        }

        $this->validate();

        $model    = new CargoType();
        $fillable = $model->getFillable();
        $allowed  = $fillable ?: array_merge($this->columns, $this->systemColumns);

        // updated_by_user_id si existe (no visible)
        if (in_array('updated_by_user_id', $allowed, true)) {
            $this->form['updated_by_user_id'] = Auth::id();
        }

        // Limpiar null/'' para permitir defaults
        $data = Arr::only($this->form, $allowed);
        $data = collect($data)->reject(function ($v) {
            return $v === null || (is_string($v) && trim($v) === '');
        })->all();

        DB::transaction(function () use ($data) {
            $this->cargoType->update($data);
        });

        session()->flash('success', __('Tipo de carga actualizado correctamente.'));
        return redirect()->route('admin.cargo-types.index');
    }

    private function truthy($v): bool
    {
        return in_array($v, [true, 1, '1', 'on', 'yes'], true);
    }

    public function render()
    {
        return view('livewire.admin.cargo-types.edit-cargo-type', [
            'columns'         => $this->columns,
            'requiredColumns' => $this->requiredColumns,
            'isBoolean'       => $this->isBoolean,
            'isDate'          => $this->isDate,
            'isNumeric'       => $this->isNumeric,
            'isJson'          => $this->isJson,
            'isEnum'          => $this->isEnum,
            'enumOptions'     => $this->enumOptions,
            'cargoType'       => $this->cargoType,
        ]);
    }
}
