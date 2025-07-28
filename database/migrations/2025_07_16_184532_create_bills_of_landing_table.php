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

            // Foreign keys principales
            $table->unsignedBigInteger('shipment_id')->comment('Envío al que pertenece');
            $table->unsignedBigInteger('shipper_id')->comment('Cargador/Exportador');
            $table->unsignedBigInteger('consignee_id')->comment('Consignatario/Importador');
            $table->unsignedBigInteger('notify_party_id')->nullable()->comment('Parte a notificar');
            $table->unsignedBigInteger('cargo_owner_id')->nullable()->comment('Propietario de la carga');
            
            // Puertos y aduanas
            $table->unsignedBigInteger('loading_port_id')->comment('Puerto de carga');
            $table->unsignedBigInteger('discharge_port_id')->comment('Puerto de descarga');
            $table->unsignedBigInteger('transshipment_port_id')->nullable()->comment('Puerto transbordo');
            $table->unsignedBigInteger('final_destination_port_id')->nullable()->comment('Destino final');
            $table->unsignedBigInteger('loading_customs_id')->nullable()->comment('Aduana de carga');
            $table->unsignedBigInteger('discharge_customs_id')->nullable()->comment('Aduana de descarga');
            
            // Tipos de carga y embalaje
            $table->unsignedBigInteger('primary_cargo_type_id')->comment('Tipo principal de carga');
            $table->unsignedBigInteger('primary_packaging_type_id')->comment('Tipo principal embalaje');
                    
            // Identificación del conocimiento
            $table->string('bill_number', 50)->unique()->comment('Número conocimiento embarque');
            $table->string('master_bill_number', 50)->nullable()->comment('Conocimiento madre (consolidados)');
            $table->string('house_bill_number', 50)->nullable()->comment('Conocimiento hijo');
            $table->string('internal_reference', 100)->nullable()->comment('Referencia interna empresa');
            $table->datetime('bill_date')->comment('Fecha del conocimiento');
            $table->string('manifest_number', 50)->nullable()->comment('Número de manifiesto');
            $table->integer('manifest_line_number')->nullable()->comment('Línea en manifiesto');

            // Fechas operacionales
            $table->datetime('loading_date')->comment('Fecha de carga');
            $table->datetime('discharge_date')->nullable()->comment('Fecha de descarga');
            $table->datetime('arrival_date')->nullable()->comment('Fecha de arribo');
            $table->datetime('delivery_date')->nullable()->comment('Fecha de entrega');
            $table->datetime('cargo_ready_date')->nullable()->comment('Fecha mercadería lista');
            $table->datetime('free_time_expires_at')->nullable()->comment('Vencimiento tiempo libre');

            // Términos comerciales
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

            $table->string('incoterms', 10)->nullable()->comment('Términos Incoterms');
            $table->string('currency_code', 3)->default('USD')->comment('Moneda');

            // Medidas y pesos
            $table->integer('total_packages')->default(0)->comment('Total bultos');
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en kilogramos');
            $table->decimal('net_weight_kg', 12, 2)->nullable()->comment('Peso neto en kilogramos');
            $table->decimal('volume_m3', 10, 3)->nullable()->comment('Volumen en metros cúbicos');
            $table->string('measurement_unit', 10)->default('KG')->comment('Unidad de medida');
            $table->integer('container_count')->default(0)->comment('Cantidad contenedores');

            // Descripción de carga
            $table->text('cargo_description')->comment('Descripción de la mercadería');
            $table->text('cargo_marks')->nullable()->comment('Marcas de la mercadería');
            $table->string('commodity_code', 20)->nullable()->comment('Código commodity/NCM');

            // Tipo y características del conocimiento
            $table->enum('bill_type', [
                'original',         // Original
                'copy',            // Copia
                'duplicate',       // Duplicado
                'amendment'        // Enmienda
            ])->default('original')->comment('Tipo de conocimiento');

            // Estados y control
            $table->enum('status', [
                'draft',           // Borrador
                'pending_review',  // Pendiente Revisión
                'verified',        // Verificado
                'sent_to_customs', // Enviado a Aduana
                'accepted',        // Aceptado
                'rejected',        // Rechazado
                'completed',       // Completado
                'cancelled'        // Cancelado
            ])->default('draft')->comment('Estado del conocimiento');

            $table->enum('priority_level', [
                'low', 'normal', 'high', 'urgent'
            ])->default('normal')->comment('Nivel de prioridad');

            // Características especiales de carga
            $table->boolean('requires_inspection')->default(false)->comment('Requiere inspección');
            $table->boolean('contains_dangerous_goods')->default(false)->comment('Contiene mercancías peligrosas');
            $table->boolean('requires_refrigeration')->default(false)->comment('Requiere refrigeración');
            $table->boolean('is_transhipment')->default(false)->comment('Es transbordo');
            $table->boolean('is_partial_shipment')->default(false)->comment('Es envío parcial');
            $table->boolean('allows_partial_delivery')->default(true)->comment('Permite entrega parcial');
            $table->boolean('requires_documents_on_arrival')->default(false)->comment('Requiere documentos al arribo');

            // Consolidación
            $table->boolean('is_consolidated')->default(false)->comment('Es consolidado');
            $table->boolean('is_master_bill')->default(false)->comment('Es conocimiento madre');
            $table->boolean('is_house_bill')->default(false)->comment('Es conocimiento hijo');
            $table->boolean('requires_surrender')->default(false)->comment('Requiere entrega');

            // Mercancías peligrosas
            $table->string('un_number', 10)->nullable()->comment('Número UN (mercancías peligrosas)');
            $table->string('imdg_class', 10)->nullable()->comment('Clase IMDG');

            // Información financiera
            $table->decimal('freight_amount', 10, 2)->nullable()->comment('Monto flete');
            $table->decimal('insurance_amount', 10, 2)->nullable()->comment('Monto seguro');
            $table->decimal('declared_value', 12, 2)->nullable()->comment('Valor declarado');
            $table->json('additional_charges')->nullable()->comment('Cargos adicionales');

            // Instrucciones y observaciones
            $table->json('special_instructions')->nullable()->comment('Instrucciones especiales');
            $table->text('handling_instructions')->nullable()->comment('Instrucciones manejo');
            $table->text('customs_remarks')->nullable()->comment('Observaciones aduaneras');
            $table->text('internal_notes')->nullable()->comment('Notas internas');
            $table->text('loading_remarks')->nullable()->comment('Observaciones carga');
            $table->text('discharge_remarks')->nullable()->comment('Observaciones descarga');
            $table->text('delivery_remarks')->nullable()->comment('Observaciones entrega');

            // Control de calidad y condición
            $table->enum('cargo_condition_loading', [
                'good', 'fair', 'poor', 'damaged', 'not_inspected'
            ])->default('good')->comment('Condición en carga');
            $table->enum('cargo_condition_discharge', [
                'good', 'fair', 'poor', 'damaged', 'not_inspected'
            ])->nullable()->comment('Condición en descarga');
            $table->text('condition_remarks')->nullable()->comment('Observaciones condición');

            // Verificación y discrepancias
            $table->datetime('verified_at')->nullable()->comment('Fecha verificación');
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->comment('Usuario verificador');
            $table->boolean('has_discrepancies')->default(false)->comment('Tiene discrepancias');
            $table->json('discrepancy_details')->nullable()->comment('Detalles discrepancias');

            // Webservices integración
            $table->enum('webservice_status', [
                'pending', 'sent', 'accepted', 'rejected', 'error'
            ])->nullable()->comment('Estado webservice');
            $table->string('webservice_reference', 100)->nullable()->comment('Referencia webservice');
            $table->datetime('webservice_sent_at')->nullable()->comment('Enviado webservice');
            $table->datetime('webservice_response_at')->nullable()->comment('Respuesta webservice');
            $table->text('webservice_error_message')->nullable()->comment('Error webservice');

            // Específicos para Argentina y Paraguay
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

            // Entrega y recogida
            $table->string('delivery_address', 500)->nullable()->comment('Dirección entrega');
            $table->string('pickup_address', 500)->nullable()->comment('Dirección recogida');
            $table->string('delivery_contact_name', 100)->nullable()->comment('Contacto entrega');
            $table->string('delivery_contact_phone', 30)->nullable()->comment('Teléfono contacto');
            $table->json('delivery_instructions')->nullable()->comment('Instrucciones entrega');

            // Documentos
            $table->json('required_documents')->nullable()->comment('Documentos requeridos');
            $table->json('attached_documents')->nullable()->comment('Documentos adjuntos');
            $table->boolean('original_released')->default(false)->comment('Original liberado');
            $table->datetime('original_release_date')->nullable()->comment('Fecha liberación original');
            $table->boolean('documentation_complete')->default(false)->comment('Documentación completa');
            $table->boolean('ready_for_delivery')->default(false)->comment('Listo para entrega');

            // Control aduanero
            $table->boolean('customs_cleared')->default(false)->comment('Despacho aduanero');
            $table->boolean('customs_bond_required')->default(false)->comment('Requiere fianza aduanera');
            $table->string('customs_bond_number', 100)->nullable()->comment('Número fianza');

            // Auditoría
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();
            $table->softDeletes();

            // Índices de rendimiento
            $table->index(['shipment_id', 'status'], 'idx_bills_shipment_status');
            $table->index(['shipper_id', 'status'], 'idx_bills_shipper_status');
            $table->index(['consignee_id', 'status'], 'idx_bills_consignee_status');
            $table->index(['loading_port_id', 'discharge_port_id'], 'idx_bills_route');
            $table->index(['bill_date', 'loading_date'], 'idx_bills_dates');
            $table->index(['status', 'customs_cleared'], 'idx_bills_status_customs');
            $table->index(['is_consolidated', 'is_master_bill'], 'idx_bills_consolidation');
            $table->index(['argentina_status'], 'idx_bills_argentina');
            $table->index(['paraguay_status'], 'idx_bills_paraguay');
            $table->index(['primary_cargo_type_id'], 'idx_bills_cargo_type');
            $table->index(['has_discrepancies'], 'idx_bills_discrepancies');
            $table->index(['webservice_status'], 'idx_bills_webservice');
            $table->index(['verified_at'], 'idx_bills_verified');

            // Constraint único
            $table->unique(['bill_number'], 'uk_bills_number');

            // Foreign key constraints
            $table->foreign('shipment_id', 'fk_bills_shipment')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('shipper_id', 'fk_bills_shipper')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('consignee_id', 'fk_bills_consignee')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('notify_party_id', 'fk_bills_notify_party')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('cargo_owner_id', 'fk_bills_cargo_owner')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('loading_port_id', 'fk_bills_loading_port')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('discharge_port_id', 'fk_bills_discharge_port')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('transshipment_port_id', 'fk_bills_transshipment_port')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('final_destination_port_id', 'fk_bills_final_destination_port')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('loading_customs_id', 'fk_bills_loading_customs')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('discharge_customs_id', 'fk_bills_discharge_customs')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('primary_cargo_type_id', 'fk_bills_cargo_type')->references('id')->on('cargo_types')->onDelete('restrict');
            $table->foreign('primary_packaging_type_id', 'fk_bills_packaging_type')->references('id')->on('packaging_types')->onDelete('restrict');
            $table->foreign('verified_by_user_id', 'fk_bills_verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by_user_id', 'fk_bills_created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_updated_by_user_id', 'fk_bills_updated_by')->references('id')->on('users')->onDelete('set null');
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