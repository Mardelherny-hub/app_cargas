<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * Modelo para tipos de contenedor
 * Soporta contenedores estándar ISO y especializados
 * Compatible con webservices AR/PY
 * 
 * 100% coherente con migración create_container_types_table.php
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property string|null $description
 * @property string|null $iso_code
 * @property string|null $iso_size_type
 * @property string|null $iso_group
 * @property string $length_feet
 * @property string $width_feet
 * @property string $height_feet
 * @property float $length_mm
 * @property float $width_mm
 * @property float $height_mm
 * @property float $internal_length_mm
 * @property float $internal_width_mm
 * @property float $internal_height_mm
 * @property float $door_width_mm
 * @property float $door_height_mm
 * @property float $internal_volume_m3
 * @property float $external_volume_m3
 * @property string $category
 * @property float $tare_weight_kg
 * @property float $max_gross_weight_kg
 * @property float $max_payload_kg
 * @property int $stacking_strength
 * @property bool $is_refrigerated
 * @property float|null $min_temperature_celsius
 * @property float|null $max_temperature_celsius
 * @property bool $has_controlled_atmosphere
 * @property float|null $power_consumption_kw
 * @property string|null $refrigeration_system
 * @property bool $suitable_for_food
 * @property bool $suitable_for_dangerous_goods
 * @property bool $suitable_for_chemicals
 * @property bool $suitable_for_pharmaceuticals
 * @property bool $suitable_for_bulk_liquids
 * @property bool $suitable_for_automobiles
 * @property array|null $cargo_restrictions
 * @property array|null $handling_instructions
 * @property array|null $safety_requirements
 * @property bool $requires_special_permits
 * @property string|null $csc_plate_location
 * @property string|null $inspection_requirements
 * @property int|null $inspection_interval_months
 * @property bool $stackable
 * @property int|null $max_stack_height
 * @property bool $foldable
 * @property bool $has_side_doors
 * @property bool $has_top_openings
 * @property string|null $floor_type
 * @property string|null $wall_material
 * @property string|null $roof_type
 * @property array|null $special_features
 * @property float|null $daily_rental_rate
 * @property float|null $cleaning_cost
 * @property float|null $repair_cost_factor
 * @property bool $widely_available
 * @property int|null $typical_lifespan_years
 * @property array|null $depreciation_schedule
 * @property array|null $maintenance_schedule
 * @property array|null $operating_costs
 * @property string|null $argentina_ws_code
 * @property string|null $paraguay_ws_code
 * @property string|null $customs_code
 * @property array|null $webservice_mapping
 * @property array|null $port_compatibility
 * @property array|null $vessel_compatibility
 * @property array|null $terminal_requirements
 * @property array|null $regulatory_compliance
 * @property array|null $environmental_standards
 * @property string|null $manufacturer_standards
 * @property array|null $quality_certifications
 * @property array|null $condition_descriptions
 * @property array|null $compatible_vessel_types
 * @property array|null $restricted_ports
 * @property array|null $handling_equipment_required
 * @property bool $eco_friendly
 * @property float|null $carbon_footprint_kg
 * @property array|null $environmental_certifications
 * @property bool $active
 * @property bool $is_standard
 * @property bool $is_common
 * @property bool $is_specialized
 * @property int $display_order
 * @property string|null $icon
 * @property string|null $color_code
 * @property Carbon $created_date
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * // Relationships
 * @property User|null $createdByUser
 */
