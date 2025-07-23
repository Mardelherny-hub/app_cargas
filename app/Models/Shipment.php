<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shipment Model
 * 
 * Modelo para envíos individuales dentro de un viaje.
 * Cada embarcación en un convoy es un shipment.
 * Un viaje puede tener 1 shipment (barco único) o varios (convoy).
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * @property int $id
 * @property int $voyage_id
 * @property int $vessel_id
 * @property int|null $captain_id
 * @property string $shipment_number
 * @property int $sequence_in_voyage
 * @property string $vessel_role
 * @property int|null $convoy_position
 * @property bool $is_lead_vessel
 * @property decimal $cargo_capacity_tons
 * @property int $container_capacity
 * @property decimal $cargo_weight_loaded
 * @property int $containers_loaded
 * @property decimal $utilization_percentage
 * @property string $status
 * @property \Carbon\Carbon|null $departure_time
 * @property \Carbon\Carbon|null $arrival_time
 * @property \Carbon\Carbon|null $loading_start_time
 * @property \Carbon\Carbon|null $loading_end_time
 * @property \Carbon\Carbon|null $discharge_start_time
 * @property \Carbon\Carbon|null $discharge_end_time
 * @property bool $safety_approved
 * @property bool $customs_cleared
 * @property bool $documentation_complete
 * @property bool $cargo_inspected
 * @property string|null $special_instructions
 * @property string|null $handling_notes
 * @property string|null $delay_reason
 * @property int|null $delay_minutes
 * @property bool $active
 * @property bool $requires_attention
 * @property bool $has_delays
 * @property \Carbon\Carbon $created_date
 * @property int|null $created_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Shipment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shipments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'voyage_id',
        'vessel_id',
        'captain_id',
        'shipment_number',
        'sequence_in_voyage',
        'vessel_role',
        'convoy_position',
        'is_lead_vessel',
        'cargo_capacity_tons',
        'container_capacity',
        'cargo_weight_loaded',
        'containers_loaded',
        'utilization_percentage',
        'status',
        'departure_time',
        'arrival_time',
        'loading_start_time',
        'loading_end_time',
        'discharge_start_time',
        'discharge_end_time',
        'safety_approved',
        'customs_cleared',
        'documentation_complete',
        'cargo_inspected',
        'special_instructions',
        'handling_notes',
        'delay_reason',
        'delay_minutes',
        'active',
        'requires_attention',
        'has_delays',
        'created_date',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'voyage_id' => 'integer',
        'vessel_id' => 'integer',
        'captain_id' => 'integer',
        'sequence_in_voyage' => 'integer',
        'convoy_position' => 'integer',
        'is_lead_vessel' => 'boolean',
        'cargo_capacity_tons' => 'decimal:2',
        'container_capacity' => 'integer',
        'cargo_weight_loaded' => 'decimal:2',
        'containers_loaded' => 'integer',
        'utilization_percentage' => 'decimal:2',
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'loading_start_time' => 'datetime',
        'loading_end_time' => 'datetime',
        'discharge_start_time' => 'datetime',
        'discharge_end_time' => 'datetime',
        'safety_approved' => 'boolean',
        'customs_cleared' => 'boolean',
        'documentation_complete' => 'boolean',
        'cargo_inspected' => 'boolean',
        'delay_minutes' => 'integer',
        'active' => 'boolean',
        'requires_attention' => 'boolean',
        'has_delays' => 'boolean',
        'created_date' => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'created_by_user_id',
    ];

    // ================================
    // RELATIONSHIPS
    // ================================

    /**
     * Viaje al que pertenece este envío
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class, 'voyage_id');
    }

    /**
     * Embarcación de este envío
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'vessel_id');
    }

    /**
     * Capitán de esta embarcación
     */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(Captain::class, 'captain_id');
    }

    /**
     * Conocimientos de embarque de este envío
     */
    public function billsOfLading(): HasMany
    {
        return $this->hasMany(BillOfLading::class, 'shipment_id');
    }

    // ================================
    // QUERY SCOPES
    // ================================

    /**
     * Envíos activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Envíos por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Envíos que requieren atención
     */
    public function scopeRequiringAttention($query)
    {
        return $query->where('requires_attention', true);
    }

    /**
     * Envíos con demoras
     */
    public function scopeWithDelays($query)
    {
        return $query->where('has_delays', true);
    }

    /**
     * Envíos de un viaje específico
     */
    public function scopeForVoyage($query, $voyageId)
    {
        return $query->where('voyage_id', $voyageId);
    }

    /**
     * Envíos por rol de embarcación
     */
    public function scopeByVesselRole($query, $role)
    {
        return $query->where('vessel_role', $role);
    }

    /**
     * Embarcaciones líder
     */
    public function scopeLeadVessels($query)
    {
        return $query->where('is_lead_vessel', true);
    }

    /**
     * Envíos en convoy
     */
    public function scopeInConvoy($query)
    {
        return $query->whereNotNull('convoy_position');
    }

    /**
     * Envíos ordenados por secuencia en viaje
     */
    public function scopeOrderedBySequence($query)
    {
        return $query->orderBy('sequence_in_voyage');
    }

    // ================================
    // ACCESSORS & MUTATORS
    // ================================

    /**
     * Calcular automáticamente el porcentaje de utilización
     */
    public function setCargoWeightLoadedAttribute($value)
    {
        $this->attributes['cargo_weight_loaded'] = $value;
        $this->calculateUtilization();
    }

    /**
     * Calcular automáticamente el porcentaje de utilización
     */
    public function setContainersLoadedAttribute($value)
    {
        $this->attributes['containers_loaded'] = $value;
        $this->calculateUtilization();
    }

    /**
     * Calcular utilización basada en peso y contenedores
     */
    private function calculateUtilization()
    {
        if (!isset($this->attributes['cargo_capacity_tons']) || !isset($this->attributes['container_capacity'])) {
            return;
        }

        $weightUtilization = 0;
        $containerUtilization = 0;

        // Utilización por peso
        if ($this->attributes['cargo_capacity_tons'] > 0) {
            $weightUtilization = ($this->attributes['cargo_weight_loaded'] ?? 0) / $this->attributes['cargo_capacity_tons'] * 100;
        }

        // Utilización por contenedores
        if ($this->attributes['container_capacity'] > 0) {
            $containerUtilization = ($this->attributes['containers_loaded'] ?? 0) / $this->attributes['container_capacity'] * 100;
        }

        // Tomar el mayor de los dos
        $this->attributes['utilization_percentage'] = max($weightUtilization, $containerUtilization);
    }

    // ================================
    // BUSINESS METHODS
    // ================================

    /**
     * Verificar si el envío está listo para partir
     */
    public function isReadyForDeparture(): bool
    {
        return $this->safety_approved && 
               $this->customs_cleared && 
               $this->documentation_complete && 
               $this->active;
    }

    /**
     * Verificar si está en tránsito
     */
    public function isInTransit(): bool
    {
        return in_array($this->status, ['departed', 'in_transit']) && 
               $this->departure_time !== null && 
               $this->arrival_time === null;
    }

    /**
     * Verificar si ha llegado
     */
    public function hasArrived(): bool
    {
        return $this->status === 'arrived' && $this->arrival_time !== null;
    }

    /**
     * Calcular duración del viaje en horas
     */
    public function getTravelDurationHours(): ?float
    {
        if (!$this->departure_time || !$this->arrival_time) {
            return null;
        }

        return $this->departure_time->diffInHours($this->arrival_time);
    }

    /**
     * Calcular tiempo de carga en horas
     */
    public function getLoadingDurationHours(): ?float
    {
        if (!$this->loading_start_time || !$this->loading_end_time) {
            return null;
        }

        return $this->loading_start_time->diffInHours($this->loading_end_time);
    }

    /**
     * Calcular tiempo de descarga en horas
     */
    public function getDischargeDurationHours(): ?float
    {
        if (!$this->discharge_start_time || !$this->discharge_end_time) {
            return null;
        }

        return $this->discharge_start_time->diffInHours($this->discharge_end_time);
    }

    /**
     * Obtener el peso disponible restante
     */
    public function getRemainingCapacityTons(): float
    {
        return max(0, $this->cargo_capacity_tons - $this->cargo_weight_loaded);
    }

    /**
     * Obtener contenedores disponibles restantes
     */
    public function getRemainingContainerCapacity(): int
    {
        return max(0, $this->container_capacity - $this->containers_loaded);
    }

    /**
     * Verificar si puede cargar más mercadería
     */
    public function canLoadMore(): bool
    {
        return $this->getRemainingCapacityTons() > 0 || $this->getRemainingContainerCapacity() > 0;
    }

    /**
     * Marcar como que requiere atención
     */
    public function markRequiresAttention(string $reason = null): void
    {
        $this->update([
            'requires_attention' => true,
            'handling_notes' => $reason ? ($this->handling_notes ? $this->handling_notes . "\n" . $reason : $reason) : $this->handling_notes
        ]);
    }

    /**
     * Registrar demora
     */
    public function recordDelay(int $minutes, string $reason): void
    {
        $this->update([
            'has_delays' => true,
            'delay_minutes' => ($this->delay_minutes ?? 0) + $minutes,
            'delay_reason' => $reason,
            'requires_attention' => true
        ]);
    }

    /**
     * Obtener estado en texto legible
     */
    public function getStatusText(): string
    {
        $statuses = [
            'planning' => 'En Planificación',
            'approved' => 'Aprobado',
            'loading' => 'Cargando',
            'ready' => 'Listo',
            'departed' => 'Partió',
            'in_transit' => 'En Tránsito',
            'arrived' => 'Arribó',
            'discharging' => 'Descargando',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'delayed' => 'Demorado'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Obtener rol de embarcación en texto
     */
    public function getVesselRoleText(): string
    {
        $roles = [
            'single' => 'Embarcación Única',
            'lead' => 'Líder (Remolcador/Empujador)',
            'towed' => 'Remolcada',
            'pushed' => 'Empujada',
            'escort' => 'Escolta'
        ];

        return $roles[$this->vessel_role] ?? $this->vessel_role;
    }
}