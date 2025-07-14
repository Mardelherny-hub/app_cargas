<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document types table for client identification
     * Support for CUIT, RUC, DNI, Passport, etc. by country
     */
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            // Basic identification
            $table->string('code', 10)->unique(); // CUIT, RUC, DNI, PASS, etc.
            $table->string('name', 100); // Código Único de Identificación Tributaria
            $table->string('short_name', 20); // CUIT

            // Country association
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');

            // Validation configuration
            $table->string('validation_pattern', 100)->nullable(); // Regex pattern
            $table->integer('min_length')->nullable(); // Minimum length
            $table->integer('max_length')->nullable(); // Maximum length
            $table->boolean('has_check_digit')->default(false); // Has verification digit
            $table->string('check_digit_algorithm', 50)->nullable(); // mod11, mod10, etc.

            // Format configuration
            $table->string('display_format', 50)->nullable(); // 99-99999999-9 for CUIT
            $table->string('input_mask', 50)->nullable(); // Input mask for forms
            $table->json('format_examples')->nullable(); // ["20-12345678-9", "27-12345678-4"]

            // Business rules
            $table->boolean('for_individuals')->default(true); // For natural persons
            $table->boolean('for_companies')->default(true); // For legal entities
            $table->boolean('for_tax_purposes')->default(false); // Tax identification
            $table->boolean('for_customs')->default(false); // Required for customs

            // System configuration
            $table->boolean('is_primary')->default(false); // Primary document for country
            $table->boolean('required_for_clients')->default(false); // Required for client registration
            $table->integer('display_order')->default(999);
            $table->boolean('active')->default(true);

            // Webservice integration
            $table->string('webservice_field', 50)->nullable(); // Field name in webservices
            $table->json('webservice_config')->nullable(); // Additional WS configuration

            // Audit trail
            $table->timestamp('created_date')->useCurrent();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['country_id', 'active'], 'idx_document_types_country_active');
            $table->index(['code'], 'idx_document_types_code');
            $table->index(['for_tax_purposes', 'active'], 'idx_document_types_tax');
            $table->index(['required_for_clients', 'active'], 'idx_document_types_required');

            // Unique constraint
            $table->unique(['country_id', 'code'], 'uk_document_types_country_code');

            // Foreign key for audit
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
