<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShipmentItem Model
 * 
 * Modelo para ítems de mercadería dentro de un shipment.
 * Cada línea de mercadería en un shipment es un ShipmentItem.
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * JERARQUÍA CORREGIDA: Voyages → Shipments → Shipment Items → (después) Bills of Lading
 * 
 * @property int $id
 * @property int|null $client_id
 * @property int $shipment_id
 * @property int $cargo_type_id
 * @property int $packaging_type_id
 * @property int $line_number
 * @property string|null $item_reference
 * @property string|null $lot_number
 * @property string|null $serial_number
 * @property int $package_quantity
 * @property decimal $gross_weight_kg
 * @property decimal|null $net_weight_kg
 * @property decimal|null $volume_m3
 * @property decimal|null $declared_value
 * @property string $currency_code
 * @property string $item_description
 * @property string|null $cargo_marks
 * @property string|null $commodity_code
 * @property string|null $commodity_description
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $manufacturer
 * @property string|null $country_of_origin
 * @property string|null $package_type_description
 * @property array|null $package_dimensions
 * @property int|null $units_per_package
 * @property string $unit_of_measure
 * @property bool $is_dangerous_goods
 * @property string|null $un_number
 * @property string|null $imdg_class
 * @property bool $is_perishable
 * @property bool $is_fragile
 * @property bool $requires_refrigeration
 * @property decimal|null $temperature_min
 * @property decimal|null $temperature_max
 * @property bool $requires_permit
 * @property string|null $permit_number
 * @property bool $requires_inspection
 * @property string|null $inspection_type
 * @property string|null $webservice_item_id
 * @property string|null $packaging_code
 * @property array|null $webservice_data
 * @property string $status
 * @property bool $has_discrepancies
 * @property string|null $discrepancy_notes
 * @property bool $requires_review
 * @property \Carbon\Carbon $created_date
 * @property int|null $created_by_user_id
 * @property \Carbon\Carbon $last_updated_date
 * @property int|null $last_updated_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ShipmentItem extends Model
{
    use HasFactory;

    /**
     * Obtener estado descriptivo
     */
    public function getStatusDescriptionAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Borrador',
            'validated' => 'Validado',
            'submitted' => 'Enviado a Aduana',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'modified' => 'Modificado',
            default => 'Desconocido'
        };
    }

    /**
     * Verificar si está listo para envío
     */
    public function getReadyForSubmissionAttribute(): bool
    {
        return $this->status === 'validated' && 
               !$this->has_discrepancies && 
               !$this->requires_review;
    }

    /**
     * Obtener color de estado para UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'validated' => 'blue',
            'submitted' => 'yellow',
            'accepted' => 'green',
            'rejected' => 'red',
            'modified' => 'orange',
            default => 'gray'
        };
    }

    //
    // === METHODS ===
    //

    /**
     * Validar ítem
     */
    public function validate(): void
    {
        $this->update([
            'status' => 'validated',
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Marcar como enviado a aduana
     */
    public function submit(): void
    {
        if ($this->status !== 'validated') {
            throw new \InvalidArgumentException('Solo se pueden enviar ítems validados');
        }

        $this->update([
            'status' => 'submitted',
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Aceptar ítem
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Rechazar ítem
     */
    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'discrepancy_notes' => $reason,
            'has_discrepancies' => true,
            'requires_review' => true,
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Reportar discrepancia
     */
    public function reportDiscrepancy(string $notes): void
    {
        $this->update([
            'has_discrepancies' => true,
            'discrepancy_notes' => $notes,
            'requires_review' => true,
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Resolver discrepancia
     */
    public function resolveDiscrepancy(?string $resolution = null): void
    {
        $this->update([
            'has_discrepancies' => false,
            'requires_review' => false,
            'discrepancy_notes' => $resolution,
            'status' => 'modified',
            'last_updated_date' => now(),
        ]);
    }

    /**
     * Calcular peso total por tipo de embalaje
     */
    public function getTotalWeightByPackageType(): array
    {
        return [
            'gross_total' => $this->package_quantity * $this->gross_weight_kg,
            'net_total' => $this->package_quantity * $this->effective_net_weight,
            'per_package' => [
                'gross' => $this->gross_weight_kg,
                'net' => $this->effective_net_weight,
            ],
        ];
    }

    /**
     * Verificar compatibilidad con temperatura
     */
    public function isTemperatureCompatible(?float $containerTemp): bool
    {
        if (!$this->requires_refrigeration || !$containerTemp) {
            return true;
        }

        return $containerTemp >= ($this->temperature_min ?? -100) && 
               $containerTemp <= ($this->temperature_max ?? 100);
    }

    /**
     * Obtener requisitos especiales
     */
    public function getSpecialRequirements(): array
    {
        $requirements = [];

        if ($this->is_dangerous_goods) {
            $requirements[] = [
                'type' => 'dangerous_goods',
                'description' => 'Mercancía Peligrosa',
                'un_number' => $this->un_number,
                'imdg_class' => $this->imdg_class,
            ];
        }

        if ($this->requires_refrigeration) {
            $requirements[] = [
                'type' => 'refrigeration',
                'description' => 'Requiere Refrigeración',
                'temp_min' => $this->temperature_min,
                'temp_max' => $this->temperature_max,
            ];
        }

        if ($this->requires_permit) {
            $requirements[] = [
                'type' => 'permit',
                'description' => 'Requiere Permiso',
                'permit_number' => $this->permit_number,
            ];
        }

        if ($this->requires_inspection) {
            $requirements[] = [
                'type' => 'inspection',
                'description' => 'Requiere Inspección',
                'inspection_type' => $this->inspection_type,
            ];
        }

        return $requirements;
    }

    /**
     * Generar datos para webservice
     */
    public function prepareWebserviceData(): array
    {
        return [
            'item_id' => $this->id,
            'line_number' => $this->line_number,
            'commodity_code' => $this->commodity_code,
            'description' => $this->item_description,
            'quantity' => $this->package_quantity,
            'gross_weight' => $this->gross_weight_kg,
            'net_weight' => $this->effective_net_weight,
            'declared_value' => $this->declared_value,
            'currency' => $this->currency_code,
            'packaging_code' => $this->packaging_code,
            'dangerous_goods' => $this->is_dangerous_goods,
            'un_number' => $this->un_number,
            'country_origin' => $this->country_of_origin,
            'special_requirements' => $this->special_requirements,
        ];
    }

    /**
     * Actualizar desde datos de webservice
     */
    public function updateFromWebservice(array $data): void
    {
        $updateData = [];

        // Mapear campos del webservice
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['validation_errors'])) {
            $updateData['has_discrepancies'] = !empty($data['validation_errors']);
            $updateData['discrepancy_notes'] = implode('; ', $data['validation_errors']);
        }

        if (isset($data['webservice_data'])) {
            $updateData['webservice_data'] = $data['webservice_data'];
        }

        $updateData['last_updated_date'] = now();

        $this->update($updateData);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Actualizar estadísticas del shipment cuando se modifica un item
        static::saved(function ($shipmentItem) {
            $shipmentItem->shipment->recalculateItemStats();
        });
        
        static::deleted(function ($shipmentItem) {
            $shipmentItem->shipment->recalculateItemStats();
        });
    }
    /**
     * Tabla asociada al modelo
     * LIMPIO - Solo campos que existen en migración
     */
    protected $table = 'shipment_items';

    /**
     * The attributes that are mass assignable.
     * LIMPIO - Solo campos que existen en migración
     */
    protected $fillable = [
        // Foreign keys
        'client_id',
        'shipment_id',
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
    ];

    /**
     * The attributes that should be cast.
     * LIMPIO - Solo campos que existen en migración
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
    ];

    //
    // === RELATIONSHIPS ===
    //

    /**
     * Shipment al que pertenece este ítem
     * CORREGIDO: Jerarquía correcta
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
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
     * Cliente dueño de la mercadería (opcional)
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
    
    //
    // === SCOPES ===
    //

    /**
     * Filtrar por shipment
     */
    public function scopeByShipment($query, $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId);
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
     * Que requieren refrigeración
     */
    public function scopeRequiresRefrigeration($query)
    {
        return $query->where('requires_refrigeration', true);
    }

    /**
     * Con discrepancias
     */
    public function scopeWithDiscrepancies($query)
    {
        return $query->where('has_discrepancies', true);
    }

    /**
     * Que requieren revisión
     */
    public function scopeRequiresReview($query)
    {
        return $query->where('requires_review', true);
    }

    /**
     * Por tipo de carga
     */
    public function scopeByCargoType($query, $cargoTypeId)
    {
        return $query->where('cargo_type_id', $cargoTypeId);
    }

    /**
     * Por código NCM
     */
    public function scopeByCommodityCode($query, $code)
    {
        return $query->where('commodity_code', $code);
    }

    /**
     * Ordenar por línea
     */
    public function scopeOrderByLine($query)
    {
        return $query->orderBy('line_number');
    }

    //
    // === ACCESSORS & MUTATORS ===
    //

    /**
     * Obtener referencia completa del ítem
     */
    public function getFullReferenceAttribute(): string
    {
        return $this->item_reference ?? 
               ($this->shipment->shipment_number . '-L' . str_pad($this->line_number, 3, '0', STR_PAD_LEFT));
    }

    /**
     * Verificar si tiene características especiales
     */
    public function getHasSpecialCharacteristicsAttribute(): bool
    {
        return $this->is_dangerous_goods || 
               $this->is_perishable || 
               $this->is_fragile || 
               $this->requires_refrigeration;
    }

    /**
     * Obtener peso neto o calculado
     */
    public function getEffectiveNetWeightAttribute(): float
    {
        return $this->net_weight_kg ?? ($this->gross_weight_kg * 0.85); // 85% del bruto por defecto
    }

    /**
     * Calcular densidad
     */
    public function getDensityAttribute(): ?float
    {
        if (!$this->volume_m3 || $this->volume_m3 <= 0) {
            return null;
        }
        
        return $this->gross_weight_kg / $this->volume_m3;
    }

    /**
     * Obtener valor por kg
     */
    public function getValuePerKgAttribute(): ?float
    {
        if (!$this->declared_value || $this->gross_weight_kg <= 0) {
            return null;
        }
        
        return $this->declared_value / $this->gross_weight_kg;
    }

}