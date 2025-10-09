<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Shipment Model
 * 
 * Modelo para envíos individuales dentro de un viaje.
 * Cada embarcación en un convoy es un shipment.
 * Un viaje puede tener 1 shipment (barco único) o varios (convoy).
 * 
 * MÓDULO 3: VIAJES Y CARGAS
 * 
 * RELACIONES CORREGIDAS:
 * Shipment → BillsOfLading → ShipmentItems
 */
class Shipment extends Model
{
    use HasFactory;

    /**
     * Shipment Model
     * 
     * @property string|null $origin_manifest_id ID Manifiesto arribo país partida (trasbordo AFIP)
     * @property string|null $origin_transport_doc ID Título Transporte arribo país partida (trasbordo AFIP)
     */

    /**
     * The table associated with the model.
     */ 
    protected $table = 'shipments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Foreign keys
        'voyage_id',
        'vessel_id',
        'captain_id',
        
        // Shipment identification
        'shipment_number',
        'sequence_in_voyage',
        
        // Convoy configuration
        'vessel_role',
        'convoy_position',
        'is_lead_vessel',
        
        // Cargo capacity and loading
        'cargo_capacity_tons',
        'container_capacity',
        'cargo_weight_loaded',
        'containers_loaded',
        'utilization_percentage',
        
        // Shipment status
        'status',
        // Campos de trasbordo AFIP MIC/DTA
        'origin_manifest_id',
        'origin_transport_doc',
        
        // Operational times
        'departure_time',
        'arrival_time',
        'loading_start_time',
        'loading_end_time',
        'discharge_start_time',
        'discharge_end_time',
        
        // Operational status
        'safety_approved',
        'customs_cleared',
        'documentation_complete',
        'cargo_inspected',
        
        // Notes and special handling
        'special_instructions',
        'handling_notes',
        'delay_reason',
        'delay_minutes',
        
        // Status flags
        'active',
        'requires_attention',
        'has_delays',
        
        // Audit
        'created_date',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Decimal fields
        'cargo_capacity_tons' => 'decimal:2',
        'cargo_weight_loaded' => 'decimal:2',
        'utilization_percentage' => 'decimal:2',
        
        // Boolean fields
        'is_lead_vessel' => 'boolean',
        'safety_approved' => 'boolean',
        'customs_cleared' => 'boolean',
        'documentation_complete' => 'boolean',
        'cargo_inspected' => 'boolean',
        'active' => 'boolean',
        'requires_attention' => 'boolean',
        'has_delays' => 'boolean',
        
