<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS
     * Tabla bills_of_lading - Conocimientos de embarque
     * Cada conocimiento pertenece a un shipment específico
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - shipments, clients, ports, customs_offices, cargo_types, packaging_types
     */
    public function up(): void
    {
        Schema::create('bills_of_lading', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Reference to shipment (confirmed table)
            $table->foreignId('shipment_id')->constrained('shipments')->comment('Envío al que pertenece');

            // Bill of lading identification
            $table->string('bill_number', 50)->unique()->comment('Número conocimiento embarque');
            $table->string('master_bill_number', 50)->nullable()->comment('Conocimiento madre (consolidados)');
            $table->string('house_bill_number', 50)->nullable()->comment('Conocimiento hijo');
            $table->string('internal_reference', 100)->nullable()->comment('Referencia interna empresa');

            // Client references (confirmed table structure)
            $table->foreignId('shipper_id')->constrained('clients')->comment('Cargador/Exportador');
            $table->foreignId('consignee_id')->constrained('clients')->comment('Consignatario/Importador');
            $table->foreignId('notify_party_id')->nullable()->constrained('clients')->comment('Parte a notificar');
            $table->foreignId('cargo_owner_id')->nullable()->constrained('clients')->comment('Propietario de la carga');

            // Port and customs information (confirmed tables)
            $table->foreignId('loading_port_id')->constrained('ports')->comment('Puerto de carga');
            $table->foreignId('discharge_port_id')->constrained('ports')->comment('Puerto de descarga');
            $table->foreignId('transshipment_port_id')->nullable()->constrained('ports')->comment('Puerto transbordo');
            $table->foreignId('final_destination_port_id')->nullable()->constrained('ports')->comment('Destino final');
            
            $table->foreignId('loading_customs_id')->nullable()->constrained('customs_offices')->comment('Aduana de carga');
            $table->foreignId('discharge_customs_id')->nullable()->constrained('customs_offices')->comment('Aduana de descarga');

            // Cargo classification (confirmed tables)
            $table->foreignId('primary_cargo_type_id')->constrained('cargo_types')->comment('Tipo principal de carga');
            $table->foreignId('primary_packaging_type_id')->constrained('packaging_types')->comment('Tipo principal embalaje');

            // Bill of lading dates
            $table->date('issue_date')->comment('Fecha emisión conocimiento');
            $table->date('loading_date')->comment('Fecha de carga');
            $table->date('discharge_date')->nullable()->comment('Fecha de descarga');
            $table->date('delivery_date')->nullable()->comment('Fecha de entrega');
            $table->date('cargo_ready_date')->nullable()->comment('Fecha mercadería lista');

            // Cargo summary
            $table->integer('total_packages')->default(0)->comment('Total bultos');
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en kilogramos');
            $table->decimal('net_weight_kg', 12, 2)->nullable()->comment('Peso neto en kilogramos');
            $table->decimal('volume_m3', 10, 3)->nullable()->comment('Volumen en metros cúbicos');
            $table->integer('container_count')->default(0)->comment('Cantidad contenedores');

            // Cargo description
            $table->text('cargo_description')->comment('Descripción de la mercadería');
            $table->text('cargo_marks')->nullable()->comment('Marcas de la mercadería');
            $table->string('commodity_code', 20)->nullable()->comment('Código commodity/NCM');
            $table->json('special_instructions')->nullable()->comment('Instrucciones especiales');

            // Bill type and characteristics
            $table->enum('bill_type', [
                'original',         // Original
                'copy',            // Copia
                'duplicate',       // Duplicado
                'amendment'        // Enmienda
            ])->default('original')->comment('Tipo de conocimiento');

            $table->enum('freight_terms', [
                'prepaid',         // Prepagado
                'collect',         // Por cobrar
                'third_party'      // Tercero
            ])->default('prepaid')->comment('Términos de flete');

            $table->enum('payment_terms', [
                'cash',            // Contado
                'credit',          // Crédito
                'letter_of_credit', // Carta crédito
                'other'            // Otro
            ])->default('cash')->comment('Términos de pago');

            // Consolidation and transshipment
            $table->boolean('is_consolidated')->default(false)->comment('Es consolidado');
            $table->boolean('is_master_bill')->default(false)->comment('Es conocimiento madre');
            $table->boolean('is_house_bill')->default(false)->comment('Es conocimiento hijo');
            $table->boolean('allows_transshipment')->default(true)->comment('Permite transbordo');
            $table->boolean('requires_surrender')->default(false)->comment('Requiere entrega');

            // Special cargo characteristics
            $table->boolean('is_dangerous_cargo')->default(false)->comment('Carga peligrosa');
            $table->boolean('is_perishable')->default(false)->comment('Carga perecedera');
            $table->boolean('is_fragile')->default(false)->comment('Carga frágil');
            $table->boolean('requires_refrigeration')->default(false)->comment('Requiere refrigeración');
            $table->boolean('requires_special_handling')->default(false)->comment('Requiere manejo especial');
            $table->string('un_number', 10)->nullable()->comment('Número UN (mercancías peligrosas)');
            $table->string('imdg_class', 10)->nullable()->comment('Clase IMDG');

            // Status tracking
            $table->enum('status', [
                'draft',           // Borrador
                'issued',          // Emitido
                'loaded',          // Cargado
                'in_transit',      // En tránsito
                'arrived',         // Arribado
                'discharged',      // Descargado
                'delivered',       // Entregado
                'completed',       // Completado
                'cancelled'        // Cancelado
            ])->default('draft')->comment('Estado del conocimiento');

            $table->boolean('customs_cleared')->default(false)->comment('Despacho aduanero');
            $table->boolean('documentation_complete')->default(false)->comment('Documentación completa');
            $table->boolean('ready_for_delivery')->default(false)->comment('Listo para entrega');

            // Financial information
            $table->decimal('freight_amount', 10, 2)->nullable()->comment('Monto flete');
            $table->decimal('insurance_amount', 10, 2)->nullable()->comment('Monto seguro');
            $table->decimal('declared_value', 12, 2)->nullable()->comment('Valor declarado');
            $table->string('currency_code', 3)->default('USD')->comment('Moneda');
            $table->json('additional_charges')->nullable()->comment('Cargos adicionales');

            // Webservice integration
            $table->string('argentina_bill_id', 50)->nullable()->comment('ID en webservice Argentina');
            $table->string('paraguay_bill_id', 50)->nullable()->comment('ID en webservice Paraguay');
            $table->enum('argentina_status', [
                'pending', 'sent', 'approved', 'rejected', 'error'
            ])->nullable()->comment('Estado en Argentina');
            $table->enum('paraguay_status', [
                'pending', 'sent', 'approved', 'rejected', 'error'
            ])->nullable()->comment('Estado en Paraguay');
            $table->datetime('argentina_sent_at')->nullable()->comment('Enviado a Argentina');
            $table->datetime('paraguay_sent_at')->nullable()->comment('Enviado a Paraguay');
            $table->json('webservice_errors')->nullable()->comment('Errores webservice');

            // Delivery and pickup information
            $table->string('delivery_address', 500)->nullable()->comment('Dirección entrega');
            $table->string('pickup_address', 500)->nullable()->comment('Dirección recogida');
            $table->string('delivery_contact_name', 100)->nullable()->comment('Contacto entrega');
            $table->string('delivery_contact_phone', 30)->nullable()->comment('Teléfono contacto');
            $table->json('delivery_instructions')->nullable()->comment('Instrucciones entrega');

            // Documents and attachments
            $table->json('required_documents')->nullable()->comment('Documentos requeridos');
            $table->json('attached_documents')->nullable()->comment('Documentos adjuntos');
            $table->boolean('original_released')->default(false)->comment('Original liberado');
            $table->datetime('original_release_date')->nullable()->comment('Fecha liberación original');

            // Temperature control (for refrigerated cargo)
            $table->decimal('required_temperature_min', 5, 2)->nullable()->comment('Temperatura mínima °C');
            $table->decimal('required_temperature_max', 5, 2)->nullable()->comment('Temperatura máxima °C');
            $table->boolean('temperature_controlled')->default(false)->comment('Control temperatura');
            $table->json('temperature_log')->nullable()->comment('Registro temperaturas');

            // Operational notes
            $table->text('loading_remarks')->nullable()->comment('Observaciones carga');
            $table->text('discharge_remarks')->nullable()->comment('Observaciones descarga');
            $table->text('delivery_remarks')->nullable()->comment('Observaciones entrega');
            $table->text('general_remarks')->nullable()->comment('Observaciones generales');
            $table->json('incident_reports')->nullable()->comment('Reportes incidentes');

            // Quality and condition
            $table->enum('cargo_condition_loading', [
                'good', 'fair', 'poor', 'damaged', 'not_inspected'
            ])->default('good')->comment('Condición en carga');
            $table->enum('cargo_condition_discharge', [
                'good', 'fair', 'poor', 'damaged', 'not_inspected'
            ])->nullable()->comment('Condición en descarga');
            $table->text('condition_remarks')->nullable()->comment('Observaciones condición');

            // Environmental and regulatory
            $table->json('environmental_requirements')->nullable()->comment('Requerimientos ambientales');
            $table->json('regulatory_compliance')->nullable()->comment('Cumplimiento regulatorio');
            $table->boolean('customs_bond_required')->default(false)->comment('Requiere fianza aduanera');
            $table->string('customs_bond_number', 100)->nullable()->comment('Número fianza');

            // Status flags
            $table->boolean('active')->default(true)->comment('Conocimiento activo');
            $table->boolean('archived')->default(false)->comment('Archivado');
            $table->boolean('requires_review')->default(false)->comment('Requiere revisión');
            $table->boolean('has_discrepancies')->default(false)->comment('Tiene discrepancias');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['shipment_id', 'status'], 'idx_bills_shipment_status');
            $table->index(['shipper_id', 'status'], 'idx_bills_shipper_status');
            $table->index(['consignee_id', 'status'], 'idx_bills_consignee_status');
            $table->index(['loading_port_id', 'discharge_port_id'], 'idx_bills_route');
            $table->index(['issue_date', 'loading_date'], 'idx_bills_dates');
            $table->index(['status', 'customs_cleared'], 'idx_bills_status_customs');
            $table->index(['is_consolidated', 'is_master_bill'], 'idx_bills_consolidation');
            $table->index(['argentina_status'], 'idx_bills_argentina');
            $table->index(['paraguay_status'], 'idx_bills_paraguay');
            $table->index(['primary_cargo_type_id'], 'idx_bills_cargo_type');
            $table->index(['requires_review'], 'idx_bills_review');
            $table->index(['has_discrepancies'], 'idx_bills_discrepancies');
            $table->index(['active', 'archived'], 'idx_bills_active');

            // Unique constraints
            $table->unique(['bill_number'], 'uk_bills_number');

            // Foreign key constraints (only to confirmed existing tables)
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('shipper_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('consignee_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('notify_party_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('cargo_owner_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('loading_port_id')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('discharge_port_id')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('transshipment_port_id')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('final_destination_port_id')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('loading_customs_id')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('discharge_customs_id')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('primary_cargo_type_id')->references('id')->on('cargo_types')->onDelete('restrict');
            $table->foreign('primary_packaging_type_id')->references('id')->on('packaging_types')->onDelete('restrict');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills_of_lading');
    }
};