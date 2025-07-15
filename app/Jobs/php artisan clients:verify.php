<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\ClientValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * FASE 3 - VALIDACIONES Y SERVICIOS
 *
 * Job for asynchronous client tax ID verification
 *
 * Handles:
 * - Verification of CUITs/RUCs against official registries
 * - Automatic update of verification status
 * - Error handling and retry logic
 * - Batch processing capabilities
 * - Logging and auditing
 *
 * Usage:
 * VerifyClientTaxIdJob::dispatch($clientId);
 * VerifyClientTaxIdJob::dispatch($clientId, $forceRevalidation = true);
 */
class VerifyClientTaxIdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Client ID to verify
     */
    public int $clientId;

    /**
     * Force revalidation even if already verified
     */
    public bool $forceRevalidation;

    /**
     * User who requested the verification
     */
    public ?int $requestedByUserId;

    /**
     * Company context for verification
     */
    public ?int $companyId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // 10 seconds, 30 seconds, 1 minute
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $clientId,
        bool $forceRevalidation = false,
        ?int $requestedByUserId = null,
        ?int $companyId = null
    ) {
        $this->clientId = $clientId;
        $this->forceRevalidation = $forceRevalidation;
        $this->requestedByUserId = $requestedByUserId;
        $this->companyId = $companyId;

        // Set queue name based on priority
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(ClientValidationService $validationService): void
    {
        try {
            // Load client with country relationship
            $client = Client::with('country')->find($this->clientId);

            if (!$client) {
                $this->logError('Client not found', ['client_id' => $this->clientId]);
                $this->fail(new Exception("Client with ID {$this->clientId} not found"));
                return;
            }

            // Check if already verified and not forcing revalidation
            if ($client->verified_at && !$this->forceRevalidation) {
                $this->logInfo('Client already verified, skipping', [
                    'client_id' => $this->clientId,
                    'verified_at' => $client->verified_at
                ]);
                return;
            }

            // Mark verification as in progress
            $this->markVerificationInProgress($client);

            // Perform validation
            $validationResult = $this->performValidation($client, $validationService);

            // Process validation result
            $this->processValidationResult($client, $validationResult);

            // Clear verification progress
            $this->clearVerificationProgress($client);

            $this->logSuccess('Client verification completed', [
                'client_id' => $this->clientId,
                'tax_id' => $client->tax_id,
                'valid' => $validationResult['valid']
            ]);

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Perform the actual validation
     */
    private function performValidation(Client $client, ClientValidationService $validationService): array
    {
        $this->logInfo('Starting tax ID validation', [
            'client_id' => $client->id,
            'tax_id' => $client->tax_id,
            'country' => $client->country->iso_code
        ]);

        // Validate tax ID format and check digit
        $formatValidation = $validationService->validateTaxIdForCountry(
            $client->tax_id,
            $client->country->iso_code
        );

        if (!$formatValidation['valid']) {
            return [
                'valid' => false,
                'source' => 'format_validation',
                'message' => $formatValidation['message'],
                'details' => $formatValidation
            ];
        }

        // Try to verify with official registry (if available)
        $registryValidation = $this->verifyWithOfficialRegistry($client, $validationService);

        if ($registryValidation !== null) {
            return [
                'valid' => $registryValidation['valid'],
                'source' => 'official_registry',
                'message' => $registryValidation['message'] ?? 'Verified against official registry',
                'details' => $registryValidation
            ];
        }

        // Fallback to format validation only
        return [
            'valid' => true,
            'source' => 'format_validation',
            'message' => 'Tax ID format is valid',
            'details' => $formatValidation
        ];
    }

    /**
     * Try to verify with official registry
     */
    private function verifyWithOfficialRegistry(Client $client, ClientValidationService $validationService): ?array
    {
        try {
            // Check if we have API credentials/configuration for this country
            if (!$this->hasOfficialRegistryAccess($client->country->iso_code)) {
                return null;
            }

            // This would call external API - placeholder for now
            // In a real implementation, you'd call AFIP API for Argentina or similar for Paraguay
            $this->logInfo('Attempting official registry verification', [
                'client_id' => $client->id,
                'country' => $client->country->iso_code
            ]);

            // Simulate API call based on country
            switch ($client->country->iso_code) {
                case 'AR':
                    return $this->verifyWithAFIP($client->tax_id);
                case 'PY':
                    return $this->verifyWithSetRUC($client->tax_id);
                default:
                    return null;
            }

        } catch (Exception $e) {
            $this->logError('Official registry verification failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
            return null; // Fallback to format validation
        }
    }

    /**
     * Verify with AFIP (Argentina)
     */
    private function verifyWithAFIP(string $cuit): ?array
    {
        // Placeholder for AFIP API integration
        // In real implementation, you'd call AFIP webservice

        $this->logInfo('AFIP verification attempted', ['cuit' => $cuit]);

        // For now, return null to indicate no official verification available
        return null;

        // Real implementation would be something like:
        /*
        $afipService = app(AFIPService::class);
        $response = $afipService->verifyCUIT($cuit);

        return [
            'valid' => $response['exists'],
            'message' => $response['message'],
            'registry_data' => $response['data']
        ];
        */
    }

    /**
     * Verify with SET RUC (Paraguay)
     */
    private function verifyWithSetRUC(string $ruc): ?array
    {
        // Placeholder for SET RUC API integration
        // In real implementation, you'd call SET webservice

        $this->logInfo('SET RUC verification attempted', ['ruc' => $ruc]);

        // For now, return null to indicate no official verification available
        return null;

        // Real implementation would be something like:
        /*
        $setService = app(SETService::class);
        $response = $setService->verifyRUC($ruc);

        return [
            'valid' => $response['active'],
            'message' => $response['message'],
            'registry_data' => $response['data']
        ];
        */
    }

    /**
     * Check if we have official registry access for country
     */
    private function hasOfficialRegistryAccess(string $countryCode): bool
    {
        // Check configuration for official registry API access
        switch ($countryCode) {
            case 'AR':
                return config('services.afip.enabled', false) &&
                       config('services.afip.api_key') !== null;
            case 'PY':
                return config('services.set.enabled', false) &&
                       config('services.set.api_key') !== null;
            default:
                return false;
        }
    }

    /**
     * Process validation result and update client
     */
    private function processValidationResult(Client $client, array $validationResult): void
    {
        $updateData = [];

        if ($validationResult['valid']) {
            // Mark as verified
            $updateData['verified_at'] = now();

            // Store additional verification data if available
            if (isset($validationResult['details']['registry_data'])) {
                $notes = $client->notes ?? '';
                $verificationNote = "Verified via {$validationResult['source']} on " . now()->format('Y-m-d H:i:s');
                $updateData['notes'] = $notes ? $notes . "\n" . $verificationNote : $verificationNote;
            }
        } else {
            // Clear verification if it was invalid
            $updateData['verified_at'] = null;

            // Add error note
            $notes = $client->notes ?? '';
            $errorNote = "Verification failed ({$validationResult['source']}): {$validationResult['message']} on " . now()->format('Y-m-d H:i:s');
            $updateData['notes'] = $notes ? $notes . "\n" . $errorNote : $errorNote;
        }

        // Update client
        $client->update($updateData);

        // Log the result
        $this->logInfo('Client verification status updated', [
            'client_id' => $client->id,
            'verified' => $validationResult['valid'],
            'source' => $validationResult['source']
        ]);

        // Cache the verification result
        $this->cacheVerificationResult($client, $validationResult);

        // Trigger events if needed
        $this->triggerVerificationEvents($client, $validationResult);
    }

    /**
     * Mark verification as in progress
     */
    private function markVerificationInProgress(Client $client): void
    {
        $cacheKey = "client_verification_progress_{$client->id}";
        Cache::put($cacheKey, [
            'status' => 'in_progress',
            'started_at' => now()->toISOString(),
            'job_id' => $this->job?->getJobId(),
            'requested_by' => $this->requestedByUserId,
            'company_id' => $this->companyId
        ], 300); // 5 minutes timeout
    }

    /**
     * Clear verification progress
     */
    private function clearVerificationProgress(Client $client): void
    {
        $cacheKey = "client_verification_progress_{$client->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Cache verification result
     */
    private function cacheVerificationResult(Client $client, array $validationResult): void
    {
        $cacheKey = "client_verification_result_{$client->id}";
        Cache::put($cacheKey, [
            'client_id' => $client->id,
            'tax_id' => $client->tax_id,
            'valid' => $validationResult['valid'],
            'source' => $validationResult['source'],
            'message' => $validationResult['message'],
            'verified_at' => $validationResult['valid'] ? now()->toISOString() : null,
            'cached_at' => now()->toISOString()
        ], 3600); // 1 hour cache
    }

    /**
     * Trigger verification events
     */
    private function triggerVerificationEvents(Client $client, array $validationResult): void
    {
        // Trigger events based on verification result
        if ($validationResult['valid']) {
            // event(new ClientVerified($client, $validationResult));
        } else {
            // event(new ClientVerificationFailed($client, $validationResult));
        }
    }

    /**
     * Handle exceptions during job execution
     */
    private function handleException(Exception $e): void
    {
        $this->logError('Job execution failed', [
            'client_id' => $this->clientId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempt' => $this->attempts()
        ]);

        // Clear progress on failure
        if ($this->clientId) {
            $cacheKey = "client_verification_progress_{$this->clientId}";
            Cache::forget($cacheKey);
        }

        // If this is the last attempt, mark as failed
        if ($this->attempts() >= $this->tries) {
            $this->markVerificationFailed($e);
        }

        // Re-throw to trigger retry logic
        throw $e;
    }

    /**
     * Mark verification as failed
     */
    private function markVerificationFailed(Exception $e): void
    {
        try {
            $client = Client::find($this->clientId);
            if ($client) {
                $notes = $client->notes ?? '';
                $errorNote = "Verification failed with error: {$e->getMessage()} on " . now()->format('Y-m-d H:i:s');
                $client->update([
                    'verified_at' => null,
                    'notes' => $notes ? $notes . "\n" . $errorNote : $errorNote
                ]);
            }
        } catch (Exception $updateException) {
            $this->logError('Failed to mark verification as failed', [
                'client_id' => $this->clientId,
                'error' => $updateException->getMessage()
            ]);
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception $exception): void
    {
        $this->logError('Job permanently failed', [
            'client_id' => $this->clientId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Clean up any progress indicators
        if ($this->clientId) {
            $cacheKey = "client_verification_progress_{$this->clientId}";
            Cache::forget($cacheKey);
        }

        // Notify relevant parties about the failure
        // $this->notifyFailure($exception);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'client_verification',
            'client:' . $this->clientId,
            'company:' . ($this->companyId ?? 'none'),
            'user:' . ($this->requestedByUserId ?? 'system')
        ];
    }

    /**
     * Log info message
     */
    private function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => 'VerifyClientTaxIdJob',
            'client_id' => $this->clientId,
            'job_id' => $this->job?->getJobId(),
        ], $context));
    }

    /**
     * Log error message
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge([
            'job' => 'VerifyClientTaxIdJob',
            'client_id' => $this->clientId,
            'job_id' => $this->job?->getJobId(),
        ], $context));
    }

    /**
     * Log success message
     */
    private function logSuccess(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => 'VerifyClientTaxIdJob',
            'client_id' => $this->clientId,
            'job_id' => $this->job?->getJobId(),
            'status' => 'success'
        ], $context));
    }

    // =====================================================
    // STATIC HELPER METHODS
    // =====================================================

    /**
     * Dispatch verification for a single client
     */
    public static function verifyClient(
        int $clientId,
        bool $forceRevalidation = false,
        ?int $requestedByUserId = null,
        ?int $companyId = null
    ): \Illuminate\Foundation\Bus\PendingDispatch {
        return self::dispatch($clientId, $forceRevalidation, $requestedByUserId, $companyId);
    }

    /**
     * Dispatch verification for multiple clients
     */
    public static function verifyClients(
        array $clientIds,
        bool $forceRevalidation = false,
        ?int $requestedByUserId = null,
        ?int $companyId = null
    ): void {
        foreach ($clientIds as $clientId) {
            self::verifyClient($clientId, $forceRevalidation, $requestedByUserId, $companyId);
        }
    }

    /**
     * Check if client verification is in progress
     */
    public static function isVerificationInProgress(int $clientId): bool
    {
        $cacheKey = "client_verification_progress_{$clientId}";
        return Cache::has($cacheKey);
    }

    /**
     * Get verification progress status
     */
    public static function getVerificationStatus(int $clientId): ?array
    {
        $cacheKey = "client_verification_progress_{$clientId}";
        return Cache::get($cacheKey);
    }

    /**
     * Get cached verification result
     */
    public static function getCachedResult(int $clientId): ?array
    {
        $cacheKey = "client_verification_result_{$clientId}";
        return Cache::get($cacheKey);
    }
}
