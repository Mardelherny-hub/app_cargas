<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * MODELO WSAA TOKEN - Cache de tokens AFIP
 * 
 * Gestiona tokens de autenticación WSAA para webservices de AFIP.
 * Previene el error "El CEE ya posee un TA valido" mediante cache inteligente.
 * 
 * FUNCIONALIDADES:
 * - Cache de tokens por empresa+servicio+ambiente
 * - Validación automática de expiración
 * - Limpieza de tokens vencidos
 * - Auditoría completa de uso
 * - Reutilización segura de tokens válidos
 * 
 * RELACIONES:
 * - BelongsTo Company (empresa propietaria)
 * 
 * @property int $id
 * @property int $company_id
 * @property string $service_name
 * @property string $environment
 * @property string $token
 * @property string $sign
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property string $generation_time
 * @property string $unique_id
 * @property string|null $certificate_used
 * @property int $usage_count
 * @property Carbon|null $last_used_at
 * @property string $status
 * @property string|null $error_message
 * @property string $created_by_process
 * @property array|null $creation_context
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read Company $company
 */
class WsaaToken extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'wsaa_tokens';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'service_name',
        'environment',
        'token',
        'sign',
        'issued_at',
        'expires_at',
        'generation_time',
        'unique_id',
        'certificate_used',
        'usage_count',
        'last_used_at',
        'status',
        'error_message',
        'created_by_process',
        'creation_context',
    ];

    /**
     * Atributos que deben ser tratados como fechas
     */
    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'creation_context' => 'array',
        'usage_count' => 'integer',
        'company_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Estados válidos para tokens
     */
    public const STATUSES = [
        'active' => 'Activo',
        'expired' => 'Expirado',
        'revoked' => 'Revocado',
        'error' => 'Error',
    ];

    /**
     * Servicios AFIP soportados
     */
    public const AFIP_SERVICES = [
        'wgesregsintia2' => 'MIC/DTA y Desconsolidados',
        'wgesinformacionanticipada' => 'Información Anticipada',
        'wstransbordos' => 'Transbordos',
    ];

    /**
     * Servicios DNA Paraguay soportados
     */
    public const PARAGUAY_SERVICES = [
        'serviciotemaflu' => 'GDSF - Gestión de Declaración Sumaria Fluvial',
    ];

    /**
     * Ambientes disponibles
     */
    public const ENVIRONMENTS = [
        'testing' => 'Homologación',
        'production' => 'Producción',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Empresa propietaria del token
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // =====================================================
    // MÉTODOS DE NEGOCIO
    // =====================================================

    /**
     * Verificar si el token está activo y válido
     */
    public function isValid(): bool
    {
        return $this->status === 'active' && 
               $this->expires_at > now();
    }

    /**
     * Verificar si el token ha expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Marcar token como usado
     */
    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Marcar token como expirado
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Marcar token como revocado
     */
    public function markAsRevoked(string $reason = null): void
    {
        $this->update([
            'status' => 'revoked',
            'error_message' => $reason,
        ]);
    }

    /**
     * Obtener tiempo restante en minutos
     */
    public function getTimeToExpiryMinutes(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return now()->diffInMinutes($this->expires_at);
    }

    // =====================================================
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // =====================================================

    /**
     * Obtener token válido para empresa+servicio+ambiente
     * Con auto-cleanup de tokens expirados
     */
    public static function getValidToken(int $companyId, string $serviceName, string $environment): ?self
    {
        // 1. MARCAR automáticamente como expired los tokens que ya expiraron
        static::where('company_id', $companyId)
            ->where('service_name', $serviceName)
            ->where('environment', $environment)
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
        
        // 2. BUSCAR token válido (active + no expirado)
        return static::where('company_id', $companyId)
            ->where('service_name', $serviceName)
            ->where('environment', $environment)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Crear nuevo token WSAA con limpieza agresiva
     */
    public static function createToken(array $tokenData): self
    {
        $companyId = $tokenData['company_id'];
        $serviceName = $tokenData['service_name'];
        $environment = $tokenData['environment'];
        
        // 1. LIMPIEZA AGRESIVA - eliminar tokens viejos según política de retención
        static::cleanupOldTokens($companyId, $serviceName, $environment);
        
        // 2. ELIMINAR token active actual (si existe) - HARD DELETE para evitar constraint violation
        static::where([
            'company_id' => $companyId,
            'service_name' => $serviceName,
            'environment' => $environment,
            'status' => 'active'
        ])->delete();
        
        // 3. CREAR nuevo token limpio (sin conflictos de constraint)
        return static::create($tokenData);
    }

    /**
     * Limpieza agresiva de tokens antiguos según política de retención
     * 
     * POLÍTICA:
     * - Tokens EXPIRED > 7 días: DELETE
     * - Tokens REVOKED > 24 horas: DELETE  
     * - Tokens ERROR > 24 horas: DELETE
     * - Mantiene solo tokens recientes para debugging
     */
    private static function cleanupOldTokens(int $companyId, string $serviceName, string $environment): void
    {
        try {
            $deleted = static::where('company_id', $companyId)
                ->where('service_name', $serviceName)
                ->where('environment', $environment)
                ->where(function($query) {
                    $query->where(function($q) {
                        // Tokens expirados > 7 días
                        $q->where('status', 'expired')
                        ->where('updated_at', '<', now()->subDays(7));
                    })->orWhere(function($q) {
                        // Tokens revocados > 24 horas  
                        $q->where('status', 'revoked')
                        ->where('updated_at', '<', now()->subHours(24));
                    })->orWhere(function($q) {
                        // Tokens con error > 24 horas
                        $q->where('status', 'error')
                        ->where('updated_at', '<', now()->subHours(24));
                    });
                })
                ->delete();
                
            if ($deleted > 0) {
                \Log::info("WSAA Cleanup: {$deleted} tokens antiguos eliminados", [
                    'company_id' => $companyId,
                    'service_name' => $serviceName,
                    'environment' => $environment,
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error en limpieza de tokens WSAA', [
                'company_id' => $companyId,
                'service_name' => $serviceName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Limpiar tokens expirados
     */
    public static function cleanupExpiredTokens(): int
    {
        $expired = static::where('expires_at', '<', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return $expired;
    }

    /**
     * Obtener estadísticas de uso por empresa
     */
    public static function getUsageStats(int $companyId): array
    {
        $tokens = static::where('company_id', $companyId)->get();

        return [
            'total_tokens' => $tokens->count(),
            'active_tokens' => $tokens->where('status', 'active')->count(),
            'expired_tokens' => $tokens->where('status', 'expired')->count(),
            'total_usage' => $tokens->sum('usage_count'),
            'last_used' => $tokens->max('last_used_at'),
        ];
    }

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope para tokens activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para tokens válidos (activos y no expirados)
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope para una empresa específica
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para un servicio específico
     */
    public function scopeForService($query, string $serviceName)
    {
        return $query->where('service_name', $serviceName);
    }

    /**
     * Scope para un ambiente específico
     */
    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
}