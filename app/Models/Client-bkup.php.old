<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\ClientContactData;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Modelo Client para gestión de clientes con CUIT/RUC
 * Resuelve la problemática de registro simple y datos variables
 *
 * CORRECCIÓN APLICADA: 
 * - ❌ REMOVIDO: client_type 'owner' (ahora en VesselOwner)
 * - ❌ REMOVIDO: relación companyRelations (base de datos compartida)
 * - ✅ MANTIENE: created_by_company_id (solo para auditoría)
 * - ✅ CORRECCIÓN CRÍTICA: client_type → client_roles (JSON array para múltiples roles)
 *
 * @property int $id
 * @property string $tax_id CUIT/RUC del cliente
 * @property int $country_id País del cliente
 * @property int $document_type_id Tipo de documento
 * @property array $client_roles Array de roles del cliente ['shipper', 'consignee', 'notify_party']
 * @property string $legal_name Razón social oficial
 * @property int|null $primary_port_id Puerto principal
 * @property int|null $customs_offices_id Aduana habitual
 * @property string $status Estado (active, inactive, suspended)
 * @property int $created_by_company_id Empresa creadora (solo auditoría)
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
        'client_roles',
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
        'client_roles' => 'array',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
 
    /**
     * Cargar automáticamente relaciones.
     */
    protected $with = [
        'primaryContact',  
    ];

    /**
     * Roles de cliente disponibles.
     * CORRECCIÓN: Cambio de CLIENT_TYPES a CLIENT_ROLES
     */
    public const CLIENT_ROLES = [
        'shipper' => 'Cargador/Exportador',
        'consignee' => 'Consignatario/Importador',
        'notify_party' => 'Notificatario',
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
    // RELACIONES CON CLIENT_CONTACT_DATA
    // =====================================================

    /**
     * Relación con datos de contacto.
     * Ordenados por prioridad (primario primero)
     */
    public function contactData(): HasMany
    {
        return $this->hasMany(ClientContactData::class)
                   ->orderBy('is_primary', 'desc')
                   ->orderBy('active', 'desc');
    }

    /**
     * Contacto principal del cliente.
     * Solo retorna el contacto marcado como primario y activo
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContactData::class)
                   ->where('is_primary', true)
                   ->where('active', true);
    }

    /**
     * Contactos activos.
     */
    public function activeContacts(): HasMany
    {
        return $this->hasMany(ClientContactData::class)
                   ->where('active', true)
                   ->orderBy('is_primary', 'desc');
    }

    // =====================================================
    // MÉTODOS AUXILIARES PARA DATOS DE CONTACTO
    // =====================================================

    /**
     * Obtener email principal del cliente.
     */
    public function getPrimaryEmail(): ?string
    {
        return $this->primaryContact?->email;
    }

    /**
     * Obtener teléfono principal del cliente.
     */
    public function getPrimaryPhone(): ?string
    {
        return $this->primaryContact?->phone ?: $this->primaryContact?->mobile_phone;
    }

    /**
     * Obtener dirección principal formateada.
     */
    public function getPrimaryAddress(): ?string
    {
        $contact = $this->primaryContact;
        if (!$contact) return null;

        $parts = array_filter([
            $contact->address_line_1,
            $contact->address_line_2,
            $contact->city,
            $contact->state_province,
            $contact->postal_code
        ]);

        return implode(', ', $parts) ?: null;
    }

    /**
     * Verificar si el cliente tiene información de contacto completa.
     */
    public function hasCompleteContactInfo(): bool
    {
        $primary = $this->primaryContact;
        return $primary && ($primary->email || $primary->phone);
    }

    // =====================================================
    // RELACIONES CON OTRAS ENTIDADES
    // =====================================================

    /**
     * País del cliente.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Tipo de documento.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Puerto principal.
     */
    public function primaryPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'primary_port_id');
    }

    /**
     * Aduana habitual.
     */
    public function customOffice(): BelongsTo
    {
        return $this->belongsTo(CustomOffice::class, 'customs_offices_id');
    }

    /**
     * Empresa que creó el cliente (solo para auditoría).
     * NOTA: Los clientes son ahora base de datos compartida.
     */
    public function createdByCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'created_by_company_id');
    }

    // =====================================================
    // MÉTODOS AUXILIARES PARA FORMULARIOS
    // =====================================================

    /**
     * Opciones para select de roles de cliente.
     * CORRECCIÓN: Cambio de getClientTypeOptions a getClientRoleOptions
     */
    public static function getClientRoleOptions(): array
    {
        return self::CLIENT_ROLES;
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
    // MÉTODOS PARA GESTIÓN DE ROLES MÚLTIPLES
    // =====================================================

    /**
     * Verificar si el cliente tiene un rol específico.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->client_roles ?? []);
    }

    /**
     * Verificar si el cliente tiene alguno de los roles especificados.
     */
    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($this->client_roles ?? [], $roles));
    }

    /**
     * Obtener roles formateados para mostrar.
     */
    public function getRolesForDisplay(): string
    {
        if (empty($this->client_roles)) {
            return 'Sin roles asignados';
        }

        $roleLabels = [];
        foreach ($this->client_roles as $role) {
            $roleLabels[] = self::CLIENT_ROLES[$role] ?? $role;
        }

        return implode(', ', $roleLabels);
    }

    /**
     * Obtener el rol principal (primer rol en el array).
     */
    public function getPrimaryRole(): ?string
    {
        return $this->client_roles[0] ?? null;
    }

    /**
     * Verificar si es un cliente cargador.
     */
    public function isShipper(): bool
    {
        return $this->hasRole('shipper');
    }

    /**
     * Verificar si es un cliente consignatario.
     */
    public function isConsignee(): bool
    {
        return $this->hasRole('consignee');
    }

    /**
     * Verificar si es parte a notificar.
     */
    public function isNotifyParty(): bool
    {
        return $this->hasRole('notify_party');
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
            // Limpiar CUIT/RUC
            if (!empty($client->tax_id)) {
                $client->tax_id = preg_replace('/[^0-9]/', '', $client->tax_id);
            }
            
            // CORRECCIÓN: Manejo seguro de client_roles
            $roles = $client->client_roles;
            
            // Convertir a array si no lo es, o si está vacío
            if (!is_array($roles) || empty($roles)) {
                $client->client_roles = ['consignee']; // Rol por defecto
            }
            
            // Limpiar duplicados si es array
            if (is_array($client->client_roles)) {
                $client->client_roles = array_values(array_unique($client->client_roles));
            }
        });
    }

    // =====================================================
    // MÉTODOS DE NEGOCIO
    // =====================================================

    /**
     * Verificar si el cliente está verificado.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Obtener CUIT/RUC formateado.
     */
    public function getFormattedTaxId(): string
    {
        if ($this->country_id == 1) { // Argentina
            return substr($this->tax_id, 0, 2) . '-' . 
                   substr($this->tax_id, 2, 8) . '-' . 
                   substr($this->tax_id, 10, 1);
        }
        
        return $this->tax_id;
    }

    /**
     * Obtener identificador para webservices.
     */
    public function getWebserviceIdentifier(): string
    {
        return $this->country->alpha2_code . '_' . $this->tax_id;
    }

        /**
     * Obtener todos los emails para cartas de arribo.
     */
    public function getArrivalNoticeEmails(): array
    {
        return $this->contactData()
                    ->arrivalNoticeContacts()
                    ->whereNotNull('email')
                    ->pluck('email')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
    }

    /**
     * Verificar si tiene contactos de un tipo específico.
     */
    public function hasContactType(string $type): bool
    {
        return $this->contactData()->byType($type)->exists();
    }

    /**
     * Obtener contacto principal de un tipo específico.
     */
    public function getPrimaryContactOfType(string $type): ?ClientContactData
    {
        return $this->contactData()
                    ->byType($type)
                    ->where('active', true)
                    ->orderBy('is_primary', 'desc')
                    ->first();
    }

     /**
     * Verificar si puede recibir notificaciones por email.
     */
    public function canReceiveEmailNotifications(): bool
    {
        $contact = $this->primaryContact;
        return $contact && 
               $contact->accepts_email_notifications && 
               !empty($contact->email);
    }

}