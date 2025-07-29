<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Shipment Model
 * 
 * Modelo para envíos individuales dentro de un viaje.
 * Cada embarcación en un convoy es un shipment.
 * Un viaje puede tener 1 shipment (barco único) o varios (convoy).
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * Basado en datos reales del sistema:
 * - Manifiestos PARANA con 253 registros, 73 columnas
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Contenedores: 40HC, 20GP, múltiples tipos
 * - Rutas fluviales AR/PY con terminales específicos
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
     * UNIFICADO - Coherente con migración y seeder
     */
    protected $fillable = [
        // Identification and relationships
        'voyage_id',
        'vessel_id',
        'captain_id',
        'shipment_number',
        'sequence_in_voyage',
        
        // Convoy configuration
        'vessel_role',
        'convoy_position',
        'is_lead_vessel',
        
        // Cargo capacity and loading
        'cargo_capacity_tons',
        'container_capacity',
        'cargo_weight_loaded',
        'containers_loaded',
        'utilization_percentage',
        
        // Status
        'status',
        
        // Operational times - NOMBRES UNIFICADOS (descriptivos)
        'loading_start_time',
        'loading_end_time',
        'departure_time',
        'arrival_time',
        'discharge_start_time',
        'discharge_end_time',
        
        // Position and tracking
        'current_latitude',
        'current_longitude',
        'position_updated_at',
        'distance_from_lead',
        
        // Operational data
        'fuel_consumption',
        'average_speed',
        'cargo_manifest',
        'bills_of_lading_count',
        
        // Safety and compliance - UNIFICADO CON SEEDER
        'safety_approved',
        'customs_cleared',
        'documentation_complete',
        'cargo_inspected',
        'has_dangerous_cargo',
        'safety_notes',
        
        // Communication
        'radio_frequency',
        'last_communication',
        'communication_log',
        
        // Performance metrics
        'loading_efficiency',
        'loading_time_minutes',
        'discharge_time_minutes',
        'on_schedule',
        
        // Financial data
        'freight_cost',
        'fuel_cost',
        'port_charges',
        'total_cost',
        
        // Documents
        'required_documents',
        'uploaded_documents',
        
        // Notes - UNIFICADO CON MODELO Y SEEDER
        'operational_notes',
        'cargo_notes',
        'incidents',
        'special_instructions',
        'handling_notes',
        'delay_reason',
        'delay_minutes',
        
        // Status flags
        'active',
        'requires_attention',
        'has_delays',
        
        // Audit trail
        'created_date',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     * UNIFICADO - Coherente con migración
     */
    protected $casts = [
        // Operational times - NOMBRES UNIFICADOS
        'loading_start_time' => 'datetime',
        'loading_end_time' => 'datetime',
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'discharge_start_time' => 'datetime',
        'discharge_end_time' => 'datetime',
        'position_updated_at' => 'datetime',
        'last_communication' => 'datetime',
        'created_date' => 'datetime',
        
        // Boolean fields
        'is_lead_vessel' => 'boolean',
        'safety_approved' => 'boolean',
        'customs_cleared' => 'boolean',
        'documentation_complete' => 'boolean',
        'cargo_inspected' => 'boolean',
        'has_dangerous_cargo' => 'boolean',
        'on_schedule' => 'boolean',
        'active' => 'boolean',
        'requires_attention' => 'boolean',
        'has_delays' => 'boolean',
        
        // Decimal fields
        'cargo_capacity_tons' => 'decimal:2',
        'cargo_weight_loaded' => 'decimal:2',
        'utilization_percentage' => 'decimal:2',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'distance_from_lead' => 'decimal:2',
        'fuel_consumption' => 'decimal:2',
        'average_speed' => 'decimal:2',
        'loading_efficiency' => 'decimal:2',
        'freight_cost' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'port_charges' => 'decimal:2',
        'total_cost' => 'decimal:2',
        
        // JSON fields
        'cargo_manifest' => 'array',
        'communication_log' => 'array',
        'required_documents' => 'array',
        'uploaded_documents' => 'array',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calcular utilization_percentage cuando se actualiza la carga
        static::saving(function ($shipment) {
            $shipment->calculateUtilization();
        });

        // Actualizar estadísticas del viaje cuando se modifica un shipment
        static::saved(function ($shipment) {
            $shipment->voyage->recalculateShipmentStats();
        });
        
        static::deleted(function ($shipment) {
            $shipment->voyage->recalculateShipmentStats();
        });
    }

    //
    // === RELATIONSHIPS ===
    //

    /**
     * Viaje al que pertenece este envío
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    /**
     * Embarcación de este envío
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    /**
     * Capitán de esta embarcación específica
     */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(Captain::class);
    }

    /**
     * Usuario que creó el envío
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Conocimientos de embarque específicos de este envío
     */
    public function billsOfLading(): HasMany
    {
        return $this->hasMany(BillOfLading::class);
    }

    /**
     * Ítems de mercadería específicos de este envío
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * Contenedores asignados a este envío
     */
    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    //
    // === SCOPES ===
    //

    /**
     * Solo envíos activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Solo embarcaciones líderes
     */
    public function scopeLeadVessels($query)
    {
        return $query->where('is_lead_vessel', true);
    }

    /**
     * Filtrar por rol de embarcación
     */
    public function scopeByVesselRole($query, $role)
    {
        return $query->where('vessel_role', $role);
    }

    /**
     * Solo envíos que requieren atención
     */
    public function scopeRequiringAttention($query)
    {
        return $query->where('requires_attention', true);
    }

    /**
     * Solo envíos con retrasos
     */
    public function scopeWithDelays($query)
    {
        return $query->where('has_delays', true);
    }

    /**
     * Envíos con todas las aprobaciones
     */
    public function scopeFullyApproved($query)
    {
        return $query->where('safety_approved', true)
                    ->where('customs_cleared', true)
                    ->where('documentation_complete', true)
                    ->where('cargo_inspected', true);
    }

    /**
     * Filtrar por viaje
     */
    public function scopeByVoyage($query, $voyageId)
    {
        return $query->where('voyage_id', $voyageId);
    }

    /**
     * Ordenar por secuencia en el viaje
     */
    public function scopeBySequence($query)
    {
        return $query->orderBy('sequence_in_voyage');
    }

    /**
     * Envíos en proceso de carga
     */
    public function scopeLoading($query)
    {
        return $query->where('status', 'loading')
                    ->whereNotNull('loading_start_time')
                    ->whereNull('loading_end_time');
    }

    /**
     * Envíos en proceso de descarga
     */
    public function scopeDischarging($query)
    {
        return $query->where('status', 'discharging')
                    ->whereNotNull('discharge_start_time')
                    ->whereNull('discharge_end_time');
    }

    //
    // === ACCESSORS & MUTATORS ===
    //

    /**
     * Obtener descripción del rol en español
     */
    public function getVesselRoleDescriptionAttribute(): string
    {
        return match($this->vessel_role) {
            'single' => 'Embarcación Única',
            'lead' => 'Líder (Remolcador/Empujador)',
            'towed' => 'Remolcada',
            'pushed' => 'Empujada',
            'escort' => 'Escolta',
            default => 'Indefinido'
        };
    }

    /**
     * Verificar si está en convoy
     */
    public function getIsInConvoyAttribute(): bool
    {
        return $this->vessel_role !== 'single' && $this->convoy_position !== null;
    }

    /**
     * Calcular duración de carga
     */
    public function getLoadingDurationAttribute(): ?int
    {
        if (!$this->loading_start_time) {
            return null;
        }
        
        $endTime = $this->loading_end_time ?? now();
        return $this->loading_start_time->diffInMinutes($endTime);
    }

    /**
     * Calcular duración de descarga
     */
    public function getDischargeDurationAttribute(): ?int
    {
        if (!$this->discharge_start_time) {
            return null;
        }
        
        $endTime = $this->discharge_end_time ?? now();
        return $this->discharge_start_time->diffInMinutes($endTime);
    }

    /**
     * Verificar si está retrasado
     */
    public function getIsDelayedAttribute(): bool
    {
        return $this->has_delays || ($this->delay_minutes && $this->delay_minutes > 0);
    }

    /**
     * Obtener todas las aprobaciones pendientes
     */
    public function getPendingApprovalsAttribute(): array
    {
        $pending = [];
        
        if (!$this->safety_approved) {
            $pending[] = 'safety';
        }
        
        if (!$this->customs_cleared) {
            $pending[] = 'customs';
        }
        
        if (!$this->documentation_complete) {
            $pending[] = 'documentation';
        }
        
        if (!$this->cargo_inspected) {
            $pending[] = 'cargo_inspection';
        }
        
        return $pending;
    }

    /**
     * Verificar si puede partir
     */
    public function getCanDepartAttribute(): bool
    {
        return $this->safety_approved && 
               $this->customs_cleared && 
               $this->documentation_complete && 
               $this->cargo_inspected &&
               $this->status === 'ready';
    }

    /**
     * Obtener estado detallado
     */
    public function getDetailedStatusAttribute(): array
    {
        return [
            'status' => $this->status,
            'is_delayed' => $this->is_delayed,
            'delay_minutes' => $this->delay_minutes,
            'requires_attention' => $this->requires_attention,
            'approvals' => [
                'safety' => $this->safety_approved,
                'customs' => $this->customs_cleared,
                'documentation' => $this->documentation_complete,
                'cargo_inspection' => $this->cargo_inspected,
            ],
            'loading_progress' => $this->getLoadingProgressStatus(),
            'can_depart' => $this->can_depart,
        ];
    }

    /**
     * Obtener color de estado para UI
     */
    public function getStatusColorAttribute(): string
    {
        if ($this->requires_attention) {
            return 'red';
        }
        
        return match($this->status) {
            'planning' => 'blue',
            'loading' => 'yellow',
            'ready' => 'green',
            'departed' => 'orange',
            'in_transit' => 'purple',
            'arrived' => 'indigo',
            'discharging' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    //
    // === METHODS ===
    //

    /**
     * Calcular porcentaje de utilización
     */
    private function calculateUtilization(): void
    {
        if ($this->cargo_capacity_tons > 0) {
            $this->utilization_percentage = min(100, ($this->cargo_weight_loaded / $this->cargo_capacity_tons) * 100);
        } else {
            $this->utilization_percentage = 0;
        }
    }

    /**
     * Iniciar proceso de carga
     */
    public function startLoading(): void
    {
        $this->update([
            'status' => 'loading',
            'loading_start_time' => now(),
            'loading_end_time' => null,
        ]);
    }

    /**
     * Finalizar proceso de carga
     */
    public function finishLoading(): void
    {
        $this->update([
            'status' => 'ready',
            'loading_end_time' => now(),
        ]);
    }

    /**
     * Iniciar proceso de descarga
     */
    public function startDischarging(): void
    {
        $this->update([
            'status' => 'discharging',
            'discharge_start_time' => now(),
            'discharge_end_time' => null,
        ]);
    }

    /**
     * Finalizar proceso de descarga
     */
    public function finishDischarging(): void
    {
        $this->update([
            'status' => 'completed',
            'discharge_end_time' => now(),
        ]);
    }

    /**
     * Marcar salida
     */
    public function markDeparture(): void
    {
        $this->update([
            'status' => 'departed',
            'departure_time' => now(),
        ]);
    }

    /**
     * Marcar llegada
     */
    public function markArrival(): void
    {
        $this->update([
            'status' => 'arrived',
            'arrival_time' => now(),
        ]);
        
        // Verificar si llegó tarde comparado con el viaje
        if ($this->voyage->estimated_arrival_date && now() > $this->voyage->estimated_arrival_date) {
            $delayMinutes = $this->voyage->estimated_arrival_date->diffInMinutes(now());
            $this->reportDelay($delayMinutes, 'Arribó tarde según cronograma del viaje');
        }
    }

    /**
     * Reportar retraso
     */
    public function reportDelay(int $minutes, ?string $reason = null): void
    {
        $this->update([
            'has_delays' => true,
            'delay_minutes' => ($this->delay_minutes ?? 0) + $minutes,
            'delay_reason' => $reason,
            'requires_attention' => true,
        ]);
    }

    /**
     * Aprobar aspecto específico
     */
    public function approve(string $aspect): void
    {
        $validAspects = ['safety', 'customs', 'documentation', 'cargo'];
        
        if (!in_array($aspect, $validAspects)) {
            throw new \InvalidArgumentException("Aspecto inválido: {$aspect}");
        }
        
        $field = match($aspect) {
            'safety' => 'safety_approved',
            'customs' => 'customs_cleared',
            'documentation' => 'documentation_complete',
            'cargo' => 'cargo_inspected',
        };
        
        $this->update([$field => true]);
        
        // Si todas las aprobaciones están completas, verificar si puede cambiar estado
        if (empty($this->pending_approvals) && $this->status === 'planning') {
            $this->update(['status' => 'approved']);
        }
    }

    /**
     * Obtener progreso de carga
     */
    private function getLoadingProgressStatus(): array
    {
        if (!$this->loading_start_time) {
            return ['status' => 'not_started', 'percentage' => 0];
        }
        
        if (!$this->loading_end_time) {
            // Estimar progreso basado en utilización actual
            return [
                'status' => 'in_progress',
                'percentage' => min(100, $this->utilization_percentage),
                'duration_minutes' => $this->loading_duration,
            ];
        }
        
        return [
            'status' => 'completed',
            'percentage' => 100,
            'duration_minutes' => $this->loading_duration,
        ];
    }

    /**
     * Cambiar estado con validaciones
     */
    public function changeStatus(string $newStatus, ?string $reason = null): void
    {
        $oldStatus = $this->status;
        
        // Validaciones de transición de estado
        $this->validateStatusTransition($oldStatus, $newStatus);
        
        $this->update([
            'status' => $newStatus,
        ]);
        
        // Acciones específicas por estado
        match($newStatus) {
            'loading' => $this->handleLoadingStart(),
            'ready' => $this->handleReadyState(),
            'departed' => $this->handleDeparture(),
            'arrived' => $this->handleArrival(),
            'completed' => $this->handleCompletion(),
            default => null,
        };
        
        // Log del cambio
        $this->logStatusChange($oldStatus, $newStatus, $reason);
    }

    /**
     * Validar transición de estado
     */
    private function validateStatusTransition(string $from, string $to): void
    {
        $validTransitions = [
            'planning' => ['loading', 'approved', 'cancelled'],
            'approved' => ['loading', 'ready', 'cancelled'],
            'loading' => ['ready', 'cancelled'],
            'ready' => ['departed', 'loading', 'cancelled'],
            'departed' => ['in_transit', 'arrived'],
            'in_transit' => ['arrived'],
            'arrived' => ['discharging'],
            'discharging' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
        
        if (!isset($validTransitions[$from]) || !in_array($to, $validTransitions[$from])) {
            throw new \InvalidArgumentException("Transición inválida de {$from} a {$to}");
        }
    }

    /**
     * Manejar inicio de carga
     */
    private function handleLoadingStart(): void
    {
        if (!$this->loading_start_time) {
            $this->update(['loading_start_time' => now()]);
        }
    }

    /**
     * Manejar estado listo
     */
    private function handleReadyState(): void
    {
        if (!$this->loading_end_time) {
            $this->update(['loading_end_time' => now()]);
        }
    }

    /**
     * Manejar salida
     */
    private function handleDeparture(): void
    {
        if (!$this->departure_time) {
            $this->update(['departure_time' => now()]);
        }
    }

    /**
     * Manejar llegada
     */
    private function handleArrival(): void
    {
        if (!$this->arrival_time) {
            $this->update(['arrival_time' => now()]);
        }
    }

    /**
     * Manejar finalización
     */
    private function handleCompletion(): void
    {
        if (!$this->discharge_end_time) {
            $this->update(['discharge_end_time' => now()]);
        }
        
        // Actualizar estadísticas de la embarcación
        $this->vessel->updateAfterShipment($this);
    }

    /**
     * Obtener otros envíos del mismo convoy
     */
    public function getConvoyShipments(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->is_in_convoy) {
            return collect([$this]);
        }
        
        return $this->voyage->shipments()
                          ->where('id', '!=', $this->id)
                          ->orderBy('convoy_position')
                          ->get();
    }

    /**
     * Verificar si es compatible con otro envío para formar convoy
     */
    public function isCompatibleForConvoy(Shipment $other): bool
    {
        return $this->voyage_id === $other->voyage_id &&
               $this->status === $other->status &&
               $this->departure_time && $other->departure_time &&
               abs($this->departure_time->diffInMinutes($other->departure_time)) <= 30;
    }

    /**
     * Generar número de envío automático
     */
    public static function generateShipmentNumber(Voyage $voyage, int $sequence): string
    {
        return $voyage->voyage_number . '-' . str_pad($sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener documentos específicos requeridos
     */
    public function getRequiredDocuments(): array
    {
        $documents = [
            'loading_manifest' => 'Manifiesto de Carga',
            'vessel_certificate' => 'Certificado de Embarcación',
        ];
        
        if ($this->is_lead_vessel) {
            $documents['convoy_authorization'] = 'Autorización de Convoy';
            $documents['navigation_plan'] = 'Plan de Navegación';
        }
        
        if ($this->hazardous_cargo ?? false) {
            $documents['hazmat_declaration'] = 'Declaración de Mercancías Peligrosas';
        }
        
        return $documents;
    }

    /**
     * Log de cambio de estado
     */
    private function logStatusChange(string $oldStatus, string $newStatus, ?string $reason): void
    {
        \Log::info("Shipment {$this->shipment_number} status changed from {$oldStatus} to {$newStatus}", [
            'shipment_id' => $this->id,
            'voyage_id' => $this->voyage_id,
            'vessel_id' => $this->vessel_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'timestamp' => now(),
        ]);
    }

    /**
     * Obtener métricas de rendimiento
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'capacity_utilization' => $this->utilization_percentage,
            'loading_efficiency' => $this->calculateLoadingEfficiency(),
            'schedule_adherence' => $this->calculateScheduleAdherence(),
            'approval_completeness' => $this->calculateApprovalCompleteness(),
            'delay_impact' => $this->calculateDelayImpact(),
        ];
    }

    /**
     * Calcular eficiencia de carga
     */
    private function calculateLoadingEfficiency(): ?float
    {
        if (!$this->loading_duration || $this->loading_duration <= 0) {
            return null;
        }
        
        // Tiempo estándar basado en toneladas (ej: 2 horas por 100 toneladas)
        $standardTime = ($this->cargo_capacity_tons / 100) * 120; // minutos
        
        return min(100, ($standardTime / $this->loading_duration) * 100);
    }

    /**
     * Calcular adherencia al cronograma
     */
    private function calculateScheduleAdherence(): ?float
    {
        if (!$this->departure_time || !$this->voyage->departure_date) {
            return null;
        }
        
        $scheduledDeparture = $this->voyage->departure_date;
        $actualDeparture = $this->departure_time;
        
        $diffMinutes = abs($scheduledDeparture->diffInMinutes($actualDeparture));
        
        // Máximo 100% si está dentro de 30 minutos, decrece linealmente
        return max(0, 100 - ($diffMinutes / 30 * 100));
    }

    /**
     * Calcular completitud de aprobaciones
     */
    private function calculateApprovalCompleteness(): float
    {
        $totalApprovals = 4; // safety, customs, documentation, cargo
        $completedApprovals = 4 - count($this->pending_approvals);
        
        return ($completedApprovals / $totalApprovals) * 100;
    }

    /**
     * Calcular impacto de retrasos
     */
    private function calculateDelayImpact(): float
    {
        if (!$this->delay_minutes || $this->delay_minutes <= 0) {
            return 0;
        }
        
        // Impacto basado en minutos de retraso (máximo 100 para 6+ horas)
        return min(100, ($this->delay_minutes / 360) * 100);
    }

    /**
     * Recalcular estadísticas de items del shipment
     * NUEVO: Método para mantener consistencia
     */
    public function recalculateItemStats(): void
    {
        $items = $this->shipmentItems;
        
        if ($items->isEmpty()) {
            $this->updateItemStatistics(0, 0, 0, 0, false, false);
            return;
        }

        // Agregaciones básicas
        $totalWeight = $items->sum('gross_weight_kg');
        $totalVolume = $items->sum('volume_m3');
        $totalValue = $items->sum('declared_value');
        $totalPackages = $items->sum('package_quantity');
        
        // Características especiales
        $hasDangerousGoods = $items->where('is_dangerous_goods', true)->isNotEmpty();
        $requiresRefrigeration = $items->where('requires_refrigeration', true)->isNotEmpty();
        
        $this->updateItemStatistics(
            $totalWeight,
            $totalVolume, 
            $totalValue,
            $totalPackages,
            $hasDangerousGoods,
            $requiresRefrigeration
        );
    }

    /**
     * Actualizar estadísticas calculadas
     */
    private function updateItemStatistics(
        float $totalWeight,
        float $totalVolume,
        float $totalValue,
        int $totalPackages,
        bool $hasDangerousGoods,
        bool $requiresRefrigeration
    ): void {
        $this->update([
            'cargo_weight_loaded' => $totalWeight,
            'utilization_percentage' => $this->calculateWeightUtilization($totalWeight),
            'has_dangerous_cargo' => $hasDangerousGoods,
            // Actualizar otros campos relacionados con la carga
        ]);
    }

    /**
     * Calcular utilización por peso
     */
    private function calculateWeightUtilization(float $loadedWeight): float
    {
        if ($this->cargo_capacity_tons <= 0) {
            return 0;
        }
        
        return min(100, ($loadedWeight / ($this->cargo_capacity_tons * 1000)) * 100);
    }

    /**
     * Obtener resumen de items
     */
    public function getItemsSummary(): array
    {
        $items = $this->shipmentItems;
        
        return [
            'total_items' => $items->count(),
            'total_lines' => $items->max('line_number') ?? 0,
            'total_packages' => $items->sum('package_quantity'),
            'total_weight_kg' => $items->sum('gross_weight_kg'),
            'total_volume_m3' => $items->sum('volume_m3'),
            'total_value_usd' => $items->sum('declared_value'),
            'dangerous_goods_count' => $items->where('is_dangerous_goods', true)->count(),
            'perishable_count' => $items->where('is_perishable', true)->count(),
            'refrigerated_count' => $items->where('requires_refrigeration', true)->count(),
            'items_with_discrepancies' => $items->where('has_discrepancies', true)->count(),
            'items_requiring_review' => $items->where('requires_review', true)->count(),
        ];
    }

    /**
     * Obtener items por estado
     */
    public function getItemsByStatus(): array
    {
        return $this->shipmentItems()
                   ->selectRaw('status, COUNT(*) as count')
                   ->groupBy('status')
                   ->pluck('count', 'status')
                   ->toArray();
    }

    /**
     * Obtener items por tipo de carga
     */
    public function getItemsByCargoType(): array
    {
        return $this->shipmentItems()
                   ->with('cargoType')
                   ->get()
                   ->groupBy('cargoType.name')
                   ->map(function ($items) {
                       return [
                           'count' => $items->count(),
                           'total_weight' => $items->sum('gross_weight_kg'),
                           'total_packages' => $items->sum('package_quantity'),
                       ];
                   })
                   ->toArray();
    }

    /**
     * Verificar si todos los items están validados
     */
    public function areAllItemsValidated(): bool
    {
        return $this->shipmentItems()
                   ->whereNotIn('status', ['validated', 'submitted', 'accepted'])
                   ->doesntExist();
    }

    /**
     * Verificar si hay items con discrepancias pendientes
     */
    public function hasPendingDiscrepancies(): bool
    {
        return $this->shipmentItems()
                   ->where('has_discrepancies', true)
                   ->where('requires_review', true)
                   ->exists();
    }

    /**
     * Obtener items que requieren atención
     */
    public function getItemsRequiringAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->shipmentItems()
                   ->where(function ($query) {
                       $query->where('has_discrepancies', true)
                             ->orWhere('requires_review', true)
                             ->orWhere('status', 'rejected');
                   })
                   ->orderBy('line_number')
                   ->get();
    }

    /**
     * Validar todos los items del shipment
     */
    public function validateAllItems(): array
    {
        $results = [
            'validated' => 0,
            'errors' => [],
        ];

        $this->shipmentItems()->where('status', 'draft')->each(function ($item) use (&$results) {
            try {
                $item->validate();
                $results['validated']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Item línea {$item->line_number}: " . $e->getMessage();
            }
        });

        return $results;
    }

    /**
     * Generar manifiesto de carga desde items
     */
    public function generateCargoManifest(): array
    {
        $items = $this->shipmentItems()->with(['cargoType', 'packagingType'])->get();
        
        $manifest = [
            'shipment_number' => $this->shipment_number,
            'vessel_name' => $this->vessel->name ?? 'N/A',
            'generated_at' => now()->toISOString(),
            'summary' => $this->getItemsSummary(),
            'items' => [],
        ];

        foreach ($items as $item) {
            $manifest['items'][] = [
                'line_number' => $item->line_number,
                'item_reference' => $item->full_reference,
                'description' => $item->item_description,
                'commodity_code' => $item->commodity_code,
                'cargo_type' => $item->cargoType->name ?? 'N/A',
                'packaging_type' => $item->packagingType->name ?? 'N/A',
                'packages' => $item->package_quantity,
                'gross_weight_kg' => $item->gross_weight_kg,
                'net_weight_kg' => $item->effective_net_weight,
                'volume_m3' => $item->volume_m3,
                'declared_value' => $item->declared_value,
                'currency' => $item->currency_code,
                'special_requirements' => $item->special_requirements,
                'status' => $item->status,
            ];
        }

        return $manifest;
    }

    /**
     * Obtener items por país de origen
     */
    public function getItemsByCountryOfOrigin(): array
    {
        return $this->shipmentItems()
                   ->whereNotNull('country_of_origin')
                   ->selectRaw('country_of_origin, COUNT(*) as count, SUM(gross_weight_kg) as total_weight')
                   ->groupBy('country_of_origin')
                   ->get()
                   ->mapWithKeys(function ($item) {
                       return [$item->country_of_origin => [
                           'count' => $item->count,
                           'total_weight' => $item->total_weight,
                       ]];
                   })
                   ->toArray();
    }

    /**
     * Verificar capacidad de carga vs items
     */
    public function checkCapacityVsItems(): array
    {
        $summary = $this->getItemsSummary();
        $capacityTons = $this->cargo_capacity_tons;
        $loadedTons = $summary['total_weight_kg'] / 1000;
        
        return [
            'capacity_tons' => $capacityTons,
            'loaded_tons' => $loadedTons,
            'available_tons' => $capacityTons - $loadedTons,
            'utilization_percentage' => $capacityTons > 0 ? ($loadedTons / $capacityTons) * 100 : 0,
            'is_overloaded' => $loadedTons > $capacityTons,
            'weight_status' => $loadedTons > $capacityTons ? 'overloaded' : 
                              ($loadedTons > $capacityTons * 0.9 ? 'near_capacity' : 'within_limits'),
        ];
    }

    /**
     * Obtener estadísticas de temperaturas requeridas
     */
    public function getTemperatureRequirements(): array
    {
        $refrigeratedItems = $this->shipmentItems()
                                 ->where('requires_refrigeration', true)
                                 ->whereNotNull('temperature_min')
                                 ->whereNotNull('temperature_max')
                                 ->get();

        if ($refrigeratedItems->isEmpty()) {
            return ['requires_refrigeration' => false];
        }

        return [
            'requires_refrigeration' => true,
            'min_temperature' => $refrigeratedItems->min('temperature_min'),
            'max_temperature' => $refrigeratedItems->max('temperature_max'),
            'optimal_range' => [
                'min' => $refrigeratedItems->max('temperature_min'), // El mínimo más alto
                'max' => $refrigeratedItems->min('temperature_max'), // El máximo más bajo
            ],
            'refrigerated_items_count' => $refrigeratedItems->count(),
        ];
    }
}