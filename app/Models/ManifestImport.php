<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * MODELO PARA TRACKING Y REVERSIÓN DE IMPORTACIONES DE MANIFIESTOS
 * 
 * Permite:
 * - Registrar cada importación con detalles completos
 * - Rastrear todos los objetos creados para reversión
 * - Historial completo con estadísticas  
 * - Control de reversión granular
 * 
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $voyage_id
 * @property string $file_name
 * @property string $file_format
 * @property int|null $file_size_bytes
 * @property string|null $file_hash
 * @property string $status
 * @property array|null $import_statistics
 * @property int $created_voyages
 * @property int $created_shipments
 * @property int $created_bills
 * @property int $created_items
 * @property int $created_containers
 * @property int $created_clients
 * @property int $created_ports
 * @property array|null $warnings
 * @property array|null $errors
 * @property int $warnings_count
 * @property int $errors_count
 * @property array|null $created_voyage_ids
 * @property array|null $created_shipment_ids
 * @property array|null $created_bill_ids
 * @property array|null $created_item_ids
 * @property array|null $created_container_ids
 * @property array|null $created_client_ids
 * @property array|null $created_port_ids
 * @property bool $can_be_reverted
 * @property string|null $revert_blocked_reason
 * @property Carbon|null $reverted_at
 * @property int|null $reverted_by_user_id
 * @property array|null $revert_details
 * @property string|null $notes
 * @property array|null $parser_config
 * @property float|null $processing_time_seconds
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ManifestImport extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'manifest_imports';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'user_id', 
        'voyage_id',
        'file_name',
        'file_format',
        'file_size_bytes',
        'file_hash',
        'status',
        'import_statistics',
        'created_voyages',
        'created_shipments',
        'created_bills',
        'created_items',
        'created_containers',
        'created_clients',
        'created_ports',
        'warnings',
        'errors',
        'warnings_count',
        'errors_count',
        'created_voyage_ids',
        'created_shipment_ids',
        'created_bill_ids',
        'created_item_ids',
        'created_container_ids',
        'created_client_ids',
        'created_port_ids',
        'can_be_reverted',
        'revert_blocked_reason',
        'reverted_at',
        'reverted_by_user_id',
        'revert_details',
        'notes',
        'parser_config',
        'processing_time_seconds',
        'started_at',
        'completed_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'import_statistics' => 'array',
        'warnings' => 'array',
        'errors' => 'array',
        'created_voyage_ids' => 'array',
        'created_shipment_ids' => 'array',
        'created_bill_ids' => 'array',
        'created_item_ids' => 'array',
        'created_container_ids' => 'array',
        'created_client_ids' => 'array',
        'created_port_ids' => 'array',
        'can_be_reverted' => 'boolean',
        'revert_details' => 'array',
        'parser_config' => 'array',
        'processing_time_seconds' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reverted_at' => 'datetime'
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    /**
     * La empresa que realizó la importación
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * El usuario que realizó la importación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El voyage principal creado (si existe)
     */
    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    /**
     * El usuario que ejecutó la reversión (si fue revertida)
     */
    public function revertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by_user_id');
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    /**
     * Scope para importaciones de una empresa específica
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para importaciones de un usuario específico
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para importaciones por estado
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para importaciones completadas exitosamente
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', ['completed', 'completed_with_warnings']);
    }

    /**
     * Scope para importaciones que pueden ser revertidas
     */
    public function scopeRevertible($query)
    {
        return $query->where('can_be_reverted', true)
                    ->whereNotIn('status', ['reverted', 'failed']);
    }

    /**
     * Scope para importaciones por formato
     */
    public function scopeByFormat($query, string $format)
    {
        return $query->where('file_format', $format);
    }

    // =============================================================================
    // ACCESSORS & MUTATORS
    // =============================================================================

    /**
     * Obtener el tamaño del archivo formateado
     */
    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size_bytes) {
            return 'N/A';
        }

        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    /**
     * Obtener el tiempo de procesamiento formateado
     */
    public function getProcessingTimeFormattedAttribute(): string
    {
        if (!$this->processing_time_seconds) {
            return 'N/A';
        }

        $seconds = $this->processing_time_seconds;
        
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }

    /**
     * Obtener el estado formateado con colores
     */
    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'processing' => ['text' => 'Procesando', 'color' => 'yellow'],
            'completed' => ['text' => 'Completado', 'color' => 'green'],
            'completed_with_warnings' => ['text' => 'Con Advertencias', 'color' => 'orange'],
            'failed' => ['text' => 'Fallido', 'color' => 'red'],
            'reverted' => ['text' => 'Revertido', 'color' => 'gray']
        ];

        return $badges[$this->status] ?? ['text' => $this->status, 'color' => 'gray'];
    }

    /**
     * Obtener resumen de objetos creados
     */
    public function getCreatedObjectsSummaryAttribute(): string
    {
        $summary = [];
        
        if ($this->created_bills > 0) {
            $summary[] = "{$this->created_bills} conocimientos";
        }
        if ($this->created_items > 0) {
            $summary[] = "{$this->created_items} items";
        }
        if ($this->created_containers > 0) {
            $summary[] = "{$this->created_containers} contenedores";
        }
        if ($this->created_clients > 0) {
            $summary[] = "{$this->created_clients} clientes";
        }

        return empty($summary) ? 'Ningún objeto creado' : implode(', ', $summary);
    }

    // =============================================================================
    // METHODS
    // =============================================================================

    /**
     * Verificar si la importación fue exitosa
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_warnings']);
    }

    /**
     * Verificar si la importación falló
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verificar si la importación fue revertida
     */
    public function isReverted(): bool
    {
        return $this->status === 'reverted';
    }

    /**
     * Verificar si la importación puede ser revertida
     */
    public function canBeReverted(): bool
    {
        return $this->can_be_reverted && 
               $this->isSuccessful() && 
               !$this->isReverted();
    }

    /**
     * Marcar como iniciada
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now()
        ]);
    }

    /**
     * Marcar como completada exitosamente
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge([
            'status' => 'completed',
            'completed_at' => now()
        ], $data));
    }

    /**
     * Marcar como completada con advertencias
     */
    public function markAsCompletedWithWarnings(array $data = []): void
    {
        $this->update(array_merge([
            'status' => 'completed_with_warnings',
            'completed_at' => now()
        ], $data));
    }

    /**
     * Marcar como fallida
     */
    public function markAsFailed(array $errors = [], array $data = []): void
    {
        $this->update(array_merge([
            'status' => 'failed',
            'errors' => $errors,
            'errors_count' => count($errors),
            'completed_at' => now(),
            'can_be_reverted' => false
        ], $data));
    }

    /**
     * Marcar como revertida
     */
    public function markAsReverted(int $revertedByUserId, array $revertDetails = []): void
    {
        $this->update([
            'status' => 'reverted',
            'reverted_at' => now(),
            'reverted_by_user_id' => $revertedByUserId,
            'revert_details' => $revertDetails,
            'can_be_reverted' => false
        ]);
    }

    /**
     * Bloquear reversión con razón
     */
    public function blockReversion(string $reason): void
    {
        $this->update([
            'can_be_reverted' => false,
            'revert_blocked_reason' => $reason
        ]);
    }

    /**
     * Registrar IDs de objetos creados
     */
    public function recordCreatedObjects(array $objects): void
    {
        $updates = [];
        
        foreach ($objects as $type => $ids) {
            if (!empty($ids)) {
                $fieldName = "created_{$type}_ids";
                $countField = "created_{$type}";
                
                $updates[$fieldName] = array_values($ids);
                $updates[$countField] = count($ids);
            }
        }
        
        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Obtener IDs de todos los objetos creados para reversión
     */
    public function getAllCreatedObjectIds(): array
    {
        return [
            'voyages' => $this->created_voyage_ids ?: [],
            'shipments' => $this->created_shipment_ids ?: [],
            'bills' => $this->created_bill_ids ?: [],
            'items' => $this->created_item_ids ?: [],
            'containers' => $this->created_container_ids ?: [],
            'clients' => $this->created_client_ids ?: [],
            'ports' => $this->created_port_ids ?: []
        ];
    }

    /**
     * Generar hash del archivo para detectar duplicados
     */
    public static function generateFileHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Verificar si un archivo ya fue importado
     */
    public static function isFileAlreadyImported(string $fileHash, int $companyId): ?self
    {
        return static::where('file_hash', $fileHash)
                    ->where('company_id', $companyId)
                    ->successful()
                    ->first();
    }

    /**
     * Crear registro de importación
     */
    public static function createForImport(array $data): self
    {
        return static::create(array_merge([
            'status' => 'processing',
            'started_at' => now(),
            'can_be_reverted' => true
        ], $data));
    }
}