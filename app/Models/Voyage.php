<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Schema; 
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\WebserviceTransaction;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Vessel;
use App\Models\VoyageWebserviceStatus;



/**
 * Voyage Model
 * 
 * Modelo principal para viajes del sistema de transporte fluvial.
 * Agrupa embarcaciones y cargas en un viaje específico.
 * Un viaje puede contener múltiples envíos (convoy) y múltiples cargas.
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * Basado en datos reales del sistema:
 * - MAERSK LINE ARGENTINA S.A
 * - Ruta ARBUE → PYTVT (Buenos Aires → Paraguay Terminal Villeta)  
 * - Viajes como V022NB, embarcaciones como PAR13001, GUARAN F
 * 
 * @property int $id
 * @property string $voyage_number
 * @property string|null $internal_reference
 * @property int $company_id
 * @property int $lead_vessel_id
 * @property int|null $captain_id
 * @property int $origin_country_id
 * @property int $origin_port_id
 * @property int $destination_country_id
 * @property int $destination_port_id
 * @property int|null $transshipment_port_id
 * @property int|null $origin_customs_id
 * @property int|null $destination_customs_id
 * @property int|null $transshipment_customs_id
 * @property \Carbon\Carbon $departure_date
 * @property \Carbon\Carbon $estimated_arrival_date
 * @property \Carbon\Carbon|null $actual_arrival_date
 * @property \Carbon\Carbon|null $customs_clearance_deadline
 * @property string $voyage_type
 * @property string $cargo_type
 * @property bool $is_convoy
 * @property int $vessel_count
 * @property decimal $total_cargo_capacity_tons
 * @property int $total_container_capacity
 * @property decimal $total_cargo_weight_loaded
 * @property int $total_containers_loaded
 * @property decimal $capacity_utilization_percentage
 * @property string $status
 * @property string $priority_level
 * @property bool $requires_escort
 * @property bool $requires_pilot
 * @property bool $hazardous_cargo
 * @property bool $refrigerated_cargo
 * @property bool $oversized_cargo
 * @property string|null $weather_conditions
 * @property string|null $route_conditions
 * @property string|null $special_instructions
 * @property string|null $operational_notes
 * @property decimal|null $estimated_cost
 * @property decimal|null $actual_cost
 * @property string|null $cost_currency
 * @property bool $safety_approved
 * @property bool $customs_cleared_origin
 * @property bool $customs_cleared_destination
 * @property bool $documentation_complete
 * @property bool $environmental_approved
 * @property \Carbon\Carbon|null $safety_approval_date
 * @property \Carbon\Carbon|null $customs_approval_date
 * @property \Carbon\Carbon|null $environmental_approval_date
 * @property bool $active
 * @property bool $archived
 * @property bool $requires_follow_up
 * @property string|null $follow_up_reason
 * @property \Carbon\Carbon $created_date
 * @property int|null $created_by_user_id
 * @property \Carbon\Carbon $last_updated_date
 * @property int|null $last_updated_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Voyage extends Model
{
    use HasFactory;

       /**
     * The table associated with the model.
     */
    protected $table = 'voyages';

    /**
     * The attributes that are mass assignable.
     * 100% coherente con migración create_voyages_table.php corregida
     */
    protected $fillable = [
        // Basic identification
        'voyage_number',
        'internal_reference',

        // Foreign keys
        'company_id',
        'lead_vessel_id',
        'captain_id',
        'origin_country_id',
        'origin_port_id',
        'destination_country_id',
        'destination_port_id',
        'transshipment_port_id',
        'origin_customs_id',
        'destination_customs_id',
        'transshipment_customs_id',

        // Voyage dates
        'departure_date',
        'estimated_arrival_date',
        'actual_arrival_date',
        'customs_clearance_date',
        'customs_clearance_deadline',
        'cargo_loading_start',
        'cargo_loading_end',
        'cargo_discharge_start',
        'cargo_discharge_end',

        // Voyage type and characteristics
        'voyage_type',
        'cargo_type',
        'is_consolidated',
        'has_transshipment',
        'requires_pilot',
        'is_convoy',
        'vessel_count',

        // Voyage status
        'status',
        'priority_level',

        // Cargo capacity and statistics (CAMPOS CRÍTICOS)
        'total_cargo_capacity_tons',
        'total_container_capacity',
        'total_cargo_weight_loaded',
        'total_containers_loaded',
        'capacity_utilization_percentage',

        // Cargo summary (compatibilidad con migración original)
        'total_containers',
        'total_cargo_weight',
        'total_cargo_volume',
        'total_bills_of_lading',
        'total_clients',

        // Special requirements and cargo types
        'requires_escort',
        'hazardous_cargo',
        'refrigerated_cargo',
        'oversized_cargo',
        'dangerous_cargo',

        // Webservice integration
        'argentina_voyage_id',
        'paraguay_voyage_id',
        'argentina_status',
        'paraguay_status',
        'argentina_sent_at',
        'paraguay_sent_at',

        // Financial information (nombres del modelo + alias migración)
        'estimated_cost',
        'actual_cost',
        'cost_currency',
        'estimated_freight_cost',
        'actual_freight_cost',
        'fuel_cost',
        'port_charges',
        'total_voyage_cost',
        'currency_code',

        // Weather and conditions (nombres del modelo + alias migración)
        'weather_conditions',
        'route_conditions',
        'river_conditions',
        'special_instructions',
        'operational_notes',
        'voyage_notes',
        'delays_explanation',

        // Documents and approvals (campos del modelo + migración)
        'required_documents',
        'uploaded_documents',
        'customs_approved',
        'port_authority_approved',
        'all_documents_ready',
        'safety_approved',
        'customs_cleared_origin',
        'customs_cleared_destination',
        'documentation_complete',
        'environmental_approved',

        // Approval dates
        'safety_approval_date',
        'customs_approval_date',
        'environmental_approval_date',

        // Emergency and safety
        'emergency_contacts',
        'safety_equipment',
        'safety_notes',

        // Performance tracking
        'distance_nautical_miles',
        'average_speed_knots',
        'transit_time_hours',
        'fuel_consumption',
        'fuel_efficiency',

        // Communication
        'communication_frequency',
        'reporting_schedule',
        'last_position_report',

        // Status flags
        'active',
        'archived',
        'requires_follow_up',
        'follow_up_reason',
        'has_incidents',

        // Audit trail
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     * 100% coherente con migración create_voyages_table.php corregida
     */
    protected $casts = [
        // === FECHAS Y TIMESTAMPS ===
        'departure_date' => 'datetime',
        'estimated_arrival_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
        'customs_clearance_date' => 'datetime',
        'customs_clearance_deadline' => 'datetime',
        'cargo_loading_start' => 'datetime',
        'cargo_loading_end' => 'datetime',
        'cargo_discharge_start' => 'datetime',
        'cargo_discharge_end' => 'datetime',
        'argentina_sent_at' => 'datetime',
        'paraguay_sent_at' => 'datetime',
        'safety_approval_date' => 'datetime',
        'customs_approval_date' => 'datetime',
        'environmental_approval_date' => 'datetime',
        'last_position_report' => 'datetime',
        'created_date' => 'datetime',
        'last_updated_date' => 'datetime',

        // === DECIMALES (CAPACIDADES Y ESTADÍSTICAS) ===
        // Campos críticos de capacidad
        'total_cargo_capacity_tons' => 'decimal:2',        // 10,2
        'total_cargo_weight_loaded' => 'decimal:2',        // 10,2
        'capacity_utilization_percentage' => 'decimal:2',  // 5,2
        
        // Campos de resumen de carga (alias)
        'total_cargo_weight' => 'decimal:2',               // 12,2
        'total_cargo_volume' => 'decimal:2',               // 12,2
        
        // Información financiera
        'estimated_cost' => 'decimal:2',                   // 10,2
        'actual_cost' => 'decimal:2',                      // 10,2
        'estimated_freight_cost' => 'decimal:2',           // 10,2 (alias)
        'actual_freight_cost' => 'decimal:2',              // 10,2 (alias)
        'fuel_cost' => 'decimal:2',                        // 10,2
        'port_charges' => 'decimal:2',                     // 10,2
        'total_voyage_cost' => 'decimal:2',                // 10,2
        
        // Tracking de rendimiento
        'distance_nautical_miles' => 'decimal:2',          // 8,2
        'average_speed_knots' => 'decimal:2',              // 5,2
        'fuel_consumption' => 'decimal:2',                 // 8,2
        'fuel_efficiency' => 'decimal:2',                  // 8,2

        // === BOOLEANOS (CARACTERÍSTICAS Y ESTADOS) ===
        // Características del viaje
        'is_consolidated' => 'boolean',
        'has_transshipment' => 'boolean',
        'requires_pilot' => 'boolean',
        'is_convoy' => 'boolean',
        
        // Requerimientos especiales
        'requires_escort' => 'boolean',
        'hazardous_cargo' => 'boolean',
        'refrigerated_cargo' => 'boolean',
        'oversized_cargo' => 'boolean',
        'dangerous_cargo' => 'boolean',                    // alias
        
        // Aprobaciones y despachos
        'customs_approved' => 'boolean',
        'port_authority_approved' => 'boolean',
        'all_documents_ready' => 'boolean',
        'safety_approved' => 'boolean',
        'customs_cleared_origin' => 'boolean',
        'customs_cleared_destination' => 'boolean',
        'documentation_complete' => 'boolean',
        'environmental_approved' => 'boolean',
        
        // Estados de control
        'active' => 'boolean',
        'archived' => 'boolean',
        'requires_follow_up' => 'boolean',
        'has_incidents' => 'boolean',

        // === CAMPOS JSON (DATOS COMPLEJOS) ===
        'weather_conditions' => 'array',
        'route_conditions' => 'array',
        'river_conditions' => 'array',                     // alias
        'required_documents' => 'array',
        'uploaded_documents' => 'array',
        'emergency_contacts' => 'array',
        'safety_equipment' => 'array',
        'reporting_schedule' => 'array',

        // === ENTEROS (CANTIDADES Y CONTADORES) ===
        'vessel_count' => 'integer',
        'total_container_capacity' => 'integer',
        'total_containers_loaded' => 'integer',
        'total_containers' => 'integer',                   // alias
        'total_bills_of_lading' => 'integer',
        'total_clients' => 'integer',
        'transit_time_hours' => 'integer',
        'created_by_user_id' => 'integer',
        'last_updated_by_user_id' => 'integer',
    ];

    /**
     * Relations
     */
    // Relacion con la empresa propietaria del viaje
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
    /**
     * Transacciones de webservice relacionadas con el viaje
     */
    public function webserviceTransactions(): HasMany
    {
        return $this->hasMany(WebserviceTransaction::class, 'voyage_id');
    }

    //
// === RELACIONES PRINCIPALES ===
//
 
/**
 * Empresa organizadora del viaje.
 */
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class, 'company_id');
}

