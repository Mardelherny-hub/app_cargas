<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS - shipment_items
     * Tabla de ítems de mercadería por shipment
     * 
     * JERARQUÍA CORREGIDA:
     * Voyages → Shipments → Shipment Items → (después) Bills of Lading
     * 
     * COMPATIBLE CON WEBSERVICES AR/PY:
     * - LineaMercaderia (RegistrarTitulosCbc)
     * - Campos: CodigoEmbalaje, TipoEmbalaje, CantidadManifestada, etc.
     */
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            // Primary key
            $table->id();
           
            // CORREGIDO: Reference to bill of lading (jerarquía correcta)
            $table->unsignedBigInteger('bill_of_lading_id')->comment('Conocimiento de embarque al que pertenece');

            // Item identification
            $table->integer('line_number')->comment('Número de línea en el shipment');
            $table->string('item_reference', 100)->nullable()->comment('Referencia del ítem');
            $table->string('lot_number', 50)->nullable()->comment('Número de lote');
            $table->string('serial_number', 100)->nullable()->comment('Número de serie');

            // Cargo classification (confirmed tables)
            $table->unsignedBigInteger('cargo_type_id')->comment('Tipo de carga');
            $table->unsignedBigInteger('packaging_type_id')->comment('Tipo de embalaje');

            // Quantities and measurements
            $table->integer('package_quantity')->comment('Cantidad de bultos');
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en kilogramos');
            $table->decimal('net_weight_kg', 12, 2)->nullable()->comment('Peso neto en kilogramos');
            $table->decimal('volume_m3', 10, 3)->nullable()->comment('Volumen en metros cúbicos');
            $table->decimal('declared_value', 12, 2)->nullable()->comment('Valor declarado');
            $table->string('currency_code', 3)->default('USD')->comment('Moneda del valor declarado');

            // Cargo description
            $table->text('item_description')->comment('Descripción detallada del ítem');
            $table->string('cargo_marks', 500)->nullable()->comment('Marcas de la mercadería');
            $table->string('commodity_code', 20)->nullable()->comment('Código NCM/HS');
            $table->string('tariff_position', 16)->nullable()->comment('Posición arancelaria AFIP (obligatorio, 7-15 chars + puntos)');
            $table->char('is_secure_logistics_operator', 1)->default('N')->comment('Indicador operador logístico seguro AFIP (S/N)');
            $table->char('is_monitored_transit', 1)->default('N')->comment('Indicador tránsito monitoreado AFIP (S/N)');
            $table->char('is_renar', 1)->default('N')->comment('Indicador RENAR AFIP (S/N)');
            $table->string('foreign_forwarder_name', 70)->nullable()->comment('Razón social del forwarder del exterior (obligatorio AFIP)');
            $table->string('foreign_forwarder_tax_id', 35)->nullable()->comment('Número identificador tributario forwarder exterior (optativo)');
            $table->string('foreign_forwarder_country', 3)->nullable()->comment('País emisor identificador tributario forwarder (código 3 chars)');

            $table->string('commodity_description', 255)->nullable()->comment('Descripción del commodity');

            // Commercial information
            $table->string('brand', 100)->nullable()->comment('Marca comercial');
            $table->string('model', 100)->nullable()->comment('Modelo');
            $table->string('manufacturer', 200)->nullable()->comment('Fabricante');
            $table->string('country_of_origin', 3)->nullable()->comment('País de origen (ISO)');

            // Package details
            $table->string('package_type_description', 100)->nullable()->comment('Descripción específica del embalaje');
            $table->json('package_dimensions')->nullable()->comment('Dimensiones del embalaje (largo, ancho, alto)');
            $table->integer('units_per_package')->nullable()->comment('Unidades por bulto');
            $table->string('unit_of_measure', 10)->default('PCS')->comment('Unidad de medida (PCS, KG, LT, etc.)');

            // Special characteristics
            $table->boolean('is_dangerous_goods')->default(false)->comment('Mercancía peligrosa');
            $table->string('un_number', 10)->nullable()->comment('Número UN');
            $table->string('imdg_class', 10)->nullable()->comment('Clase IMDG');
            $table->boolean('is_perishable')->default(false)->comment('Perecedero');
            $table->boolean('is_fragile')->default(false)->comment('Frágil');
            $table->boolean('requires_refrigeration')->default(false)->comment('Requiere refrigeración');
            $table->decimal('temperature_min', 5, 2)->nullable()->comment('Temperatura mínima °C');
            $table->decimal('temperature_max', 5, 2)->nullable()->comment('Temperatura máxima °C');

            // Regulatory and documentation
            $table->boolean('requires_permit')->default(false)->comment('Requiere permiso especial');
            $table->string('permit_number', 100)->nullable()->comment('Número de permiso');
            $table->boolean('requires_inspection')->default(false)->comment('Requiere inspección');
            $table->string('inspection_type', 50)->nullable()->comment('Tipo de inspección');

            // Webservice integration fields
            $table->string('webservice_item_id', 50)->nullable()->comment('ID en webservices AR/PY');
            $table->string('packaging_code', 10)->nullable()->comment('Código embalaje para webservices');
            $table->json('webservice_data')->nullable()->comment('Datos adicionales de webservices');

            // Status and validation
            $table->enum('status', [
                'draft',           // Borrador
                'validated',       // Validado
                'submitted',       // Enviado a aduana
                'accepted',        // Aceptado por aduana
                'rejected',        // Rechazado
                'modified'         // Modificado
            ])->default('draft')->comment('Estado del ítem');

            $table->boolean('has_discrepancies')->default(false)->comment('Tiene discrepancias');
            $table->text('discrepancy_notes')->nullable()->comment('Notas de discrepancias');
            $table->boolean('requires_review')->default(false)->comment('Requiere revisión');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['bill_of_lading_id', 'line_number'], 'idx_shipment_items_bill_of_lading_line');
            $table->index(['cargo_type_id', 'status'], 'idx_shipment_items_cargo_status');
            $table->index(['packaging_type_id'], 'idx_shipment_items_packaging');
            $table->index(['status', 'requires_review'], 'idx_shipment_items_status_review');
            $table->index(['is_dangerous_goods'], 'idx_shipment_items_dangerous');
            $table->index(['commodity_code'], 'idx_shipment_items_commodity');
            $table->index(['webservice_item_id'], 'idx_shipment_items_webservice');
            $table->index(['has_discrepancies'], 'idx_shipment_items_discrepancies');
            $table->index(['created_date'], 'idx_shipment_items_created_date');
            $table->index('tariff_position', 'idx_shipment_items_tariff_position');
            $table->index(['is_secure_logistics_operator', 'is_monitored_transit', 'is_renar'], 'idx_shipment_items_afip_indicators');

            // Unique constraints
            $table->unique(['bill_of_lading_id', 'line_number'], 'uk_shipment_items_bill_of_lading_line');

            // CORREGIDO: Foreign key constraints a shipments (jerarquía correcta)
            $table->foreign('bill_of_lading_id', 'fk_shipment_items_bill_of_lading')->references('id')->on('bills_of_lading')->onDelete('cascade');            $table->foreign('cargo_type_id', 'fk_shipment_items_cargo_type')->references('id')->on('cargo_types')->onDelete('restrict');
            $table->foreign('packaging_type_id', 'fk_shipment_items_packaging_type')->references('id')->on('packaging_types')->onDelete('restrict');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};