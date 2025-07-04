<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('business_name'); // razón social
            $table->string('commercial_name')->nullable(); // nombre comercial
            $table->string('tax_id', 11)->unique(); // CUIT
            $table->string('country', 2); // AR, PY
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();

            // Digital certificates for webservices
            $table->string('certificate_path')->nullable(); // .p12 file path
            $table->string('certificate_password')->nullable(); // encrypted
            $table->string('certificate_alias')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();

            // Webservice configuration
            $table->json('ws_config')->nullable(); // URLs, specific endpoints
            $table->boolean('ws_active')->default(true);
            $table->string('ws_environment', 10)->default('testing'); // testing, production

            // Status
            $table->boolean('active')->default(true);
            $table->timestamp('created_date')->useCurrent();
            $table->timestamp('last_access')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tax_id', 'country']);
            $table->index(['active', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
