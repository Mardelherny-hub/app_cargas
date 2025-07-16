<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCompanyRelation;
use App\Models\Country;
use App\Models\Company;
use App\Services\ClientValidationService;
use App\Services\TaxIdExtractionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 2 - SISTEMA DE DATOS VARIABLES
 *
 * Service for providing intelligent client suggestions
 *
 * Handles:
 * - Existing client suggestions during data entry
 * - Duplicate detection and prevention
 * - Partial tax ID completion suggestions
 * - Name-based client matching
 * - Bulk import assistance
 * - Data correction suggestions
 *
 * Used for autocomplete, duplicate prevention, and data quality improvement
 */
class ClientSuggestionService
{
    private ClientValidationService $validationService;
    private TaxIdExtractionService $extractionService;

    /**
     * Similarity threshold for string matching (0-1)
     */
    private const SIMILARITY_THRESHOLD = 0.7;

    /**
     * Maximum number of suggestions to return
     */
    private const MAX_SUGGESTIONS = 10;

    /**
     * Minimum query length for suggestions
     */
    private const MIN_QUERY_LENGTH = 3;

    public function __construct(
        ClientValidationService $validationService,
        TaxIdExtractionService $extractionService
    ) {
        $this->validationService = $validationService;
        $this->extractionService = $extractionService;
    }

    // =====================================================
    // MÉTODOS PRINCIPALES DE SUGERENCIAS
    // =====================================================

