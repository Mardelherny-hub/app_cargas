<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ClientCompanyRelation;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Services\ClientValidationService;
use App\Services\TaxIdExtractionService;
use App\Services\ClientSuggestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Exception;

/**
 * FASE 3 - VALIDACIONES Y SERVICIOS
 *
 * Job for processing bulk client data import
 *
 * Handles:
 * - Batch processing of client import data
 * - Automatic CUIT/RUC extraction from mixed content
 * - Duplicate detection and prevention
 * - Data validation and correction suggestions
 * - Client creation with proper relationships
 * - Progress reporting and error handling
 * - Result reporting with detailed statistics
 *
 * Usage:
 * ProcessBulkClientDataJob::dispatch($importData, $companyId, $userId);
 */
class ProcessBulkClientDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Import data array
     */
    public array $importData;

    /**
     * Company ID for client association
     */
    public int $companyId;

    /**
     * User ID who requested the import
     */
    public int $userId;

    /**
     * Import batch ID for tracking
     */
    public string $batchId;

    /**
     * Import options
     */
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Processing statistics
     */
    private array $stats = [
        'total_records' => 0,
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'duplicates_found' => 0,
        'suggestions_applied' => 0,
    ];

    /**
     * Processing results
     */
    private array $results = [
        'successful' => [],
        'errors' => [],
        'duplicates' => [],
        'suggestions' => [],
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $importData,
        int $companyId,
        int $userId,
        string $batchId = null,
        array $options = []
    ) {
        $this->importData = $importData;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->batchId = $batchId ?? uniqid('bulk_import_');
        $this->options = array_merge([
            'skip_duplicates' => true,
            'auto_extract_tax_ids' => true,
            'apply_suggestions' => false,
            'verify_tax_ids' => true,
            'chunk_size' => 100,
        ], $options);

        // Set queue based on data size
        $this->onQueue(count($importData) > 1000 ? 'bulk' : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ClientValidationService $validationService,
        TaxIdExtractionService $extractionService,
        ClientSuggestionService $suggestionService
    ): void {
        try {
            $this->initializeProcessing();

            // Process data in chunks
            $chunks = collect($this->importData)->chunk($this->options['chunk_size']);

            foreach ($chunks as $chunkIndex => $chunk) {
                $this->processChunk($chunk, $chunkIndex, $validationService, $extractionService, $suggestionService);
                $this->updateProgress();
            }

            $this->finalizeProcessing();

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Initialize processing
     */
    private function initializeProcessing(): void
    {
        $this->stats['total_records'] = count($this->importData);

        // Initialize progress tracking
        $this->updateProgressCache([
            'status' => 'processing',
            'started_at' => now()->toISOString(),
            'stats' => $this->stats,
        ]);

        $this->logInfo('Bulk import processing started', [
            'batch_id' => $this->batchId,
            'total_records' => $this->stats['total_records'],
            'options' => $this->options
        ]);
    }

    /**
     * Process a chunk of data
     */
    private function processChunk(
        Collection $chunk,
        int $chunkIndex,
        ClientValidationService $validationService,
        TaxIdExtractionService $extractionService,
        ClientSuggestionService $suggestionService
    ): void {
        $this->logInfo("Processing chunk {$chunkIndex}", [
            'chunk_size' => $chunk->count()
        ]);

        foreach ($chunk as $recordIndex => $record) {
            try {
                $this->processRecord(
                    $record,
                    $recordIndex,
                    $validationService,
                    $extractionService,
                    $suggestionService
                );
                $this->stats['processed']++;
            } catch (Exception $e) {
                $this->handleRecordError($record, $recordIndex, $e);
            }
        }
    }

    /**
     * Process a single record
     */
    private function processRecord(
        array $record,
        int $recordIndex,
        ClientValidationService $validationService,
        TaxIdExtractionService $extractionService,
        ClientSuggestionService $suggestionService
    ): void {
        // Step 1: Extract and normalize data
        $clientData = $this->extractClientData($record, $extractionService);

        if (empty($clientData['tax_id'])) {
            throw new Exception('No tax ID found in record');
        }

        // Step 2: Check for duplicates
        if ($this->options['skip_duplicates']) {
            $duplicates = $suggestionService->suggestDuplicates($clientData);
            if ($duplicates->isNotEmpty()) {
                $this->handleDuplicateFound($record, $recordIndex, $duplicates);
                return;
            }
        }

        // Step 3: Validate data
        $validation = $validationService->validateClientData($clientData);
        if (!$validation['valid']) {
            throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
        }

        // Step 4: Apply suggestions if enabled
        if ($this->options['apply_suggestions']) {
            $suggestions = $suggestionService->suggestCorrections($clientData);
            if ($suggestions->isNotEmpty()) {
                $clientData = $this->applySuggestions($clientData, $suggestions);
                $this->stats['suggestions_applied']++;
            }
        }

        // Step 5: Create or update client
        $client = $this->createOrUpdateClient($clientData, $recordIndex);

        // Step 6: Verify tax ID if enabled
        if ($this->options['verify_tax_ids'] && $client->wasRecentlyCreated) {
            VerifyClientTaxIdJob::verifyClient($client->id, false, $this->userId, $this->companyId);
        }

        $this->handleSuccessfulRecord($record, $recordIndex, $client);
    }

    /**
     * Extract client data from record
     */
    private function extractClientData(array $record, TaxIdExtractionService $extractionService): array
    {
        $clientData = [
            'tax_id' => null,
            'legal_name' => null,
            'country_id' => null,
            'document_type_id' => null,
            'client_type' => 'consignee',
            'primary_port_id' => null,
            'customs_offices_id' => null,
            'status' => 'active',
            'created_by_company_id' => $this->companyId,
            'notes' => null,
        ];

        // Extract tax ID
        if ($this->options['auto_extract_tax_ids']) {
            $extracted = $extractionService->extractFromMixedContent(
                json_encode($record),
                $this->guessCountryFromRecord($record)
            );

            if ($extracted->isNotEmpty()) {
                $bestMatch = $extracted->first();
                $clientData['tax_id'] = $bestMatch['tax_id'];
                $clientData['country_id'] = $this->getCountryIdFromCode($bestMatch['country_code']);
            }
        }

        // Extract other fields from record
        $fieldMappings = [
            'tax_id' => ['tax_id', 'cuit', 'ruc', 'documento_fiscal', 'documento'],
            'legal_name' => ['legal_name', 'razon_social', 'nombre', 'company_name', 'empresa'],
            'client_type' => ['client_type', 'tipo_cliente', 'tipo', 'role'],
            'notes' => ['notes', 'observaciones', 'comentarios', 'notas'],
        ];

        foreach ($fieldMappings as $field => $possibleKeys) {
            foreach ($possibleKeys as $key) {
                if (isset($record[$key]) && !empty($record[$key])) {
                    $clientData[$field] = $record[$key];
                    break;
                }
            }
        }

        // Ensure we have required fields
        if (empty($clientData['tax_id']) && isset($record['tax_id'])) {
            $clientData['tax_id'] = $record['tax_id'];
        }

        if (empty($clientData['legal_name']) && isset($record['name'])) {
            $clientData['legal_name'] = $record['name'];
        }

        // Set country and document type if not already set
        if (empty($clientData['country_id'])) {
            $clientData['country_id'] = $this->guessCountryIdFromRecord($record);
        }

        if (empty($clientData['document_type_id']) && $clientData['country_id']) {
            $clientData['document_type_id'] = $this->getDefaultDocumentTypeForCountry($clientData['country_id']);
        }

        return $clientData;
    }

    /**
     * Create or update client
     */
    private function createOrUpdateClient(array $clientData, int $recordIndex): Client
    {
        DB::beginTransaction();

        try {
            // Try to find existing client
            $existingClient = Client::where('tax_id', $clientData['tax_id'])
                ->where('country_id', $clientData['country_id'])
                ->first();

            if ($existingClient) {
                // Update existing client
                $existingClient->update(array_filter($clientData, function ($value) {
                    return $value !== null;
                }));
                $client = $existingClient;
                $this->stats['updated']++;
            } else {
                // Create new client
                $client = Client::create($clientData);
                $this->stats['created']++;
            }

            // Create or update company relation
            ClientCompanyRelation::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'company_id' => $this->companyId,
                ],
                [
                    'relation_type' => 'customer',
                    'can_edit' => true,
                    'active' => true,
                    'created_by_user_id' => $this->userId,
                    'last_activity_at' => now(),
                ]
            );

            DB::commit();
            return $client;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle duplicate found
     */
    private function handleDuplicateFound(array $record, int $recordIndex, Collection $duplicates): void
    {
        $this->stats['duplicates_found']++;
        $this->stats['skipped']++;

        $this->results['duplicates'][] = [
            'record_index' => $recordIndex,
            'record' => $record,
            'duplicates' => $duplicates->toArray(),
        ];

        $this->logInfo('Duplicate found and skipped', [
            'record_index' => $recordIndex,
            'duplicates_count' => $duplicates->count()
        ]);
    }

    /**
     * Apply suggestions to client data
     */
    private function applySuggestions(array $clientData, Collection $suggestions): array
    {
        foreach ($suggestions as $suggestion) {
            if ($suggestion['confidence'] > 0.8) {
                $clientData[$suggestion['field']] = $suggestion['suggestion'];
            }
        }

        return $clientData;
    }

    /**
     * Handle successful record processing
     */
    private function handleSuccessfulRecord(array $record, int $recordIndex, Client $client): void
    {
        $this->results['successful'][] = [
            'record_index' => $recordIndex,
            'record' => $record,
            'client_id' => $client->id,
            'tax_id' => $client->tax_id,
            'legal_name' => $client->legal_name,
            'action' => $client->wasRecentlyCreated ? 'created' : 'updated',
        ];
    }

    /**
     * Handle record processing error
     */
    private function handleRecordError(array $record, int $recordIndex, Exception $e): void
    {
        $this->stats['errors']++;
        $this->stats['processed']++;

        $this->results['errors'][] = [
            'record_index' => $recordIndex,
            'record' => $record,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];

        $this->logError('Record processing failed', [
            'record_index' => $recordIndex,
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Update progress tracking
     */
    private function updateProgress(): void
    {
        $this->updateProgressCache([
            'status' => 'processing',
            'stats' => $this->stats,
            'progress_percentage' => round(($this->stats['processed'] / $this->stats['total_records']) * 100, 2),
            'updated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Finalize processing
     */
    private function finalizeProcessing(): void
    {
        $finalReport = [
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'stats' => $this->stats,
            'results' => $this->results,
            'completed_at' => now()->toISOString(),
        ];

        // Cache final report
        Cache::put("bulk_import_report_{$this->batchId}", $finalReport, 3600 * 24); // 24 hours

        // Update progress to completed
        $this->updateProgressCache([
            'status' => 'completed',
            'stats' => $this->stats,
            'progress_percentage' => 100,
            'completed_at' => now()->toISOString(),
        ]);

        $this->logSuccess('Bulk import completed successfully', [
            'batch_id' => $this->batchId,
            'stats' => $this->stats
        ]);

        // Trigger completion event
        // event(new BulkImportCompleted($finalReport));
    }

    /**
     * Update progress cache
     */
    private function updateProgressCache(array $data): void
    {
        $cacheKey = "bulk_import_progress_{$this->batchId}";
        Cache::put($cacheKey, array_merge([
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'job_id' => $this->job?->getJobId(),
        ], $data), 3600); // 1 hour
    }

    /**
     * Handle job exception
     */
    private function handleException(Exception $e): void
    {
        $this->logError('Bulk import job failed', [
            'batch_id' => $this->batchId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts()
        ]);

        // Update progress to failed
        $this->updateProgressCache([
            'status' => 'failed',
            'stats' => $this->stats,
            'error' => $e->getMessage(),
            'failed_at' => now()->toISOString(),
        ]);

        throw $e;
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception $exception): void
    {
        $this->logError('Bulk import job permanently failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'stats' => $this->stats
        ]);

        // Update progress to failed
        $this->updateProgressCache([
            'status' => 'failed',
            'stats' => $this->stats,
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'bulk_import',
            'batch:' . $this->batchId,
            'company:' . $this->companyId,
            'user:' . $this->userId,
        ];
    }

    // =====================================================
    // HELPER METHODS
    // =====================================================

    /**
     * Guess country from record
     */
    private function guessCountryFromRecord(array $record): ?string
    {
        $countryFields = ['country', 'pais', 'country_code', 'codigo_pais'];

        foreach ($countryFields as $field) {
            if (isset($record[$field])) {
                $value = strtoupper($record[$field]);
                if (in_array($value, ['AR', 'ARG', 'ARGENTINA'])) {
                    return 'AR';
                }
                if (in_array($value, ['PY', 'PRY', 'PARAGUAY'])) {
                    return 'PY';
                }
            }
        }

        return null;
    }

    /**
     * Get country ID from ISO code
     */
    private function getCountryIdFromCode(string $code): ?int
    {
        $country = Country::where('iso_code', $code)->first();
        return $country?->id;
    }

    /**
     * Guess country ID from record
     */
    private function guessCountryIdFromRecord(array $record): ?int
    {
        $countryCode = $this->guessCountryFromRecord($record);
        return $countryCode ? $this->getCountryIdFromCode($countryCode) : null;
    }

    /**
     * Get default document type for country
     */
    private function getDefaultDocumentTypeForCountry(int $countryId): ?int
    {
        $docType = DocumentType::where('country_id', $countryId)
            ->where('is_default', true)
            ->first();

        return $docType?->id;
    }

    /**
     * Log info message
     */
    private function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => 'ProcessBulkClientDataJob',
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'job_id' => $this->job?->getJobId(),
        ], $context));
    }

    /**
     * Log error message
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge([
            'job' => 'ProcessBulkClientDataJob',
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'job_id' => $this->job?->getJobId(),
        ], $context));
    }

    /**
     * Log success message
     */
    private function logSuccess(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => 'ProcessBulkClientDataJob',
            'batch_id' => $this->batchId,
            'company_id' => $this->companyId,
            'job_id' => $this->job?->getJobId(),
            'status' => 'success'
        ], $context));
    }

    // =====================================================
    // STATIC HELPER METHODS
    // =====================================================

    /**
     * Process bulk import
     */
    public static function processBulkImport(
        array $importData,
        int $companyId,
        int $userId,
        array $options = []
    ): string {
        $batchId = uniqid('bulk_import_');

        self::dispatch($importData, $companyId, $userId, $batchId, $options);

        return $batchId;
    }

    /**
     * Get import progress
     */
    public static function getImportProgress(string $batchId): ?array
    {
        $cacheKey = "bulk_import_progress_{$batchId}";
        return Cache::get($cacheKey);
    }

    /**
     * Get import report
     */
    public static function getImportReport(string $batchId): ?array
    {
        $cacheKey = "bulk_import_report_{$batchId}";
        return Cache::get($cacheKey);
    }

    /**
     * Cancel import
     */
    public static function cancelImport(string $batchId): bool
    {
        // This would require job cancellation logic
        // For now, just mark as cancelled in cache
        $cacheKey = "bulk_import_progress_{$batchId}";
        $progress = Cache::get($cacheKey);

        if ($progress) {
            $progress['status'] = 'cancelled';
            $progress['cancelled_at'] = now()->toISOString();
            Cache::put($cacheKey, $progress, 3600);
            return true;
        }

        return false;
    }
}
