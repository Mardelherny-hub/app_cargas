<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
* Modelo BillOfLadingContact
* 
* Gestiona contactos específicos por conocimiento de embarque
* Permite direcciones diferentes del mismo cliente según el conocimiento
* 
* @property int $id
* @property int $bill_of_lading_id
* @property int $client_contact_data_id
* @property string $role
* @property string|null $specific_company_name
* @property string|null $specific_address_line_1
* @property string|null $specific_address_line_2
* @property string|null $specific_city
* @property string|null $specific_state_province
* @property string|null $specific_postal_code
* @property string|null $specific_country
* @property string|null $specific_contact_person
* @property string|null $specific_phone
* @property string|null $specific_email
* @property string|null $notes
* @property bool $use_specific_data
* @property int|null $created_by_user_id
* @property \Carbon\Carbon $created_at
* @property \Carbon\Carbon $updated_at
* 
* @property-read BillOfLading $billOfLading
* @property-read ClientContactData $contactData
* @property-read User|null $createdBy
* @property-read string $display_name
* @property-read string $full_address
* @property-read string $contact_info
*/
class BillOfLadingContact extends Model
{
   use HasFactory;

   /**
    * Tabla asociada
    */
   protected $table = 'bill_of_lading_contacts';

   /**
    * Roles disponibles
    */
   public const ROLES = [
       'shipper' => 'Cargador/Exportador',
       'consignee' => 'Consignatario/Importador', 
       'notify_party' => 'Parte a Notificar',
       'cargo_owner' => 'Dueño de la Carga'
   ];

   /**
    * Atributos asignables en masa
    */
   protected $fillable = [
       'bill_of_lading_id',
       'client_contact_data_id', 
       'role',
       'specific_company_name',
       'specific_address_line_1',
       'specific_address_line_2',
       'specific_city',
       'specific_state_province',
       'specific_postal_code',
       'specific_country',
       'specific_contact_person',
       'specific_phone',
       'specific_email',
       'notes',
       'use_specific_data',
       'created_by_user_id',
   ];

   /**
    * Casting de atributos
    */
   protected $casts = [
       'use_specific_data' => 'boolean',
       'created_at' => 'datetime',
       'updated_at' => 'datetime',
   ];

   // =====================================================
   // RELACIONES
   // =====================================================

   /**
    * Bill of Lading al que pertenece
    */
   public function billOfLading(): BelongsTo
   {
       return $this->belongsTo(BillOfLading::class);
   }

   /**
    * Datos de contacto base del cliente
    */
   public function contactData(): BelongsTo
   {
       return $this->belongsTo(ClientContactData::class, 'client_contact_data_id');
   }

   /**
    * Cliente (a través del contacto)
    */
   public function client(): BelongsTo
   {
       return $this->contactData()->getRelated()->client();
   }

   /**
    * Usuario que creó la relación
    */
   public function createdBy(): BelongsTo
   {
       return $this->belongsTo(User::class, 'created_by_user_id');
   }

   // =====================================================
   // ACCESSORS - DATOS INTELIGENTES
   // =====================================================

   /**
    * Obtener nombre de empresa para mostrar
    * Usa específico si existe, sino el del cliente base
    */
   public function getDisplayNameAttribute(): string
   {
       if ($this->use_specific_data && !empty($this->specific_company_name)) {
           return $this->specific_company_name;
       }

       return $this->contactData->client->legal_name ?? 'Sin nombre';
   }

   /**
    * Obtener dirección completa inteligente
    * Usa específica si está marcado, sino la del contacto base
    */
   public function getFullAddressAttribute(): string
   {
       if ($this->use_specific_data && $this->hasSpecificAddress()) {
           return $this->buildSpecificAddress();
       }

       return $this->buildContactDataAddress();
   }

   /**
    * Obtener información de contacto (teléfono/email)
    */
   public function getContactInfoAttribute(): string
   {
       $info = [];

       $phone = $this->use_specific_data && $this->specific_phone 
           ? $this->specific_phone 
           : $this->contactData->phone;

       $email = $this->use_specific_data && $this->specific_email
           ? $this->specific_email
           : $this->contactData->email;

       if ($phone) $info[] = "Tel: {$phone}";
       if ($email) $info[] = "Email: {$email}";

       return implode(' | ', $info);
   }

