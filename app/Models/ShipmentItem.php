<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// CORREGIDO: Agregar use statements faltantes
use App\Models\BillOfLading;
use App\Models\Shipment;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\Client;
use App\Models\User;
use App\Models\Container;

/**
 * ShipmentItem Model
 * 
 * Modelo para ítems de mercadería dentro de un conocimiento de embarque.
 * Cada línea de mercadería en un BillOfLading es un ShipmentItem.
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * JERARQUÍA CORREGIDA: Voyages → Shipments → BillsOfLading → ShipmentItems
 */
class ShipmentItem extends Model
{
    use HasFactory;

    /**
     * Constantes para campos AFIP
     */
    public const AFIP_YES = 'S';
    public const AFIP_NO = 'N';

    public const SECURE_LOGISTICS_OPTIONS = [
        self::AFIP_YES => 'Sí - Operador Logístico Seguro',
        self::AFIP_NO => 'No - Operador Regular'
    ];

    public const MONITORED_TRANSIT_OPTIONS = [
        self::AFIP_YES => 'Sí - Tránsito Monitoreado', 
        self::AFIP_NO => 'No - Tránsito Regular'
    ];

    public const RENAR_OPTIONS = [
        self::AFIP_YES => 'Sí - Sujeto a RENAR',
        self::AFIP_NO => 'No - No sujeto a RENAR'
    ];

