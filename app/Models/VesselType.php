<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * MÓDULO 3: VIAJES Y CARGAS - VESSEL TYPES
 *
 * Modelo VesselType para tipos de embarcación
 * Soporta transporte fluvial y marítimo con diferentes categorías
 *
 * @property int $id
 * @property string $code Código único del tipo de embarcación
 * @property string $name Nombre del tipo de embarcación
 * @property string|null $short_name Nombre corto para UI
 * @property string|null $description Descripción detallada del tipo
 * @property string $category Categoría principal (barge, tugboat, self_propelled, pusher, mixed)
 * @property string $propulsion_type Tipo de propulsión
 * @property float|null $min_length Longitud mínima en metros
 * @property float|null $max_length Longitud máxima en metros
 * @property float|null $min_beam Manga mínima en metros
 * @property float|null $max_beam Manga máxima en metros
 * @property float|null $min_draft Calado mínimo en metros
 * @property float|null $max_draft Calado máximo en metros
 * @property float|null $min_deadweight Peso muerto mínimo en toneladas
 * @property float|null $max_deadweight Peso muerto máximo en toneladas
 * @property bool $handles_containers Maneja contenedores
 * @property bool $handles_bulk_cargo Maneja carga a granel
 * @property bool $handles_liquid_cargo Maneja carga líquida
 * @property bool $handles_general_cargo Maneja carga general
 * @property bool $handles_dangerous_goods Maneja mercancías peligrosas
 * @property bool $handles_passengers Transporta pasajeros
 * @property bool $river_navigation Navegación fluvial
 * @property bool $maritime_navigation Navegación marítima
 * @property bool $can_be_lead_vessel Puede ser embarcación principal
 * @property bool $can_be_in_convoy Puede formar parte de convoy
 * @property int|null $max_convoy_size Tamaño máximo de convoy
 * @property int|null $crew_capacity Capacidad de tripulación
 * @property int|null $passenger_capacity Capacidad de pasajeros
 * @property float|null $fuel_capacity_liters Capacidad de combustible
 * @property string|null $engine_configuration Configuración del motor
 * @property float|null $max_speed_knots Velocidad máxima en nudos
 * @property float|null $service_speed_knots Velocidad de servicio
 * @property string|null $construction_materials Materiales de construcción
 * @property int|null $typical_lifespan_years Vida útil típica
 * @property string|null $environmental_standards Estándares ambientales
 * @property string|null $regulatory_requirements Requisitos regulatorios
 * @property int|null $maintenance_interval_months Intervalo de mantenimiento
 * @property bool $requires_dry_dock Requiere dique seco
 * @property int|null $dry_dock_interval_months Intervalo dique seco
 * @property bool $active Tipo activo
 * @property bool $is_common Tipo común/frecuente
 * @property bool $is_specialized Tipo especializado
 * @property int $display_order Orden de visualización
 * @property string|null $icon Icono para UI
 * @property string|null $color_code Color para gráficos
 * @property Carbon $created_date Fecha de creación
 * @property int|null $created_by_user_id Usuario que creó el registro
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VesselType extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'vessel_types';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'code',
        'name',
        'short_name',
        'description',
        'category',
        'propulsion_type',
        'min_length',
        'max_length',
        'min_beam',
        'max_beam',
        'min_draft',
        'max_draft',
        'min_deadweight',
        'max_deadweight',
        'handles_containers',
        'handles_bulk_cargo',
        'handles_liquid_cargo',
        'handles_general_cargo',
        'handles_dangerous_goods',
        'handles_passengers',
        'river_navigation',
        'maritime_navigation',
        'can_be_lead_vessel',
        'can_be_in_convoy',
        'max_convoy_size',
        'crew_capacity',
        'passenger_capacity',
        'fuel_capacity_liters',
        'engine_configuration',
        'max_speed_knots',
        'service_speed_knots',
        'construction_materials',
        'typical_lifespan_years',
        'environmental_standards',
        'regulatory_requirements',
        'maintenance_interval_months',
        'requires_dry_dock',
        'dry_dock_interval_months',
        'active',
        'is_common',
        'is_specialized',
        'display_order',
        'icon',
        'color_code',
        'created_date',
        'created_by_user_id',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'min_length' => 'decimal:2',
        'max_length' => 'decimal:2',
        'min_beam' => 'decimal:2',
        'max_beam' => 'decimal:2',
        'min_draft' => 'decimal:2',
        'max_draft' => 'decimal:2',
        'min_deadweight' => 'decimal:2',
        'max_deadweight' => 'decimal:2',
        'fuel_capacity_liters' => 'decimal:2',
        'max_speed_knots' => 'decimal:2',
        'service_speed_knots' => 'decimal:2',
        'handles_containers' => 'boolean',
        'handles_bulk_cargo' => 'boolean',
        'handles_liquid_cargo' => 'boolean',
        'handles_general_cargo' => 'boolean',
        'handles_dangerous_goods' => 'boolean',
        'handles_passengers' => 'boolean',
        'river_navigation' => 'boolean',
        'maritime_navigation' => 'boolean',
        'can_be_lead_vessel' => 'boolean',
        'can_be_in_convoy' => 'boolean',
        'requires_dry_dock' => 'boolean',
        'active' => 'boolean',
        'is_common' => 'boolean',
        'is_specialized' => 'boolean',
        'created_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Categorías principales de embarcación.
     */
    public const CATEGORIES = [
        'barge' => 'Barcaza',
        'tugboat' => 'Remolcador',
        'self_propelled' => 'Autopropulsado',
        'pusher' => 'Empujador',
        'mixed' => 'Mixto',
    ];

    /**
     * Tipos de propulsión disponibles.
     */
    public const PROPULSION_TYPES = [
        'self_propelled' => 'Autopropulsado',
        'towed' => 'Remolcado',
        'pushed' => 'Empujado',
        'hybrid' => 'Híbrido',
    ];

    /**
     * Tipos de carga que puede manejar.
     */
    public const CARGO_CAPABILITIES = [
        'containers' => 'Contenedores',
        'bulk_cargo' => 'Carga a Granel',
        'liquid_cargo' => 'Carga Líquida',
        'general_cargo' => 'Carga General',
        'dangerous_goods' => 'Mercancías Peligrosas',
        'passengers' => 'Pasajeros',
    ];

    /**
     * Tipos de navegación.
     */
    public const NAVIGATION_TYPES = [
        'river' => 'Navegación Fluvial',
        'maritime' => 'Navegación Marítima',
        'both' => 'Ambas',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Usuario que creó el registro.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Embarcaciones que son de este tipo.
     */
    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'vessel_type_id');
    }

    // =====================================================
    // SCOPES Y CONSULTAS
    // =====================================================

    /**
     * Scope para tipos activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope para tipos comunes/frecuentes.
     */
    public function scopeCommon(Builder $query): Builder
    {
        return $query->where('is_common', true);
    }

    /**
     * Scope para tipos especializados.
     */
    public function scopeSpecialized(Builder $query): Builder
    {
        return $query->where('is_specialized', true);
    }

    /**
     * Scope para filtrar por categoría.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para tipos que manejan contenedores.
     */
    public function scopeHandlesContainers(Builder $query): Builder
    {
        return $query->where('handles_containers', true);
    }

    /**
     * Scope para tipos que manejan carga a granel.
     */
    public function scopeHandlesBulkCargo(Builder $query): Builder
    {
        return $query->where('handles_bulk_cargo', true);
    }

    /**
     * Scope para tipos que pueden liderar convoy.
     */
    public function scopeCanLead(Builder $query): Builder
    {
        return $query->where('can_be_lead_vessel', true);
    }

    /**
     * Scope para tipos que pueden estar en convoy.
     */
    public function scopeCanBeInConvoy(Builder $query): Builder
    {
        return $query->where('can_be_in_convoy', true);
    }

    /**
     * Scope para navegación fluvial.
     */
    public function scopeRiverNavigation(Builder $query): Builder
    {
        return $query->where('river_navigation', true);
    }

    /**
     * Scope para navegación marítima.
     */
    public function scopeMaritimeNavigation(Builder $query): Builder
    {
        return $query->where('maritime_navigation', true);
    }

    /**
     * Scope con ordenamiento por display_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // =====================================================
    // MÉTODOS AUXILIARES
    // =====================================================

    /**
     * Obtener el nombre de la categoría.
     */
    public function getCategoryNameAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Obtener el nombre del tipo de propulsión.
     */
    public function getPropulsionTypeNameAttribute(): string
    {
        return self::PROPULSION_TYPES[$this->propulsion_type] ?? $this->propulsion_type;
    }

    /**
     * Verificar si puede manejar un tipo de carga específico.
     */
    public function canHandle(string $cargoType): bool
    {
        $field = "handles_{$cargoType}";
        return $this->$field ?? false;
    }

    /**
     * Obtener capacidades de carga disponibles.
     */
    public function getCargoCapabilitiesAttribute(): array
    {
        $capabilities = [];
        
        foreach (self::CARGO_CAPABILITIES as $key => $label) {
            $field = "handles_" . str_replace('_cargo', '_cargo', $key);
            if ($key === 'containers') $field = 'handles_containers';
            if ($key === 'passengers') $field = 'handles_passengers';
            if ($key === 'dangerous_goods') $field = 'handles_dangerous_goods';
            
            if ($this->$field ?? false) {
                $capabilities[$key] = $label;
            }
        }
        
        return $capabilities;
    }

    /**
     * Obtener tipos de navegación disponibles.
     */
    public function getNavigationCapabilitiesAttribute(): array
    {
        $capabilities = [];
        
        if ($this->river_navigation) {
            $capabilities[] = 'river';
        }
        
        if ($this->maritime_navigation) {
            $capabilities[] = 'maritime';
        }
        
        return $capabilities;
    }

    /**
     * Verificar si el tipo está activo y disponible.
     */
    public function isAvailable(): bool
    {
        return $this->active;
    }

    /**
     * Obtener rango de longitud como string.
     */
    public function getLengthRangeAttribute(): ?string
    {
        if (!$this->min_length && !$this->max_length) {
            return null;
        }
        
        if ($this->min_length && $this->max_length) {
            return "{$this->min_length}m - {$this->max_length}m";
        }
        
        if ($this->min_length) {
            return "≥ {$this->min_length}m";
        }
        
        return "≤ {$this->max_length}m";
    }

    /**
     * Obtener rango de manga como string.
     */
    public function getBeamRangeAttribute(): ?string
    {
        if (!$this->min_beam && !$this->max_beam) {
            return null;
        }
        
        if ($this->min_beam && $this->max_beam) {
            return "{$this->min_beam}m - {$this->max_beam}m";
        }
        
        if ($this->min_beam) {
            return "≥ {$this->min_beam}m";
        }
        
        return "≤ {$this->max_beam}m";
    }

    /**
     * Obtener el color asignado o uno por defecto según categoría.
     */
    public function getDisplayColorAttribute(): string
    {
        if ($this->color_code) {
            return $this->color_code;
        }
        
        // Colores por defecto según categoría
        $defaultColors = [
            'barge' => '#3B82F6',      // Azul
            'tugboat' => '#EF4444',    // Rojo
            'self_propelled' => '#10B981', // Verde
            'pusher' => '#F59E0B',     // Amarillo
            'mixed' => '#8B5CF6',      // Púrpura
        ];
        
        return $defaultColors[$this->category] ?? '#6B7280'; // Gris por defecto
    }

    /**
     * Verificar si requiere mantenimiento especializado.
     */
    public function requiresSpecializedMaintenance(): bool
    {
        return $this->requires_dry_dock || 
               $this->handles_dangerous_goods || 
               $this->handles_liquid_cargo;
    }

    /**
     * Obtener el próximo mantenimiento sugerido.
     */
    public function getMaintenanceIntervalText(): ?string
    {
        if (!$this->maintenance_interval_months) {
            return null;
        }
        
        $interval = $this->maintenance_interval_months;
        
        if ($interval >= 12) {
            $years = round($interval / 12, 1);
            return $years == 1 ? "1 año" : "{$years} años";
        }
        
        return "{$interval} meses";
    }

    // =====================================================
    // MÉTODOS ESTÁTICOS
    // =====================================================

    /**
     * Obtener tipos disponibles para un propósito específico.
     */
    public static function getAvailableForPurpose(string $purpose): Builder
    {
        $query = static::active()->ordered();
        
        switch ($purpose) {
            case 'containers':
                return $query->handlesContainers();
            case 'bulk':
                return $query->handlesBulkCargo();
            case 'convoy_lead':
                return $query->canLead();
            case 'river':
                return $query->riverNavigation();
            case 'maritime':
                return $query->maritimeNavigation();
            default:
                return $query;
        }
    }

    /**
     * Obtener tipos comunes para selects.
     */
    public static function getCommonTypes(): array
    {
        return static::active()
            ->common()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Obtener estadísticas de tipos de embarcación.
     */
    public static function getStatistics(): array
    {
        return [
            'total' => static::count(),
            'active' => static::active()->count(),
            'by_category' => static::active()
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'common' => static::active()->common()->count(),
            'specialized' => static::active()->specialized()->count(),
        ];
    }
}