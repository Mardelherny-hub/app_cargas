<?php

namespace App\Livewire\Admin\PackagingTypes;

use App\Models\PackagingType;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class EditPackagingType extends Component
{
    public $packagingTypeId;
    public $packagingType;
    public $columns = [];
    
    // Propiedades del formulario - basadas en el modelo PackagingType
    public $code;
    public $name;
    public $short_name;
    public $description;
    public $unece_code;
    public $iso_code;
    public $imdg_code;
    public $category;
    public $material_type;
    public $length_mm;
    public $width_mm;
    public $height_mm;
    public $diameter_mm;
    public $volume_liters;
    public $volume_m3;
    public $empty_weight_kg;
    public $max_gross_weight_kg;
    public $max_net_weight_kg;
    public $weight_tolerance_percent;
    public $is_stackable = false;
    public $max_stack_height;
    public $stacking_weight_limit_kg;
    public $is_reusable = false;
    public $is_returnable = false;
    public $is_collapsible = false;
    public $requires_palletizing = false;
    public $requires_strapping = false;
    public $requires_wrapping = false;
    public $requires_special_handling = false;
    public $handling_equipment;
    public $is_weatherproof = false;
    public $is_moisture_resistant = false;
    public $temperature_range_min;
    public $temperature_range_max;
    public $is_food_grade = false;
    public $suitable_for_food = false;
    public $suitable_for_dangerous_goods = false;
    public $suitable_for_liquids = false;
    public $suitable_for_gases = false;
    public $suitable_for_solids = false;
    public $suitable_for_powders = false;
    public $suitable_for_chemicals = false;
    public $suitable_for_pharmaceuticals = false;
    public $suitable_for_electronics = false;
    public $suitable_for_textiles = false;
    public $suitable_for_automotive = false;
    public $closure_type;
    public $seal_type;
    public $valve_type;
    public $opening_mechanism;
    public $dispensing_method;
    public $barrier_properties;
    public $protection_level;
    public $certifications;
    public $regulatory_compliance;
    public $requires_labeling = false;
    public $allows_printing = false;
    public $requires_hazmat_marking = false;
    public $required_markings;
    public $prohibited_markings;
    public $argentina_ws_code;
    public $paraguay_ws_code;
    public $customs_code;
    public $senasa_code;
    public $webservice_mapping;
    public $industry_applications;
    public $commodity_compatibility;
    public $seasonal_considerations;
    public $requires_testing = false;
    public $testing_frequency_days;
    public $quality_standards;
    public $acceptable_defect_rate_percent;
    public $widely_available = false;
    public $typical_lead_time_days;
    public $preferred_suppliers;
    public $alternative_types;
    public $active = true;
    public $is_standard = true;
    public $is_common = false;
    public $is_specialized = false;
    public $is_deprecated = false;
    public $display_order = 999;
    public $icon;
    public $color_code;

    // Columnas del sistema que no se deben mostrar en el formulario
    protected $systemColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at', 
        'created_date', 'created_by_user_id'
    ];

    public function mount($packagingTypeId)
    {
        $this->packagingTypeId = $packagingTypeId;
        $this->packagingType = PackagingType::findOrFail($packagingTypeId);
        
        // Obtener columnas visibles
        $table = $this->packagingType->getTable();
        $dbCols = Schema::getColumnListing($table);
        $fills = $this->packagingType->getFillable();
        $all = $fills ? array_values(array_intersect($fills, $dbCols)) : $dbCols;
        $this->columns = array_values(array_diff($all, $this->systemColumns));
        
        // Cargar datos existentes en las propiedades
        $this->loadPackagingTypeData();
    }

    protected function loadPackagingTypeData()
    {
        foreach ($this->columns as $column) {
            if (property_exists($this, $column)) {
                $value = $this->packagingType->$column;
                
                // Manejar arrays y objetos JSON
                if (is_array($value) || is_object($value)) {
                    $this->$column = json_encode($value);
                } else {
                    $this->$column = $value;
                }
            }
        }
    }

    public function save()
    {
        // Validación básica
        $this->validate([
            'code' => 'required|string|max:50|unique:packaging_types,code,' . $this->packagingTypeId,
            'name' => 'required|string|max:200',
            'unece_code' => 'nullable|string|max:20|unique:packaging_types,unece_code,' . $this->packagingTypeId,
            'category' => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () {
                $data = [];
                
                // Preparar datos para actualización
                foreach ($this->columns as $column) {
                    if (property_exists($this, $column)) {
                        $value = $this->$column;
                        
                        // Limpiar valores nulos o vacíos para permitir defaults de BD
                        if ($value === null || (is_string($value) && trim($value) === '')) {
                            continue;
                        }
                        
                        // Manejar arrays/JSON
                        if (in_array($column, [
                            'handling_equipment', 'barrier_properties', 'certifications',
                            'regulatory_compliance', 'required_markings', 'prohibited_markings',
                            'webservice_mapping', 'industry_applications', 'commodity_compatibility',
                            'seasonal_considerations', 'quality_standards', 'preferred_suppliers',
                            'alternative_types'
                        ])) {
                            if (is_string($value)) {
                                // Intentar decodificar JSON
                                $decoded = json_decode($value, true);
                                $data[$column] = $decoded !== null ? $decoded : $value;
                            } else {
                                $data[$column] = $value;
                            }
                        } else {
                            $data[$column] = $value;
                        }
                    }
                }

                // Agregar campos de auditoría si existen
                if (in_array('updated_by_user_id', $this->packagingType->getFillable())) {
                    $data['updated_by_user_id'] = Auth::id();
                }

                $this->packagingType->update($data);
            });

            session()->flash('success', 'Tipo de packaging actualizado correctamente.');
            
            // Redireccionar al índice
            return redirect()->route('admin.packaging-types.index');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error al actualizar el tipo de packaging: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.packaging-types.edit-packaging-type');
    }
}