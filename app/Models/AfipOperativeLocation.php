<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfipOperativeLocation extends Model
{
    use HasFactory;

    protected $table = 'afip_operative_locations';

    protected $fillable = [
        'country_id',
        'port_id',
        'customs_code',
        'location_code',
        'description',
        'is_foreign',
        'is_active',
    ];

    protected $casts = [
        'is_foreign' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeArgentina($query)
    {
        return $query->where('is_foreign', false);
    }

    public function scopeForeign($query)
    {
        return $query->where('is_foreign', true);
    }

    public function scopeByCustoms($query, string $customsCode)
    {
        return $query->where('customs_code', $customsCode);
    }
}