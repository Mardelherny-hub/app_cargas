<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   /**
    * Run the migrations.
    * 
    * PROPÓSITO: Permitir direcciones específicas por conocimiento de embarque
    * REQUERIMIENTO DEL CLIENTE: "La dirección debería poder ingresarse en cada conocimiento"
    * 
    * CONTEXTO: El mismo cliente puede tener direcciones distintas según 
    * lo manifestado en cada conocimiento para cumplir con el conocimiento oceánico original
    */
   public function up(): void
   {
       Schema::create('bill_of_lading_contacts', function (Blueprint $table) {
           // Primary key
           $table->id();

           // Referencias principales
           $table->foreignId('bill_of_lading_id')
               ->constrained('bills_of_lading')
               ->onDelete('cascade')
               ->comment('Bill of Lading al que pertenece');

           $table->foreignId('client_contact_data_id')
               ->constrained('client_contact_data')
               ->onDelete('cascade')
               ->comment('Contacto base del cliente');

           // Rol en este conocimiento específico
           $table->enum('role', [
               'shipper',          // Cargador/Exportador
               'consignee',        // Consignatario/Importador  
               'notify_party',     // Parte a notificar
               'cargo_owner'       // Dueño de la carga
           ])->comment('Rol del contacto en este conocimiento');

           // ============================================
           // DIRECCIÓN ESPECÍFICA PARA ESTE CONOCIMIENTO
           // (Sobrescribe datos del client_contact_data)
           // ============================================

           $table->string('specific_company_name', 255)
               ->nullable()
               ->comment('Nombre específico para este conocimiento (ej: ALUAR SOCIEDAD ANONIMA)');

           $table->string('specific_address_line_1', 255)
               ->nullable()
               ->comment('Dirección específica línea 1 para este conocimiento');

           $table->string('specific_address_line_2', 255)
               ->nullable()
               ->comment('Dirección específica línea 2 para este conocimiento');

           $table->string('specific_city', 100)
               ->nullable()
               ->comment('Ciudad específica para este conocimiento');

           $table->string('specific_state_province', 100)
               ->nullable()
               ->comment('Provincia específica para este conocimiento');

           $table->string('specific_postal_code', 20)
               ->nullable()
               ->comment('Código postal específico para este conocimiento');

           $table->string('specific_country', 100)
               ->nullable()
               ->comment('País específico para este conocimiento');

           // Contacto específico para este conocimiento
           $table->string('specific_contact_person', 255)
               ->nullable()
               ->comment('Persona de contacto específica para este conocimiento');

           $table->string('specific_phone', 20)
               ->nullable()
               ->comment('Teléfono específico para este conocimiento');

           $table->string('specific_email', 255)
               ->nullable()
               ->comment('Email específico para este conocimiento');

           // Metadatos
           $table->text('notes')
               ->nullable()
               ->comment('Notas sobre este contacto en este conocimiento');

           $table->boolean('use_specific_data')
               ->default(false)
               ->comment('TRUE: usar datos específicos, FALSE: usar datos del contacto base');

           // Auditoría
           $table->unsignedBigInteger('created_by_user_id')
               ->nullable()
               ->comment('Usuario que creó la relación');

           $table->timestamps();

           // ============================================
           // ÍNDICES Y CONSTRAINTS
           // ============================================

           // Un rol por conocimiento (shipper único, consignee único, etc.)
           $table->unique(['bill_of_lading_id', 'role'], 'unique_bill_role');

           // Índice para consultas por conocimiento
           $table->index(['bill_of_lading_id', 'role'], 'idx_bill_contacts');

           // Índice para consultas por cliente
           $table->index(['client_contact_data_id', 'role'], 'idx_client_contacts');

           // Foreign key para auditoría
           $table->foreign('created_by_user_id')
               ->references('id')
               ->on('users')
               ->onDelete('set null');
       });
   }

   /**
    * Reverse the migrations.
    */
   public function down(): void
   {
       Schema::dropIfExists('bill_of_lading_contacts');
   }
};

/*
CASOS DE USO:

1. USAR CONTACTO BASE (use_specific_data = false):
  - Toma datos de client_contact_data
  - Caso común para clientes regulares

2. USAR DATOS ESPECÍFICOS (use_specific_data = true):
  - Usa campos specific_* de esta tabla
  - Para casos como: "ALUAR S.A." en BD vs "ALUAR SOCIEDAD ANONIMA" en conocimiento
  - Direcciones diferentes del cliente según el conocimiento oceánico original

3. MÚLTIPLES ROLES:
  - Un conocimiento puede tener shipper, consignee, notify_party diferentes
  - Cada uno con su dirección específica

COMPATIBILIDAD:
- Bills of Lading existentes siguen funcionando con shipper_id, consignee_id
- Esta tabla es opcional/adicional para casos específicos
*/