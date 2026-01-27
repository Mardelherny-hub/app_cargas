<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortAfipCustoms extends Model
{
    use HasFactory;

    protected $table = 'port_afip_customs';

    protected $fillable = [
        'port_id',
        'afip_customs_office_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    public function afipCustomsOffice(): BelongsTo
    {
        return $this->belongsTo(AfipCustomsOffice::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByPort($query, int $portId)
    {
        return $query->where('port_id', $portId);
    }
}