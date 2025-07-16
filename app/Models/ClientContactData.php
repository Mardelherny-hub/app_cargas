<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Modelo ClientContactData
 * 
 * Gestiona información de contacto detallada de clientes
 * Incluye emails, teléfonos, direcciones y preferencias de comunicación
 * Compatible con webservices Argentina y Paraguay
 * 
 * @property int $id
 * @property int $client_id
 * @property string|null $email
 * @property string|null $secondary_email
 * @property string|null $phone
 * @property string|null $mobile_phone
 * @property string|null $fax
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $contact_person_name
 * @property string|null $contact_person_position
 * @property string|null $contact_person_phone
 * @property string|null $contact_person_email
 * @property array|null $business_hours
 * @property string|null $timezone
 * @property array|null $communication_preferences
 * @property bool $accepts_email_notifications
 * @property bool $accepts_sms_notifications
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property bool $active
 * @property bool $is_primary
 * @property bool $verified
 * @property Carbon|null $verified_at
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read Client $client
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read string $full_address
 * @property-read string $primary_contact_info
 * @property-read array $notification_methods
 */
class ClientContactData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'client_contact_data';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'client_id',
        'email',
        'secondary_email',
        'phone',
        'mobile_phone',
        'fax',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'latitude',
        'longitude',
        'contact_person_name',
        'contact_person_position',
        'contact_person_phone',
        'contact_person_email',
        'business_hours',
        'timezone',
        'communication_preferences',
        'accepts_email_notifications',
        'accepts_sms_notifications',
        'notes',
        'internal_notes',
        'active',
        'is_primary',
        'verified',
        'verified_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'business_hours' => 'array',
        'communication_preferences' => 'array',
        'accepts_email_notifications' => 'boolean',
        'accepts_sms_notifications' => 'boolean',
        'active' => 'boolean',
        'is_primary' => 'boolean',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'internal_notes',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-asignar usuario creador
        static::creating(function ($model) {
            if (Auth::check() && !$model->created_by_user_id) {
                $model->created_by_user_id = Auth::id();
            }
            
            // Asegurar zona horaria por defecto según país del cliente
            if (!$model->timezone && $model->client) {
                $model->timezone = $model->client->country_id === 1 // Argentina
                    ? 'America/Argentina/Buenos_Aires'
                    : 'America/Asuncion'; // Paraguay
            }
        });

        // Auto-asignar usuario que actualiza
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by_user_id = Auth::id();
            }
        });

        // Asegurar que solo hay un contacto primario por cliente
        static::saving(function ($model) {
            if ($model->is_primary) {
                static::where('client_id', $model->client_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    // ===============================================
    // RELACIONES ELOQUENT
    // ===============================================

    /**
     * Relación con el cliente.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Usuario que creó el registro.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Usuario que actualizó el registro.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // ===============================================
    // ACCESSORS (GETTERS)
    // ===============================================

    /**
     * Obtener dirección completa formateada.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state_province,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Obtener información del contacto principal formateada.
     */
    public function getPrimaryContactInfoAttribute(): string
    {
        if (!$this->contact_person_name) {
            return 'No especificado';
        }

        $info = $this->contact_person_name;
        
        if ($this->contact_person_position) {
            $info .= ' (' . $this->contact_person_position . ')';
        }

        $contact_methods = [];
        if ($this->contact_person_email) {
            $contact_methods[] = $this->contact_person_email;
        }
        if ($this->contact_person_phone) {
            $contact_methods[] = $this->contact_person_phone;
        }

        if (!empty($contact_methods)) {
            $info .= ' - ' . implode(', ', $contact_methods);
        }

        return $info;
    }

    /**
     * Obtener métodos de notificación habilitados.
     */
    public function getNotificationMethodsAttribute(): array
    {
        $methods = [];

        if ($this->accepts_email_notifications && $this->email) {
            $methods[] = [
                'type' => 'email',
                'address' => $this->email,
                'label' => 'Email'
            ];
        }

        if ($this->accepts_sms_notifications && $this->mobile_phone) {
            $methods[] = [
                'type' => 'sms',
                'address' => $this->mobile_phone,
                'label' => 'SMS'
            ];
        }

        return $methods;
    }

    // ===============================================
    // MUTATORS (SETTERS)
    // ===============================================

    /**
     * Formatear email antes de guardar.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * Formatear email secundario antes de guardar.
     */
    public function setSecondaryEmailAttribute($value): void
    {
        $this->attributes['secondary_email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * Formatear email del contacto antes de guardar.
     */
    public function setContactPersonEmailAttribute($value): void
    {
        $this->attributes['contact_person_email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * Formatear teléfonos (remover caracteres no numéricos excepto +).
     */
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value ? preg_replace('/[^\d\+\-\(\)\s]/', '', trim($value)) : null;
    }

    public function setMobilePhoneAttribute($value): void
    {
        $this->attributes['mobile_phone'] = $value ? preg_replace('/[^\d\+\-\(\)\s]/', '', trim($value)) : null;
    }

    public function setFaxAttribute($value): void
    {
        $this->attributes['fax'] = $value ? preg_replace('/[^\d\+\-\(\)\s]/', '', trim($value)) : null;
    }

    public function setContactPersonPhoneAttribute($value): void
    {
        $this->attributes['contact_person_phone'] = $value ? preg_replace('/[^\d\+\-\(\)\s]/', '', trim($value)) : null;
    }

    // ===============================================
    // QUERY SCOPES
    // ===============================================

    /**
     * Scope para contactos activos.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Scope para contactos inactivos.
     */
    public function scopeInactive(Builder $query): void
    {
        $query->where('active', false);
    }

    /**
     * Scope para contactos primarios.
     */
    public function scopePrimary(Builder $query): void
    {
        $query->where('is_primary', true);
    }

    /**
     * Scope para contactos verificados.
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('verified', true);
    }

    /**
     * Scope para contactos no verificados.
     */
    public function scopeUnverified(Builder $query): void
    {
        $query->where('verified', false);
    }

    /**
     * Scope para buscar por email.
     */
    public function scopeByEmail(Builder $query, string $email): void
    {
        $email = strtolower(trim($email));
        $query->where(function ($q) use ($email) {
            $q->where('email', $email)
              ->orWhere('secondary_email', $email)
              ->orWhere('contact_person_email', $email);
        });
    }

    /**
     * Scope para buscar por teléfono.
     */
    public function scopeByPhone(Builder $query, string $phone): void
    {
        $phone = preg_replace('/[^\d]/', '', $phone); // Solo números
        $query->where(function ($q) use ($phone) {
            $q->whereRaw('REGEXP_REPLACE(phone, "[^0-9]", "") LIKE ?', ["%{$phone}%"])
              ->orWhereRaw('REGEXP_REPLACE(mobile_phone, "[^0-9]", "") LIKE ?', ["%{$phone}%"])
              ->orWhereRaw('REGEXP_REPLACE(contact_person_phone, "[^0-9]", "") LIKE ?', ["%{$phone}%"]);
        });
    }

    /**
     * Scope para buscar por ciudad.
     */
    public function scopeByCity(Builder $query, string $city): void
    {
        $query->where('city', 'like', "%{$city}%");
    }

    /**
     * Scope para contactos que aceptan notificaciones por email.
     */
    public function scopeAcceptsEmailNotifications(Builder $query): void
    {
        $query->where('accepts_email_notifications', true)
              ->whereNotNull('email');
    }

    /**
     * Scope para contactos que aceptan notificaciones por SMS.
     */
    public function scopeAcceptsSmsNotifications(Builder $query): void
    {
        $query->where('accepts_sms_notifications', true)
              ->whereNotNull('mobile_phone');
    }

    // ===============================================
    // MÉTODOS DE NEGOCIO
    // ===============================================

    /**
     * Marcar como contacto primario.
     */
    public function makePrimary(): bool
    {
        // Desmarcar otros contactos primarios del mismo cliente
        static::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Marcar este como primario
        return $this->update(['is_primary' => true]);
    }

    /**
     * Verificar información de contacto.
     */
    public function markAsVerified(?User $verifiedBy = null): bool
    {
        return $this->update([
            'verified' => true,
            'verified_at' => now(),
            'updated_by_user_id' => $verifiedBy?->id ?: Auth::id(),
        ]);
    }

    /**
     * Desmarcar verificación.
     */
    public function markAsUnverified(): bool
    {
        return $this->update([
            'verified' => false,
            'verified_at' => null,
        ]);
    }

    /**
     * Verificar si el contacto está completo.
     */
    public function isComplete(): bool
    {
        return !empty($this->email) && 
               !empty($this->phone) && 
               !empty($this->address_line_1) && 
               !empty($this->city);
    }

    /**
     * Obtener horarios de atención para un día específico.
     */
    public function getBusinessHoursForDay(string $day): ?array
    {
        if (!$this->business_hours || !isset($this->business_hours[$day])) {
            return null;
        }

        return $this->business_hours[$day];
    }

    /**
     * Verificar si está abierto en un momento específico.
     */
    public function isOpenAt(Carbon $datetime): bool
    {
        $day = strtolower($datetime->format('l')); // monday, tuesday, etc.
        $hours = $this->getBusinessHoursForDay($day);

        if (!$hours || !isset($hours['open']) || !isset($hours['close'])) {
            return false;
        }

        $openTime = Carbon::createFromFormat('H:i', $hours['open'], $this->timezone);
        $closeTime = Carbon::createFromFormat('H:i', $hours['close'], $this->timezone);
        $checkTime = $datetime->setTimezone($this->timezone);

        return $checkTime->between($openTime, $closeTime);
    }

    /**
     * Obtener preferencia de comunicación.
     */
    public function getCommunicationPreference(string $type): mixed
    {
        return $this->communication_preferences[$type] ?? null;
    }

    /**
     * Establecer preferencia de comunicación.
     */
    public function setCommunicationPreference(string $type, mixed $value): bool
    {
        $preferences = $this->communication_preferences ?: [];
        $preferences[$type] = $value;
        
        return $this->update(['communication_preferences' => $preferences]);
    }

    /**
     * Obtener distancia a coordenadas específicas (en kilómetros).
     */
    public function getDistanceTo(float $lat, float $lng): ?float
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        $earthRadius = 6371; // Radio de la Tierra en km

        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Generar resumen de contacto para logging.
     */
    public function getContactSummary(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'is_primary' => $this->is_primary,
            'verified' => $this->verified,
            'active' => $this->active,
        ];
    }

    /**
     * Convertir a array para webservices.
     */
    public function toWebserviceArray(): array
    {
        return [
            'email' => $this->email,
            'telefono' => $this->phone,
            'celular' => $this->mobile_phone,
            'direccion' => $this->full_address,
            'ciudad' => $this->city,
            'provincia' => $this->state_province,
            'codigo_postal' => $this->postal_code,
            'contacto' => $this->contact_person_name,
            'cargo_contacto' => $this->contact_person_position,
        ];
    }
}