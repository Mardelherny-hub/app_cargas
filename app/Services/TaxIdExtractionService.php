<?php

namespace App\Services;

use App\Services\ClientValidationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Service for extracting tax IDs (CUIT/RUC) from text and addresses
 *
 * Handles automatic recognition of tax identification numbers from:
 * - Free text documents
 * - Address strings
 * - Mixed content with embedded tax IDs
 * - Malformed or partial tax IDs with correction suggestions
 *
 * Used for bulk import and data recognition processes
 */
class TaxIdExtractionService
{
    private ClientValidationService $validationService;

    /**
     * CUIT patterns for different formats
     */
    private const CUIT_PATTERNS = [
        // Standard format: 20-12345678-9
        '/\b(20|23|24|27|30|33|34)[-\s]?(\d{8})[-\s]?(\d)\b/',
        // No separators: 20123456789
        '/\b(20|23|24|27|30|33|34)(\d{8})(\d)\b/',
        // Alternative separators
        '/\b(20|23|24|27|30|33|34)[.\/_](\d{8})[.\/_](\d)\b/',
        // With spaces
        '/\b(20|23|24|27|30|33|34)\s+(\d{8})\s+(\d)\b/',
    ];

    /**
     * RUC patterns for Paraguay
     */
    private const RUC_PATTERNS = [
        // Standard format: 1234567-8
        '/\b(\d{7,8})[-\s]?(\d)\b/',
        // With RUC prefix
        '/RUC[\s:]*(\d{7,8})[-\s]?(\d)\b/i',
        // In parentheses
        '/\((\d{7,8})[-\s]?(\d)\)/',
    ];

    /**
     * Context patterns that might contain tax IDs
     */
    private const CONTEXT_PATTERNS = [
        'cuit' => [
            '/CUIT[\s:]*([0-9\-\s\.\/]+)/i',
            '/C\.U\.I\.T[\s:]*([0-9\-\s\.\/]+)/i',
            '/Nº\s*CUIT[\s:]*([0-9\-\s\.\/]+)/i',
        ],
        'ruc' => [
            '/RUC[\s:]*([0-9\-\s\.\/]+)/i',
            '/R\.U\.C[\s:]*([0-9\-\s\.\/]+)/i',
            '/Nº\s*RUC[\s:]*([0-9\-\s\.\/]+)/i',
        ],
        'generic' => [
            '/(?:CUIT|RUC)[\s:]*([0-9\-\s\.\/]+)/i',
            '/Documento[\s:]*([0-9\-\s\.\/]+)/i',
            '/Identificación[\s:]*([0-9\-\s\.\/]+)/i',
        ]
    ];

    /**
     * Common malformed patterns and their corrections
     */
    private const CORRECTION_PATTERNS = [
        // Missing check digits
        '/\b(20|23|24|27|30|33|34)(\d{8})\b/' => '$1$2X', // X will be calculated
        // Wrong separators
        '/\b(\d{2})[\.\/](\d{8})[\.\/](\d)\b/' => '$1-$2-$3',
        // Extra spaces
        '/\b(\d{2})\s+(\d{8})\s+(\d)\b/' => '$1$2$3',
    ];

