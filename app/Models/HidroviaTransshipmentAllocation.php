<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Porción local de una línea de conocimiento destinada a una barcaza. */
class HidroviaTransshipmentAllocation extends Model
{
    protected $table = 'hidrovia_transshipment_allocations';

    protected $fillable = [
        'bill_of_lading_id',
        'shipment_item_id',
        'target_shipment_id',
        'container_id',
        'package_quantity',
        'gross_weight_kg',
        'created_by_user_id',
    ];

    protected $casts = [
        'package_quantity' => 'integer',
        'gross_weight_kg' => 'decimal:2',
    ];

    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class);
    }

    public function shipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }

    public function targetShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'target_shipment_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
