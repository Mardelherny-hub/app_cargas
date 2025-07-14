<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Modelo ClientCompanyRelation para relaciones M:N entre clientes y empresas
 * Gestiona permisos, tipos de relación y configuraciones comerciales
 *
 * @property int $id
 * @property int $client_id Cliente relacionado
 * @property int $company_id Empresa relacionada
 * @property string $relation_type Tipo de relación (customer, provider, both)
 * @property bool $can_edit Permisos de edición
 * @property bool $active Estado de la relación
 * @property float|null $credit_limit Límite de crédito
 * @property string|null $internal_code Código interno
 * @property string $priority Prioridad (low, normal, high, critical)
 * @property array|null $relation_config Configuración JSON
 * @property int|null $created_by_user_id Usuario creador
 * @property Carbon|null $last_activity_at Última actividad
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ClientCompanyRelation extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'client_company_relations';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'client_id',
        'company_id',
        'relation_type',
        'can_edit',
        'active',
        'credit_limit',
        'internal_code',
        'priority',
        'relation_config',
        'created_by_user_id',
        'last_activity_at',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'can_edit' => 'boolean',
        'active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'relation_config' => 'array',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de relación disponibles.
     */
    public const RELATION_TYPES = [
        'customer' => 'Cliente',
        'provider' => 'Proveedor',
        'both' => 'Cliente y Proveedor',
    ];

    /**
     * Niveles de prioridad disponibles.
     */
    public const PRIORITIES = [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Cliente relacionado.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Empresa relacionada.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Usuario que creó la relación.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // =====================================================
    // SCOPES ESPECIALIZADOS PARA CONSULTAS FRECUENTES
    // =====================================================

    /**
     * Solo relaciones activas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Solo relaciones inactivas.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    /**
     * Filtrar por empresa.
     */
    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Filtrar por cliente.
     */
    public function scopeByClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Filtrar por tipo de relación.
     */
    public function scopeByRelationType(Builder $query, string $type): Builder
    {
        return $query->where('relation_type', $type);
    }

    /**
     * Solo relaciones donde cliente es customer.
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('relation_type', ['customer', 'both']);
    }

    /**
     * Solo relaciones donde cliente es provider.
     */
    public function scopeProviders(Builder $query): Builder
    {
        return $query->whereIn('relation_type', ['provider', 'both']);
    }

    /**
     * Solo relaciones con permisos de edición.
     */
    public function scopeCanEdit(Builder $query): Builder
    {
        return $query->where('can_edit', true);
    }

    /**
     * Filtrar por prioridad.
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Solo relaciones de alta prioridad.
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    /**
     * Filtrar por código interno.
     */
    public function scopeByInternalCode(Builder $query, string $code): Builder
    {
        return $query->where('internal_code', $code);
    }

    /**
     * Con límite de crédito asignado.
     */
    public function scopeWithCreditLimit(Builder $query): Builder
    {
        return $query->whereNotNull('credit_limit')
                    ->where('credit_limit', '>', 0);
    }

    /**
     * Actividad reciente (últimos N días).
     */
    public function scopeRecentActivity(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    /**
     * Con eager loading de relaciones principales.
     */
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['client', 'company', 'createdByUser']);
    }

    // =====================================================
    // MÉTODOS DE NEGOCIO ESPECÍFICOS DEL DOMINIO
    // =====================================================

    /**
     * Verifica si la relación está operativa.
     */
    public function isOperational(): bool
    {
        return $this->active &&
               $this->client->isOperational() &&
               $this->company->active;
    }

    /**
     * Verifica si es relación de cliente.
     */
    public function isCustomerRelation(): bool
    {
        return in_array($this->relation_type, ['customer', 'both']);
    }

    /**
     * Verifica si es relación de proveedor.
     */
    public function isProviderRelation(): bool
    {
        return in_array($this->relation_type, ['provider', 'both']);
    }

    /**
     * Verifica si es relación bidireccional.
     */
    public function isBidirectional(): bool
    {
        return $this->relation_type === 'both';
    }

    /**
     * Verifica si la empresa puede editar el cliente.
     */
    public function canEditClient(): bool
    {
        return $this->active && $this->can_edit;
    }

    /**
     * Verifica si es relación de alta prioridad.
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, ['high', 'critical']);
    }

    /**
     * Verifica si es relación crítica.
     */
    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }

    /**
     * Verifica si tiene límite de crédito configurado.
     */
    public function hasCreditLimit(): bool
    {
        return !is_null($this->credit_limit) && $this->credit_limit > 0;
    }

    /**
     * Obtiene el límite de crédito formateado.
     */
    public function getFormattedCreditLimit(): string
    {
        if (!$this->hasCreditLimit()) {
            return 'Sin límite';
        }

        return '$' . number_format($this->credit_limit, 2, ',', '.');
    }

    /**
     * Actualiza la última actividad.
     */
    public function updateActivity(): bool
    {
        $this->last_activity_at = now();
        return $this->save();
    }

    /**
     * Activa la relación.
     */
    public function activate(): bool
    {
        $this->active = true;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Desactiva la relación.
     */
    public function deactivate(): bool
    {
        $this->active = false;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Concede permisos de edición.
     */
    public function grantEditPermission(): bool
    {
        $this->can_edit = true;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Revoca permisos de edición.
     */
    public function revokeEditPermission(): bool
    {
        $this->can_edit = false;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Establece código interno.
     */
    public function setInternalCode(string $code): bool
    {
        $this->internal_code = $code;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Establece límite de crédito.
     */
    public function setCreditLimit(float $limit): bool
    {
        $this->credit_limit = $limit;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Cambia la prioridad.
     */
    public function setPriority(string $priority): bool
    {
        if (!array_key_exists($priority, self::PRIORITIES)) {
            return false;
        }

        $this->priority = $priority;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Obtiene configuración específica.
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->relation_config[$key] ?? $default;
    }

    /**
     * Establece configuración específica.
     */
    public function setConfig(string $key, $value): bool
    {
        $config = $this->relation_config ?? [];
        $config[$key] = $value;
        $this->relation_config = $config;
        $this->updateActivity();
        return $this->save();
    }

    /**
     * Obtiene el tipo de relación en formato legible.
     */
    public function getRelationTypeLabel(): string
    {
        return self::RELATION_TYPES[$this->relation_type] ?? $this->relation_type;
    }

    /**
     * Obtiene la prioridad en formato legible.
     */
    public function getPriorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Obtiene el estado en formato legible.
     */
    public function getStatusLabel(): string
    {
        return $this->active ? 'Activa' : 'Inactiva';
    }

    /**
     * Verifica compatibilidad para webservices.
     */
    public function isCompatibleForWebservice(): bool
    {
        return $this->isOperational() &&
               $this->client->isVerified() &&
               $this->company->ws_active;
    }

    // =====================================================
    // MÉTODOS AUXILIARES PARA FORMULARIOS
    // =====================================================

    /**
     * Opciones para select de tipos de relación.
     */
    public static function getRelationTypeOptions(): array
    {
        return self::RELATION_TYPES;
    }

    /**
     * Opciones para select de prioridades.
     */
    public static function getPriorityOptions(): array
    {
        return self::PRIORITIES;
    }

    /**
     * Buscar relación específica.
     */
    public static function findRelation(int $clientId, int $companyId): ?self
    {
        return self::where('client_id', $clientId)
                   ->where('company_id', $companyId)
                   ->first();
    }

    /**
     * Crear o actualizar relación.
     */
    public static function createOrUpdate(int $clientId, int $companyId, array $attributes = []): self
    {
        $relation = self::findRelation($clientId, $companyId);

        if ($relation) {
            $relation->update($attributes);
            $relation->updateActivity();
        } else {
            $attributes['client_id'] = $clientId;
            $attributes['company_id'] = $companyId;
            $relation = self::create($attributes);
        }

        return $relation;
    }

    /**
     * Obtiene estadísticas de relaciones por empresa.
     */
    public static function getCompanyStats(int $companyId): array
    {
        $relations = self::byCompany($companyId);

        return [
            'total' => $relations->count(),
            'active' => $relations->active()->count(),
            'customers' => $relations->customers()->count(),
            'providers' => $relations->providers()->count(),
            'high_priority' => $relations->highPriority()->count(),
            'with_credit' => $relations->withCreditLimit()->count(),
            'recent_activity' => $relations->recentActivity(7)->count(),
        ];
    }

    // =====================================================
    // EVENTOS DEL MODELO
    // =====================================================

    /**
     * Boot del modelo para eventos automáticos.
     */
    protected static function booted(): void
    {
        // Actualizar actividad al crear
        static::creating(function (ClientCompanyRelation $relation) {
            $relation->last_activity_at = now();
        });

        // Actualizar actividad al modificar
        static::updating(function (ClientCompanyRelation $relation) {
            if ($relation->isDirty() && !$relation->isDirty('last_activity_at')) {
                $relation->last_activity_at = now();
            }
        });
    }
}