    public function __construct(ClientValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Extract all possible tax IDs from text
     *
     * @param string $text Text to analyze
     * @param string|null $countryHint Country hint ('AR' or 'PY')
     * @return Collection Collection of extraction results
     */
    public function extractFromText(string $text, ?string $countryHint = null): Collection
    {
        $results = collect();

        // Extract by country type
        if (!$countryHint || $countryHint === 'AR') {
            $results = $results->merge($this->extractCUITs($text));
        }

        if (!$countryHint || $countryHint === 'PY') {
            $results = $results->merge($this->extractRUCs($text));
        }

        // Try context-based extraction
        $contextResults = $this->extractFromContext($text, $countryHint);
        $results = $results->merge($contextResults);

        // Remove duplicates and sort by confidence
        return $results->unique('tax_id')->sortByDesc('confidence');
    }

    /**
     * Extract tax IDs from address strings
     *
     * @param string $address Address string
     * @param string|null $countryHint Country hint
     * @return Collection Collection of extraction results
     */
    public function extractFromAddress(string $address, ?string $countryHint = null): Collection
    {
        // Addresses sometimes contain tax IDs in various formats
        // Example: "Av. Corrientes 1234, CUIT: 20-12345678-9, CABA"

        $results = collect();

        // First try standard extraction
        $standardResults = $this->extractFromText($address, $countryHint);
        $results = $results->merge($standardResults);

        // Try extracting numbers that might be tax IDs
        $numberResults = $this->extractPotentialTaxIdsFromNumbers($address, $countryHint);
        $results = $results->merge($numberResults);

        // Mark as address context
        return $results->map(function ($result) {
            $result['context'] = 'address';
            $result['confidence'] *= 0.8; // Lower confidence for address context
            return $result;
        });
    }

    /**
     * Extract tax IDs from mixed content (multiple lines, structured data)
     *
     * @param string $content Mixed content
     * @param string|null $countryHint Country hint
     * @return Collection Collection of extraction results
     */
    public function extractFromMixedContent(string $content, ?string $countryHint = null): Collection
    {
        $results = collect();

        // Split by lines and process each
        $lines = preg_split('/[\r\n]+/', $content);

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') continue;

            $lineResults = $this->extractFromText($line, $countryHint);

            // Add line context
            $lineResults = $lineResults->map(function ($result) use ($lineNumber, $line) {
                $result['line_number'] = $lineNumber + 1;
                $result['line_content'] = trim($line);
                $result['context'] = 'mixed_content';
                return $result;
            });

            $results = $results->merge($lineResults);
        }

        return $results->unique('tax_id')->sortByDesc('confidence');
    }

    /**
     * Suggest corrections for malformed tax IDs
     *
     * @param string $malformedTaxId Malformed tax ID
     * @param string|null $countryHint Country hint
     * @return array Correction suggestions
     */
    public function suggestCorrections(string $malformedTaxId, ?string $countryHint = null): array
    {
        $suggestions = [];

        // Clean the input
        $cleaned = preg_replace('/[^\d\-]/', '', $malformedTaxId);

        // Try automatic corrections
        foreach (self::CORRECTION_PATTERNS as $pattern => $replacement) {
            $corrected = preg_replace($pattern, $replacement, $cleaned);
            if ($corrected !== $cleaned) {
                $suggestions[] = [
                    'original' => $malformedTaxId,
                    'suggestion' => $corrected,
                    'type' => 'format_correction',
                    'confidence' => 0.7
                ];
            }
        }

        // Try completing missing check digits
        if ($countryHint === 'AR' || !$countryHint) {
            $cuitSuggestions = $this->suggestCUITCompletions($cleaned);
            $suggestions = array_merge($suggestions, $cuitSuggestions);
        }

        if ($countryHint === 'PY' || !$countryHint) {
            $rucSuggestions = $this->suggestRUCCompletions($cleaned);
            $suggestions = array_merge($suggestions, $rucSuggestions);
        }

        return $suggestions;
    }

    /**
     * Extract potential client suggestions based on found tax IDs
     *
     * @param string $text Text to analyze
     * @param string|null $countryHint Country hint
     * @return Collection Collection of client suggestions
     */
    public function extractClientSuggestions(string $text, ?string $countryHint = null): Collection
    {
        $taxIds = $this->extractFromText($text, $countryHint);
        $suggestions = collect();

        foreach ($taxIds as $taxIdData) {
            // Look for existing clients
            $existingClients = \App\Models\Client::where('tax_id', $taxIdData['tax_id'])->get();

            foreach ($existingClients as $client) {
                $suggestions->push([
                    'client_id' => $client->id,
                    'legal_name' => $client->legal_name,
                    'tax_id' => $client->tax_id,
                    'country' => $client->country->name,
                    'found_in_text' => $taxIdData['original_text'],
                    'confidence' => $taxIdData['confidence'],
                    'match_type' => 'existing_client'
                ]);
            }

            // If no existing client, suggest creating new one
            if ($existingClients->isEmpty()) {
                $suggestions->push([
                    'client_id' => null,
                    'legal_name' => null,
                    'tax_id' => $taxIdData['tax_id'],
                    'country' => $this->guessCountryFromTaxId($taxIdData['tax_id']),
                    'found_in_text' => $taxIdData['original_text'],
                    'confidence' => $taxIdData['confidence'],
                    'match_type' => 'new_client_suggestion'
                ]);
            }
        }

        return $suggestions->sortByDesc('confidence');
    }

