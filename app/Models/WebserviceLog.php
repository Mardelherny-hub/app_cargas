<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Modelo WebserviceLog
 *
 * Modelo para logs detallados de transacciones webservice.
 * Registra cada paso, evento, error o información relevante durante
 * el proceso de comunicación con webservices aduaneros.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int|null $user_id
 * @property string $level
 * @property string $message
 * @property string $category
 * @property string|null $subcategory
 * @property string|null $process_step
 * @property array|null $context_data
 * @property array|null $request_data
 * @property array|null $response_data
 * @property array|null $error_details
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $environment
 * @property int|null $execution_time_ms
 * @property int|null $memory_usage_mb
 * @property string|null $webservice_endpoint
 * @property string|null $soap_action
 * @property int|null $http_status_code
 * @property array|null $http_headers
 * @property bool $requires_alert
 * @property bool $alert_sent
 * @property Carbon|null $alert_sent_at
 * @property string|null $alert_method
 * @property string|null $error_group_id
 * @property bool $is_recurring_error
 * @property string|null $correlation_id
 * @property bool $is_archived
 * @property Carbon|null $archive_date
 * @property string $retention_level
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * // Relationships
 * @property WebserviceTransaction $transaction
 * @property User|null $user
 */
class WebserviceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'level',
        'message',
        'category',
        'subcategory',
        'process_step',
        'context_data',
        'request_data',
        'response_data',
        'error_details',
        'ip_address',
        'user_agent',
        'environment',
        'execution_time_ms',
        'memory_usage_mb',
        'webservice_endpoint',
        'soap_action',
        'http_status_code',
        'http_headers',
        'requires_alert',
        'alert_sent',
        'alert_sent_at',
        'alert_method',
        'error_group_id',
        'is_recurring_error',
        'correlation_id',
        'is_archived',
        'archive_date',
        'retention_level',
    ];

    protected $casts = [
        'context_data' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'error_details' => 'array',
        'http_headers' => 'array',
        'requires_alert' => 'boolean',
        'alert_sent' => 'boolean',
        'alert_sent_at' => 'datetime',
        'is_recurring_error' => 'boolean',
        'is_archived' => 'boolean',
        'archive_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Constantes para niveles de log
     */
    public const LEVELS = [
        'debug' => 'Debug - Información de desarrollo',
        'info' => 'Info - Información general',
        'warning' => 'Warning - Advertencias',
        'error' => 'Error - Errores del sistema',
        'critical' => 'Critical - Errores críticos',
        'alert' => 'Alert - Alertas inmediatas',
    ];

    /**
     * Constantes para categorías de log
     */
    public const CATEGORIES = [
        'authentication' => 'Autenticación y certificados',
        'validation' => 'Validación de datos',
        'soap_request' => 'Envío de requests SOAP',
        'soap_response' => 'Procesamiento de responses',
        'xml_processing' => 'Procesamiento XML',
        'business_logic' => 'Lógica de negocio',
        'database' => 'Operaciones de base de datos',
        'external_api' => 'APIs externas',
        'security' => 'Eventos de seguridad',
        'performance' => 'Métricas de rendimiento',
        'retry_logic' => 'Lógica de reintentos',
        'error_handling' => 'Manejo de errores',
    ];

    /**
     * Constantes para pasos del proceso
     */
    public const PROCESS_STEPS = [
        'initialization' => 'Inicialización',
        'pre_validation' => 'Pre-validación',
        'certificate_loading' => 'Carga de certificados',
        'xml_generation' => 'Generación XML',
        'soap_client_creation' => 'Creación cliente SOAP',
        'request_sending' => 'Envío de request',
        'response_processing' => 'Procesamiento de response',
        'data_extraction' => 'Extracción de datos',
        'post_processing' => 'Post-procesamiento',
        'completion' => 'Finalización',
        'error_recovery' => 'Recuperación de errores',
        'cleanup' => 'Limpieza',
    ];

    /**
     * Constantes para ambientes
     */
    public const ENVIRONMENTS = [
        'testing' => 'Testing/Desarrollo',
        'staging' => 'Staging/Pruebas',
        'production' => 'Producción',
    ];

    /**
     * Constantes para niveles de retención
     */
    public const RETENTION_LEVELS = [
        'temporary' => 'Temporal (7 días)',
        'standard' => 'Estándar (30 días)',
        'extended' => 'Extendido (90 días)',
        'permanent' => 'Permanente',
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

    /**
     * Usuario que generó el log (opcional)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // QUERY SCOPES
    // ==========================================

    /**
     * Filtrar por nivel de log
     */
    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Filtrar por categoría
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Filtrar por ambiente
     */
    public function scopeEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Logs que requieren alerta
     */
    public function scopeRequiresAlert(Builder $query): Builder
    {
        return $query->where('requires_alert', true);
    }

    /**
     * Logs pendientes de alerta
     */
    public function scopePendingAlert(Builder $query): Builder
    {
        return $query->where('requires_alert', true)
                    ->where('alert_sent', false);
    }

    /**
     * Logs de errores
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->whereIn('level', ['error', 'critical', 'alert']);
    }

    /**
     * Logs recientes (últimas 24 horas)
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDay());
    }

    /**
     * Logs por rango de fechas
     */
    public function scopeDateRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Logs activos (no archivados)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * Logs por grupo de error
     */
    public function scopeErrorGroup(Builder $query, string $groupId): Builder
    {
        return $query->where('error_group_id', $groupId);
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Determinar si el log es crítico
     */
    public function isCritical(): bool
    {
        return in_array($this->level, ['critical', 'alert']);
    }

    /**
     * Determinar si el log es de error
     */
    public function isError(): bool
    {
        return in_array($this->level, ['error', 'critical', 'alert']);
    }

    /**
     * Obtener descripción del nivel
     */
    public function getLevelDescription(): string
    {
        return self::LEVELS[$this->level] ?? 'Nivel desconocido';
    }

    /**
     * Obtener descripción de la categoría
     */
    public function getCategoryDescription(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Categoría desconocida';
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
     * Marcar alerta como enviada
     */
    public function markAlertSent(string $method = 'email'): void
    {
        $this->update([
            'alert_sent' => true,
            'alert_sent_at' => Carbon::now(),
            'alert_method' => $method,
        ]);
    }

    /**
     * Formatear el log para visualización
     */
    public function getFormattedMessage(): string
    {
        $timestamp = $this->created_at->format('Y-m-d H:i:s');
        $level = strtoupper($this->level);
        $category = strtoupper($this->category);
        
        return "[$timestamp] {$level}.{$category}: {$this->message}";
    }

    /**
     * Obtener contexto resumido para dashboard
     */
    public function getSummaryContext(): array
    {
        return [
            'level' => $this->level,
            'category' => $this->category,
            'process_step' => $this->process_step,
            'execution_time' => $this->execution_time_ms ? "{$this->execution_time_ms}ms" : null,
            'http_status' => $this->http_status_code,
            'requires_alert' => $this->requires_alert,
        ];
    }
}