/**
 * Embarcación líder/principal del viaje.
 */
public function leadVessel(): BelongsTo
{
    return $this->belongsTo(Vessel::class, 'lead_vessel_id');
}

/**
 * Capitán principal del viaje.
 */
public function captain(): BelongsTo
{
    return $this->belongsTo(Captain::class, 'captain_id');
}

/**
 * Puerto de origen.
 */
public function originPort(): BelongsTo
{
    return $this->belongsTo(Port::class, 'origin_port_id');
}

/**
 * Puerto de destino.
 */
public function destinationPort(): BelongsTo
{
    return $this->belongsTo(Port::class, 'destination_port_id');
}

/**
 * Puerto de transbordo (opcional).
 */
public function transshipmentPort(): BelongsTo
{
    return $this->belongsTo(Port::class, 'transshipment_port_id');
}

/**
 * País de origen.
 */
public function originCountry(): BelongsTo
{
    return $this->belongsTo(Country::class, 'origin_country_id');
}

/**
 * País de destino.
 */
public function destinationCountry(): BelongsTo
{
    return $this->belongsTo(Country::class, 'destination_country_id');
}

//
// === RELACIONES CON OTROS MÓDULOS ===
//

/**
 * Embarcación principal (alias de leadVessel para compatibilidad)
 */
