<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomOffice extends Model
{
    use HasFactory;

    protected $table = 'customs_offices'; // Explicit table name

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'country_id',
        'city',
        'province_state',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'office_type',
        'handles_maritime',
        'handles_fluvial',
        'handles_containers',
        'handles_bulk_cargo',
        'handles_passengers',
        'webservice_code',
        'webservice_config',
        'supports_anticipada',
        'supports_micdta',
        'supports_desconsolidado',
        'supports_transbordo',
        'operating_hours',
        'holiday_schedule',
        'operates_24h',
        'phone',
        'fax',
        'email',
        'website',
        'supervisor_name',
        'supervisor_contact',
        'region_code',
        'active',
        'accepts_new_operations',
        'display_order',
        'established_date',
        'special_requirements',
        'prohibited_goods',
        'max_container_capacity',
        'created_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'handles_maritime' => 'boolean',
        'handles_fluvial' => 'boolean',
        'handles_containers' => 'boolean',
        'handles_bulk_cargo' => 'boolean',
        'handles_passengers' => 'boolean',
        'supports_anticipada' => 'boolean',
        'supports_micdta' => 'boolean',
        'supports_desconsolidado' => 'boolean',
        'supports_transbordo' => 'boolean',
        'operates_24h' => 'boolean',
        'active' => 'boolean',
        'accepts_new_operations' => 'boolean',
        'webservice_config' => 'array',
        'operating_hours' => 'array',
        'holiday_schedule' => 'array',
        'special_requirements' => 'array',
        'prohibited_goods' => 'array',
        'max_container_capacity' => 'decimal:2',
        'display_order' => 'integer',
        'established_date' => 'date',
        'created_date' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Country this customs office belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Ports that use this as primary customs office
     */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class, 'primary_customs_office_id');
    }

    /**
     * User who created this record
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Clients that use this customs office (will be implemented in Phase 1)
     */
    // public function clients(): HasMany
    // {
    //     return $this->hasMany(Client::class, 'customs_office_id');
    // }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active customs offices
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for customs offices by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope for customs offices by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('office_type', $type);
    }

    /**
     * Scope for customs offices accepting new operations
     */
    public function scopeAccepting($query)
    {
        return $query->where('accepts_new_operations', true);
    }

    /**
     * Scope for maritime operations
     */
    public function scopeHandlesMaritime($query)
    {
        return $query->where('handles_maritime', true);
    }

    /**
     * Scope for fluvial operations
     */
    public function scopeHandlesFluvial($query)
    {
        return $query->where('handles_fluvial', true);
    }

    /**
     * Scope for container operations
     */
    public function scopeHandlesContainers($query)
    {
        return $query->where('handles_containers', true);
    }

    /**
     * Scope for 24h operations
     */
    public function scopeOperates24h($query)
    {
        return $query->where('operates_24h', true);
    }

    /**
     * Scope by webservice support
     */
    public function scopeWithWebserviceSupport($query, string $webservice)
    {
        return $query->where("supports_{$webservice}", true);
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // ========================================
    // WEBSERVICE METHODS
    // ========================================

    /**
     * Check if supports specific webservice
     */
    public function supportsWebservice(string $webservice): bool
    {
        return match($webservice) {
            'anticipada' => $this->supports_anticipada,
            'micdta' => $this->supports_micdta,
            'desconsolidado' => $this->supports_desconsolidado,
            'transbordo' => $this->supports_transbordo,
            default => false,
        };
    }

    /**
     * Get supported webservices array
     */
    public function getSupportedWebservices(): array
    {
        $webservices = [];

        if ($this->supports_anticipada) $webservices[] = 'anticipada';
        if ($this->supports_micdta) $webservices[] = 'micdta';
        if ($this->supports_desconsolidado) $webservices[] = 'desconsolidado';
        if ($this->supports_transbordo) $webservices[] = 'transbordo';

        return $webservices;
    }

    /**
     * Get webservice code for specific service
     */
    public function getWebserviceCode(string $webservice = null): string
    {
        if ($webservice && isset($this->webservice_config[$webservice]['code'])) {
            return $this->webservice_config[$webservice]['code'];
        }

        return $this->webservice_code ?? $this->code;
    }

    // ========================================
    // OPERATIONAL METHODS
    // ========================================

    /**
     * Check if handles specific cargo type
     */
    public function handlesCargo(string $cargoType): bool
    {
        return match($cargoType) {
            'maritime' => $this->handles_maritime,
            'fluvial' => $this->handles_fluvial,
            'containers' => $this->handles_containers,
            'bulk' => $this->handles_bulk_cargo,
            'passengers' => $this->handles_passengers,
            default => false,
        };
    }

    /**
     * Get handling capabilities array
     */
    public function getHandlingCapabilities(): array
    {
        $capabilities = [];

        if ($this->handles_maritime) $capabilities[] = 'maritime';
        if ($this->handles_fluvial) $capabilities[] = 'fluvial';
        if ($this->handles_containers) $capabilities[] = 'containers';
        if ($this->handles_bulk_cargo) $capabilities[] = 'bulk_cargo';
        if ($this->handles_passengers) $capabilities[] = 'passengers';

        return $capabilities;
    }

    /**
     * Check if office is currently open
     */
    public function isCurrentlyOpen(): bool
    {
        if ($this->operates_24h) {
            return true;
        }

        if (empty($this->operating_hours)) {
            return true; // Assume open if no schedule defined
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        if (!isset($this->operating_hours[$currentDay])) {
            return false; // Closed on this day
        }

        $schedule = $this->operating_hours[$currentDay];

        if ($schedule['closed'] ?? false) {
            return false;
        }

        return $currentTime >= $schedule['open'] && $currentTime <= $schedule['close'];
    }

    /**
     * Get today's operating hours
     */
    public function getTodayHours(): ?array
    {
        if ($this->operates_24h) {
            return ['open' => '00:00', 'close' => '23:59', '24h' => true];
        }

        if (empty($this->operating_hours)) {
            return null;
        }

        $currentDay = strtolower(now()->format('l'));

        return $this->operating_hours[$currentDay] ?? null;
    }

    // ========================================
    // GEOGRAPHIC METHODS
    // ========================================

    /**
     * Calculate distance to another customs office (in km)
     */
    public function distanceTo(CustomOffice $other): ?float
    {
        if (!$this->latitude || !$this->longitude || !$other->latitude || !$other->longitude) {
            return null;
        }

        $earthRadius = 6371; // Earth radius in kilometers

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($other->latitude);
        $lon2 = deg2rad($other->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat/2) * sin($deltaLat/2) + cos($lat1) * cos($lat2) * sin($deltaLon/2) * sin($deltaLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Get coordinates for mapping
     */
    public function getCoordinates(): ?array
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Get full address string
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province_state,
            $this->postal_code,
            $this->country->name ?? null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get display name with type
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . ucfirst($this->office_type) . ')';
    }

    /**
     * Check if it's a port customs office
     */
    public function isPort(): bool
    {
        return $this->office_type === 'port';
    }

    /**
     * Check if it's an airport customs office
     */
    public function isAirport(): bool
    {
        return $this->office_type === 'airport';
    }

    /**
     * Check if it's a border customs office
     */
    public function isBorder(): bool
    {
        return $this->office_type === 'border';
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    /**
     * Get customs offices for select dropdown
     */
    public static function forSelect(?int $countryId = null): array
    {
        $query = static::active()->ordered();

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * Get customs offices by webservice support
     */
    public static function supportingWebservice(string $webservice, ?int $countryId = null)
    {
        $query = static::active()->withWebserviceSupport($webservice);

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->ordered()->get();
    }

    /**
     * Get port customs offices
     */
    public static function portOffices(?int $countryId = null)
    {
        $query = static::active()->byType('port');

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->ordered()->get();
    }
}
