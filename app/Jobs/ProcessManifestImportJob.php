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
 *
 * Envuelve el mismo $parser->parse(...) que corre hoy en el request, sin tocar
 * los parsers ni ManifestImport. La diferencia clave respecto a la primera
 * versión: LEE el ManifestParseResult que devuelve el parser y actualiza el
 * ImportTracking en consecuencia (completed / failed con mensaje real). Así el
 * caso "voyage duplicado" -que el parser devuelve como failure sin lanzar
 * excepción- deja de pasar como éxito silencioso, y el spinner sabe qué mostrar.
 *
 * El ImportTracking existe desde el encolado (lo crea el controller), así que el
 * polling nunca queda colgado aunque el parser aborte antes de crear su
 * ManifestImport.
 *
 * tries = 1: los parsers crean voyage/BL/clientes/items y no está verificado que
 * los 8 formatos sean idempotentes; un reintento podría duplicar registros.
 */
class ProcessManifestImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public int $trackingId,
        public string $storedPath,
        public int $vesselId,
        public int $userId,
        public string $originalName,
    ) {
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

        // Reconstruye el contexto de auth que todo el parse() espera.
        Auth::loginUsingId($this->userId);

        $tracking->markProcessing();

        $fullPath = Storage::path($this->storedPath);

        Log::info('ProcessManifestImportJob: iniciando', [
            'tracking_id'   => $this->trackingId,
            'original_name' => $this->originalName,
            'stored_path'   => $this->storedPath,
            'file_exists'   => is_file($fullPath),
        ]);

        // El archivo solo se borra si la importacion termino bien. Si fallo, se
        // conserva para que soporte pueda diagnosticar sin pedirselo al cliente.
        $importacionExitosa = false;

        try {
            $parser = (new ManifestParserFactory())->getParser($fullPath);

            /** @var ManifestParseResult $result */
            $result = $parser->parse($fullPath, ['vessel_id' => $this->vesselId]);

            if ($result->isSuccessful()) {
                // Import OK (con o sin advertencias). Guardamos voyage_id y el
                // ManifestImport asociado (buscado por voyage, si el parser lo creó).
                $voyageId = $result->voyage?->id;
                $manifestImportId = $this->resolveManifestImportId($voyageId);

                $tracking->markCompleted(
                    $result->hasWarnings(),
                    $manifestImportId,
                    $voyageId
                );

                Log::info('ProcessManifestImportJob: completado', [
                    'tracking_id' => $this->trackingId,
                    'voyage_id'   => $voyageId,
                    'warnings'    => $result->hasWarnings(),
                ]);

                $importacionExitosa = true;
            } else {
                // El parser devolvió failure (ej. voyage duplicado). NO es éxito.
                $message = $result->getFirstError() ?? 'La importación no pudo completarse.';
                $tracking->markFailed($message);

                Log::warning('ProcessManifestImportJob: import fallido (result failure)', [
                    'tracking_id' => $this->trackingId,
                    'error'       => $message,
                ]);
            }
        } catch (Throwable $e) {
            // Excepción no controlada por el parser.
            $tracking->markFailed('Error durante la importación: ' . $e->getMessage());
            Log::error('ProcessManifestImportJob: excepción', [
                'tracking_id' => $this->trackingId,
                'error'       => $e->getMessage(),
            ]);
            // Relanzar para que quede registro en failed_jobs también.
            throw $e;
        } finally {
            if ($importacionExitosa) {
                Storage::delete($this->storedPath);
            } else {
                Log::info('ProcessManifestImportJob: archivo conservado para diagnostico', [
                    'tracking_id' => $this->trackingId,
                    'stored_path' => $this->storedPath,
                ]);
                $this->limpiarImportacionesViejas();
            }
        }
    }

    /**
     * Ubica el ManifestImport que el parser creó para este viaje (si lo creó),
     * para enlazarlo al tracking y poder armar el reporte después. Se busca por
     * voyage_id porque es el vínculo fiable; puede no existir (parser que abortó).
     */
    protected function resolveManifestImportId(?int $voyageId): ?int
    {
        if (!$voyageId) {
            return null;
        }

        return \App\Models\ManifestImport::where('voyage_id', $voyageId)
            ->latest('id')
            ->value('id');
    }
    /**
     * Los archivos solo se acumulan cuando una importacion falla (las exitosas
     * borran el suyo), asi que la limpieza se dispara en ese mismo momento: no
     * hace falta cron y se ejecuta exactamente cuando el directorio crecio.
     *
     * Nunca interrumpe el flujo: si la limpieza falla se loguea y se sigue. Es
     * mantenimiento, y no puede tapar el error real de la importacion.
     */
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
                Log::info('ProcessManifestImportJob: limpieza de importaciones viejas', [
                    'borrados' => $borrados,
                    'dias'     => $dias,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('ProcessManifestImportJob: fallo la limpieza de archivos viejos', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fallo duro (excepción no atrapada / timeout / OOM parcial): marca el
     * tracking como failed si el finally no llegó. El archivo NO se borra:
     * un job que muere asi es justo el caso donde mas se necesita.
     */
    public function failed(Throwable $exception): void
    {
        $tracking = ImportTracking::find($this->trackingId);
        if ($tracking && !$tracking->isFinished()) {
            $tracking->markFailed('La importación falló: ' . $exception->getMessage());
        }

        Log::error('ProcessManifestImportJob: failed()', [
            'tracking_id' => $this->trackingId,
            'error'       => $exception->getMessage(),
        ]);
    }
}