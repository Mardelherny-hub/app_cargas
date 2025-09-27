<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * El Viaje principal creado (si existe)
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
        // 1) Leer lo que ya dejó trackeado la importación (si existe)
        $voyageIds    = collect($this->created_voyage_ids ?? [])->filter()->values();
        $shipmentIds  = collect($this->created_shipment_ids ?? [])->filter()->values();
        $billIds      = collect($this->created_bill_ids ?? [])->filter()->values();
        $containerIds = collect($this->created_container_ids ?? [])->filter()->values();
        $itemIds      = collect($this->created_item_ids ?? [])->filter()->values();

        // 2) Descubrir nombres reales de tablas
        $billsTable = Schema::hasTable('bills_of_lading')
            ? 'bills_of_lading'
            : (Schema::hasTable('bills_of_landing') ? 'bills_of_landing' : null);

        $itemsTable = Schema::hasTable('shipment_items')
            ? 'shipment_items'
            : (Schema::hasTable('shipments_items') ? 'shipments_items' : null);

        $containersTable = Schema::hasTable('containers') ? 'containers' : null;
        $shipmentsTable  = Schema::hasTable('shipments')  ? 'shipments'  : null;
        $voyagesTable    = Schema::hasTable('voyages')    ? 'voyages'    : null;

        // 3) Fallbacks por created_by_import_id o relaciones típicas
        if ($voyageIds->isEmpty() && $voyagesTable) {
            if (Schema::hasColumn($voyagesTable, 'created_by_import_id')) {
                $voyageIds = DB::table($voyagesTable)
                    ->where('created_by_import_id', $this->id)
                    ->pluck('id');
            } elseif (isset($this->voyage_id)) {
                $voyageIds = collect([$this->voyage_id])->filter();
            } elseif (method_exists($this, 'voyage') && optional($this->voyage)->id) {
                $voyageIds = collect([$this->voyage->id]);
            }
        }

        if ($shipmentsTable && $shipmentIds->isEmpty()) {
            if (Schema::hasColumn($shipmentsTable, 'created_by_import_id')) {
                $shipmentIds = DB::table($shipmentsTable)
                    ->where('created_by_import_id', $this->id)
                    ->pluck('id');
            } elseif ($voyageIds->isNotEmpty() && Schema::hasColumn($shipmentsTable, 'voyage_id')) {
                $shipmentIds = DB::table($shipmentsTable)
                    ->whereIn('voyage_id', $voyageIds)
                    ->pluck('id');
            }
        }

        if ($billsTable && $billIds->isEmpty()) {
            if (Schema::hasColumn($billsTable, 'created_by_import_id')) {
                $billIds = DB::table($billsTable)
                    ->where('created_by_import_id', $this->id)
                    ->pluck('id');
            } elseif ($shipmentIds->isNotEmpty() && Schema::hasColumn($billsTable, 'shipment_id')) {
                $billIds = DB::table($billsTable)
                    ->whereIn('shipment_id', $shipmentIds)
                    ->pluck('id');
            }
        }

        if ($itemsTable && $itemIds->isEmpty()) {
            if (Schema::hasColumn($itemsTable, 'created_by_import_id')) {
                $itemIds = DB::table($itemsTable)
                    ->where('created_by_import_id', $this->id)
                    ->pluck('id');
            } else {
                $c = collect();
                $bolItemFk = null;
                if (Schema::hasColumn($itemsTable, 'bill_of_lading_id'))   $bolItemFk = 'bill_of_lading_id';
                if (Schema::hasColumn($itemsTable, 'bill_of_landing_id'))  $bolItemFk = $bolItemFk ?? 'bill_of_landing_id';

                if ($bolItemFk && $billIds->isNotEmpty()) {
                    $c = $c->merge(DB::table($itemsTable)->whereIn($bolItemFk, $billIds)->pluck('id'));
                }
                if ($shipmentIds->isNotEmpty() && Schema::hasColumn($itemsTable, 'shipment_id')) {
                    $c = $c->merge(DB::table($itemsTable)->whereIn('shipment_id', $shipmentIds)->pluck('id'));
                }
                $itemIds = $c->unique()->values();
            }
        }

        // 4) Containers por FKs directas (si existieran)
        if ($containersTable && $containerIds->isEmpty()) {
            $c = collect();

            $bolFk = null;
            if (Schema::hasColumn($containersTable, 'bill_of_lading_id'))   $bolFk = 'bill_of_lading_id';
            if (Schema::hasColumn($containersTable, 'bill_of_landing_id'))  $bolFk = $bolFk ?? 'bill_of_landing_id';

            if ($bolFk && $billIds->isNotEmpty()) {
                $c = $c->merge(DB::table($containersTable)->whereIn($bolFk, $billIds)->pluck('id'));
            }
            if ($shipmentIds->isNotEmpty() && Schema::hasColumn($containersTable, 'shipment_id')) {
                $c = $c->merge(DB::table($containersTable)->whereIn('shipment_id', $shipmentIds)->pluck('id'));
            }

            // 5) 🔎 BÚSQUEDA AUTOMÁTICA DE PIVOTES QUE REFERENCIAN container_id
            //    (caso PARANA: contenedores asociados a ítems vía pivote)
            //    Recorremos todas las tablas y buscamos columnas: container_id + {shipment_item_id | bill_of_lading_id | bill_of_landing_id | shipment_id | voyage_id}
            $driver = DB::getDriverName();
            $tables = [];

            if ($driver === 'mysql') {
                $rows = DB::select('SHOW TABLES');
                foreach ($rows as $row) {
                    $tables[] = array_values((array)$row)[0] ?? null;
                }
            } else {
                // fallback simple si no es MySQL (ajustar si fuera necesario)
                $tables = []; // podrías listar manualmente tus posibles pivotes aquí
            }

            foreach ($tables as $t) {
                if (!$t || !Schema::hasTable($t)) continue;
                $cols = collect(Schema::getColumnListing($t));

                if (!$cols->contains('container_id')) continue;

                $linkCols = $cols->intersect([
                    'shipment_item_id',
                    'bill_of_lading_id',
                    'bill_of_landing_id',
                    'shipment_id',
                    'voyage_id',
                ]);

                if ($linkCols->isEmpty()) continue;

                // Construimos consulta al pivote para recolectar container_id por cualquier conjunto de IDs que tengamos
                $q = DB::table($t)->select('container_id');

                $hasFilter = false;
                if ($itemIds->isNotEmpty() && $cols->contains('shipment_item_id')) {
                    $q->orWhereIn('shipment_item_id', $itemIds); $hasFilter = true;
                }
                if ($billIds->isNotEmpty()) {
                    if ($cols->contains('bill_of_lading_id'))  { $q->orWhereIn('bill_of_lading_id',  $billIds); $hasFilter = true; }
                    if ($cols->contains('bill_of_landing_id')) { $q->orWhereIn('bill_of_landing_id', $billIds); $hasFilter = true; }
                }
                if ($shipmentIds->isNotEmpty() && $cols->contains('shipment_id')) {
                    $q->orWhereIn('shipment_id', $shipmentIds); $hasFilter = true;
                }
                if ($voyageIds->isNotEmpty() && $cols->contains('voyage_id')) {
                    $q->orWhereIn('voyage_id', $voyageIds); $hasFilter = true;
                }

                if ($hasFilter) {
                    $c = $c->merge($q->pluck('container_id'));
                }
            }

            $containerIds = $c->unique()->values();
        }

        return [
            'voyages'    => $voyageIds->values()->all(),
            'shipments'  => $shipmentIds->values()->all(),
            'bills'      => $billIds->values()->all(),
            'containers' => $containerIds->values()->all(),
            'items'      => $itemIds->values()->all(),
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

// Detecta la tabla pivote contenedores⇆ítems (sin inventar nombres raros)
public function detectContainerPivot(): ?array
{
    $candidates = [
        'container_shipment_item',
        'container_shipment_items',
        'container_items',
    ];

    foreach ($candidates as $table) {
        if (
            Schema::hasTable($table) &&
            Schema::hasColumn($table, 'container_id') &&
            Schema::hasColumn($table, 'shipment_item_id')
        ) {
            return [
                'table'        => $table,
                'container_fk' => 'container_id',
                'item_fk'      => 'shipment_item_id',
            ];
        }
    }

    return null;
}



}