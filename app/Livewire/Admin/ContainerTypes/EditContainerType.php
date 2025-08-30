<?php

namespace App\Livewire\Admin\ContainerTypes;

use App\Models\ContainerType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EditContainerType extends Component
{
    public ContainerType $containerType;

    /**
     * Campos reales de la tabla, saneados con $fillable.
     */
    public array $columns = [];

    /**
     * Datos del formulario (clave = nombre de columna).
     */
    public array $form = [];

    protected array $systemColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'created_by_user_id', 'updated_by_user_id',
    ];

    public array $requiredColumns = [];
    public array $stringMaxLengths = [];
    public array $isBoolean = [];
    public array $isNumeric = [];
    public array $isDate = [];

    public function mount(ContainerType $containerType): void
    {
        $this->containerType = $containerType;
        
        $table = $containerType->getTable();

        // Columnas reales de la BD
        $columnsDb = Schema::getColumnListing($table);

        // Fillables del modelo
        $fillables = $containerType->getFillable();

        $all = $fillables ? array_values(array_intersect($fillables, $columnsDb)) : $columnsDb;

        // Quitamos campos de sistema del formulario visible
        $this->columns = array_values(array_diff($all, $this->systemColumns));

        // Inicializamos el form con los datos existentes
        foreach ($this->columns as $col) {
            $this->form[$col] = $containerType->getAttribute($col);
        }

        // Si existe updated_by_user_id, lo preparamos
        if (in_array('updated_by_user_id', $all, true)) {
            $this->form['updated_by_user_id'] = Auth::id();
        }

        // === Metadatos desde INFORMATION_SCHEMA (MySQL) ===
        $dbName = DB::getDatabaseName();
        $rows = DB::select(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$dbName, $table]
        );

        foreach ($rows as $r) {
            $name = $r->COLUMN_NAME;
            $isNotNullNoDefault = ($r->IS_NULLABLE === 'NO') && is_null($r->COLUMN_DEFAULT) && stripos((string)$r->EXTRA, 'auto_increment') === false;

            if (!in_array($name, $this->systemColumns, true)) {
                if ($isNotNullNoDefault) {
                    $this->requiredColumns[] = $name;
                }
                if (!is_null($r->CHARACTER_MAXIMUM_LENGTH)) {
                    $this->stringMaxLengths[$name] = (int) $r->CHARACTER_MAXIMUM_LENGTH;
                }
            }
        }

        // Heurísticas de tipos para la vista
        foreach ($this->columns as $col) {
            $name = (string) $col;

            $this->isBoolean[$col] = (bool) preg_match('/^(is_|has_)/i', $name) || in_array($name, ['active'], true);
            $this->isDate[$col] = (bool) preg_match('/_date$/i', $name);
            $this->isNumeric[$col] = !$this->isBoolean[$col] && !$this->isDate[$col] &&
                (bool) preg_match('/(kg|mm|m3|feet|width|height|length|volume|max|min|rate|cost|price|strength|power|temp|years|months|count|order|stack|carbon|footprint|interval)/i', $name);
        }
    }

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
                    $colRules[] = 'max:' . $this->stringMaxLengths[$col];
                } else {
                    $colRules[] = 'max:65535';
                }
            }

            // Validación única para código, excluyendo el registro actual
            if ($col === 'code') {
                $colRules[] = 'unique:container_types,code,' . $this->containerType->id;
            }

            // Validación única para ISO code, excluyendo el registro actual
            if ($col === 'iso_code') {
                $colRules[] = 'unique:container_types,iso_code,' . $this->containerType->id;
            }

            $rules["form.$col"] = implode('|', $colRules);
        }

        if (isset($this->form['updated_by_user_id'])) {
            $rules['form.updated_by_user_id'] = 'nullable|integer';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            '*.required' => 'Este campo es obligatorio.',
            '*.numeric' => 'Debe ser un número válido.',
            '*.date' => 'Debe ser una fecha válida.',
            '*.boolean' => 'Valor inválido.',
            '*.max' => 'No puede superar :max caracteres.',
            '*.unique' => 'Este valor ya existe en otro registro.',
        ];
    }

    public function validationAttributes(): array
    {
        $labels = [];
        foreach ($this->columns as $col) {
            $labels["form.$col"] = \Illuminate\Support\Str::of($col)->replace('_', ' ')->title();
        }
        if (isset($this->form['updated_by_user_id'])) {
            $labels['form.updated_by_user_id'] = 'Usuario que modifica';
        }
        return $labels;
    }

    public function save()
    {
        // Normalizar booleans
        foreach ($this->columns as $col) {
            if ($this->isBoolean[$col]) {
                $this->form[$col] = $this->truthy($this->form[$col]) ? 1 : 0;
            }
        }

        // Validar y si hay errores, hacer scroll
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        $fillable = $this->containerType->getFillable();
        $allowed = $fillable ?: array_merge($this->columns, $this->systemColumns);

        // Forzar updated_by_user_id si corresponde
        if (array_key_exists('updated_by_user_id', $this->form)) {
            $this->form['updated_by_user_id'] = Auth::id();
        }

        // Tomar solo claves permitidas
        $data = Arr::only($this->form, $allowed);

        // Limpieza: quitar null y '' para campos opcionales
        $cleanData = [];
        foreach ($data as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                // Si el campo es requerido, mantener el valor para que falle la validación
                if (in_array($key, $this->requiredColumns)) {
                    $cleanData[$key] = $value;
                }
                // Si no es requerido, omitir para que use el default de la BD
            } else {
                $cleanData[$key] = $value;
            }
        }

        DB::transaction(fn() => $this->containerType->update($cleanData));

        session()->flash('success', __('Tipo de contenedor actualizado correctamente.'));
        return redirect()->route('admin.container-types.index');
    }

    public function delete()
    {
        // Verificar que no esté en uso (opcional - agregar según tu lógica de negocio)
        // if ($this->containerType->containers()->exists()) {
        //     session()->flash('error', 'No se puede eliminar un tipo de contenedor que está en uso.');
        //     return;
        // }

        DB::transaction(fn() => $this->containerType->delete());

        session()->flash('success', __('Tipo de contenedor eliminado correctamente.'));
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

    public function updatedForm()
    {
        $this->dispatch('scroll-to-error');
    }

    public function render()
    {
        return view('livewire.admin.container-types.edit-container-type', [
            'columns' => $this->columns,
            'isBoolean' => $this->isBoolean,
            'isNumeric' => $this->isNumeric,
            'isDate' => $this->isDate,
            'requiredColumns' => $this->requiredColumns,
        ]);
    }
}