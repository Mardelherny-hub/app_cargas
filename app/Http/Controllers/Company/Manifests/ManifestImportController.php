<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use App\Services\Parsers\ManifestParserFactory;
use App\Models\Vessel;
use App\ValueObjects\ManifestParseResult;
use App\Models\ManifestImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Str;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\Voyage;

/**
 * CONTROLADOR UNIFICADO PARA IMPORTACIÓN DE MANIFIESTOS - VERSIÓN CORREGIDA
 * 
 * CORRECCIÓN CRÍTICA APLICADA:
 * ✅ Preservar extensión original del archivo al almacenar
 * ✅ Mejorar detección de parser basada en contenido Y extensión
 * ✅ Logging detallado para debugging de auto-detección
 * ✅ Fallback inteligente cuando falla detección por contenido
 * 
 * FORMATOS SOPORTADOS:
 * - KLine.DAT (✅ integrado)
 * - PARANA.xlsx (✅ integrado) 
 * - Guaran.csv (✅ integrado)
 * - Login.xml (pendiente)
 * - TFP.txt (pendiente)
 * - CMSP.EDI (pendiente)
 * - Navsur.txt (pendiente)
 */
class ManifestImportController extends Controller
{
    protected ManifestParserFactory $parserFactory;

    public function __construct()
    {
        $this->parserFactory = new ManifestParserFactory();
    }

    /**
     * Mostrar formulario de importación con información de formatos soportados
     */
    public function showForm()
    {
        $supportedFormats = $this->parserFactory->getSupportedFormats();
        $formatStats = $this->parserFactory->getFormatStatistics();

        // Obtener embarcaciones disponibles de la empresa
        $company = auth()->user()->company;
        $vessels = Vessel::where('company_id', $company->id)
            ->where('active', true)
            ->where('operational_status', 'active')
            ->orderBy('name')
            ->get();

        // Viajes a los que se puede agregar un archivo: solo en planificación.
        $planningVoyages = Voyage::where('company_id', $company->id)
            ->where('status', 'planning')
            ->with(['leadVessel:id,name', 'shipments:id,voyage_id,vessel_id'])
            ->orderByDesc('id')
            ->get(['id', 'voyage_number', 'lead_vessel_id', 'is_convoy', 'departure_date']);

        $recentImports = ManifestImport::forCompany($company->id)
            ->with('importTracking', 'voyage:id,voyage_number')
            ->latest()
            ->limit(5)
            ->get();

        return view('company.manifests.import', [
            'recentImports' => $recentImports,
            'supportedFormats' => $supportedFormats,
            'formatStats' => $formatStats,
            'vessels' => $vessels,
            'planningVoyages' => $planningVoyages,
        ]);
    }

