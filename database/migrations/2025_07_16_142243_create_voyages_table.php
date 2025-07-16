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
     * Tabla principal para viajes - agrupa embarcaciones y cargas
     * Un viaje puede contener múltiples envíos (convoy) y múltiples cargas
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - companies, vessels, countries, ports, customs_offices, captains, users
     */
    public function up(): void
    {
        Schema::create('voyages', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic identification
            $table->string('voyage_number', 50)->unique()->comment('Número único del viaje');
            $table->string('internal_reference', 100)->nullable()->comment('Referencia interna empresa');

            // Foreign keys definidos manualmente
            $table->unsignedBigInteger('company_id')->comment('Empresa organizadora');
            $table->unsignedBigInteger('lead_vessel_id')->comment('Embarcación líder/principal');
            $table->unsignedBigInteger('captain_id')->nullable()->comment('Capitán principal del viaje');
            $table->unsignedBigInteger('origin_country_id')->comment('País de origen');
            $table->unsignedBigInteger('origin_port_id')->comment('Puerto de origen');
            $table->unsignedBigInteger('destination_country_id')->comment('País de destino');
            $table->unsignedBigInteger('destination_port_id')->comment('Puerto de destino');
            $table->unsignedBigInteger('transshipment_port_id')->nullable()->comment('Puerto de transbordo');
            $table->unsignedBigInteger('origin_customs_id')->nullable()->comment('Aduana de origen');
            $table->unsignedBigInteger('destination_customs_id')->nullable()->comment('Aduana de destino');
            $table->unsignedBigInteger('transshipment_customs_id')->nullable()->comment('Aduana de transbordo');
            // Voyage dates
            $table->datetime('departure_date')->comment('Fecha/hora salida');
            $table->datetime('estimated_arrival_date')->comment('Fecha/hora llegada estimada');
            $table->datetime('actual_arrival_date')->nullable()->comment('Fecha/hora llegada real');
            $table->datetime('customs_clearance_date')->nullable()->comment('Fecha despacho aduanero');
            $table->datetime('cargo_loading_start')->nullable()->comment('Inicio carga mercadería');
            $table->datetime('cargo_loading_end')->nullable()->comment('Fin carga mercadería');
            $table->datetime('cargo_discharge_start')->nullable()->comment('Inicio descarga mercadería');
            $table->datetime('cargo_discharge_end')->nullable()->comment('Fin descarga mercadería');

            // Voyage type and characteristics
            $table->enum('voyage_type', [
                'single_vessel',    // Embarcación única
                'convoy',           // Convoy (remolcador + barcazas)
                'fleet'             // Flota coordinada
            ])->comment('Tipo de viaje');

            $table->enum('cargo_type', [
                'export',           // Exportación
                'import',           // Importación
                'transit',          // Tránsito
                'transshipment',    // Transbordo
                'cabotage'          // Cabotaje
            ])->comment('Tipo de operación');

            $table->boolean('is_consolidated')->default(false)->comment('Viaje consolidado');
            $table->boolean('has_transshipment')->default(false)->comment('Tiene transbordo');
            $table->boolean('requires_pilot')->default(false)->comment('Requiere piloto');

            // Voyage status
            $table->enum('status', [
                'planning',         // En planificación
                'approved',         // Aprobado
                'in_transit',       // En tránsito
                'at_destination',   // En destino
                'completed',        // Completado
                'cancelled',        // Cancelado
                'delayed'           // Demorado
            ])->default('planning')->comment('Estado del viaje');

            // Cargo summary
            $table->integer('total_containers')->default(0)->comment('Total contenedores');
            $table->decimal('total_cargo_weight', 12, 2)->default(0)->comment('Peso total carga en toneladas');
            $table->decimal('total_cargo_volume', 12, 2)->default(0)->comment('Volumen total en m³');
            $table->integer('total_bills_of_lading')->default(0)->comment('Total conocimientos embarque');
            $table->integer('total_clients')->default(0)->comment('Total clientes involucrados');

            // Webservice integration
            $table->string('argentina_voyage_id', 50)->nullable()->comment('ID viaje en Argentina');
            $table->string('paraguay_voyage_id', 50)->nullable()->comment('ID viaje en Paraguay');
            $table->enum('argentina_status', [
                'pending', 'sent', 'approved', 'rejected', 'error'
            ])->nullable()->comment('Estado en webservice Argentina');
            $table->enum('paraguay_status', [
                'pending', 'sent', 'approved', 'rejected', 'error'
            ])->nullable()->comment('Estado en webservice Paraguay');
            $table->datetime('argentina_sent_at')->nullable()->comment('Enviado a Argentina');
            $table->datetime('paraguay_sent_at')->nullable()->comment('Enviado a Paraguay');

            // Financial information
            $table->decimal('estimated_freight_cost', 10, 2)->nullable()->comment('Costo flete estimado');
            $table->decimal('actual_freight_cost', 10, 2)->nullable()->comment('Costo flete real');
            $table->decimal('fuel_cost', 10, 2)->nullable()->comment('Costo combustible');
            $table->decimal('port_charges', 10, 2)->nullable()->comment('Tasas portuarias');
            $table->decimal('total_voyage_cost', 10, 2)->nullable()->comment('Costo total viaje');
            $table->string('currency_code', 3)->default('USD')->comment('Moneda');

            // Weather and conditions
            $table->json('weather_conditions')->nullable()->comment('Condiciones climáticas');
            $table->json('river_conditions')->nullable()->comment('Condiciones del río');
            $table->text('voyage_notes')->nullable()->comment('Notas del viaje');
            $table->text('delays_explanation')->nullable()->comment('Explicación demoras');

            // Documents and approvals
            $table->json('required_documents')->nullable()->comment('Documentos requeridos');
            $table->json('uploaded_documents')->nullable()->comment('Documentos subidos');
            $table->boolean('customs_approved')->default(false)->comment('Aprobado por aduana');
            $table->boolean('port_authority_approved')->default(false)->comment('Aprobado por autoridad portuaria');
            $table->boolean('all_documents_ready')->default(false)->comment('Todos documentos listos');

            // Emergency and safety
            $table->json('emergency_contacts')->nullable()->comment('Contactos de emergencia');
            $table->json('safety_equipment')->nullable()->comment('Equipos de seguridad');
            $table->boolean('dangerous_cargo')->default(false)->comment('Carga peligrosa a bordo');
            $table->text('safety_notes')->nullable()->comment('Notas de seguridad');

            // Performance tracking
            $table->decimal('distance_nautical_miles', 8, 2)->nullable()->comment('Distancia en millas náuticas');
            $table->decimal('average_speed_knots', 5, 2)->nullable()->comment('Velocidad promedio en nudos');
            $table->integer('transit_time_hours')->nullable()->comment('Tiempo tránsito en horas');
            $table->decimal('fuel_consumption', 8, 2)->nullable()->comment('Consumo combustible');
            $table->decimal('fuel_efficiency', 8, 2)->nullable()->comment('Eficiencia combustible');

            // Communication
            $table->string('communication_frequency', 20)->nullable()->comment('Frecuencia comunicación');
            $table->json('reporting_schedule')->nullable()->comment('Horarios de reporte');
            $table->datetime('last_position_report')->nullable()->comment('Último reporte posición');

            // Status flags
            $table->boolean('active')->default(true)->comment('Viaje activo');
            $table->boolean('archived')->default(false)->comment('Archivado');
            $table->boolean('requires_follow_up')->default(false)->comment('Requiere seguimiento');
            $table->boolean('has_incidents')->default(false)->comment('Tiene incidentes reportados');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha de creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario que actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['company_id', 'status'], 'idx_voyages_company_status');
            $table->index(['status', 'departure_date'], 'idx_voyages_status_departure');
            $table->index(['lead_vessel_id', 'status'], 'idx_voyages_vessel_status');
            $table->index(['origin_port_id', 'destination_port_id'], 'idx_voyages_route');
            $table->index(['departure_date', 'estimated_arrival_date'], 'idx_voyages_dates');
            $table->index(['argentina_status'], 'idx_voyages_argentina');
            $table->index(['paraguay_status'], 'idx_voyages_paraguay');
            $table->index(['voyage_type', 'cargo_type'], 'idx_voyages_types');
            $table->index(['captain_id'], 'idx_voyages_captain');
            $table->index(['customs_approved', 'port_authority_approved'], 'idx_voyages_approvals');
            $table->index(['active', 'archived'], 'idx_voyages_active');
            $table->index(['requires_follow_up'], 'idx_voyages_follow_up');

            // Foreign key constraints con nombres explícitos
            $table->foreign('company_id', 'fk_voyages_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('lead_vessel_id', 'fk_voyages_lead_vessel')->references('id')->on('vessels')->onDelete('restrict');
            $table->foreign('captain_id', 'fk_voyages_captain')->references('id')->on('captains')->onDelete('set null');
            $table->foreign('origin_country_id', 'fk_voyages_origin_country')->references('id')->on('countries')->onDelete('restrict');
            $table->foreign('destination_country_id', 'fk_voyages_destination_country')->references('id')->on('countries')->onDelete('restrict');
            $table->foreign('origin_port_id', 'fk_voyages_origin_port')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('destination_port_id', 'fk_voyages_destination_port')->references('id')->on('ports')->onDelete('restrict');
            $table->foreign('transshipment_port_id', 'fk_voyages_transshipment_port')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('origin_customs_id', 'fk_voyages_origin_customs')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('destination_customs_id', 'fk_voyages_destination_customs')->references('id')->on('customs_offices')->onDelete('set null');
            $table->foreign('transshipment_customs_id', 'fk_voyages_transshipment_customs')->references('id')->on('customs_offices')->onDelete('set null');
// $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voyages');
    }
};