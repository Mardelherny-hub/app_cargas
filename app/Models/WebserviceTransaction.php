<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Modelo WebserviceTransaction
 * 
 * Modelo principal para transacciones de webservices aduaneros.
 * Maneja el ciclo completo de vida de las transacciones SOAP con
 * Argentina (AFIP) y Paraguay (DNA/Aduanas).
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $shipment_id
 * @property int|null $voyage_id
 * @property string $transaction_id
 * @property string|null $external_reference
 * @property string|null $batch_id
 * @property string $webservice_type
 * @property string $country
 * @property string $webservice_url
 * @property string|null $soap_action
 * @property string $status
 * @property int $retry_count
 * @property int $max_retries
 * @property Carbon|null $next_retry_at
 * @property array|null $retry_intervals
 * @property string|null $request_xml
 * @property string|null $response_xml
 * @property string|null $request_headers
 * @property string|null $response_headers
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array|null $error_details
 * @property bool $is_blocking_error
 * @property string|null $confirmation_number
 * @property array|null $success_data
 * @property array|null $tracking_numbers
 * @property Carbon|null $sent_at
 * @property Carbon|null $response_at
 * @property int|null $response_time_ms
 * @property Carbon|null $expires_at
 * @property array|null $validation_errors
 * @property bool $requires_manual_review
 * @property string|null $reviewer_user_id
 * @property Carbon|null $reviewed_at
 * @property float|null $total_weight_kg
 * @property float|null $total_value
 * @property string $currency_code
 * @property int $container_count
 * @property int $bill_of_lading_count
 * @property string $environment
 * @property string|null $certificate_used
 * @property array|null $webservice_config
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $additional_metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * // Relationships
 * @property Company $company
 * @property User $user
 * @property Shipment|null $shipment
 * @property Voyage|null $voyage
 * @property WebserviceLog[] $logs
 * @property WebserviceResponse|null $response
 */
class WebserviceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'shipment_id',
        'voyage_id',
        'transaction_id',
        'external_reference',
        'batch_id',
        'webservice_type',
        'country',
        'webservice_url',
        'soap_action',
        'status',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'retry_intervals',
        'request_xml',
        'response_xml',
        'request_headers',
        'response_headers',
        'error_code',
        'error_message',
        'error_details',
        'is_blocking_error',
        'confirmation_number',
        'success_data',
        'tracking_numbers',
        'sent_at',
        'response_at',
        'response_time_ms',
        'expires_at',
        'validation_errors',
        'requires_manual_review',
        'reviewer_user_id',
        'reviewed_at',
        'total_weight_kg',
        'total_value',
        'currency_code',
        'container_count',
        'bill_of_lading_count',
        'environment',
        'certificate_used',
        'webservice_config',
        'ip_address',
        'user_agent',
        'additional_metadata',
    ];

    protected $casts = [
        'retry_intervals' => 'array',
        'error_details' => 'array',
        'is_blocking_error' => 'boolean',
        'success_data' => 'array',
        'tracking_numbers' => 'array',
        'sent_at' => 'datetime',
        'response_at' => 'datetime',
        'expires_at' => 'datetime',
        'validation_errors' => 'array',
        'requires_manual_review' => 'boolean',
        'reviewed_at' => 'datetime',
        'total_weight_kg' => 'decimal:2',
        'total_value' => 'decimal:2',
        'webservice_config' => 'array',
        'additional_metadata' => 'array',
        'next_retry_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
        'is_blocking_error' => false,
        'requires_manual_review' => false,
        'currency_code' => 'USD',
        'container_count' => 0,
        'bill_of_lading_count' => 0,
    ];

    /**
     * Constantes para tipos de webservice
     */
    public const WEBSERVICE_TYPES = [
        'anticipada' => 'Información Anticipada Argentina',
        'micdta' => 'MIC/DTA Argentina',
        'desconsolidado' => 'Desconsolidados Argentina',
        'transbordo' => 'Transbordos Argentina/Paraguay',
        'manifiesto' => 'Manifiestos Paraguay',
        'consulta' => 'Consultas de estado',
        'rectificacion' => 'Rectificaciones',
        'anulacion' => 'Anulaciones',
    ];

    /**
     * Constantes para estados de transacción
     */
    public const STATUSES = [
        'pending' => 'Pendiente de envío',
        'validating' => 'En validación pre-envío',
        'sending' => 'Enviando al webservice',
        'sent' => 'Enviado exitosamente',
        'success' => 'Respuesta exitosa recibida',
        'error' => 'Error en la transacción',
        'retry' => 'En reintento',
        'cancelled' => 'Cancelado por usuario',
        'expired' => 'Expirado por timeout',
    ];

    /**
     * Constantes para países
     */
    public const COUNTRIES = [
        'AR' => 'Argentina',
        'PY' => 'Paraguay',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Empresa que realiza la transacción
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Usuario que inició la transacción
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Envío relacionado (opcional)
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Viaje relacionado (opcional)
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    /**
     * TRACKs generados por esta transacción
     */
    public function webserviceTracks()
    {
        return $this->hasMany(\App\Models\WebserviceTrack::class, 'webservice_transaction_id');
    }

    /**
     * Logs de la transacción
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WebserviceLog::class, 'transaction_id');
    }

    /**
     * Respuesta estructurada de la transacción
     */
    public function response(): HasOne
    {
        return $this->hasOne(WebserviceResponse::class, 'transaction_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope para filtrar por empresa
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para transacciones pendientes de reintento
     */
    public function scopePendingRetry(Builder $query): Builder
    {
        return $query
            ->where('status', 'retry')
            ->where('next_retry_at', '<=', now())
            ->where('retry_count', '<', \DB::raw('max_retries'));
    }

    /**
     * Scope para transacciones exitosas
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope para transacciones con errores
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereIn('status', ['error', 'expired']);
    }

    /**
     * Scope para filtrar por tipo de webservice
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('webservice_type', $type);
    }

    /**
     * Scope para filtrar por país
     */
    public function scopeForCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Scope para transacciones que requieren revisión manual
     */
    public function scopeRequiringReview(Builder $query): Builder
    {
        return $query->where('requires_manual_review', true);
    }

    // ==========================================
    // MUTATORS & ACCESSORS
    // ==========================================

    /**
     * Accessor para el nombre del tipo de webservice
     */
    public function getWebserviceTypeNameAttribute(): string
    {
        return self::WEBSERVICE_TYPES[$this->webservice_type] ?? $this->webservice_type;
    }

    /**
     * Accessor para el nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor para el nombre del país
     */
    public function getCountryNameAttribute(): string
    {
        return self::COUNTRIES[$this->country] ?? $this->country;
    }

    /**
     * Accessor para verificar si la transacción está en progreso
     */
    public function getIsInProgressAttribute(): bool
    {
        return in_array($this->status, ['pending', 'validating', 'sending', 'retry']);
    }

    /**
     * Accessor para verificar si la transacción está completada
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['success', 'error', 'cancelled', 'expired']);
    }

    /**
     * Accessor para verificar si puede reintentarse
     */
    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'error'
            && !$this->is_blocking_error
            && $this->retry_count < $this->max_retries;
    }

    // ==========================================
    // BUSINESS METHODS
    // ==========================================

    /**
     * Generar ID único de transacción para la empresa
     */
    public function generateTransactionId(): string
    {
        $prefix = $this->country . $this->webservice_type;
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return strtoupper($prefix . $timestamp . $random);
    }

    /**
     * Marcar transacción como enviada
     */
    public function markAsSent(string $requestXml, array $headers = []): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'request_xml' => $requestXml,
            'request_headers' => json_encode($headers),
        ]);
    }

    /**
     * Marcar transacción como exitosa
     */
    public function markAsSuccess(string $responseXml, array $successData = []): void
    {
        $responseTime = $this->sent_at ? now()->diffInMilliseconds($this->sent_at) : null;

        $this->update([
            'status' => 'success',
            'response_at' => now(),
            'response_xml' => $responseXml,
            'response_time_ms' => $responseTime,
            'success_data' => $successData,
            'confirmation_number' => $successData['confirmation_number'] ?? null,
            'tracking_numbers' => $successData['tracking_numbers'] ?? null,
        ]);
    }

    /**
     * Marcar transacción como error
     */
    public function markAsError(string $errorCode, string $errorMessage, array $errorDetails = []): void
    {
        $this->update([
            'status' => 'error',
            'response_at' => now(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
            'is_blocking_error' => $this->isBlockingError($errorCode),
        ]);
    }

    /**
     * Programar siguiente reintento
     */
    public function scheduleRetry(): void
    {
        if (!$this->can_retry) {
            return;
        }

        $intervals = $this->retry_intervals ?? [60, 300, 900]; // 1min, 5min, 15min
        $delay = $intervals[$this->retry_count] ?? end($intervals);

        $this->update([
            'status' => 'retry',
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addSeconds($delay),
        ]);
    }

    /**
     * Verificar si un código de error es bloqueante
     */
    private function isBlockingError(string $errorCode): bool
    {
        // TODO: Implementar lógica con catálogo de errores
        $blockingErrors = ['AUTH_FAILED', 'CERT_INVALID', 'VALIDATION_FAILED'];
        return in_array($errorCode, $blockingErrors);
    }
}