class ContainerType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'container_types';

    /**
     * The attributes that are mass assignable.
     * 100% coherente con migración create_container_types_table.php
     */
    protected $fillable = [
        // Basic identification
        'code',
        'name',
        'short_name',
        'description',

        // ISO classification
        'iso_code',
        'iso_size_type',
        'iso_group',

        // Physical dimensions
        'length_feet',
        'width_feet',
        'height_feet',
        'length_mm',
        'width_mm',
        'height_mm',
        'internal_length_mm',
        'internal_width_mm',
        'internal_height_mm',
        'door_width_mm',
        'door_height_mm',
        'internal_volume_m3',
        'external_volume_m3',

        // Container category and specifications
        'category',
        'tare_weight_kg',
        'max_gross_weight_kg',
        'max_payload_kg',
        'stacking_strength',

        // Refrigeration capabilities
        'is_refrigerated',
        'min_temperature_celsius',
        'max_temperature_celsius',
        'has_controlled_atmosphere',
        'power_consumption_kw',
        'refrigeration_system',

        // Cargo suitability
        'suitable_for_food',
        'suitable_for_dangerous_goods',
        'suitable_for_chemicals',
        'suitable_for_pharmaceuticals',
        'suitable_for_bulk_liquids',
        'suitable_for_automobiles',
        'cargo_restrictions',
        'handling_instructions',
        'safety_requirements',
        'requires_special_permits',

        // Structural and design features
        'csc_plate_location',
        'inspection_requirements',
        'inspection_interval_months',
        'stackable',
        'max_stack_height',
        'foldable',
        'has_side_doors',
        'has_top_openings',
        'floor_type',
        'wall_material',
        'roof_type',
        'special_features',

        // Economic factors
        'daily_rental_rate',
        'cleaning_cost',
        'repair_cost_factor',
        'widely_available',
        'typical_lifespan_years',
        'depreciation_schedule',
        'maintenance_schedule',
        'operating_costs',

        // Webservice integration
        'argentina_ws_code',
        'paraguay_ws_code',
        'customs_code',
        'webservice_mapping',

        // Operational considerations
        'port_compatibility',
        'vessel_compatibility',
        'terminal_requirements',

        // Regulatory and standards
        'regulatory_compliance',
        'environmental_standards',
        'manufacturer_standards',
        'quality_certifications',
        'condition_descriptions',

        // Port and vessel compatibility
        'compatible_vessel_types',
        'restricted_ports',
        'handling_equipment_required',

        // Environmental considerations
        'eco_friendly',
        'carbon_footprint_kg',
        'environmental_certifications',

        // Status and display
        'active',
        'is_standard',
        'is_common',
        'is_specialized',
        'display_order',
        'icon',
        'color_code',

        // Audit trail
        'created_date',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     * Coherente con tipos de datos de la migración
     */
    protected $casts = [
        // Decimals - Physical dimensions
        'length_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'internal_length_mm' => 'decimal:2',
        'internal_width_mm' => 'decimal:2',
        'internal_height_mm' => 'decimal:2',
        'door_width_mm' => 'decimal:2',
        'door_height_mm' => 'decimal:2',
        'internal_volume_m3' => 'decimal:3',
        'external_volume_m3' => 'decimal:3',

        // Decimals - Weight and capacity
        'tare_weight_kg' => 'decimal:2',
        'max_gross_weight_kg' => 'decimal:2',
        'max_payload_kg' => 'decimal:2',

        // Decimals - Temperature and power
        'min_temperature_celsius' => 'decimal:2',
        'max_temperature_celsius' => 'decimal:2',
        'power_consumption_kw' => 'decimal:2',

        // Decimals - Economic
        'daily_rental_rate' => 'decimal:2',
        'cleaning_cost' => 'decimal:2',
        'repair_cost_factor' => 'decimal:2',
        'carbon_footprint_kg' => 'decimal:2',

        // Booleans - Refrigeration
        'is_refrigerated' => 'boolean',
        'has_controlled_atmosphere' => 'boolean',

        // Booleans - Cargo suitability
        'suitable_for_food' => 'boolean',
        'suitable_for_dangerous_goods' => 'boolean',
        'suitable_for_chemicals' => 'boolean',
        'suitable_for_pharmaceuticals' => 'boolean',
        'suitable_for_bulk_liquids' => 'boolean',
        'suitable_for_automobiles' => 'boolean',
        'requires_special_permits' => 'boolean',

        // Booleans - Structural features
        'stackable' => 'boolean',
        'foldable' => 'boolean',
        'has_side_doors' => 'boolean',
        'has_top_openings' => 'boolean',

        // Booleans - Economic and availability
        'widely_available' => 'boolean',

        // Booleans - Environmental
        'eco_friendly' => 'boolean',

        // Booleans - Status
        'active' => 'boolean',
        'is_standard' => 'boolean',
        'is_common' => 'boolean',
        'is_specialized' => 'boolean',

        // JSON fields
        'cargo_restrictions' => 'array',
        'handling_instructions' => 'array',
        'safety_requirements' => 'array',
        'special_features' => 'array',
        'depreciation_schedule' => 'array',
        'maintenance_schedule' => 'array',
        'operating_costs' => 'array',
        'webservice_mapping' => 'array',
        'port_compatibility' => 'array',
        'vessel_compatibility' => 'array',
        'terminal_requirements' => 'array',
        'regulatory_compliance' => 'array',
        'environmental_standards' => 'array',
        'quality_certifications' => 'array',
        'condition_descriptions' => 'array',
        'compatible_vessel_types' => 'array',
        'restricted_ports' => 'array',
        'handling_equipment_required' => 'array',
        'environmental_certifications' => 'array',

        // Integers
        'stacking_strength' => 'integer',
        'inspection_interval_months' => 'integer',
        'max_stack_height' => 'integer',
        'typical_lifespan_years' => 'integer',
        'display_order' => 'integer',

        // Timestamps
        'created_date' => 'datetime',
    ];

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Usuario que creó el registro
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Contenedores de este tipo
     * Relación será agregada cuando se confirme modelo Container
     */
    // public function containers(): HasMany
    // {
    //     return $this->hasMany(Container::class);
    // }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para tipos activos
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope para tipos comunes
     */
    public function scopeCommon(Builder $query): Builder
    {
        return $query->where('is_common', true);
    }

    /**
     * Scope para tipos estándar
     */
    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('is_standard', true);
    }

    /**
     * Scope para tipos especializados
     */
    public function scopeSpecialized(Builder $query): Builder
    {
        return $query->where('is_specialized', true);
    }

    /**
     * Scope para tipos refrigerados
     */
    public function scopeRefrigerated(Builder $query): Builder
    {
        return $query->where('is_refrigerated', true);
    }

    /**
     * Scope por longitud en pies
     */
    public function scopeByLength(Builder $query, string $length): Builder
    {
        return $query->where('length_feet', $length);
    }

    /**
     * Scope por categoría
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para tipos aptos para alimentos
     */
    public function scopeFoodSafe(Builder $query): Builder
    {
        return $query->where('suitable_for_food', true);
    }

    /**
     * Scope para mercancías peligrosas
     */
    public function scopeDangerousGoods(Builder $query): Builder
    {
        return $query->where('suitable_for_dangerous_goods', true);
    }

    /**
     * Scope ordenado por display_order y nombre
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // ========================================
    // MÉTODOS DE NEGOCIO
    // ========================================

    /**
     * Verificar si puede manejar cierto peso
     */
    public function canHandle(float $weight): bool
    {
        return $this->max_payload_kg >= $weight;
    }

    /**
     * Verificar si es apto para un tipo de carga específico
     */
    public function isSuitableFor(string $cargoType): bool
    {
        return match(strtolower($cargoType)) {
            'food', 'alimentos' => $this->suitable_for_food,
            'dangerous', 'peligrosas' => $this->suitable_for_dangerous_goods,
            'chemical', 'quimicos' => $this->suitable_for_chemicals,
            'pharmaceutical', 'farmaceuticos' => $this->suitable_for_pharmaceuticals,
            'liquid', 'liquidos' => $this->suitable_for_bulk_liquids,
            'automobile', 'vehiculos' => $this->suitable_for_automobiles,
            default => true
        };
    }

    /**
     * Calcular volumen interno en m³
     */
    public function calculateInternalVolume(): float
    {
        if ($this->internal_volume_m3) {
            return $this->internal_volume_m3;
        }

        // Convertir de mm³ a m³
        return ($this->internal_length_mm * $this->internal_width_mm * $this->internal_height_mm) / 1000000000;
    }

    /**
     * Obtener código para webservice por país
     */
    public function getWebserviceCode(string $country = 'AR'): ?string
    {
        return match(strtoupper($country)) {
            'AR', 'ARGENTINA' => $this->argentina_ws_code,
            'PY', 'PARAGUAY' => $this->paraguay_ws_code,
            default => $this->iso_code ?? $this->code
        };
    }

    /**
     * Verificar si requiere refrigeración
     */
    public function requiresRefrigeration(): bool
    {
        return $this->is_refrigerated;
    }

    /**
     * Obtener rango de temperatura como string
     */
    public function getTemperatureRange(): ?string
    {
        if (!$this->is_refrigerated) {
            return null;
        }

        if ($this->min_temperature_celsius !== null && $this->max_temperature_celsius !== null) {
            return "{$this->min_temperature_celsius}°C a {$this->max_temperature_celsius}°C";
        } elseif ($this->min_temperature_celsius !== null) {
            return "Mín: {$this->min_temperature_celsius}°C";
        } elseif ($this->max_temperature_celsius !== null) {
            return "Máx: {$this->max_temperature_celsius}°C";
        }

        return null;
    }

    /**
     * Verificar si es apilable
     */
    public function isStackable(): bool
    {
        return $this->stackable;
    }

    /**
     * Obtener descripción completa del contenedor
     */
    public function getFullDescription(): string
    {
        $parts = [
            $this->length_feet . "'",
            $this->category
        ];

        if ($this->is_refrigerated) {
            $parts[] = 'Refrigerado';
        }

        if ($this->has_side_doors) {
            $parts[] = 'Puertas Laterales';
        }

        if ($this->has_top_openings) {
            $parts[] = 'Techo Abierto';
        }

        return implode(' - ', $parts);
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    /**
     * Obtener tipos para dropdown
     */
    public static function getDropdownOptions(bool $onlyActive = true): array
    {
        $query = static::query();
        
        if ($onlyActive) {
            $query->active();
        }

        return $query->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Obtener tipos comunes para selección rápida
     */
    public static function getCommonTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->common()
            ->ordered()
            ->get();
    }

    /**
     * Obtener tipos estándar
     */
    public static function getStandardTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->standard()
            ->ordered()
            ->get();
    }

    /**
     * Buscar por código ISO
     */
    public static function findByIsoCode(string $code): ?static
    {
        return static::where('iso_code', $code)->first();
    }

    /**
     * Buscar por código de webservice
     */
    public static function findByWebserviceCode(string $code, string $country = 'AR'): ?static
    {
        $field = match(strtoupper($country)) {
            'AR' => 'argentina_ws_code',
            'PY' => 'paraguay_ws_code',
            default => 'iso_code'
        };

        return static::where($field, $code)->first();
    }

    /**
     * Obtener categorías disponibles
     */
    public static function getAvailableCategories(): array
    {
        return [
            'standard' => 'Estándar',
            'high_cube' => 'High Cube',
            'refrigerated' => 'Refrigerado',
            'open_top' => 'Techo Abierto',
            'flat_rack' => 'Plataforma',
            'tank' => 'Tanque',
            'bulk' => 'Granelero',
            'car_carrier' => 'Portavehículos',
            'specialized' => 'Especializado'
        ];
    }

    /**
     * Obtener tamaños disponibles en pies
     */
    public static function getAvailableLengths(): array
    {
        return [
            '10' => "10'",
            '20' => "20'",
            '40' => "40'",
            '45' => "45'",
            '48' => "48'",
            '53' => "53'"
        ];
    }
}