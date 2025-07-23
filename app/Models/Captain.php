<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Captain Model
 * 
 * Modelo para capitanes de embarcaciones.
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
 * @property \Carbon\Carbon|null $document_expires
 * @property string $license_number
 * @property string $license_class
 * @property string $license_status
 * @property \Carbon\Carbon|null $license_issued_at
 * @property \Carbon\Carbon|null $license_expires_at
 * @property string|null $medical_certificate_number
 * @property \Carbon\Carbon|null $medical_certificate_expires_at
 * @property string|null $safety_training_certificate
 * @property \Carbon\Carbon|null $safety_training_expires_at
 * @property int $years_of_experience
 * @property string $employment_status
 * @property bool $available_for_hire
 * @property \Carbon\Carbon|null $available_from
 * @property \Carbon\Carbon|null $available_until
 * @property \Carbon\Carbon|null $last_voyage_date
 * @property decimal $performance_rating
 * @property array|null $vessel_type_competencies
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
        'document_expires',
        'license_number',
        'license_class',
        'license_status',
        'license_issued_at',
        'license_expires_at',
        'medical_certificate_number',
        'medical_certificate_expires_at',
        'safety_training_certificate',
        'safety_training_expires_at',
        'years_of_experience',
        'employment_status',
        'available_for_hire',
        'available_from',
        'available_until',
        'last_voyage_date',
        'performance_rating',
        'vessel_type_competencies',
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
        'document_expires' => 'date',
        'license_issued_at' => 'date',
        'license_expires_at' => 'date',
        'medical_certificate_expires_at' => 'date',
        'safety_training_expires_at' => 'date',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'last_voyage_date' => 'datetime',
        'performance_rating' => 'decimal:2',
        'years_of_experience' => 'integer',
        'available_for_hire' => 'boolean',
        'active' => 'boolean',
        'verified' => 'boolean',
        'vessel_type_competencies' => 'array',
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
        'created_by_user_id',
        'last_updated_by_user_id',
    ];

    // ================================
    // RELATIONSHIPS
    // ================================

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
     * Embarcaciones donde este capitán es el capitán principal
     */
    public function primaryVessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'primary_captain_id');
    }

    /**
     * Viajes donde este capitán es el capitán principal
     */
    public function voyages(): HasMany
    {
        return $this->hasMany(Voyage::class, 'captain_id');
    }

    /**
     * Envíos donde este capitán está asignado
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'captain_id');
    }

    // ================================
    // ACCESSORS & MUTATORS
    // ================================

    /**
     * Generar full_name automáticamente
     */
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value;
        $this->updateFullName();
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value;
        $this->updateFullName();
    }

    /**
     * Actualizar el nombre completo
     */
    private function updateFullName()
    {
        if (isset($this->attributes['first_name']) && isset($this->attributes['last_name'])) {
            $this->attributes['full_name'] = trim($this->attributes['first_name'] . ' ' . $this->attributes['last_name']);
        }
    }

    // ================================
    // QUERY SCOPES
    // ================================

    /**
     * Capitanes activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Capitanes disponibles para contratación
     */
    public function scopeAvailable($query)
    {
        return $query->where('active', true)
                    ->where('available_for_hire', true);
    }

    /**
     * Capitanes con licencia activa
     */
    public function scopeWithActiveLicense($query)
    {
        return $query->where('license_status', 'active')
                    ->where(function($q) {
                        $q->whereNull('license_expires_at')
                          ->orWhere('license_expires_at', '>', now());
                    });
    }

    /**
     * Capitanes por clase de licencia
     */
    public function scopeByLicenseClass($query, $class)
    {
        return $query->where('license_class', $class);
    }

    /**
     * Capitanes por empresa
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('primary_company_id', $companyId);
    }

    /**
     * Capitanes con experiencia mínima
     */
    public function scopeWithMinExperience($query, $years)
    {
        return $query->where('years_of_experience', '>=', $years);
    }

    // ================================
    // BUSINESS METHODS
    // ================================

    /**
     * Verificar si la licencia está vigente
     */
    public function hasValidLicense(): bool
    {
        return $this->license_status === 'active' && 
               ($this->license_expires_at === null || $this->license_expires_at->isFuture());
    }

    /**
     * Verificar si el certificado médico está vigente
     */
    public function hasValidMedicalCertificate(): bool
    {
        return $this->medical_certificate_expires_at === null || 
               $this->medical_certificate_expires_at->isFuture();
    }

    /**
     * Verificar si está disponible en un rango de fechas
     */
    public function isAvailableForPeriod($startDate, $endDate): bool
    {
        if (!$this->available_for_hire || !$this->active) {
            return false;
        }

        // Verificar disponibilidad general
        if ($this->available_from && $this->available_from->isAfter($startDate)) {
            return false;
        }

        if ($this->available_until && $this->available_until->isBefore($endDate)) {
            return false;
        }

        return true;
    }

    /**
     * Obtener rating como texto
     */
    public function getRatingText(): string
    {
        $rating = (float) $this->performance_rating;
        
        if ($rating >= 4.5) return 'Excelente';
        if ($rating >= 4.0) return 'Muy Bueno';
        if ($rating >= 3.5) return 'Bueno';
        if ($rating >= 3.0) return 'Regular';
        return 'Necesita Mejora';
    }

    /**
     * Obtener días hasta vencimiento de licencia
     */
    public function getDaysUntilLicenseExpiry(): ?int
    {
        if (!$this->license_expires_at) {
            return null;
        }

        return now()->diffInDays($this->license_expires_at, false);
    }

    /**
     * Verificar si requiere renovación próxima (30 días)
     */
    public function requiresLicenseRenewal(): bool
    {
        $days = $this->getDaysUntilLicenseExpiry();
        return $days !== null && $days <= 30 && $days >= 0;
    }
}