   /**
    * Obtener persona de contacto
    */
   public function getContactPersonAttribute(): string
   {
       if ($this->use_specific_data && $this->specific_contact_person) {
           return $this->specific_contact_person;
       }

       return $this->contactData->contact_person_name ?? '';
   }

   /**
    * Obtener label del rol
    */
   public function getRoleLabelAttribute(): string
   {
       return self::ROLES[$this->role] ?? $this->role;
   }

   // =====================================================
   // MÉTODOS AUXILIARES
   // =====================================================

   /**
    * Verificar si tiene dirección específica completa
    */
   public function hasSpecificAddress(): bool
   {
       return !empty($this->specific_address_line_1) || !empty($this->specific_city);
   }

   /**
    * Construir dirección específica
    */
   protected function buildSpecificAddress(): string
   {
       $parts = array_filter([
           $this->specific_address_line_1,
           $this->specific_address_line_2,
           $this->specific_city,
           $this->specific_state_province,
           $this->specific_postal_code,
           $this->specific_country,
       ]);

       return implode(', ', $parts);
   }

   /**
    * Construir dirección del contacto base
    */
   protected function buildContactDataAddress(): string
   {
       $contact = $this->contactData;
       
       $parts = array_filter([
           $contact->address_line_1,
           $contact->address_line_2,
           $contact->city,
           $contact->state_province,
           $contact->postal_code,
       ]);

       return implode(', ', $parts);
   }

   /**
    * Obtener datos completos para impresión/XML
    */
   public function getCompleteDataForExport(): array
   {
       return [
           'company_name' => $this->display_name,
           'address' => $this->full_address,
           'contact_person' => $this->contact_person,
           'phone' => $this->use_specific_data && $this->specific_phone 
               ? $this->specific_phone 
               : $this->contactData->phone,
           'email' => $this->use_specific_data && $this->specific_email
               ? $this->specific_email
               : $this->contactData->email,
           'tax_id' => $this->contactData->client->tax_id ?? '',
           'role' => $this->role,
           'role_label' => $this->role_label,
       ];
   }

   // =====================================================
   // SCOPES
   // =====================================================

   /**
    * Filtrar por rol
    */
   public function scopeByRole($query, string $role)
   {
       return $query->where('role', $role);
   }

   /**
    * Filtrar por Bill of Lading
    */
   public function scopeByBillOfLading($query, int $billOfLadingId)
   {
       return $query->where('bill_of_lading_id', $billOfLadingId);
   }

   /**
    * Con datos específicos
    */
   public function scopeWithSpecificData($query)
   {
       return $query->where('use_specific_data', true);
   }

   // =====================================================
   // VALIDACIONES
   // =====================================================

   /**
    * Reglas de validación
    */
   public static function validationRules(): array
   {
       return [
           'bill_of_lading_id' => 'required|exists:bills_of_lading,id',
           'client_contact_data_id' => 'required|exists:client_contact_data,id',
           'role' => 'required|in:' . implode(',', array_keys(self::ROLES)),
           'specific_company_name' => 'nullable|string|max:255',
           'specific_address_line_1' => 'nullable|string|max:255',
           'specific_address_line_2' => 'nullable|string|max:255',
           'specific_city' => 'nullable|string|max:100',
           'specific_state_province' => 'nullable|string|max:100',
           'specific_postal_code' => 'nullable|string|max:20',
           'specific_country' => 'nullable|string|max:100',
           'specific_contact_person' => 'nullable|string|max:255',
           'specific_phone' => 'nullable|string|max:20',
           'specific_email' => 'nullable|email|max:255',
           'use_specific_data' => 'boolean',
           'notes' => 'nullable|string|max:1000',
       ];
   }

   /**
    * Mensajes de validación personalizados
    */
   public static function validationMessages(): array
   {
       return [
           'bill_of_lading_id.required' => 'El conocimiento de embarque es obligatorio.',
           'client_contact_data_id.required' => 'El contacto del cliente es obligatorio.',
           'role.required' => 'El rol es obligatorio.',
           'role.in' => 'El rol debe ser uno de los permitidos.',
           'specific_email.email' => 'El email específico debe tener formato válido.',
       ];
   }
}