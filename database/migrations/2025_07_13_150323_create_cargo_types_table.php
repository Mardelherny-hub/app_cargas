<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo types table for classifying merchandise
     * Support for different cargo classifications and handling requirements
     */
    public function up(): void
    {
        Schema::create('cargo_types', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('code', 20)->unique(); // CONT, BULK, GENE, DANG, etc.
            $table->string('name', 100); // Containerized, Bulk, General Cargo
            $table->string('short_name', 30)->nullable(); // Container, Bulk, General
            $table->text('description')->nullable(); // Detailed description

            // Classification hierarchy
            $table->unsignedBigInteger('parent_id')->nullable(); // For subcategories
            $table->integer('level')->default(1); // Hierarchy level (1=main, 2=sub, etc.)
            $table->string('full_path', 500)->nullable(); // Full hierarchy path for queries

            // International classifications
            $table->string('imdg_class', 10)->nullable(); // IMDG code for dangerous goods
            $table->string('hs_code_prefix', 10)->nullable(); // Harmonized System prefix
            $table->string('unece_code', 10)->nullable(); // UN/ECE cargo type code

            // Physical characteristics
            $table->enum('cargo_nature', [
                'solid', 'liquid', 'gas', 'mixed', 'other'
            ])->default('solid');

            $table->enum('packaging_type', [
                'containerized', 'bulk', 'break_bulk', 'ro_ro', 'neo_bulk'
            ])->default('containerized');

            // Handling requirements
            $table->boolean('requires_refrigeration')->default(false);
            $table->boolean('requires_special_handling')->default(false);
            $table->boolean('is_dangerous_goods')->default(false);
            $table->boolean('requires_permits')->default(false);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('requires_fumigation')->default(false);

            // Storage and transport requirements
            $table->json('temperature_range')->nullable(); // Min/max temperatures
            $table->json('humidity_requirements')->nullable(); // Humidity specifications
            $table->json('stacking_limitations')->nullable(); // Stacking rules
            $table->boolean('can_be_mixed')->default(true); // Can be mixed with other cargo
            $table->json('incompatible_with')->nullable(); // Array of incompatible cargo types

            // Documentation requirements
            $table->boolean('requires_certificate_origin')->default(false);
            $table->boolean('requires_health_certificate')->default(false);
            $table->boolean('requires_fumigation_certificate')->default(false);
            $table->boolean('requires_insurance')->default(false);
            $table->json('required_documents')->nullable(); // Additional required documents

            // Customs and regulatory
            $table->boolean('subject_to_inspection')->default(false);
            $table->integer('inspection_percentage')->nullable(); // % of shipments inspected
            $table->json('customs_requirements')->nullable(); // Special customs requirements
            $table->json('prohibited_countries')->nullable(); // Countries where prohibited

            // Weight and dimension characteristics
            $table->decimal('typical_density', 8, 3)->nullable(); // kg/m³
            $table->decimal('max_weight_per_container', 8, 2)->nullable(); // Maximum weight
            $table->json('dimension_restrictions')->nullable(); // Size limitations

            // Economic information
            $table->string('tariff_classification', 50)->nullable(); // Customs tariff class
            $table->decimal('insurance_rate', 5, 4)->nullable(); // Typical insurance rate %
            $table->json('freight_rates')->nullable(); // Typical freight rates by route

            // Webservice configuration
            $table->string('webservice_code', 20)->nullable(); // Code for webservices
            $table->json('webservice_mapping')->nullable(); // Mapping to different WS systems

            // Operational configuration
            $table->boolean('allows_consolidation')->default(true); // Can be consolidated
            $table->boolean('allows_deconsolidation')->default(true); // Can be deconsolidated
            $table->boolean('allows_transshipment')->default(true); // Can be transshipped
            $table->integer('typical_loading_time')->nullable(); // Hours for loading/unloading

            // Status and display
            $table->boolean('active')->default(true);
            $table->boolean('is_common')->default(false); // Frequently used types
            $table->integer('display_order')->default(999);
            $table->string('icon', 100)->nullable(); // Icon for UI
            $table->string('color_code', 7)->nullable(); // Color for charts/maps

            // Seasonal and temporal information
            $table->json('seasonal_restrictions')->nullable(); // Seasonal limitations
            $table->json('embargo_periods')->nullable(); // Time periods when restricted

            // Audit trail
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['active', 'is_common'], 'idx_cargo_types_active_common');
            $table->index(['parent_id'], 'idx_cargo_types_parent');
            $table->index(['packaging_type', 'active'], 'idx_cargo_types_packaging');
            $table->index(['is_dangerous_goods', 'active'], 'idx_cargo_types_dangerous');
            $table->index(['requires_special_handling'], 'idx_cargo_types_special');
            $table->index(['code'], 'idx_cargo_types_code');
            $table->index(['level', 'display_order'], 'idx_cargo_types_hierarchy');

            // Foreign key constraints
            $table->foreign('parent_id')->references('id')->on('cargo_types')->onDelete('set null');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo_types');
    }
};
