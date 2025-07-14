<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $table = 'document_types';

    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'country_id',
        'validation_pattern',
        'min_length',
        'max_length',
        'has_check_digit',
        'check_digit_algorithm',
        'display_format',
        'input_mask',
        'format_examples',
        'for_individuals',
        'for_companies',
        'for_tax_purposes',
        'for_customs',
        'is_primary',
        'required_for_clients',
        'display_order',
        'active',
        'webservice_field',
        'webservice_config',
        'created_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'format_examples' => 'array',
        'for_individuals' => 'boolean',
        'for_companies' => 'boolean',
        'for_tax_purposes' => 'boolean',
        'for_customs' => 'boolean',
        'is_primary' => 'boolean',
        'required_for_clients' => 'boolean',
        'active' => 'boolean',
        'has_check_digit' => 'boolean',
        'webservice_config' => 'array',
        'created_date' => 'datetime',
        'min_length' => 'integer',
        'max_length' => 'integer',
        'display_order' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Country this document type belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * User who created this record
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Clients using this document type (will be implemented in Phase 1)
     */
    // public function clients(): HasMany
    // {
    //     return $this->hasMany(Client::class);
    // }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active document types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for document types by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope for tax documents (CUIT, RUC)
     */
    public function scopeForTax($query)
    {
        return $query->where('for_tax_purposes', true);
    }

    /**
     * Scope for individual documents
     */
    public function scopeForIndividuals($query)
    {
        return $query->where('for_individuals', true);
    }

    /**
     * Scope for company documents
     */
    public function scopeForCompanies($query)
    {
        return $query->where('for_companies', true);
    }

    /**
     * Scope for customs documents
     */
    public function scopeForCustoms($query)
    {
        return $query->where('for_customs', true);
    }

    /**
     * Scope for primary documents
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for required documents
     */
    public function scopeRequired($query)
    {
        return $query->where('required_for_clients', true);
    }

    /**
     * Scope for ordering by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // ========================================
    // VALIDATION METHODS
    // ========================================

    /**
     * Validate document number format
     */
    public function validateFormat(string $document): bool
    {
        // Remove spaces and special characters for validation
        $cleanDocument = preg_replace('/[^0-9A-Za-z]/', '', $document);

        // Check length constraints
        if ($this->min_length && strlen($cleanDocument) < $this->min_length) {
            return false;
        }

        if ($this->max_length && strlen($cleanDocument) > $this->max_length) {
            return false;
        }

        // Check regex pattern if defined
        if ($this->validation_pattern) {
            return (bool) preg_match('/' . $this->validation_pattern . '/', $document);
        }

        return true;
    }

    /**
     * Validate check digit if applicable
     */
    public function validateCheckDigit(string $document): bool
    {
        if (!$this->has_check_digit || empty($this->check_digit_algorithm)) {
            return true; // No check digit validation required
        }

        $cleanDocument = preg_replace('/[^0-9]/', '', $document);

        return match($this->check_digit_algorithm) {
            'mod11' => $this->validateMod11($cleanDocument),
            'mod10' => $this->validateMod10($cleanDocument),
            'cuit_ar' => $this->validateCuitArgentina($cleanDocument),
            default => true, // Unknown algorithm, skip validation
        };
    }

    /**
     * Full validation (format + check digit)
     */
    public function validate(string $document): array
    {
        $errors = [];

        if (!$this->validateFormat($document)) {
            $errors[] = "Formato inválido para {$this->name}";
        }

        if (!$this->validateCheckDigit($document)) {
            $errors[] = "Dígito verificador inválido para {$this->name}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // ========================================
    // CHECK DIGIT ALGORITHMS
    // ========================================

    /**
     * Validate CUIT Argentina (mod11 algorithm)
     */
    private function validateCuitArgentina(string $cuit): bool
    {
        if (strlen($cuit) !== 11) {
            return false;
        }

        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        // Calculate sum using multipliers
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cuit[$i]) * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = 11 - $remainder;

        if ($checkDigit === 11) {
            $checkDigit = 0;
        } elseif ($checkDigit === 10) {
            return false; // Invalid CUIT
        }

        return intval($cuit[10]) === $checkDigit;
    }

    /**
     * Generic mod11 validation
     */
    private function validateMod11(string $document): bool
    {
        $length = strlen($document);
        if ($length < 2) return false;

        $checkDigit = intval($document[$length - 1]);
        $number = substr($document, 0, $length - 1);

        $sum = 0;
        $multiplier = 2;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += intval($number[$i]) * $multiplier;
            $multiplier = $multiplier === 9 ? 2 : $multiplier + 1;
        }

        $remainder = $sum % 11;
        $expectedDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return $checkDigit === $expectedDigit;
    }

    /**
     * Generic mod10 validation (Luhn algorithm)
     */
    private function validateMod10(string $document): bool
    {
        $length = strlen($document);
        if ($length < 2) return false;

        $checkDigit = intval($document[$length - 1]);
        $number = substr($document, 0, $length - 1);

        $sum = 0;
        $alternate = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }

            $sum += $digit;
            $alternate = !$alternate;
        }

        $expectedDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === $expectedDigit;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Format document according to display format
     */
    public function formatDocument(string $document): string
    {
        if (empty($this->display_format)) {
            return $document;
        }

        $cleanDocument = preg_replace('/[^0-9A-Za-z]/', '', $document);
        $format = $this->display_format;

        // Replace format markers with actual digits
        $formatIndex = 0;
        $result = '';

        for ($i = 0; $i < strlen($format); $i++) {
            if ($format[$i] === '9' && $formatIndex < strlen($cleanDocument)) {
                $result .= $cleanDocument[$formatIndex];
                $formatIndex++;
            } else {
                $result .= $format[$i];
            }
        }

        return $result;
    }

    /**
     * Get random format example
     */
    public function getExampleFormat(): ?string
    {
        if (empty($this->format_examples)) {
            return null;
        }

        return $this->format_examples[array_rand($this->format_examples)];
    }

    /**
     * Check if document type is CUIT
     */
    public function isCuit(): bool
    {
        return $this->code === 'CUIT' && $this->country->alpha2_code === 'AR';
    }

    /**
     * Check if document type is RUC
     */
    public function isRuc(): bool
    {
        return $this->code === 'RUC' && $this->country->alpha2_code === 'PY';
    }

    /**
     * Get webservice field name
     */
    public function getWebserviceField(): string
    {
        return $this->webservice_field ?? strtolower($this->code);
    }

    // ========================================
    // STATIC METHODS
    // ========================================

    /**
     * Get CUIT document type for Argentina
     */
    public static function cuitArgentina(): ?self
    {
        return static::where('code', 'CUIT')
            ->whereHas('country', function($query) {
                $query->where('alpha2_code', 'AR');
            })
            ->first();
    }

    /**
     * Get RUC document type for Paraguay
     */
    public static function rucParaguay(): ?self
    {
        return static::where('code', 'RUC')
            ->whereHas('country', function($query) {
                $query->where('alpha2_code', 'PY');
            })
            ->first();
    }

    /**
     * Get document types for select dropdown by country
     */
    public static function forSelectByCountry(int $countryId): array
    {
        return static::active()
            ->forCountry($countryId)
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get tax document types for select dropdown
     */
    public static function taxDocumentsForSelect(): array
    {
        return static::active()
            ->forTax()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }
}
