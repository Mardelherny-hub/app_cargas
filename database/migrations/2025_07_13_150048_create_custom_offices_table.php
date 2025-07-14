<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customs offices table for Argentina and Paraguay
     * Support for different types of customs offices and their webservice configurations
     */
    public function up(): void
    {
        Schema::create('customs_offices', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('code', 10)->unique(); // 033, 011, etc. (official codes)
            $table->string('name', 150); // Aduana Buenos Aires, Aduana Asunción
            $table->string('short_name', 50)->nullable(); // BA, ASU

            // Location information
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->string('city', 100); // Buenos Aires, Asunción
            $table->string('province_state', 100)->nullable(); // Buenos Aires, Central
            $table->text('address')->nullable(); // Full address
            $table->string('postal_code', 20)->nullable();

            // Geographic coordinates (for mapping/distance calculations)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Customs office type and capabilities
            $table->enum('office_type', [
                'port', 'airport', 'border', 'inland', 'warehouse', 'free_zone'
            ])->default('port');

            // Operational capabilities
            $table->boolean('handles_maritime')->default(true); // Maritime cargo
            $table->boolean('handles_fluvial')->default(true); // River cargo
            $table->boolean('handles_containers')->default(true); // Container operations
            $table->boolean('handles_bulk_cargo')->default(true); // Bulk cargo
            $table->boolean('handles_passengers')->default(false); // Passenger operations

            // Webservice configuration
            $table->string('webservice_code', 20)->nullable(); // Code used in webservices
            $table->json('webservice_config')->nullable(); // Specific WS configuration
            $table->boolean('supports_anticipada')->default(true); // Información Anticipada
            $table->boolean('supports_micdta')->default(true); // MIC/DTA
            $table->boolean('supports_desconsolidado')->default(false); // Desconsolidados
            $table->boolean('supports_transbordo')->default(false); // Transbordos

            // Operating schedule
            $table->json('operating_hours')->nullable(); // Operating schedule by day
            $table->json('holiday_schedule')->nullable(); // Special holiday hours
            $table->boolean('operates_24h')->default(false); // 24/7 operations

            // Contact information
            $table->string('phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 255)->nullable();

            // Administrative information
            $table->string('supervisor_name', 100)->nullable(); // Customs supervisor
            $table->string('supervisor_contact', 100)->nullable();
            $table->string('region_code', 10)->nullable(); // Administrative region

            // Status and configuration
            $table->boolean('active')->default(true);
            $table->boolean('accepts_new_operations')->default(true); // Accepting new operations
            $table->integer('display_order')->default(999);
            $table->date('established_date')->nullable(); // When office was established

            // Special configurations
            $table->json('special_requirements')->nullable(); // Special documentation requirements
            $table->json('prohibited_goods')->nullable(); // Goods that cannot be handled
            $table->decimal('max_container_capacity', 8, 2)->nullable(); // TEU capacity

            // Audit trail
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['country_id', 'active'], 'idx_customs_offices_country_active');
            $table->index(['office_type', 'active'], 'idx_customs_offices_type');
            $table->index(['code'], 'idx_customs_offices_code');
            $table->index(['city', 'active'], 'idx_customs_offices_city');
            $table->index(['handles_maritime', 'active'], 'idx_customs_offices_maritime');
            $table->index(['accepts_new_operations', 'active'], 'idx_customs_offices_accepting');

            // Foreign key for audit
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customs_offices');
    }
};
