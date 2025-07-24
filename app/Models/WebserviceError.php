<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Modelo WebserviceError
 *
 * Modelo para catálogo de errores conocidos de webservices aduaneros.
 * Permite diagnosticar errores automáticamente y ofrecer soluciones
 * basadas en experiencia previa y documentación oficial.
 *
 * @property int $id
 * @property string $country
 * @property string $webservice_type
 * @property string $error_code
 * @property string|null $error_subcode
 * @property string $error_title
 * @property string $error_description
 * @property string|null $error_message_pattern
 * @property string $category
 * @property string $subcategory
 * @property string $severity
 * @property bool $is_blocking
 * @property bool $allows_retry
 * @property int $suggested_retry_count
 * @property array|null $retry_strategy
 * @property string|null $suggested_solution
 * @property array|null $solution_steps
 * @property array|null $prevention_tips
 * @property string|null $official_documentation_url
 * @property array|null $related_fields
 * @property array|null $common_causes
 * @property string|null $soap_fault_code
 * @property int|null $http_status_code
 * @property array|null $http_headers_indicators
 * @property int $frequency_count
 * @property Carbon|null $first_occurrence
 * @property Carbon|null $last_occurrence
 * @property string|null $error_group
 * @property int|null $parent_error_id
 * @property bool $requires_immediate_alert
 * @property int $alert_threshold
 * @property string|null $alert_message_template
 * @property array|null $notification_config
 * @property bool $is_active
 * @property bool $is_deprecated
 * @property string|null $deprecated_reason
 * @property Carbon|null $deprecated_date
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $last_reviewed_at
 * @property string|null $review_notes
 * @property array|null $integration_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * // Relationships
 * @property WebserviceError|null $parentError
 * @property WebserviceError[] $childErrors
 * @property User|null $reviewedByUser
 */