    /**
     * Propone la embarcación según el archivo, antes de importar.
     */
    public function detectVessel(Request $request)
    {
        $request->validate(['manifest_file' => 'required|file|max:10240']);

        $company = auth()->user()->company;
        $uploadedFile = $request->file('manifest_file');
        $path = $uploadedFile->storeAs(
            'imports/manifests/preview',
            $this->generateUniqueFileName($uploadedFile->getClientOriginalName(), $uploadedFile->getClientOriginalExtension()),
            'local'
        );

        try {
            $parser = $this->parserFactory->getParser(Storage::path($path));
            $vesselName = method_exists($parser, 'detectVesselName')
                ? $parser->detectVesselName(Storage::path($path))
                : null;
        } catch (Exception $e) {
            return response()->json(['message' => 'No se reconoce el formato del archivo.'], 422);
        } finally {
            Storage::disk('local')->delete($path);
        }

        $vesselName = $vesselName !== null ? trim($vesselName) : null;
        $vessel = $vesselName
            ? Vessel::where('company_id', $company->id)
                ->where('active', true)
                ->where('operational_status', 'active')
                ->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($vesselName)])
                ->first(['id', 'name'])
            : null;

        return response()->json([
            'supports_target' => \App\Jobs\ProcessManifestImportJob::parserSupportsTargetVoyage($parser),
            'vessel_name' => $vesselName,
            'vessel_id' => $vessel?->id,
        ]);
    }

    /**
     * Verifica en pantalla si el archivo se puede agregar al viaje elegido.
     */
    public function checkTarget(Request $request)
    {
        $data = $request->validate([
            'target_voyage_id' => 'required|integer',
            'vessel_id' => 'required|integer',
            'convoy_leader_vessel_id' => 'nullable|integer',
        ]);

        $check = $this->inspectTargetVoyage(
            auth()->user()->company->id,
            (int) $data['target_voyage_id'],
            (int) $data['vessel_id'],
            isset($data['convoy_leader_vessel_id']) ? (int) $data['convoy_leader_vessel_id'] : null
        );

        return response()->json($check);
    }

    /**
     * Reglas para agregar un archivo a un viaje existente.
     *
     * @return array{allowed: bool, needs_convoy: bool, error: ?string}
     */
    protected function inspectTargetVoyage(int $companyId, int $voyageId, int $vesselId, ?int $leaderId): array
    {
        $deny = fn (string $error, bool $needsConvoy = false) => [
            'allowed' => false, 'needs_convoy' => $needsConvoy, 'error' => $error,
        ];

        $voyage = Voyage::where('id', $voyageId)
            ->where('company_id', $companyId)
            ->where('status', 'planning')
            ->first();

        if (!$voyage) {
            return $deny('El viaje elegido no existe, no pertenece a su empresa o no está en planificación.');
        }

        $voyageVesselIds = Shipment::where('voyage_id', $voyage->id)->pluck('vessel_id')
            ->push($voyage->lead_vessel_id)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $needsConvoy = !$voyage->is_convoy && !$voyageVesselIds->contains($vesselId);

        if (!$needsConvoy) {
            $leaderId = null;
        } elseif ($leaderId === null) {
            return $deny(
                "El viaje {$voyage->voyage_number} no es un convoy. Para agregar otra embarcación, conviértalo en convoy y elija el líder.",
                true
            );
        } elseif (!Vessel::where('id', $leaderId)->where('company_id', $companyId)
            ->where('active', true)->where('operational_status', 'active')->exists()) {
            return $deny('La embarcación líder elegida no es válida o no pertenece a su empresa.', true);
        }

        // La conversión cambia el rol de todas las embarcaciones del viaje.
        $checked = $leaderId !== null
            ? $voyageVesselIds->push($vesselId)->push($leaderId)->unique()
            : collect([$vesselId]);

        $lock = app(\App\Services\Simple\AfipVesselLock::class);
        foreach ($checked as $checkedVesselId) {
            $result = $lock->inspectAfipVesselLock($companyId, $voyage->id, (int) $checkedVesselId);
            if ($result['locked']) {
                $name = Vessel::whereKey($checkedVesselId)->value('name') ?? "#{$checkedVesselId}";

                return $deny(
                    "La embarcación {$name} tiene registros vigentes en AFIP para este viaje: "
                        . implode(' ', array_column($result['reasons'], 'message')),
                    $needsConvoy
                );
            }
        }

        return ['allowed' => true, 'needs_convoy' => $needsConvoy, 'error' => null];
    }

    /**
     * Procesar archivo importado con auto-detección de formato - CORREGIDO
     */
    public function store(Request $request)
    {
        $request->validate([
            'manifest_file' => 'required|file|max:10240', // 10MB max
            'vessel_id' => 'required|exists:vessels,id',
            'departure_date' => 'nullable|date',
            'loading_date' => 'nullable|date',
            'discharge_date' => 'nullable|date',
            'voyage_number' => 'nullable|string|max:100',
            'operation_type' => 'nullable|in:import,export',
            'target_voyage_id' => 'nullable|integer',
            'convoy_leader_vessel_id' => 'nullable|integer',
        ], [
            'manifest_file.required' => 'Debe seleccionar un archivo para importar.',
            'manifest_file.file' => 'El archivo seleccionado no es válido.',
            'manifest_file.max' => 'El archivo no puede ser mayor a 10MB.'
        ]);

        /*
         * Guard transversal antes de persistir/encolar: cuando el operador
         * informa ambas fechas, la salida no puede quedar después de la
         * descarga. Se compara por día porque descarga es un campo date y
         * salida puede incluir hora.
         */
        if (
            $request->filled('departure_date')
            && $request->filled('discharge_date')
            && \Carbon\Carbon::parse($request->input('departure_date'))
                ->startOfDay()
                ->gt(\Carbon\Carbon::parse($request->input('discharge_date'))->startOfDay())
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'discharge_date' =>
                        'La fecha de descarga no puede ser anterior a la fecha de salida.',
                ]);
        }

        // Verificar que el vessel pertenece a la empresa del usuario
        $company = auth()->user()->company;
        $vessel = Vessel::where('id', $request->vessel_id)
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where('operational_status', 'active')
            ->first();

        if (!$vessel) {
            return back()->with('error', 'La embarcación seleccionada no es válida o no pertenece a su empresa.');
        }

        /*
         * Agregar a un viaje existente: se valida antes de encolar para dar
         * el motivo en pantalla. El parser vuelve a validarlo al procesar.
         */
        $targetVoyageId = $request->filled('target_voyage_id')
            ? (int) $request->input('target_voyage_id')
            : null;

        $convoyLeaderVesselId = null;

        if ($targetVoyageId !== null) {
            $check = $this->inspectTargetVoyage(
                $company->id,
                $targetVoyageId,
                $vessel->id,
                $request->filled('convoy_leader_vessel_id') ? (int) $request->input('convoy_leader_vessel_id') : null
            );

            if (!$check['allowed']) {
                return back()->withInput()->withErrors(['target_voyage_id' => $check['error']]);
            }

            $convoyLeaderVesselId = $check['needs_convoy']
                ? (int) $request->input('convoy_leader_vessel_id')
                : null;
        }

        $uploadedFile = $request->file('manifest_file');
        $originalName = $uploadedFile->getClientOriginalName();
        $originalExtension = $uploadedFile->getClientOriginalExtension();

        // ✅ CORREGIDO: Almacenar archivo preservando la extensión original
        $fileName = $this->generateUniqueFileName($originalName, $originalExtension);
        $path = $uploadedFile->storeAs('imports/manifests', $fileName, 'local');
        $fullPath = Storage::path($path);

        Log::info('Starting manifest import process - IMPROVED', [
            'original_name' => $originalName,
            'original_extension' => $originalExtension,
            'stored_filename' => $fileName,
            'stored_path' => $path,
            'full_path' => $fullPath,
            'file_size' => filesize($fullPath),
            'user_id' => auth()->id(),
            'company_id' => $company->id
        ]);

        try {
            /*
             * El tipo comercial de CUSCAR no se puede deducir de LOC+9/LOC+11:
             * archivos de importación y exportación pueden declarar la misma ruta
             * física. Validarlo antes de encolar evita esperar al worker para
             * descubrir que faltó una decisión obligatoria del operador.
             */
            $detectedParser = $this->parserFactory->getParser($fullPath);

            if (
                $detectedParser instanceof
                    \App\Services\Parsers\CmspEdiParserCompat
                && !in_array(
                    $request->input('operation_type'),
                    ['import', 'export'],
                    true
                )
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'operation_type' =>
                            'Para archivos CUSCAR debe seleccionar '
                            . 'Importación o Exportación.',
                    ]);
            }

            if (
                $targetVoyageId !== null
                && !\App\Jobs\ProcessManifestImportJob::parserSupportsTargetVoyage($detectedParser)
            ) {
                Storage::disk('local')->delete($path);

                return back()->withInput()->withErrors([
                    'target_voyage_id' => 'Este formato todavía no admite agregar archivos a un viaje existente.',
                ]);
            }

            // Registro de seguimiento: existe desde el encolado, lo sigue el spinner.
            // Independiente de si el parser llega a crear su ManifestImport.
            $tracking = \App\Models\ImportTracking::create([
                'company_id'    => $company->id,
                'user_id'       => auth()->id(),
                'vessel_id'     => $vessel->id,
                'original_name' => $originalName,
                'stored_path'   => $path,
                'status'        => \App\Models\ImportTracking::STATUS_QUEUED,
            ]);

            // Encolar: el parseo pesado corre en el worker de la cola 'imports',
            // fuera del request (evita crashes de memoria y no bloquea la web).
            // El Job reautentica, llama al mismo parse() de siempre y actualiza el
            // tracking según el resultado. Los parsers no se tocan.
            \App\Jobs\ProcessManifestImportJob::dispatch(
                $tracking->id,
                $path,
                $vessel->id,
                auth()->id(),
                $originalName,
                $request->input('departure_date'),
                $request->input('voyage_number'),
                $request->input('loading_date'),
                $request->input('discharge_date'),
                $request->input('operation_type'),
                $targetVoyageId,
                $convoyLeaderVesselId
            );

            Log::info('Manifest import encolado', [
                'tracking_id'   => $tracking->id,
                'tracking_uuid' => $tracking->uuid,
                'original_name' => $originalName,
                'stored_path'   => $path,
                'vessel_id'     => $vessel->id,
                'user_id'       => auth()->id(),
                'company_id'    => $company->id,
                'operation_type' => $request->input('operation_type'),
                'target_voyage_id' => $targetVoyageId,
                'convoy_leader_vessel_id' => $convoyLeaderVesselId,
            ]);

            // Pantalla de espera con spinner (polling por uuid hasta que el worker termine).
            return redirect()->route('company.manifests.import.processing', [
                'uuid' => $tracking->uuid,
            ]);
        } catch (Exception $e) {
            Log::error('Critical error during manifest import', [
                'original_name' => $originalName,
                'original_extension' => $originalExtension,
                'stored_path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'compatible_parsers' => $this->getCompatibleParsersForDebugging($fullPath, $originalExtension)
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error crítico durante la importación: ' . $e->getMessage())
                ->with('error_details', [
                    'file' => $originalName,
                    'extension' => $originalExtension,
                    'error_type' => 'critical_error',
                    'suggestion' => $this->generateSuggestionBasedOnExtension($originalExtension)
                ]);
        }
    }

    /**
     * Generar nombre único preservando extensión original - NUEVO
     */
    protected function generateUniqueFileName(string $originalName, string $extension): string
    {
        // Limpiar nombre original para seguridad
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = Str::slug($baseName);
        
        // Si el nombre queda vacío después de limpiar, usar timestamp
        if (empty($safeName)) {
            $safeName = 'manifest_' . now()->format('YmdHis');
        }

        // Generar sufijo único
        $uniqueSuffix = '_' . now()->format('YmdHis') . '_' . Str::random(8);
        
        // Asegurar que la extensión tenga punto
        $cleanExtension = $extension ? '.' . ltrim($extension, '.') : '';
        
        return $safeName . $uniqueSuffix . $cleanExtension;
    }

    /**
     * Obtener parsers compatibles para debugging - NUEVO
     */
    protected function getCompatibleParsersForDebugging(string $filePath, string $originalExtension): array
    {
        try {
            if (file_exists($filePath)) {
                return $this->parserFactory->getCompatibleParsers($filePath);
            }
        } catch (Exception $e) {
            Log::warning('Error getting compatible parsers for debugging', [
                'error' => $e->getMessage()
            ]);
        }

        return ['original_extension' => $originalExtension];
    }

    /**
     * Generar sugerencia basada en extensión - NUEVO
     */
    protected function generateSuggestionBasedOnExtension(string $extension): string
    {
        $suggestions = [
            'dat' => 'Verifique que el archivo .DAT tenga formato KLine válido con registros BLNOREC.',
            'xlsx' => 'Verifique que el archivo Excel tenga el formato esperado de PARANA.',
            'csv' => 'Verifique que el archivo CSV tenga la estructura correcta de Guaraní.',
            'xml' => 'Los archivos XML aún no están soportados (próxima versión).',
            'txt' => 'Verifique que el archivo de texto tenga formato válido.',
            'edi' => 'Los archivos EDI aún no están soportados (próxima versión).',
            '' => 'El archivo no tiene extensión. Asegúrese de que sea un formato soportado (.dat, .xlsx, .csv).'
        ];

        return $suggestions[strtolower($extension)] ?? 
               'Verifique que el archivo tenga un formato soportado: .dat, .xlsx, .csv, .xml, .txt, .edi';
    }

    /**
     * Mostrar historial de importaciones
     */
    public function history(Request $request)
    {
        $company = auth()->user()->company;
        
        if (!$company) {
            return redirect()->route('company.manifests.import.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Obtener importaciones de la empresa con relaciones
        $imports = ManifestImport::forCompany($company->id)
            ->with(['user', 'importTracking', 'voyage', 'voyage.originPort', 'voyage.destinationPort'])
            ->latest()
            ->paginate(20);

        // Viajes de la página con archivos agregados vigentes: se ofrece revertir el viaje completo.
        $voyagesWithAddedImports = ManifestImport::forCompany($company->id)
            ->whereIn('voyage_id', $imports->pluck('voyage_id')->filter()->unique())
            ->whereNull('reverted_at')
            ->whereIn('status', ['completed', 'completed_with_warnings'])
            ->whereNotNull('parser_config->target_voyage')
            ->pluck('voyage_id')
            ->unique()
            ->all();

        return view('company.manifests.import-history', [
            'imports' => $imports,
            'company' => $company,
            'voyagesWithAddedImports' => $voyagesWithAddedImports,
        ]);
    }

    /**
     * Mostrar detalles de una importación específica
     */
    public function showImport(Request $request, ManifestImport $import)
    {
        // Verificar que pertenece a la empresa del usuario
        $company = auth()->user()->company;
        
        if ($import->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver esta importación.');
        }

        // Cargar relaciones necesarias
        $import->load(['user', 'voyage', 'revertedByUser']);

        return view('company.manifests.import-details', [
            'import' => $import,
            'company' => $company,
            'targetVoyage' => $this->targetVoyageReport($import),
        ]);
    }

    /**
     * Manejar resultado de importación y generar respuesta apropiada
     */
    protected function handleImportResult(ManifestParseResult $result, string $fileName): \Illuminate\Http\RedirectResponse
    {
        $stats = $result->getStatsSummary();

        // Preparar datos para el reporte
        $reportData = $this->prepareReportData($result, $fileName, $stats);

        if ($result->isSuccessful() && $result->hasWarnings()) {
            // Importación exitosa con advertencias
            Log::warning('Manifest import completed with warnings', [
                'file_name' => $fileName,
                'stats' => $stats,
                'warnings' => $result->warnings
            ]);

            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('warning', $this->buildWarningMessage($result, $fileName));

        } elseif ($result->isSuccessful()) {
            // Importación completamente exitosa
            Log::info('Manifest import completed successfully', [
                'file_name' => $fileName,
                'stats' => $stats
            ]);

            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('success', $this->buildSuccessMessage($result, $fileName));

        } else {
            // Importación falló
            Log::error('Manifest import failed', [
                'file_name' => $fileName,
                'stats' => $stats,
                'errors' => $result->errors
            ]);

            // Para errores, redirigir al reporte también pero con datos de error
            return redirect()
                ->route('company.manifests.import.report')
                ->with('import_report_data', $reportData)
                ->with('error', 'La importación falló: ' . implode('; ', $result->errors));
        }
    }
    /**
     * Construir mensaje de éxito detallado
     */
    protected function buildSuccessMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "✅ Archivo '{$fileName}' importado exitosamente.";
        
        if ($result->voyage) {
            $message .= " Viaje: {$result->voyage->voyage_number}";
        }
        
        if (!empty($stats)) {
            $details = [];
            if (isset($stats['bills'])) $details[] = "{$stats['bills']} conocimientos";
            if (isset($stats['shipments'])) $details[] = "{$stats['shipments']} cargas";
            if (isset($stats['items'])) $details[] = "{$stats['items']} items";
            
            if (!empty($details)) {
                $message .= " (" . implode(', ', $details) . ")";
            }
        }
        
        return $message;
    }

    /**
     * Construir mensaje de advertencia detallado
     */
    protected function buildWarningMessage(ManifestParseResult $result, string $fileName): string
    {
        $stats = $result->getStatsSummary();
        
        $message = "⚠️ Archivo '{$fileName}' importado con advertencias.";
        
        if ($result->voyage) {
            $message .= " Viaje: {$result->voyage->voyage_number}";
        }
        
        if (!empty($stats)) {
            $details = [];
            if (isset($stats['bills'])) $details[] = "{$stats['bills']} conocimientos";
            if (isset($stats['warnings'])) $details[] = "{$stats['warnings']} advertencias";
            
            if (!empty($details)) {
                $message .= " (" . implode(', ', $details) . ")";
            }
        }
        
        $message .= " Revise los detalles antes de continuar.";
        
        return $message;
    }

    /**
     * Preparar datos para el reporte de importación
     */
    protected function prepareReportData(ManifestParseResult $result, string $fileName, array $stats): array
    {
        $user = auth()->user();
        
        $reportData = [
            'importResult' => [
                'success' => $result->isSuccessful(),
                'hasWarnings' => $result->hasWarnings(),
            ],
            'fileInfo' => [
                'name' => $fileName,
                'imported_at' => now()->format('d/m/Y H:i:s'),
                'imported_by' => $user->name ?? 'Usuario',
            ],
            'stats' => $stats,
            'voyage' => null,
            'createdRecords' => [],
            'warnings' => $result->warnings ?? [],
            'errors' => $result->errors ?? [],
        ];

        // Agregar datos del viaje si existe
        if ($result->voyage) {
            $reportData['voyage'] = [
                'id' => $result->voyage->id,
                'voyage_number' => $result->voyage->voyage_number,
                'status' => $result->voyage->status ?? 'planning',
                'vessel_name' => $result->voyage->vessel->name ?? null,
                'origin_port' => $result->voyage->originPort->name ?? null,
                'destination_port' => $result->voyage->destinationPort->name ?? null,
                'departure_date' => $result->voyage->departure_date ? $result->voyage->departure_date->format('d/m/Y') : null,
            ];
        }

        // Agregar registros creados
        $reportData['createdRecords'] = [
            'billsOfLading' => $this->formatBillsOfLading($result->billsOfLading ?? []),
            'containers' => $this->formatContainers($result->containers ?? []),
        ];

        // Conteos confiables: parsers que vacían los arrays por memoria (ej. Login)
        // exponen los totales en statistics. Si no, se cae al count de los arrays.
        $reportData['counts'] = [
            'billsOfLading' => $result->statistics['bills_of_lading'] ?? count($reportData['createdRecords']['billsOfLading']),
            'containers' => $result->statistics['containers'] ?? count($reportData['createdRecords']['containers']),
            'items' => $result->statistics['items'] ?? $result->statistics['processed_items'] ?? 0,
        ];

        return $reportData;
    }

    /**
     * Formatear datos de Bills of Lading para el reporte
     */
    protected function formatBillsOfLading(array $billsOfLading): array
    {
        $formatted = [];
        
        foreach ($billsOfLading as $bl) {
            $formatted[] = [
                'id' => $bl->id,
                'bl_number' => $bl->bl_number ?? 'N/A',
                'shipper_name' => $bl->shipper->legal_name ?? 'N/A',
                'consignee_name' => $bl->consignee->legal_name ?? 'N/A',
                'items_count' => $bl->shipmentItems ? $bl->shipmentItems->count() : 0,
            ];
        }
        
        return $formatted;
    }

    /**
     * Formatear datos de contenedores para el reporte
     */
    protected function formatContainers(array $containers): array
    {
        $formatted = [];
        
        foreach ($containers as $container) {
            $formatted[] = [
                'id' => $container->id ?? null,
                'container_number' => $container->container_number ?? $container['number'] ?? 'N/A',
                'container_type' => $container->container_type ?? $container['type'] ?? 'N/A',
                'gross_weight' => $container->gross_weight_kg ?? $container['gross_weight'] ?? 0,
            ];
        }
        
        return $formatted;
    }

    /**
     * Pantalla de espera de una importación encolada. Muestra el spinner que
     * hace polling a importStatus() y redirige al reporte cuando termina.
     */
    public function processing(Request $request, string $uuid)
    {
        $company = auth()->user()->company;
        $tracking = \App\Models\ImportTracking::where('uuid', $uuid)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.manifests.import-processing', [
            'uuid'     => $tracking->uuid,
            'fileName' => $tracking->original_name,
        ]);
    }

    /**
     * Estado de una importación encolada (para polling desde la pantalla de espera).
     * Busca el ManifestImport por file_hash creado a partir del encolado ($qt),
     * para no confundirlo con una importación previa del mismo archivo.
     */
    public function importStatus(Request $request, string $uuid)
    {
        $company = auth()->user()->company;

        $tracking = \App\Models\ImportTracking::where('uuid', $uuid)
            ->where('company_id', $company->id)
            ->first();

        if (!$tracking) {
            return response()->json(['state' => 'queued']);
        }

        switch ($tracking->status) {
            case \App\Models\ImportTracking::STATUS_COMPLETED:
            case \App\Models\ImportTracking::STATUS_WARNINGS:
                // Si el parser creó su ManifestImport, redirigimos al reporte;
                // si no (raro en éxito), caemos al historial.
                $redirect = $tracking->manifest_import_id
                    ? route('company.manifests.import.report', ['import' => $tracking->manifest_import_id])
                    : route('company.manifests.import.history');

                return response()->json([
                    'state'        => 'completed',
                    'voyage_id'    => $tracking->voyage_id,
                    'redirect_url' => $redirect,
                ]);

            case \App\Models\ImportTracking::STATUS_FAILED:
                return response()->json([
                    'state'  => 'failed',
                    'errors' => [$tracking->error_message ?? 'La importación falló.'],
                ]);

            default: // queued / processing
                return response()->json([
                    'state' => $tracking->status === \App\Models\ImportTracking::STATUS_PROCESSING
                        ? 'processing'
                        : 'queued',
                ]);
        }
    }

    /**
     * Mostrar reporte detallado de importación
     */
