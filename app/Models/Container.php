<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MODELO CONTAINER - SIMPLE Y DIRECTO
 * 
 * Para datos reales PARANA: 6 tipos de contenedores
 * 40HC, 20DV, 40DV, 40FR, 20TN, 40RH
 */
class Container extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_of_lading_id',
        'container_number',
        'container_type',
        'container_status', 
        'seal_number',
        'gross_weight',
        'net_weight',
        'tare_weight',
        'volume',
        'package_count',
        'package_type',
        'cargo_description',
        'hazmat_info',
        'created_by_user_id'
    ];

    protected $casts = [
        'gross_weight' => 'decimal:2',
        'net_weight' => 'decimal:2', 
        'tare_weight' => 'decimal:2',
        'volume' => 'decimal:3',
        'package_count' => 'integer',
        'hazmat_info' => 'array'
    ];

    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class);
    }
}