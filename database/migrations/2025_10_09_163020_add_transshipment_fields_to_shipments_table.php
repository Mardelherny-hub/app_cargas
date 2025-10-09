<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * AFIP MIC/DTA - Campos de Trasbordo
     * 
     * Agrega campos opcionales para operaciones de trasbordo según especificación AFIP:
     * - idManiCargaArrPaisPart: Identificador del Manifiesto de arribo en el país de partida
     * - idDocTranspArrPaisPart: Identificador del Título de Transporte de arribo en el país de partida
     * 
     * Estos campos se usan SOLO cuando hay trasbordo y permiten vincular 
     * el manifiesto actual con documentos del país de origen.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Identificador del Manifiesto de arribo en país de partida
            // AFIP: idManiCargaArrPaisPart, C(20), Optativo
            // Solo para operaciones de trasbordo
            $table->string('origin_manifest_id', 20)
                ->nullable()
                ->after('status')
                ->comment('ID Manifiesto arribo país partida (trasbordo) - AFIP MIC/DTA');
            
            // Identificador del Título de Transporte de arribo en país de partida
            // AFIP: idDocTranspArrPaisPart, C(39), Optativo
            // Solo para operaciones de trasbordo
            $table->string('origin_transport_doc', 39)
                ->nullable()
                ->after('origin_manifest_id')
                ->comment('ID Título Transporte arribo país partida (trasbordo) - AFIP MIC/DTA');
            
            // Índice para búsquedas por manifiesto de origen
            $table->index('origin_manifest_id', 'idx_shipments_origin_manifest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_origin_manifest');
            $table->dropColumn(['origin_manifest_id', 'origin_transport_doc']);
        });
    }
};