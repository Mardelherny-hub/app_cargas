<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'certificate_expires_at' => 'datetime',
        'ws_config' => 'array',
        'ws_active' => 'boolean',
        'active' => 'boolean',
        'created_date' => 'datetime',
        'last_access' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'certificate_password',
    ];

    /**
     * Relación con operadores de la empresa.
     */
    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }

    /**
     * Relación polimórfica inversa con User.
     * Una empresa puede tener un usuario administrador.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Relación con operadores activos.
     */
    public function activeOperators(): HasMany
    {
        return $this->hasMany(Operator::class)->where('active', true);
    }

    /**
     * Relación con operadores externos.
     */
    public function externalOperators(): HasMany
    {
        return $this->hasMany(Operator::class)->where('type', 'external');
    }

    /**
     * Scope para empresas activas.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para empresas con certificados válidos.
     */
    public function scopeWithValidCertificates($query)
    {
        return $query->whereNotNull('certificate_path')
        ->where('certificate_expires_at', '>', now());
    }

    /**
     * Scope para empresas con certificados vencidos.
     */
    public function scopeWithExpiredCertificates($query)
    {
        return $query->whereNotNull('certificate_expires_at')
        ->where('certificate_expires_at', '<', now());
    }

    /**
     * Scope para empresas sin certificados.
     */
    public function scopeWithoutCertificates($query)
    {
        return $query->whereNull('certificate_path');
    }

    /**
     * Scope para empresas por país.
     */
    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Accessor para obtener el nombre completo de la empresa.
     */
    public function getFullNameAttribute()
    {
        return $this->commercial_name ?: $this->business_name;
    }

    /**
     * Accessor para obtener el nombre del país.
     */
    public function getCountryNameAttribute()
    {
        return $this->country === 'AR' ? 'Argentina' : 'Paraguay';
    }

    /**
     * Accessor para verificar si tiene certificado.
     */
    public function getHasCertificateAttribute()
    {
        return !empty($this->certificate_path);
    }

    /**
     * Accessor para verificar si el certificado está vencido.
     */
    public function getIsCertificateExpiredAttribute()
    {
        return $this->certificate_expires_at && $this->certificate_expires_at->isPast();
    }

    /**
     * Accessor para verificar si el certificado vence pronto.
     */
    public function getIsCertificateExpiringSoonAttribute()
    {
        return $this->certificate_expires_at &&
        $this->certificate_expires_at->isFuture() &&
        $this->certificate_expires_at->diffInDays(now()) <= 30;
    }

    /**
     * Accessor para obtener el estado del certificado.
     */
    public function getCertificateStatusAttribute()
    {
        if (!$this->has_certificate) {
            return 'none';
        }

        if ($this->is_certificate_expired) {
            return 'expired';
        }

        if ($this->is_certificate_expiring_soon) {
            return 'warning';
        }

        return 'valid';
    }

    /**
     * Accessor para obtener días hasta vencimiento del certificado.
     */
    public function getCertificateDaysToExpiryAttribute()
    {
        if (!$this->certificate_expires_at) {
            return null;
        }

        return now()->diffInDays($this->certificate_expires_at, false);
    }

    /**
     * Mutator para encriptar la contraseña del certificado.
     */
    public function setCertificatePasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['certificate_password'] = encrypt($value);
        }
    }

    /**
     * Accessor para desencriptar la contraseña del certificado.
     */
    public function getCertificatePasswordAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Verificar si la empresa tiene webservices activos.
     */
    public function hasActiveWebservices()
    {
        return $this->ws_active && $this->has_certificate && !$this->is_certificate_expired;
    }

    /**
     * Obtener la ruta completa del certificado.
     */
    public function getCertificateFullPath()
    {
        if ($this->certificate_path) {
            return Storage::path($this->certificate_path);
        }
        return null;
    }

    /**
     * Verificar si el certificado existe físicamente.
     */
    public function certificateExists()
    {
        return $this->certificate_path && Storage::exists($this->certificate_path);
    }

    /**
     * Eliminar el certificado físico.
     */
    public function deleteCertificate()
    {
        if ($this->certificate_path && Storage::exists($this->certificate_path)) {
            Storage::delete($this->certificate_path);
        }

        $this->update([
            'certificate_path' => null,
            'certificate_password' => null,
            'certificate_alias' => null,
            'certificate_expires_at' => null,
        ]);
    }

    /**
     * Actualizar último acceso.
     */
    public function updateLastAccess()
    {
        $this->update(['last_access' => now()]);
    }

    /**
     * Obtener configuración de webservices.
     */
    public function getWebserviceConfig($key = null, $default = null)
    {
        if ($key) {
            return data_get($this->ws_config, $key, $default);
        }
        return $this->ws_config;
    }

    /**
     * Establecer configuración de webservices.
     */
    public function setWebserviceConfig($key, $value = null)
    {
        if (is_array($key)) {
            $this->ws_config = array_merge($this->ws_config ?? [], $key);
        } else {
            $config = $this->ws_config ?? [];
            $config[$key] = $value;
            $this->ws_config = $config;
        }
        $this->save();
    }
}
