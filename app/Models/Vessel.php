<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Modelo Vessel para embarcaciones específicas
 * 
 * @property int $id
 * @property string $name Nombre de la embarcación
 * @property string $registration_number Número de matrícula/registro
 * @property string|null $imo_number Número IMO
 * @property string|null $call_sign Señal de llamada
 * @property string|null $mmsi_number Número MMSI
 * @property int $company_id Empresa propietaria
 * @property int|null $owner_id Propietario específico
 * @property int $vessel_type_id Tipo de embarcación
 * @property int $flag_country_id País de bandera
 * @property int|null $home_port_id Puerto base
 * @property int|null $primary_captain_id Capitán principal
 * @property float $length_meters Longitud en metros
 * @property float $beam_meters Manga en metros
 * @property float $draft_meters Calado en metros
 * @property float $depth_meters Puntal en metros
 * @property float|null $gross_tonnage Tonelaje bruto
 * @property float|null $net_tonnage Tonelaje neto
 * @property float|null $deadweight_tons Peso muerto
 * @property string $operational_status Estado operacional
 * @property bool $active Embarcación activa
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Vessel extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'vessels';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'name',
        'registration_number',
        'imo_number',
        'call_sign',
        'mmsi_number',
        'company_id',
        'owner_id',
        'vessel_type_id',
        'flag_country_id',
        'home_port_id',
        'primary_captain_id',
        'length_meters',
        'beam_meters',
        'draft_meters',
        'depth_meters',
        'gross_tonnage',
        'net_tonnage',
        'deadweight_tons',
        'max_cargo_capacity',
        'operational_status',
        'available_for_charter',
        'charter_rate',
        'current_port_id',
        'next_available_date',
        'next_inspection_due',
        'next_maintenance_due',
        'insurance_expires',
        'safety_certificate_expires',
        'active',
        'verified',
        'inspection_current',
        'insurance_current',
        'certificates_current',
        'created_by_user_id',
        'last_updated_by_user_id',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'next_available_date' => 'datetime',
        'next_inspection_due' => 'date',
        'next_maintenance_due' => 'date',
        'insurance_expires' => 'date',
        'safety_certificate_expires' => 'date',
        'available_for_charter' => 'boolean',
        'active' => 'boolean',
        'verified' => 'boolean',
        'inspection_current' => 'boolean',
        'insurance_current' => 'boolean',
        'certificates_current' => 'boolean',
        'charter_rate' => 'decimal:2',
        'length_meters' => 'decimal:2',
        'beam_meters' => 'decimal:2',
        'draft_meters' => 'decimal:2',
        'depth_meters' => 'decimal:2',
        'gross_tonnage' => 'decimal:2',
        'net_tonnage' => 'decimal:2',
        'deadweight_tons' => 'decimal:2',
        'max_cargo_capacity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Estados operacionales disponibles.
     */
    public const OPERATIONAL_STATUSES = [
        'active' => 'Activa',
        'maintenance' => 'En Mantenimiento',
        'dry_dock' => 'En Dique Seco',
        'charter' => 'En Fletamento',
        'inactive' => 'Inactiva',
        'decommissioned' => 'Fuera de Servicio',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Empresa propietaria de la embarcación.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Propietario específico de la embarcación.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(VesselOwner::class, 'owner_id');
    }

    /**
     * Tipo de embarcación.
     */
    public function vesselType(): BelongsTo
    {
        return $this->belongsTo(VesselType::class);
    }

    /**
     * País de bandera.
     */
    public function flagCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'flag_country_id');
    }

    /**
     * Puerto base.
     */
    public function homePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'home_port_id');
    }

    /**
     * Puerto actual.
     */
    public function currentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'current_port_id');
    }

    /**
     * Capitán principal.
     */
    public function primaryCaptain(): BelongsTo
    {
        return $this->belongsTo(Captain::class, 'primary_captain_id');
    }

    /**
     * Usuario que creó el registro.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Usuario que actualizó el registro por última vez.
     */
    public function lastUpdatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    // =====================================================
    // SCOPES Y CONSULTAS
    // =====================================================

    /**
     * Scope para embarcaciones activas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope para embarcaciones verificadas.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verified', true);
    }

    /**
     * Scope para embarcaciones disponibles para fletamento.
     */
    public function scopeAvailableForCharter(Builder $query): Builder
    {
        return $query->where('available_for_charter', true)
                    ->where('operational_status', 'active');
    }

    /**
     * Scope para filtrar por empresa.
     */
    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para filtrar por propietario.
     */
    public function scopeByOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope para filtrar por tipo de embarcación.
     */
    public function scopeByType(Builder $query, int $vesselTypeId): Builder
    {
        return $query->where('vessel_type_id', $vesselTypeId);
    }

    /**
     * Scope para embarcaciones con certificados al día.
     */
    public function scopeWithCurrentCertificates(Builder $query): Builder
    {
        return $query->where('certificates_current', true)
                    ->where('inspection_current', true)
                    ->where('insurance_current', true);
    }

    /**
     * Scope para embarcaciones que requieren inspección.
     */
    public function scopeRequiringInspection(Builder $query): Builder
    {
        return $query->where('next_inspection_due', '<=', now()->addDays(30))
                    ->orWhere('inspection_current', false);
    }

    /**
     * Scope para embarcaciones que requieren mantenimiento.
     */
    public function scopeRequiringMaintenance(Builder $query): Builder
    {
        return $query->where('next_maintenance_due', '<=', now()->addDays(30));
    }

    // =====================================================
    // MÉTODOS DE UTILIDAD
    // =====================================================

    /**
     * Verificar si la embarcación está disponible.
     */
    public function isAvailable(): bool
    {
        return $this->active && 
               $this->operational_status === 'active' &&
               $this->certificates_current &&
               $this->inspection_current &&
               $this->insurance_current;
    }

    /**
     * Verificar si requiere mantenimiento urgente.
     */
    public function requiresUrgentMaintenance(): bool
    {
        return $this->next_maintenance_due && 
               $this->next_maintenance_due->isPast();
    }

    /**
     * Verificar si requiere inspección urgente.
     */
    public function requiresUrgentInspection(): bool
    {
        return !$this->inspection_current || 
               ($this->next_inspection_due && $this->next_inspection_due->isPast());
    }

    /**
     * Obtener el estado legible del estatus operacional.
     */
    public function getOperationalStatusLabelAttribute(): string
    {
        return self::OPERATIONAL_STATUSES[$this->operational_status] ?? 'Desconocido';
    }

    /**
     * Calcular capacidad total de carga.
     */
    public function getTotalCargoCapacityAttribute(): float
    {
        return $this->max_cargo_capacity ?? 0;
    }

    /**
     * Verificar si está en condiciones para viaje.
     */
    public function isSeaworthy(): bool
    {
        return $this->isAvailable() && 
               !$this->requiresUrgentMaintenance() && 
               !$this->requiresUrgentInspection();
    }
}