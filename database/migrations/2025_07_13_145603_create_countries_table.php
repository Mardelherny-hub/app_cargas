<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Countries table for fluvial cargo system AR/PY
     * Support for origin, destination and transit countries
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            // International codes
            $table->string('iso_code', 3)->unique(); // ARG, PRY, BRA, URY, etc.
            $table->string('alpha2_code', 2)->unique(); // AR, PY, BR, UY
            $table->string('numeric_code', 3)->nullable(); // 032, 600, etc.

            // Basic information
            $table->string('name', 100); // Argentina, Paraguay
            $table->string('official_name', 150)->nullable(); // República Argentina
            $table->string('nationality', 50)->nullable(); // argentino, paraguayo

            // Customs system configuration
            $table->string('customs_code', 10)->nullable(); // Code used in webservices
            $table->string('senasa_code', 10)->nullable(); // For SENASA integrations
            $table->string('document_format', 50)->nullable(); // Expected CUIT/RUC format

            // Regional configuration
            $table->string('currency_code', 3)->nullable(); // ARS, PYG, USD
            $table->string('timezone', 50)->default('America/Argentina/Buenos_Aires');
            $table->string('primary_language', 5)->default('es');

            // Operational configuration
            $table->boolean('allows_import')->default(true);
            $table->boolean('allows_export')->default(true);
            $table->boolean('allows_transit')->default(true);
            $table->boolean('requires_visa')->default(false);

            // Status and ordering
            $table->boolean('active')->default(true);
            $table->integer('display_order')->default(999); // For ordering in selects
            $table->boolean('is_primary')->default(false); // AR and PY would be true

            // Audit trail
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['active', 'display_order'], 'idx_countries_active_order');
            $table->index(['iso_code'], 'idx_countries_iso_code');
            $table->index(['is_primary', 'active'], 'idx_countries_primary');

            // Foreign key for audit (if users table exists)
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