public function vessel(): BelongsTo
{
    return $this->leadVessel();
}

/**
 * ✅ NUEVO: Estados de webservice independientes por tipo
 * Permite múltiples webservices por voyage (anticipada + micdta + desconsolidado + transbordo)
 */
public function webserviceStatuses(): HasMany
{
    return $this->hasMany(VoyageWebserviceStatus::class);
}

/**
 * Contenedores a través de eager loading (más simple)
 */
public function getAllContainers()
{
    return Container::whereHas('shipmentItems.shipment', function($query) {
        $query->where('voyage_id', $this->id);
    });
}

/**
 * Todas las embarcaciones del convoy (más simple)
 */
public function getAllVessels()
{
    return Vessel::whereIn('id', $this->shipments->pluck('vessel_id'));
}

public function vessels()
{
    return $this->hasManyThrough(
        Vessel::class,
        Shipment::class,
        'voyage_id',      // Foreign key en shipments
        'id',             // Foreign key en vessels  
        'id',             // Local key en voyages
        'vessel_id'       // Local key en shipments
    );
}


//
// === SCOPES ÚTILES ===
//

/**
 * Scope para viajes disponibles para webservices.
 */
public function scopeAvailableForWebservices(Builder $query): Builder
{
    return $query->where('active', true)
                 ->where('status', '!=', 'cancelled')
                 ->whereNotNull('lead_vessel_id');
}

/**
 * Scope para viajes por empresa.
 */
public function scopeForCompany(Builder $query, int $companyId): Builder
{
    return $query->where('company_id', $companyId);
}

/**
 * Scope para incluir relaciones necesarias para webservices.
 */
