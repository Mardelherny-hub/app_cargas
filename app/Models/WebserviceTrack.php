<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Modelo WebserviceTrack
 * 
 * SESIÓN 2: SISTEMA TRACKS AFIP
 * Gestiona TRACKs individuales para vinculación de procesos AFIP
 * 
 * CASOS DE USO SEGÚN MANUAL AFIP:
 * 1. RegistrarTitEnvios devuelve TRACKs → se almacenan aquí
 * 2. RegistrarMicDta usa TRACKs → se marca como 'used_in_micdta' 
 * 3. RegistrarConvoy usa TRACKs → se marca como 'used_in_convoy'
 * 4. Auditoría completa del flujo AFIP
 * 
 * @property int $id
 * @property int $webservice_transaction_id
 * @property int|null $shipment_id
 * @property int|null $container_id  
 * @property int|null $bill_of_lading_id
 * @property string $track_number Número TRACK devuelto por AFIP
 * @property string $track_type Tipo: envio, contenedor_vacio, bulto
 * @property string $webservice_method Método que generó: RegistrarTitEnvios, etc.
 * @property string $reference_type Tipo: shipment, container, bill
 * @property string $reference_number Número de referencia del objeto
 * @property string|null $description
 * @property string|null $afip_title_number
 * @property array|null $afip_metadata
 * @property Carbon $generated_at Fecha cuando AFIP generó el TRACK
 * @property string $status Estado: generated, used_in_micdta, completed, etc.
 * @property Carbon|null $used_at
 * @property Carbon|null $completed_at
 * @property int $created_by_user_id
 * @property string|null $created_from_ip
 * @property array|null $process_chain
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebserviceTrack extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'webservice_tracks';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'webservice_transaction_id',
        'shipment_id',
        'container_id',
        'bill_of_lading_id',
        'track_number',
        'track_type',
        'webservice_method',
        'reference_type',
        'reference_number',
        'description',
        'afip_title_number',
        'afip_metadata',
        'generated_at',
        'status',
        'used_at',
        'completed_at',
        'created_by_user_id',
        'created_from_ip',
        'process_chain',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'afip_metadata' => 'array',
        'process_chain' => 'array',
        'generated_at' => 'datetime',
        'used_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de TRACK disponibles según AFIP
     */
    public const TRACK_TYPES = [
        'envio' => 'Envío/Carga Suelta',
        'contenedor_vacio' => 'Contenedor Vacío',
        'bulto' => 'Bulto Individual',
    ];

    /**
     * Estados posibles del TRACK
     */
    public const STATUSES = [
        'generated' => 'TRACK generado por TitEnvios',
        'used_in_micdta' => 'Usado en RegistrarMicDta',
        'used_in_convoy' => 'Usado en RegistrarConvoy',
        'completed' => 'Proceso completo terminado',
        'expired' => 'TRACK expirado sin usar',
        'error' => 'Error en el proceso',
    ];

    /**
     * Métodos webservice válidos
     */
    public const WEBSERVICE_METHODS = [
        'RegistrarTitEnvios',
        'RegistrarMicDta', 
        'RegistrarConvoy',
        'TitTransContVacioReg',
    ];

    // === RELATIONSHIPS ===

    /**
     * Transacción webservice que generó este TRACK
     */
    public function webserviceTransaction(): BelongsTo
    {
        return $this->belongsTo(WebserviceTransaction::class);
    }

    /**
     * Envío asociado (si aplica)
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Contenedor asociado (si aplica)  
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /**
     * Conocimiento asociado (si aplica)
     */
    public function billOfLading(): BelongsTo
    {
        return $this->belongsTo(BillOfLading::class);
    }

    /**
     * Usuario que creó el TRACK
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // === SCOPES ===

    /**
     * TRACKs por método webservice
     */
    public function scopeByMethod(Builder $query, string $method): Builder
    {
        return $query->where('webservice_method', $method);
    }

    /**
     * TRACKs por estado
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * TRACKs disponibles para usar en MIC/DTA
     */
    public function scopeAvailableForMicDta(Builder $query): Builder
    {
        return $query->where('status', 'generated')
                    ->where('webservice_method', 'RegistrarTitEnvios')
                    ->whereNotNull('shipment_id');
    }

    /**
     * TRACKs por transacción
     */
    public function scopeForTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('webservice_transaction_id', $transactionId);
    }

    /**
     * TRACKs por número
     */
    public function scopeByTrackNumber(Builder $query, string $trackNumber): Builder
    {
        return $query->where('track_number', $trackNumber);
    }

    // === METHODS ===

    /**
     * Marcar TRACK como usado en MIC/DTA
     */
    public function markAsUsedInMicDta(): bool
    {
        if ($this->status !== 'generated') {
            return false;
        }

        return $this->update([
            'status' => 'used_in_micdta',
            'used_at' => now(),
            'process_chain' => array_merge($this->process_chain ?? [], ['used_in_micdta']),
        ]);
    }

    /**
     * Marcar TRACK como usado en Convoy
     */
    public function markAsUsedInConvoy(): bool
    {
        if (!in_array($this->status, ['generated', 'used_in_micdta'])) {
            return false;
        }

        return $this->update([
            'status' => 'used_in_convoy',
            'used_at' => now(),
            'process_chain' => array_merge($this->process_chain ?? [], ['used_in_convoy']),
        ]);
    }

    /**
     * Marcar TRACK como completado
     */
    public function markAsCompleted(string $notes = null): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes,
            'process_chain' => array_merge($this->process_chain ?? [], ['completed']),
        ]);
    }

    /**
     * Verificar si el TRACK está disponible para usar
     */
    public function isAvailable(): bool
    {
        return $this->status === 'generated';
    }

    /**
     * Verificar si el TRACK ha expirado
     */
    public function isExpired(): bool
    {
        // AFIP: TRACKs expiran después de 24 horas sin usar
        return $this->status === 'generated' && 
               $this->generated_at->diffInHours(now()) > 24;
    }

    /**
     * Obtener información de seguimiento
     */
    public function getTrackingInfo(): array
    {
        return [
            'track_number' => $this->track_number,
            'status' => $this->status,
            'status_description' => self::STATUSES[$this->status] ?? 'Estado desconocido',
            'generated_at' => $this->generated_at,
            'used_at' => $this->used_at,
            'completed_at' => $this->completed_at,
            'process_chain' => $this->process_chain ?? [],
            'reference' => [
                'type' => $this->reference_type,
                'number' => $this->reference_number,
                'description' => $this->description,
            ],
        ];
    }

    /**
     * Crear TRACKs desde respuesta de RegistrarTitEnvios
     */
    public static function createFromTitEnviosResponse(
        int $transactionId,
        array $tracksData,
        int $userId,
        string $ip = null
    ): array {
        $tracks = [];

        foreach ($tracksData as $trackData) {
            $track = self::create([
                'webservice_transaction_id' => $transactionId,
                'shipment_id' => $trackData['shipment_id'] ?? null,
                'container_id' => $trackData['container_id'] ?? null,
                'bill_of_lading_id' => $trackData['bill_of_lading_id'] ?? null,
                'track_number' => $trackData['track_number'],
                'track_type' => $trackData['track_type'] ?? 'envio',
                'webservice_method' => 'RegistrarTitEnvios',
                'reference_type' => $trackData['reference_type'] ?? 'shipment',
                'reference_number' => $trackData['reference_number'],
                'description' => $trackData['description'] ?? null,
                'afip_title_number' => $trackData['afip_title_number'] ?? null,
                'afip_metadata' => $trackData['afip_metadata'] ?? null,
                'generated_at' => now(),
                'status' => 'generated',
                'created_by_user_id' => $userId,
                'created_from_ip' => $ip,
                'process_chain' => ['generated'],
            ]);

            $tracks[] = $track;
        }

        return $tracks;
    }

    /**
     * Obtener TRACKs para usar en MIC/DTA
     */
    public static function getForMicDta(array $shipmentIds): array
    {
        return self::availableForMicDta()
                  ->whereIn('shipment_id', $shipmentIds)
                  ->get()
                  ->pluck('track_number')
                  ->toArray();
    }
}