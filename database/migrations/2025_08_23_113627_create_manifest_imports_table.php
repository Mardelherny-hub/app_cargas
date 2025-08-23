<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * TABLA PARA TRACKING Y REVERSIÓN DE IMPORTACIONES DE MANIFIESTOS
     * 
     * Permite:
     * - Registrar cada importación con detalles completos
     * - Rastrear todos los objetos creados para reversión
     * - Historial completo con estadísticas
     * - Control de reversión granular
     */
    public function up(): void
    {
        Schema::create('manifest_imports', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Referencias principales
            $table->unsignedBigInteger('company_id')->comment('Empresa que realizó la importación');
            $table->unsignedBigInteger('user_id')->comment('Usuario que realizó la importación');
            $table->unsignedBigInteger('voyage_id')->nullable()->comment('Voyage principal creado');

            // Información del archivo
            $table->string('file_name', 255)->comment('Nombre original del archivo');
            $table->string('file_format', 50)->comment('Formato detectado (kline, parana, guaran, etc.)');
            $table->integer('file_size_bytes')->nullable()->comment('Tamaño del archivo en bytes');
            $table->string('file_hash', 64)->nullable()->comment('Hash SHA256 del archivo para detectar duplicados');

            // Estado de la importación
            $table->enum('status', ['processing', 'completed', 'completed_with_warnings', 'failed', 'reverted'])
                  ->default('processing')
                  ->comment('Estado de la importación');

            // Estadísticas de la importación
            $table->json('import_statistics')->nullable()->comment('Estadísticas detalladas en JSON');
            $table->integer('created_voyages')->default(0)->comment('Cantidad de viajes creados');
            $table->integer('created_shipments')->default(0)->comment('Cantidad de envíos creados');
            $table->integer('created_bills')->default(0)->comment('Cantidad de conocimientos creados');
            $table->integer('created_items')->default(0)->comment('Cantidad de items de carga creados');
            $table->integer('created_containers')->default(0)->comment('Cantidad de contenedores creados');
            $table->integer('created_clients')->default(0)->comment('Cantidad de clientes creados');
            $table->integer('created_ports')->default(0)->comment('Cantidad de puertos creados');

            // Manejo de errores y advertencias
            $table->json('warnings')->nullable()->comment('Advertencias generadas durante la importación');
            $table->json('errors')->nullable()->comment('Errores ocurridos durante la importación');
            $table->integer('warnings_count')->default(0)->comment('Cantidad total de advertencias');
            $table->integer('errors_count')->default(0)->comment('Cantidad total de errores');

            // IDs de objetos creados para reversión
            $table->json('created_voyage_ids')->nullable()->comment('IDs de voyages creados');
            $table->json('created_shipment_ids')->nullable()->comment('IDs de shipments creados');
            $table->json('created_bill_ids')->nullable()->comment('IDs de bills of lading creados');
            $table->json('created_item_ids')->nullable()->comment('IDs de shipment items creados');
            $table->json('created_container_ids')->nullable()->comment('IDs de contenedores creados');
            $table->json('created_client_ids')->nullable()->comment('IDs de clientes creados');
            $table->json('created_port_ids')->nullable()->comment('IDs de puertos creados');

            // Control de reversión
            $table->boolean('can_be_reverted')->default(true)->comment('Si puede ser revertida');
            $table->string('revert_blocked_reason', 500)->nullable()->comment('Razón por la cual no puede revertirse');
            $table->timestamp('reverted_at')->nullable()->comment('Cuándo fue revertida');
            $table->unsignedBigInteger('reverted_by_user_id')->nullable()->comment('Usuario que ejecutó la reversión');
            $table->json('revert_details')->nullable()->comment('Detalles de la reversión');

            // Metadatos adicionales
            $table->text('notes')->nullable()->comment('Notas adicionales sobre la importación');
            $table->json('parser_config')->nullable()->comment('Configuración del parser utilizada');
            $table->decimal('processing_time_seconds', 8, 2)->nullable()->comment('Tiempo de procesamiento en segundos');

            // Timestamps
            $table->timestamp('started_at')->nullable()->comment('Cuándo inició la importación');
            $table->timestamp('completed_at')->nullable()->comment('Cuándo completó la importación');
            $table->timestamps();

            // Índices para performance
            $table->index(['company_id', 'status'], 'idx_manifest_imports_company_status');
            $table->index(['user_id', 'created_at'], 'idx_manifest_imports_user_date');
            $table->index(['voyage_id'], 'idx_manifest_imports_voyage');
            $table->index(['file_format', 'status'], 'idx_manifest_imports_format_status');
            $table->index(['file_hash'], 'idx_manifest_imports_file_hash');
            $table->index(['can_be_reverted', 'status'], 'idx_manifest_imports_revertible');
            $table->index(['created_at'], 'idx_manifest_imports_date');

            // Foreign key constraints
            $table->foreign('company_id', 'fk_manifest_imports_company')
                  ->references('id')->on('companies')
                  ->onDelete('cascade');
            
            $table->foreign('user_id', 'fk_manifest_imports_user')
                  ->references('id')->on('users')
                  ->onDelete('restrict');
            
            $table->foreign('voyage_id', 'fk_manifest_imports_voyage')
                  ->references('id')->on('voyages')
                  ->onDelete('set null');
                  
            $table->foreign('reverted_by_user_id', 'fk_manifest_imports_reverted_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_imports');
    }
};