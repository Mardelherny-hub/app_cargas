<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\CustomOffice;
use App\Models\Port;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for validating client data
 *
 * Handles validation of tax IDs (CUIT/RUC), document types,
 * customs offices, ports and business rules according to country
 *
 * Designed to prevent webservice errors and ensure data integrity
 */
class ClientValidationService
{
    /**
     * CUIT validation multipliers for Argentina
     */
    private const CUIT_MULTIPLIERS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /**
     * RUC validation multipliers for Paraguay
     */
    private const RUC_MULTIPLIERS = [2, 3, 4, 5, 6, 7, 2];

    /**
     * Valid CUIT prefixes for Argentina
     */
    private const VALID_CUIT_PREFIXES = ['20', '23', '24', '27', '30', '33', '34'];

    /**
     * Cache duration for validation results (1 hour)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Validate complete client data
     *
     * @param array $data Client data to validate
     * @return array Validation result with errors
     */
    public function validateClientData(array $data): array
    {
        $errors = [];

        // Validate required fields
        $requiredFields = ['tax_id', 'country_id', 'legal_name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "El campo {$field} es obligatorio";
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        // Get country for context
        $country = Country::find($data['country_id']);
        if (!$country) {
            $errors['country_id'] = 'País no válido';
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate tax ID format and check digit
        $taxIdValidation = $this->validateTaxIdForCountry($data['tax_id'], $country->iso_code);
        if (!$taxIdValidation['valid']) {
            $errors['tax_id'] = $taxIdValidation['message'];
        }

        // Validate document type for country
        if (!empty($data['document_type_id'])) {
            $docTypeValidation = $this->validateDocumentsTypeForCountry($data['document_type_id'], $data['country_id']);
            if (!$docTypeValidation['valid']) {
                $errors['document_type_id'] = $docTypeValidation['message'];
            }
        }

        // Validate customs office for country
        if (!empty($data['$customs_offices'])) {
            $customsValidation = $this->validateCustomOfficeForCountry($data['$customs_offices'], $data['country_id']);
            if (!$customsValidation['valid']) {
                $errors['$customs_offices'] = $customsValidation['message'];
            }
        }

        // Validate port for country
        if (!empty($data['primary_port_id'])) {
            $portValidation = $this->validatePortForCountry($data['primary_port_id'], $data['country_id']);
            if (!$portValidation['valid']) {
                $errors['primary_port_id'] = $portValidation['message'];
            }
        }

        // Validate client type
        if (!empty($data['client_type'])) {
            $clientTypeValidation = $this->validateClientType($data['client_type']);
            if (!$clientTypeValidation['valid']) {
                $errors['client_type'] = $clientTypeValidation['message'];
            }
        }

        // Check for existing client with same tax_id + country
        if (!empty($data['tax_id']) && !empty($data['country_id'])) {
            $existingValidation = $this->validateUniqueClientIdentification(
                $data['tax_id'],
                $data['country_id'],
                $data['id'] ?? null
            );
            if (!$existingValidation['valid']) {
                $errors['tax_id'] = $existingValidation['message'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'country_code' => $country->iso_code,
            'formatted_tax_id' => $this->formatTaxId($data['tax_id'], $country->iso_code)
        ];
    }

    /**
     * Validate tax ID format according to country
     *
     * @param string $taxId Tax identification number
     * @param string $countryCode Country ISO code (AR/PY)
     * @return array Validation result
     */
    public function validateTaxIdForCountry(string $taxId, string $countryCode): array
    {
        $cacheKey = "tax_validation_{$countryCode}_{$taxId}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($taxId, $countryCode) {
            switch (strtoupper($countryCode)) {
                case 'AR':
                    return $this->validateArgentineCUIT($taxId);
                case 'PY':
                    return $this->validateParaguayanRUC($taxId);
                default:
                    return [
                        'valid' => false,
                        'message' => 'País no soportado para validación de documento fiscal'
                    ];
            }
        });
    }

    /**
     * Validate Argentine CUIT with mod11 algorithm
     *
     * @param string $cuit CUIT to validate
     * @return array Validation result
     */
    public function validateArgentineCUIT(string $cuit): array
    {
        // Remove any formatting
        $cleanCuit = preg_replace('/[^0-9]/', '', $cuit);

        // Check length
        if (strlen($cleanCuit) !== 11) {
            return [
                'valid' => false,
                'message' => 'El CUIT debe tener exactamente 11 dígitos'
            ];
        }

        // Check prefix
        $prefix = substr($cleanCuit, 0, 2);
        if (!in_array($prefix, self::VALID_CUIT_PREFIXES)) {
            return [
                'valid' => false,
                'message' => 'Prefijo de CUIT no válido. Debe comenzar con: ' . implode(', ', self::VALID_CUIT_PREFIXES)
            ];
        }

        // Validate check digit using mod11 algorithm
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cleanCuit[$i] * self::CUIT_MULTIPLIERS[$i];
        }

        $remainder = $sum % 11;
        $expectedCheckDigit = $remainder < 2 ? $remainder : 11 - $remainder;
        $actualCheckDigit = (int) $cleanCuit[10];

        if ($expectedCheckDigit !== $actualCheckDigit) {
            return [
                'valid' => false,
                'message' => 'Dígito verificador de CUIT incorrecto'
            ];
        }

        return [
            'valid' => true,
            'message' => 'CUIT válido',
            'formatted' => $this->formatCUIT($cleanCuit)
        ];
    }

    /**
     * Validate Paraguayan RUC
     *
     * @param string $ruc RUC to validate
     * @return array Validation result
     */
    public function validateParaguayanRUC(string $ruc): array
    {
        // Remove any formatting
        $cleanRuc = preg_replace('/[^0-9]/', '', $ruc);

        // Check length (7-8 digits + check digit)
        if (strlen($cleanRuc) < 8 || strlen($cleanRuc) > 9) {
            return [
                'valid' => false,
                'message' => 'El RUC debe tener entre 8 y 9 dígitos'
            ];
        }

        // Validate check digit
        $baseNumber = substr($cleanRuc, 0, -1);
        $checkDigit = (int) substr($cleanRuc, -1);

        // Pad to 7 digits for calculation
        $baseNumber = str_pad($baseNumber, 7, '0', STR_PAD_LEFT);

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $baseNumber[$i] * self::RUC_MULTIPLIERS[$i];
        }

        $remainder = $sum % 11;
        $expectedCheckDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        if ($expectedCheckDigit !== $checkDigit) {
            return [
                'valid' => false,
                'message' => 'Dígito verificador de RUC incorrecto'
            ];
        }

        return [
            'valid' => true,
            'message' => 'RUC válido',
            'formatted' => $this->formatRUC($cleanRuc)
        ];
    }