public function scopeWithWebserviceRelations(Builder $query): Builder
{
    return $query->with([
        'leadVessel',
        'captain',
        'originPort.country',
        'destinationPort.country',
        'transshipmentPort'
    ]);
}

    //
    // === SCOPES ===
    //

    /**
     * Solo viajes activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)->where('archived', false);
    }

    /**
     * Solo viajes archivados
     */
    public function scopeArchived($query)
    {
        return $query->where('archived', true);
    }

    /**
     * Filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filtrar por empresa
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Viajes en rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('departure_date', [$startDate, $endDate]);
    }

    /**
     * Viajes con salida en los próximos días
     */
    public function scopeDepartingInDays($query, $days = 7)
    {
        return $query->whereBetween('departure_date', [now(), now()->addDays($days)]);
    }

    /**
     * Viajes con llegada estimada en los próximos días
     */
    public function scopeArrivingInDays($query, $days = 7)
    {
        return $query->whereBetween('estimated_arrival_date', [now(), now()->addDays($days)]);
    }

    /**
     * Viajes que requieren seguimiento
     */
    public function scopeRequiringFollowUp($query)
    {
        return $query->where('requires_follow_up', true);
    }

    /**
     * Viajes con carga peligrosa
     */
    public function scopeWithHazardousCargo($query)
    {
        return $query->where('hazardous_cargo', true);
    }

    /**
     * Viajes con carga refrigerada
     */
    public function scopeWithRefrigeratedCargo($query)
    {
        return $query->where('refrigerated_cargo', true);
    }

    /**
     * Viajes tipo convoy
     */
    public function scopeConvoys($query)
    {
        return $query->where('is_convoy', true);
    }

    /**
     * Viajes por ruta (origen → destino)
     */
    public function scopeByRoute($query, $originPortId, $destinationPortId)
    {
        return $query->where('origin_port_id', $originPortId)
                    ->where('destination_port_id', $destinationPortId);
    }

    /**
     * Viajes con todas las aprobaciones completas
     */
    public function scopeFullyApproved($query)
    {
        return $query->where('safety_approved', true)
                    ->where('customs_cleared_origin', true)
                    ->where('documentation_complete', true);
    }

    //
    // === ACCESSORS & MUTATORS ===
    //

    /**
     * Obtener descripción de la ruta
     */
    public function getRouteDescriptionAttribute(): string
    {
        $route = $this->originPort->code . ' → ' . $this->destinationPort->code;
        
        if ($this->transshipmentPort) {
            $route = $this->originPort->code . ' → ' . $this->transshipmentPort->code . ' → ' . $this->destinationPort->code;
        }
        
        return $route;
    }

    /**
     * Calcular duración estimada del viaje
     */
    public function getEstimatedDurationAttribute(): ?int
    {
        if (!$this->departure_date || !$this->estimated_arrival_date) {
            return null;
        }
        
        return $this->departure_date->diffInHours($this->estimated_arrival_date);
    }

    /**
     * Calcular duración real del viaje
     */
    public function getActualDurationAttribute(): ?int
    {
        if (!$this->departure_date || !$this->actual_arrival_date) {
            return null;
        }
        
        return $this->departure_date->diffInHours($this->actual_arrival_date);
    }

    /**
     * Verificar si hay retraso
     */
    public function getIsDelayedAttribute(): bool
    {
        if (!$this->estimated_arrival_date) {
            return false;
        }
        
        $compareDate = $this->actual_arrival_date ?? now();
        return $compareDate > $this->estimated_arrival_date;
    }

    /**
     * Calcular horas de retraso
     */
    public function getDelayHoursAttribute(): int
    {
        if (!$this->is_delayed) {
            return 0;
        }
        
        $compareDate = $this->actual_arrival_date ?? now();
        return max(0, $this->estimated_arrival_date->diffInHours($compareDate));
    }

    /**
     * Verificar si está en tránsito
     */
    public function getIsInTransitAttribute(): bool
    {
        return in_array($this->status, ['departed', 'in_transit']);
    }

    /**
     * Verificar si está completado
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'delivered']);
    }

    /**
     * Obtener color de estado para UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'planning' => 'blue',
            'approved' => 'green',
            'departed' => 'yellow',
            'in_transit' => 'orange',
            'arrived' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red',
            'delayed' => 'red',
            default => 'gray'
        };
    }

    /**
     * Calcular progreso del viaje (0-100%)
     */
    public function getProgressPercentageAttribute(): int
    {
        if (!$this->departure_date || !$this->estimated_arrival_date) {
            return 0;
        }
        
        if ($this->status === 'planning') {
            return 0;
        }
        
        if ($this->is_completed) {
            return 100;
        }
        
        $totalDuration = $this->departure_date->diffInMinutes($this->estimated_arrival_date);
        $elapsed = $this->departure_date->diffInMinutes(now());
        
        return min(100, max(0, round(($elapsed / $totalDuration) * 100)));
    }

    //
    // === METHODS ===
    //

    /**
     * Recalcular estadísticas basadas en shipments
     * CORREGIDO: Usa campos que realmente existen en la tabla voyages
     */
    public function recalculateShipmentStats(): void
    {
        // Cargar la relación si no está cargada
        if (!$this->relationLoaded('shipments')) {
            $this->load('shipments');
        }
        
        $shipments = $this->shipments;
        
        // Verificar que la colección no sea null
        if ($shipments === null || $shipments->isEmpty()) {
            $this->updateEmptyStats();
            return;
        }

        // Calcular estadísticas básicas
        $totalVessels = $shipments->count();
        $totalCapacityTons = $shipments->sum('cargo_capacity_tons');
        $totalContainerCapacity = $shipments->sum('container_capacity');
        $totalCargoLoaded = $shipments->sum('cargo_weight_loaded');
        $totalContainersLoaded = $shipments->sum('containers_loaded');
        
        // Calcular utilización promedio manualmente
        $avgUtilization = $shipments->count() > 0 
            ? $shipments->avg('utilization_percentage') ?? 0 
            : 0;

        // Verificar si hay carga peligrosa en algún shipment
        $hasDangerousCargo = $shipments->contains(function ($shipment) {
            return $shipment->has_dangerous_cargo ?? false;
        });

        // Verificar si requiere manejo especial
        $requiresSpecialHandling = $shipments->contains(function ($shipment) {
            return ($shipment->has_dangerous_cargo ?? false) || 
                ($shipment->requires_refrigeration ?? false) ||
                ($shipment->oversized_cargo ?? false);
        });

        // ACTUALIZACIÓN CON CAMPOS QUE EXISTEN EN LA MIGRACIÓN
        $this->update([
            // Estadísticas básicas de capacidad (✅ EXISTEN)
            'vessel_count' => $totalVessels,
            'total_cargo_capacity_tons' => $totalCapacityTons,
            'total_container_capacity' => $totalContainerCapacity,
            'total_cargo_weight_loaded' => $totalCargoLoaded,
            'total_containers_loaded' => $totalContainersLoaded,
            'capacity_utilization_percentage' => round($avgUtilization, 2),
            
            // Campos alias para compatibilidad (✅ EXISTEN)
            'total_containers' => $totalContainersLoaded,
            'total_cargo_weight' => $totalCargoLoaded,
            
            // Campos agregados para estadísticas de items (✅ EXISTEN EN MIGRACIÓN)
            'has_dangerous_cargo' => $hasDangerousCargo,
            'requires_special_handling' => $requiresSpecialHandling,
            
            // Actualizar timestamp
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Actualizar estadísticas cuando no hay shipments
     */
    private function updateEmptyStats(): void
    {
        $this->update([
            'vessel_count' => 0,
            'total_cargo_capacity_tons' => 0,
            'total_container_capacity' => 0,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
            'total_containers' => 0,
            'total_cargo_weight' => 0,
            'has_dangerous_cargo' => false,
            'requires_special_handling' => false,
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Obtener estadísticas agregadas de todos los items del viaje
     */
    private function getAggregatedItemsStats(): array
    {
        // Usar query optimizada para obtener estadísticas de items
        $stats = DB::table('shipment_items')
                   ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
                   ->where('shipments.voyage_id', $this->id)
                   ->selectRaw('
                       COUNT(*) as total_items,
                       SUM(package_quantity) as total_packages,
                       SUM(gross_weight_kg) as total_weight_kg,
                       SUM(volume_m3) as total_volume_m3,
                       SUM(declared_value) as total_value,
                       SUM(CASE WHEN is_dangerous_goods = 1 THEN 1 ELSE 0 END) as dangerous_goods_count,
                       SUM(CASE WHEN requires_refrigeration = 1 THEN 1 ELSE 0 END) as refrigerated_count,
                       SUM(CASE WHEN has_discrepancies = 1 THEN 1 ELSE 0 END) as discrepancies_count
                   ')
                   ->first();

        return [
            'total_items' => $stats->total_items ?? 0,
            'total_packages' => $stats->total_packages ?? 0,
            'total_weight_kg' => $stats->total_weight_kg ?? 0,
            'total_volume_m3' => $stats->total_volume_m3 ?? 0,
            'total_value' => $stats->total_value ?? 0,
            'dangerous_goods_count' => $stats->dangerous_goods_count ?? 0,
            'refrigerated_count' => $stats->refrigerated_count ?? 0,
            'discrepancies_count' => $stats->discrepancies_count ?? 0,
        ];
    }

    /**
     * Actualizar estadísticas del viaje
     */
    private function updateShipmentStatistics(
        int $totalShipments,
        float $totalCapacityTons,
        float $totalLoadedTons,
        int $totalContainerCapacity,
        int $totalContainersLoaded,
        array $itemsStats = []
    ): void {
        $utilizationPercentage = $totalCapacityTons > 0 
            ? min(100, ($totalLoadedTons / $totalCapacityTons) * 100) 
            : 0;

        $updateData = [
            'total_shipments' => $totalShipments,
            'total_cargo_capacity_tons' => $totalCapacityTons,
            'total_cargo_loaded_tons' => $totalLoadedTons,
            'cargo_utilization_percentage' => round($utilizationPercentage, 2),
            'total_container_capacity' => $totalContainerCapacity,
            'total_containers_loaded' => $totalContainersLoaded,
        ];

        // Agregar estadísticas de items si están disponibles
        if (!empty($itemsStats)) {
            $updateData = array_merge($updateData, [
                'total_items' => $itemsStats['total_items'],
                'total_packages' => $itemsStats['total_packages'],
                'has_dangerous_cargo' => $itemsStats['dangerous_goods_count'] > 0,
                'requires_special_handling' => $itemsStats['refrigerated_count'] > 0 || $itemsStats['dangerous_goods_count'] > 0,
            ]);
        }

        $this->update($updateData);
    }

    /**
     * Obtener resumen completo del viaje incluyendo items
     */
    public function getCompleteSummary(): array
    {
        $shipmentsStats = $this->getShipmentsSummary();
        $itemsStats = $this->getAggregatedItemsStats();
        
        return [
            'voyage_info' => [
                'voyage_number' => $this->voyage_number,
                'status' => $this->status,
                'route' => $this->origin_port . ' → ' . $this->destination_port,
                'departure_date' => $this->departure_date?->format('Y-m-d H:i'),
                'estimated_arrival' => $this->estimated_arrival_date?->format('Y-m-d H:i'),
            ],
            'shipments' => $shipmentsStats,
            'items' => $itemsStats,
            'capacity_analysis' => [
                'weight_utilization' => $this->cargo_utilization_percentage,
                'is_near_capacity' => $this->cargo_utilization_percentage > 90,
                'available_capacity_tons' => $this->total_cargo_capacity_tons - $this->total_cargo_loaded_tons,
            ],
            'special_requirements' => [
                'has_dangerous_goods' => $itemsStats['dangerous_goods_count'] > 0,
                'requires_refrigeration' => $itemsStats['refrigerated_count'] > 0,
                'has_discrepancies' => $itemsStats['discrepancies_count'] > 0,
            ],
        ];
    }

    /**
     * Verificar si el viaje está listo para partir (incluyendo items)
     */
    public function isReadyForDeparture(): bool
    {
        // Verificaciones básicas del viaje
        if (!parent::isReadyForDeparture()) {
            return false;
        }

        // Verificar que todos los shipments tengan items validados
        foreach ($this->shipments as $shipment) {
            if (!$shipment->areAllItemsValidated()) {
                return false;
            }
            
            if ($shipment->hasPendingDiscrepancies()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener todos los items del viaje agrupados por shipment
     */
    public function getAllItemsByShipment(): array
    {
        return $this->shipments()
                   ->with(['shipmentItems' => function ($query) {
                       $query->orderBy('line_number');
                   }])
                   ->get()
                   ->mapWithKeys(function ($shipment) {
                       return [$shipment->shipment_number => [
                           'shipment_info' => [
                               'id' => $shipment->id,
                               'vessel' => $shipment->vessel->name ?? 'N/A',
                               'status' => $shipment->status,
                           ],
                           'items' => $shipment->shipmentItems->map(function ($item) {
                               return [
                                   'line_number' => $item->line_number,
                                   'description' => $item->item_description,
                                   'packages' => $item->package_quantity,
                                   'weight_kg' => $item->gross_weight_kg,
                                   'status' => $item->status,
                                   'has_issues' => $item->has_discrepancies || $item->requires_review,
                               ];
                           })->toArray(),
                       ]];
                   })
                   ->toArray();
    }

    /**
     * Calcular porcentaje de utilización de capacidad
     */
    private function calculateUtilizationPercentage(): float
    {
        if ($this->total_cargo_capacity_tons == 0) {
            return 0;
        }
        
        return min(100, ($this->total_cargo_weight_loaded / $this->total_cargo_capacity_tons) * 100);
    }

    /**
     * Cambiar estado del viaje
     */
    public function changeStatus(string $newStatus, ?string $reason = null): void
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $newStatus,
            'last_updated_date' => now(),
        ]);
        
        // Acciones específicas por estado
        match($newStatus) {
            'departed' => $this->handleDeparture(),
            'arrived' => $this->handleArrival(),
            'completed' => $this->handleCompletion(),
            'cancelled' => $this->handleCancellation($reason),
            default => null,
        };
        
        // Log del cambio de estado
        $this->logStatusChange($oldStatus, $newStatus, $reason);
    }

    /**
     * Manejar salida del viaje
     */
    private function handleDeparture(): void
    {
        // Actualizar fecha de salida si no está configurada o es futura
        if (!$this->departure_date || $this->departure_date > now()) {
            $this->update(['departure_date' => now()]);
        }
        
        // Marcar capitán como no disponible
        if ($this->captain) {
            $this->captain->markUnavailable($this->estimated_arrival_date);
        }
    }

    /**
     * Manejar llegada del viaje
     */
    private function handleArrival(): void
    {
        $this->update(['actual_arrival_date' => now()]);
        
        // Verificar si hay retraso
        if ($this->is_delayed) {
            $this->update([
                'requires_follow_up' => true,
                'follow_up_reason' => 'Arribó con retraso de ' . $this->delay_hours . ' horas',
            ]);
        }
    }

    /**
     * Manejar finalización del viaje
     */
    private function handleCompletion(): void
    {
        if (!$this->actual_arrival_date) {
            $this->update(['actual_arrival_date' => now()]);
        }
        
        // Marcar capitán como disponible nuevamente
        if ($this->captain) {
            $this->captain->markAvailable();
            $this->captain->updateAfterVoyage($this->actual_arrival_date);
        }
        
        // Actualizar estadísticas de embarcaciones
        foreach ($this->shipments as $shipment) {
            $shipment->vessel->updateAfterVoyage($this->actual_arrival_date);
        }
    }

    /**
     * Manejar cancelación del viaje
     */
    private function handleCancellation(?string $reason): void
    {
        $this->update([
            'requires_follow_up' => true,
            'follow_up_reason' => 'Viaje cancelado: ' . ($reason ?? 'Sin motivo especificado'),
        ]);
        
        // Liberar capitán
        if ($this->captain) {
            $this->captain->markAvailable();
        }
    }

    /**
     * Verificar si puede partir
     */
    public function canDepart(): bool
    {
        return $this->status === 'approved' && 
               $this->safety_approved && 
               $this->customs_cleared_origin && 
               $this->documentation_complete;
    }

    /**
     * Obtener embarcaciones del convoy
     */
    public function getConvoyVessels(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->shipments->map(function ($shipment) {
            return $shipment->vessel;
        });
    }

    /**
     * Verificar si es viaje internacional
     */
    public function isInternational(): bool
    {
        return $this->origin_country_id !== $this->destination_country_id;
    }

    /**
     * Obtener documentos requeridos
     */
    public function getRequiredDocuments(): array
    {
        $documents = [
            'manifest' => 'Manifiesto de Carga',
            'crew_list' => 'Lista de Tripulación',
            'safety_certificate' => 'Certificado de Seguridad',
        ];
        
        if ($this->isInternational()) {
            $documents['customs_declaration'] = 'Declaración Aduanera';
            $documents['port_clearance'] = 'Despacho Portuario';
        }
        
        if ($this->hazardous_cargo) {
            $documents['hazmat_certificate'] = 'Certificado de Mercancías Peligrosas';
        }
        
        if ($this->refrigerated_cargo) {
            $documents['temperature_log'] = 'Registro de Temperatura';
        }
        
        return $documents;
    }

    /**
     * Log de cambio de estado
     */
    private function logStatusChange(string $oldStatus, string $newStatus, ?string $reason): void
    {
        // Aquí se podría implementar un sistema de logs más robusto
        \Log::info("Voyage {$this->voyage_number} status changed from {$oldStatus} to {$newStatus}", [
            'voyage_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'timestamp' => now(),
        ]);
    }

    /**
     * Generar número de viaje automático
     */
    public static function generateVoyageNumber(?Company $company = null): string
    {
        $prefix = $company ? strtoupper(substr($company->name, 0, 3)) : 'VOY';
        $year = now()->format('Y');
        $sequence = str_pad(static::whereYear('created_at', now()->year)->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $year . $sequence;
    }

    /**
     * Obtener próximos hitos del viaje
     */
    public function getUpcomingMilestones(): array
    {
        $milestones = [];
        $now = now();
        
        if ($this->departure_date > $now) {
            $milestones[] = [
                'type' => 'departure',
                'description' => 'Salida programada',
                'datetime' => $this->departure_date,
                'hours_from_now' => $now->diffInHours($this->departure_date),
            ];
        }
        
        if ($this->estimated_arrival_date > $now) {
            $milestones[] = [
                'type' => 'arrival',
                'description' => 'Llegada estimada',
                'datetime' => $this->estimated_arrival_date,
                'hours_from_now' => $now->diffInHours($this->estimated_arrival_date),
            ];
        }
        
        if ($this->customs_clearance_deadline && $this->customs_clearance_deadline > $now) {
            $milestones[] = [
                'type' => 'customs_deadline',
                'description' => 'Fecha límite despacho aduanero',
                'datetime' => $this->customs_clearance_deadline,
                'hours_from_now' => $now->diffInHours($this->customs_clearance_deadline),
                'urgency' => $this->customs_clearance_deadline->diffInDays($now) <= 1 ? 'high' : 'normal',
            ];
        }
        
        return collect($milestones)->sortBy('datetime')->values()->toArray();
    }

    /**
     * Verificar si el viaje puede ser eliminado
     * 
     * Un viaje NO puede ser eliminado si:
     * - Tiene shipments asociados
     * - Está en progreso, completado o en tránsito
     * - Tiene documentación oficial generada
     * - Tiene bills of lading asociados
     */
    public function canBeDeleted(): bool
    {
        // No se puede eliminar si tiene shipments
        if ($this->shipments()->count() > 0) {
            return false;
        }

        // No se puede eliminar si tiene bills of lading
        if ($this->billsOfLading()->count() > 0) {
            return false;
        }

        // No se puede eliminar si está en estados avanzados
        $nonDeletableStatuses = [
            'loading',      // Ya se está cargando
            'in_transit',   // En tránsito
            'discharging',  // Descargando
            'completed',    // Completado
            'departed'      // Ya partió
        ];

        if (in_array($this->status, $nonDeletableStatuses)) {
            return false;
        }

        // No se puede eliminar si ya partió (tiene fecha de salida real)
        if ($this->actual_departure_date) {
            return false;
        }

        // Solo se pueden eliminar viajes en estados iniciales
        $deletableStatuses = ['draft', 'planned', 'confirmed', 'cancelled'];
        
        return in_array($this->status, $deletableStatuses);
    }

    /**
     * Verificar si el viaje puede ser editado
     * 
     * Un viaje NO puede ser editado si:
     * - Está completado o cancelado
     * - Ya partió (tiene fecha de salida real)
     * - Está en tránsito
     */
    public function canBeEdited(): bool
    {
        // No se puede editar si está completado o cancelado
        if (in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }

        // No se puede editar si ya partió
        if ($this->actual_departure_date) {
            return false;
        }

        // No se puede editar si está en tránsito
        if ($this->status === 'in_transit') {
            return false;
        }

        return true;
    }

    /**
     * Verificar si ciertos campos críticos pueden ser editados
     * 
     * Algunos campos no se pueden editar una vez que el viaje está confirmado
     */
    public function canEditCriticalFields(): bool
    {
        // Los campos críticos (embarcación, ruta, fechas) solo se pueden
        // editar en estados iniciales
        $allowedStatuses = ['draft', 'planned'];
        
        return in_array($this->status, $allowedStatuses);
    }

    // ==========================================
    // NUEVOS MÉTODOS PARA ESTADOS INDEPENDIENTES POR PAÍS
    // Agregar al final de la clase Voyage, antes del último }
    // ==========================================

    /**
     * ✅ CORRECCIÓN ERROR CONCEPTUAL: Verificar si puede enviar a un país específico
     * 
     * ANTES: Un viaje se bloqueaba globalmente al enviar a cualquier aduana
     * AHORA: Cada país tiene estado independiente (argentina_status, paraguay_status)
     */
    public function canSendToCountry(string $country): array
    {
         // AGREGAR ESTE LOG AL INICIO
        Log::info('🔥 canSendToCountry EJECUTÁNDOSE', [
            'country_input' => $country,
            'voyage_id' => $this->id,
            'argentina_status' => $this->argentina_status,
        ]);
        $country = strtolower($country);
        
        switch ($country) {
            case 'argentina':
            case 'ar':
                return [
                    'allowed' => in_array($this->argentina_status, ['pending', 'error', null]),
                    'current_status' => $this->argentina_status,
                    'sent_at' => $this->argentina_sent_at,
                    'reason' => $this->argentina_status === 'sent' 
                        ? 'Ya enviado a Argentina el ' . $this->argentina_sent_at?->format('d/m/Y H:i')
                        : ($this->argentina_status === 'approved' 
                            ? 'Ya aprobado por Argentina' 
                            : null)
                ];
            break;  
                
            case 'paraguay':
            case 'py':
                return [
                    'allowed' => in_array($this->paraguay_status, ['pending', 'error', null]),
                    'current_status' => $this->paraguay_status,
                    'sent_at' => $this->paraguay_sent_at,
                    'reason' => $this->paraguay_status === 'sent' 
                        ? 'Ya enviado a Paraguay el ' . $this->paraguay_sent_at?->format('d/m/Y H:i')
                        : ($this->paraguay_status === 'approved' 
                            ? 'Ya aprobado por Paraguay' 
                            : null)
                ];
                
            default:
                return [
                    'allowed' => false,
                    'current_status' => null,
                    'sent_at' => null,
                    'reason' => "País '$country' no soportado"
                ];
        }
    }

    /**
     * ✅ Actualizar estado de envío para un país específico
     */
    public function updateCountryStatus(string $country, string $status, array $additionalData = []): bool
    {
        $country = strtolower($country);
        $updateData = [];
        
        switch ($country) {
            case 'argentina':
            case 'ar':
                $updateData['argentina_status'] = $status;
                if ($status === 'sent') {
                    $updateData['argentina_sent_at'] = now();
                }
                if (isset($additionalData['voyage_id'])) {
                    $updateData['argentina_voyage_id'] = $additionalData['voyage_id'];
                }
                break;
                
            case 'paraguay':
            case 'py':
                $updateData['paraguay_status'] = $status;
                if ($status === 'sent') {
                    $updateData['paraguay_sent_at'] = now();
                }
                if (isset($additionalData['voyage_id'])) {
                    $updateData['paraguay_voyage_id'] = $additionalData['voyage_id'];
                }
                break;
                
            default:
                return false;
        }
        
        return $this->update($updateData);
    }

    /**
     * ✅ MÉTODO CORREGIDO: Obtener estado real desde webservice_transactions
     */
    public function getWebserviceStatusSummary(): array
    {
        // ✅ ARGENTINA: Buscar última transacción exitosa
        $argTransaction = $this->webserviceTransactions()
            ->whereIn('webservice_type', ['anticipada', 'micdta'])
            ->where('country', 'AR')
            ->orderBy('created_at', 'desc')
            ->first();
        
        $argStatus = 'pending';
        $argSentAt = null;
        $argCanSend = true;
        
        if ($argTransaction) {
            if ($argTransaction->status === 'success') {
                $argStatus = 'approved';
                $argSentAt = $argTransaction->response_at;
                $argCanSend = false; // Ya aprobado
            } elseif ($argTransaction->status === 'sent') {
                $argStatus = 'sent';
                $argSentAt = $argTransaction->sent_at;
                $argCanSend = false; // Ya enviado
            } elseif ($argTransaction->status === 'error') {
                $argStatus = 'error';
                $argCanSend = true; // Puede reintentar
            }
        }

        // ✅ PARAGUAY: Buscar última transacción exitosa
        $pyTransaction = $this->webserviceTransactions()
            ->whereIn('webservice_type', ['paraguay_customs', 'manifiesto'])
            ->where('country', 'PY')
            ->orderBy('created_at', 'desc')
            ->first();
        
        $pyStatus = 'pending';
        $pySentAt = null;
        $pyCanSend = true;
        
        if ($pyTransaction) {
            if ($pyTransaction->status === 'success') {
                $pyStatus = 'approved';
                $pySentAt = $pyTransaction->response_at;
                $pyCanSend = false; // Ya aprobado
            } elseif ($pyTransaction->status === 'sent') {
                $pyStatus = 'sent';
                $pySentAt = $pyTransaction->sent_at;
                $pyCanSend = false; // Ya enviado
            } elseif ($pyTransaction->status === 'error') {
                $pyStatus = 'error';
                $pyCanSend = true; // Puede reintentar
            }
        }

        return [
            'argentina' => [
                'status' => $argStatus,
                'sent_at' => $argSentAt,
                'can_send' => $argCanSend,
                'display_status' => $this->getDisplayStatus($argStatus),
                'transaction_id' => $argTransaction?->id
            ],
            'paraguay' => [
                'status' => $pyStatus,
                'sent_at' => $pySentAt,
                'can_send' => $pyCanSend,
                'display_status' => $this->getDisplayStatus($pyStatus),
                'transaction_id' => $pyTransaction?->id
            ]
        ];
    }

    /**
     * ✅ Helper para mostrar estado legible
     */
    private function getDisplayStatus(?string $status): string
    {
        $statusMap = [
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'error' => 'Error'
        ];
        
        return $statusMap[$status] ?? 'Pendiente';
    }

    /**
     * ✅ Verificar si el viaje puede ser enviado a múltiples países
     */
    public function getAvailableDestinationCountries(): array
    {
        $countries = [];
        
        // Determinar países basado en puertos de origen y destino
        if ($this->originPort && $this->destinationPort) {
            $originCountry = $this->originPort->country->code ?? '';
            $destCountry = $this->destinationPort->country->code ?? '';
            
            // Argentina
            if (in_array('AR', [$originCountry, $destCountry])) {
                $argStatus = $this->canSendToCountry('argentina');
                $countries['argentina'] = [
                    'code' => 'AR',
                    'name' => 'Argentina',
                    'can_send' => $argStatus['allowed'],
                    'status' => $argStatus['current_status'] ?? 'pending',
                    'reason' => $argStatus['reason']
                ];
            }
            
            // Paraguay
            if (in_array('PY', [$originCountry, $destCountry])) {
                $pyStatus = $this->canSendToCountry('paraguay');
                $countries['paraguay'] = [
                    'code' => 'PY',
                    'name' => 'Paraguay',
                    'can_send' => $pyStatus['allowed'],
                    'status' => $pyStatus['current_status'] ?? 'pending',
                    'reason' => $pyStatus['reason']
                ];
            }
        }
        
        return $countries;
    }

    // ========================================
    // ✅ NUEVOS MÉTODOS: ESTADOS POR WEBSERVICE ESPECÍFICO
    // Complementan el sistema existente sin reemplazarlo
    // ========================================

    /**
     * ✅ NUEVO: Obtener estado de Información Anticipada
     */
    public function getAnticipadaStatus(): ?VoyageWebserviceStatus
    {
        return $this->webserviceStatuses()
            ->where('country', 'AR')
            ->where('webservice_type', 'anticipada')
            ->first();
    }

    /**
     * ✅ NUEVO: Obtener estado de MIC/DTA
     */
    public function getMicDtaStatus(): ?VoyageWebserviceStatus
    {
        return $this->webserviceStatuses()
            ->where('country', 'AR')
            ->where('webservice_type', 'micdta')
            ->first();
    }

    /**
     * ✅ NUEVO: Obtener estado de Desconsolidados
     */
    public function getDesconsolidadoStatus(): ?VoyageWebserviceStatus
    {
        return $this->webserviceStatuses()
            ->where('country', 'AR')
            ->where('webservice_type', 'desconsolidado')
            ->first();
    }

    /**
     * ✅ NUEVO: Obtener estado de Transbordos
     */
    public function getTransbordoStatus(string $country = 'AR'): ?VoyageWebserviceStatus
    {
        return $this->webserviceStatuses()
            ->where('country', $country)
            ->where('webservice_type', 'transbordo')
            ->first();
    }

    /**
     * ✅ NUEVO: Verificar si puede enviar un webservice específico
     */
    public function canSendWebservice(string $webserviceType, string $country = 'AR'): bool
    {
        $status = $this->webserviceStatuses()
            ->where('country', $country)
            ->where('webservice_type', $webserviceType)
            ->first();

        return $status ? $status->canSend() : true; // Si no existe estado, se puede enviar
    }

    /**
     * ✅ NUEVO: Crear estados iniciales basados en roles de empresa
     */
    public function createInitialWebserviceStatuses(): void
    {
        $company = $this->company;
        if (!$company) {
            return;
        }

        $roles = $company->getRoles() ?? [];
        
        // Mapear roles a webservices requeridos
        $requiredWebservices = [];
        
        if (in_array('cargas', $roles)) {
            $requiredWebservices[] = ['country' => 'AR', 'type' => 'anticipada'];
            $requiredWebservices[] = ['country' => 'AR', 'type' => 'micdta'];
            $requiredWebservices[] = ['country' => 'AR', 'type' => 'mane'];
        }
        
        if (in_array('desconsolidador', $roles)) {
            $requiredWebservices[] = ['country' => 'AR', 'type' => 'desconsolidado'];
        }
        
        if (in_array('transbordos', $roles)) {
            $requiredWebservices[] = ['country' => 'AR', 'type' => 'transbordo'];
            $requiredWebservices[] = ['country' => 'PY', 'type' => 'transbordo'];
        }

        // Crear estados si no existen
        foreach ($requiredWebservices as $webservice) {
            VoyageWebserviceStatus::firstOrCreate([
                'voyage_id' => $this->id,
                'country' => $webservice['country'],
                'webservice_type' => $webservice['type'],
            ], [
                'company_id' => $this->company_id,
                'status' => 'pending',
                'can_send' => true,
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => 3,
            ]);
        }
    }

    /**
     * ✅ NUEVO: Obtener resumen completo de estados múltiples
     * Complementa getWebserviceStatusSummary() existente con información detallada
     */
    public function getMultipleWebserviceStatusSummary(): array
    {
        $summary = [
            'argentina' => [
                'anticipada' => null,
                'micdta' => null,
                'desconsolidado' => null,
                'transbordo' => null,
                'mane' => null,
            ],
            'paraguay' => [
                'manifiesto' => null,
                'transbordo' => null,
            ],
            'stats' => [
                'total_webservices' => 0,
                'approved_count' => 0,
                'pending_count' => 0,
                'error_count' => 0,
            ]
        ];

        foreach ($this->webserviceStatuses as $status) {
            $statusData = [
                'status' => $status->status,
                'can_send' => $status->canSend(),
                'last_sent_at' => $status->last_sent_at,
                'confirmation_number' => $status->confirmation_number,
                'error_message' => $status->last_error_message,
            ];

            // Agregar al resumen por país
            if ($status->country === 'AR') {
                $summary['argentina'][$status->webservice_type] = $statusData;
            } else {
                $summary['paraguay'][$status->webservice_type] = $statusData;
            }

            // Estadísticas generales
            $summary['stats']['total_webservices']++;
            
            if ($status->isSuccessful()) {
                $summary['stats']['approved_count']++;
            } elseif ($status->status === 'pending') {
                $summary['stats']['pending_count']++;
            } elseif ($status->hasErrors()) {
                $summary['stats']['error_count']++;
            }
        }

        return $summary;
    }
}