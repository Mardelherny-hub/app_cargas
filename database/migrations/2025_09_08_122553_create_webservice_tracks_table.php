<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SESIÓN 2: SISTEMA TRACKS AFIP
     * Tabla webservice_tracks - TRACKs individuales para vinculación de procesos
     * 
     * PROPÓSITO ESPECÍFICO AFIP:
     * Según manual AFIP, el flujo correcto es:
     * 1. RegistrarTitEnvios → devuelve TRACKs (TRACK001, TRACK002, etc.)
     * 2. RegistrarMicDta → usa los TRACKs del paso 1 en cargasSueltasIdTrack
     * 
     * Esta tabla gestiona esos TRACKs individuales para:
     * - Vincular envíos específicos con su TRACK asignado por AFIP
     * - Permitir envío de MIC/DTA usando TRACKs válidos
     * - Seguimiento del estado de cada envío individual
     * - Auditoría completa del proceso AFIP
     */
    public function up(): void
    {
        Schema::create('webservice_tracks', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys a tablas existentes del sistema
            $table->unsignedBigInteger('webservice_transaction_id')->comment('Transacción que generó este TRACK');
            $table->unsignedBigInteger('shipment_id')->nullable()->comment('Envío asociado (si aplica)');
            $table->unsignedBigInteger('container_id')->nullable()->comment('Contenedor asociado (si aplica)');
            $table->unsignedBigInteger('bill_of_lading_id')->nullable()->comment('Conocimiento asociado (si aplica)');

            // TRACK data from AFIP
            $table->string('track_number', 50)->comment('Número TRACK devuelto por AFIP (ej: TRACK001)');
            $table->string('track_type', 30)->comment('Tipo de TRACK: envio, contenedor_vacio, bulto');
            $table->enum('webservice_method', [
                'RegistrarTitEnvios',
                'RegistrarMicDta',
                'RegistrarConvoy',
                'TitTransContVacioReg'
            ])->comment('Método AFIP que generó el TRACK');

            // Business data
            $table->string('reference_type', 50)->comment('Tipo de referencia (shipment, container, bill)');
            $table->string('reference_number', 100)->comment('Número de referencia del objeto rastreado');
            $table->text('description')->nullable()->comment('Descripción del ítem rastreado');

            // AFIP integration data
            $table->string('afip_title_number', 50)->nullable()->comment('Número título AFIP asociado');
            $table->json('afip_metadata')->nullable()->comment('Metadatos adicionales de AFIP');
            $table->timestamp('generated_at')->comment('Fecha cuando AFIP generó el TRACK');

            // Status tracking
            $table->enum('status', [
                'generated',        // TRACK generado por TitEnvios
                'used_in_micdta',   // Usado en RegistrarMicDta
                'used_in_convoy',   // Usado en RegistrarConvoy
                'completed',        // Proceso completo terminado
                'expired',          // TRACK expirado sin usar
                'error'             // Error en el proceso
            ])->default('generated')->comment('Estado actual del TRACK');

            $table->timestamp('used_at')->nullable()->comment('Fecha cuando se usó el TRACK');
            $table->timestamp('completed_at')->nullable()->comment('Fecha de finalización del proceso');

            // Audit trail
            $table->unsignedBigInteger('created_by_user_id')->comment('Usuario que generó el TRACK');
            $table->string('created_from_ip', 45)->nullable()->comment('IP desde donde se generó');
            
            // Additional tracking
            $table->json('process_chain')->nullable()->comment('Cadena de procesos donde se usó este TRACK');
            $table->text('notes')->nullable()->comment('Notas adicionales del proceso');

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['track_number'], 'idx_track_number');
            $table->index(['webservice_transaction_id', 'status'], 'idx_transaction_status');
            $table->index(['shipment_id', 'track_type'], 'idx_shipment_type');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['webservice_method', 'generated_at'], 'idx_method_generated');

            // Unique constraint
            $table->unique(['track_number', 'webservice_method'], 'uk_track_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webservice_tracks');
    }
};