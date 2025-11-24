<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Modelo WebserviceResponse
 *
 * Modelo para respuestas estructuradas de webservices aduaneros.
 * Almacena información procesada y extraída de las respuestas XML/SOAP
 * para uso posterior en el sistema.
 *
 * @property int $id
 * @property int $transaction_id
 * @property string $response_type
 * @property bool $requires_action
 * @property string $processing_status
 * @property string|null $confirmation_number
 * @property string|null $reference_number
 * @property string|null $voyage_number
 * @property string|null $manifest_number
 * @property array|null $tracking_numbers
 * @property array|null $container_numbers
 * @property array|null $bill_of_lading_numbers
 * @property array|null $customs_data
 * @property string|null $customs_status
 * @property Carbon|null $customs_processed_at
 * @property array|null $validation_errors
 * @property array|null $validation_warnings
 * @property array|null $business_errors
 * @property bool $urgent_action_required
 * @property Carbon|null $action_deadline
 * @property string|null $action_description
 * @property array|null $next_steps
 * @property string|null $payment_status
 * @property float|null $customs_fees
 * @property string|null $currency_code
 * @property array|null $fee_breakdown
 * @property bool $documents_required
 * @property array|null $required_documents
 * @property Carbon|null $documents_due_date
 * @property bool $documents_approved
 * @property array|null $approved_documents
 * @property array|null $rejected_documents
 * @property array|null $additional_data
 * @property array|null $integration_metadata
 * @property string|null $external_system_id
 * @property string|null $external_reference
 * @property Carbon|null $processed_at
 * @property Carbon|null $last_updated_at
 * @property bool $is_final_response
 * @property bool $is_archived
 * @property Carbon|null $archive_date
 * @property string $retention_level
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * // Relationships
 * @property WebserviceTransaction $transaction
 */
class WebserviceResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'response_type',
        'requires_action',
        'processing_status',
        'confirmation_number',
        'reference_number',
        'voyage_number',
        'manifest_number',
        'tracking_numbers',
        'container_numbers',
        'bill_of_lading_numbers',
        'customs_data',
        'customs_status',
        'customs_processed_at',
        'validation_errors',
        'validation_warnings',
        'business_errors',
        'urgent_action_required',
        'action_deadline',
        'action_description',
        'next_steps',
        'payment_status',
        'customs_fees',
        'currency_code',
        'fee_breakdown',
        'documents_required',
        'required_documents',
        'documents_due_date',
        'documents_approved',
        'approved_documents',
        'rejected_documents',
        'customs_metadata',
        'additional_data',
        'integration_metadata',
        'external_system_id',
        'external_reference',
        'processed_at',
        'last_updated_at',
        'is_final_response',
        'is_archived',
        'archive_date',
        'retention_level',
    ];

    protected $casts = [
        'requires_action' => 'boolean',
        'tracking_numbers' => 'array',
        'container_numbers' => 'array',
        'bill_of_lading_numbers' => 'array',
        'customs_data' => 'array',
        'customs_processed_at' => 'datetime',
        'validation_errors' => 'array',
        'validation_warnings' => 'array',
        'business_errors' => 'array',
        'urgent_action_required' => 'boolean',
        'action_deadline' => 'datetime',
        'next_steps' => 'array',
        'customs_fees' => 'decimal:2',
        'fee_breakdown' => 'array',
        'documents_required' => 'boolean',
        'required_documents' => 'array',
        'documents_due_date' => 'datetime',
        'documents_approved' => 'boolean',
        'approved_documents' => 'array',
        'rejected_documents' => 'array',
        'customs_metadata' => 'array',
        'additional_data' => 'array',
        'integration_metadata' => 'array',
        'processed_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'is_final_response' => 'boolean',
        'is_archived' => 'boolean',
        'archive_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Constantes para tipos de respuesta
     */
    public const RESPONSE_TYPES = [
        'success' => 'Respuesta exitosa completa',
        'partial_success' => 'Éxito parcial (algunos elementos fallaron)',
        'business_error' => 'Error de reglas de negocio',
        'validation_error' => 'Error de validación de datos',
        'system_error' => 'Error del sistema aduanero',
        'timeout' => 'Timeout del webservice',
        'unknown' => 'Respuesta no clasificada',
    ];

    /**
     * Constantes para estados de procesamiento
     */
    public const PROCESSING_STATUSES = [
        'completed' => 'Procesamiento completado',
        'pending' => 'Pendiente de procesamiento adicional',
        'requires_retry' => 'Requiere reintento',
        'requires_manual' => 'Requiere intervención manual',
        'cancelled' => 'Procesamiento cancelado',
    ];

    /**
     * Constantes para estado aduanero
     */
    public const CUSTOMS_STATUSES = [
        'received' => 'Recibido por aduana',
        'under_review' => 'En revisión',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'requires_amendment' => 'Requiere enmienda',
        'processed' => 'Procesado',
        'cleared' => 'Despachado',
        'pending_payment' => 'Pendiente de pago',
        'pending_documents' => 'Pendiente de documentos',
    ];

    /**
     * Constantes para estado de pago
     */
    public const PAYMENT_STATUSES = [
        'none_required' => 'No requerido',
        'pending' => 'Pendiente',
        'partial' => 'Parcial',
        'completed' => 'Completado',
        'overdue' => 'Vencido',
        'refunded' => 'Reembolsado',
    ];

    /**
     * Constantes para niveles de retención
     */
    public const RETENTION_LEVELS = [
        'temporary' => 'Temporal (30 días)',
        'standard' => 'Estándar (1 año)',
        'extended' => 'Extendido (3 años)',
        'permanent' => 'Permanente (legal)',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Transacción webservice relacionada
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WebserviceTransaction::class, 'transaction_id');
    }

    // ==========================================
    // QUERY SCOPES
    // ==========================================

    /**
     * Filtrar por tipo de respuesta
     */
    public function scopeResponseType(Builder $query, string $type): Builder
    {
        return $query->where('response_type', $type);
    }

    /**
     * Filtrar respuestas exitosas
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('response_type', ['success', 'partial_success']);
    }

    /**
     * Filtrar respuestas con errores
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereIn('response_type', ['business_error', 'validation_error', 'system_error']);
    }

    /**
     * Respuestas que requieren acción
     */
    public function scopeRequiresAction(Builder $query): Builder
    {
        return $query->where('requires_action', true);
    }

    /**
     * Respuestas con acción urgente
     */
    public function scopeUrgentAction(Builder $query): Builder
    {
        return $query->where('urgent_action_required', true);
    }

    /**
     * Respuestas por estado aduanero
     */
    public function scopeCustomsStatus(Builder $query, string $status): Builder
    {
        return $query->where('customs_status', $status);
    }

    /**
     * Respuestas pendientes de pago
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Respuestas con documentos requeridos
     */
    public function scopeDocumentsRequired(Builder $query): Builder
    {
        return $query->where('documents_required', true)
                    ->where('documents_approved', false);
    }

    /**
     * Respuestas finales
     */
    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('is_final_response', true);
    }

    /**
     * Respuestas activas (no archivadas)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * Respuestas con fecha límite próxima
     */
    public function scopeUpcomingDeadline(Builder $query, int $days = 7): Builder
    {
        return $query->where('action_deadline', '<=', Carbon::now()->addDays($days))
                    ->where('requires_action', true);
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Determinar si la respuesta es exitosa
     */
    public function isSuccessful(): bool
    {
        return in_array($this->response_type, ['success', 'partial_success']);
    }

    /**
     * Determinar si tiene errores
     */
    public function hasErrors(): bool
    {
        return in_array($this->response_type, ['business_error', 'validation_error', 'system_error']);
    }

    /**
     * Obtener todos los errores combinados
     */
    public function getAllErrors(): array
    {
        $errors = [];
        
        if ($this->validation_errors) {
            $errors['validation'] = $this->validation_errors;
        }
        
        if ($this->business_errors) {
            $errors['business'] = $this->business_errors;
        }
        
        return $errors;
    }

    /**
     * Obtener todos los números de seguimiento
     */
    public function getAllTrackingNumbers(): array
    {
        $numbers = [];
        
        if ($this->tracking_numbers) {
            $numbers = array_merge($numbers, $this->tracking_numbers);
        }
        
        if ($this->container_numbers) {
            $numbers = array_merge($numbers, $this->container_numbers);
        }
        
        if ($this->bill_of_lading_numbers) {
            $numbers = array_merge($numbers, $this->bill_of_lading_numbers);
        }
        
        return array_unique($numbers);
    }

    /**
     * Verificar si la fecha límite está próxima
     */
    public function hasUpcomingDeadline(int $days = 7): bool
    {
        if (!$this->action_deadline || !$this->requires_action) {
            return false;
        }
        
        return $this->action_deadline <= Carbon::now()->addDays($days);
    }

    /**
     * Verificar si la fecha límite está vencida
     */
    public function isOverdue(): bool
    {
        if (!$this->action_deadline || !$this->requires_action) {
            return false;
        }
        
        return $this->action_deadline < Carbon::now();
    }

    /**
     * Obtener descripción del tipo de respuesta
     */
    public function getResponseTypeDescription(): string
    {
        return self::RESPONSE_TYPES[$this->response_type] ?? 'Tipo desconocido';
    }

    /**
     * Obtener descripción del estado de procesamiento
     */
    public function getProcessingStatusDescription(): string
    {
        return self::PROCESSING_STATUSES[$this->processing_status] ?? 'Estado desconocido';
    }

    /**
     * Marcar como archivado
     */
    public function archive(): void
    {
        $this->update([
            'is_archived' => true,
            'archive_date' => Carbon::now(),
        ]);
    }

    /**
     * Marcar como respuesta final
     */
    public function markAsFinal(): void
    {
        $this->update([
            'is_final_response' => true,
            'last_updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Actualizar estado aduanero
     */
    public function updateCustomsStatus(string $status, ?Carbon $processedAt = null): void
    {
        $this->update([
            'customs_status' => $status,
            'customs_processed_at' => $processedAt ?? Carbon::now(),
            'last_updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Obtener resumen para dashboard
     */
    public function getSummary(): array
    {
        return [
            'type' => $this->response_type,
            'status' => $this->processing_status,
            'customs_status' => $this->customs_status,
            'requires_action' => $this->requires_action,
            'urgent' => $this->urgent_action_required,
            'deadline' => $this->action_deadline?->format('Y-m-d H:i'),
            'tracking_count' => count($this->getAllTrackingNumbers()),
            'fees' => $this->customs_fees,
            'currency' => $this->currency_code,
            'documents_required' => $this->documents_required,
        ];
    }
}