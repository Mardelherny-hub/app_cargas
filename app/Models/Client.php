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
 * @property int $id
 * @property string $tax_id CUIT/RUC del cliente
 * @property int $country_id País del cliente
 * @property int $document_type_id Tipo de documento
 * @property string $client_type Rol del cliente (shipper, consignee, etc.)
 * @property string $legal_name Razón social oficial
 * @property int|null $primary_port_id Puerto principal
 * @property int|null $customs_offices_id Aduana habitual
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
     * Cargar automáticamente relaciones.
     */
    protected $with = [
        'primaryContact',  
    ];

    /**
     * Tipos de cliente disponibles.
     * ACTUALIZADO: Cambio de nomenclatura según feedback del cliente
     */
    public const CLIENT_TYPES = [
        'shipper' => 'Cargador/Exportador',
        'consignee' => 'Consignatario/Importador',
        'notify_party' => 'Notificatario',  // ← CAMBIADO
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
        $contact = $this->primaryContact;
        return $contact && 
               !empty($contact->email) && 
               !empty($contact->address_line_1) && 
               !empty($contact->city);
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

    /**
     * Crear contacto principal para el cliente.
     */
    public function createPrimaryContact(array $data): ClientContactData
    {
        // Asegurar que sea el contacto principal
        $data['is_primary'] = true;
        $data['active'] = true;
        
        // Desactivar otros contactos primarios si existen
        $this->contactData()->where('is_primary', true)->update(['is_primary' => false]);
        
        return $this->contactData()->create($data);
    }

    /**
     * Actualizar o crear contacto principal.
     */
    public function updateOrCreatePrimaryContact(array $data): ClientContactData
    {
        $contact = $this->primaryContact;
        
        if ($contact) {
            $contact->update($data);
            return $contact;
        }
        
        return $this->createPrimaryContact($data);
    }

    // =====================================================
    // RELACIONES EXISTENTES
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
     * Empresa que creó el cliente.
     */
    public function createdByCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'created_by_company_id');
    }

    /**
     * Relaciones con empresas.
     */
    public function companyRelations(): HasMany
    {
        return $this->hasMany(ClientCompanyRelation::class);
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
}