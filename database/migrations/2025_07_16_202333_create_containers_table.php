<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS - containers
     * Tabla de contenedores específicos en el sistema
     * Cada contenedor puede asociarse con múltiples shipment_items
     * 
     * REFERENCIAS CONFIRMADAS DEL SISTEMA:
     * - container_types, ports, clients (obligatorias según contexto)
     * - shipment_items (opcional, relación many-to-many vía tabla pivote)
     * 
     * COMPATIBLE CON WEBSERVICES AR/PY:
     * - <ar:Contenedor> con IdentificadorContenedor, CaracteristicasContenedor
     * - CondicionContenedor, Tara, PesoBruto, FechaVencimiento, etc.
     */
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Container identification
            $table->string('container_number', 15)->unique()->comment('Número de contenedor (11 chars ISO)');
            $table->string('container_check_digit', 1)->nullable()->comment('Dígito verificador');
            $table->string('full_container_number', 20)->nullable()->comment('Número completo con formato');

            // Foreign keys definidos manualmente
            $table->unsignedBigInteger('container_type_id')->comment('Tipo de contenedor');
            $table->unsignedBigInteger('vessel_owner_id')->nullable()->comment('Propietario del contenedor');
            $table->unsignedBigInteger('lessee_client_id')->nullable()->comment('Arrendatario actual');
            $table->unsignedBigInteger('operator_client_id')->nullable()->comment('Operador responsable');
            $table->unsignedBigInteger('current_port_id')->nullable()->comment('Puerto actual');
            $table->unsignedBigInteger('last_port_id')->nullable()->comment('Último puerto');

            // Physical specifications
            $table->decimal('tare_weight_kg', 8, 2)->comment('Peso tara en kilogramos');
            $table->decimal('max_gross_weight_kg', 8, 2)->comment('Peso bruto máximo');
            $table->decimal('current_gross_weight_kg', 8, 2)->nullable()->comment('Peso bruto actual');
            $table->decimal('cargo_weight_kg', 8, 2)->nullable()->comment('Peso de la carga');

            // Container condition
            $table->enum('condition', [
                'V',    // Vacío/Empty
                'D',    // Dañado/Damaged  
                'S',    // Sucio/Dirty
                'P',    // Parcial/Partial
                'L',    // Lleno/Loaded
                'R'     // Reparación/Repair
            ])->default('V')->comment('Condición del contenedor (para webservices)');

            $table->char('container_condition', 1)->default('P')->comment('Condición contenedor AFIP (H=casa a casa, P=muelle a muelle)');
            $table->text('condition_description')->nullable()->comment('Descripción detallada de la condición');
            $table->json('damages')->nullable()->comment('Daños registrados');
            $table->boolean('requires_repair')->default(false)->comment('Requiere reparación');
            $table->boolean('out_of_service')->default(false)->comment('Fuera de servicio');

            // Seals and security
            $table->string('customs_seal', 50)->nullable()->comment('Precinto aduanero');
            $table->string('shipper_seal', 50)->nullable()->comment('Precinto del cargador');
            $table->string('carrier_seal', 50)->nullable()->comment('Precinto del transportista');
            $table->json('additional_seals')->nullable()->comment('Precintos adicionales');

            // Location and movement tracking
            $table->string('current_location', 200)->nullable()->comment('Ubicación actual detallada');
            $table->string('terminal_position', 50)->nullable()->comment('Posición en terminal');

            // Important dates
            $table->date('manufacture_date')->nullable()->comment('Fecha de fabricación');
            $table->date('last_inspection_date')->nullable()->comment('Última inspección');
            $table->date('next_inspection_date')->nullable()->comment('Próxima inspección');
            $table->date('expiry_date')->nullable()->comment('Fecha de vencimiento');
            $table->date('last_cleaning_date')->nullable()->comment('Última limpieza');

            // Movement dates
            $table->datetime('loaded_at')->nullable()->comment('Fecha y hora de carga');
            $table->datetime('discharged_at')->nullable()->comment('Fecha y hora de descarga');
            $table->datetime('gate_in_at')->nullable()->comment('Fecha ingreso a terminal');
            $table->datetime('gate_out_at')->nullable()->comment('Fecha salida de terminal');

            // Operational status
            $table->enum('operational_status', [
                'available',       // Disponible
                'allocated',       // Asignado
                'loading',         // Cargando
                'loaded',          // Cargado
                'in_transit',      // En tránsito
                'discharging',     // Descargando
                'empty',           // Vacío
                'maintenance',     // Mantenimiento
                'inspection',      // Inspección
                'quarantine'       // Cuarentena
            ])->default('available')->comment('Estado operacional');

            // Special handling requirements
            $table->boolean('requires_power')->default(false)->comment('Requiere energía eléctrica');
            $table->boolean('has_gps_tracking')->default(false)->comment('Tiene rastreo GPS');
            $table->boolean('temperature_controlled')->default(false)->comment('Temperatura controlada');
            $table->decimal('set_temperature', 5, 2)->nullable()->comment('Temperatura configurada °C');
            $table->boolean('requires_ventilation')->default(false)->comment('Requiere ventilación');

            // Commercial information
            $table->decimal('daily_storage_rate', 8, 2)->nullable()->comment('Tarifa diaria de almacenaje');
            $table->decimal('demurrage_rate', 8, 2)->nullable()->comment('Tarifa de demora');
            $table->integer('free_days')->default(0)->comment('Días libres de almacenaje');
            $table->string('rate_currency', 3)->default('USD')->comment('Moneda de las tarifas');

            // Webservice integration
            $table->string('webservice_container_id', 50)->nullable()->comment('ID en webservices AR/PY');
            $table->string('argentina_container_code', 20)->nullable()->comment('Código para Argentina');
            $table->string('paraguay_container_code', 20)->nullable()->comment('Código para Paraguay');
            $table->json('webservice_data')->nullable()->comment('Datos adicionales webservices');

            // Documentation
            $table->string('csc_certificate', 100)->nullable()->comment('Certificado CSC');
            $table->date('csc_expiry_date')->nullable()->comment('Vencimiento CSC');
            $table->string('insurance_certificate', 100)->nullable()->comment('Certificado de seguro');
            $table->json('certifications')->nullable()->comment('Certificaciones adicionales');

            // Status flags
            $table->boolean('active')->default(true)->comment('Contenedor activo');
            $table->boolean('blocked')->default(false)->comment('Bloqueado para operaciones');
            $table->text('block_reason')->nullable()->comment('Razón del bloqueo');
            $table->boolean('requires_customs_clearance')->default(false)->comment('Requiere despacho aduanero');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['container_number'], 'idx_containers_number');
            $table->index(['container_type_id', 'condition'], 'idx_containers_type_condition');
            $table->index(['operational_status', 'active'], 'idx_containers_status_active');
            $table->index(['current_port_id', 'condition'], 'idx_containers_port_condition');
            $table->index(['vessel_owner_id'], 'idx_containers_owner');
            $table->index(['lessee_client_id'], 'idx_containers_lessee');
            $table->index(['expiry_date'], 'idx_containers_expiry');
            $table->index(['next_inspection_date'], 'idx_containers_inspection');
            $table->index(['webservice_container_id'], 'idx_containers_webservice');
            $table->index(['blocked', 'active'], 'idx_containers_blocked');
            $table->index(['requires_customs_clearance'], 'idx_containers_customs');
            $table->index(['created_date'], 'idx_containers_created_date');
            $table->index('container_condition', 'idx_containers_afip_condition');

            // Unique constraints
            $table->unique(['container_number'], 'uk_containers_number');

            // Foreign key constraints (only to confirmed existing tables)
            $table->foreign('container_type_id')->references('id')->on('container_types')->onDelete('restrict');
            $table->foreign('vessel_owner_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('lessee_client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('operator_client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('current_port_id')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('last_port_id')->references('id')->on('ports')->onDelete('set null');

            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};