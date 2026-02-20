<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage_attachments', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('voyage_id')
                ->constrained('voyages')
                ->onDelete('cascade')
                ->comment('Viaje al que pertenece el adjunto');

            $table->foreignId('bill_of_lading_id')
                ->nullable()
                ->constrained('bills_of_lading')
                ->onDelete('cascade')
                ->comment('BL específico (opcional, puede ser general del viaje)');

            // Información del archivo
            $table->string('original_name', 255)->comment('Nombre original del archivo subido');
            $table->string('file_name', 255)->comment('Nombre del archivo en storage');
            $table->string('file_path', 500)->comment('Ruta completa en storage');
            $table->unsignedInteger('file_size')->comment('Tamaño en bytes');
            $table->string('mime_type', 100)->comment('Tipo MIME del archivo');

            // Información del documento (para XML GDSF)
            $table->string('document_type', 10)->nullable()
                ->comment('Código EDIFACT del tipo de documento (ej: 380=Factura)');
            $table->string('document_number', 100)->nullable()
                ->comment('Número del documento (ej: FACTURA-12345)');

            // Control
            $table->string('country', 2)->default('PY')
                ->comment('País del webservice (PY=Paraguay)');

            // Estado envío a DNA
            $table->boolean('sent_to_dna')->default(false)
                ->comment('Si ya fue enviado a DNA Paraguay via enviarDocumento');
            $table->datetime('sent_to_dna_at')->nullable()
                ->comment('Fecha/hora del envío exitoso a DNA');

            // Auditoría
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que subió el archivo');

            $table->timestamps();

            // Índices
            $table->index(['voyage_id', 'country']);
            $table->index('bill_of_lading_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_attachments');
    }
};