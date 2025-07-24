<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Captain Model
 * 
 * Modelo para capitanes de embarcaciones del sistema de transporte fluvial.
 * Gestiona datos personales, licencias, certificaciones y competencias.
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $full_name
 * @property \Carbon\Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $nationality
 * @property string|null $blood_type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile_phone
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $postal_code
 * @property int|null $country_id
 * @property int|null $license_country_id
 * @property int|null $primary_company_id
 * @property string|null $document_type
 * @property string|null $document_number
 * @property string|null $license_number
 * @property string|null $license_class
 * @property string $license_status
 * @property \Carbon\Carbon|null $license_issued_at
 * @property \Carbon\Carbon|null $license_expires_at
 * @property \Carbon\Carbon|null $medical_certificate_expires_at
 * @property \Carbon\Carbon|null $safety_training_expires_at
 * @property string $employment_status
 * @property bool $available_for_hire
 * @property \Carbon\Carbon|null $available_from
 * @property \Carbon\Carbon|null $available_until
 * @property \Carbon\Carbon|null $last_voyage_date
 * @property int $total_voyages
 * @property int $years_of_experience
 * @property decimal $performance_rating
 * @property string|null $specializations
 * @property array|null $vessel_type_competencies
 * @property array|null $route_competencies
 * @property array|null $cargo_type_competencies
 * @property array|null $route_restrictions
 * @property array|null $additional_certifications
 * @property bool $active
 * @property bool $verified
 * @property string|null $verification_notes
 * @property \Carbon\Carbon $created_date
 * @property int|null $created_by_user_id
 * @property \Carbon\Carbon $last_updated_date
 * @property int|null $last_updated_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Captain extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'captains';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'full_name',
        'birth_date',
        'gender',
        'nationality',
        'blood_type',
        'email',
        'phone',
        'mobile_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'address',
        'city',
        'state_province',
        'postal_code',
        'country_id',
        'license_country_id',
        'primary_company_id',
        'document_type',
        'document_number',
        'license_number',
        'license_class',
        'license_status',
        'license_issued_at',
        'license_expires_at',
        'medical_certificate_expires_at',
        'safety_training_expires_at',
        'employment_status',
        'available_for_hire',
        'available_from',
        'available_until',
        'last_voyage_date',
        'total_voyages',
        'years_of_experience',
        'performance_rating',
        'specializations',
        'vessel_type_competencies',
        'route_competencies',
        'cargo_type_competencies',
        'route_restrictions',
        'additional_certifications',
        'active',
        'verified',
        'verification_notes',
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'birth_date' => 'date',
        'license_issued_at' => 'datetime',
        'license_expires_at' => 'datetime',
        'medical_certificate_expires_at' => 'datetime',
        'safety_training_expires_at' => 'datetime',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'last_voyage_date' => 'datetime',
        'available_for_hire' => 'boolean',
        'active' => 'boolean',
        'verified' => 'boolean',
        'performance_rating' => 'decimal:2',
        'vessel_type_competencies' => 'array',
        'route_competencies' => 'array',
        'cargo_type_competencies' => 'array',
        'route_restrictions' => 'array',
        'additional_certifications' => 'array',
        'created_date' => 'datetime',
        'last_updated_date' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'document_number', // Documento personal sensible
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generar full_name cuando se crea o actualiza
        static::saving(function ($captain) {
            if ($captain->first_name || $captain->last_name) {
                $captain->full_name = trim($captain->first_name . ' ' . $captain->last_name);
            }
        });
    }

    //
    // === RELATIONSHIPS ===
    //

    /**
     * País de residencia del capitán
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * País emisor de la licencia
     */
    public function licenseCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'license_country_id');
    }

    /**
     * Empresa principal del capitán
     */
    public function primaryCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'primary_company_id');
    }

    /**
     * Usuario que creó el registro
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Usuario que actualizó el registro por última vez
     */
    public function lastUpdatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    /**
     * Embarcaciones donde este capitán es el capitán principal
     */
    public function primaryVessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'primary_captain_id');
    }

    /**
     * Viajes donde actúa como capitán principal
     */
    public function voyages(): HasMany
    {
        return $this->hasMany(Voyage::class, 'captain_id');
    }

    /**
     * Envíos individuales asignados a este capitán
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'captain_id');
    }

    //
    // === SCOPES ===
    //

    /**
     * Solo capitanes activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Solo capitanes verificados
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Solo capitanes disponibles para contratación
     */
    public function scopeAvailableForHire($query)
    {
        return $query->where('available_for_hire', true)
                    ->where('active', true);
    }

    /**
     * Capitanes con licencia válida
     */
    public function scopeWithValidLicense($query)
    {
        return $query->where('license_status', 'valid')
                    ->where(function ($q) {
                        $q->whereNull('license_expires_at')
                          ->orWhere('license_expires_at', '>', now());
                    });
    }

    /**
     * Capitanes con certificado médico vigente
     */
    public function scopeWithValidMedical($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('medical_certificate_expires_at')
              ->orWhere('medical_certificate_expires_at', '>', now());
        });
    }

    /**
     * Capitanes disponibles en un rango de fechas
     */
    public function scopeAvailableBetween($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->where('available_from', '<=', $start)
              ->where(function ($sub) use ($end) {
                  $sub->whereNull('available_until')
                      ->orWhere('available_until', '>=', $end);
              });
        });
    }

    /**
     * Filtrar por empresa
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('primary_company_id', $companyId);
    }

    /**
     * Filtrar por años de experiencia mínima
     */
    public function scopeWithExperienceAtLeast($query, $years)
    {
        return $query->where('years_of_experience', '>=', $years);
    }

    /**
     * Filtrar por rating mínimo
     */
    public function scopeWithRatingAtLeast($query, $rating)
    {
        return $query->where('performance_rating', '>=', $rating);
    }

    //
    // === ACCESSORS & MUTATORS ===
    //

    /**
     * Calcular edad del capitán
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    /**
     * Verificar si la licencia está próxima a vencer
     */
    public function getLicenseExpiringAttribute(): bool
    {
        if (!$this->license_expires_at) {
            return false;
        }
        
        return $this->license_expires_at->diffInDays(now()) <= 30;
    }

    /**
     * Verificar si el certificado médico está próximo a vencer
     */
    public function getMedicalExpiringAttribute(): bool
    {
        if (!$this->medical_certificate_expires_at) {
            return false;
        }
        
        return $this->medical_certificate_expires_at->diffInDays(now()) <= 30;
    }

    /**
     * Obtener estado general del capitán
     */
    public function getStatusAttribute(): string
    {
        if (!$this->active) {
            return 'inactive';
        }
        
        if ($this->license_status !== 'valid') {
            return 'license_invalid';
        }
        
        if ($this->license_expires_at && $this->license_expires_at <= now()) {
            return 'license_expired';
        }
        
        if ($this->medical_certificate_expires_at && $this->medical_certificate_expires_at <= now()) {
            return 'medical_expired';
        }
        
        if (!$this->available_for_hire) {
            return 'unavailable';
        }
        
        return 'available';
    }

    /**
     * Formatear nombre completo con título
     */
    public function getDisplayNameAttribute(): string
    {
        $title = '';
        
        // Agregar título basado en clase de licencia
        if ($this->license_class) {
            $titles = [
                'master' => 'Cap.',
                'chief_officer' => 'Primer Of.',
                'officer' => 'Of.',
                'pilot' => 'Práctico',
            ];
            $title = $titles[$this->license_class] ?? '';
        }
        
        return trim($title . ' ' . $this->full_name);
    }

    //
    // === METHODS ===
    //

    /**
     * Marcar como no disponible
     */
    public function markUnavailable(?Carbon $until = null): void
    {
        $this->update([
            'available_for_hire' => false,
            'available_until' => $until,
        ]);
    }

    /**
     * Marcar como disponible
     */
    public function markAvailable(?Carbon $from = null): void
    {
        $this->update([
            'available_for_hire' => true,
            'available_from' => $from ?? now(),
            'available_until' => null,
        ]);
    }

    /**
     * Actualizar después de completar un viaje
     */
    public function updateAfterVoyage(Carbon $voyageDate, float $rating = null): void
    {
        $updateData = [
            'last_voyage_date' => $voyageDate,
            'total_voyages' => $this->total_voyages + 1,
        ];
        
        // Actualizar rating si se proporciona
        if ($rating !== null) {
            $currentRating = $this->performance_rating ?? 0;
            $totalVoyages = $this->total_voyages;
            
            // Promedio ponderado
            $newRating = (($currentRating * $totalVoyages) + $rating) / ($totalVoyages + 1);
            $updateData['performance_rating'] = round($newRating, 2);
        }
        
        $this->update($updateData);
    }

    /**
     * Verificar si puede comandar un tipo de embarcación
     */
    public function canCommandVesselType(string $vesselType): bool
    {
        if (!$this->vessel_type_competencies) {
            return false;
        }
        
        return in_array($vesselType, $this->vessel_type_competencies);
    }

    /**
     * Verificar si puede navegar una ruta específica
     */
    public function canNavigateRoute(string $route): bool
    {
        // Verificar restricciones
        if ($this->route_restrictions && in_array($route, $this->route_restrictions)) {
            return false;
        }
        
        // Verificar competencias (si están definidas)
        if ($this->route_competencies) {
            return in_array($route, $this->route_competencies);
        }
        
        // Si no hay competencias específicas definidas, asumir que puede
        return true;
    }

    /**
     * Verificar disponibilidad en un período
     */
    public function isAvailableBetween(Carbon $start, Carbon $end): bool
    {
        if (!$this->available_for_hire || !$this->active) {
            return false;
        }
        
        // Verificar ventana de disponibilidad
        if ($this->available_from && $this->available_from > $start) {
            return false;
        }
        
        if ($this->available_until && $this->available_until < $end) {
            return false;
        }
        
        return true;
    }

    /**
     * Obtener próximas expiraciones
     */
    public function getUpcomingExpirations(): array
    {
        $expirations = [];
        
        if ($this->license_expires_at) {
            $daysToExpiry = $this->license_expires_at->diffInDays(now());
            if ($daysToExpiry <= 60) {
                $expirations[] = [
                    'type' => 'license',
                    'description' => 'Licencia de Navegación',
                    'expires_at' => $this->license_expires_at,
                    'days_remaining' => $daysToExpiry,
                    'urgency' => $daysToExpiry <= 30 ? 'high' : 'medium',
                ];
            }
        }
        
        if ($this->medical_certificate_expires_at) {
            $daysToExpiry = $this->medical_certificate_expires_at->diffInDays(now());
            if ($daysToExpiry <= 60) {
                $expirations[] = [
                    'type' => 'medical',
                    'description' => 'Certificado Médico',
                    'expires_at' => $this->medical_certificate_expires_at,
                    'days_remaining' => $daysToExpiry,
                    'urgency' => $daysToExpiry <= 30 ? 'high' : 'medium',
                ];
            }
        }
        
        if ($this->safety_training_expires_at) {
            $daysToExpiry = $this->safety_training_expires_at->diffInDays(now());
            if ($daysToExpiry <= 60) {
                $expirations[] = [
                    'type' => 'safety',
                    'description' => 'Entrenamiento de Seguridad',
                    'expires_at' => $this->safety_training_expires_at,
                    'days_remaining' => $daysToExpiry,
                    'urgency' => $daysToExpiry <= 30 ? 'high' : 'medium',
                ];
            }
        }
        
        return $expirations;
    }
}