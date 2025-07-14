<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Port extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'local_name',
        'country_id',
        'city',
        'province_state',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'water_depth',
        'port_type',
        'port_category',
        'handles_containers',
        'handles_bulk_cargo',
        'handles_general_cargo',
        'handles_passengers',
        'handles_dangerous_goods',
        'has_customs_office',
        'max_vessel_length',
        'max_draft',
        'berths_count',
        'storage_area',
        'has_crane',
        'has_warehouse',
        'primary_customs_office_id',
        'port_authority',
        'timezone',
        'webservice_code',
        'webservice_config',
        'supports_anticipada',
        'supports_micdta',
        'supports_manifest',
        'operating_hours',
        'operates_24h',
        'tide_information',
        'navigation_restrictions',
        'has_pilot_service',
        'has_tugboat_service',
        'has_fuel_service',
        'has_fresh_water',
        'has_waste_disposal',
        'available_services',
        'phone',
        'fax',
        'email',
        'website',
        'vhf_channel',
        'tariff_structure',
        'currency_code',
        'active',
        'accepts_new_vessels',
        'display_order',
        'established_date',
        'restrictions',
        'required_documents',
        'special_notes',
        'created_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'water_depth' => 'decimal:2',
        'max_draft' => 'decimal:2',
        'storage_area' => 'decimal:2',
        'handles_containers' => 'boolean',
        'handles_bulk_cargo' => 'boolean',
        'handles_general_cargo' => 'boolean',
        'handles_passengers' => 'boolean',
        'handles_dangerous_goods' => 'boolean',
        'has_customs_office' => 'boolean',
        'has_crane' => 'boolean',
        'has_warehouse' => 'boolean',
        'supports_anticipada' => 'boolean',
        'supports_micdta' => 'boolean',
        'supports_manifest' => 'boolean',
        'operates_24h' => 'boolean',
        'has_pilot_service' => 'boolean',
        'has_tugboat_service' => 'boolean',
        'has_fuel_service' => 'boolean',
        'has_fresh_water' => 'boolean',
        'has_waste_disposal' => 'boolean',
        'active' => 'boolean',
        'accepts_new_vessels' => 'boolean',
        'webservice_config' => 'array',
        'operating_hours' => 'array',
        'tide_information' => 'array',
        'navigation_restrictions' => 'array',
        'available_services' => 'array',
        'tariff_structure' => 'array',
        'restrictions' => 'array',
        'required_documents' => 'array',
        'max_vessel_length' => 'integer',
        'berths_count' => 'integer',
        'display_order' => 'integer',
        'established_date' => 'date',
        'created_date' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Country this port belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Primary customs office for this port
     */
    public function primaryCustomsOffice(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'primary_customs_office_id');
    }

    /**
     * User who created this record
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Clients using this port (will be implemented in Phase 1)
     */
    // public function clients(): HasMany
    // {
    //     return $this->hasMany(Client::class, 'primary_port_id');
    // }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active ports
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for ports by country
     */
    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope for ports by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('port_type', $type);
    }

    /**
     * Scope for ports by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('port_category', $category);
    }

    /**
     * Scope for ports accepting new vessels
     */
    public function scopeAccepting($query)
    {
        return $query->where('accepts_new_vessels', true);
    }

    /**
     * Scope for river ports
     */
    public function scopeRiver($query)
    {
        return $query->where('port_type', 'river');
    }

    /**
     * Scope for maritime ports
     */
    public function scopeMaritime($query)
    {
        return $query->where('port_type', 'maritime');
    }

    /**
     * Scope for container handling ports
     */
    public function scopeHandlesContainers($query)
    {
        return $query->where('handles_containers', true);
    }

    /**
     * Scope for bulk cargo handling ports
     */
    public function scopeHandlesBulk($query)
    {
        return $query->where('handles_bulk_cargo', true);
    }

    /**
     * Scope for ports with customs office
     */
    public function scopeWithCustoms($query)
    {
        return $query->where('has_customs_office', true);
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
    // CARGO HANDLING METHODS
    // ========================================

    /**
     * Check if port handles specific cargo type
     */
    public function handlesCargo(string $cargoType): bool
    {
        return match($cargoType) {
            'containers' => $this->handles_containers,
            'bulk' => $this->handles_bulk_cargo,
            'general' => $this->handles_general_cargo,
            'passengers' => $this->handles_passengers,
            'dangerous' => $this->handles_dangerous_goods,
            default => false,
        };
    }

    /**
     * Get all handling capabilities
     */
    public function getHandlingCapabilities(): array
    {
        $capabilities = [];

        if ($this->handles_containers) $capabilities[] = 'containers';
        if ($this->handles_bulk_cargo) $capabilities[] = 'bulk_cargo';
        if ($this->handles_general_cargo) $capabilities[] = 'general_cargo';
        if ($this->handles_passengers) $capabilities[] = 'passengers';
        if ($this->handles_dangerous_goods) $capabilities[] = 'dangerous_goods';

        return $capabilities;
    }

    /**
     * Check if port can accommodate vessel
     */
    public function canAccommodateVessel(float $length, float $draft): bool
    {
        $lengthOk = !$this->max_vessel_length || $length <= $this->max_vessel_length;
        $draftOk = !$this->max_draft || $draft <= $this->max_draft;
        $depthOk = !$this->water_depth || $draft <= $this->water_depth;

        return $lengthOk && $draftOk && $depthOk;
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
            'manifest' => $this->supports_manifest,
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
        if ($this->supports_manifest) $webservices[] = 'manifest';

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
    // SERVICES METHODS
    // ========================================

    /**
     * Check if port has specific service
     */
    public function hasService(string $service): bool
    {
        return match($service) {
            'pilot' => $this->has_pilot_service,
            'tugboat' => $this->has_tugboat_service,
            'fuel' => $this->has_fuel_service,
            'water' => $this->has_fresh_water,
            'waste' => $this->has_waste_disposal,
            'crane' => $this->has_crane,
            'warehouse' => $this->has_warehouse,
            default => false,
        };
    }

    /**
     * Get all available services
     */
    public function getAvailableServicesList(): array
    {
        $services = [];

        if ($this->has_pilot_service) $services[] = 'pilot';
        if ($this->has_tugboat_service) $services[] = 'tugboat';
        if ($this->has_fuel_service) $services[] = 'fuel';
        if ($this->has_fresh_water) $services[] = 'fresh_water';
        if ($this->has_waste_disposal) $services[] = 'waste_disposal';
        if ($this->has_crane) $services[] = 'crane';
        if ($this->has_warehouse) $services[] = 'warehouse';

        // Add additional services from JSON
        if ($this->available_services) {
            $services = array_merge($services, $this->available_services);
        }

        return array_unique($services);
    }

    // ========================================
    // OPERATIONAL METHODS
    // ========================================

    /**
     * Check if port is currently open
     */
    public function isCurrentlyOpen(): bool
    {
        if ($this->operates_24h) {
            return true;
        }

        if (empty($this->operating_hours)) {
            return true; // Assume open if no schedule defined
        }

        $currentDay = strtolower(now($this->timezone ?? 'UTC')->format('l'));
        $currentTime = now($this->timezone ?? 'UTC')->format('H:i');

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

        $currentDay = strtolower(now($this->timezone ?? 'UTC')->format('l'));

        return $this->operating_hours[$currentDay] ?? null;
    }

    /**
     * Get tide information for today
     */
    public function getTodayTides(): ?array
    {
        if (empty($this->tide_information)) {
            return null;
        }

        $today = now($this->timezone ?? 'UTC')->format('Y-m-d');

        return $this->tide_information[$today] ?? $this->tide_information['default'] ?? null;
    }

    // ========================================
    // GEOGRAPHIC METHODS
    // ========================================

    /**
     * Calculate distance to another port (in nautical miles)
     */
    public function distanceTo(Port $other): ?float
    {
        if (!$this->latitude || !$this->longitude || !$other->latitude || !$other->longitude) {
            return null;
        }

        $earthRadius = 3440; // Earth radius in nautical miles

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
     * Get coordinates for navigation
     */
    public function getCoordinates(): ?array
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
            'depth' => (float) $this->water_depth,
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
        return $this->name . ' (' . ucfirst($this->port_type) . ')';
    }

    /**
     * Check port types
     */
    public function isRiver(): bool
    {
        return $this->port_type === 'river';
    }

    public function isMaritime(): bool
    {
        return $this->port_type === 'maritime';
    }

    public function isMixed(): bool
    {
        return $this->port_type === 'mixed';
    }

    /**
     * Check port categories
     */
    public function isMajor(): bool
    {
        return $this->port_category === 'major';
    }

    public function isPrivate(): bool
    {
        return $this->port_category === 'private';
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    /**
     * Get ports for select dropdown
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
     * Get river ports
     */
    public static function riverPorts(?int $countryId = null)
    {
        $query = static::active()->river();

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->ordered()->get();
    }

    /**
     * Get maritime ports
     */
    public static function maritimePorts(?int $countryId = null)
    {
        $query = static::active()->maritime();

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->ordered()->get();
    }

    /**
     * Get ports supporting specific webservice
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
     * Get ports that can handle specific cargo
     */
    public static function handlingCargo(string $cargoType, ?int $countryId = null)
    {
        $query = static::active();

        match($cargoType) {
            'containers' => $query->handlesContainers(),
            'bulk' => $query->handlesBulk(),
            default => null,
        };

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->ordered()->get();
    }
}
