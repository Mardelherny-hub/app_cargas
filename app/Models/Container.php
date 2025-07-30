<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Container Model
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * Modelo para contenedores físicos específicos en el sistema
 * Cada contenedor puede asociarse con múltiples shipment_items
 * 
 * Basado en migración: create_containers_table.php
 * Compatible con webservices AR/PY
 * 
 * @property int $id
 * @property string $container_number
 * @property string|null $container_check_digit
 * @property string|null $full_container_number
 * @property int $container_type_id
 * @property int|null $vessel_owner_id
 * @property int|null $lessee_client_id
 * @property int|null $operator_client_id
 * @property int|null $current_port_id
 * @property int|null $last_port_id
 * @property float $tare_weight_kg
 * @property float $max_gross_weight_kg
 * @property float|null $current_gross_weight_kg
 * @property float|null $cargo_weight_kg
 * @property string $condition
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Container extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'containers';

    /**
     * The attributes that are mass assignable.
     * Basado en migración create_containers_table.php
     */
    protected $fillable = [
        'container_number',
        'container_check_digit',
        'full_container_number',
        'container_type_id',
        'vessel_owner_id',
        'lessee_client_id',
        'operator_client_id',
        'current_port_id',
        'last_port_id',
        'tare_weight_kg',
        'max_gross_weight_kg',
        'current_gross_weight_kg',
        'cargo_weight_kg',
        'condition',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Decimal fields
        'tare_weight_kg' => 'decimal:2',
        'max_gross_weight_kg' => 'decimal:2',
        'current_gross_weight_kg' => 'decimal:2',
        'cargo_weight_kg' => 'decimal:2',
    ];

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Tipo de contenedor
     */
    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }

    /**
     * Propietario del contenedor
     */
    public function vesselOwner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'vessel_owner_id');
    }

    /**
     * Arrendatario actual
     */
    public function lesseeClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'lessee_client_id');
    }

    /**
     * Operador responsable
     */
    public function operatorClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'operator_client_id');
    }

    /**
     * Puerto actual
     */
    public function currentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'current_port_id');
    }

    /**
     * Último puerto
     */
    public function lastPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'last_port_id');
    }

    /**
     * Items de shipment en este contenedor (many-to-many)
     */
    public function shipmentItems(): BelongsToMany
    {
        return $this->belongsToMany(ShipmentItem::class, 'container_shipment_item')
                    ->withPivot([
                        'package_quantity',
                        'gross_weight_kg', 
                        'net_weight_kg',
                        'volume_m3',
                        'quantity_percentage',
                        'weight_percentage',
                        'volume_percentage',
                        'loaded_at',
                        'sealed_at',
                        'loading_sequence'
                    ])
                    ->withTimestamps();
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Filtrar por condición
     */
    public function scopeByCondition(Builder $query, string $condition): Builder
    {
        return $query->where('condition', $condition);
    }

    /**
     * Contenedores vacíos
     */
    public function scopeEmpty(Builder $query): Builder
    {
        return $query->where('condition', 'V');
    }

    /**
     * Contenedores llenos
     */
    public function scopeLoaded(Builder $query): Builder
    {
        return $query->where('condition', 'L');
    }

    /**
     * Contenedores dañados
     */
    public function scopeDamaged(Builder $query): Builder
    {
        return $query->where('condition', 'D');
    }

    /**
     * Filtrar por tipo de contenedor
     */
    public function scopeByType(Builder $query, int $containerTypeId): Builder
    {
        return $query->where('container_type_id', $containerTypeId);
    }

    /**
     * Filtrar por puerto actual
     */
    public function scopeAtPort(Builder $query, int $portId): Builder
    {
        return $query->where('current_port_id', $portId);
    }

    /**
     * Filtrar por propietario
     */
    public function scopeByOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('vessel_owner_id', $ownerId);
    }
}