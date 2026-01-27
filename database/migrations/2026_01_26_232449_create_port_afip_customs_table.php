<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivote: Puerto ↔ Aduanas AFIP
     * 
     * Permite que un puerto tenga múltiples aduanas AFIP asociadas
     * Solo se poblarán los puertos que realmente se usan
     */
    public function up(): void
    {
        Schema::create('port_afip_customs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('port_id')
                ->constrained('ports')
                ->onDelete('cascade');
            
            $table->foreignId('afip_customs_office_id')
                ->constrained('afip_customs_offices')
                ->onDelete('cascade');
            
            $table->boolean('is_default')->default(false);
            
            $table->timestamps();
            
            $table->unique(['port_id', 'afip_customs_office_id'], 'port_afip_customs_unique');
            $table->index('port_id', 'port_afip_customs_port');
            $table->index('afip_customs_office_id', 'port_afip_customs_office');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('port_afip_customs');
    }
};