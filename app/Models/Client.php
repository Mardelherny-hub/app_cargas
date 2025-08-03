<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\ClientContactData;

/**
 * MODELO SIMPLIFICADO - Cliente sin roles
 * 
 * SIMPLIFICACIÓN APLICADA:
 * - ❌ REMOVIDO: client_roles y todos sus métodos
 * - ❌ REMOVIDO: constante CLIENT_ROLES
 * - ✅ AGREGADO: commercial_name, address, email
 * - ✅ SIMPLIFICADO: Los clientes son solo empresas propietarias de mercadería
 */
class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'tax_id',
        'country_id',
        'document_type_id',
        'legal_name',
        'commercial_name',
        'address',
        'email',
        'primary_port_id',
        'customs_offices_id',
        'status',
        'created_by_company_id',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
 
    protected $with = [
        'primaryContact',  
    ];

    /**
     * Estados disponibles.
     */
    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'suspended' => 'Suspendido',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function primaryPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'primary_port_id');
    }

    public function customsOffice(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'customs_offices_id');
    }

    public function createdByCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'created_by_company_id');
    }

    public function contactData(): HasMany
    {
        return $this->hasMany(ClientContactData::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContactData::class)
                   ->where('is_primary', true)
                   ->where('active', true);
    }

    public function activeContacts(): HasMany
    {
        return $this->hasMany(ClientContactData::class)->where('active', true);
    }

    // =====================================================
    // SCOPES
    // =====================================================

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    public function scopeByCountry(Builder $query, int $countryId): void
    {
        $query->where('country_id', $countryId);
    }

    // =====================================================
    // MÉTODOS DE UTILIDAD
    // =====================================================

    public static function findByTaxId(string $taxId, ?int $countryId = null): ?self
    {
        $cleanTaxId = preg_replace('/[^0-9]/', '', $taxId);

        $query = self::where('tax_id', $cleanTaxId);

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        return $query->first();
    }

    public function getDisplayName(): string
    {
        return $this->commercial_name ?: $this->legal_name;
    }

    public function getFormattedTaxId(): string
    {
        if ($this->country && $this->country->alpha2_code === 'AR') {
            // Formato CUIT argentino: XX-XXXXXXXX-X
            return substr($this->tax_id, 0, 2) . '-' . 
                   substr($this->tax_id, 2, 8) . '-' . 
                   substr($this->tax_id, 10, 1);
        }
        
        return $this->tax_id;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function getEmailsList(): array
    {
        if (empty($this->email)) {
            return [];
        }
        
        return array_filter(array_map('trim', explode(';', $this->email)));
    }

    // =====================================================
    // EVENTOS DEL MODELO
    // =====================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if (empty($client->verified_at)) {
                $client->verified_at = now();
            }
        });
    }
}