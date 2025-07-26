<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Schema; 
use Carbon\Carbon;
use App\Models\Company;
use App\Models\WebserviceTransaction;

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
     */
    protected $fillable = [
        'voyage_number',
        'internal_reference',
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
        'departure_date',
        'estimated_arrival_date',
        'actual_arrival_date',
        'customs_clearance_deadline',
        'voyage_type',
        'cargo_type',
        'is_convoy',
        'vessel_count',
        'total_cargo_capacity_tons',
        'total_container_capacity',
        'total_cargo_weight_loaded',
        'total_containers_loaded',
        'capacity_utilization_percentage',
        'status',
        'priority_level',
        'requires_escort',
        'requires_pilot',
        'hazardous_cargo',
        'refrigerated_cargo',
        'oversized_cargo',
        'weather_conditions',
        'route_conditions',
        'special_instructions',
        'operational_notes',
        'estimated_cost',
        'actual_cost',
        'cost_currency',
        'safety_approved',
        'customs_cleared_origin',
        'customs_cleared_destination',
        'documentation_complete',
        'environmental_approved',
        'safety_approval_date',
        'customs_approval_date',
        'environmental_approval_date',
        'active',
        'archived',
        'requires_follow_up',
        'follow_up_reason',
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',
    ];


    /**
     * Transacciones de webservice relacionadas con el viaje
     */
    public function webserviceTransactions(): HasMany
    {
        return $this->hasMany(WebserviceTransaction::class, 'voyage_id');
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
     */
    public function recalculateShipmentStats(): void
    {
        $shipments = $this->shipments;
        
        $this->update([
            'vessel_count' => $shipments->count(),
            'total_cargo_capacity_tons' => $shipments->sum('cargo_capacity_tons'),
            'total_container_capacity' => $shipments->sum('container_capacity'),
            'total_cargo_weight_loaded' => $shipments->sum('cargo_weight_loaded'),
            'total_containers_loaded' => $shipments->sum('containers_loaded'),
            'capacity_utilization_percentage' => $this->calculateUtilizationPercentage(),
        ]);
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
}