    /**
     * Suggest existing clients based on partial input
     *
     * @param string $query Search query (tax ID, name, etc.)
     * @param int|null $companyId Company context
     * @param int $limit Maximum suggestions
     * @return Collection Collection of client suggestions
     */
    public function suggestExistingClients(
        string $query,
        ?int $companyId = null,
        int $limit = self::MAX_SUGGESTIONS
    ): Collection {
        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $suggestions = collect();

        // Search by tax ID (highest priority)
        $taxIdSuggestions = $this->searchByTaxId($query, $companyId);
        $suggestions = $suggestions->merge($taxIdSuggestions);

        // Search by legal name
        $nameSuggestions = $this->searchByLegalName($query, $companyId);
        $suggestions = $suggestions->merge($nameSuggestions);

        // Search by extracted tax IDs from query
        $extractedSuggestions = $this->searchByExtractedTaxIds($query, $companyId);
        $suggestions = $suggestions->merge($extractedSuggestions);

        // Remove duplicates, calculate scores, and sort
        return $suggestions
            ->unique('client_id')
            ->map(function ($suggestion) {
                $suggestion['score'] = $this->calculateSuggestionScore($suggestion);
                return $suggestion;
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Detect potential duplicate clients
     *
     * @param array $clientData Client data to check
     * @param int|null $excludeId Client ID to exclude from duplicates
     * @return Collection Collection of potential duplicates
     */
    public function suggestDuplicates(
        array $clientData,
        ?int $excludeId = null
    ): Collection {
        $duplicates = collect();

        // Check by exact tax ID
        if (!empty($clientData['tax_id'])) {
            $exactMatches = $this->findExactTaxIdMatches(
                $clientData['tax_id'],
                $excludeId
            );
            $duplicates = $duplicates->merge($exactMatches);
        }

        // Check by similar legal name
        if (!empty($clientData['business_name'])) {
            $nameMatches = $this->findSimilarNameMatches(
                $clientData['business_name'],
                $clientData['country_id'] ?? null,
                $excludeId
            );
            $duplicates = $duplicates->merge($nameMatches);
        }

        // Check by normalized tax ID variations
        if (!empty($clientData['tax_id'])) {
            $normalizedMatches = $this->findNormalizedTaxIdMatches(
                $clientData['tax_id'],
                $excludeId
            );
            $duplicates = $duplicates->merge($normalizedMatches);
        }

        return $duplicates
            ->unique('client_id')
            ->sortByDesc('confidence')
            ->values();
    }

    /**
     * Suggest completion for partial tax ID
     *
     * @param string $partialTaxId Partial tax ID
     * @param string|null $countryCode Country hint
     * @param int|null $companyId Company context
     * @return Collection Collection of completion suggestions
     */
    public function suggestFromPartialTaxId(
        string $partialTaxId,
        ?string $countryCode = null,
        ?int $companyId = null
    ): Collection {
        $suggestions = collect();

        // Clean partial input
        $cleanPartial = preg_replace('/[^\d]/', '', $partialTaxId);

        if (strlen($cleanPartial) < 2) {
            return $suggestions;
        }

        // Search existing clients with matching prefixes
        $query = Client::query()
            ->where('tax_id', 'LIKE', $cleanPartial . '%')
            ->where('status', 'active')
            ->with(['country', 'createdByCompany']);

        // Filter by country if provided
        if ($countryCode) {
            $query->whereHas('country', function ($q) use ($countryCode) {
                $q->where('iso_code', $countryCode);
            });
        }

        // Filter by company access if provided
        if ($companyId) {
            $query->whereHas('companyRelations', function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->where('active', true);
            });
        }

        $matches = $query->limit(self::MAX_SUGGESTIONS)->get();

        foreach ($matches as $client) {
            $suggestions->push([
                'client_id' => $client->id,
                'tax_id' => $client->tax_id,
                'formatted_tax_id' => $client->getFormattedTaxId(),
                'business_name' => $client->business_name,
                'country_name' => $client->country->name,
                'country_code' => $client->country->iso_code,
                'client_type' => $client->client_type,
                'suggestion_type' => 'partial_tax_id',
                'match_length' => strlen($cleanPartial),
                'confidence' => $this->calculatePartialMatchConfidence($cleanPartial, $client->tax_id),
            ]);
        }

        return $suggestions->sortByDesc('confidence');
    }

    /**
     * Suggest clients by name similarity
     *
     * @param string $name Name to search
     * @param int|null $companyId Company context
     * @return Collection Collection of name-based suggestions
     */
    public function suggestFromName(
        string $name,
        ?int $companyId = null
    ): Collection {
        if (strlen($name) < self::MIN_QUERY_LENGTH) {
            return collect();
        }

        $suggestions = collect();
        $normalizedName = $this->normalizeName($name);

        // Exact name matches
        $exactMatches = $this->findExactNameMatches($name, $companyId);
        $suggestions = $suggestions->merge($exactMatches);

        // Similar name matches
        $similarMatches = $this->findSimilarNameMatches($name, null, null, $companyId);
        $suggestions = $suggestions->merge($similarMatches);

        // Fuzzy name matches
        $fuzzyMatches = $this->findFuzzyNameMatches($normalizedName, $companyId);
        $suggestions = $suggestions->merge($fuzzyMatches);

        return $suggestions
            ->unique('client_id')
            ->sortByDesc('confidence')
            ->take(self::MAX_SUGGESTIONS)
            ->values();
    }

    /**
     * Provide suggestions for bulk import data
     *
     * @param array $importData Array of import records
     * @param int|null $companyId Company context
     * @return Collection Collection of bulk import suggestions
     */
    public function suggestForBulkImport(
        array $importData,
        ?int $companyId = null
    ): Collection {
        $suggestions = collect();

        foreach ($importData as $index => $record) {
            $recordSuggestions = collect();

            // Extract tax IDs from record
            $extractedTaxIds = $this->extractionService->extractFromMixedContent(
                json_encode($record),
                $this->guessCountryFromRecord($record)
            );

            foreach ($extractedTaxIds as $taxIdData) {
                $clientSuggestions = $this->suggestExistingClients(
                    $taxIdData['tax_id'],
                    $companyId,
                    5
                );
                $recordSuggestions = $recordSuggestions->merge($clientSuggestions);
            }

            // Search by name if available
            if (!empty($record['name']) || !empty($record['business_name'])) {
                $nameField = $record['name'] ?? $record['business_name'];
                $nameSuggestions = $this->suggestFromName($nameField, $companyId);
                $recordSuggestions = $recordSuggestions->merge($nameSuggestions);
            }

            if ($recordSuggestions->isNotEmpty()) {
                $suggestions->push([
                    'import_index' => $index,
                    'import_record' => $record,
                    'suggestions' => $recordSuggestions->take(3)->toArray(),
                    'has_strong_match' => $recordSuggestions->where('confidence', '>', 0.8)->isNotEmpty(),
                ]);
            }
        }

        return $suggestions;
    }

    /**
     * Suggest corrections for client data
     *
     * @param array $clientData Client data to correct
     * @return Collection Collection of correction suggestions
     */
    public function suggestCorrections(array $clientData): Collection
    {
        $corrections = collect();

        // Tax ID corrections
        if (!empty($clientData['tax_id'])) {
            $taxIdCorrections = $this->extractionService->suggestCorrections(
                $clientData['tax_id'],
                $this->getCountryCodeFromData($clientData)
            );

            foreach ($taxIdCorrections as $correction) {
                $corrections->push([
                    'field' => 'tax_id',
                    'original' => $clientData['tax_id'],
                    'suggestion' => $correction['suggestion'],
                    'type' => 'tax_id_correction',
                    'confidence' => $correction['confidence'],
                    'reason' => $correction['type'],
                ]);
            }
        }

        // Legal name corrections
        if (!empty($clientData['business_name'])) {
            $nameCorrections = $this->suggestNameCorrections($clientData['business_name']);
            $corrections = $corrections->merge($nameCorrections);
        }

        // Country/document type consistency
        if (!empty($clientData['country_id']) && !empty($clientData['document_type_id'])) {
            $consistencyCorrections = $this->suggestConsistencyCorrections(
                $clientData['country_id'],
                $clientData['document_type_id']
            );
            $corrections = $corrections->merge($consistencyCorrections);
        }

        return $corrections->sortByDesc('confidence');
    }

    // =====================================================
    // MÉTODOS DE BÚSQUEDA ESPECÍFICOS
    // =====================================================

    /**
     * Search clients by tax ID
     */
    private function searchByTaxId(string $query, ?int $companyId = null): Collection
    {
        $cleanQuery = preg_replace('/[^\d]/', '', $query);

        if (strlen($cleanQuery) < 2) {
            return collect();
        }

        $clients = Client::query()
            ->where('tax_id', 'LIKE', '%' . $cleanQuery . '%')
            ->where('status', 'active')
            ->with(['country', 'createdByCompany'])
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                    $subQ->where('company_id', $companyId)->where('active', true);
                });
            })
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        return $clients->map(function ($client) use ($query) {
            return [
                'client_id' => $client->id,
                'tax_id' => $client->tax_id,
                'formatted_tax_id' => $client->getFormattedTaxId(),
                'business_name' => $client->business_name,
                'country_name' => $client->country->name,
                'country_code' => $client->country->iso_code,
                'client_type' => $client->client_type,
                'suggestion_type' => 'tax_id_match',
                'match_text' => $query,
                'confidence' => $this->calculateTaxIdMatchConfidence($query, $client->tax_id),
            ];
        });
    }

    /**
     * Search clients by legal name
     */
    private function searchByLegalName(string $query, ?int $companyId = null): Collection
    {
        $clients = Client::query()
            ->where('business_name', 'LIKE', '%' . $query . '%')
            ->where('status', 'active')
            ->with(['country', 'createdByCompany'])
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                    $subQ->where('company_id', $companyId)->where('active', true);
                });
            })
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        return $clients->map(function ($client) use ($query) {
            return [
                'client_id' => $client->id,
                'tax_id' => $client->tax_id,
                'formatted_tax_id' => $client->getFormattedTaxId(),
                'business_name' => $client->business_name,
                'country_name' => $client->country->name,
                'country_code' => $client->country->iso_code,
                'client_type' => $client->client_type,
                'suggestion_type' => 'name_match',
                'match_text' => $query,
                'confidence' => $this->calculateNameMatchConfidence($query, $client->business_name),
            ];
        });
    }

    /**
     * Search clients by extracted tax IDs
     */
    private function searchByExtractedTaxIds(string $query, ?int $companyId = null): Collection
    {
        $extractedTaxIds = $this->extractionService->extractFromText($query);
        $suggestions = collect();

        foreach ($extractedTaxIds as $taxIdData) {
            $clients = Client::query()
                ->where('tax_id', $taxIdData['tax_id'])
                ->where('status', 'active')
                ->with(['country', 'createdByCompany'])
                ->when($companyId, function ($q) use ($companyId) {
                    $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                        $subQ->where('company_id', $companyId)->where('active', true);
                    });
                })
                ->get();

            foreach ($clients as $client) {
                $suggestions->push([
                    'client_id' => $client->id,
                    'tax_id' => $client->tax_id,
                    'formatted_tax_id' => $client->getFormattedTaxId(),
                    'business_name' => $client->business_name,
                    'country_name' => $client->country->name,
                    'country_code' => $client->country->iso_code,
                    'client_type' => $client->client_type,
                    'suggestion_type' => 'extracted_tax_id',
                    'match_text' => $taxIdData['original_text'],
                    'confidence' => $taxIdData['confidence'],
                ]);
            }
        }

        return $suggestions;
    }

    // =====================================================
    // MÉTODOS DE DETECCIÓN DE DUPLICADOS
    // =====================================================

    /**
     * Find exact tax ID matches
     */
    private function findExactTaxIdMatches(string $taxId, ?int $excludeId = null): Collection
    {
        $clients = Client::query()
            ->where('tax_id', $taxId)
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->with(['country', 'createdByCompany'])
            ->get();

        return $clients->map(function ($client) {
            return [
                'client_id' => $client->id,
                'tax_id' => $client->tax_id,
                'business_name' => $client->business_name,
                'country_name' => $client->country->name,
                'duplicate_type' => 'exact_tax_id',
                'confidence' => 1.0,
            ];
        });
    }

    /**
     * Find similar name matches
     */
    private function findSimilarNameMatches(
        string $name,
        ?int $countryId = null,
        ?int $excludeId = null,
        ?int $companyId = null
    ): Collection {
        $normalizedName = $this->normalizeName($name);
        $suggestions = collect();

        // Get potential matches
        $query = Client::query()
            ->where('status', 'active')
            ->when($countryId, function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            })
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                    $subQ->where('company_id', $companyId)->where('active', true);
                });
            })
            ->with(['country', 'createdByCompany']);

        $clients = $query->get();

        foreach ($clients as $client) {
            $normalizedClientName = $this->normalizeName($client->business_name);
            $similarity = $this->calculateNameSimilarity($normalizedName, $normalizedClientName);

            if ($similarity >= self::SIMILARITY_THRESHOLD) {
                $suggestions->push([
                    'client_id' => $client->id,
                    'tax_id' => $client->tax_id,
                    'business_name' => $client->business_name,
                    'country_name' => $client->country->name,
                    'duplicate_type' => 'similar_name',
                    'confidence' => $similarity,
                ]);
            }
        }

        return $suggestions;
    }

    /**
     * Find normalized tax ID matches (different formats)
     */
    private function findNormalizedTaxIdMatches(string $taxId, ?int $excludeId = null): Collection
    {
        $normalizedTaxId = preg_replace('/[^\d]/', '', $taxId);
        $suggestions = collect();

        $clients = Client::query()
            ->where('status', 'active')
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->with(['country', 'createdByCompany'])
            ->get();

        foreach ($clients as $client) {
            $normalizedClientTaxId = preg_replace('/[^\d]/', '', $client->tax_id);

            if ($normalizedTaxId === $normalizedClientTaxId) {
                $suggestions->push([
                    'client_id' => $client->id,
                    'tax_id' => $client->tax_id,
                    'business_name' => $client->business_name,
                    'country_name' => $client->country->name,
                    'duplicate_type' => 'normalized_tax_id',
                    'confidence' => 0.9,
                ]);
            }
        }

        return $suggestions;
    }

    // =====================================================
    // MÉTODOS DE BÚSQUEDA POR NOMBRE
    // =====================================================

    /**
     * Find exact name matches
     */
    private function findExactNameMatches(string $name, ?int $companyId = null): Collection
    {
        $clients = Client::query()
            ->where('business_name', $name)
            ->where('status', 'active')
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                    $subQ->where('company_id', $companyId)->where('active', true);
                });
            })
            ->with(['country', 'createdByCompany'])
            ->get();

        return $clients->map(function ($client) {
            return [
                'client_id' => $client->id,
                'tax_id' => $client->tax_id,
                'business_name' => $client->business_name,
                'country_name' => $client->country->name,
                'suggestion_type' => 'exact_name',
                'confidence' => 1.0,
            ];
        });
    }

    /**
     * Find fuzzy name matches
     */
    private function findFuzzyNameMatches(string $normalizedName, ?int $companyId = null): Collection
    {
        $suggestions = collect();

        // Use database functions for fuzzy matching
        $clients = Client::query()
            ->where('status', 'active')
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('companyRelations', function ($subQ) use ($companyId) {
                    $subQ->where('company_id', $companyId)->where('active', true);
                });
            })
            ->with(['country', 'createdByCompany'])
            ->get();

        foreach ($clients as $client) {
            $normalizedClientName = $this->normalizeName($client->business_name);
            $similarity = $this->calculateNameSimilarity($normalizedName, $normalizedClientName);

            if ($similarity >= self::SIMILARITY_THRESHOLD) {
                $suggestions->push([
                    'client_id' => $client->id,
                    'tax_id' => $client->tax_id,
                    'business_name' => $client->business_name,
                    'country_name' => $client->country->name,
                    'suggestion_type' => 'fuzzy_name',
                    'confidence' => $similarity,
                ]);
            }
        }

        return $suggestions;
    }

    // =====================================================
    // MÉTODOS DE CORRECCIÓN
    // =====================================================

    /**
     * Suggest name corrections
     */
    private function suggestNameCorrections(string $name): Collection
    {
        $corrections = collect();

        // Common abbreviations and expansions
        $abbreviations = [
            'S.A.' => 'SOCIEDAD ANONIMA',
            'S.R.L.' => 'SOCIEDAD DE RESPONSABILIDAD LIMITADA',
            'LTDA.' => 'LIMITADA',
            'CIA.' => 'COMPAÑIA',
            'CORP.' => 'CORPORACION',
            'INC.' => 'INCORPORATED',
        ];

        foreach ($abbreviations as $abbr => $full) {
            if (stripos($name, $abbr) !== false) {
                $corrected = str_ireplace($abbr, $full, $name);
                $corrections->push([
                    'field' => 'business_name',
                    'original' => $name,
                    'suggestion' => $corrected,
                    'type' => 'name_expansion',
                    'confidence' => 0.8,
                    'reason' => 'abbreviation_expansion',
                ]);
            }
        }

        // Title case correction
        if ($name === strtoupper($name)) {
            $titleCase = ucwords(strtolower($name));
            $corrections->push([
                'field' => 'business_name',
                'original' => $name,
                'suggestion' => $titleCase,
                'type' => 'case_correction',
                'confidence' => 0.7,
                'reason' => 'title_case_conversion',
            ]);
        }

        return $corrections;
    }

    /**
     * Suggest consistency corrections
     */
    private function suggestConsistencyCorrections(int $countryId, int $documentTypeId): Collection
    {
        $corrections = collect();

        // Validate document type belongs to country
        $validation = $this->validationService->validateDocumentsTypeForCountry(
            $documentTypeId,
            $countryId
        );

        if (!$validation['valid']) {
            $corrections->push([
                'field' => 'document_type_id',
                'original' => $documentTypeId,
                'suggestion' => null,
                'type' => 'consistency_error',
                'confidence' => 1.0,
                'reason' => $validation['message'],
            ]);
        }

        return $corrections;
    }

    // =====================================================
    // MÉTODOS AUXILIARES
    // =====================================================

    /**
     * Calculate suggestion score
     */
    private function calculateSuggestionScore(array $suggestion): float
    {
        $baseScore = $suggestion['confidence'] ?? 0.5;

        // Boost score based on suggestion type
        $typeBoosts = [
            'exact_tax_id' => 1.0,
            'tax_id_match' => 0.9,
            'extracted_tax_id' => 0.8,
            'exact_name' => 0.7,
            'name_match' => 0.6,
            'fuzzy_name' => 0.5,
        ];

        $typeBoost = $typeBoosts[$suggestion['suggestion_type']] ?? 0.5;

        return min($baseScore * $typeBoost, 1.0);
    }

    /**
     * Calculate partial match confidence
     */
    private function calculatePartialMatchConfidence(string $partial, string $full): float
    {
        $partialLength = strlen($partial);
        $fullLength = strlen($full);

        if ($partialLength === 0 || $fullLength === 0) {
            return 0.0;
        }

        $matchRatio = $partialLength / $fullLength;
        $prefixMatch = stripos($full, $partial) === 0 ? 1.0 : 0.5;

        return min($matchRatio * $prefixMatch, 1.0);
    }

    /**
     * Calculate tax ID match confidence
     */
    private function calculateTaxIdMatchConfidence(string $query, string $taxId): float
    {
        $cleanQuery = preg_replace('/[^\d]/', '', $query);
        $cleanTaxId = preg_replace('/[^\d]/', '', $taxId);

        if (strlen($cleanQuery) === 0) {
            return 0.0;
        }

        if ($cleanQuery === $cleanTaxId) {
            return 1.0;
        }

        $similarity = similar_text($cleanQuery, $cleanTaxId, $percentage);
        return $percentage / 100;
    }

    /**
     * Calculate name match confidence
     */
    private function calculateNameMatchConfidence(string $query, string $name): float
    {
        $normalizedQuery = $this->normalizeName($query);
        $normalizedName = $this->normalizeName($name);

        return $this->calculateNameSimilarity($normalizedQuery, $normalizedName);
    }

    /**
     * Calculate name similarity
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        if (empty($name1) || empty($name2)) {
            return 0.0;
        }

        // Exact match
        if ($name1 === $name2) {
            return 1.0;
        }

        // Similarity using similar_text
        similar_text($name1, $name2, $percentage);
        $similarity = $percentage / 100;

        // Boost for substring matches
        if (stripos($name1, $name2) !== false || stripos($name2, $name1) !== false) {
            $similarity = min($similarity + 0.2, 1.0);
        }

        return $similarity;
    }

    /**
     * Normalize name for comparison
     */
    private function normalizeName(string $name): string
    {
        $normalized = strtoupper($name);
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Guess country from import record
     */
    private function guessCountryFromRecord(array $record): ?string
    {
        // Look for country indicators in the record
        $countryIndicators = [
            'country' => null,
            'pais' => null,
            'country_code' => null,
        ];

        foreach ($countryIndicators as $field => $value) {
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
     * Get country code from client data
     */
    private function getCountryCodeFromData(array $clientData): ?string
    {
        if (isset($clientData['country_id'])) {
            $country = Country::find($clientData['country_id']);
            return $country?->iso_code;
        }

        return null;
    }
}
