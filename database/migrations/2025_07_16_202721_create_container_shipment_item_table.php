<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS - container_shipment_item
     * Tabla pivote para relación many-to-many entre containers y shipment_items
     * Permite que un ítem de mercadería se divida entre múltiples contenedores
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - containers, shipment_items (recién creadas en el sistema)
     * 
     * CASOS DE USO:
     * - Un lote de 1000 cajas dividido entre 3 contenedores
     * - Mercadería que por volumen necesita múltiples contenedores
     * - Control granular de qué porción está en cada contenedor
     */
    public function up(): void
    {
        Schema::create('container_shipment_item', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys definidos manualmente
            $table->unsignedBigInteger('container_id')->comment('Contenedor');
            $table->unsignedBigInteger('shipment_item_id')->comment('Ítem de mercadería');
            // Quantity and measurement distribution
            $table->integer('package_quantity')->comment('Cantidad de bultos en este contenedor');
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en este contenedor');
            $table->decimal('net_weight_kg', 12, 2)->nullable()->comment('Peso neto en este contenedor');
            $table->decimal('volume_m3', 10, 3)->nullable()->comment('Volumen en este contenedor');

            // Percentage distribution (for validation)
            $table->decimal('quantity_percentage', 5, 2)->nullable()->comment('Porcentaje de la cantidad total');
            $table->decimal('weight_percentage', 5, 2)->nullable()->comment('Porcentaje del peso total');
            $table->decimal('volume_percentage', 5, 2)->nullable()->comment('Porcentaje del volumen total');

            // Loading details
            $table->datetime('loaded_at')->nullable()->comment('Fecha y hora de carga en contenedor');
            $table->datetime('sealed_at')->nullable()->comment('Fecha y hora de precintado');
            $table->string('loading_sequence', 10)->nullable()->comment('Secuencia de carga (A1, B2, etc.)');
            $table->text('stowage_position')->nullable()->comment('Posición de estiba en contenedor');

            // Special handling for this container
            $table->boolean('requires_special_position')->default(false)->comment('Requiere posición especial');
            $table->text('handling_instructions')->nullable()->comment('Instrucciones de manejo específicas');
            $table->boolean('is_hazardous_in_container')->default(false)->comment('Es peligroso en este contenedor');
            $table->string('segregation_code', 20)->nullable()->comment('Código de segregación');

            // Temperature control (for refrigerated containers)
            $table->decimal('required_temperature', 5, 2)->nullable()->comment('Temperatura requerida °C');
            $table->decimal('humidity_level', 5, 2)->nullable()->comment('Nivel de humedad requerido %');
            $table->boolean('requires_ventilation')->default(false)->comment('Requiere ventilación');

            // Documentation and compliance
            $table->string('packing_list_reference', 100)->nullable()->comment('Referencia en lista de empaque');
            $table->text('customs_description')->nullable()->comment('Descripción para aduanas');
            $table->boolean('requires_inspection')->default(false)->comment('Requiere inspección específica');

            // Status tracking
            $table->enum('status', [
                'planned',         // Planificado
                'loading',         // Cargando
                'loaded',          // Cargado
                'sealed',          // Precintado
                'in_transit',      // En tránsito
                'discharged',      // Descargado
                'delivered'        // Entregado
            ])->default('planned')->comment('Estado de este ítem en este contenedor');

            $table->boolean('has_discrepancies')->default(false)->comment('Tiene discrepancias');
            $table->text('discrepancy_notes')->nullable()->comment('Notas de discrepancias');

            // Webservice integration
            $table->json('webservice_data')->nullable()->comment('Datos para webservices AR/PY');
            $table->string('container_position_code', 20)->nullable()->comment('Código posición para webservices');

            // Quality control
            $table->boolean('quality_approved')->default(false)->comment('Aprobado por control de calidad');
            $table->string('quality_inspector', 100)->nullable()->comment('Inspector de calidad');
            $table->datetime('quality_check_date')->nullable()->comment('Fecha control de calidad');
            $table->text('quality_notes')->nullable()->comment('Notas de control de calidad');

            // Damage and condition
            $table->boolean('damaged_during_loading')->default(false)->comment('Dañado durante carga');
            $table->text('damage_description')->nullable()->comment('Descripción de daños');
            $table->decimal('damage_percentage', 5, 2)->nullable()->comment('Porcentaje de daño');

            // Commercial information
            $table->decimal('allocated_value', 12, 2)->nullable()->comment('Valor asignado a este contenedor');
            $table->string('value_currency', 3)->default('USD')->comment('Moneda del valor');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['container_id', 'status'], 'idx_container_items_container_status');
            $table->index(['shipment_item_id', 'status'], 'idx_container_items_item_status');
            $table->index(['status', 'has_discrepancies'], 'idx_container_items_status_discrepancies');
            $table->index(['loaded_at'], 'idx_container_items_loaded_at');
            $table->index(['quality_approved'], 'idx_container_items_quality');
            $table->index(['requires_inspection'], 'idx_container_items_inspection');
            $table->index(['damaged_during_loading'], 'idx_container_items_damaged');
            $table->index(['created_date'], 'idx_container_items_created_date');

            // Unique constraint - Un ítem solo puede estar una vez en un contenedor específico
            $table->unique(['container_id', 'shipment_item_id'], 'uk_container_shipment_item');

            // Foreign key constraints (to confirmed existing tables)
            $table->foreign('container_id')->references('id')->on('containers')->onDelete('cascade');
            $table->foreign('shipment_item_id')->references('id')->on('shipment_items')->onDelete('cascade');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_shipment_item');
    }
};