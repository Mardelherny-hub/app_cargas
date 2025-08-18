<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MODELO: VoyageWebserviceStatus
 * 
 * Maneja estados independientes de webservices por voyage.
 * Reemplaza los campos únicos argentina_status/paraguay_status 
 * por un sistema flexible que permite múltiples webservices por voyage.
 * 
 * CAPACIDADES:
 * - Estados independientes por webservice (anticipada, micdta, desconsolidado, transbordo)
 * - Control de reintentos por webservice específico
 * - Historial de interacciones con aduana
 * - Consultas eficientes por país/tipo
 * - Validaciones de flujo de trabajo
 * 
 * @property int $id
 * @property int $company_id
 * @property int $voyage_id
 * @property int|null $user_id
 * @property string $country AR|PY
 * @property string $webservice_type anticipada|micdta|desconsolidado|transbordo|manifiesto|mane|consulta|rectificacion|anulacion
 * @property string $status not_required|pending|validating|sending|sent|approved|rejected|error|retry|cancelled|expired
 * @property bool $can_send
 * @property bool $is_required
 * @property string|null $last_transaction_id
 * @property string|null $confirmation_number
 * @property string|null $external_voyage_number
 * @property int $retry_count
 * @property int $max_retries
 * @property Carbon|null $next_retry_at
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property Carbon|null $first_sent_at
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VoyageWebserviceStatus extends Model
{
    use HasFactory;

    protected $table = 'voyage_webservice_statuses';

    protected $fillable = [
        'company_id',
        'voyage_id',
        'user_id',
        'country',
        'webservice_type',
        'status',
        'can_send',
        'is_required',
        'last_transaction_id',
        'confirmation_number',
        'external_voyage_number',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'last_error_code',
        'last_error_message',
        'first_sent_at',
        'last_sent_at',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'can_send' => 'boolean',
        'is_required' => 'boolean',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'next_retry_at' => 'datetime',
        'first_sent_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ========================================
    // CONSTANTES
    // ========================================
    
    public const COUNTRIES = [
        'AR' => 'Argentina',
        'PY' => 'Paraguay',
    ];

    public const WEBSERVICE_TYPES = [
        'anticipada' => 'Información Anticipada',
        'micdta' => 'MIC/DTA',
        'desconsolidado' => 'Desconsolidados',
        'transbordo' => 'Transbordos',
        'manifiesto' => 'Manifiestos',
        'mane' => 'MANE/Malvina',
        'consulta' => 'Consultas',
        'rectificacion' => 'Rectificaciones',
        'anulacion' => 'Anulaciones',
    ];

    public const STATUSES = [
        'not_required' => 'No Requerido',
        'pending' => 'Pendiente',
        'validating' => 'Validando',
        'sending' => 'Enviando',
        'sent' => 'Enviado',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'error' => 'Error',
        'retry' => 'Reintentando',
        'cancelled' => 'Cancelado',
        'expired' => 'Expirado',
    ];

    // Estados que permiten envío
    public const SENDABLE_STATUSES = [
        'pending',
        'error',
        'retry',
        'expired',
    ];

    // Estados que indican éxito
    public const SUCCESS_STATUSES = [
        'sent',
        'approved',
    ];

    // Estados que indican error
    public const ERROR_STATUSES = [
        'rejected',
        'error',
        'expired',
    ];

    // ========================================
    // RELACIONES
    // ========================================

    /**
     * Empresa propietaria
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Viaje relacionado
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    /**
     * Usuario que realizó la última actualización
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Filtrar por país
     */
    public function scopeForCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Filtrar por tipo de webservice
     */
    public function scopeForWebserviceType(Builder $query, string $webserviceType): Builder
    {
        return $query->where('webservice_type', $webserviceType);
    }

    /**
     * Filtrar por estado
     */
    public function scopeWithStatus(Builder $query, string|array $status): Builder
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    /**
     * Solo estados que pueden enviarse
     */
    public function scopeSendable(Builder $query): Builder
    {
        return $query->whereIn('status', self::SENDABLE_STATUSES)
                    ->where('can_send', true);
    }

    /**
     * Solo estados exitosos
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', self::SUCCESS_STATUSES);
    }

    /**
     * Solo estados con error
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereIn('status', self::ERROR_STATUSES);
    }

    /**
     * Pendientes de reintento
     */
    public function scopePendingRetry(Builder $query): Builder
    {
        return $query->where('status', 'retry')
                    ->whereNotNull('next_retry_at')
                    ->where('next_retry_at', '<=', now());
    }

    /**
     * Por empresa
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    // ========================================
    // MÉTODOS DE ESTADO
    // ========================================

    /**
     * Verificar si puede enviarse
     */
    public function canSend(): bool
    {
        return $this->can_send && in_array($this->status, self::SENDABLE_STATUSES);
    }

    /**
     * Verificar si está en estado exitoso
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, self::SUCCESS_STATUSES);
    }

    /**
     * Verificar si tiene errores
     */
    public function hasErrors(): bool
    {
        return in_array($this->status, self::ERROR_STATUSES);
    }

    /**
     * Verificar si puede reintentarse
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries && 
               $this->hasErrors() && 
               $this->can_send;
    }

    /**
     * Verificar si está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // ========================================
    // MÉTODOS DE ACTUALIZACIÓN DE ESTADO
    // ========================================

    /**
     * Marcar como enviando
     */
    public function markAsSending(string $transactionId, int $userId = null): bool
    {
        return $this->update([
            'status' => 'sending',
            'last_transaction_id' => $transactionId,
            'user_id' => $userId,
            'last_sent_at' => now(),
            'first_sent_at' => $this->first_sent_at ?: now(),
        ]);
    }

    /**
     * Marcar como enviado exitosamente
     */
    public function markAsSent(string $transactionId = null, int $userId = null): bool
    {
        $data = [
            'status' => 'sent',
            'user_id' => $userId,
            'last_sent_at' => now(),
            'first_sent_at' => $this->first_sent_at ?: now(),
            'last_error_code' => null,
            'last_error_message' => null,
        ];

        if ($transactionId) {
            $data['last_transaction_id'] = $transactionId;
        }

        return $this->update($data);
    }

    /**
     * Marcar como aprobado por aduana
     */
    public function markAsApproved(string $confirmationNumber = null, string $externalVoyageNumber = null, int $userId = null): bool
    {
        $data = [
            'status' => 'approved',
            'approved_at' => now(),
            'user_id' => $userId,
            'last_error_code' => null,
            'last_error_message' => null,
        ];

        if ($confirmationNumber) {
            $data['confirmation_number'] = $confirmationNumber;
        }

        if ($externalVoyageNumber) {
            $data['external_voyage_number'] = $externalVoyageNumber;
        }

        return $this->update($data);
    }

    /**
     * Marcar como error
     */
    public function markAsError(string $errorCode = null, string $errorMessage = null, int $userId = null): bool
    {
        return $this->update([
            'status' => 'error',
            'last_error_code' => $errorCode,
            'last_error_message' => $errorMessage,
            'user_id' => $userId,
        ]);
    }

    /**
     * Programar reintento
     */
    public function scheduleRetry(Carbon $nextRetryAt = null, int $userId = null): bool
    {
        if (!$this->canRetry()) {
            return false;
        }

        $nextRetryAt = $nextRetryAt ?: now()->addMinutes(5 * ($this->retry_count + 1));

        return $this->update([
            'status' => 'retry',
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => $nextRetryAt,
            'user_id' => $userId,
        ]);
    }

    /**
     * Cancelar estado
     */
    public function cancel(int $userId = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'can_send' => false,
            'user_id' => $userId,
        ]);
    }

    // ========================================
    // MÉTODOS DE UTILIDAD
    // ========================================

    /**
     * Obtener descripción legible del estado
     */
    public function getStatusDescription(): string
    {
        return self::STATUSES[$this->status] ?? 'Desconocido';
    }

    /**
     * Obtener descripción legible del país
     */
    public function getCountryDescription(): string
    {
        return self::COUNTRIES[$this->country] ?? 'Desconocido';
    }

    /**
     * Obtener descripción legible del tipo de webservice
     */
    public function getWebserviceTypeDescription(): string
    {
        return self::WEBSERVICE_TYPES[$this->webservice_type] ?? 'Desconocido';
    }

    /**
     * Obtener color CSS para el estado (para UI)
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'approved' => 'green',
            'sent' => 'blue',
            'pending', 'validating' => 'yellow',
            'sending', 'retry' => 'orange',
            'rejected', 'error', 'expired' => 'red',
            'cancelled' => 'gray',
            'not_required' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Obtener tiempo restante para próximo reintento
     */
    public function getRetryTimeRemaining(): ?string
    {
        if (!$this->next_retry_at) {
            return null;
        }

        $diff = $this->next_retry_at->diffForHumans();
        return $this->next_retry_at->isFuture() ? $diff : 'Disponible ahora';
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    /**
     * Crear estado inicial para un voyage y webservice
     */
    public static function createForVoyage(Voyage $voyage, string $country, string $webserviceType, array $options = []): self
    {
        return self::create(array_merge([
            'company_id' => $voyage->company_id,
            'voyage_id' => $voyage->id,
            'country' => $country,
            'webservice_type' => $webserviceType,
            'status' => 'pending',
            'can_send' => true,
            'is_required' => true,
            'retry_count' => 0,
            'max_retries' => 3,
        ], $options));
    }

    /**
     * Obtener o crear estado para un voyage y webservice
     */
    public static function getOrCreateForVoyage(Voyage $voyage, string $country, string $webserviceType, array $options = []): self
    {
        return self::firstOrCreate([
            'voyage_id' => $voyage->id,
            'country' => $country,
            'webservice_type' => $webserviceType,
        ], array_merge([
            'company_id' => $voyage->company_id,
            'status' => 'pending',
            'can_send' => true,
            'is_required' => true,
            'retry_count' => 0,
            'max_retries' => 3,
        ], $options));
    }
}