        // DateTime fields
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'loading_start_time' => 'datetime',
        'loading_end_time' => 'datetime',
        'discharge_start_time' => 'datetime',
        'discharge_end_time' => 'datetime',
        'created_date' => 'datetime',
    ];

    /**
     * Boot events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calcular utilization_percentage cuando se actualiza la carga
        static::saving(function ($shipment) {
            $shipment->calculateUtilization();
        });

        // Actualizar estadísticas del viaje cuando se modifica un shipment
        static::saved(function ($shipment) {
            $shipment->voyage->recalculateShipmentStats();
        });
        
        static::deleted(function ($shipment) {
            $shipment->voyage->recalculateShipmentStats();
        });
    }

    //
    // === RELATIONSHIPS ===
    //

    /**
     * Cliente dueño de la mercadería
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Tipo de carga
     */
    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'cargo_type_id');
    }

    /**
     * Tipo de embalaje
     */
    public function packagingType(): BelongsTo
    {
        return $this->belongsTo(PackagingType::class, 'packaging_type_id');
    }

    /**
     * Viaje al que pertenece este envío
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    /**
     * Embarcación de este envío
     */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    /**
     * Capitán de esta embarcación específica
     */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(Captain::class);
    }

    /**
     * Usuario que creó el envío
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Conocimientos de embarque específicos de este envío
     */
    public function billsOfLading(): HasMany
    {
        return $this->hasMany(BillOfLading::class);
    }

    /**
     * CORREGIDO: Ítems de mercadería a través de bills_of_lading
     * Esta es la relación correcta según la nueva estructura
     */
    public function shipmentItems()
    {
        return $this->hasManyThrough(
            ShipmentItem::class,
            BillOfLading::class,
            'shipment_id', // Foreign key en bills_of_lading
            'bill_of_lading_id', // Foreign key en shipment_items
            'id', // Local key en shipments
            'id' // Local key en bills_of_lading
        );
    }

    /**
     * Empresa propietaria del viaje (a través de voyage)
     */
    public function getCompanyAttribute()
    {
        return $this->voyage ? $this->voyage->company : null;
    }

    //
    // === MÉTODOS DE CÁLCULO CORREGIDOS ===
    //

    /**
     * CORREGIDO: Recalcular estadísticas de items del shipment
     */
    public function recalculateItemStats(): void
    {
        // Obtener items a través de la relación correcta
        $items = $this->shipmentItems;

        $totalWeight = $items->sum('gross_weight_kg');
        $totalVolume = $items->sum('volume_m3');
        $totalValue = $items->sum('declared_value');
        $totalPackages = $items->sum('package_quantity');
        
        // Características especiales
        $hasDangerousGoods = $items->where('is_dangerous_goods', true)->isNotEmpty();
        $requiresRefrigeration = $items->where('requires_refrigeration', true)->isNotEmpty();
        
        $this->updateItemStatistics(
            $totalWeight,
            $totalVolume, 
            $totalValue,
            $totalPackages,
            $hasDangerousGoods,
            $requiresRefrigeration
        );
    }

    /**
     * Actualizar estadísticas calculadas
     */
    private function updateItemStatistics(
        float $totalWeight,
        float $totalVolume,
        float $totalValue,
        int $totalPackages,
        bool $hasDangerousGoods,
        bool $requiresRefrigeration
    ): void {
        $this->update([
            'cargo_weight_loaded' => $totalWeight,
            'utilization_percentage' => $this->calculateWeightUtilization($totalWeight),
            'has_dangerous_cargo' => $hasDangerousGoods,
        ]);
    }

    /**
     * Calcular utilización por peso
     */
    private function calculateWeightUtilization(float $loadedWeight): float
    {
        if ($this->cargo_capacity_tons <= 0) {
            return 0;
        }
        
        return min(100, ($loadedWeight / ($this->cargo_capacity_tons * 1000)) * 100);
    }

    /**
     * CORREGIDO: Obtener resumen de items
     */
    public function getItemsSummary(): array
    {
        $items = $this->shipmentItems;
        
        return [
            'total_items' => $items->count(),
            'total_lines' => $items->max('line_number') ?? 0,
            'total_packages' => $items->sum('package_quantity'),
            'total_weight_kg' => $items->sum('gross_weight_kg'),
            'total_volume_m3' => $items->sum('volume_m3'),
            'total_value_usd' => $items->sum('declared_value'),
            'dangerous_goods_count' => $items->where('is_dangerous_goods', true)->count(),
            'perishable_count' => $items->where('is_perishable', true)->count(),
            'refrigerated_count' => $items->where('requires_refrigeration', true)->count(),
            'items_with_discrepancies' => $items->where('has_discrepancies', true)->count(),
            'items_requiring_review' => $items->where('requires_review', true)->count(),
        ];
    }

    /**
     * CORREGIDO: Obtener items por estado
     */
    public function getItemsByStatus(): array
    {
        return $this->shipmentItems()
                   ->selectRaw('status, COUNT(*) as count')
                   ->groupBy('status')
                   ->pluck('count', 'status')
                   ->toArray();
    }

    /**
     * Calcular utilización automáticamente
     */
    private function calculateUtilization(): void
    {
        // Implementar lógica de cálculo de utilización
    }

       /**
     * Generar número de shipment para el seeder.
     * Método estático compatible con ShipmentSeeder.
     * 
     * @param Voyage $voyage - El viaje al que pertenece el shipment
     * @param int $sequenceInVoyage - La secuencia del shipment en el viaje (1, 2, 3...)
     * @return string - Número de shipment generado
     */
    public static function generateShipmentNumber($voyage, int $sequenceInVoyage): string
    {
        $year = now()->year;
        $company = $voyage->company;
        
        // Generar código de empresa (primeras 3 letras del nombre comercial)
        $companyCode = strtoupper(substr($company->commercial_name ?? $company->legal_name, 0, 3));
        
        // Buscar el último número para este año y empresa
        $lastShipment = self::whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->where('shipment_number', 'like', "{$companyCode}-{$year}-%")
            ->orderBy('shipment_number', 'desc')
            ->first();

        if ($lastShipment) {
            // Extraer el número secuencial del último shipment
            $parts = explode('-', $lastShipment->shipment_number);
            $lastNumber = isset($parts[2]) ? intval($parts[2]) : 0;
            $nextNumber = $lastNumber + $sequenceInVoyage;
        } else {
            $nextNumber = $sequenceInVoyage;
        }

        return sprintf('%s-%d-%04d', $companyCode, $year, $nextNumber);
    }

}