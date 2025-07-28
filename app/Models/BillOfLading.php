<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * MÓDULO 4 - PARTE 1: GESTIÓN DE DATOS PARA MANIFIESTOS
 * 
 * Modelo BillOfLading - Conocimientos de Embarque
 * Entidad central para el armado de manifiestos aduaneros
 * 
 * RELACIONES CONFIRMADAS DEL SISTEMA:
 * - shipments, clients, ports, customs_offices, cargo_types, packaging_types
 * 
 * COMPATIBLE CON WEBSERVICES AR/PY:
 * - RegistrarTitulosCbc (Argentina)
 * - Manifiestos de Carga (Paraguay)
 */
class BillOfLading extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'bills_of_lading';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Relaciones principales
        'shipment_id',
        'shipper_id',
        'consignee_id',
        'notify_party_id',
        'cargo_owner_id',
        
        // Puertos y aduanas
        'loading_port_id',
        'discharge_port_id',
        'transshipment_port_id',
        'final_destination_port_id',
        'loading_customs_id',
        'discharge_customs_id',
        
        // Tipos de carga y embalaje
        'primary_cargo_type_id',
        'primary_packaging_type_id',
        
        // Identificación del conocimiento
        'bill_number',
        'master_bill_number',
        'house_bill_number',
        'internal_reference',
        'bill_date',
        'manifest_number',
        'manifest_line_number',
        
        // Fechas operacionales
        'loading_date',
        'discharge_date',
        'arrival_date',
        'delivery_date',
        'cargo_ready_date',
        'free_time_expires_at',
        
        // Términos comerciales
        'freight_terms',
        'payment_terms',
        'incoterms',
        'currency_code',
        
        // Medidas y pesos
        'total_packages',
        'gross_weight_kg',
        'net_weight_kg',
        'volume_m3',
        'measurement_unit',
        'container_count',
        
        // Descripción de carga
        'cargo_description',
        'cargo_marks',
        'commodity_code',
        
        // Tipo y características del conocimiento
        'bill_type',
        
        // Estados y control
        'status',
        'priority_level',
        
        // Características especiales de carga
        'requires_inspection',
        'contains_dangerous_goods',
        'requires_refrigeration',
        'is_transhipment',
        'is_partial_shipment',
        'allows_partial_delivery',
        'requires_documents_on_arrival',
        
        // Consolidación
        'is_consolidated',
        'is_master_bill',
        'is_house_bill',
        'requires_surrender',
        
        // Mercancías peligrosas
        'un_number',
        'imdg_class',
        
        // Información financiera
        'freight_amount',
        'insurance_amount',
        'declared_value',
        'additional_charges',
        
        // Instrucciones y observaciones
        'special_instructions',
        'handling_instructions',
        'customs_remarks',
        'internal_notes',
        'loading_remarks',
        'discharge_remarks',
        'delivery_remarks',
        
        // Control de calidad y condición
        'cargo_condition_loading',
        'cargo_condition_discharge',
        'condition_remarks',
        
        // Verificación y discrepancias
        'verified_at',
        'verified_by_user_id',
        'has_discrepancies',
        'discrepancy_details',
        
        // Webservices integración
        'webservice_status',
        'webservice_reference',
        'webservice_sent_at',
        'webservice_response_at',
        'webservice_error_message',
        
        // Específicos para Argentina y Paraguay
        'argentina_bill_id',
        'paraguay_bill_id',
        'argentina_status',
        'paraguay_status',
        'argentina_sent_at',
        'paraguay_sent_at',
        'webservice_errors',
        
        // Entrega y recogida
        'delivery_address',
        'pickup_address',
        'delivery_contact_name',
        'delivery_contact_phone',
        'delivery_instructions',
        
        // Documentos
        'required_documents',
        'attached_documents',
        'original_released',
        'original_release_date',
        'documentation_complete',
        'ready_for_delivery',
        
        // Control aduanero
        'customs_cleared',
        'customs_bond_required',
        'customs_bond_number',
        
        // Auditoría
        'created_by_user_id',
        'last_updated_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Fechas
        'bill_date' => 'datetime',
        'loading_date' => 'datetime',
        'discharge_date' => 'datetime',
        'arrival_date' => 'datetime',
        'delivery_date' => 'datetime',
        'cargo_ready_date' => 'datetime',
        'free_time_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'webservice_sent_at' => 'datetime',
        'webservice_response_at' => 'datetime',
        'argentina_sent_at' => 'datetime',
        'paraguay_sent_at' => 'datetime',
        'original_release_date' => 'datetime',
        
        // Decimales
        'gross_weight_kg' => 'decimal:2',
        'net_weight_kg' => 'decimal:2',
        'volume_m3' => 'decimal:3',
        'freight_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'declared_value' => 'decimal:2',
        
        // Integers
        'total_packages' => 'integer',
        'container_count' => 'integer',
        'manifest_line_number' => 'integer',
        
        // Booleans
        'requires_inspection' => 'boolean',
        'contains_dangerous_goods' => 'boolean',
        'requires_refrigeration' => 'boolean',
        'is_transhipment' => 'boolean',
        'is_partial_shipment' => 'boolean',
        'allows_partial_delivery' => 'boolean',
        'requires_documents_on_arrival' => 'boolean',
        'is_consolidated' => 'boolean',
        'is_master_bill' => 'boolean',
        'is_house_bill' => 'boolean',
        'requires_surrender' => 'boolean',
        'has_discrepancies' => 'boolean',
        'original_released' => 'boolean',
        'documentation_complete' => 'boolean',
        'ready_for_delivery' => 'boolean',
        'customs_cleared' => 'boolean',
        'customs_bond_required' => 'boolean',
        
        // JSON
        'special_instructions' => 'json',
        'additional_charges' => 'json',
        'discrepancy_details' => 'json',
        'webservice_errors' => 'json',
        'delivery_instructions' => 'json',
        'required_documents' => 'json',
        'attached_documents' => 'json',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'internal_notes',
        'webservice_error_message',
    ];

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calcular campos derivados al guardar
        static::saving(function ($billOfLading) {
            // Calcular peso neto si no está definido
            if (is_null($billOfLading->net_weight_kg) && $billOfLading->gross_weight_kg) {
                $billOfLading->net_weight_kg = $billOfLading->gross_weight_kg * 0.85; // Estimación
            }
            
            // Validar fechas lógicas
            if ($billOfLading->discharge_date && $billOfLading->loading_date) {
                if ($billOfLading->discharge_date < $billOfLading->loading_date) {
                    throw new \InvalidArgumentException('La fecha de descarga no puede ser anterior a la de carga.');
                }
            }
            
            if ($billOfLading->arrival_date && $billOfLading->loading_date) {
                if ($billOfLading->arrival_date < $billOfLading->loading_date) {
                    throw new \InvalidArgumentException('La fecha de arribo no puede ser anterior a la de carga.');
                }
            }
        });
    }

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Envío al que pertenece este conocimiento
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Cargador/Exportador
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'shipper_id');
    }

    /**
     * Consignatario/Importador
     */
    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'consignee_id');
    }

    /**
     * Parte a notificar
     */
    public function notifyParty(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'notify_party_id');
    }

    /**
     * Propietario de la carga
     */
    public function cargoOwner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cargo_owner_id');
    }

    /**
     * Puerto de carga
     */
    public function loadingPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'loading_port_id');
    }

    /**
     * Puerto de descarga
     */
    public function dischargePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'discharge_port_id');
    }

    /**
     * Puerto de transbordo
     */
    public function transshipmentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'transshipment_port_id');
    }

    /**
     * Puerto de destino final
     */
    public function finalDestinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'final_destination_port_id');
    }

    /**
     * Aduana de carga
     */
    public function loadingCustoms(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'loading_customs_id');
    }

    /**
     * Aduana de descarga
     */
    public function dischargeCustoms(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'discharge_customs_id');
    }

    /**
     * Tipo principal de carga
     */
    public function primaryCargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'primary_cargo_type_id');
    }

    /**
     * Tipo principal de embalaje
     */
    public function primaryPackagingType(): BelongsTo
    {
        return $this->belongsTo(PackagingType::class, 'primary_packaging_type_id');
    }

    /**
     * Usuario que verificó el conocimiento
     */
    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Usuario que creó el registro
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
     * Ítems de mercadería de este conocimiento
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * Archivos adjuntos (relación polimórfica)
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Conocimientos activos (no cancelados)
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Conocimientos por estado
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Conocimientos pendientes de verificación
     */
    public function scopePendingVerification($query)
    {
        return $query->whereNull('verified_at')
                    ->where('status', 'draft');
    }

    /**
     * Conocimientos verificados
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Conocimientos con mercadería peligrosa
     */
    public function scopeDangerousGoods($query)
    {
        return $query->where('contains_dangerous_goods', true);
    }

    /**
     * Conocimientos que requieren refrigeración
     */
    public function scopeRefrigerated($query)
    {
        return $query->where('requires_refrigeration', true);
    }

    /**
     * Conocimientos de transbordo
     */
    public function scopeTransshipment($query)
    {
        return $query->where('is_transhipment', true);
    }

    /**
     * Conocimientos consolidados
     */
    public function scopeConsolidated($query)
    {
        return $query->where('is_consolidated', true);
    }

    /**
     * Conocimientos madre
     */
    public function scopeMasterBills($query)
    {
        return $query->where('is_master_bill', true);
    }

    /**
     * Conocimientos hijo
     */
    public function scopeHouseBills($query)
    {
        return $query->where('is_house_bill', true);
    }

    /**
     * Conocimientos por empresa (a través del shipment)
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->whereHas('shipment', function ($q) use ($companyId) {
            $q->whereHas('voyage', function ($vq) use ($companyId) {
                $vq->where('company_id', $companyId);
            });
        });
    }

    /**
     * Conocimientos por rango de fechas
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('bill_date', [$startDate, $endDate]);
    }

    /**
     * Conocimientos vencidos (tiempo libre)
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('free_time_expires_at')
                    ->where('free_time_expires_at', '<', now());
    }

    /**
     * Conocimientos próximos a vencer
     */
    public function scopeExpiringSoon($query, int $days = 3)
    {
        return $query->whereNotNull('free_time_expires_at')
                    ->whereBetween('free_time_expires_at', [
                        now(),
                        now()->addDays($days)
                    ]);
    }

    /**
     * Búsqueda por texto
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('bill_number', 'like', "%{$search}%")
              ->orWhere('master_bill_number', 'like', "%{$search}%")
              ->orWhere('house_bill_number', 'like', "%{$search}%")
              ->orWhere('manifest_number', 'like', "%{$search}%")
              ->orWhere('internal_reference', 'like', "%{$search}%")
              ->orWhere('cargo_description', 'like', "%{$search}%")
              ->orWhereHas('shipper', function ($sq) use ($search) {
                  $sq->where('legal_name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
              })
              ->orWhereHas('consignee', function ($sq) use ($search) {
                  $sq->where('legal_name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
              });
        });
    }

    // ========================================
    // MÉTODOS DE NEGOCIO
    // ========================================

    /**
     * Verificar el conocimiento de embarque
     */
    public function verify(User $user): bool
    {
        if ($this->verified_at) {
            return false; // Ya está verificado
        }

        $this->update([
            'verified_at' => now(),
            'verified_by_user_id' => $user->id,
            'status' => 'verified',
        ]);

        return true;
    }

    /**
     * Marcar como enviado a webservice
     */
    public function markAsSentToWebservice(string $reference): void
    {
        $this->update([
            'webservice_status' => 'sent',
            'webservice_reference' => $reference,
            'webservice_sent_at' => now(),
        ]);
    }

    /**
     * Marcar respuesta de webservice
     */
    public function markWebserviceResponse(bool $success, ?string $errorMessage = null): void
    {
        $this->update([
            'webservice_status' => $success ? 'accepted' : 'rejected',
            'webservice_response_at' => now(),
            'webservice_error_message' => $errorMessage,
        ]);
    }

    /**
     * Marcar como enviado a Argentina
     */
    public function markAsSentToArgentina(string $billId): void
    {
        $this->update([
            'argentina_bill_id' => $billId,
            'argentina_status' => 'sent',
            'argentina_sent_at' => now(),
        ]);
    }

    /**
     * Marcar como enviado a Paraguay
     */
    public function markAsSentToParaguay(string $billId): void
    {
        $this->update([
            'paraguay_bill_id' => $billId,
            'paraguay_status' => 'sent',
            'paraguay_sent_at' => now(),
        ]);
    }

    /**
     * Calcular peso total de ítems
     */
    public function calculateTotalItemsWeight(): float
    {
        return $this->shipmentItems()
                   ->sum('gross_weight_kg') ?? 0;
    }

    /**
     * Calcular volumen total de ítems
     */
    public function calculateTotalItemsVolume(): float
    {
        return $this->shipmentItems()
                   ->sum('volume_m3') ?? 0;
    }

    /**
     * Verificar si puede ser editado
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'pending_review']) 
               && is_null($this->webservice_sent_at)
               && is_null($this->argentina_sent_at)
               && is_null($this->paraguay_sent_at);
    }

    /**
     * Verificar si puede ser eliminado
     */
    public function canBeDeleted(): bool
    {
        return $this->status === 'draft' 
               && is_null($this->webservice_sent_at)
               && is_null($this->argentina_sent_at)
               && is_null($this->paraguay_sent_at)
               && $this->shipmentItems()->count() === 0;
    }

    /**
     * Verificar si está listo para envío a webservices
     */
    public function isReadyForWebservice(): bool
    {
        return $this->status === 'verified'
               && !is_null($this->verified_at)
               && is_null($this->webservice_sent_at)
               && $this->hasRequiredFields();
    }

    /**
     * Verificar campos requeridos
     */
    public function hasRequiredFields(): bool
    {
        return !empty($this->bill_number)
               && !empty($this->shipper_id)
               && !empty($this->consignee_id)
               && !empty($this->loading_port_id)
               && !empty($this->discharge_port_id)
               && !empty($this->cargo_description)
               && $this->gross_weight_kg > 0
               && $this->total_packages > 0;
    }

    /**
     * Obtener resumen para manifiestos
     */
    public function getManifestSummary(): array
    {
        return [
            'bill_number' => $this->bill_number,
            'manifest_line' => $this->manifest_line_number,
            'shipper_name' => $this->shipper->legal_name ?? 'N/A',
            'shipper_tax_id' => $this->shipper->tax_id ?? 'N/A',
            'consignee_name' => $this->consignee->legal_name ?? 'N/A',
            'consignee_tax_id' => $this->consignee->tax_id ?? 'N/A',
            'total_packages' => $this->total_packages,
            'gross_weight_kg' => $this->gross_weight_kg,
            'net_weight_kg' => $this->net_weight_kg,
            'volume_m3' => $this->volume_m3,
            'cargo_description' => $this->cargo_description,
            'cargo_type' => $this->primaryCargoType->name ?? 'N/A',
            'packaging_type' => $this->primaryPackagingType->name ?? 'N/A',
            'loading_port' => $this->loadingPort->name ?? 'N/A',
            'discharge_port' => $this->dischargePort->name ?? 'N/A',
            'commodity_code' => $this->commodity_code,
            'dangerous_goods' => $this->contains_dangerous_goods,
            'un_number' => $this->un_number,
            'freight_terms' => $this->freight_terms,
        ];
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    /**
     * Formatear número de conocimiento
     */
    public function getFormattedBillNumberAttribute(): string
    {
        return strtoupper($this->bill_number ?? '');
    }

    /**
     * Verificar si está vencido
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->free_time_expires_at 
               && $this->free_time_expires_at < now();
    }

    /**
     * Días hasta vencimiento
     */
    public function getDaysToExpirationAttribute(): ?int
    {
        if (!$this->free_time_expires_at) {
            return null;
        }

        return now()->diffInDays($this->free_time_expires_at, false);
    }

    /**
     * Estado para humanos
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente Revisión',
            'verified' => 'Verificado',
            'sent_to_customs' => 'Enviado a Aduana',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];

        return $labels[$this->status] ?? 'Desconocido';
    }

    /**
     * Tipo de conocimiento para humanos
     */
    public function getBillTypeLabelAttribute(): string
    {
        $labels = [
            'original' => 'Original',
            'copy' => 'Copia',
            'duplicate' => 'Duplicado',
            'amendment' => 'Enmienda',
        ];

        return $labels[$this->bill_type] ?? 'Original';
    }

    /**
     * Nivel de prioridad para humanos
     */
    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Baja',
            'normal' => 'Normal',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];

        return $labels[$this->priority_level] ?? 'Normal';
    }

    /**
     * Verificar si es conocimiento madre
     */
    public function getIsMasterAttribute(): bool
    {
        return $this->is_master_bill;
    }

    /**
     * Verificar si es conocimiento hijo
     */
    public function getIsHouseAttribute(): bool
    {
        return $this->is_house_bill;
    }

    /**
     * Peso por bulto
     */
    public function getWeightPerPackageAttribute(): float
    {
        return $this->total_packages > 0 
            ? round($this->gross_weight_kg / $this->total_packages, 2)
            : 0;
    }
}