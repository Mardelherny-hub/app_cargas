<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Giro extends Model
{
    protected $table = 'giros';

    protected $fillable = [
        'codigo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Viajes que usan este giro.
     */
    public function voyages(): HasMany
    {
        return $this->hasMany(Voyage::class, 'giro_id');
    }
}