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
 * Modelo para tipos de embalaje
 * Soporta estándares internacionales UN/ECE e IMDG
 * Compatible con webservices AR/PY
 * 
 * 100% coherente con migración create_packaging_types_table.php
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property string|null $description
 * @property string|null $unece_code
 * @property string|null $iso_code
 * @property string|null $imdg_code
 * @property string $category
 * @property string|null $material_type
 * @property float|null $length_mm
 * @property float|null $width_mm
 * @property float|null $height_mm
 * @property float|null $diameter_mm
 * @property float|null $volume_liters
 * @property float|null $volume_m3
 * @property float|null $empty_weight_kg
 * @property float|null $max_gross_weight_kg
 * @property float|null $max_net_weight_kg
 * @property float $weight_tolerance_percent
 * @property bool $is_stackable
 * @property int|null $max_stack_height
 * @property float|null $stacking_weight_limit_kg
 * @property bool $is_reusable
 * @property bool $is_returnable
 * @property bool $is_collapsible
 * @property bool $requires_palletizing
 * @property bool $requires_strapping
 * @property bool $requires_wrapping
 * @property bool $requires_special_handling
 * @property string $handling_equipment
 * @property bool $is_weatherproof
 * @property bool $is_moisture_resistant
 * @property bool $is_uv_resistant
 * @property float|null $min_temperature_celsius
 * @property float|null $max_temperature_celsius
 * @property bool $requires_ventilation
 * @property bool $requires_humidity_control
 * @property bool $suitable_for_food
 * @property bool $suitable_for_liquids
 * @property bool $suitable_for_powders
 * @property bool $suitable_for_chemicals
 * @property bool $suitable_for_dangerous_goods
 * @property bool $suitable_for_fragile_items
 * @property bool $suitable_for_heavy_items
 * @property bool $suitable_for_bulk_cargo
 * @property bool $provides_cushioning
 * @property bool $provides_impact_protection
 * @property bool $provides_theft_protection
 * @property bool $is_tamper_evident
 * @property bool $is_child_resistant
 * @property bool $is_hermetic
 * @property bool $is_recyclable
 * @property bool $is_biodegradable
 * @property bool $is_compostable
 * @property bool $contains_recycled_material
 * @property float|null $recycled_content_percent
 * @property string|null $disposal_instructions
 * @property float|null $unit_cost
 * @property float|null $cost_per_kg
 * @property float|null $cost_per_m3
 * @property bool $cost_varies_by_quantity
 * @property int $minimum_order_quantity
 * @property bool $fda_approved
 * @property bool $food_contact_safe
 * @property bool $pharmaceutical_grade
 * @property array|null $certifications
 * @property array|null $regulatory_compliance
 * @property bool $requires_labeling
 * @property bool $allows_printing
 * @property bool $requires_hazmat_marking
 * @property array|null $required_markings
 * @property array|null $prohibited_markings
 * @property string|null $argentina_ws_code
 * @property string|null $paraguay_ws_code
 * @property string|null $customs_code
 * @property string|null $senasa_code
 * @property array|null $webservice_mapping
 * @property array|null $industry_applications
 * @property array|null $commodity_compatibility
 * @property array|null $seasonal_considerations
 * @property bool $requires_testing
 * @property int|null $testing_frequency_days
 * @property array|null $quality_standards
 * @property float $acceptable_defect_rate_percent
 * @property bool $widely_available
 * @property int|null $typical_lead_time_days
 * @property array|null $preferred_suppliers
 * @property array|null $alternative_types
 * @property bool $active
 * @property bool $is_standard
 * @property bool $is_common
 * @property bool $is_specialized
 * @property bool $is_deprecated
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
class PackagingType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'packaging_types';

    /**
     * The attributes that are mass assignable.
     * 100% coherente con migración create_packaging_types_table.php
     */
    protected $fillable = [
        // Basic identification
        'code',
        'name',
        'short_name',
        'description',

        // International classification
        'unece_code',
        'iso_code',
        'imdg_code',

        // Packaging category
        'category',
        'material_type',

        // Physical specifications
        'length_mm',
        'width_mm',
        'height_mm',
        'diameter_mm',
        'volume_liters',
        'volume_m3',

        // Weight specifications
        'empty_weight_kg',
        'max_gross_weight_kg',
        'max_net_weight_kg',
        'weight_tolerance_percent',

        // Structural characteristics
        'is_stackable',
        'max_stack_height',
        'stacking_weight_limit_kg',
        'is_reusable',
        'is_returnable',
        'is_collapsible',

        // Handling characteristics
        'requires_palletizing',
        'requires_strapping',
        'requires_wrapping',
        'requires_special_handling',
        'handling_equipment',

        // Environmental characteristics
        'is_weatherproof',
        'is_moisture_resistant',
        'is_uv_resistant',
        'min_temperature_celsius',
        'max_temperature_celsius',
        'requires_ventilation',
        'requires_humidity_control',

        // Cargo compatibility
        'suitable_for_food',
        'suitable_for_liquids',
        'suitable_for_powders',
        'suitable_for_chemicals',
        'suitable_for_dangerous_goods',
        'suitable_for_fragile_items',
        'suitable_for_heavy_items',
        'suitable_for_bulk_cargo',

        // Protection characteristics
        'provides_cushioning',
        'provides_impact_protection',
        'provides_theft_protection',
        'is_tamper_evident',
        'is_child_resistant',
        'is_hermetic',

        // Sustainability and recycling
        'is_recyclable',
        'is_biodegradable',
        'is_compostable',
        'contains_recycled_material',
        'recycled_content_percent',
        'disposal_instructions',

        // Economic factors
        'unit_cost',
        'cost_per_kg',
        'cost_per_m3',
        'cost_varies_by_quantity',
        'minimum_order_quantity',

        // Regulatory and certification
        'fda_approved',
        'food_contact_safe',
        'pharmaceutical_grade',
        'certifications',
        'regulatory_compliance',

        // Labeling and marking
        'requires_labeling',
        'allows_printing',
        'requires_hazmat_marking',
        'required_markings',
        'prohibited_markings',

        // Webservice integration
        'argentina_ws_code',
        'paraguay_ws_code',
        'customs_code',
        'senasa_code',
        'webservice_mapping',

        // Industry-specific data
        'industry_applications',
        'commodity_compatibility',
        'seasonal_considerations',

        // Quality and testing
        'requires_testing',
        'testing_frequency_days',
        'quality_standards',
        'acceptable_defect_rate_percent',

        // Availability and supply chain
        'widely_available',
        'typical_lead_time_days',
        'preferred_suppliers',
        'alternative_types',

        // Status and display
        'active',
        'is_standard',
        'is_common',
        'is_specialized',
        'is_deprecated',
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
        // Decimals - Physical specifications
        'length_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
        'diameter_mm' => 'decimal:2',
        'volume_liters' => 'decimal:2',
        'volume_m3' => 'decimal:3',

        // Decimals - Weight specifications
        'empty_weight_kg' => 'decimal:2',
        'max_gross_weight_kg' => 'decimal:2',
        'max_net_weight_kg' => 'decimal:2',
        'weight_tolerance_percent' => 'decimal:2',
        'stacking_weight_limit_kg' => 'decimal:2',

        // Decimals - Temperature and other measurements
        'min_temperature_celsius' => 'decimal:2',
        'max_temperature_celsius' => 'decimal:2',
        'recycled_content_percent' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'cost_per_kg' => 'decimal:2',
        'cost_per_m3' => 'decimal:2',
        'acceptable_defect_rate_percent' => 'decimal:2',

        // Booleans - Structural characteristics
        'is_stackable' => 'boolean',
        'is_reusable' => 'boolean',
        'is_returnable' => 'boolean',
        'is_collapsible' => 'boolean',

        // Booleans - Handling characteristics
        'requires_palletizing' => 'boolean',
        'requires_strapping' => 'boolean',
        'requires_wrapping' => 'boolean',
        'requires_special_handling' => 'boolean',

        // Booleans - Environmental characteristics
        'is_weatherproof' => 'boolean',
        'is_moisture_resistant' => 'boolean',
        'is_uv_resistant' => 'boolean',
        'requires_ventilation' => 'boolean',
        'requires_humidity_control' => 'boolean',

        // Booleans - Cargo compatibility
        'suitable_for_food' => 'boolean',
        'suitable_for_liquids' => 'boolean',
        'suitable_for_powders' => 'boolean',
        'suitable_for_chemicals' => 'boolean',
        'suitable_for_dangerous_goods' => 'boolean',
        'suitable_for_fragile_items' => 'boolean',
        'suitable_for_heavy_items' => 'boolean',
        'suitable_for_bulk_cargo' => 'boolean',

        // Booleans - Protection characteristics
        'provides_cushioning' => 'boolean',
        'provides_impact_protection' => 'boolean',
        'provides_theft_protection' => 'boolean',
        'is_tamper_evident' => 'boolean',
        'is_child_resistant' => 'boolean',
        'is_hermetic' => 'boolean',

        // Booleans - Sustainability
        'is_recyclable' => 'boolean',
        'is_biodegradable' => 'boolean',
        'is_compostable' => 'boolean',
        'contains_recycled_material' => 'boolean',

        // Booleans - Economic factors
        'cost_varies_by_quantity' => 'boolean',

        // Booleans - Regulatory
        'fda_approved' => 'boolean',
        'food_contact_safe' => 'boolean',
        'pharmaceutical_grade' => 'boolean',

        // Booleans - Labeling
        'requires_labeling' => 'boolean',
        'allows_printing' => 'boolean',
        'requires_hazmat_marking' => 'boolean',

        // Booleans - Quality and testing
        'requires_testing' => 'boolean',

        // Booleans - Availability
        'widely_available' => 'boolean',

        // Booleans - Status
        'active' => 'boolean',
        'is_standard' => 'boolean',
        'is_common' => 'boolean',
        'is_specialized' => 'boolean',
        'is_deprecated' => 'boolean',

        // JSON fields
        'certifications' => 'array',
        'regulatory_compliance' => 'array',
        'required_markings' => 'array',
        'prohibited_markings' => 'array',
        'webservice_mapping' => 'array',
        'industry_applications' => 'array',
        'commodity_compatibility' => 'array',
        'seasonal_considerations' => 'array',
        'quality_standards' => 'array',
        'preferred_suppliers' => 'array',
        'alternative_types' => 'array',

        // Integers
        'max_stack_height' => 'integer',
        'minimum_order_quantity' => 'integer',
        'testing_frequency_days' => 'integer',
        'typical_lead_time_days' => 'integer',
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
     * Ítems de envío que usan este tipo de embalaje
     * Relación será agregada en fases futuras
     */
    // public function shipmentItems(): HasMany
    // {
    //     return $this->hasMany(ShipmentItem::class);
    // }

    /**
     * Bills of lading que usan este tipo como embalaje principal
     * Relación será agregada en fases futuras
     */
    // public function billsOfLading(): HasMany
    // {
    //     return $this->hasMany(BillOfLading::class, 'primary_packaging_type_id');
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
     * Scope para tipos no obsoletos
     */
    public function scopeNotDeprecated(Builder $query): Builder
    {
        return $query->where('is_deprecated', false);
    }

    /**
     * Scope por categoría
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope por material
     */
    public function scopeByMaterial(Builder $query, string $material): Builder
    {
        return $query->where('material_type', $material);
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
     * Scope para líquidos
     */
    public function scopeLiquids(Builder $query): Builder
    {
        return $query->where('suitable_for_liquids', true);
    }

    /**
     * Scope para artículos frágiles
     */
    public function scopeFragile(Builder $query): Builder
    {
        return $query->where('suitable_for_fragile_items', true);
    }

    /**
     * Scope para carga a granel
     */
    public function scopeBulkCargo(Builder $query): Builder
    {
        return $query->where('suitable_for_bulk_cargo', true);
    }

    /**
     * Scope para tipos reciclables
     */
    public function scopeRecyclable(Builder $query): Builder
    {
        return $query->where('is_recyclable', true);
    }

    /**
     * Scope para tipos biodegradables
     */
    public function scopeBiodegradable(Builder $query): Builder
    {
        return $query->where('is_biodegradable', true);
    }

    /**
     * Scope para tipos reutilizables
     */
    public function scopeReusable(Builder $query): Builder
    {
        return $query->where('is_reusable', true);
    }

    /**
     * Scope para tipos apilables
     */
    public function scopeStackable(Builder $query): Builder
    {
        return $query->where('is_stackable', true);
    }

    /**
     * Scope ordenado por display_order y nombre
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope por equipo de manejo requerido
     */
    public function scopeByHandlingEquipment(Builder $query, string $equipment): Builder
    {
        return $query->where('handling_equipment', $equipment);
    }

    /**
     * Scope para tipos que requieren manejo especial
     */
    public function scopeSpecialHandling(Builder $query): Builder
    {
        return $query->where('requires_special_handling', true);
    }

    // ========================================
    // MÉTODOS DE NEGOCIO
    // ========================================

    /**
     * Verificar si puede manejar cierto peso
     */
    public function canHandle(float $weight): bool
    {
        if (!$this->max_gross_weight_kg) {
            return true; // Sin límite específico
        }

        return $this->max_gross_weight_kg >= $weight;
    }

    /**
     * Verificar si es apto para un tipo de carga específico
     */
    public function isSuitableFor(string $cargoType): bool
    {
        return match(strtolower($cargoType)) {
            'food', 'alimentos' => $this->suitable_for_food,
            'dangerous', 'peligrosas' => $this->suitable_for_dangerous_goods,
            'liquid', 'liquidos' => $this->suitable_for_liquids,
            'powder', 'polvos' => $this->suitable_for_powders,
            'chemical', 'quimicos' => $this->suitable_for_chemicals,
            'fragile', 'fragiles' => $this->suitable_for_fragile_items,
            'heavy', 'pesados' => $this->suitable_for_heavy_items,
            'bulk', 'granel' => $this->suitable_for_bulk_cargo,
            default => true
        };
    }

    /**
     * Calcular volumen en m³ si no está definido directamente
     */
    public function calculateVolume(): ?float
    {
        if ($this->volume_m3) {
            return $this->volume_m3;
        }

        if ($this->length_mm && $this->width_mm && $this->height_mm) {
            // Convertir de mm³ a m³
            return ($this->length_mm * $this->width_mm * $this->height_mm) / 1000000000;
        }

        return null;
    }

    /**
     * Calcular volumen en litros si no está definido directamente
     */
    public function calculateVolumeInLiters(): ?float
    {
        if ($this->volume_liters) {
            return $this->volume_liters;
        }

        $volumeM3 = $this->calculateVolume();
        if ($volumeM3) {
            return $volumeM3 * 1000; // Convertir m³ a litros
        }

        return null;
    }

    /**
     * Obtener código para webservice por país
     */
    public function getWebserviceCode(string $country = 'AR'): ?string
    {
        return match(strtoupper($country)) {
            'AR', 'ARGENTINA' => $this->argentina_ws_code,
            'PY', 'PARAGUAY' => $this->paraguay_ws_code,
            default => $this->unece_code ?? $this->code
        };
    }

    /**
     * Verificar si requiere certificaciones especiales
     */
    public function requiresSpecialCertifications(): bool
    {
        return $this->fda_approved || 
               $this->pharmaceutical_grade || 
               $this->suitable_for_dangerous_goods ||
               !empty($this->certifications);
    }

    /**
     * Verificar si es ambientalmente sostenible
     */
    public function isEnvironmentallyFriendly(): bool
    {
        return $this->is_recyclable || 
               $this->is_biodegradable || 
               $this->is_compostable || 
               $this->contains_recycled_material;
    }

    /**
     * Verificar si requiere condiciones especiales de almacenamiento
     */
    public function requiresSpecialStorage(): bool
    {
        return $this->requires_ventilation ||
               $this->requires_humidity_control ||
               $this->min_temperature_celsius !== null ||
               $this->max_temperature_celsius !== null;
    }

    /**
     * Obtener rango de temperatura como string
     */
    public function getTemperatureRange(): ?string
    {
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
     * Verificar compatibilidad con otro tipo de embalaje para envío mixto
     */
    public function isCompatibleWith(PackagingType $other): bool
    {
        // Verificar incompatibilidades básicas
        if ($this->suitable_for_dangerous_goods && !$other->suitable_for_dangerous_goods) {
            return false;
        }

        if ($this->requires_special_handling !== $other->requires_special_handling) {
            return false;
        }

        // Verificar rangos de temperatura compatibles
        if ($this->min_temperature_celsius && $other->max_temperature_celsius) {
            if ($this->min_temperature_celsius > $other->max_temperature_celsius) {
                return false;
            }
        }

        return true;
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

        return $query->notDeprecated()
            ->ordered()
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
            ->notDeprecated()
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
            ->notDeprecated()
            ->ordered()
            ->get();
    }

    /**
     * Buscar por código UN/ECE
     */
    public static function findByUneceCode(string $code): ?static
    {
        return static::where('unece_code', $code)->first();
    }

    /**
     * Buscar por código de webservice
     */
    public static function findByWebserviceCode(string $code, string $country = 'AR'): ?static
    {
        $field = match(strtoupper($country)) {
            'AR' => 'argentina_ws_code',
            'PY' => 'paraguay_ws_code',
            default => 'unece_code'
        };

        return static::where($field, $code)->first();
    }

    /**
     * Obtener categorías disponibles
     */
    public static function getAvailableCategories(): array
    {
        return [
            'pallet' => 'Pallet/Tarima',
            'box' => 'Caja/Cartón',
            'bag' => 'Bolsa/Saco',
            'drum' => 'Tambor/Bidón',
            'container' => 'Contenedor pequeño',
            'bundle' => 'Fardo/Atado',
            'roll' => 'Rollo',
            'bale' => 'Fardo prensado',
            'crate' => 'Cajón/Jaula',
            'barrel' => 'Barril',
            'tank' => 'Tanque pequeño',
            'tray' => 'Bandeja',
            'bulk' => 'A granel',
            'specialized' => 'Especializado'
        ];
    }

    /**
     * Obtener tipos de material disponibles
     */
    public static function getAvailableMaterials(): array
    {
        return [
            'wood' => 'Madera',
            'cardboard' => 'Cartón',
            'plastic' => 'Plástico',
            'metal' => 'Metal',
            'fabric' => 'Tela/Tejido',
            'paper' => 'Papel',
            'composite' => 'Compuesto',
            'glass' => 'Vidrio',
            'rubber' => 'Caucho',
            'other' => 'Otro'
        ];
    }

    /**
     * Obtener equipos de manejo disponibles
     */
    public static function getAvailableHandlingEquipment(): array
    {
        return [
            'manual' => 'Manual',
            'forklift' => 'Montacargas',
            'crane' => 'Grúa',
            'conveyor' => 'Transportador',
            'specialized' => 'Especializado'
        ];
    }
}