    /**
     * Extract Argentine CUITs from text
     *
     * @param string $text Text to analyze
     * @return Collection Collection of CUIT results
     */
    private function extractCUITs(string $text): Collection
    {
        $results = collect();

        foreach (self::CUIT_PATTERNS as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $prefix = $match[1][0];
                $middle = $match[2][0];
                $checkDigit = $match[3][0];
                $position = $match[0][1];

                $cuit = $prefix . $middle . $checkDigit;

                // Validate the CUIT
                $validation = $this->validationService->validateArgentineCUIT($cuit);

                $results->push([
                    'tax_id' => $cuit,
                    'formatted_tax_id' => $validation['formatted'] ?? $cuit,
                    'country_code' => 'AR',
                    'original_text' => $fullMatch,
                    'position' => $position,
                    'valid' => $validation['valid'],
                    'confidence' => $validation['valid'] ? 0.95 : 0.5,
                    'extraction_method' => 'pattern_matching',
                    'validation_message' => $validation['message']
                ]);
            }
        }

        return $results;
    }

    /**
     * Extract Paraguayan RUCs from text
     *
     * @param string $text Text to analyze
     * @return Collection Collection of RUC results
     */
    private function extractRUCs(string $text): Collection
    {
        $results = collect();

        foreach (self::RUC_PATTERNS as $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $baseNumber = $match[1][0];
                $checkDigit = $match[2][0];
                $position = $match[0][1];

                // Ensure minimum length for RUC
                if (strlen($baseNumber) < 7) continue;

                $ruc = $baseNumber . $checkDigit;

                // Validate the RUC
                $validation = $this->validationService->validateParaguayanRUC($ruc);

                $results->push([
                    'tax_id' => $ruc,
                    'formatted_tax_id' => $validation['formatted'] ?? $ruc,
                    'country_code' => 'PY',
                    'original_text' => $fullMatch,
                    'position' => $position,
                    'valid' => $validation['valid'],
                    'confidence' => $validation['valid'] ? 0.9 : 0.4,
                    'extraction_method' => 'pattern_matching',
                    'validation_message' => $validation['message']
                ]);
            }
        }

        return $results;
    }

    /**
     * Extract tax IDs using context patterns
     *
     * @param string $text Text to analyze
     * @param string|null $countryHint Country hint
     * @return Collection Collection of context-based results
     */
    private function extractFromContext(string $text, ?string $countryHint = null): Collection
    {
        $results = collect();

        // Determine which context patterns to use
        $patternsToUse = ['generic'];
        if ($countryHint === 'AR') $patternsToUse[] = 'cuit';
        if ($countryHint === 'PY') $patternsToUse[] = 'ruc';
        if (!$countryHint) $patternsToUse = array_merge($patternsToUse, ['cuit', 'ruc']);

        foreach ($patternsToUse as $contextType) {
            if (!isset(self::CONTEXT_PATTERNS[$contextType])) continue;

            foreach (self::CONTEXT_PATTERNS[$contextType] as $pattern) {
                preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

                foreach ($matches as $match) {
                    $fullMatch = $match[0][0];
                    $taxIdCandidate = $match[1][0];
                    $position = $match[0][1];

                    // Clean and extract potential tax IDs from the candidate
                    $cleaned = preg_replace('/[^\d]/', '', $taxIdCandidate);

                    if (strlen($cleaned) >= 8) {
                        $subResults = $this->extractFromText($taxIdCandidate, $countryHint);

                        $subResults = $subResults->map(function ($result) use ($fullMatch, $position) {
                            $result['original_text'] = $fullMatch;
                            $result['position'] = $position;
                            $result['extraction_method'] = 'context_matching';
                            $result['confidence'] *= 1.1; // Boost confidence for context matches
                            return $result;
                        });

                        $results = $results->merge($subResults);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extract potential tax IDs from number sequences
     *
     * @param string $text Text to analyze
     * @param string|null $countryHint Country hint
     * @return Collection Collection of potential results
     */
    private function extractPotentialTaxIdsFromNumbers(string $text, ?string $countryHint = null): Collection
    {
        $results = collect();

        // Find sequences of 11 digits (potential CUITs)
        if (!$countryHint || $countryHint === 'AR') {
            preg_match_all('/\b(\d{11})\b/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $candidate = $match[1][0];
                $position = $match[0][1];

                // Check if it starts with valid CUIT prefix
                $prefix = substr($candidate, 0, 2);
                if (in_array($prefix, ['20', '23', '24', '27', '30', '33', '34'])) {
                    $validation = $this->validationService->validateArgentineCUIT($candidate);

                    $results->push([
                        'tax_id' => $candidate,
                        'formatted_tax_id' => $validation['formatted'] ?? $candidate,
                        'country_code' => 'AR',
                        'original_text' => $candidate,
                        'position' => $position,
                        'valid' => $validation['valid'],
                        'confidence' => $validation['valid'] ? 0.7 : 0.3,
                        'extraction_method' => 'number_sequence',
                        'validation_message' => $validation['message']
                    ]);
                }
            }
        }

        // Find sequences of 8-9 digits (potential RUCs)
        if (!$countryHint || $countryHint === 'PY') {
            preg_match_all('/\b(\d{8,9})\b/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $candidate = $match[1][0];
                $position = $match[0][1];

                $validation = $this->validationService->validateParaguayanRUC($candidate);

                if ($validation['valid']) {
                    $results->push([
                        'tax_id' => $candidate,
                        'formatted_tax_id' => $validation['formatted'],
                        'country_code' => 'PY',
                        'original_text' => $candidate,
                        'position' => $position,
                        'valid' => true,
                        'confidence' => 0.6,
                        'extraction_method' => 'number_sequence',
                        'validation_message' => $validation['message']
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Suggest CUIT completions for partial numbers
     *
     * @param string $partial Partial CUIT
     * @return array Array of suggestions
     */
    private function suggestCUITCompletions(string $partial): array
    {
        $suggestions = [];
        $cleaned = preg_replace('/[^\d]/', '', $partial);

        // If we have 10 digits, calculate check digit
        if (strlen($cleaned) === 10) {
            $prefix = substr($cleaned, 0, 2);
            if (in_array($prefix, ['20', '23', '24', '27', '30', '33', '34'])) {
                // Calculate check digit
                $sum = 0;
                $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

                for ($i = 0; $i < 10; $i++) {
                    $sum += (int) $cleaned[$i] * $multipliers[$i];
                }

                $remainder = $sum % 11;
                $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

                $completeCuit = $cleaned . $checkDigit;

                $suggestions[] = [
                    'original' => $partial,
                    'suggestion' => $completeCuit,
                    'type' => 'check_digit_completion',
                    'confidence' => 0.9
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Suggest RUC completions for partial numbers
     *
     * @param string $partial Partial RUC
     * @return array Array of suggestions
     */
    private function suggestRUCCompletions(string $partial): array
    {
        $suggestions = [];
        $cleaned = preg_replace('/[^\d]/', '', $partial);

        // If we have 7-8 digits, calculate check digit
        if (strlen($cleaned) >= 7 && strlen($cleaned) <= 8) {
            $baseNumber = str_pad($cleaned, 7, '0', STR_PAD_LEFT);

            // Calculate check digit
            $sum = 0;
            $multipliers = [2, 3, 4, 5, 6, 7, 2];

            for ($i = 0; $i < 7; $i++) {
                $sum += (int) $baseNumber[$i] * $multipliers[$i];
            }

            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

            $completeRuc = $baseNumber . $checkDigit;

            $suggestions[] = [
                'original' => $partial,
                'suggestion' => $completeRuc,
                'type' => 'check_digit_completion',
                'confidence' => 0.8
            ];
        }

        return $suggestions;
    }

    /**
     * Guess country from tax ID format
     *
     * @param string $taxId Tax ID
     * @return string|null Country code
     */
    private function guessCountryFromTaxId(string $taxId): ?string
    {
        $cleaned = preg_replace('/[^\d]/', '', $taxId);

        // CUIT patterns (11 digits, specific prefixes)
        if (strlen($cleaned) === 11) {
            $prefix = substr($cleaned, 0, 2);
            if (in_array($prefix, ['20', '23', '24', '27', '30', '33', '34'])) {
                return 'AR';
            }
        }

        // RUC patterns (7-9 digits)
        if (strlen($cleaned) >= 7 && strlen($cleaned) <= 9) {
            return 'PY';
        }

        return null;
    }
}
