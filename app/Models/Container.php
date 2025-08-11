<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        // Campos que SÍ existen en la tabla real
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
        'condition_description',
        'damages',
        'requires_repair',
        'out_of_service',
        'customs_seal',
        'shipper_seal',
        'carrier_seal',
        'additional_seals',
        'current_location',
        'terminal_position',
        'operational_status',
        'requires_power',
        'has_gps_tracking',
        'temperature_controlled',
        'set_temperature',
        'requires_ventilation',
        'daily_storage_rate',
        'demurrage_rate',
        'free_days',
        'rate_currency',
        'webservice_container_id',
        'argentina_container_code',
        'paraguay_container_code',
        'webservice_data',
        'csc_certificate',
        'csc_expiry_date',
        'insurance_certificate',
        'certifications',
        'active',
        'blocked',
        'block_reason',
        'requires_customs_clearance',
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id'
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

    /**
     * Ítems de mercadería asignados a este contenedor
     * Relación many-to-many con datos adicionales en tabla pivote
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
                'loading_sequence',
                'position_in_container',
                'stowage_reference',
                'handling_instructions',
                'special_requirements',
                'customs_seal_number',
                'temperature_setting',
                'humidity_setting',
                'loading_notes',
                'inspector_notes',
                'quality_approved',
                'quality_notes',
                'requires_inspection',
                'inspection_type',
                'inspection_completed',
                'inspection_date',
                'inspector_user_id',
                'damaged_during_loading',
                'damage_description',
                'status',
                'has_discrepancies',
                'discrepancy_notes',
                'requires_review',
                'reviewed_by_user_id',
                'reviewed_at',
                'created_date',
                'created_by_user_id',
                'last_updated_date',
                'last_updated_by_user_id'
            ])
            ->withTimestamps();
    }
}