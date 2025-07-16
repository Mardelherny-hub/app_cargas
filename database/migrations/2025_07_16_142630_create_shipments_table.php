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
     * Cada embarcación en un convoy es un shipment
     * Un viaje puede tener 1 shipment (barco único) o varios (convoy)
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            // Primary key
            $table->id();

            // References to confirmed system tables
            $table->foreignId('voyage_id')->constrained('voyages')->comment('Viaje al que pertenece');
            $table->foreignId('vessel_id')->constrained('vessels')->comment('Embarcación de este envío');
            $table->foreignId('captain_id')->nullable()->constrained('captains')->comment('Capitán específico');

            // Shipment identification
            $table->string('shipment_number', 50)->comment('Número del envío');
            $table->integer('sequence_in_voyage')->comment('Secuencia en el viaje');

            // Convoy position (for convoy operations)
            $table->enum('vessel_role', [
                'single',           // Embarcación única
                'lead',            // Líder (remolcador/empujador)
                'towed',           // Remolcada
                'pushed',          // Empujada
                'escort'           // Escolta
            ])->comment('Rol en el convoy');

            $table->integer('convoy_position')->nullable()->comment('Posición en convoy (1, 2, 3...)');
            $table->boolean('is_lead_vessel')->default(false)->comment('Es embarcación líder');

            // Cargo capacity for this shipment
            $table->decimal('cargo_capacity_tons', 10, 2)->comment('Capacidad carga en toneladas');
            $table->integer('container_capacity')->default(0)->comment('Capacidad contenedores');
            $table->decimal('cargo_weight_loaded', 10, 2)->default(0)->comment('Peso cargado');
            $table->integer('containers_loaded')->default(0)->comment('Contenedores cargados');
            $table->decimal('utilization_percentage', 5, 2)->default(0)->comment('Porcentaje utilización');

            // Individual shipment status
            $table->enum('status', [
                'planning',         // Planificación
                'loading',          // Cargando
                'loaded',           // Cargado
                'in_transit',       // En tránsito
                'arrived',          // Arribado
                'discharging',      // Descargando
                'completed',        // Completado
                'delayed'           // Demorado
            ])->default('planning')->comment('Estado del envío');

            // Dates specific to this shipment
            $table->datetime('loading_start')->nullable()->comment('Inicio carga');
            $table->datetime('loading_end')->nullable()->comment('Fin carga');
            $table->datetime('departure_time')->nullable()->comment('Hora salida');
            $table->datetime('arrival_time')->nullable()->comment('Hora llegada');
            $table->datetime('discharge_start')->nullable()->comment('Inicio descarga');
            $table->datetime('discharge_end')->nullable()->comment('Fin descarga');

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

            // Safety and compliance
            $table->boolean('safety_approved')->default(false)->comment('Aprobado seguridad');
            $table->boolean('customs_cleared')->default(false)->comment('Despacho aduanero');
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
            $table->boolean('documentation_complete')->default(false)->comment('Documentación completa');

            // Notes and observations
            $table->text('operational_notes')->nullable()->comment('Notas operacionales');
            $table->text('cargo_notes')->nullable()->comment('Notas de carga');
            $table->text('incidents')->nullable()->comment('Incidentes reportados');

            // Status flags
            $table->boolean('active')->default(true)->comment('Envío activo');
            $table->boolean('requires_attention')->default(false)->comment('Requiere atención');
            $table->boolean('has_delays')->default(false)->comment('Tiene demoras');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamps();

            // Performance indexes
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
            $table->foreign('voyage_id')->references('id')->on('voyages')->onDelete('cascade');
            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('restrict');
            $table->foreign('captain_id')->references('id')->on('captains')->onDelete('set null');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};