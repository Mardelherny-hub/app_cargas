<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * MÓDULO 3: VIAJES Y CARGAS - attachments
     * Tabla polimórfica para adjuntos del sistema
     * Maneja documentos, imágenes y archivos asociados a cualquier entidad
     * 
     * ENTIDADES SOPORTADAS:
     * - bills_of_lading: facturas, certificados, BL originales
     * - shipment_items: fotos de mercadería, especificaciones técnicas
     * - containers: inspecciones, daños, certificados CSC
     * - shipments: manifiestos, permisos, autorizaciones
     * - clients: documentos corporativos, licencias
     * - voyages: autorizaciones de viaje, manifiestos maestros
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Polymorphic relationship
            $table->unsignedBigInteger('attachable_id')->comment('ID de la entidad asociada');
            $table->string('attachable_type', 100)->comment('Tipo de entidad (Model class)');

            // File information
            $table->string('original_filename', 255)->comment('Nombre original del archivo');
            $table->string('stored_filename', 255)->comment('Nombre almacenado en disco');
            $table->string('file_path', 500)->comment('Ruta completa del archivo');
            $table->string('disk', 50)->default('local')->comment('Disco de almacenamiento (local, s3, etc.)');

            // File metadata
            $table->string('mime_type', 100)->comment('Tipo MIME del archivo');
            $table->string('file_extension', 10)->comment('Extensión del archivo');
            $table->unsignedBigInteger('file_size_bytes')->comment('Tamaño en bytes');
            $table->string('file_hash', 64)->nullable()->comment('Hash SHA256 del archivo');

            // Document classification
            $table->enum('attachment_type', [
                'invoice',              // Factura
                'bill_of_lading',       // Conocimiento de embarque
                'packing_list',         // Lista de empaque
                'certificate',          // Certificado
                'permit',               // Permiso
                'inspection_report',    // Reporte de inspección
                'photo',                // Fotografía
                'customs_document',     // Documento aduanero
                'insurance',            // Seguro
                'manifest',             // Manifiesto
                'authorization',        // Autorización
                'specification',        // Especificación técnica
                'quality_report',       // Reporte de calidad
                'damage_report',        // Reporte de daños
                'other'                 // Otro
            ])->comment('Tipo de adjunto');

            $table->string('document_category', 100)->nullable()->comment('Categoría específica del documento');
            $table->text('description')->nullable()->comment('Descripción del adjunto');
            $table->json('tags')->nullable()->comment('Etiquetas para búsqueda');

            // Document versions and references
            $table->string('document_number', 100)->nullable()->comment('Número del documento');
            $table->string('version', 20)->default('1.0')->comment('Versión del documento');
            $table->unsignedBigInteger('replaces_attachment_id')->nullable()->comment('Reemplaza a este adjunto');
            $table->boolean('is_current_version')->default(true)->comment('Es la versión actual');

            // Visibility and access control
            $table->enum('visibility', [
                'public',       // Público
                'internal',     // Interno empresa
                'restricted',   // Restringido
                'confidential'  // Confidencial
            ])->default('internal')->comment('Nivel de visibilidad');

            $table->boolean('requires_approval')->default(false)->comment('Requiere aprobación');
            $table->boolean('is_approved')->default(false)->comment('Está aprobado');
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->comment('Aprobado por usuario');
            $table->datetime('approved_at')->nullable()->comment('Fecha de aprobación');

            // Document validity
            $table->date('valid_from')->nullable()->comment('Válido desde');
            $table->date('valid_until')->nullable()->comment('Válido hasta');
            $table->boolean('is_expired')->default(false)->comment('Está vencido');
            $table->boolean('notify_expiration')->default(false)->comment('Notificar vencimiento');
            $table->integer('expiration_notice_days')->default(30)->comment('Días de aviso de vencimiento');

            // Digital signature and verification
            $table->boolean('is_digitally_signed')->default(false)->comment('Firmado digitalmente');
            $table->string('signature_hash', 128)->nullable()->comment('Hash de firma digital');
            $table->json('signature_metadata')->nullable()->comment('Metadatos de firma');
            $table->boolean('signature_verified')->default(false)->comment('Firma verificada');

            // OCR and content extraction
            $table->boolean('has_ocr_content')->default(false)->comment('Tiene contenido OCR');
            $table->longText('ocr_content')->nullable()->comment('Contenido extraído por OCR');
            $table->json('extracted_data')->nullable()->comment('Datos extraídos automáticamente');

            // Webservice integration
            $table->boolean('sent_to_webservice')->default(false)->comment('Enviado a webservice');
            $table->string('webservice_reference', 100)->nullable()->comment('Referencia en webservice');
            $table->json('webservice_response')->nullable()->comment('Respuesta del webservice');
            $table->datetime('webservice_sent_at')->nullable()->comment('Fecha envío webservice');

            // Processing status
            $table->enum('processing_status', [
                'pending',      // Pendiente
                'processing',   // Procesando
                'completed',    // Completado
                'failed',       // Falló
                'quarantine'    // En cuarentena
            ])->default('pending')->comment('Estado de procesamiento');

            $table->text('processing_notes')->nullable()->comment('Notas de procesamiento');
            $table->json('processing_metadata')->nullable()->comment('Metadatos de procesamiento');

            // Security and compliance
            $table->boolean('contains_sensitive_data')->default(false)->comment('Contiene datos sensibles');
            $table->boolean('requires_encryption')->default(false)->comment('Requiere encriptación');
            $table->boolean('is_encrypted')->default(false)->comment('Está encriptado');
            $table->string('encryption_method', 50)->nullable()->comment('Método de encriptación');

            // Usage tracking
            $table->integer('download_count')->default(0)->comment('Contador de descargas');
            $table->datetime('last_accessed_at')->nullable()->comment('Último acceso');
            $table->unsignedBigInteger('last_accessed_by_user_id')->nullable()->comment('Último usuario que accedió');

            // Status flags
            $table->boolean('active')->default(true)->comment('Adjunto activo');
            $table->boolean('is_deleted')->default(false)->comment('Marcado como eliminado');
            $table->datetime('deleted_at')->nullable()->comment('Fecha de eliminación');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->comment('Eliminado por usuario');

            // Audit trail
            $table->timestamp('created_date')->useCurrent()->comment('Fecha creación');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('Usuario creador');
            $table->timestamp('last_updated_date')->useCurrent()->useCurrentOnUpdate()->comment('Última actualización');
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable()->comment('Último usuario actualizó');
            $table->timestamps();

            // Performance indexes
            $table->index(['attachable_type', 'attachable_id'], 'idx_attachments_polymorphic');
            $table->index(['attachment_type', 'active'], 'idx_attachments_type_active');
            $table->index(['file_hash'], 'idx_attachments_hash');
            $table->index(['is_current_version', 'active'], 'idx_attachments_current');
            $table->index(['valid_until', 'notify_expiration'], 'idx_attachments_expiration');
            $table->index(['processing_status'], 'idx_attachments_processing');
            $table->index(['requires_approval', 'is_approved'], 'idx_attachments_approval');
            $table->index(['sent_to_webservice'], 'idx_attachments_webservice');
            $table->index(['visibility', 'active'], 'idx_attachments_visibility');
            $table->index(['created_date'], 'idx_attachments_created_date');
            $table->index(['mime_type'], 'idx_attachments_mime_type');

            // Composite indexes for common queries
            $table->index(['attachable_type', 'attachable_id', 'attachment_type'], 'idx_attachments_entity_type');
            $table->index(['attachable_type', 'attachable_id', 'is_current_version'], 'idx_attachments_entity_current');

            // Foreign key constraints
            $table->foreign('replaces_attachment_id')->references('id')->on('attachments')->onDelete('set null');
            // $table->foreign('approved_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_accessed_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('deleted_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('last_updated_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};