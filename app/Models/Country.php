<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'iso_code',
        'alpha2_code',
        'numeric_code',
        'name',
        'official_name',
        'nationality',
        'customs_code',
        'senasa_code',
        'document_format',
        'currency_code',
        'timezone',
        'primary_language',
        'allows_import',
        'allows_export',
        'allows_transit',
        'requires_visa',
        'active',
        'display_order',
        'is_primary',
        'created_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'allows_import' => 'boolean',
        'allows_export' => 'boolean',
        'allows_transit' => 'boolean',
        'requires_visa' => 'boolean',
        'active' => 'boolean',
        'is_primary' => 'boolean',
        'created_date' => 'datetime',
        'display_order' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Document types available for this country
     */
    public function DocumentsTypes(): HasMany
    {
        return $this->hasMany(DocumentsType::class);
    }

    /**
     * Customs offices in this country
     */
    public function CustomOffices(): HasMany
    {
        return $this->hasMany(CustomOffice::class);
    }

    /**
     * Ports in this country
     */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }

    /**
     * Companies based in this country
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'country', 'alpha2_code');
    }

    /**
     * Clients from this country
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * User who created this record
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active countries
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for primary countries (AR, PY)
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for countries that allow specific operations
     */
    public function scopeAllowsImport($query)
    {
        return $query->where('allows_import', true);
    }

    public function scopeAllowsExport($query)
    {
        return $query->where('allows_export', true);
    }

    public function scopeAllowsTransit($query)
    {
        return $query->where('allows_transit', true);
    }

    /**
     * Scope for ordering by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get active document types for this country
     */
    public function getActiveDocumentsTypes()
    {
        return $this->DocumentsTypes()
            ->where('active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get primary document type for tax purposes
     */
    public function getPrimaryTaxDocumentsType()
    {
        return $this->DocumentsTypes()
            ->where('active', true)
            ->where('for_tax_purposes', true)
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Get active customs offices
     */
    public function getActiveCustomOffices()
    {
        return $this->CustomOffices()
            ->where('active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get active ports
     */
    public function getActivePorts()
    {
        return $this->ports()
            ->where('active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Check if country supports specific webservice
     */
    public function supportsWebservice(string $webserviceType): bool
    {
        // Logic based on country capabilities
        return match($this->alpha2_code) {
            'AR' => in_array($webserviceType, ['anticipada', 'micdta', 'desconsolidado', 'transbordo']),
            'PY' => in_array($webserviceType, ['anticipada', 'micdta']),
            default => false,
        };
    }

    /**
     * Validate document format for this country
     */
    public function validateDocumentFormat(string $document, string $DocumentsTypeCode = null): bool
    {
        if (empty($this->document_format)) {
            return true; // No validation pattern defined
        }

        // If specific document type provided, use its validation
        if ($DocumentsTypeCode) {
            $docType = $this->DocumentsTypes()
                ->where('code', $DocumentsTypeCode)
                ->where('active', true)
                ->first();

            if ($docType && $docType->validation_pattern) {
                return (bool) preg_match('/' . $docType->validation_pattern . '/', $document);
            }
        }

        // Use country default validation
        return (bool) preg_match('/' . $this->document_format . '/', $document);
    }

    /**
     * Get country flag URL or emoji
     */
    public function getFlagAttribute(): string
    {
        return match($this->alpha2_code) {
            'AR' => '🇦🇷',
            'PY' => '🇵🇾',
            'BR' => '🇧🇷',
            'UY' => '🇺🇾',
            default => '🏴',
        };
    }

    /**
     * Check if this is Argentina
     */
    public function isArgentina(): bool
    {
        return $this->alpha2_code === 'AR';
    }

    /**
     * Check if this is Paraguay
     */
    public function isParaguay(): bool
    {
        return $this->alpha2_code === 'PY';
    }

    /**
     * Get display name with flag
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->flag . ' ' . $this->name;
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    /**
     * Get Argentina country record
     */
    public static function argentina(): ?self
    {
        return static::where('alpha2_code', 'AR')->first();
    }

    /**
     * Get Paraguay country record
     */
    public static function paraguay(): ?self
    {
        return static::where('alpha2_code', 'PY')->first();
    }

    /**
     * Get primary countries (AR, PY)
     */
    public static function primaryCountries()
    {
        return static::where('is_primary', true)
            ->where('active', true)
            ->ordered()
            ->get();
    }

    /**
     * Get countries for select dropdown
     */
    public static function forSelect(): array
    {
        return static::active()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }
}
