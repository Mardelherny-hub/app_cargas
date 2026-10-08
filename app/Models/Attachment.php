<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachable_id',
        'attachable_type',
        'original_filename',
        'stored_filename',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size_bytes',
        'file_hash',
        'attachment_type',
        'document_category',
        'description',
        'tags',
        'document_number',
        'version',
        'replaces_attachment_id',
        'is_current_version',
        'visibility',
        'requires_approval',
        'is_approved',
        'approved_by_user_id',
        'approved_at',
        'valid_from',
        'valid_until',
        'is_expired',
        'notify_expiration',
        'expiration_notice_days',
        'is_digitally_signed',
        'signature_hash',
        'signature_metadata',
        'signature_verified',
        'has_ocr_content',
        'ocr_content',
        'extracted_data',
        'sent_to_webservice',
        'webservice_reference',
        'webservice_response',
        'webservice_sent_at',
        'processing_status',
        'processing_notes',
        'processing_metadata',
        'contains_sensitive_data',
        'requires_encryption',
        'is_encrypted',
        'encryption_method',
        'download_count',
        'last_accessed_at',
        'last_accessed_by_user_id',
        'active',
        'is_deleted',
        'deleted_at',
        'deleted_by_user_id',
        'created_date',
        'created_by_user_id',
        'last_updated_date',
        'last_updated_by_user_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_current_version' => 'boolean',
        'requires_approval' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_expired' => 'boolean',
        'notify_expiration' => 'boolean',
        'is_digitally_signed' => 'boolean',
        'signature_metadata' => 'array',
        'signature_verified' => 'boolean',
        'has_ocr_content' => 'boolean',
        'extracted_data' => 'array',
        'sent_to_webservice' => 'boolean',
        'webservice_response' => 'array',
        'webservice_sent_at' => 'datetime',
        'processing_metadata' => 'array',
        'contains_sensitive_data' => 'boolean',
        'requires_encryption' => 'boolean',
        'is_encrypted' => 'boolean',
        'last_accessed_at' => 'datetime',
        'active' => 'boolean',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'created_date' => 'datetime',
        'last_updated_date' => 'datetime',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
