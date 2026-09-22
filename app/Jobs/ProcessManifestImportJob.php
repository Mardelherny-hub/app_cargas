<?php

namespace App\Jobs;

use App\Models\ImportTracking;
use App\Services\Parsers\ManifestParserFactory;
use App\ValueObjects\ManifestParseResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
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
    public ?string $operationType = null;

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
        ?string $operationType = null,
    ) {
        $this->departureDate = $departureDate;
        $this->voyageNumber = $voyageNumber;
        $this->loadingDate = $loadingDate;
        $this->dischargeDate = $dischargeDate;
        $this->operationType = $operationType;
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
                'operation_type' => $this->operationType,
            ]);

            if ($result->isSuccessful()) {
                $this->applyOperationalImportDates($parser, $result);
                $this->applyContainerPrimaryTypes($result);

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
            /*
             * Durante la etapa actual de auditoría/smoke se conserva SIEMPRE
             * el archivo fuente, incluso cuando la importación termina bien.
             * Esto permite reproducir observaciones detectadas después de una
             * importación exitosa y también volver a analizar importaciones
             * posteriormente revertidas.
             *
             * La retención sigue siendo acotada: la limpieza por antigüedad
             * elimina archivos de más de 30 días.
             */
            Log::info(
                'ProcessManifestImportJob: archivo conservado para trazabilidad',
                [
                    'tracking_id' => $this->trackingId,
                    'stored_path' => $this->storedPath,
                    'importacion_exitosa' => $importacionExitosa,
                ]
            );

            $this->limpiarImportacionesViejas();
        }
    }

    /**
     * Completa las fechas operativas ingresadas al importar sin reemplazar
     * fechas reales que el formato sí informa.
     *
     * Criterio operativo vigente: el archivo es la fuente primaria. Los datos
     * ingresados en la pantalla completan lo que el formato no aporta; si un
     * dato fuente debe corregirse, se edita después desde la aplicación.
     *
     * Auditoría de formatos:
     * - GUARAN, Login, Paraná, Navsur y TFP no aportan fecha específica de carga.
     * - K-Line usa ETD como referencia, no una fecha específica de carga del BL.
     * - G2Ocean sí aporta dateOfLoading.
     * - CMSP/CUSCAR aporta fechas operativas mediante DTM y debe preservarlas.
     *
     * Los parsers Compat heredan de los parsers base; se normaliza el nombre
     * para que esta política no dependa de la clase wrapper elegida por Factory.
     */
    protected function applyOperationalImportDates(
        object $parser,
        ManifestParseResult $result
    ): void {
        $voyage = $result->voyage;

        if (!$voyage) {
            return;
        }

        $parserName = preg_replace(
            '/Compat$/',
            '',
            class_basename($parser)
        );

        /*
         * Salida: el valor del archivo gana. El formulario actúa sólo como
         * fallback cuando el parser no pudo resolver departure_date.
         */
        if (
            $this->departureDate !== null
            && !$voyage->departure_date
        ) {
            $voyage->departure_date = $this->departureDate;
            $voyage->saveQuietly();
        }

        $operatorLoadingIsSource = in_array(
            $parserName,
            [
                'GuaranExcelParser',
                'LoginXmlParser',
                'ParanaExcelParser',
                'NavsurTextParser',
                'TfpTextParser',
                'KlineDataParser',
            ],
            true
        );

        /*
         * CMSP/CUSCAR sí declara descarga/ETA operativa. En los demás formatos
         * la descarga ingresada por el operador es el dato operativo disponible.
         */
        $operatorDischargeIsSource = $parserName !== 'CmspEdiParser';

        if (
            $this->dischargeDate !== null
            && (
                $operatorDischargeIsSource
                || !$voyage->estimated_arrival_date
            )
        ) {
            $voyage->estimated_arrival_date = $this->dischargeDate;
            $voyage->saveQuietly();
        }

        if ($this->loadingDate === null && $this->dischargeDate === null) {
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

        foreach ($bills as $bill) {
            $changed = false;

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

            if ($changed) {
                $bill->save();
            }
        }
    }

    /**
     * Regla funcional confirmada por Roberto (15/09/2026): todo conocimiento
     * que tenga contenedores vinculados debe quedar clasificado con tipo
     * principal de carga CONTENEDORES y tipo principal de embalaje CONTENEDOR,
     * independientemente del formato de origen.
     */
    protected function applyContainerPrimaryTypes(
        ManifestParseResult $result
    ): void {
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

        $billIds = \App\Models\BillOfLading::whereIn(
            'shipment_id',
            $shipmentIds
        )->pluck('id');

        if ($billIds->isEmpty()) {
            return;
        }

        $containerizedBillIds = \Illuminate\Support\Facades\DB::table(
            'shipment_items'
        )
            ->join(
                'container_shipment_item',
                'container_shipment_item.shipment_item_id',
                '=',
                'shipment_items.id'
            )
            ->whereIn('shipment_items.bill_of_lading_id', $billIds)
            ->distinct()
            ->pluck('shipment_items.bill_of_lading_id');

        if ($containerizedBillIds->isEmpty()) {
            return;
        }

        $cargoTypeId = \App\Models\CargoType::where('code', 'CON001')
            ->where('active', true)
            ->value('id');
        $packagingTypeId = \App\Models\PackagingType::where('code', 'T')
            ->where('active', true)
            ->value('id');

        if (!$cargoTypeId || !$packagingTypeId) {
            throw new \RuntimeException(
                'No están disponibles los catálogos activos CONTENEDORES/CONTENEDOR.'
            );
        }

        $bills = \App\Models\BillOfLading::whereIn(
            'id',
            $containerizedBillIds
        )->get();

        foreach ($bills as $bill) {
            $bill->primary_cargo_type_id = $cargoTypeId;
            $bill->primary_packaging_type_id = $packagingTypeId;
            $bill->saveQuietly();
        }
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
