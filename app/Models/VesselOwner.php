<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * CORRECCIÓN 1 - PROPIETARIOS DE EMBARCACIONES
 *
 * Modelo VesselOwner para propietarios de barcos/embarcaciones
 * Separado del concepto de clientes de carga
 *
 * @property int $id
 * @property string $tax_id CUIT/RUC del propietario
 * @property string $legal_name Razón social oficial
 * @property string|null $commercial_name Nombre comercial
 * @property int $company_id Empresa asociada
 * @property int $country_id País del propietario
 * @property string $transportista_type Tipo transportista (O/R)
 * @property string|null $email Email de contacto
 * @property string|null $phone Teléfono de contacto
 * @property string|null $address Dirección fiscal
 * @property string|null $city Ciudad
 * @property string|null $postal_code Código postal
 * @property string $status Estado del propietario
 * @property Carbon|null $tax_id_verified_at Fecha verificación CUIT
 * @property bool $webservice_authorized Autorizado webservices
 * @property array|null $webservice_config Configuración webservices
 * @property string|null $notes Observaciones
 * @property int|null $created_by_user_id Usuario creador
 * @property int|null $updated_by_user_id Usuario modificador
 * @property Carbon|null $last_activity_at Última actividad
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VesselOwner extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'vessel_owners';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'tax_id',
        'legal_name',
        'commercial_name',
        'company_id',
        'country_id',
        'transportista_type',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'status',
        'tax_id_verified_at',
        'webservice_authorized',
        'webservice_config',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'last_activity_at',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'tax_id_verified_at' => 'datetime',
        'webservice_authorized' => 'boolean',
        'webservice_config' => 'array',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de transportista según webservices.
     */
    public const TRANSPORTISTA_TYPES = [
        'O' => 'Operador',
        'R' => 'Representante',
    ];

    /**
     * Estados disponibles del propietario.
     */
    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'suspended' => 'Suspendido',
        'pending_verification' => 'Pendiente de Verificación',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Empresa a la que pertenece el propietario.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * País del propietario.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Usuario que creó el registro.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Usuario que modificó el registro por última vez.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Embarcaciones que pertenecen a este propietario.
     * TODO: Implementar cuando se modifique el modelo Vessel
     */
    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class, 'owner_id');
    }

    // =====================================================
    // SCOPES Y CONSULTAS
    // =====================================================

    /**
     * Scope para propietarios activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para propietarios inactivos.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope para propietarios autorizados para webservices.
     */
    public function scopeWebserviceAuthorized(Builder $query): Builder
    {
        return $query->where('webservice_authorized', true);
    }

    /**
     * Scope para filtrar por empresa.
     */
    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para filtrar por tipo de transportista.
     */
    public function scopeByTransportistaType(Builder $query, string $type): Builder
    {
        return $query->where('transportista_type', $type);
    }

    /**
     * Scope para propietarios verificados fiscalmente.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('tax_id_verified_at');
    }

    /**
     * Scope de búsqueda general.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('legal_name', 'like', "%{$search}%")
              ->orWhere('commercial_name', 'like', "%{$search}%")
              ->orWhere('tax_id', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Scope para filtrar por país (usando códigos AR/PY).
     */
    public function scopeByCountryCode(Builder $query, string $countryCode): Builder
    {
        return $query->whereHas('country', function (Builder $q) use ($countryCode) {
            $q->where('iso_code', $countryCode);
        });
    }

    // =====================================================
    // MÉTODOS DE VALIDACIÓN Y VERIFICACIÓN
    // =====================================================

    /**
     * Verificar si el propietario puede ser usado en webservices.
     */
    public function canUseInWebservices(): bool
    {
        return $this->status === 'active'
            && $this->webservice_authorized
            && !is_null($this->tax_id_verified_at)
            && !empty($this->transportista_type);
    }

    /**
     * Verificar si el CUIT/RUC está verificado.
     */
    public function isTaxIdVerified(): bool
    {
        return !is_null($this->tax_id_verified_at);
    }

    /**
     * Marcar como verificado fiscalmente.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'tax_id_verified_at' => now(),
            'last_activity_at' => now(),
            'updated_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Autorizar para uso en webservices.
     */
    public function authorizeForWebservices(array $config = []): void
    {
        $this->update([
            'webservice_authorized' => true,
            'webservice_config' => array_merge($this->webservice_config ?? [], $config),
            'last_activity_at' => now(),
            'updated_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Desautorizar para uso en webservices.
     */
    public function unauthorizeFromWebservices(): void
    {
        $this->update([
            'webservice_authorized' => false,
            'last_activity_at' => now(),
            'updated_by_user_id' => auth()->id(),
        ]);
    }

    // =====================================================
    // MÉTODOS DE INFORMACIÓN Y DISPLAY
    // =====================================================

    /**
     * Obtener nombre completo (comercial o business).
     */
    public function getFullNameAttribute(): string
    {
        return $this->commercial_name ?: $this->legal_name;
    }

    /**
     * Obtener nombre para mostrar con tipo de transportista.
     */
    public function getDisplayNameAttribute(): string
    {
        $type = self::TRANSPORTISTA_TYPES[$this->transportista_type] ?? $this->transportista_type;
        return "{$this->full_name} ({$type})";
    }

    /**
     * Obtener estado para mostrar.
     */
    public function getStatusDisplayAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Obtener indicador de verificación.
     */
    public function getVerificationStatusAttribute(): string
    {
        if ($this->isTaxIdVerified()) {
            return 'Verificado el ' . $this->tax_id_verified_at->format('d/m/Y');
        }
        return 'No verificado';
    }

    /**
     * Obtener información completa para webservices.
     */
    public function getWebserviceDataAttribute(): array
    {
        return [
            'tax_id' => $this->tax_id,
            'legal_name' => $this->legal_name,
            'transportista_type' => $this->transportista_type,
            'country_code' => $this->country->iso_code ?? null,
            'authorized' => $this->webservice_authorized,
            'verified' => $this->isTaxIdVerified(),
            'config' => $this->webservice_config ?? [],
        ];
    }

    /**
     * Obtener nombre de la empresa asociada.
     */
    public function getCompanyNameAttribute(): string
    {
        return $this->company ? 
            ($this->company->commercial_name ?: $this->company->legal_name) : 
            'Sin empresa';
    }

    // =====================================================
    // MÉTODOS ESTÁTICOS Y UTILIDADES
    // =====================================================

    /**
     * Buscar propietario por CUIT/RUC.
     */
    public static function findByTaxId(string $taxId): ?self
    {
        return static::where('tax_id', $taxId)->first();
    }

    /**
     * Obtener propietarios disponibles para una empresa.
     */
    public static function availableForCompany(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return static::byCompany($companyId)
            ->active()
            ->orderBy('legal_name')
            ->get();
    }

    /**
     * Obtener propietarios autorizados para webservices de una empresa.
     */
    public static function webserviceAuthorizedForCompany(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return static::byCompany($companyId)
            ->active()
            ->webserviceAuthorized()
            ->verified()
            ->orderBy('legal_name')
            ->get();
    }

    /**
     * Validar CUIT/RUC según el país.
     */
    public function validateTaxId(): bool
    {
        // Limpiar CUIT/RUC
        $taxId = preg_replace('/[^0-9]/', '', $this->tax_id);
        
        if ($this->country && $this->country->iso_code === 'AR') {
            // Validación CUIT Argentina (11 dígitos)
            return strlen($taxId) === 11 && is_numeric($taxId);
        }
        
        if ($this->country && $this->country->iso_code === 'PY') {
            // Validación RUC Paraguay (variable)
            return strlen($taxId) >= 6 && is_numeric($taxId);
        }
        
        // Validación genérica
        return strlen($taxId) >= 6;
    }

    // =====================================================
    // EVENTOS DEL MODELO
    // =====================================================

    /**
     * Boot del modelo.
     */
    protected static function boot()
    {
        parent::boot();

        // Actualizar last_activity_at en cada modificación
        static::updating(function ($vesselOwner) {
            $vesselOwner->last_activity_at = now();
            $vesselOwner->updated_by_user_id = auth()->id();
        });

        // Validar y limpiar datos antes de guardar
        static::saving(function ($vesselOwner) {
            // Limpiar y normalizar tax_id
            $vesselOwner->tax_id = preg_replace('/[^0-9]/', '', $vesselOwner->tax_id);
            
            // Normalizar nombres
            $vesselOwner->legal_name = trim($vesselOwner->legal_name);
            if ($vesselOwner->commercial_name) {
                $vesselOwner->commercial_name = trim($vesselOwner->commercial_name);
            }
        });

        // Asignar usuario creador en la creación
        static::creating(function ($vesselOwner) {
            if (!$vesselOwner->created_by_user_id) {
                $vesselOwner->created_by_user_id = auth()->id();
            }
            $vesselOwner->last_activity_at = now();
        });
    }
}