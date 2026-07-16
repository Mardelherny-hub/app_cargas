<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Seguimiento de una importación encolada, para la pantalla de espera (spinner).
 *
 * Existe desde el instante del encolado (lo crea el controller), independiente
 * de si el parser llega a crear su ManifestImport. El Job lo actualiza leyendo
 * el ManifestParseResult que devuelve el parser. Es lo que sigue el polling:
 * así el spinner nunca queda colgado aunque el parser aborte temprano
 * (ej. voyage duplicado, que no crea ManifestImport).
 */
class ImportTracking extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'vessel_id',
        'original_name',
        'stored_path',
        'status',
        'manifest_import_id',
        'voyage_id',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    // Estados posibles
    public const STATUS_QUEUED      = 'queued';
    public const STATUS_PROCESSING  = 'processing';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_WARNINGS    = 'completed_with_warnings';
    public const STATUS_FAILED      = 'failed';

    protected static function booted(): void
    {
        // Genera el uuid automáticamente si no viene seteado.
        static::creating(function (ImportTracking $tracking) {
            if (empty($tracking->uuid)) {
                $tracking->uuid = (string) Str::uuid();
            }
        });
    }

    public function markProcessing(): void
    {
        $this->update([
            'status'     => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(bool $withWarnings, ?int $manifestImportId, ?int $voyageId): void
    {
        $this->update([
            'status'             => $withWarnings ? self::STATUS_WARNINGS : self::STATUS_COMPLETED,
            'manifest_import_id' => $manifestImportId,
            'voyage_id'          => $voyageId,
            'finished_at'        => now(),
        ]);
    }

    public function markFailed(string $message, ?int $manifestImportId = null): void
    {
        $this->update([
            'status'             => self::STATUS_FAILED,
            'error_message'      => $message,
            'manifest_import_id' => $manifestImportId,
            'finished_at'        => now(),
        ]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_WARNINGS,
            self::STATUS_FAILED,
        ]);
    }
}