public function showReport(Request $request)
    {
        // Flujo asíncrono: el polling redirige acá con ?import=ID. Se arma el
        // reporte desde el ManifestImport (el worker no puede escribir en sesión).
        if ($request->filled('import')) {
            $company = auth()->user()->company;
            $import = ManifestImport::where('id', $request->query('import'))
                ->where('company_id', $company->id)
                ->first();

            if (!$import) {
                return redirect()->route('company.manifests.import.index')
                    ->with('error', 'No se encontró la importación solicitada.');
            }

            return view('company.manifests.import-report', $this->buildReportDataFromImport($import));
        }

        // Compatibilidad: flujo viejo por sesión flash.
        if (!$request->session()->has('import_report_data')) {
            return redirect()->route('company.manifests.import.index')
                ->with('error', 'No hay datos de importación para mostrar.');
        }
        $reportData = $request->session()->get('import_report_data');

        // Limpiar datos de sesión después de obtenerlos
        $request->session()->forget('import_report_data');
        return view('company.manifests.import-report', $reportData);
    }

    /**
     * Arma los datos del reporte desde un ManifestImport (flujo asíncrono).
     * Usa los conteos y datos ya persistidos por el parser, sin depender de
     * $result. Misma estructura que prepareReportData para reusar la vista.
     */
    protected function buildReportDataFromImport(ManifestImport $import): array
    {
        $import->loadMissing(['user', 'voyage', 'voyage.vessel', 'voyage.originPort', 'voyage.destinationPort']);

        $createdObjectIds = $import->getAllCreatedObjectIds();

        $isSuccess  = in_array($import->status, ['completed', 'completed_with_warnings']);
        $hasWarnings = $import->status === 'completed_with_warnings';

        $reportData = [
            'importResult' => [
                'success'     => $isSuccess,
                'hasWarnings' => $hasWarnings,
            ],
            'fileInfo' => [
                'name'        => $import->display_file_name,
                'imported_at' => optional($import->created_at)->format('d/m/Y H:i:s'),
                'imported_by' => $import->user->name ?? 'Usuario',
            ],
            'stats'    => [],
            'voyage'   => null,
            'createdRecords' => [
                'billsOfLading' => [],
                'containers'    => [],
            ],
            'counts' => [
                'billsOfLading' => count($createdObjectIds['bills']),
                'containers'    => count($createdObjectIds['containers']),
                'items'         => count($createdObjectIds['items']),
            ],
            'warnings' => $import->warnings ?? [],
            'errors'   => $import->errors ?? [],
            'targetVoyage' => $this->targetVoyageReport($import),
        ];

        if ($import->voyage) {
            $reportData['voyage'] = [
                'id'               => $import->voyage->id,
                'voyage_number'    => $import->voyage->voyage_number,
                'status'           => $import->voyage->status ?? 'planning',
                'vessel_name'      => $import->voyage->vessel->name ?? null,
                'origin_port'      => $import->voyage->originPort->name ?? null,
                'destination_port' => $import->voyage->destinationPort->name ?? null,
                'departure_date'   => $import->voyage->departure_date
                    ? $import->voyage->departure_date->format('d/m/Y') : null,
            ];
        }

        return $reportData;
    }

    /**
     * Informe de una importación agregada a un viaje existente: conocimientos
     * salteados por repetidos y conversión a convoy. null si creó su viaje.
     */
    protected function targetVoyageReport(ManifestImport $import): ?array
    {
        $target = $import->parser_config['target_voyage'] ?? null;

        if (!is_array($target)) {
            return null;
        }

        return [
            'skipped_bills' => array_values((array) ($target['skipped_bills'] ?? [])),
            'shipment_created' => (bool) ($target['shipment_created'] ?? false),
            'converted_to_convoy' => isset($import->parser_config['convoy_conversion']),
        ];
    }

    /**
     * Revertir una importación específica
     * Solo permite revertir si el Viaje está en estado 'planning'
     */
    public function revert(Request $request, ManifestImport $import)
    {
        $company = auth()->user()->company;

        if (!$company || $import->company_id !== $company->id) {
            abort(403, 'No tiene permisos para revertir esta importación.');
        }

        try {
            $summary = app(\App\Services\ManifestImportRevertService::class)->revertImport(
                $import,
                auth()->id(),
                auth()->user()->name,
                $request->input('reason', 'Reversión manual')
            );
        } catch (\DomainException $e) {
            return back()->with('error', 'Esta importación no puede ser revertida: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error al revertir importación', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Error al revertir la importación: ' . $e->getMessage());
        }

        $message = empty($summary)
            ? 'Reversión completada - No había objetos para eliminar'
            : 'Importación revertida exitosamente. Se eliminaron: ' . implode(', ', $summary);

        return redirect()->route('company.manifests.import.history')->with('success', $message);
    }

    /**
     * Revierte el viaje completo de la importación: primero los archivos
     * agregados, del más nuevo al más viejo, y al final el que lo creó.
     */
    public function revertVoyage(Request $request, ManifestImport $import)
    {
        $company = auth()->user()->company;

        if (!$company || $import->company_id !== $company->id) {
            abort(403, 'No tiene permisos para revertir este viaje.');
        }

        $voyage = Voyage::where('id', $import->voyage_id)->where('company_id', $company->id)->first();

        if (!$voyage) {
            return back()->with('error', 'El viaje de esta importación ya no existe.');
        }

        try {
            $summaries = app(\App\Services\ManifestImportRevertService::class)->revertVoyage(
                $voyage,
                auth()->id(),
                auth()->user()->name,
                $request->input('reason', 'Reversión del viaje completo')
            );
        } catch (\DomainException $e) {
            return back()->with('error', "El viaje {$voyage->voyage_number} no puede ser revertido: " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error al revertir viaje completo', [
                'import_id' => $import->id,
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Error al revertir el viaje: ' . $e->getMessage());
        }

        return redirect()->route('company.manifests.import.history')->with(
            'success',
            "Viaje {$voyage->voyage_number} revertido: " . count($summaries) . ' importaciones revertidas.'
        );
    }
}