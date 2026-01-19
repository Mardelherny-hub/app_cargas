<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar códigos de aduana específicos por país a puertos
     * 
     * afip_code: Código de aduana AFIP Argentina (ej: 033 Buenos Aires, 001 La Plata)
     * dna_code: Código de aduana DNA Paraguay (ej: 001 Capital, 019 Terport)
     */
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->string('afip_code', 10)->nullable()->after('webservice_code')->comment('Código aduana AFIP Argentina');
            $table->string('dna_code', 10)->nullable()->after('afip_code')->comment('Código aduana DNA Paraguay');
            
            // Índices para búsqueda rápida
            $table->index('afip_code', 'idx_ports_afip_code');
            $table->index('dna_code', 'idx_ports_dna_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropIndex('idx_ports_afip_code');
            $table->dropIndex('idx_ports_dna_code');
            $table->dropColumn(['afip_code', 'dna_code']);
        });
    }
};