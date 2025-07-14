<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Modelo Client para gestión de clientes con CUIT/RUC
 * Resuelve la problemática de registro simple y datos variables
 *
 * @property int $id
 * @property string $tax_id CUIT/RUC del cliente
 * @property int $country_id País del cliente
 * @property int $document_type_id Tipo de documento
 * @property string $client_type Rol del cliente (shipper, consignee, etc.)
 * @property string $legal_name Razón social oficial
 * @property int|null $primary_port_id Puerto principal
 * @property int|null $custom_office_id Aduana habitual
 * @property string $status Estado (active, inactive, suspended)
 * @property int $created_by_company_id Empresa creadora
 * @property Carbon|null $verified_at Fecha verificación CUIT
 * @property string|null $notes Observaciones
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Client extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     */
    protected $table = 'clients';

    /**
     * Atributos asignables en masa.
     */
    protected $fillable = [
        'tax_id',
        'country_id',
        'document_type_id',
        'client_type',
        'legal_name',
        'primary_port_id',
        'customs_offices_id',
        'status',
        'created_by_company_id',
        'verified_at',
        'notes',
    ];

    /**
     * Atributos que deben ser tratados como fechas.
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de cliente disponibles.
     */
    public const CLIENT_TYPES = [
        'shipper' => 'Cargador/Exportador',
        'consignee' => 'Consignatario/Importador',
        'notify_party' => 'Parte a Notificar',
        'owner' => 'Propietario de la Carga',
    ];

    /**
     * Estados disponibles.
     */
    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'suspended' => 'Suspendido',
    ];

    // =====================================================
    // RELACIONES CON CATÁLOGOS (FASE 0)
    // =====================================================

    /**
     * País del cliente.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Tipo de documento según país.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Puerto principal de operaciones.
     */
    public function primaryPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'primary_port_id');
    }

    /**
     * Aduana habitual de operaciones.
     */
    public function customOffice(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class);
    }

    /**
     * Empresa que creó el registro.
     */
    public function createdByCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'created_by_company_id');
    }

    /**
     * Relaciones del cliente con empresas (futuro FASE 1).
     * Preparado para tabla client_company_relations.
     */
    public function companyRelations(): HasMany
    {
        return $this->hasMany(ClientCompanyRelation::class);
    }

    /**
     * Datos variables del cliente (futuro FASE 2).
     * Preparado para tabla client_document_data.
     */
    public function documentData(): HasMany
    {
        return $this->hasMany(ClientDocumentData::class);
    }

    // =====================================================
    // SCOPES ESPECIALIZADOS PARA CONSULTAS FRECUENTES
    // =====================================================

    /**
     * Solo clientes activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Filtrar por país.
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->whereHas('country', function ($q) use ($countryCode) {
            $q->where('iso_code', $countryCode);
        });
    }

    /**
     * Clientes argentinos.
     */
    public function scopeArgentina(Builder $query): Builder
    {
        return $query->byCountry('AR');
    }

    /**
     * Clientes paraguayos.
     */
    public function scopeParaguay(Builder $query): Builder
    {
        return $query->byCountry('PY');
    }

    /**
     * Filtrar por tipo de cliente.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('client_type', $type);
    }

    /**
     * Solo cargadores/exportadores.
     */
    public function scopeShippers(Builder $query): Builder
    {
        return $query->byType('shipper');
    }

    /**
     * Solo consignatarios/importadores.
     */
    public function scopeConsignees(Builder $query): Builder
    {
        return $query->byType('consignee');
    }

    /**
     * Filtrar por empresa creadora.
     */
    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('created_by_company_id', $companyId);
    }

    /**
     * Búsqueda por CUIT/RUC.
     */
    public function scopeByTaxId(Builder $query, string $taxId): Builder
    {
        // Limpiar caracteres no numéricos para búsqueda flexible
        $cleanTaxId = preg_replace('/[^0-9]/', '', $taxId);
        return $query->where('tax_id', 'LIKE', "%{$cleanTaxId}%");
    }

    /**
     * Búsqueda por nombre (parcial, insensible a mayúsculas).
     */
    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where('legal_name', 'LIKE', "%{$name}%");
    }

    /**
     * Clientes verificados (CUIT validado).
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Clientes pendientes de verificación.
     */
    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Filtrar por puerto principal.
     */
    public function scopeByPort(Builder $query, int $portId): Builder
    {
        return $query->where('primary_port_id', $portId);
    }

    // =====================================================
    // MÉTODOS DE NEGOCIO ESPECÍFICOS DEL DOMINIO
    // =====================================================

    /**
     * Verifica si el cliente está activo y verificado.
     */
    public function isOperational(): bool
    {
        return $this->status === 'active' && $this->isVerified();
    }

    /**
     * Verifica si el CUIT/RUC ha sido validado.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Verifica si es cliente argentino.
     */
    public function isArgentinian(): bool
    {
        return $this->country->iso_code === 'AR';
    }

    /**
     * Verifica si es cliente paraguayo.
     */
    public function isParaguayan(): bool
    {
        return $this->country->iso_code === 'PY';
    }

    /**
     * Obtiene el formato correcto del CUIT/RUC según país.
     */
    public function getFormattedTaxId(): string
    {
        if ($this->isArgentinian()) {
            // Formato CUIT argentino: XX-XXXXXXXX-X
            return preg_replace('/(\d{2})(\d{8})(\d{1})/', '$1-$2-$3', $this->tax_id);
        }

        if ($this->isParaguayan()) {
            // Formato RUC paraguayo: XXXXXXX-X
            return preg_replace('/(\d{7})(\d{1})/', '$1-$2', $this->tax_id);
        }

        return $this->tax_id;
    }

    /**
     * Valida el formato del CUIT/RUC según el país.
     */
    public function validateTaxIdFormat(): bool
    {
        if ($this->isArgentinian()) {
            return $this->validateArgentinianCuit();
        }

        if ($this->isParaguayan()) {
            return $this->validateParaguayanRuc();
        }

        return false;
    }

    /**
     * Validación específica CUIT argentino (algoritmo mod11).
     */
    protected function validateArgentinianCuit(): bool
    {
        $cuit = preg_replace('/[^0-9]/', '', $this->tax_id);

        if (strlen($cuit) !== 11) {
            return false;
        }

        // Algoritmo de validación CUIT argentino
        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cuit[$i]) * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return intval($cuit[10]) === $checkDigit;
    }

    /**
     * Validación específica RUC paraguayo.
     */
    protected function validateParaguayanRuc(): bool
    {
        $ruc = preg_replace('/[^0-9]/', '', $this->tax_id);

        if (strlen($ruc) < 6 || strlen($ruc) > 8) {
            return false;
        }

        // Validación básica RUC paraguayo
        // TODO: Implementar algoritmo específico si está disponible
        return is_numeric($ruc);
    }

    /**
     * Marca el cliente como verificado.
     */
    public function markAsVerified(): bool
    {
        $this->verified_at = now();
        return $this->save();
    }

    /**
     * Obtiene el tipo de cliente en formato legible.
     */
    public function getClientTypeLabel(): string
    {
        return self::CLIENT_TYPES[$this->client_type] ?? $this->client_type;
    }

    /**
     * Obtiene el estado en formato legible.
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Verifica si puede operar en un puerto específico.
     */
    public function canOperateInPort(int $portId): bool
    {
        // El cliente puede operar en su puerto principal
        if ($this->primary_port_id === $portId) {
            return true;
        }

        // También puede operar en puertos del mismo país
        if ($this->primaryPort) {
            $targetPort = Port::find($portId);
            return $targetPort && $targetPort->country_id === $this->country_id;
        }

        return false;
    }

    /**
     * Genera identificador único para webservices.
     */
    public function getWebserviceIdentifier(): string
    {
        return $this->country->iso_code . '_' . $this->tax_id;
    }

    // =====================================================
    // MÉTODOS AUXILIARES PARA FORMULARIOS
    // =====================================================

    /**
     * Opciones para select de tipos de cliente.
     */
    public static function getClientTypeOptions(): array
    {
        return self::CLIENT_TYPES;
    }

    /**
     * Opciones para select de estados.
     */
    public static function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    /**
     * Buscar cliente por CUIT/RUC limpio.
     */
    public static function findByTaxId(string $taxId, ?int $countryId = null): ?self
    {
        $cleanTaxId = preg_replace('/[^0-9]/', '', $taxId);

        $query = self::where('tax_id', $cleanTaxId);

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        return $query->first();
    }

    // =====================================================
    // EVENTOS DEL MODELO
    // =====================================================

    /**
     * Boot del modelo para eventos automáticos.
     */
    protected static function booted(): void
    {
        // Limpiar CUIT/RUC antes de guardar
        static::saving(function (Client $client) {
            $client->tax_id = preg_replace('/[^0-9]/', '', $client->tax_id);
        });
    }
}
