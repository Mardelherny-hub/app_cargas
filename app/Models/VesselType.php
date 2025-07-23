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
 * @property float|null $min_cargo_capacity Capacidad mínima de carga en toneladas
 * @property float|null $max_cargo_capacity Capacidad máxima de carga en toneladas
 * @property int|null $min_container_capacity Capacidad mínima de contenedores
 * @property int|null $max_container_capacity Capacidad máxima de contenedores
 * @property float|null $min_liquid_capacity Capacidad mínima líquidos en m³
 * @property float|null $max_liquid_capacity Capacidad máxima líquidos en m³
 * @property int|null $typical_crew_size Tamaño típico de tripulación
 * @property int|null $max_crew_size Tamaño máximo de tripulación
 * @property float|null $typical_speed Velocidad típica en nudos
 * @property float|null $max_speed Velocidad máxima en nudos
 * @property int|null $fuel_consumption_per_day Consumo combustible por día en litros
 * @property bool $handles_containers Maneja contenedores
 * @property bool $handles_bulk_cargo Maneja carga a granel
 * @property bool $handles_general_cargo Maneja carga general
 * @property bool $handles_liquid_cargo Maneja carga líquida
 * @property bool $handles_dangerous_goods Maneja mercancías peligrosas
 * @property bool $handles_refrigerated_cargo Maneja carga refrigerada
 * @property bool $handles_oversized_cargo Maneja carga sobredimensionada
 * @property bool $river_navigation Navegación fluvial
 * @property bool $maritime_navigation Navegación marítima
 * @property bool $coastal_navigation Navegación costera
 * @property bool $lake_navigation Navegación lacustre
 * @property float|null $min_water_depth Profundidad mínima requerida en metros
 * @property bool $can_be_lead_vessel Puede ser embarcación líder
 * @property bool $can_be_in_convoy Puede ir en convoy
 * @property bool $can_push_barges Puede empujar barcazas
 * @property bool $can_tow_barges Puede remolcar barcazas
 * @property int|null $max_barges_in_convoy Máximo de barcazas en convoy
 * @property bool $requires_pilot Requiere piloto
 * @property bool $requires_tugboat_assistance Requiere asistencia de remolcador
 * @property array|null $environmental_restrictions Restricciones ambientales
 * @property array|null $seasonal_restrictions Restricciones estacionales
 * @property array|null $weather_limitations Limitaciones climáticas
 * @property bool $requires_special_permits Requiere permisos especiales
 * @property bool $requires_insurance Requiere seguro
 * @property bool $requires_safety_certificate Requiere certificado de seguridad
 * @property array|null $required_certifications Certificaciones requeridas
 * @property string|null $imo_type_code Código tipo IMO
 * @property string|null $inland_vessel_code Código embarcación fluvial
 * @property string|null $imdg_class Clase IMDG para mercancías peligrosas
 * @property string|null $argentina_ws_code Código para webservice Argentina
 * @property string|null $paraguay_ws_code Código para webservice Paraguay
 * @property array|null $webservice_mapping Mapeo adicional para webservices
 * @property float|null $daily_charter_rate Tarifa diaria de alquiler
 * @property float|null $fuel_cost_per_day Costo combustible por día
 * @property int|null $typical_voyage_duration Duración típica de viaje en días
 * @property int|null $loading_time_hours Tiempo de carga en horas
 * @property int|null $unloading_time_hours Tiempo de descarga en horas
 * @property array|null $compatible_ports Puertos compatibles
 * @property array|null $restricted_ports Puertos restringidos
 * @property array|null $preferred_berths Muelles preferidos por puerto
 * @property int|null $typical_lifespan_years Vida útil típica en años
 * @property int|null $maintenance_interval_days Intervalo mantenimiento en días
 * @property bool $requires_dry_dock Requiere dique seco
 * @property int|null $dry_dock_interval_months Intervalo dique seco en meses
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
        'min_cargo_capacity',
        'max_cargo_capacity',
        'min_container_capacity',
        'max_container_capacity',
        'min_liquid_capacity',
        'max_liquid_capacity',
        'typical_crew_size',
        'max_crew_size',
        'typical_speed',
        'max_speed',
        'fuel_consumption_per_day',
        'handles_containers',
        'handles_bulk_cargo',
        'handles_general_cargo',
        'handles_liquid_cargo',
        'handles_dangerous_goods',
        'handles_refrigerated_cargo',
        'handles_oversized_cargo',
        'river_navigation',
        'maritime_navigation',
        'coastal_navigation',
        'lake_navigation',
        'min_water_depth',
        'can_be_lead_vessel',
        'can_be_in_convoy',
        'can_push_barges',
        'can_tow_barges',
        'max_barges_in_convoy',
        'requires_pilot',
        'requires_tugboat_assistance',
        'environmental_restrictions',
        'seasonal_restrictions',
        'weather_limitations',
        'requires_special_permits',
        'requires_insurance',
        'requires_safety_certificate',
        'required_certifications',
        'imo_type_code',
        'inland_vessel_code',
        'imdg_class',
        'argentina_ws_code',
        'paraguay_ws_code',
        'webservice_mapping',
        'daily_charter_rate',
        'fuel_cost_per_day',
        'typical_voyage_duration',
        'loading_time_hours',
        'unloading_time_hours',
        'compatible_ports',
        'restricted_ports',
        'preferred_berths',
        'typical_lifespan_years',
        'maintenance_interval_days',
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
        'min_cargo_capacity' => 'decimal:2',
        'max_cargo_capacity' => 'decimal:2',
        'min_liquid_capacity' => 'decimal:2',
        'max_liquid_capacity' => 'decimal:2',
        'min_water_depth' => 'decimal:2',
        'typical_speed' => 'decimal:2',
        'max_speed' => 'decimal:2',
        'daily_charter_rate' => 'decimal:2',
        'fuel_cost_per_day' => 'decimal:2',
        'handles_containers' => 'boolean',
        'handles_bulk_cargo' => 'boolean',
        'handles_general_cargo' => 'boolean',
        'handles_liquid_cargo' => 'boolean',
        'handles_dangerous_goods' => 'boolean',
        'handles_refrigerated_cargo' => 'boolean',
        'handles_oversized_cargo' => 'boolean',
        'river_navigation' => 'boolean',
        'maritime_navigation' => 'boolean',
        'coastal_navigation' => 'boolean',
        'lake_navigation' => 'boolean',
        'can_be_lead_vessel' => 'boolean',
        'can_be_in_convoy' => 'boolean',
        'can_push_barges' => 'boolean',
        'can_tow_barges' => 'boolean',
        'requires_pilot' => 'boolean',
        'requires_tugboat_assistance' => 'boolean',
        'requires_special_permits' => 'boolean',
        'requires_insurance' => 'boolean',
        'requires_safety_certificate' => 'boolean',
        'requires_dry_dock' => 'boolean',
        'active' => 'boolean',
        'is_common' => 'boolean',
        'is_specialized' => 'boolean',
        'environmental_restrictions' => 'array',
        'seasonal_restrictions' => 'array',
        'weather_limitations' => 'array',
        'required_certifications' => 'array',
        'webservice_mapping' => 'array',
        'compatible_ports' => 'array',
        'restricted_ports' => 'array',
        'preferred_berths' => 'array',
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
        'general_cargo' => 'Carga General',
        'liquid_cargo' => 'Carga Líquida',
        'dangerous_goods' => 'Mercancías Peligrosas',
        'refrigerated_cargo' => 'Carga Refrigerada',
        'oversized_cargo' => 'Carga Sobredimensionada',
    ];

    /**
     * Tipos de navegación.
     */
    public const NAVIGATION_TYPES = [
        'river' => 'Navegación Fluvial',
        'maritime' => 'Navegación Marítima',
        'coastal' => 'Navegación Costera',
        'lake' => 'Navegación Lacustre',
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
// RELACIONES ADICIONALES CORRECTAS
// =====================================================

/**
 * Alias para el propietario (usado en algunas vistas).
 */
public function vesselOwner(): BelongsTo
{
    return $this->owner();
}

/**
 * Viajes donde participa esta embarcación.
 * TEMPORAL: Comentado hasta crear modelo Voyage
 */
// public function voyages(): HasMany
// {
//     return $this->hasMany(Voyage::class, 'lead_vessel_id');
// }

/**
 * Envíos/shipments de esta embarcación.
 * TEMPORAL: Comentado hasta crear modelo Shipment
 */
// public function shipments(): HasMany
// {
//     return $this->hasMany(Shipment::class, 'vessel_id');
// }

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
     * Scope para tipos que manejan carga líquida.
     */
    public function scopeHandlesLiquidCargo(Builder $query): Builder
    {
        return $query->where('handles_liquid_cargo', true);
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
     * Scope para tipos que pueden empujar barcazas.
     */
    public function scopeCanPushBarges(Builder $query): Builder
    {
        return $query->where('can_push_barges', true);
    }

    /**
     * Scope para tipos que pueden remolcar barcazas.
     */
    public function scopeCanTowBarges(Builder $query): Builder
    {
        return $query->where('can_tow_barges', true);
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
     * Scope para navegación costera.
     */
    public function scopeCoastalNavigation(Builder $query): Builder
    {
        return $query->where('coastal_navigation', true);
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
            $field = "handles_{$key}";
            
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

        if ($this->coastal_navigation) {
            $capabilities[] = 'coastal';
        }

        if ($this->lake_navigation) {
            $capabilities[] = 'lake';
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
     * Obtener rango de capacidad de carga como string.
     */
    public function getCargoCapacityRangeAttribute(): ?string
    {
        if (!$this->min_cargo_capacity && !$this->max_cargo_capacity) {
            return null;
        }
        
        if ($this->min_cargo_capacity && $this->max_cargo_capacity) {
            return "{$this->min_cargo_capacity}t - {$this->max_cargo_capacity}t";
        }
        
        if ($this->min_cargo_capacity) {
            return "≥ {$this->min_cargo_capacity}t";
        }
        
        return "≤ {$this->max_cargo_capacity}t";
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
        if (!$this->maintenance_interval_days) {
            return null;
        }
        
        $days = $this->maintenance_interval_days;
        
        if ($days >= 365) {
            $years = round($days / 365, 1);
            return $years == 1 ? "1 año" : "{$years} años";
        }
        
        if ($days >= 30) {
            $months = round($days / 30, 1);
            return $months == 1 ? "1 mes" : "{$months} meses";
        }
        
        return "{$days} días";
    }

    /**
     * Verificar si puede operar en convoy.
     */
    public function canOperateInConvoy(): bool
    {
        return $this->can_be_in_convoy || $this->can_be_lead_vessel;
    }

    /**
     * Verificar si puede manejar convoy de barcazas.
     */
    public function canHandleBargeConvoy(): bool
    {
        return $this->can_push_barges || $this->can_tow_barges;
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
            case 'liquid':
                return $query->handlesLiquidCargo();
            case 'convoy_lead':
                return $query->canLead();
            case 'push_barges':
                return $query->canPushBarges();
            case 'tow_barges':
                return $query->canTowBarges();
            case 'river':
                return $query->riverNavigation();
            case 'maritime':
                return $query->maritimeNavigation();
            case 'coastal':
                return $query->coastalNavigation();
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
            'with_convoy_capability' => static::active()
                ->where(function($q) {
                    $q->where('can_push_barges', true)
                      ->orWhere('can_tow_barges', true);
                })->count(),
        ];
    }
}