    /**
     * CORREGIDO: Boot events para actualizar estadísticas del shipment y bill of lading
     */
    protected static function boot()
    {
        parent::boot();
        
        // Actualizar estadísticas cuando se modifica un item
        static::saved(function ($shipmentItem) {
            // CORREGIDO: Recalcular estadísticas del bill of lading
            if ($shipmentItem->billOfLading) {
                $shipmentItem->billOfLading->recalculateItemStats();
                
                // También recalcular estadísticas del shipment
                if ($shipmentItem->billOfLading->shipment) {
                    $shipmentItem->billOfLading->shipment->recalculateItemStats();
                }
            }
        });
        
        static::deleted(function ($shipmentItem) {
            // CORREGIDO: Recalcular estadísticas del bill of lading
            if ($shipmentItem->billOfLading) {
                $shipmentItem->billOfLading->recalculateItemStats();
                
                // También recalcular estadísticas del shipment
                if ($shipmentItem->billOfLading->shipment) {
                    $shipmentItem->billOfLading->shipment->recalculateItemStats();
                }
            }
        });
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'shipment_items';

    /**
     * The attributes that are mass assignable.
     * CORREGIDO - Usar bill_of_lading_id en lugar de shipment_id
     */
    protected $fillable = [
        // Foreign keys
        'bill_of_lading_id', // CORREGIDO: Cambiar de shipment_id a bill_of_lading_id
        'cargo_type_id',
        'packaging_type_id',
        
        // Item identification
        'line_number',
        'item_reference',
        'lot_number',
        'serial_number',
        
        // Quantities and measurements
        'package_quantity',
        'gross_weight_kg',
        'net_weight_kg',
        'volume_m3',
        'declared_value',
        'currency_code',
        
        // Descriptions
        'item_description',
        'cargo_marks',
        'commodity_code',
        'commodity_description',
        'brand',
        'model',
        'manufacturer',
        'country_of_origin',
        
        // Package details
        'package_type_description',
        'package_dimensions',
        'units_per_package',
        'unit_of_measure',
        
        // Special characteristics
        'is_dangerous_goods',
        'un_number',
        'imdg_class',
        'is_perishable',
        'is_fragile',
        'requires_refrigeration',
        'temperature_min',
        'temperature_max',
        
        // Regulatory
        'requires_permit',
        'permit_number',
        'requires_inspection',
        'inspection_type',
        
        // Webservice integration
        'webservice_item_id',
        'packaging_code',
        'webservice_data',
        
        // Status
        'status',
        'has_discrepancies',
        'discrepancy_notes',
        'requires_review',
        
        // Audit
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',

        // NUEVOS CAMPOS AFIP 
        'tariff_position',
        'is_secure_logistics_operator',
        'is_monitored_transit', 
        'is_renar',
        'foreign_forwarder_name',
        'foreign_forwarder_tax_id',
        'foreign_forwarder_country',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Decimal fields
        'gross_weight_kg' => 'decimal:2',
        'net_weight_kg' => 'decimal:2',
        'volume_m3' => 'decimal:3',
        'declared_value' => 'decimal:2',
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
        
        // Boolean fields
        'is_dangerous_goods' => 'boolean',
        'is_perishable' => 'boolean',
        'is_fragile' => 'boolean',
        'requires_refrigeration' => 'boolean',
        'requires_permit' => 'boolean',
        'requires_inspection' => 'boolean',
        'has_discrepancies' => 'boolean',
        'requires_review' => 'boolean',
        
        // JSON fields
        'package_dimensions' => 'array',
        'webservice_data' => 'array',
        
        // DateTime fields
        'created_date' => 'datetime',
        'last_updated_date' => 'datetime',

        // NUEVOS CASTS AFIP 
        'is_secure_logistics_operator' => 'string',
        'is_monitored_transit' => 'string',
        'is_renar' => 'string',
        
    ];

    //
    // === RELATIONSHIPS CORREGIDAS ===
    //

    /**
     * CORREGIDO: Bill of Lading al que pertenece este ítem
     * La relación correcta es bill_of_lading_id → bills_of_lading.id
     */
    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class, 'bill_of_lading_id');
    }

    /**
     * CORREGIDO: Shipment al que pertenece este ítem (a través de bill_of_lading)
     * Método de conveniencia para mantener compatibilidad
     */
    public function shipment()
    {
        return $this->billOfLading ? $this->billOfLading->shipment : null;
    }

    /**
     * CORREGIDO: Atributo accessor para obtener el shipment
     * Para compatibilidad con código existente que usa $item->shipment
     */
    public function getShipmentAttribute()
    {
        return $this->billOfLading ? $this->billOfLading->shipment : null;
    }

    /**
     * Tipo de carga
     */
    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class);
    }

    /**
     * Tipo de embalaje
     */
    public function packagingType(): BelongsTo
    {
        return $this->belongsTo(PackagingType::class);
    }

    /**
     * Usuario que creó el ítem
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Usuario que actualizó por última vez
     */
    public function lastUpdatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    /**
     * CORREGIDO: Contenedores asignados a este ítem
     */
    public function containers(): BelongsToMany
    {
        return $this->belongsToMany(Container::class, 'container_shipment_item')
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
                'loading_sequence',
                'status'
            ])
            ->withTimestamps();
    }
    
    //
    // === SCOPES CORREGIDOS ===
    //

    /**
     * CORREGIDO: Filtrar por bill of lading
     */
    public function scopeByBillOfLading($query, $billOfLadingId)
    {
        return $query->where('bill_of_lading_id', $billOfLadingId);
    }

    /**
     * CORREGIDO: Filtrar por shipment (a través de bill of lading)
     */
    public function scopeByShipment($query, $shipmentId)
    {
        return $query->whereHas('billOfLading', function($q) use ($shipmentId) {
            $q->where('shipment_id', $shipmentId);
        });
    }

    /**
     * Filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Solo mercancías peligrosas
     */
    public function scopeDangerousGoods($query)
    {
        return $query->where('is_dangerous_goods', true);
    }

    /**
     * Solo perecederos
     */
    public function scopePerishable($query)
    {
        return $query->where('is_perishable', true);
    }

    /**
     * Solo refrigerados
     */
    public function scopeRefrigerated($query)
    {
        return $query->where('requires_refrigeration', true);
    }

    /**
     * MÉTODOS HELPER PARA CAMPOS AFIP
     */

    /**
     * Verificar si es operador logístico seguro
     */
    public function isSecureLogisticsOperator(): bool
    {
        return $this->is_secure_logistics_operator === self::AFIP_YES;
    }

    /**
     * Verificar si es tránsito monitoreado
     */
    public function isMonitoredTransit(): bool
    {
        return $this->is_monitored_transit === self::AFIP_YES;
    }

    /**
     * Verificar si está sujeto a RENAR
     */
    public function isRenar(): bool
    {
        return $this->is_renar === self::AFIP_YES;
    }

    /**
     * Obtener descripción del operador logístico
     */
    public function getSecureLogisticsDescription(): string
    {
        return self::SECURE_LOGISTICS_OPTIONS[$this->is_secure_logistics_operator] ?? 'No especificado';
    }

    /**
     * Validar posición arancelaria
     */
    public function hasValidTariffPosition(): bool
    {
        if (empty($this->tariff_position)) {
            return false;
        }
        
        // AFIP requiere entre 7 y 15 caracteres (incluyendo puntos)
        $length = strlen($this->tariff_position);
        return $length >= 7 && $length <= 15;
    }

    /**
     * Validar datos completos para AFIP
     */
    public function isAfipCompliant(): bool
    {
        return !empty($this->tariff_position) &&
            !empty($this->foreign_forwarder_name) &&
            in_array($this->is_secure_logistics_operator, [self::AFIP_YES, self::AFIP_NO]) &&
            in_array($this->is_monitored_transit, [self::AFIP_YES, self::AFIP_NO]) &&
            in_array($this->is_renar, [self::AFIP_YES, self::AFIP_NO]);
    }
}