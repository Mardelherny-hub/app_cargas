<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ports table for fluvial and maritime operations
     * Support for Argentina and Paraguay river ports
     */
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('code', 10)->unique(); // USAHU, ARBUE, PYASU, etc. (UN/LOCODE)
            $table->string('name', 150); // Puerto de Buenos Aires, Puerto de Asunción
            $table->string('short_name', 50)->nullable(); // Buenos Aires, Asunción
            $table->string('local_name', 150)->nullable(); // Local language name if different

            // Location information
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->string('city', 100); // Buenos Aires, Asunción, Rosario
            $table->string('province_state', 100)->nullable(); // Buenos Aires, Central, Santa Fe
            $table->text('address')->nullable(); // Full port address
            $table->string('postal_code', 20)->nullable();

            // Geographic coordinates (essential for navigation)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('water_depth', 5, 2)->nullable(); // Water depth in meters

            // Port classification
            $table->enum('port_type', [
                'river', 'maritime', 'lake', 'canal', 'mixed'
            ])->default('river');

            $table->enum('port_category', [
                'major', 'minor', 'terminal', 'anchorage', 'private'
            ])->default('major');

            // Operational capabilities
            $table->boolean('handles_containers')->default(true);
            $table->boolean('handles_bulk_cargo')->default(true);
            $table->boolean('handles_general_cargo')->default(true);
            $table->boolean('handles_passengers')->default(false);
            $table->boolean('handles_dangerous_goods')->default(false);
            $table->boolean('has_customs_office')->default(true);

            // Infrastructure information
            $table->integer('max_vessel_length')->nullable(); // Maximum vessel length in meters
            $table->decimal('max_draft', 5, 2)->nullable(); // Maximum draft in meters
            $table->integer('berths_count')->nullable(); // Number of berths
            $table->decimal('storage_area', 10, 2)->nullable(); // Storage area in hectares
            $table->boolean('has_crane')->default(false);
            $table->boolean('has_warehouse')->default(false);

            // Customs and administrative
            $table->foreignId('primary_customs_office_id')->nullable()
                  ->constrained('customs_offices')->onDelete('set null');
            $table->string('port_authority', 100)->nullable(); // Port authority name
            $table->string('timezone', 50)->nullable(); // Port timezone

            // Webservice configuration
            $table->string('webservice_code', 20)->nullable(); // Code used in webservices
            $table->json('webservice_config')->nullable(); // Specific WS configuration
            $table->boolean('supports_anticipada')->default(true);
            $table->boolean('supports_micdta')->default(true);
            $table->boolean('supports_manifest')->default(true);

            // Operating information
            $table->json('operating_hours')->nullable(); // Operating schedule
            $table->boolean('operates_24h')->default(false);
            $table->json('tide_information')->nullable(); // Tide schedules if applicable
            $table->json('navigation_restrictions')->nullable(); // Navigation limitations

            // Services and facilities
            $table->boolean('has_pilot_service')->default(false);
            $table->boolean('has_tugboat_service')->default(false);
            $table->boolean('has_fuel_service')->default(false);
            $table->boolean('has_fresh_water')->default(false);
            $table->boolean('has_waste_disposal')->default(false);
            $table->json('available_services')->nullable(); // Additional services

            // Contact information
            $table->string('phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('vhf_channel', 10)->nullable(); // Radio communication

            // Economic information
            $table->json('tariff_structure')->nullable(); // Port tariffs
            $table->string('currency_code', 3)->nullable(); // Billing currency

            // Status and configuration
            $table->boolean('active')->default(true);
            $table->boolean('accepts_new_vessels')->default(true);
            $table->integer('display_order')->default(999);
            $table->date('established_date')->nullable();

            // Special configurations
            $table->json('restrictions')->nullable(); // Operational restrictions
            $table->json('required_documents')->nullable(); // Required documentation
            $table->text('special_notes')->nullable(); // Additional notes

            // Audit trail
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['country_id', 'active'], 'idx_ports_country_active');
            $table->index(['port_type', 'active'], 'idx_ports_type');
            $table->index(['code'], 'idx_ports_code');
            $table->index(['city', 'active'], 'idx_ports_city');
            $table->index(['has_customs_office', 'active'], 'idx_ports_customs');
            $table->index(['accepts_new_vessels', 'active'], 'idx_ports_accepting');
            $table->index(['primary_customs_office_id'], 'idx_ports_customs_office');

            // Foreign key for audit
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
