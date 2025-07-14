<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargoType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'description',
        'parent_id',
        'level',
        'full_path',
        'imdg_class',
        'hs_code_prefix',
        'unece_code',
        'cargo_nature',
        'packaging_type',
        'requires_refrigeration',
        'requires_special_handling',
        'is_dangerous_goods',
        'requires_permits',
        'is_perishable',
        'is_fragile',
        'requires_fumigation',
        'temperature_range',
        'humidity_requirements',
        'stacking_limitations',
        'can_be_mixed',
        'incompatible_with',
        'requires_certificate_origin',
        'requires_health_certificate',
        'requires_fumigation_certificate',
        'requires_insurance',
        'required_documents',
        'subject_to_inspection',
        'inspection_percentage',
        'customs_requirements',
        'prohibited_countries',
        'typical_density',
        'max_weight_per_container',
        'dimension_restrictions',
        'tariff_classification',
        'insurance_rate',
        'freight_rates',
        'webservice_code',
        'webservice_mapping',
        'allows_consolidation',
        'allows_deconsolidation',
        'allows_transshipment',
        'typical_loading_time',
        'active',
        'is_common',
        'display_order',
        'icon',
        'color_code',
        'seasonal_restrictions',
        'embargo_periods',
        'created_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'requires_refrigeration' => 'boolean',
        'requires_special_handling' => 'boolean',
        'is_dangerous_goods' => 'boolean',
        'requires_permits' => 'boolean',
        'is_perishable' => 'boolean',
        'is_fragile' => 'boolean',
        'requires_fumigation' => 'boolean',
        'can_be_mixed' => 'boolean',
        'requires_certificate_origin' => 'boolean',
        'requires_health_certificate' => 'boolean',
        'requires_fumigation_certificate' => 'boolean',
        'requires_insurance' => 'boolean',
        'subject_to_inspection' => 'boolean',
        'allows_consolidation' => 'boolean',
        'allows_deconsolidation' => 'boolean',
        'allows_transshipment' => 'boolean',
        'active' => 'boolean',
        'is_common' => 'boolean',
        'temperature_range' => 'array',
        'humidity_requirements' => 'array',
        'stacking_limitations' => 'array',
        'incompatible_with' => 'array',
        'required_documents' => 'array',
        'customs_requirements' => 'array',
        'prohibited_countries' => 'array',
        'dimension_restrictions' => 'array',
        'freight_rates' => 'array',
        'webservice_mapping' => 'array',
        'seasonal_restrictions' => 'array',
        'embargo_periods' => 'array',
        'typical_density' => 'decimal:3',
        'max_weight_per_container' => 'decimal:2',
        'insurance_rate' => 'decimal:4',
        'level' => 'integer',
        'inspection_percentage' => 'integer',
        'typical_loading_time' => 'integer',
        'display_order' => 'integer',
        'created_date' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Parent cargo type (for hierarchy)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'parent_id');
    }

    /**
     * Child cargo types (subcategories)
     */
    public function children(): HasMany
    {
        return $this->hasMany(CargoType::class, 'parent_id');
    }

    /**
     * All descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * User who created this record
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Note: Shipments/Cargo relationships will be added in future phases

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active cargo types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for common cargo types
     */
    public function scopeCommon($query)
    {
        return $query->where('is_common', true);
    }

    /**
     * Scope for dangerous goods
     */
    public function scopeDangerous($query)
    {
        return $query->where('is_dangerous_goods', true);
    }

    /**
     * Scope for perishable goods
     */
    public function scopePerishable($query)
    {
        return $query->where('is_perishable', true);
    }

    /**
     * Scope for refrigerated cargo
     */
    public function scopeRefrigerated($query)
    {
        return $query->where('requires_refrigeration', true);
    }

    /**
     * Scope by packaging type
     */
    public function scopeByPackaging($query, string $packaging)
    {
        return $query->where('packaging_type', $packaging);
    }

    /**
     * Scope by cargo nature
     */
    public function scopeByNature($query, string $nature)
    {
        return $query->where('cargo_nature', $nature);
    }

    /**
     * Scope for containerized cargo
     */
    public function scopeContainerized($query)
    {
        return $query->where('packaging_type', 'containerized');
    }

    /**
     * Scope for bulk cargo
     */
    public function scopeBulk($query)
    {
        return $query->where('packaging_type', 'bulk');
    }

    /**
     * Scope by hierarchy level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for root level cargo types
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for cargo requiring permits
     */
    public function scopeRequiringPermits($query)
    {
        return $query->where('requires_permits', true);
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('level')->orderBy('display_order')->orderBy('name');
    }

    // ========================================
    // HIERARCHY METHODS
    // ========================================

    /**
     * Get full hierarchy path as string
     */
    public function getFullHierarchyPath(): string
    {
        if ($this->full_path) {
            return $this->full_path;
        }

        $path = [$this->name];
        $current = $this->parent;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get all ancestors
     */
    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Check if is root level
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Update full path for this node and descendants
     */
    public function updateFullPath(): void
    {
        $this->full_path = $this->getFullHierarchyPath();
        $this->save();

        // Update children paths
        foreach ($this->children as $child) {
            $child->updateFullPath();
        }
    }

    // ========================================
    // VALIDATION AND COMPATIBILITY METHODS
    // ========================================

    /**
     * Check if compatible with another cargo type
     */
    public function isCompatibleWith(CargoType $other): bool
    {
        // Check if explicitly incompatible
        if ($this->incompatible_with && in_array($other->code, $this->incompatible_with)) {
            return false;
        }

        if ($other->incompatible_with && in_array($this->code, $other->incompatible_with)) {
            return false;
        }

        // Dangerous goods compatibility rules
        if ($this->is_dangerous_goods && $other->is_dangerous_goods) {
            return $this->checkDangerousGoodsCompatibility($other);
        }

        // Temperature requirements compatibility
        if ($this->requires_refrigeration || $other->requires_refrigeration) {
            return $this->checkTemperatureCompatibility($other);
        }

        // Check if both allow mixing
        return $this->can_be_mixed && $other->can_be_mixed;
    }

    /**
     * Check dangerous goods compatibility (basic IMDG classes)
     */
    private function checkDangerousGoodsCompatibility(CargoType $other): bool
    {
        if (!$this->imdg_class || !$other->imdg_class) {
            return false; // Cannot determine compatibility
        }

        // Basic incompatible IMDG class combinations
        $incompatibleCombinations = [
            ['1', '5.2'], // Explosives with Organic Peroxides
            ['2.3', '8'], // Toxic Gases with Corrosives
            ['4.2', '5.1'], // Spontaneously Combustible with Oxidizers
        ];

        foreach ($incompatibleCombinations as $combo) {
            if (in_array($this->imdg_class, $combo) && in_array($other->imdg_class, $combo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check temperature compatibility
     */
    private function checkTemperatureCompatibility(CargoType $other): bool
    {
        if (!$this->temperature_range || !$other->temperature_range) {
            return true; // Cannot determine, assume compatible
        }

        $thisMin = $this->temperature_range['min'] ?? null;
        $thisMax = $this->temperature_range['max'] ?? null;
        $otherMin = $other->temperature_range['min'] ?? null;
        $otherMax = $other->temperature_range['max'] ?? null;

        if (!$thisMin || !$thisMax || !$otherMin || !$otherMax) {
            return true; // Incomplete data, assume compatible
        }

        // Check if temperature ranges overlap
        return !($thisMax < $otherMin || $otherMax < $thisMin);
    }

    // ========================================
    // REQUIREMENTS METHODS
    // ========================================

    /**
     * Get all required documents
     */
    public function getRequiredDocuments(): array
    {
        $documents = [];

        if ($this->requires_certificate_origin) $documents[] = 'certificate_of_origin';
        if ($this->requires_health_certificate) $documents[] = 'health_certificate';
        if ($this->requires_fumigation_certificate) $documents[] = 'fumigation_certificate';
        if ($this->requires_insurance) $documents[] = 'insurance_certificate';

        // Add custom required documents
        if ($this->required_documents) {
            $documents = array_merge($documents, $this->required_documents);
        }

        return array_unique($documents);
    }

    /**
     * Get all special handling requirements
     */
    public function getSpecialRequirements(): array
    {
        $requirements = [];

        if ($this->requires_refrigeration) $requirements[] = 'refrigeration';
        if ($this->requires_special_handling) $requirements[] = 'special_handling';
        if ($this->requires_permits) $requirements[] = 'permits';
        if ($this->requires_fumigation) $requirements[] = 'fumigation';
        if ($this->is_fragile) $requirements[] = 'fragile_handling';

        return $requirements;
    }

    /**
     * Check if prohibited in specific country
     */
    public function isProhibitedInCountry(string $countryCode): bool
    {
        return $this->prohibited_countries && in_array($countryCode, $this->prohibited_countries);
    }

    /**
     * Check if subject to seasonal restrictions
     */
    public function hasSeasonalRestrictions(): bool
    {
        return !empty($this->seasonal_restrictions);
    }

    // ========================================
    // OPERATIONAL METHODS
    // ========================================

    /**
     * Calculate container capacity for this cargo type
     */
    public function calculateContainerCapacity(float $containerVolume): ?float
    {
        if (!$this->typical_density) {
            return null;
        }

        $maxByWeight = $this->max_weight_per_container;
        $maxByVolume = $containerVolume * $this->typical_density;

        return $maxByWeight ? min($maxByWeight, $maxByVolume) : $maxByVolume;
    }

    /**
     * Get estimated loading time
     */
    public function getEstimatedLoadingTime(float $weight = null): int
    {
        if ($this->typical_loading_time) {
            return $this->typical_loading_time;
        }

        // Estimate based on cargo characteristics
        $baseTime = match($this->packaging_type) {
            'containerized' => 2, // hours
            'bulk' => 8,
            'break_bulk' => 12,
            'ro_ro' => 1,
            default => 6,
        };

        // Add time for special requirements
        if ($this->requires_special_handling) $baseTime *= 1.5;
        if ($this->is_dangerous_goods) $baseTime *= 1.3;
        if ($this->is_fragile) $baseTime *= 1.2;

        return (int) round($baseTime);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get display color for charts/UI
     */
    public function getDisplayColorAttribute(): string
    {
        if ($this->color_code) {
            return $this->color_code;
        }

        // Default colors based on type
        return match($this->packaging_type) {
            'containerized' => '#3B82F6', // Blue
            'bulk' => '#EF4444', // Red
            'break_bulk' => '#10B981', // Green
            'ro_ro' => '#F59E0B', // Orange
            default => '#6B7280', // Gray
        };
    }

    /**
     * Get icon for UI display
     */
    public function getIconAttribute(): string
    {
        if ($this->attributes['icon']) {
            return $this->attributes['icon'];
        }

        // Default icons based on type
        return match($this->packaging_type) {
            'containerized' => '📦',
            'bulk' => '🚛',
            'break_bulk' => '📋',
            'ro_ro' => '🚗',
            'neo_bulk' => '⚙️',
            default => '📄',
        };
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    /**
     * Get cargo types for select dropdown
     */
    public static function forSelect(?int $level = null): array
    {
        $query = static::active()->ordered();

        if ($level) {
            $query->byLevel($level);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * Get common cargo types
     */
    public static function commonTypes()
    {
        return static::active()->common()->ordered()->get();
    }

    /**
     * Get dangerous goods types
     */
    public static function dangerousGoods()
    {
        return static::active()->dangerous()->ordered()->get();
    }

    /**
     * Get root level cargo types
     */
    public static function rootTypes()
    {
        return static::active()->root()->ordered()->get();
    }

    /**
     * Get cargo types by packaging
     */
    public static function byPackaging(string $packaging)
    {
        return static::active()->byPackaging($packaging)->ordered()->get();
    }

    /**
     * Find compatible cargo types for a given type
     */
    public static function compatibleWith(CargoType $cargoType)
    {
        return static::active()
            ->where('id', '!=', $cargoType->id)
            ->get()
            ->filter(function ($type) use ($cargoType) {
                return $cargoType->isCompatibleWith($type);
            });
    }
}
