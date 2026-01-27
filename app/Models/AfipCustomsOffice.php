<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfipCustomsOffice extends Model
{
    use HasFactory;

    protected $table = 'afip_customs_offices';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function operativeLocations(): HasMany
    {
        return $this->hasMany(AfipOperativeLocation::class, 'customs_code', 'code');
    }

    /**
     * Puertos asociados a esta aduana AFIP
     */
    public function ports()
    {
        return $this->belongsToMany(Port::class, 'port_afip_customs')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}