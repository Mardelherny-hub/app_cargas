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
            $table->string('legal_name'); // razón social
            $table->string('commercial_name')->nullable(); // nombre comercial
            $table->string('tax_id', 11)->unique(); // CUIT
            $table->string('country', 2); // AR, PY
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();

            // NUEVO: Roles de empresa (términos de Roberto)
            $table->json('company_roles')->nullable(); // ["Cargas", "Transbordos", "Desconsolidador"]
            $table->json('roles_config')->nullable(); // configuración específica por rol

            // Certificates - SOPORTE MULTI-PAÍS
            $table->json('certificates')->nullable()->comment('Certificados por país: {argentina: {...}, paraguay: {...}}');
                        
            // Certificado legacy (mantener compatibilidad)
            $table->string('certificate_path')->nullable()->comment('DEPRECATED: Usar certificates JSON');
            $table->text('certificate_password')->nullable()->comment('DEPRECATED: Usar certificates JSON');
            $table->string('certificate_alias')->nullable()->comment('DEPRECATED: Usar certificates JSON');
            $table->timestamp('certificate_expires_at')->nullable()->comment('DEPRECATED: Usar certificates JSON');

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
            // NOTA: No se puede indexar columnas JSON directamente en MySQL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
