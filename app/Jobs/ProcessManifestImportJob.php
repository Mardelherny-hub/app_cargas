<?php

namespace App\Jobs;

use App\Models\ImportTracking;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\CmspEdiParserCompat;
use App\Services\Parsers\ManifestParserFactory;
use App\ValueObjects\ManifestParseResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Procesa una importación de manifiesto en segundo plano.
 */
class ProcessManifestImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public ?string $departureDate = null;
    public ?string $voyageNumber = null;
    public ?string $loadingDate = null;
    public ?string $dischargeDate = null;

    public function __construct(
        public int $trackingId,
        public string $storedPath,
        public int $vesselId,
        public int $userId,
        public string $originalName,
        ?string $departureDate = null,
        ?string $voyageNumber = null,
        ?string $loadingDate = null,
        ?string $dischargeDate = null,
    ) {
        $this->departureDate = $departureDate;
        $this->voyageNumber = $voyageNumber;
        $this->loadingDate = $loadingDate;
        $this->dischargeDate = $dischargeDate;
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $tracking = ImportTracking::find($this->trackingId);
        if (!$tracking) {
            Log::error('ProcessManifestImportJob: tracking no encontrado', [
                'tracking_id' => $this->trackingId,
            ]);
            return;
        }

        Auth::loginUsingId($this->userId);
        $tracking->markProcessing();

        $fullPath = Storage::path($this->storedPath);

        Log::info('ProcessManifestImportJob: iniciando', [
            'tracking_id' => $this->trackingId,
            'original_name' => $this->originalName,
            'stored_path' => $this->storedPath,
            'file_exists' => is_file($fullPath),
        ]);

        $importacionExitosa = false;

        try {
            $parser = (new ManifestParserFactory())->getParser($fullPath);

            /** @var ManifestParseResult $result */
            $result = $parser->parse($fullPath, [
                'vessel_id' => $this->vesselId,
                'departure_date' => $this->departureDate,
                'voyage_number' => $this->voyageNumber,
                'loading_date' => $this->loadingDate,
                'discharge_date' => $this->dischargeDate,
            ]);

            if ($result->isSuccessful()) {
                $this->applyOperationalImportDates($parser, $result);
                $this->applyFormatPostProcessing($parser, $result);

                $voyageId = $result->voyage?->id;
                $manifestImportId = $this->resolveManifestImportId($voyageId);

                $tracking->markCompleted(
                    $result->hasWarnings(),
                    $manifestImportId,
                    $voyageId
                );

                Log::info('ProcessManifestImportJob: completado', [
                    'tracking_id' => $this->trackingId,
                    'voyage_id' => $voyageId,
                    'warnings' => $result->hasWarnings(),
                ]);

                $importacionExitosa = true;
            } else {
                $message = $result->getFirstError()
                    ?? 'La importación no pudo completarse.';

                $tracking->markFailed($message);

                Log::warning(
                    'ProcessManifestImportJob: import fallido (result failure)',
                    [
                        'tracking_id' => $this->trackingId,
                        'error' => $message,
                    ]
                );
            }
        } catch (Throwable $e) {
            $tracking->markFailed(
                'Error durante la importación: ' . $e->getMessage()
            );

            Log::error('ProcessManifestImportJob: excepción', [
                'tracking_id' => $this->trackingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($importacionExitosa) {
                Storage::delete($this->storedPath);
            } else {
                Log::info(
                    'ProcessManifestImportJob: archivo conservado para diagnostico',
                    [
                        'tracking_id' => $this->trackingId,
                        'stored_path' => $this->storedPath,
                    ]
                );
                $this->limpiarImportacionesViejas();
            }
        }
    }

    /**
     * Completa únicamente datos operativos que el formato no aporta.
     * Las fechas reales del archivo siempre tienen prioridad.
     */
    protected function applyOperationalImportDates(
        object $parser,
        ManifestParseResult $result
    ): void {
        $voyage = $result->voyage;

        if (!$voyage) {
            return;
        }

        if (
            $this->departureDate !== null
            && !$voyage->departure_date
        ) {
            $voyage->departure_date = $this->departureDate;
            $voyage->saveQuietly();
        }

        $shipmentIds = \App\Models\Shipment::where(
            'voyage_id',
            $voyage->id
        )->pluck('id');

        if ($shipmentIds->isEmpty()) {
            return;
        }

        $operatorLoadingIsSource =
            $parser instanceof \App\Services\Parsers\GuaranExcelParser
            || $parser instanceof \App\Services\Parsers\LoginXmlParser
            || $parser instanceof \App\Services\Parsers\ParanaExcelParser
            || $parser instanceof \App\Services\Parsers\NavsurTextParser
            || $parser instanceof \App\Services\Parsers\TfpTextParser
            || $parser instanceof \App\Services\Parsers\KlineDataParser;

        $operatorDischargeIsSource = !($parser instanceof CmspEdiParser);

        $sourceBillDate = $parser instanceof CmspEdiParserCompat
            ? $parser->sourceDocumentDate()
            : null;

        $bills = \App\Models\BillOfLading::whereIn(
            'shipment_id',
            $shipmentIds
        )->get();

        foreach ($bills as $bill) {
            $changed = false;

            if ($parser instanceof CmspEdiParser) {
                if (!$bill->loading_date && $voyage->departure_date) {
                    $bill->loading_date = $voyage->departure_date;
                    $changed = true;
                }

                if (!$bill->discharge_date && $voyage->estimated_arrival_date) {
                    $bill->discharge_date = $voyage->estimated_arrival_date;
                    $changed = true;
                }
            }

            if (
                $this->loadingDate !== null
                && (
                    $operatorLoadingIsSource
                    || !$bill->loading_date
                )
            ) {
                $bill->loading_date = $this->loadingDate;
                $changed = true;
            }

            if (
                $this->dischargeDate !== null
                && (
                    $operatorDischargeIsSource
                    || !$bill->discharge_date
                )
            ) {
                $bill->discharge_date = $this->dischargeDate;
                $changed = true;
            }

            if (!$bill->bill_date) {
                $bill->bill_date = $sourceBillDate ?? now()->toDateString();
                $changed = true;
            }

            if ($changed) {
                $bill->save();
            }
        }
    }

    /**
     * Normalización posterior específica de CUSCAR/CMSP.
     */
    protected function applyFormatPostProcessing(
        object $parser,
        ManifestParseResult $result
    ): void {
        if (!($parser instanceof CmspEdiParser)) {
            return;
        }

        $voyage = $result->voyage;
        if (!$voyage) {
            return;
        }

        $shipmentIds = \App\Models\Shipment::where(
            'voyage_id',
            $voyage->id
        )->pluck('id');

        if ($shipmentIds->isEmpty()) {
            return;
        }

        $bills = \App\Models\BillOfLading::whereIn(
            'shipment_id',
            $shipmentIds
        )->get();

        $billIds = $bills->pluck('id');

        foreach ($bills as $bill) {
            if ($bill->source_format !== 'CMSP_EDI_CUSCAR') {
                $bill->source_format = 'CMSP_EDI_CUSCAR';
                $bill->saveQuietly();
            }

            $bill->specificContacts()
                ->where('use_specific_data', true)
                ->update(['use_specific_data' => false]);
        }

        if ($billIds->isEmpty()) {
            return;
        }

        $itemIds = \App\Models\ShipmentItem::whereIn(
            'bill_of_lading_id',
            $billIds
        )->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        DB::table('container_shipment_item')
            ->whereIn('shipment_item_id', $itemIds)
            ->whereNull('package_quantity')
            ->update([
                'package_quantity' => 0,
                'updated_at' => now(),
            ]);
    }

    protected function resolveManifestImportId(?int $voyageId): ?int
    {
        if (!$voyageId) {
            return null;
        }

        return \App\Models\ManifestImport::where('voyage_id', $voyageId)
            ->latest('id')
            ->value('id');
    }

    protected function limpiarImportacionesViejas(int $dias = 30): void
    {
        try {
            $limite = now()->subDays($dias)->getTimestamp();
            $borrados = 0;

            foreach (Storage::files('imports/manifests') as $archivo) {
                if (Storage::lastModified($archivo) < $limite) {
                    Storage::delete($archivo);
                    $borrados++;
                }
            }

            if ($borrados > 0) {
                Log::info(
                    'ProcessManifestImportJob: limpieza de importaciones viejas',
                    [
                        'borrados' => $borrados,
                        'dias' => $dias,
                    ]
                );
            }
        } catch (Throwable $e) {
            Log::warning(
                'ProcessManifestImportJob: fallo la limpieza de archivos viejos',
                ['error' => $e->getMessage()]
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $tracking = ImportTracking::find($this->trackingId);
        if ($tracking && !$tracking->isFinished()) {
            $tracking->markFailed(
                'La importación falló: ' . $exception->getMessage()
            );
        }

        Log::error('ProcessManifestImportJob: failed()', [
            'tracking_id' => $this->trackingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
