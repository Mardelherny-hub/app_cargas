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
     * Tabla shipments - Envíos individuales dentro de un viaje
     * UNIFICADA - Coherente con modelo y seeder
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('voyage_id')->comment('Viaje al que pertenece');
            $table->unsignedBigInteger('vessel_id')->comment('Embarcación de este envío');
            $table->unsignedBigInteger('captain_id')->nullable()->comment('Capitán de esta embarcación');
            
            // Shipment identification
            $table->string('shipment_number', 50)->comment('Número del envío');
            $table->integer('sequence_in_voyage')->comment('Secuencia en el viaje');

            // Convoy configuration
            $table->enum('vessel_role', ['single', 'lead', 'towed', 'pushed', 'escort'])->comment('Rol en el convoy');
            $table->integer('convoy_position')->nullable()->comment('Posición en convoy (1, 2, 3...)');
            $table->boolean('is_lead_vessel')->default(false)->comment('Es embarcación líder');

            // Cargo capacity and loading
            $table->decimal('cargo_capacity_tons', 10, 2)->comment('Capacidad carga en toneladas');
            $table->integer('container_capacity')->default(0)->comment('Capacidad contenedores');
            $table->decimal('cargo_weight_loaded', 10, 2)->default(0)->comment('Peso cargado');
            $table->integer('containers_loaded')->default(0)->comment('Contenedores cargados');
            $table->decimal('utilization_percentage', 5, 2)->default(0)->comment('Porcentaje utilización');

            // Shipment status
            $table->enum('status', ['planning', 'loading', 'loaded', 'in_transit', 'arrived', 'discharging', 'completed', 'delayed'])->default('planning')->comment('Estado del envío');

            // NOMBRES UNIFICADOS - Tiempos operacionales (formato descriptivo)
            $table->datetime('loading_start_time')->nullable()->comment('Inicio carga');
            $table->datetime('loading_end_time')->nullable()->comment('Fin carga');
            $table->datetime('departure_time')->nullable()->comment('Hora salida');
            $table->datetime('arrival_time')->nullable()->comment('Hora llegada');
            $table->datetime('discharge_start_time')->nullable()->comment('Inicio descarga');
            $table->datetime('discharge_end_time')->nullable()->comment('Fin descarga');

            // Position and tracking
            $table->decimal('current_latitude', 10, 8)->nullable()->comment('Latitud actual');
            $table->decimal('current_longitude', 11, 8)->nullable()->comment('Longitud actual');
            $table->timestamp('position_updated_at')->nullable()->comment('Actualización posición');
            $table->decimal('distance_from_lead', 8, 2)->nullable()->comment('Distancia del líder en metros');

            // Operational data
            $table->decimal('fuel_consumption', 8, 2)->nullable()->comment('Consumo combustible');
            $table->decimal('average_speed', 5, 2)->nullable()->comment('Velocidad promedio');
            $table->json('cargo_manifest')->nullable()->comment('Manifiesto de carga');
            $table->integer('bills_of_lading_count')->default(0)->comment('Cantidad conocimientos');

            // Safety and compliance - UNIFICADO CON SEEDER
            $table->boolean('safety_approved')->default(false)->comment('Aprobado seguridad');
            $table->boolean('customs_cleared')->default(false)->comment('Despacho aduanero');
            $table->boolean('documentation_complete')->default(false)->comment('Documentación completa');
            $table->boolean('cargo_inspected')->default(false)->comment('Carga inspeccionada');
            $table->boolean('has_dangerous_cargo')->default(false)->comment('Carga peligrosa');
            $table->text('safety_notes')->nullable()->comment('Notas seguridad');

            // Communication
            $table->string('radio_frequency', 20)->nullable()->comment('Frecuencia radio');
            $table->datetime('last_communication')->nullable()->comment('Última comunicación');
            $table->json('communication_log')->nullable()->comment('Log comunicaciones');

            // Performance metrics
            $table->decimal('loading_efficiency', 5, 2)->nullable()->comment('Eficiencia carga %');
            $table->integer('loading_time_minutes')->nullable()->comment('Tiempo carga en minutos');
            $table->integer('discharge_time_minutes')->nullable()->comment('Tiempo descarga en minutos');
            $table->boolean('on_schedule')->default(true)->comment('En horario');

            // Financial data
            $table->decimal('freight_cost', 8, 2)->nullable()->comment('Costo flete');
            $table->decimal('fuel_cost', 8, 2)->nullable()->comment('Costo combustible');
            $table->decimal('port_charges', 8, 2)->nullable()->comment('Tasas portuarias');
            $table->decimal('total_cost', 8, 2)->nullable()->comment('Costo total');

            // Documents and attachments
            $table->json('required_documents')->nullable()->comment('Documentos requeridos');
            $table->json('uploaded_documents')->nullable()->comment('Documentos subidos');

            // Notes and observations - UNIFICADO CON MODELO Y SEEDER
            $table->text('operational_notes')->nullable()->comment('Notas operacionales');
            $table->text('cargo_notes')->nullable()->comment('Notas de carga');
            $table->text('incidents')->nullable()->comment('Incidentes reportados');
            $table->text('special_instructions')->nullable()->comment('Instrucciones especiales');
            $table->text('handling_notes')->nullable()->comment('Notas de manejo');
            $table->text('delay_reason')->nullable()->comment('Razón del retraso');
            $table->integer('delay_minutes')->nullable()->comment('Minutos de retraso');

            // Status flags
            $table->boolean('active')->default(true)->comment('Envío activo');
            $table->boolean('requires_attention')->default(false)->comment('Requiere atención');
            $table->boolean('has_delays')->default(false)->comment('Tiene demoras');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamps();

            // Indexes
            $table->index(['voyage_id', 'sequence_in_voyage'], 'idx_shipments_voyage_sequence');
            $table->index(['vessel_id', 'status'], 'idx_shipments_vessel_status');
            $table->index(['status', 'departure_time'], 'idx_shipments_status_departure');
            $table->index(['convoy_position', 'vessel_role'], 'idx_shipments_convoy');
            $table->index(['captain_id'], 'idx_shipments_captain');
            $table->index(['is_lead_vessel'], 'idx_shipments_lead');
            $table->index(['requires_attention'], 'idx_shipments_attention');
            $table->index(['has_delays'], 'idx_shipments_delays');
            $table->index(['safety_approved', 'customs_cleared'], 'idx_shipments_approvals');

            // Unique constraints
            $table->unique(['voyage_id', 'vessel_id'], 'uk_shipments_voyage_vessel');
            $table->unique(['voyage_id', 'sequence_in_voyage'], 'uk_shipments_voyage_sequence');

            // Foreign key constraints
            $table->foreign('voyage_id', 'fk_shipments_voyage')->references('id')->on('voyages')->onDelete('cascade');
            $table->foreign('vessel_id', 'fk_shipments_vessel')->references('id')->on('vessels')->onDelete('restrict');
            $table->foreign('captain_id', 'fk_shipments_captain')->references('id')->on('captains')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};