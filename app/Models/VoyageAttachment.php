<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo VoyageAttachment
 * 
 * Adjuntos de documentos para envíos XFBL a DNA Paraguay
 * Los archivos se suben en el momento del envío, no al crear el viaje
 * 
 * @property int $id
 * @property int $voyage_id
 * @property int|null $bill_of_lading_id
 * @property string $original_name
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size
 * @property string $mime_type
 * @property string|null $document_type
 * @property string|null $document_number
 * @property string $country
 * @property int $uploaded_by
 */
class VoyageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'voyage_id',
        'bill_of_lading_id',
        'original_name',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'document_type',
        'document_number',
        'country',
        'uploaded_by',
        'sent_to_dna',
        'sent_to_dna_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'sent_to_dna' => 'boolean',
        'sent_to_dna_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ========================================
    // MÉTODOS PARA XML GDSF
    // ========================================

    /**
     * Obtener contenido del archivo en Base64
     * Para incluir en el XML <archivo> de GDSF
     */
    public function getBase64Content(): string
    {
        $fullPath = Storage::path($this->file_path);
        
        if (!file_exists($fullPath)) {
            throw new \Exception("Archivo no encontrado: {$this->original_name}");
        }
        
        $content = file_get_contents($fullPath);
        return base64_encode($content);
    }

    /**
     * Obtener código EDIFACT del tipo de documento
     * Mapea tipos comunes a códigos según manual GDSF
     */
    public function getDocumentTypeCode(): string
    {
        if ($this->document_type) {
            return $this->document_type;
        }
        
        // Mapeo por defecto según nombre del archivo
        $name = strtolower($this->original_name);
        
        if (str_contains($name, 'factura') || str_contains($name, 'invoice')) {
            return '380'; // Factura Comercial
        }
        
        if (str_contains($name, 'packing') || str_contains($name, 'embalaje')) {
            return '271'; // Packing List
        }
        
        return '1'; // Documento genérico
    }

    /**
     * Obtener tamaño formateado legible
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }
}