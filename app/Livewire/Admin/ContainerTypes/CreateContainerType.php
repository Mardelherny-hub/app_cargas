<?php

namespace App\Livewire\Admin\ContainerTypes;

use App\Http\Controllers\Controller;
use App\Models\ContainerType; // ajusta el namespace si difiere
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class CreateContainerType extends Component
{
    /**
     * Campos reales de la tabla, saneados con $fillable.
     * Se calculan en mount().
     * @var array<int, string>
     */
    public array $columns = [];

    /**
     * Datos del formulario (clave = nombre de columna).
     * @var array<string, mixed>
     */
    public array $form = [];

    protected array $systemColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'created_by_user_id', 'updated_by_user_id',
    ];

    public array $requiredColumns = [];          // columnas NOT NULL sin default
    public array $stringMaxLengths = [];         // COLUMN_NAME => max length
    /**
     * Para UX condicional en la vista (sin asumir columnas):
     * - detectamos booleanos por convención de nombre.
     * - detectamos "numéricos" por patrones comunes.
     * La vista usa estas ayudas para decidir inputs.
     */
    public array $isBoolean = [];
    public array $isNumeric = [];
    public array $isDate    = [];
    

    public function mount(): void
    {
        $model = new ContainerType();
        $table = $model->getTable();

        // Columnas reales de la BD
        $columnsDb = Schema::getColumnListing($table);

        // Fillables del modelo (pueden ser subset/superset)
        $fillables = $model->getFillable();

        $all = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;

          // 1) Quitamos campos de sistema del formulario visible
        $this->columns = array_values(array_diff($all, $this->systemColumns));

        // 2) Inicializamos el form sólo con columnas visibles
        foreach ($this->columns as $col) {
            $this->form[$col] = null;
        }

        // 3) Si existe created_by_user_id en la tabla/fillable, lo seteamos internamente
        if (in_array('created_by_user_id', $all, true)) {
            // No lo mostramos, pero lo guardamos al final; si querés también podés mantenerlo en $form:
            $this->form['created_by_user_id'] = Auth::id();
        }

        // === Metadatos desde INFORMATION_SCHEMA (MySQL) ===
        $dbName = \Illuminate\Support\Facades\DB::getDatabaseName();
        $rows = \Illuminate\Support\Facades\DB::select(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$dbName, $table]
        );

        foreach ($rows as $r) {
            $name  = $r->COLUMN_NAME;
            $isNotNullNoDefault = ($r->IS_NULLABLE === 'NO') && is_null($r->COLUMN_DEFAULT) && stripos((string)$r->EXTRA, 'auto_increment') === false;

            // Guardamos solo si no es columna de sistema
            if (!in_array($name, $this->systemColumns, true)) {
                if ($isNotNullNoDefault) {
                    $this->requiredColumns[] = $name;
                }
                if (!is_null($r->CHARACTER_MAXIMUM_LENGTH)) {
                    $this->stringMaxLengths[$name] = (int) $r->CHARACTER_MAXIMUM_LENGTH;
                }
            }
        }
        
        // DEBUG: Temporal para ver qué campos se detectan como obligatorios
        \Log::info('Campos obligatorios detectados:', $this->requiredColumns);
        

        // Heurísticas de tipos para la vista (no forzamos estructura)
        foreach ($this->columns as $col) {
            $name = (string) $col;

            $this->isBoolean[$col] = (bool) preg_match('/^(is_|has_)/i', $name) || in_array($name, ['active'], true);
            $this->isDate[$col]    = (bool) preg_match('/_date$/i', $name);

            // "numérico" por patrones de nombre habituales en este dominio
            $this->isNumeric[$col] = !$this->isBoolean[$col] && !$this->isDate[$col] &&
                (bool) preg_match('/(kg|mm|m3|feet|width|height|length|volume|max|min|rate|cost|price|strength|power|temp|years|months|count|order|stack|carbon|footprint|interval)/i', $name);
        }
    }

    /**
     * Reglas mínimas y genéricas basadas en heurísticas.
     * Evitamos asumir columnas específicas.
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->columns as $col) {
            $colRules = [];

            // required si la BD lo marca NOT NULL sin default
            if (in_array($col, $this->requiredColumns, true)) {
                $colRules[] = 'required';
            } else {
                $colRules[] = 'nullable';
            }

            if ($this->isBoolean[$col] ?? false) {
                $colRules[] = 'boolean';
            } elseif ($this->isDate[$col] ?? false) {
                $colRules[] = 'date';
            } elseif ($this->isNumeric[$col] ?? false) {
                $colRules[] = 'numeric';
            } else {
                $colRules[] = 'string';
                if (isset($this->stringMaxLengths[$col]) && $this->stringMaxLengths[$col] > 0) {
                    $colRules[] = 'max:'.$this->stringMaxLengths[$col];
                } else {
                    $colRules[] = 'max:65535';
                }
            }

            $rules["form.$col"] = implode('|', $colRules);
        }

        if (isset($this->form['created_by_user_id'])) {
            $rules['form.created_by_user_id'] = 'nullable|integer';
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
            '*.max'      => 'No puede superar :max caracteres.',
        ];
    }

    public function validationAttributes(): array
    {
        $labels = [];
        foreach ($this->columns as $col) {
            $labels["form.$col"] = \Illuminate\Support\Str::of($col)->replace('_',' ')->title();
        }
        if (isset($this->form['created_by_user_id'])) {
            $labels['form.created_by_user_id'] = 'Usuario creador';
        }
        return $labels;
    }


    public function save()
    {
        // normalizar booleans…
        foreach ($this->columns as $col) {
            if ($this->isBoolean[$col]) {
                $this->form[$col] = $this->truthy($this->form[$col]) ? 1 : 0;
            }
        }

        $this->validate();

        $model = new ContainerType();
        $fillable = $model->getFillable();
        $allowed = $fillable ?: array_merge($this->columns, $this->systemColumns);

        // Forzar created_by_user_id si corresponde
        if (array_key_exists('created_by_user_id', $this->form)) {
            $this->form['created_by_user_id'] = \Illuminate\Support\Facades\Auth::id();
        }

        // Tomar solo claves permitidas
        $data = \Illuminate\Support\Arr::only($this->form, $allowed);

        // *** Limpieza: quitar null y '' para que la BD aplique defaults cuando existan ***
        $data = collect($data)->reject(function ($v) {
            return $v === null || (is_string($v) && trim($v) === '');
        })->all();

        DB::transaction(fn() => ContainerType::create($data));

        session()->flash('success', __('Tipo de contenedor creado correctamente.'));
        return redirect()->route('admin.container-types.index');
    }

    private function truthy($value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes'], true);
    }

    public function updated($propertyName): void
    {
        if (str_starts_with($propertyName, 'form.')) {
            $this->validateOnly($propertyName);
        }
    }


    public function render()
    {
        return view('livewire.admin.container-types.create-container-type', [
            'columns'         => $this->columns,
            'isBoolean'       => $this->isBoolean,
            'isNumeric'       => $this->isNumeric,
            'isDate'          => $this->isDate,
            'requiredColumns' => $this->requiredColumns, // Pasar campos obligatorios a la vista
        ]);
    }

    // Método temporal para debug
    public function debug()
    {
        return view('test-required-fields', [
            'columns'         => $this->columns,
            'requiredColumns' => $this->requiredColumns,
        ]);
    }
}