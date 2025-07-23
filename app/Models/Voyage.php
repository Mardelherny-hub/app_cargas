<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Voyage extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

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
        'customs_clearance_date',
        'cargo_loading_start',
        'cargo_loading_end',
        'cargo_discharge_start',
        'cargo_discharge_end',
        'voyage_type',
        'cargo_type',
        'is_consolidated',
        'has_transshipment',
        'requires_pilot',
        'status',
        'total_containers',
        'total_cargo_weight',
        'total_cargo_volume',
        'total_bills_of_lading',
        'total_clients',
        'argentina_voyage_id',
        'paraguay_voyage_id',
        'argentina_status',
        'paraguay_status',
        'argentina_sent_at',
        'paraguay_sent_at',
        'estimated_freight_cost',
        'actual_freight_cost',
        'fuel_cost',
        'port_charges',
        'total_voyage_cost',
        'currency_code',
        'weather_conditions',
        'river_conditions',
        'voyage_notes',
        'delays_explanation',
        'required_documents',
        'uploaded_documents',
        'customs_approved',
        'port_authority_approved',
        'all_documents_ready',
        'emergency_contacts',
        'safety_equipment',
        'dangerous_cargo',
        'safety_notes',
        'distance_nautical_miles',
        'average_speed_knots',
        'transit_time_hours',
        'fuel_consumption',
        'fuel_efficiency',
        'communication_frequency',
        'reporting_schedule',
        'last_position_report',
        'active',
        'archived',
        'requires_follow_up',
        'has_incidents',
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',
    ];

    protected $casts = [
        'departure_date' => 'datetime',
        'estimated_arrival_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
        'customs_clearance_date' => 'datetime',
        'cargo_loading_start' => 'datetime',
        'cargo_loading_end' => 'datetime',
        'cargo_discharge_start' => 'datetime',
        'cargo_discharge_end' => 'datetime',
        'argentina_sent_at' => 'datetime',
        'paraguay_sent_at' => 'datetime',
        'last_position_report' => 'datetime',
        'created_date' => 'datetime',
        'last_updated_date' => 'datetime',
        'is_consolidated' => 'boolean',
        'has_transshipment' => 'boolean',
        'requires_pilot' => 'boolean',
        'customs_approved' => 'boolean',
        'port_authority_approved' => 'boolean',
        'all_documents_ready' => 'boolean',
        'dangerous_cargo' => 'boolean',
        'active' => 'boolean',
        'archived' => 'boolean',
        'requires_follow_up' => 'boolean',
        'has_incidents' => 'boolean',
        'weather_conditions' => 'array',
        'river_conditions' => 'array',
        'required_documents' => 'array',
        'uploaded_documents' => 'array',
        'emergency_contacts' => 'array',
        'safety_equipment' => 'array',
        'reporting_schedule' => 'array',
        'estimated_freight_cost' => 'decimal:2',
        'actual_freight_cost' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'port_charges' => 'decimal:2',
        'total_voyage_cost' => 'decimal:2',
        'total_cargo_weight' => 'decimal:2',
        'total_cargo_volume' => 'decimal:2',
        'distance_nautical_miles' => 'decimal:2',
        'average_speed_knots' => 'decimal:2',
        'fuel_consumption' => 'decimal:2',
        'fuel_efficiency' => 'decimal:2',
    ];

    // Enums
    public const VOYAGE_TYPES = [
        'single_vessel' => 'Embarcación Única',
        'convoy' => 'Convoy',
        'fleet' => 'Flota Coordinada'
    ];

    public const CARGO_TYPES = [
        'export' => 'Exportación',
        'import' => 'Importación',
        'transit' => 'Tránsito',
        'transshipment' => 'Transbordo',
        'cabotage' => 'Cabotaje'
    ];

    public const STATUS_OPTIONS = [
        'planning' => 'En Planificación',
        'approved' => 'Aprobado',
        'in_transit' => 'En Tránsito',
        'at_destination' => 'En Destino',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        'delayed' => 'Demorado'
    ];

    public const WEBSERVICE_STATUS = [
        'pending' => 'Pendiente',
        'sent' => 'Enviado',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'error' => 'Error'
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leadVessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class, 'lead_vessel_id');
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(Captain::class);
    }

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_id');
    }

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function transshipmentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'transshipment_port_id');
    }

    public function originCustoms(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'origin_customs_id');
    }

    public function destinationCustoms(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'destination_customs_id');
    }

    public function transshipmentCustoms(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'transshipment_customs_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastUpdatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    // Future relationships (when other tables are created)
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function billsOfLading(): HasMany
    {
        return $this->hasMany(BillOfLading::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('archived', false);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeRequiringFollowUp($query)
    {
        return $query->where('requires_follow_up', true);
    }

    public function scopeByRoute($query, $originPortId, $destinationPortId)
    {
        return $query->where('origin_port_id', $originPortId)
                    ->where('destination_port_id', $destinationPortId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('departure_date', [$startDate, $endDate]);
    }

    // Accessors
    public function getVoyageTypeNameAttribute()
    {
        return self::VOYAGE_TYPES[$this->voyage_type] ?? $this->voyage_type;
    }

    public function getCargoTypeNameAttribute()
    {
        return self::CARGO_TYPES[$this->cargo_type] ?? $this->cargo_type;
    }

    public function getStatusNameAttribute()
    {
        return self::STATUS_OPTIONS[$this->status] ?? $this->status;
    }

    public function getIsDelayedAttribute()
    {
        if (!$this->estimated_arrival_date) {
            return false;
        }

        if ($this->actual_arrival_date) {
            return $this->actual_arrival_date > $this->estimated_arrival_date;
        }

        return now() > $this->estimated_arrival_date && !in_array($this->status, ['completed', 'at_destination']);
    }

    public function getProgressPercentageAttribute()
    {
        if (!$this->departure_date || !$this->estimated_arrival_date) {
            return 0;
        }

        $totalDuration = $this->departure_date->diffInMinutes($this->estimated_arrival_date);
        $elapsed = $this->departure_date->diffInMinutes(now());

        if ($elapsed <= 0) {
            return 0;
        }

        if ($elapsed >= $totalDuration) {
            return 100;
        }

        return round(($elapsed / $totalDuration) * 100, 2);
    }

    public function getTotalTransitTimeAttribute()
    {
        if (!$this->departure_date || !$this->actual_arrival_date) {
            return null;
        }

        return $this->departure_date->diffInHours($this->actual_arrival_date);
    }

    // Mutators
    public function setVoyageNumberAttribute($value)
    {
        $this->attributes['voyage_number'] = strtoupper($value);
    }

    public function setInternalReferenceAttribute($value)
    {
        $this->attributes['internal_reference'] = $value ? strtoupper($value) : null;
    }

    // Static methods
    public static function generateVoyageNumber($companyId, $departureDate = null)
    {
        $date = $departureDate ? carbon($departureDate) : now();
        $company = Company::find($companyId);
        
        $prefix = $company?->code ?? 'VOY';
        $dateStr = $date->format('ymd');
        
        $lastVoyage = self::where('voyage_number', 'like', "{$prefix}-{$dateStr}-%")
                         ->orderBy('voyage_number', 'desc')
                         ->first();
        
        $sequence = 1;
        if ($lastVoyage) {
            $lastSequence = (int) substr($lastVoyage->voyage_number, -3);
            $sequence = $lastSequence + 1;
        }
        
        return sprintf('%s-%s-%03d', $prefix, $dateStr, $sequence);
    }

    // Business logic methods
    public function canBeModified(): bool
    {
        return in_array($this->status, ['planning', 'approved']);
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    public function markAsInTransit(): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $this->update([
            'status' => 'in_transit',
            'departure_date' => $this->departure_date ?? now(),
        ]);

        return true;
    }

    public function markAsCompleted(): bool
    {
        $this->update([
            'status' => 'completed',
            'actual_arrival_date' => $this->actual_arrival_date ?? now(),
        ]);

        return true;
    }

    public function calculateTotalCost(): float
    {
        return collect([
            $this->estimated_freight_cost ?? 0,
            $this->fuel_cost ?? 0,
            $this->port_charges ?? 0,
        ])->sum();
    }

    public function updateTotalCost(): void
    {
        $this->update([
            'total_voyage_cost' => $this->calculateTotalCost()
        ]);
    }
}