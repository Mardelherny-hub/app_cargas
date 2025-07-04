<?php
// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Company extends Model implements Auditable
{
    use HasFactory;
    use AuditableTrait;

    protected $fillable = [
        'business_name',
        'commercial_name',
        'tax_id',
        'country',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'certificate_path',
        'certificate_password',
        'certificate_alias',
        'certificate_expires_at',
        'ws_config',
        'ws_active',
        'ws_environment',
        'active',
        'created_date',
        'last_access',
    ];

    protected $casts = [
        'ws_config' => 'array',
        'ws_active' => 'boolean',
        'active' => 'boolean',
        'created_date' => 'datetime',
        'last_access' => 'datetime',
        'certificate_expires_at' => 'datetime',
    ];

    /**
     * Inverse polymorphic relationship with User
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Operators that belong to this company
     */
    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }

    /**
     * Trips for this company
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Shipments/loads for this company
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Check if certificate is about to expire
     */
    public function isCertificateExpiringSoon($days = 30): bool
    {
        if (!$this->certificate_expires_at) {
            return false;
        }

        return $this->certificate_expires_at->diffInDays(now()) <= $days;
    }

    /**
     * Check if certificate is expired
     */
    public function isCertificateExpired(): bool
    {
        if (!$this->certificate_expires_at) {
            return true;
        }

        return $this->certificate_expires_at->isPast();
    }

    /**
     * Get webservice configuration
     */
    public function getWebserviceConfig($service = null)
    {
        $config = $this->ws_config ?? [];

        if ($service) {
            return $config[$service] ?? null;
        }

        return $config;
    }

    /**
     * Update webservice configuration
     */
    public function updateWebserviceConfig($service, $configuration)
    {
        $config = $this->ws_config ?? [];
        $config[$service] = $configuration;

        $this->update(['ws_config' => $config]);
    }

    /**
     * Check if can use webservices
     */
    public function canUseWebservices(): bool
    {
        return $this->ws_active &&
        $this->active &&
        !$this->isCertificateExpired();
    }

    /**
     * Scope for active companies
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for companies by country
     */
    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope for companies with valid certificate
     */
    public function scopeWithValidCertificate($query)
    {
        return $query->where('certificate_expires_at', '>', now())
        ->whereNotNull('certificate_path');
    }

    /**
     * Accessor for display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->commercial_name ?: $this->business_name;
    }

    /**
     * Accessor for formatted country name
     */
    public function getCountryNameAttribute(): string
    {
        return match($this->country) {
            'AR' => 'Argentina',
            'PY' => 'Paraguay',
            default => $this->country
        };
    }

    /**
     * Accessor for certificate status
     */
    public function getCertificateStatusAttribute(): string
    {
        if (!$this->certificate_path) {
            return 'No certificate';
        }

        if ($this->isCertificateExpired()) {
            return 'Expired';
        }

        if ($this->isCertificateExpiringSoon()) {
            return 'Expiring soon';
        }

        return 'Valid';
    }

    /**
     * Audit configuration
     */
    protected $auditInclude = [
        'business_name',
        'tax_id',
        'country',
        'ws_active',
        'ws_environment',
        'active',
    ];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Company {$this->display_name} ({$this->tax_id}) was {$eventName}";
    }
}
