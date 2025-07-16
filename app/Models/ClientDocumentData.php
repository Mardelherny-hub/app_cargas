<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FASE 2 - SISTEMA DE DATOS VARIABLES
 *
 * Modelo ClientDocumentData para datos contextuales de impresión
 * Resuelve el problema de "datos oficiales vs datos de impresión"
 *
 * CASO DE USO:
 * BD: "ALUAR S.A." + "San José 1234"
 * Impresión: "ALUAR SOCIEDAD ANONIMA" + "Salta 4532"
 *
 * @property int $id
 * @property int $client_id Cliente base
 * @property string $context_type Tipo de contexto
 * @property int|null $context_id ID específico del contexto
 * @property string $display_name Nombre para mostrar
 * @property string|null $display_address Dirección para mostrar
 * @property string|null $display_city Ciudad para mostrar
 * @property string|null $display_postal_code Código postal
 * @property string|null $display_phone Teléfono
 * @property string|null $display_email Email
 * @property array|null $additional_data Datos adicionales JSON
 * @property bool $is_default Es dato por defecto
 * @property int $priority Prioridad de aplicación
 * @property Carbon|null $valid_from Válido desde
 * @property Carbon|null $valid_until Válido hasta
 * @property int $created_by_user_id Usuario creador
 * @property int $company_id Empresa gestora
 * @property string|null $notes Observaciones
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ClientDocumentData extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'client_document_data';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'client_id',
        'context_type',
        'context_id',
        'display_name',
        'display_address',
        'display_city',
        'display_postal_code',
        'display_phone',
        'display_email',
        'additional_data',
        'is_default',
        'priority',
        'valid_from',
        'valid_until',
        'created_by_user_id',
        'company_id',
        'notes',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'additional_data' => 'array',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de contexto disponibles.
     */
    public const CONTEXT_TYPES = [
        'bill_of_lading' => 'Conocimiento de Embarque',
        'manifest' => 'Manifiesto',
        'voyage' => 'Viaje',
        'default' => 'Por Defecto',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Cliente base al que pertenecen estos datos.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Empresa que gestiona estos datos.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Usuario que creó estos datos variables.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // =====================================================
    // SCOPES ESPECIALIZADOS PARA CONSULTAS FRECUENTES
    // =====================================================

    /**
     * Filtrar por cliente.
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Filtrar por empresa.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Filtrar por tipo de contexto.
     */
    public function scopeForContextType(Builder $query, string $contextType): Builder
    {
        return $query->where('context_type', $contextType);
    }

    /**
     * Filtrar por contexto específico.
     */
    public function scopeForContext(Builder $query, string $contextType, ?int $contextId = null): Builder
    {
        $query->where('context_type', $contextType);

        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }

        return $query;
    }

    /**
     * Solo datos por defecto.
     */
    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Solo datos específicos (no por defecto).
     */
    public function scopeSpecific(Builder $query): Builder
    {
        return $query->where('is_default', false);
    }

    /**
     * Datos vigentes en una fecha específica.
     */
    public function scopeValidOn(Builder $query, Carbon $date = null): Builder
    {
        $date = $date ?? now();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($subQ) use ($date) {
                // Sin fecha de inicio O fecha de inicio <= fecha
                $subQ->whereNull('valid_from')
                     ->orWhere('valid_from', '<=', $date);
            })->where(function ($subQ) use ($date) {
                // Sin fecha de fin O fecha de fin >= fecha
                $subQ->whereNull('valid_until')
                     ->orWhere('valid_until', '>=', $date);
            });
        });
    }

    /**
     * Ordenar por prioridad (1 = más alta).
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Datos para conocimientos de embarque.
     */
    public function scopeForBillOfLading(Builder $query, ?int $billId = null): Builder
    {
        return $query->forContext('bill_of_lading', $billId);
    }

    /**
     * Datos para manifiestos.
     */
    public function scopeForManifest(Builder $query, ?int $manifestId = null): Builder
    {
        return $query->forContext('manifest', $manifestId);
    }

    /**
     * Datos para viajes.
     */
    public function scopeForVoyage(Builder $query, ?int $voyageId = null): Builder
    {
        return $query->forContext('voyage', $voyageId);
    }

    // =====================================================
    // MÉTODOS DE NEGOCIO PRINCIPALES
    // =====================================================

    /**
     * Obtiene los datos de display para un contexto específico.
     *
     * @param int $clientId ID del cliente
     * @param string $contextType Tipo de contexto
     * @param int|null $contextId ID específico del contexto
     * @param int $companyId ID de la empresa
     * @param Carbon|null $date Fecha de validez (default: ahora)
     * @return array|null Datos de display o null si no hay
     */
    public static function getDataForContext(
        int $clientId,
        string $contextType,
        ?int $contextId = null,
        int $companyId = null,
        Carbon $date = null
    ): ?array {
        // Intentar obtener datos específicos del contexto
        $specificData = self::forClient($clientId)
            ->forContext($contextType, $contextId)
            ->when($companyId, fn($q) => $q->forCompany($companyId))
            ->validOn($date)
            ->byPriority()
            ->first();

        if ($specificData) {
            return $specificData->toDisplayArray();
        }

        // Fallback: datos por defecto del cliente
        $defaultData = self::forClient($clientId)
            ->defaults()
            ->when($companyId, fn($q) => $q->forCompany($companyId))
            ->validOn($date)
            ->byPriority()
            ->first();

        return $defaultData ? $defaultData->toDisplayArray() : null;
    }

    /**
     * Obtiene datos completos mezclando datos oficiales con datos de display.
     *
     * @param Client $client Cliente base
     * @param string $contextType Tipo de contexto
     * @param int|null $contextId ID específico del contexto
     * @param int|null $companyId ID de la empresa
     * @return array Datos completos para el documento
     */
    public static function getMergedDataForDocument(
        Client $client,
        string $contextType,
        ?int $contextId = null,
        ?int $companyId = null
    ): array {
        // Datos oficiales del cliente
        $officialData = [
            'tax_id' => $client->tax_id,
            'formatted_tax_id' => $client->getFormattedTaxId(),
            'country_code' => $client->country->iso_code,
            'client_type' => $client->client_type,
            'status' => $client->status,
            'verified' => $client->isVerified(),
        ];

        // Datos de display (variables según contexto)
        $displayData = self::getDataForContext(
            $client->id,
            $contextType,
            $contextId,
            $companyId
        );

        // Si no hay datos de display, usar datos oficiales
        if (!$displayData) {
            $displayData = [
                'name' => $client->business_name,
                'address' => null,
                'city' => null,
                'postal_code' => null,
                'phone' => null,
                'email' => null,
            ];
        }

        // Mezclar datos oficiales (inmutables) con datos de display (variables)
        return array_merge($officialData, [
            'display' => $displayData,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Crea o actualiza datos de display para un contexto.
     */
    public static function setDataForContext(
        int $clientId,
        string $contextType,
        array $displayData,
        ?int $contextId = null,
        int $companyId = null,
        int $userId = null,
        array $options = []
    ): self {
        $attributes = array_merge([
            'client_id' => $clientId,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'company_id' => $companyId,
            'created_by_user_id' => $userId,
        ], $displayData, $options);

        // Buscar registro existente
        $existing = self::forClient($clientId)
            ->forContext($contextType, $contextId)
            ->when($companyId, fn($q) => $q->forCompany($companyId))
            ->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return self::create($attributes);
    }

    /**
     * Establece datos por defecto para un cliente.
     */
    public static function setDefaultData(
        int $clientId,
        array $displayData,
        int $companyId,
        int $userId
    ): self {
        $attributes = array_merge($displayData, [
            'is_default' => true,
            'priority' => 1,
        ]);

        return self::setDataForContext(
            $clientId,
            'default',
            $attributes,
            null,
            $companyId,
            $userId
        );
    }

    // =====================================================
    // MÉTODOS DE INSTANCIA
    // =====================================================

    /**
     * Verifica si está vigente en una fecha.
     */
    public function isValidOn(Carbon $date = null): bool
    {
        $date = $date ?? now();

        $fromValid = is_null($this->valid_from) || $this->valid_from <= $date;
        $untilValid = is_null($this->valid_until) || $this->valid_until >= $date;

        return $fromValid && $untilValid;
    }

    /**
     * Verifica si es dato por defecto.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Verifica si es dato específico.
     */
    public function isSpecific(): bool
    {
        return !$this->is_default;
    }

    /**
     * Obtiene el contexto completo.
     */
    public function getFullContext(): string
    {
        if ($this->context_id) {
            return "{$this->context_type}:{$this->context_id}";
        }

        return $this->context_type;
    }

    /**
     * Convierte a array para uso en documentos.
     */
    public function toDisplayArray(): array
    {
        return [
            'name' => $this->display_name,
            'address' => $this->display_address,
            'city' => $this->display_city,
            'postal_code' => $this->display_postal_code,
            'phone' => $this->display_phone,
            'email' => $this->display_email,
            'additional' => $this->additional_data ?? [],
            'context' => $this->getFullContext(),
            'priority' => $this->priority,
            'is_default' => $this->is_default,
        ];
    }

    /**
     * Obtiene datos adicionales específicos.
     */
    public function getAdditionalData(string $key, $default = null)
    {
        return $this->additional_data[$key] ?? $default;
    }

    /**
     * Establece datos adicionales específicos.
     */
    public function setAdditionalData(string $key, $value): bool
    {
        $data = $this->additional_data ?? [];
        $data[$key] = $value;
        $this->additional_data = $data;

        return $this->save();
    }

    /**
     * Obtiene el tipo de contexto en formato legible.
     */
    public function getContextTypeLabel(): string
    {
        return self::CONTEXT_TYPES[$this->context_type] ?? $this->context_type;
    }

    /**
     * Obtiene descripción completa del contexto.
     */
    public function getContextDescription(): string
    {
        $label = $this->getContextTypeLabel();

        if ($this->context_id) {
            return "{$label} #{$this->context_id}";
        }

        return $label;
    }

    /**
     * Clona datos para un nuevo contexto.
     */
    public function cloneForContext(string $newContextType, ?int $newContextId = null): self
    {
        $attributes = $this->toArray();

        // Remover campos que no se deben clonar
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        // Establecer nuevo contexto
        $attributes['context_type'] = $newContextType;
        $attributes['context_id'] = $newContextId;
        $attributes['is_default'] = false;

        return self::create($attributes);
    }

    // =====================================================
    // MÉTODOS AUXILIARES ESTÁTICOS
    // =====================================================

    /**
     * Obtiene opciones para select de tipos de contexto.
     */
    public static function getContextTypeOptions(): array
    {
        return self::CONTEXT_TYPES;
    }

    /**
     * Busca datos por cliente y empresa.
     */
    public static function getClientDataByCompany(int $clientId, int $companyId): Collection
    {
        return self::forClient($clientId)
            ->forCompany($companyId)
            ->validOn()
            ->byPriority()
            ->get();
    }

    /**
     * Obtiene estadísticas de datos variables.
     */
    public static function getStatsForClient(int $clientId): array
    {
        $data = self::forClient($clientId);

        return [
            'total' => $data->count(),
            'by_context' => $data->get()->groupBy('context_type')->map->count(),
            'defaults' => $data->clone()->defaults()->count(),
            'specific' => $data->clone()->specific()->count(),
            'companies' => $data->get()->pluck('company_id')->unique()->count(),
        ];
    }

    /**
     * Limpia datos vencidos.
     */
    public static function cleanExpiredData(Carbon $beforeDate = null): int
    {
        $beforeDate = $beforeDate ?? now()->subMonths(6);

        return self::where('valid_until', '<', $beforeDate)
            ->where('is_default', false)
            ->delete();
    }

    // =====================================================
    // EVENTOS DEL MODELO
    // =====================================================

    /**
     * Boot del modelo para eventos automáticos.
     */
    protected static function booted(): void
    {
        // Validar que solo haya un registro por defecto por cliente-empresa
        static::saving(function (ClientDocumentData $data) {
            if ($data->is_default) {
                // Desmarcar otros defaults del mismo cliente-empresa
                self::forClient($data->client_id)
                    ->forCompany($data->company_id)
                    ->defaults()
                    ->where('id', '!=', $data->id ?? 0)
                    ->update(['is_default' => false]);
            }
        });
    }
}