    /**
     * Validate document type belongs to specified country
     *
     * @param int $DocumentsTypeId Document type ID
     * @param int $countryId Country ID
     * @return array Validation result
     */
    public function validateDocumentsTypeForCountry(int $DocumentsTypeId, int $countryId): array
    {
        $DocumentsType = DocumentType::find($DocumentsTypeId);

        if (!$DocumentsType) {
            return [
                'valid' => false,
                'message' => 'Tipo de documento no encontrado'
            ];
        }

        if ($DocumentsType->country_id !== $countryId) {
            return [
                'valid' => false,
                'message' => 'El tipo de documento no corresponde al país seleccionado'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Tipo de documento válido'
        ];
    }

    /**
     * Validate customs office belongs to specified country
     *
     * @param int $customOfficeId Customs office ID
     * @param int $countryId Country ID
     * @return array Validation result
     */
    public function validateCustomOfficeForCountry(int $customOfficeId, int $countryId): array
    {
        $customOffice = CustomOffice::find($customOfficeId);

        if (!$customOffice) {
            return [
                'valid' => false,
                'message' => 'Aduana no encontrada'
            ];
        }

        if ($customOffice->country_id !== $countryId) {
            return [
                'valid' => false,
                'message' => 'La aduana no corresponde al país seleccionado'
            ];
        }

        if (!$customOffice->active) {
            return [
                'valid' => false,
                'message' => 'La aduana seleccionada no está activa'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Aduana válida'
        ];
    }

    /**
     * Validate port belongs to specified country
     *
     * @param int $portId Port ID
     * @param int $countryId Country ID
     * @return array Validation result
     */
    public function validatePortForCountry(int $portId, int $countryId): array
    {
        $port = Port::find($portId);

        if (!$port) {
            return [
                'valid' => false,
                'message' => 'Puerto no encontrado'
            ];
        }

        if ($port->country_id !== $countryId) {
            return [
                'valid' => false,
                'message' => 'El puerto no corresponde al país seleccionado'
            ];
        }

        if (!$port->active) {
            return [
                'valid' => false,
                'message' => 'El puerto seleccionado no está activo'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Puerto válido'
        ];
    }

    /**
     * Validate client type
     *
     * @param string $clientType Client type
     * @return array Validation result
     */
    public function validateClientType(string $clientType): array
    {
        $validTypes = ['shipper', 'consignee', 'notify_party', 'owner'];

        if (!in_array($clientType, $validTypes)) {
            return [
                'valid' => false,
                'message' => 'Tipo de cliente no válido. Valores permitidos: ' . implode(', ', $validTypes)
            ];
        }

        return [
            'valid' => true,
            'message' => 'Tipo de cliente válido'
        ];
    }

    /**
     * Validate unique client identification (tax_id + country)
     *
     * @param string $taxId Tax ID
     * @param int $countryId Country ID
     * @param int|null $excludeId Client ID to exclude from check (for updates)
     * @return array Validation result
     */
    public function validateUniqueClientIdentification(string $taxId, int $countryId, ?int $excludeId = null): array
    {
        $query = Client::where('tax_id', $taxId)
                      ->where('country_id', $countryId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingClient = $query->first();

        if ($existingClient) {
            return [
                'valid' => false,
                'message' => 'Ya existe un cliente con este documento fiscal en el país seleccionado',
                'existing_client_id' => $existingClient->id
            ];
        }

        return [
            'valid' => true,
            'message' => 'Identificación única válida'
        ];
    }

    /**
     * Validate if company can access/edit client
     *
     * @param int $clientId Client ID
     * @param int $companyId Company ID
     * @return array Validation result
     */
    public function validateCompanyClientAccess(int $clientId, int $companyId): array
    {
        $client = Client::find($clientId);

        if (!$client) {
            return [
                'valid' => false,
                'message' => 'Cliente no encontrado'
            ];
        }

        // Check if company created the client
        if ($client->created_by_company_id === $companyId) {
            return [
                'valid' => true,
                'message' => 'Acceso permitido - empresa creadora',
                'access_type' => 'creator'
            ];
        }

        // Check if company has explicit relation with client
        $relation = $client->companyRelations()
                          ->where('company_id', $companyId)
                          ->where('active', true)
                          ->first();

        if ($relation) {
            return [
                'valid' => true,
                'message' => 'Acceso permitido - relación establecida',
                'access_type' => 'relation',
                'can_edit' => $relation->can_edit
            ];
        }

        return [
            'valid' => false,
            'message' => 'La empresa no tiene acceso a este cliente'
        ];
    }

    /**
     * Check if client data is compatible for webservice submission
     *
     * @param Client $client Client to check
     * @return array Compatibility result
     */
    public function validateWebserviceCompatibility(Client $client): array
    {
        $errors = [];

        // Tax ID must be valid
        $taxIdValidation = $this->validateTaxIdForCountry($client->tax_id, $client->country->iso_code);
        if (!$taxIdValidation['valid']) {
            $errors[] = 'CUIT/RUC inválido para webservices';
        }

        // Must have customs office
        if (!$client->$customs_offices) {
            $errors[] = 'Aduana requerida para webservices';
        }

        // Must have primary port
        if (!$client->primary_port_id) {
            $errors[] = 'Puerto principal requerido para webservices';
        }

        // Must be verified
        if (!$client->verified_at) {
            $errors[] = 'Cliente debe estar verificado para webservices';
        }

        // Must be active
        if ($client->status !== 'active') {
            $errors[] = 'Cliente debe estar activo para webservices';
        }

        return [
            'compatible' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Cliente compatible con webservices' : 'Cliente requiere correcciones para webservices'
        ];
    }

    /**
     * Format CUIT with standard notation
     *
     * @param string $cuit Clean CUIT
     * @return string Formatted CUIT
     */
    private function formatCUIT(string $cuit): string
    {
        return substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10, 1);
    }

    /**
     * Format RUC with standard notation
     *
     * @param string $ruc Clean RUC
     * @return string Formatted RUC
     */
    private function formatRUC(string $ruc): string
    {
        $baseNumber = substr($ruc, 0, -1);
        $checkDigit = substr($ruc, -1);
        return $baseNumber . '-' . $checkDigit;
    }

    /**
     * Format tax ID according to country
     *
     * @param string $taxId Tax ID
     * @param string $countryCode Country code
     * @return string Formatted tax ID
     */
    public function formatTaxId(string $taxId, string $countryCode): string
    {
        $cleanTaxId = preg_replace('/[^0-9]/', '', $taxId);

        switch (strtoupper($countryCode)) {
            case 'AR':
                return $this->formatCUIT($cleanTaxId);
            case 'PY':
                return $this->formatRUC($cleanTaxId);
            default:
                return $taxId;
        }
    }

    /**
     * Clear validation cache for specific tax ID
     *
     * @param string $taxId Tax ID
     * @param string $countryCode Country code
     * @return void
     */
    public function clearValidationCache(string $taxId, string $countryCode): void
    {
        $cacheKey = "tax_validation_{$countryCode}_{$taxId}";
        Cache::forget($cacheKey);
    }

    /**
     * Log validation activity for auditing
     *
     * @param string $action Validation action
     * @param array $data Validation data
     * @param bool $success Validation success
     * @return void
     */
    private function logValidation(string $action, array $data, bool $success): void
    {
        Log::info('Client validation', [
            'action' => $action,
            'success' => $success,
            'tax_id' => $data['tax_id'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'timestamp' => now()
        ]);
    }
}