class WebserviceError extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'webservice_type',
        'error_code',
        'error_subcode',
        'error_title',
        'error_description',
        'error_message_pattern',
        'category',
        'subcategory',
        'severity',
        'is_blocking',
        'allows_retry',
        'suggested_retry_count',
        'retry_strategy',
        'suggested_solution',
        'solution_steps',
        'prevention_tips',
        'official_documentation_url',
        'related_fields',
        'common_causes',
        'soap_fault_code',
        'http_status_code',
        'http_headers_indicators',
        'frequency_count',
        'first_occurrence',
        'last_occurrence',
        'error_group',
        'parent_error_id',
        'requires_immediate_alert',
        'alert_threshold',
        'alert_message_template',
        'notification_config',
        'is_active',
        'is_deprecated',
        'deprecated_reason',
        'deprecated_date',
        'reviewed_by_user_id',
        'last_reviewed_at',
        'review_notes',
        'integration_data',
    ];

    protected $casts = [
        'retry_strategy' => 'array',
        'solution_steps' => 'array',
        'prevention_tips' => 'array',
        'related_fields' => 'array',
        'common_causes' => 'array',
        'http_headers_indicators' => 'array',
        'frequency_count' => 'integer',
        'first_occurrence' => 'datetime',
        'last_occurrence' => 'datetime',
        'is_blocking' => 'boolean',
        'allows_retry' => 'boolean',
        'suggested_retry_count' => 'integer',
        'alert_threshold' => 'integer',
        'requires_immediate_alert' => 'boolean',
        'notification_config' => 'array',
        'is_active' => 'boolean',
        'is_deprecated' => 'boolean',
        'deprecated_date' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'integration_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Constantes para países
     */
    public const COUNTRIES = [
        'AR' => 'Argentina',
        'PY' => 'Paraguay',
    ];

    /**
     * Constantes para tipos de webservice
     */
    public const WEBSERVICE_TYPES = [
        'micdta' => 'MIC/DTA Argentina',
        'anticipada' => 'Información Anticipada Argentina',
        'desconsolidado' => 'Desconsolidados Argentina',
        'transbordo' => 'Transbordos',
        'manifiesto' => 'Manifiestos Paraguay',
        'consulta' => 'Consultas de estado',
        'rectificacion' => 'Rectificaciones',
        'anulacion' => 'Anulaciones',
    ];

    /**
     * Constantes para categorías de error
     */
    public const CATEGORIES = [
        'authentication' => 'Autenticación y certificados',
        'validation' => 'Validación de datos',
        'business_rules' => 'Reglas de negocio',
        'technical' => 'Errores técnicos',
        'network' => 'Conectividad y red',
        'timeout' => 'Timeouts y límites',
        'data_format' => 'Formato de datos',
        'permissions' => 'Permisos y autorización',
        'system_unavailable' => 'Sistema no disponible',
        'rate_limiting' => 'Límites de velocidad',
    ];

    /**
     * Constantes para subcategorías
     */
    public const SUBCATEGORIES = [
        'certificate_expired' => 'Certificado vencido',
        'certificate_invalid' => 'Certificado inválido',
        'missing_required_field' => 'Campo obligatorio faltante',
        'invalid_field_format' => 'Formato de campo inválido',
        'duplicate_reference' => 'Referencia duplicada',
        'invalid_date_range' => 'Rango de fechas inválido',
        'insufficient_permissions' => 'Permisos insuficientes',
        'service_maintenance' => 'Mantenimiento del servicio',
        'connection_timeout' => 'Timeout de conexión',
        'response_timeout' => 'Timeout de respuesta',
    ];

    /**
     * Constantes para severidad
     */
    public const SEVERITIES = [
        'low' => 'Baja - No bloquea operación',
        'medium' => 'Media - Puede reintentarse',
        'high' => 'Alta - Requiere intervención',
        'critical' => 'Crítica - Bloquea completamente',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Error padre (para errores jerárquicos)
     */
    public function parentError(): BelongsTo
    {
        return $this->belongsTo(WebserviceError::class, 'parent_error_id');
    }

    /**
     * Errores hijos (sub-errores)
     */
    public function childErrors(): HasMany
    {
        return $this->hasMany(WebserviceError::class, 'parent_error_id');
    }

    /**
     * Usuario que revisó el error
     */
    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    // ==========================================
    // QUERY SCOPES
    // ==========================================

    /**
     * Filtrar por país
     */
    public function scopeCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Filtrar por tipo de webservice
     */
    public function scopeWebserviceType(Builder $query, string $type): Builder
    {
        return $query->where('webservice_type', $type);
    }

    /**
     * Filtrar por código de error
     */
    public function scopeErrorCode(Builder $query, string $code): Builder
    {
        return $query->where('error_code', $code);
    }

    /**
     * Filtrar por categoría
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Errores activos
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Errores bloqueantes
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where('is_blocking', true);
    }

    /**
     * Errores que permiten reintento
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('allows_retry', true);
    }

    /**
     * Errores por severidad
     */
    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Errores críticos
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Errores frecuentes
     */
    public function scopeFrequent(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('frequency_count', '>=', $threshold);
    }

    /**
     * Errores que requieren alerta inmediata
     */
    public function scopeRequiresAlert(Builder $query): Builder
    {
        return $query->where('requires_immediate_alert', true);
    }

    /**
     * Errores por grupo
     */
    public function scopeErrorGroup(Builder $query, string $group): Builder
    {
        return $query->where('error_group', $group);
    }

    /**
     * Errores pendientes de revisión
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereNull('last_reviewed_at')
                    ->orWhere('last_reviewed_at', '<', Carbon::now()->subMonths(6));
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Verificar si el error coincide con un mensaje
     */
    public function matchesMessage(string $message): bool
    {
        if (!$this->error_message_pattern) {
            return false;
        }
        
        $pattern = '/' . str_replace('*', '.*', preg_quote($this->error_message_pattern, '/')) . '/i';
        return preg_match($pattern, $message);
    }

    /**
     * Incrementar contador de frecuencia
     */
    public function incrementFrequency(): void
    {
        $now = Carbon::now();
        
        $this->update([
            'frequency_count' => $this->frequency_count + 1,
            'last_occurrence' => $now,
            'first_occurrence' => $this->first_occurrence ?? $now,
        ]);
    }

    /**
     * Verificar si debe generar alerta
     */
    public function shouldAlert(): bool
    {
        return $this->requires_immediate_alert || 
               ($this->alert_threshold && $this->frequency_count >= $this->alert_threshold);
    }

    /**
     * Obtener estrategia de reintento recomendada
     */
    public function getRetryStrategy(): array
    {
        if (!$this->allows_retry) {
            return [];
        }
        
        return $this->retry_strategy ?? [
            'max_attempts' => $this->suggested_retry_count ?? 3,
            'delay_seconds' => [5, 15, 30],
            'exponential_backoff' => true,
        ];
    }

    /**
     * Obtener descripción de severidad
     */
    public function getSeverityDescription(): string
    {
        return self::SEVERITIES[$this->severity] ?? 'Severidad desconocida';
    }

    /**
     * Obtener descripción de categoría
     */
    public function getCategoryDescription(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Categoría desconocida';
    }

    /**
     * Marcar como revisado
     */
    public function markAsReviewed(int $userId, string $notes = null): void
    {
        $this->update([
            'reviewed_by_user_id' => $userId,
            'last_reviewed_at' => Carbon::now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Marcar como deprecated
     */
    public function deprecate(string $reason): void
    {
        $this->update([
            'is_deprecated' => true,
            'deprecated_reason' => $reason,
            'deprecated_date' => Carbon::now(),
            'is_active' => false,
        ]);
    }

    /**
     * Obtener resumen para dashboard
     */
    public function getSummary(): array
    {
        return [
            'code' => $this->error_code,
            'title' => $this->error_title,
            'category' => $this->category,
            'severity' => $this->severity,
            'frequency' => $this->frequency_count,
            'blocking' => $this->is_blocking,
            'retryable' => $this->allows_retry,
            'last_seen' => $this->last_occurrence?->diffForHumans(),
        ];
    }

    /**
     * Generar mensaje de alerta personalizado
     */
    public function generateAlertMessage(array $context = []): string
    {
        if ($this->alert_message_template) {
            $message = $this->alert_message_template;
            
            foreach ($context as $key => $value) {
                $message = str_replace("{{$key}}", $value, $message);
            }
            
            return $message;
        }
        
        return "Error {$this->error_code} detectado en {$this->webservice_type} ({$this->country}). Frecuencia: {$this->frequency_count}";
    }

    /**
     * Buscar error por patrón de mensaje
     */
    public static function findByMessage(string $country, string $webserviceType, string $message): ?self
    {
        return static::active()
                    ->country($country)
                    ->webserviceType($webserviceType)
                    ->get()
                    ->first(function ($error) use ($message) {
                        return $error->matchesMessage($message);
                    });
    }
}