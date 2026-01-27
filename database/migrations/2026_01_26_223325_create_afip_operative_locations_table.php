<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPRINT: Lugares Operativos AFIP
     * 
     * Fuentes: Aduanas.pdf, LugaresOperativosArgentina.pdf, LugaresOperativosNoArgentina.pdf
     * 
     * Argentina: customs_code (3 chars), location_code (5 chars)
     * Extranjeros: customs_code (6 chars), location_code (7 chars)
     */
    public function up(): void
    {
        Schema::create('afip_operative_locations', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('country_id')
                ->constrained('countries')
                ->onDelete('cascade');
            
            $table->foreignId('port_id')
                ->nullable()
                ->constrained('ports')
                ->onDelete('set null');
            
            $table->string('customs_code', 10);
            $table->string('location_code', 10);
            $table->string('description', 150);
            
            $table->boolean('is_foreign')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->unique(['country_id', 'customs_code', 'location_code'], 'afip_op_loc_unique');
            $table->index(['country_id', 'is_active'], 'afip_op_loc_country_active');
            $table->index(['customs_code'], 'afip_op_loc_customs');
            $table->index(['port_id'], 'afip_op_loc_port');
            $table->index(['is_foreign', 'is_active'], 'afip_op_loc_foreign_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afip_operative_locations');
    }
};