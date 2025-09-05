<?php

namespace App\Actions\Customs;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\CertificateManagerService;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - ValidateVoyageForCustoms
 * 
 * Action centralizada para validar datos de viaje antes de envío a webservices aduaneros.
 * Soporta validaciones específicas por país y tipo de webservice.
 * 
 * WEBSERVICES SOPORTADOS:
 * - Paraguay (GDSF): Manifiestos, Adjuntos, Consultas, Rectificaciones, Cierres
 * - Argentina (AFIP): Información Anticipada, MIC/DTA, Desconsolidados, Transbordos
 * 
 * VALIDACIONES IMPLEMENTADAS:
 * - Datos obligatorios por webservice
 * - Certificados digitales (.p12)
 * - Formatos de datos (fechas, códigos, CUIT/RUC)
 * - Consistencia de datos (pesos, volúmenes, capacidades)
 * - Estados de viaje y flujo de operaciones
 * - Adjuntos obligatorios por operación
 * 
 * USO:
 * $validator = new ValidateVoyageForCustoms();
 * $result = $validator->validate($voyage, $webserviceType, $country, $options);
 */
class ValidateVoyageForCustoms
{
    private Company $company;
    private ?User $user = null;
    private CertificateManagerService $certificateManager;
    private array $validationResults = [];

    /**
     * Reglas de validación por webservice
     */
    private const VALIDATION_RULES = [
        'PY' => [
            'manifiesto' => [
                'required_voyage_fields' => ['voyage_number', 'departure_date', 'origin_port_id', 'destination_port_id'],
                'required_vessel_fields' => ['name', 'flag_country', 'captain_name'],
                'required_shipment_fields' => ['bl_number', 'shipper_name', 'consignee_name'],
                'required_attachments' => [],
                'max_bl_length' => 35,
                'allow_past_dates' => false,
            ],
            'adjuntos' => [
                'required_voyage_fields' => ['voyage_number'],
                'required_attachments' => ['conocimiento', 'factura'],
                'max_file_size_mb' => 10,
                'allowed_file_types' => ['pdf'],
                'requires_sent_manifest' => true,
            ],
            'consulta' => [
                'required_voyage_fields' => ['voyage_number'],
                'requires_paraguay_reference' => true,
            ],
            'rectificacion' => [
                'required_voyage_fields' => ['voyage_number'],
                'requires_paraguay_reference' => true,
                'max_rectifications' => 3,
            ],
            'cierre' => [
                'required_voyage_fields' => ['voyage_number'],
                'requires_paraguay_reference' => true,
                'requires_all_attachments' => true,
            ],
        ],
        'AR' => [
            'anticipada' => [
                'required_voyage_fields' => ['voyage_number', 'departure_date', 'arrival_date', 'origin_port_id', 'destination_port_id'],
                'required_vessel_fields' => ['name', 'flag_country', 'captain_name', 'captain_license'],
                'required_shipment_fields' => ['bl_number', 'shipper_name', 'consignee_name'],
                'max_bl_length' => 20,
                'allow_past_dates' => true,
                'requires_captain_license' => true,
            ],
            'micdta' => [
                'required_voyage_fields' => ['voyage_number', 'departure_date', 'origin_port_id', 'destination_port_id'],
                'required_vessel_fields' => ['name', 'vessel_code', 'flag_country'],
                'required_shipment_fields' => ['bl_number', 'shipper_name', 'shipper_tax_id', 'consignee_name', 'consignee_tax_id'],
                'required_container_fields' => ['container_number', 'container_type', 'gross_weight'],
                'max_bl_length' => 35,
                'validate_cuit' => true,
                'validate_container_check_digit' => true,
            ],
            'desconsolidado' => [
                'required_voyage_fields' => ['voyage_number', 'departure_date', 'origin_port_id', 'destination_port_id'],
                'required_shipment_fields' => ['bl_number', 'master_bl_number', 'shipper_name', 'consignee_name'],
                'requires_master_bl' => true,
            ],
            'transbordo' => [
                'required_voyage_fields' => ['voyage_number', 'departure_date', 'origin_port_id', 'destination_port_id', 'transshipment_port_id'],
                'required_vessel_fields' => ['name', 'vessel_code'],
                'requires_transshipment_port' => true,
            ],
        ],
    ];

    /**
     * Códigos de puerto válidos (UN/LOCODE)
     */
    private const VALID_PORT_CODES = [
        'ARBUE' => 'Buenos Aires, Argentina',
        'ARROS' => 'Rosario, Argentina',
        'ARSLA' => 'San Lorenzo, Argentina',
        'ARSNI' => 'San Nicolás, Argentina',
        'PYASU' => 'Asunción, Paraguay',
        'PYTVT' => 'Terminal Villeta, Paraguay',
        'PYCDE' => 'Concepción, Paraguay',
        'PYPIL' => 'Pilar, Paraguay',
    ];

    public function __construct()
    {
        // Constructor vacío - company y user se setean en validate()
    }

    /**
     * Validar viaje para envío a webservices aduaneros
     * 
     * @param Voyage $voyage Viaje a validar
     * @param string $webserviceType Tipo de webservice (manifiesto, anticipada, micdta, etc.)
     * @param string $country País de la aduana (AR, PY)
     * @param array $options Opciones adicionales de validación
     * @return array Resultado de validación
     */
    public function validate(Voyage $voyage, string $webserviceType, string $country, array $options = []): array
    {
        $this->company = $voyage->company;
        $this->user = $options['user'] ?? auth()->user();
        $this->certificateManager = new CertificateManagerService($this->company);
        
        $this->validationResults = [
            'is_valid' => false,
            'errors' => [],
            'warnings' => [],
            'voyage_id' => $voyage->id,
            'webservice_type' => $webserviceType,
            'country' => $country,
            'validation_timestamp' => now()->toISOString(),
            'validations_performed' => [],
        ];

        try {
            $this->logValidation('info', 'Iniciando validación de viaje para webservices', [
                'voyage_id' => $voyage->id,
                'voyage_number' => $voyage->voyage_number,
                'webservice_type' => $webserviceType,
                'country' => $country,
                'company_id' => $this->company->id,
            ]);

            // 1. Validaciones básicas del sistema
            $this->validateSystemRequirements($webserviceType, $country);
            
            // 2. Validar certificados digitales
            $this->validateDigitalCertificates($country);
            
            // 3. Validar reglas específicas del webservice
            $this->validateWebserviceRules($voyage, $webserviceType, $country);
            
            // 4. Validar datos del viaje
            $this->validateVoyageData($voyage, $webserviceType, $country);
            
            // 5. Validar datos de la embarcación
            $this->validateVesselData($voyage, $webserviceType, $country);
            
            // 6. Validar shipments y conocimientos
            $this->validateShipmentsData($voyage, $webserviceType, $country);
            
            // 7. Validar contenedores (si aplica)
            $this->validateContainersData($voyage, $webserviceType, $country);
            
            // 8. Validar estados y flujo de operaciones
            $this->validateOperationFlow($voyage, $webserviceType, $country, $options);
            
            // 9. Validar adjuntos requeridos
            $this->validateRequiredAttachments($voyage, $webserviceType, $country, $options);
            
            // 10. Validaciones adicionales específicas
            $this->validateSpecificRequirements($voyage, $webserviceType, $country, $options);

            // Determinar resultado final
            $this->validationResults['is_valid'] = empty($this->validationResults['errors']);
            
            $this->logValidation(
                $this->validationResults['is_valid'] ? 'info' : 'warning',
                'Validación completada',
                [
                    'is_valid' => $this->validationResults['is_valid'],
                    'errors_count' => count($this->validationResults['errors']),
                    'warnings_count' => count($this->validationResults['warnings']),
                    'validations_performed' => count($this->validationResults['validations_performed']),
                ]
            );

            return $this->validationResults;

        } catch (Exception $e) {
            $this->logValidation('error', 'Error durante validación', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->validationResults['errors'][] = 'Error interno de validación: ' . $e->getMessage();
            $this->validationResults['is_valid'] = false;
            
            return $this->validationResults;
        }
    }

    /**
 * Validar requerimientos básicos del sistema - CORREGIDO para usar UserHelper
 */
private function validateSystemRequirements(string $webserviceType, string $country): void
{
    $this->validationResults['validations_performed'][] = 'system_requirements';

    // Validar que la empresa está activa
    if (!$this->company->active) {
        $this->validationResults['errors'][] = 'La empresa no está activa en el sistema';
    }

    // Validar que los webservices están habilitados para la empresa
    if (!$this->company->ws_active) {
        $this->validationResults['errors'][] = 'Los webservices están deshabilitados para esta empresa';
    }

    // Validar combinación de webservice y país
    if (!isset(self::VALIDATION_RULES[$country][$webserviceType])) {
        $this->validationResults['errors'][] = "Combinación no soportada: {$webserviceType} para {$country}";
    }

    // CORREGIDO: Validar permisos usando el patrón del sistema
    // Verificar que la empresa tenga el rol apropiado según el webservice
    $requiredRole = $this->getRequiredCompanyRole($webserviceType, $country);
    if ($requiredRole && !in_array($requiredRole, $this->company->company_roles ?? [])) {
        $this->validationResults['errors'][] = "Su empresa no tiene el rol '{$requiredRole}' requerido para {$webserviceType}";
    }
}

/**
 * NUEVO: Obtener el rol de empresa requerido según el webservice
 */
private function getRequiredCompanyRole(string $webserviceType, string $country): ?string
{
    $roleMapping = [
        'AR' => [
            'anticipada' => 'Cargas',
            'micdta' => 'Cargas',
            'desconsolidado' => 'Desconsolidador',
            'transbordo' => 'Transbordos',
        ],
        'PY' => [
            'manifiesto' => 'Cargas',
            'adjuntos' => 'Cargas',
            'consulta' => 'Cargas',
            'rectificacion' => 'Cargas',
            'cierre' => 'Cargas',
        ]
    ];

    return $roleMapping[$country][$webserviceType] ?? null;
}

    /**
     * Validar certificados digitales requeridos
     */
    private function validateDigitalCertificates(string $country): void
    {
        $this->validationResults['validations_performed'][] = 'digital_certificates';

        try {
            $certValidation = $this->certificateManager->validateCompanyCertificate();
            
            if (!$certValidation['is_valid']) {
                $this->validationResults['errors'] = array_merge(
                    $this->validationResults['errors'], 
                    $certValidation['errors']
                );
            }

            if (!empty($certValidation['warnings'])) {
                $this->validationResults['warnings'] = array_merge(
                    $this->validationResults['warnings'], 
                    $certValidation['warnings']
                );
            }

        } catch (Exception $e) {
            $this->validationResults['errors'][] = 'Error validando certificados: ' . $e->getMessage();
        }
    }

    /**
     * Validar reglas específicas del webservice
     */
    private function validateWebserviceRules(Voyage $voyage, string $webserviceType, string $country): void
    {
        $this->validationResults['validations_performed'][] = 'webservice_rules';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];

        // Validar si requiere referencia externa previa
        if (!empty($rules['requires_paraguay_reference']) && $country === 'PY') {
            $hasReference = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'manifiesto')
                ->where('status', 'success')
                ->whereNotNull('external_reference')
                ->exists();
                
            if (!$hasReference) {
                $this->validationResults['errors'][] = 'Se requiere enviar el manifiesto principal antes de esta operación';
            }
        }

        // Validar si requiere manifiesto enviado previamente
        if (!empty($rules['requires_sent_manifest'])) {
            $hasManifest = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'manifiesto')
                ->where('status', 'success')
                ->exists();
                
            if (!$hasManifest) {
                $this->validationResults['errors'][] = 'Se requiere enviar el manifiesto antes de adjuntar documentos';
            }
        }

        // Validar límite de rectificaciones
        if (!empty($rules['max_rectifications'])) {
            $rectificationCount = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', $webserviceType)
                ->where('additional_metadata->is_rectification', true)
                ->count();
                
            if ($rectificationCount >= $rules['max_rectifications']) {
                $this->validationResults['errors'][] = "Se ha alcanzado el límite de {$rules['max_rectifications']} rectificaciones";
            }
        }
    }

    /**
     * Validar datos obligatorios del viaje
     */
    private function validateVoyageData(Voyage $voyage, string $webserviceType, string $country): void
    {
        $this->validationResults['validations_performed'][] = 'voyage_data';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];
        $requiredFields = $rules['required_voyage_fields'] ?? [];

        foreach ($requiredFields as $field) {
            if (empty($voyage->$field)) {
                $this->validationResults['errors'][] = "Campo obligatorio del viaje faltante: {$field}";
            }
        }

        // Validar formato de número de viaje
        if (!empty($voyage->voyage_number)) {
            if (strlen($voyage->voyage_number) > 20) {
                $this->validationResults['errors'][] = 'El número de viaje no puede exceder 20 caracteres';
            }
            
            if (!preg_match('/^[A-Z0-9\-_]+$/i', $voyage->voyage_number)) {
                $this->validationResults['errors'][] = 'El número de viaje contiene caracteres no válidos';
            }
        }

        // Validar fechas
        $this->validateVoyageDates($voyage, $rules);

        // Validar puertos
        $this->validateVoyagePorts($voyage, $rules);
    }

    /**
     * Validar fechas del viaje - CORREGIDO para campos reales
     */
    private function validateVoyageDates(Voyage $voyage, array $rules): void
    {
        $allowPastDates = $rules['allow_past_dates'] ?? true;

        // Validar fecha de salida
        if ($voyage->departure_date) {
            if (!$allowPastDates && $voyage->departure_date->isPast()) {
                $this->validationResults['errors'][] = 'La fecha de salida no puede ser anterior a hoy';
            }
            
            if ($voyage->departure_date->year < 2020 || $voyage->departure_date->year > 2030) {
                $this->validationResults['errors'][] = 'La fecha de salida está fuera del rango válido (2020-2030)';
            }
        }

        // CORREGIDO: Usar estimated_arrival_date en lugar de arrival_date
        if ($voyage->estimated_arrival_date && $voyage->departure_date) {
            if ($voyage->estimated_arrival_date->lt($voyage->departure_date)) {
                $this->validationResults['errors'][] = 'La fecha de llegada estimada no puede ser anterior a la fecha de salida';
            }
            
            $daysDifference = $voyage->departure_date->diffInDays($voyage->estimated_arrival_date);
            if ($daysDifference > 90) {
                $this->validationResults['warnings'][] = 'El viaje tiene una duración muy larga (más de 90 días)';
            }
        }
    }

    /**
     * Validar puertos del viaje
     */
    private function validateVoyagePorts(Voyage $voyage, array $rules): void
    {
        // Validar puerto de origen
        if ($voyage->originPort) {
            $originCode = $voyage->originPort->un_locode;
            if ($originCode && !isset(self::VALID_PORT_CODES[$originCode])) {
                $this->validationResults['warnings'][] = "Puerto de origen con código no reconocido: {$originCode}";
            }
        }

        // Validar puerto de destino
        if ($voyage->destinationPort) {
            $destinationCode = $voyage->destinationPort->un_locode;
            if ($destinationCode && !isset(self::VALID_PORT_CODES[$destinationCode])) {
                $this->validationResults['warnings'][] = "Puerto de destino con código no reconocido: {$destinationCode}";
            }
        }

        // Validar puerto de transbordo si es requerido
        if (!empty($rules['requires_transshipment_port']) && !$voyage->transshipment_port_id) {
            $this->validationResults['errors'][] = 'Se requiere puerto de transbordo para esta operación';
        }

        // Validar que origen y destino sean diferentes
        if ($voyage->origin_port_id && $voyage->destination_port_id && 
            $voyage->origin_port_id === $voyage->destination_port_id) {
            $this->validationResults['errors'][] = 'El puerto de origen y destino no pueden ser iguales';
        }
    }

    /**
     * Validar datos de la embarcación
     */
    private function validateVesselData(Voyage $voyage, string $webserviceType, string $country): void
    {
        $this->validationResults['validations_performed'][] = 'vessel_data';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];
        $requiredFields = $rules['required_vessel_fields'] ?? [];

        if (empty($requiredFields)) {
            return; // No se requieren datos del vessel para este webservice
        }

        if (!$voyage->vessel) {
            $this->validationResults['errors'][] = 'Se requiere información de la embarcación';
            return;
        }

        $vessel = $voyage->vessel;

        foreach ($requiredFields as $field) {
            $value = $vessel->$field;
            if (empty($value)) {
                $this->validationResults['errors'][] = "Campo obligatorio de la embarcación faltante: {$field}";
            }
        }

        // Validar código de embarcación si está presente
        if ($vessel->vessel_code && strlen($vessel->vessel_code) > 15) {
            $this->validationResults['errors'][] = 'El código de embarcación no puede exceder 15 caracteres';
        }

        // Validar capitán y licencia si es requerido
        if (!empty($rules['requires_captain_license'])) {
            if (empty($vessel->captain_name)) {
                $this->validationResults['errors'][] = 'Se requiere nombre del capitán';
            }
            
            if (empty($vessel->captain_license)) {
                $this->validationResults['errors'][] = 'Se requiere número de licencia del capitán';
            } elseif (strlen($vessel->captain_license) > 20) {
                $this->validationResults['errors'][] = 'La licencia del capitán no puede exceder 20 caracteres';
            }
        }

        // Validar país de bandera
        if ($vessel->flag_country && !in_array($vessel->flag_country, ['AR', 'PY', 'BR', 'UY', 'BO'])) {
            $this->validationResults['warnings'][] = "País de bandera no común en la región: {$vessel->flag_country}";
        }
    }

    /**
     * Validar datos de shipments
     */
    private function validateShipmentsData(Voyage $voyage, string $webserviceType, string $country): void
    {
        $this->validationResults['validations_performed'][] = 'shipments_data';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];
        $requiredFields = $rules['required_shipment_fields'] ?? [];
        $maxBlLength = $rules['max_bl_length'] ?? 35;
        $validateCuit = $rules['validate_cuit'] ?? false;

        // Cargar shipments con relación
        $voyage->load('shipments');
        $shipments = $voyage->shipments;

        if (!$shipments || $shipments->isEmpty()) {
            $this->validationResults['errors'][] = 'El viaje no tiene shipments asociados';
            return;
        }

        foreach ($shipments as $shipment) {
            $this->validateSingleShipment($shipment, $requiredFields, $maxBlLength, $validateCuit);
        }

        // Validar BL numbers únicos
        $blNumbers = $shipments->pluck('bl_number')->filter()->toArray();
        if (count($blNumbers) !== count(array_unique($blNumbers))) {
            $this->validationResults['errors'][] = 'Hay números de conocimiento duplicados en el viaje';
        }
    }

    /**
     * Validar un shipment individual
     */
    private function validateSingleShipment(Shipment $shipment, array $requiredFields, int $maxBlLength, bool $validateCuit): void
    {
        foreach ($requiredFields as $field) {
            if (empty($shipment->$field)) {
                $this->validationResults['errors'][] = "Shipment {$shipment->id}: Campo obligatorio faltante: {$field}";
            }
        }

        // Validar longitud de BL
        if ($shipment->bl_number && strlen($shipment->bl_number) > $maxBlLength) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: Número de BL excede {$maxBlLength} caracteres";
        }

        // Validar formato de BL
        if ($shipment->bl_number && !preg_match('/^[A-Z0-9\-_\/]+$/i', $shipment->bl_number)) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: Número de BL contiene caracteres no válidos";
        }

        // Validar CUIT si es requerido
        if ($validateCuit) {
            $this->validateCuitField($shipment, 'shipper_tax_id', 'expedidor');
            $this->validateCuitField($shipment, 'consignee_tax_id', 'consignatario');
        }

        // Validar pesos y volúmenes
        if ($shipment->gross_weight && $shipment->gross_weight <= 0) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: El peso bruto debe ser mayor a cero";
        }

        if ($shipment->volume && $shipment->volume <= 0) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: El volumen debe ser mayor a cero";
        }

        // Validar consistencia peso bruto >= peso neto
        if ($shipment->gross_weight && $shipment->net_weight && $shipment->gross_weight < $shipment->net_weight) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: El peso bruto no puede ser menor al peso neto";
        }
    }

    /**
     * Validar campo CUIT
     */
    private function validateCuitField(Shipment $shipment, string $field, string $description): void
    {
        $cuit = $shipment->$field;
        if (empty($cuit)) {
            return; // Ya se validó en campos requeridos
        }

        // Limpiar CUIT (solo números)
        $cleanCuit = preg_replace('/[^0-9]/', '', $cuit);
        
        if (strlen($cleanCuit) !== 11) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: CUIT del {$description} debe tener 11 dígitos";
            return;
        }

        // Validar dígito verificador CUIT
        if (!$this->isValidCuit($cleanCuit)) {
            $this->validationResults['errors'][] = "Shipment {$shipment->id}: CUIT del {$description} tiene dígito verificador inválido";
        }
    }

    /**
     * Validar dígito verificador de CUIT
     */
    private function isValidCuit(string $cuit): bool
    {
        if (strlen($cuit) !== 11) {
            return false;
        }

        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cuit[$i]) * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $verificationDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return intval($cuit[10]) === $verificationDigit;
    }

    /**
     * Validar datos de contenedores
     */
    private function validateContainersData(Voyage $voyage, string $webserviceType, string $country): void
    {
        $this->validationResults['validations_performed'][] = 'containers_data';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];
        $requiredFields = $rules['required_container_fields'] ?? [];
        $validateCheckDigit = $rules['validate_container_check_digit'] ?? false;

        if (empty($requiredFields)) {
            return; // No se requieren validaciones de contenedores
        }

        // Por ahora, solo validar si hay containers_loaded > 0
        $voyage->load('shipments');
        
        foreach ($voyage->shipments as $shipment) {
            if ($shipment->containers_loaded && $shipment->containers_loaded > 0) {
                // Si hay contenedores pero no tenemos la relación, dar warning
                $this->validationResults['warnings'][] = "Shipment {$shipment->bl_number}: Tiene {$shipment->containers_loaded} contenedores declarados pero no se pueden validar los detalles";
            }
        }
    }

    /**
     * Validar un contenedor individual
     */
    private function validateSingleContainer($container, array $requiredFields, bool $validateCheckDigit): void
    {
        foreach ($requiredFields as $field) {
            if (empty($container->$field)) {
                $this->validationResults['errors'][] = "Contenedor {$container->container_number}: Campo obligatorio faltante: {$field}";
            }
        }

        // Validar formato de número de contenedor
        if ($container->container_number) {
            $containerNumber = strtoupper(trim($container->container_number));
            
            if (!preg_match('/^[A-Z]{4}[0-9]{7}$/', $containerNumber)) {
                $this->validationResults['errors'][] = "Contenedor {$container->container_number}: Formato inválido (debe ser 4 letras + 7 números)";
            } elseif ($validateCheckDigit && !$this->isValidContainerCheckDigit($containerNumber)) {
                $this->validationResults['errors'][] = "Contenedor {$container->container_number}: Dígito verificador inválido";
            }
        }

        // Validar tipo de contenedor
        $validTypes = ['20GP', '40GP', '40HC', '20OT', '40OT', '20RF', '40RF'];
        if ($container->container_type && !in_array($container->container_type, $validTypes)) {
            $this->validationResults['warnings'][] = "Contenedor {$container->container_number}: Tipo no estándar: {$container->container_type}";
        }
    }

    /**
     * Validar dígito verificador de contenedor ISO
     */
    private function isValidContainerCheckDigit(string $containerNumber): bool
    {
        if (strlen($containerNumber) !== 11) {
            return false;
        }

        $letters = substr($containerNumber, 0, 4);
        $digits = substr($containerNumber, 4);
        $checkDigit = intval(substr($digits, -1));
        $serialNumber = substr($digits, 0, 6);

        // Convertir letras a números según estándar ISO 6346
        $letterValues = [];
        for ($i = 0; $i < 4; $i++) {
            $ascii = ord($letters[$i]);
            $value = $ascii - 55; // A=10, B=11, ..., Z=35
            if ($value >= 11 && $value <= 22) $value--; // Omitir K (ausente en ISO 6346)
            $letterValues[] = $value;
        }

        // Calcular suma ponderada
        $sum = 0;
        $multiplier = 1;

        // Procesar letras
        for ($i = 0; $i < 4; $i++) {
            $sum += $letterValues[$i] * $multiplier;
            $multiplier *= 2;
        }

        // Procesar dígitos del número de serie
        for ($i = 0; $i < 6; $i++) {
            $sum += intval($serialNumber[$i]) * $multiplier;
            $multiplier *= 2;
        }

        $calculatedCheckDigit = $sum % 11;
        if ($calculatedCheckDigit === 10) {
            $calculatedCheckDigit = 0;
        }

        return $checkDigit === $calculatedCheckDigit;
    }

    /**
     * Validar flujo de operaciones y estados
     */
    private function validateOperationFlow(Voyage $voyage, string $webserviceType, string $country, array $options): void
    {
        $this->validationResults['validations_performed'][] = 'operation_flow';

        // Obtener transacciones previas del viaje
        $previousTransactions = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', '!=', $webserviceType)
            ->orderBy('created_at')
            ->get();

        // Validar flujo específico por país y tipo
        $this->validateCountrySpecificFlow($voyage, $webserviceType, $country, $previousTransactions);

        // Validar que no hay operación duplicada en proceso
        $duplicateInProgress = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', $webserviceType)
            ->whereIn('status', ['pending', 'sending', 'retry'])
            ->exists();

        if ($duplicateInProgress) {
            $this->validationResults['errors'][] = "Ya hay una operación {$webserviceType} en proceso para este viaje";
        }

        // Validar límite de reintentos
        $failedAttempts = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', $webserviceType)
            ->where('status', 'error')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($failedAttempts >= 5) {
            $this->validationResults['errors'][] = 'Se ha excedido el límite de reintentos (5) en las últimas 24 horas';
        }
    }

    /**
     * Validar flujo específico por país
     */
    private function validateCountrySpecificFlow(Voyage $voyage, string $webserviceType, string $country, $previousTransactions): void
    {
        if ($country === 'PY') {
            $this->validateParaguayFlow($voyage, $webserviceType, $previousTransactions);
        } elseif ($country === 'AR') {
            $this->validateArgentinaFlow($voyage, $webserviceType, $previousTransactions);
        }
    }

    /**
     * Validar flujo específico Paraguay
     */
    private function validateParaguayFlow(Voyage $voyage, string $webserviceType, $previousTransactions): void
    {
        $manifestSent = $previousTransactions->where('webservice_type', 'manifiesto')
            ->where('status', 'success')->isNotEmpty();

        switch ($webserviceType) {
            case 'adjuntos':
                if (!$manifestSent) {
                    $this->validationResults['errors'][] = 'Debe enviar el manifiesto antes de adjuntar documentos';
                }
                break;

            case 'consulta':
                if (!$manifestSent) {
                    $this->validationResults['warnings'][] = 'No hay manifiesto enviado para consultar';
                }
                break;

            case 'rectificacion':
                if (!$manifestSent) {
                    $this->validationResults['errors'][] = 'Debe enviar el manifiesto antes de rectificar';
                }
                break;

            case 'cierre':
                if (!$manifestSent) {
                    $this->validationResults['errors'][] = 'Debe enviar el manifiesto antes de cerrar el viaje';
                }
                
                // Validar que todos los adjuntos requeridos estén subidos
                $attachmentsSent = $previousTransactions->where('webservice_type', 'adjuntos')
                    ->where('status', 'success')->isNotEmpty();
                
                if (!$attachmentsSent) {
                    $this->validationResults['warnings'][] = 'Se recomienda adjuntar documentos antes de cerrar el viaje';
                }
                break;
        }
    }

    /**
     * Validar flujo específico Argentina
     */
    private function validateArgentinaFlow(Voyage $voyage, string $webserviceType, $previousTransactions): void
    {
        switch ($webserviceType) {
            case 'micdta':
                // MIC/DTA puede enviarse independientemente
                break;

            case 'anticipada':
                // Información anticipada puede enviarse independientemente
                break;

            case 'desconsolidado':
                // Validar que existe información anticipada o MIC/DTA
                $hasBasicInfo = $previousTransactions->whereIn('webservice_type', ['anticipada', 'micdta'])
                    ->where('status', 'success')->isNotEmpty();
                
                if (!$hasBasicInfo) {
                    $this->validationResults['warnings'][] = 'Se recomienda enviar información anticipada o MIC/DTA antes del desconsolidado';
                }
                break;

            case 'transbordo':
                // Validar que el puerto de transbordo está configurado
                if (!$voyage->transshipment_port_id) {
                    $this->validationResults['errors'][] = 'Se requiere puerto de transbordo para esta operación';
                }
                break;
        }
    }

    /**
     * Validar adjuntos requeridos
     */
    private function validateRequiredAttachments(Voyage $voyage, string $webserviceType, string $country, array $options): void
    {
        $this->validationResults['validations_performed'][] = 'required_attachments';

        $rules = self::VALIDATION_RULES[$country][$webserviceType] ?? [];
        $requiredAttachments = $rules['required_attachments'] ?? [];

        if (empty($requiredAttachments)) {
            return; // No se requieren adjuntos para esta operación
        }

        // Verificar adjuntos en el sistema
        $uploadedAttachments = $options['uploaded_attachments'] ?? [];

        foreach ($requiredAttachments as $attachmentType) {
            if (!isset($uploadedAttachments[$attachmentType])) {
                $this->validationResults['errors'][] = "Documento requerido faltante: {$attachmentType}";
                continue;
            }

            $attachment = $uploadedAttachments[$attachmentType];
            
            // Validar tamaño de archivo
            $maxSizeMb = $rules['max_file_size_mb'] ?? 10;
            if ($attachment['size'] > ($maxSizeMb * 1024 * 1024)) {
                $this->validationResults['errors'][] = "Documento {$attachmentType} excede el tamaño máximo ({$maxSizeMb}MB)";
            }

            // Validar tipo de archivo
            $allowedTypes = $rules['allowed_file_types'] ?? ['pdf'];
            $fileExtension = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $this->validationResults['errors'][] = "Documento {$attachmentType} debe ser de tipo: " . implode(', ', $allowedTypes);
            }
        }
    }

    /**
     * Validaciones específicas adicionales
     */
    private function validateSpecificRequirements(Voyage $voyage, string $webserviceType, string $country, array $options): void
    {
        $this->validationResults['validations_performed'][] = 'specific_requirements';

        // Validaciones específicas por combinación país-webservice
        $validationMethod = 'validate' . ucfirst($country) . ucfirst($webserviceType);
        
        if (method_exists($this, $validationMethod)) {
            $this->$validationMethod($voyage, $options);
        }

        // Validar consistencia de datos totales
        $this->validateDataConsistency($voyage, $webserviceType, $country);

        // Validar límites operativos
        $this->validateOperationalLimits($voyage, $webserviceType, $country);
    }

    /**
     * Validación específica Paraguay Adjuntos
     */
    private function validatePyAdjuntos(Voyage $voyage, array $options): void
    {
        // Validar que exista referencia de Paraguay del manifiesto
        $paraguayReference = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('webservice_type', 'manifiesto')
            ->where('status', 'success')
            ->value('external_reference');

        if (empty($paraguayReference)) {
            $this->validationResults['errors'][] = 'No se encontró referencia de Paraguay del manifiesto enviado';
        }

        // Validar documentos obligatorios por tipo de carga
        $voyage->load('shipments');
        
        // Verificar si hay contenedores (usando relación que existe o conteo)
        $hasContainers = false;
        $hasBreakBulk = false;
        
        foreach ($voyage->shipments as $shipment) {
            if ($shipment->containers_loaded && $shipment->containers_loaded > 0) {
                $hasContainers = true;
            }
            if ($shipment->cargo_type === 'break_bulk') {
                $hasBreakBulk = true;
            }
        }

        $requiredDocs = ['conocimiento']; // Siempre requerido

        if ($hasContainers) {
            $requiredDocs[] = 'packing_list';
        }

        if ($hasBreakBulk) {
            $requiredDocs[] = 'tally_sheet';
        }

        $uploadedAttachments = $options['uploaded_attachments'] ?? [];
        
        foreach ($requiredDocs as $docType) {
            if (!isset($uploadedAttachments[$docType])) {
                $this->validationResults['errors'][] = "Documento obligatorio faltante: {$docType}";
            }
        }
    }

    /**
     * Validación específica Argentina Anticipada
     */
    private function validateArAnticipada(Voyage $voyage, array $options): void
    {
        // Validar datos específicos de información anticipada
        if ($voyage->vessel) {
            // Validar que el capitán tenga licencia válida
            if (empty($voyage->vessel->captain_license)) {
                $this->validationResults['errors'][] = 'Se requiere licencia del capitán para información anticipada';
            }

            // Validar tripulación si es requerida
            if ($voyage->vessel->crew_count && $voyage->vessel->crew_count > 20) {
                $this->validationResults['warnings'][] = 'Tripulación numerosa (>20), verificar datos';
            }
        }

        // Validar fechas ETA/ATA específicas para anticipada
        if (!$voyage->arrival_date) {
            $this->validationResults['errors'][] = 'Se requiere fecha estimada de arribo para información anticipada';
        }

        // Validar que no sea muy antigua
        if ($voyage->departure_date && $voyage->departure_date->lt(now()->subDays(30))) {
            $this->validationResults['warnings'][] = 'Fecha de salida muy antigua (>30 días), verificar si es correcto';
        }
    }

    /**
     * Validación específica Argentina MIC/DTA
     */
    private function validateArMicdta(Voyage $voyage, array $options): void
    {
        $voyage->load('shipments');
        
        // Validar que todos los shipments tengan CUIT válido
        foreach ($voyage->shipments as $shipment) {
            if (empty($shipment->shipper_tax_id) || empty($shipment->consignee_tax_id)) {
                $this->validationResults['errors'][] = "Shipment {$shipment->bl_number}: Faltan CUIT de expedidor o consignatario";
            }
        }

        // Validar capacidad total vs embarcación (usando containers_loaded en lugar de relación)
        if ($voyage->vessel && $voyage->vessel->container_capacity) {
            $totalContainers = $voyage->shipments->sum('containers_loaded') ?? 0;
            if ($totalContainers > $voyage->vessel->container_capacity) {
                $this->validationResults['warnings'][] = "Total contenedores ({$totalContainers}) excede capacidad embarcación ({$voyage->vessel->container_capacity})";
            }
        }
    }

    /**
     * Validar consistencia general de datos
     */
    private function validateDataConsistency(Voyage $voyage, string $webserviceType, string $country): void
    {
        // Cargar shipments si no están cargados
        if (!$voyage->relationLoaded('shipments')) {
            $voyage->load('shipments');
        }

        $shipments = $voyage->shipments;
        
        if (!$shipments || $shipments->isEmpty()) {
            return; // No hay shipments para validar consistencia
        }

        // Validar consistencia peso total
        $totalGrossWeight = $shipments->sum('gross_weight') ?? 0;
        $totalNetWeight = $shipments->sum('net_weight') ?? 0;

        if ($totalGrossWeight > 0 && $totalNetWeight > 0 && $totalGrossWeight < $totalNetWeight) {
            $this->validationResults['errors'][] = 'El peso bruto total no puede ser menor al peso neto total';
        }

        // Validar consistencia volumen vs peso
        $totalVolume = $shipments->sum('volume') ?? 0;
        if ($totalGrossWeight > 0 && $totalVolume > 0) {
            $density = $totalGrossWeight / $totalVolume; // kg/m3
            
            if ($density < 50) {
                $this->validationResults['warnings'][] = 'Densidad muy baja (<50 kg/m³), verificar pesos y volúmenes';
            } elseif ($density > 5000) {
                $this->validationResults['warnings'][] = 'Densidad muy alta (>5000 kg/m³), verificar pesos y volúmenes';
            }
        }

        // Validar cantidad de contenedores usando containers_loaded
        $totalContainers = $shipments->sum('containers_loaded') ?? 0;
        
        if ($totalContainers > 1000) {
            $this->validationResults['warnings'][] = "Cantidad muy alta de contenedores declarados ({$totalContainers})";
        }
    }

    /**
     * Validar límites operativos
     */
    private function validateOperationalLimits(Voyage $voyage, string $webserviceType, string $country): void
    {
        // Cargar shipments si no están cargados
        if (!$voyage->relationLoaded('shipments')) {
            $voyage->load('shipments');
        }

        $shipments = $voyage->shipments;
        
        if (!$shipments) {
            return; // No hay shipments para validar límites
        }

        // Límite de shipments por viaje
        $shipmentsCount = $shipments->count();
        $maxShipments = $country === 'PY' ? 500 : 1000;

        if ($shipmentsCount > $maxShipments) {
            $this->validationResults['errors'][] = "Cantidad de shipments ({$shipmentsCount}) excede el límite ({$maxShipments})";
        }

        // Límite de contenedores declarados por shipment
        foreach ($shipments as $shipment) {
            $containersCount = $shipment->containers_loaded ?? 0;
            if ($containersCount > 50) {
                $this->validationResults['warnings'][] = "Shipment {$shipment->bl_number} tiene muchos contenedores declarados ({$containersCount})";
            }
        }

        // Límite de peso total
        $totalWeight = $shipments->sum('gross_weight') ?? 0;
        $maxWeight = 50000; // 50 toneladas límite razonable

        if ($totalWeight > $maxWeight) {
            $this->validationResults['warnings'][] = "Peso total muy alto ({$totalWeight} kg), verificar datos";
        }

        // Límite de caracteres en descripciones
        foreach ($shipments as $shipment) {
            if ($shipment->cargo_description && strlen($shipment->cargo_description) > 500) {
                $this->validationResults['warnings'][] = "Shipment {$shipment->bl_number}: Descripción muy larga (>500 caracteres)";
            }
        }
    }

    /**
     * Log de operaciones de validación
     */
    private function logValidation(string $level, string $message, array $context = []): void
    {
        $context['validator'] = 'ValidateVoyageForCustoms';
        $context['company_id'] = $this->company?->id;
        $context['user_id'] = $this->user?->id;

        Log::channel('webservices')->$level($message, $context);
    }

    /**
     * Obtener resumen de validación para mostrar al usuario
     */
    public function getValidationSummary(): array
    {
        if (empty($this->validationResults)) {
            return ['status' => 'not_validated'];
        }

        return [
            'status' => $this->validationResults['is_valid'] ? 'valid' : 'invalid',
            'errors_count' => count($this->validationResults['errors']),
            'warnings_count' => count($this->validationResults['warnings']),
            'validations_count' => count($this->validationResults['validations_performed']),
            'can_proceed' => $this->validationResults['is_valid'],
            'summary_message' => $this->generateSummaryMessage(),
        ];
    }

    /**
     * Generar mensaje resumen para el usuario
     */
    private function generateSummaryMessage(): string
    {
        $errorsCount = count($this->validationResults['errors']);
        $warningsCount = count($this->validationResults['warnings']);

        if ($errorsCount === 0 && $warningsCount === 0) {
            return 'Todos los datos están correctos. El viaje puede enviarse a la aduana.';
        }

        if ($errorsCount > 0) {
            $message = "Se encontraron {$errorsCount} error(es) que deben corregirse antes del envío.";
            if ($warningsCount > 0) {
                $message .= " También hay {$warningsCount} advertencia(s) que se recomienda revisar.";
            }
            return $message;
        }

        return "Se encontraron {$warningsCount} advertencia(s). El viaje puede enviarse, pero se recomienda revisar las observaciones.";
    }

    /**
     * Obtener errores agrupados por categoría para mejor UX
     */
    public function getGroupedErrors(): array
    {
        $grouped = [
            'sistema' => [],
            'certificados' => [],
            'viaje' => [],
            'embarcacion' => [],
            'conocimientos' => [],
            'contenedores' => [],
            'adjuntos' => [],
            'flujo' => [],
            'otros' => [],
        ];

        foreach ($this->validationResults['errors'] as $error) {
            $category = $this->categorizeError($error);
            $grouped[$category][] = $error;
        }

        // Filtrar categorías vacías
        return array_filter($grouped, fn($errors) => !empty($errors));
    }

    /**
     * Categorizar error para agrupación
     */
    private function categorizeError(string $error): string
    {
        if (str_contains($error, 'certificado') || str_contains($error, '.p12')) {
            return 'certificados';
        }
        
        if (str_contains($error, 'Shipment') || str_contains($error, 'BL') || str_contains($error, 'CUIT')) {
            return 'conocimientos';
        }
        
        if (str_contains($error, 'Contenedor') || str_contains($error, 'container')) {
            return 'contenedores';
        }
        
        if (str_contains($error, 'embarcación') || str_contains($error, 'vessel') || str_contains($error, 'capitán')) {
            return 'embarcacion';
        }
        
        if (str_contains($error, 'adjunto') || str_contains($error, 'documento') || str_contains($error, 'PDF')) {
            return 'adjuntos';
        }
        
        if (str_contains($error, 'viaje') || str_contains($error, 'fecha') || str_contains($error, 'puerto')) {
            return 'viaje';
        }
        
        if (str_contains($error, 'empresa') || str_contains($error, 'usuario') || str_contains($error, 'sistema')) {
            return 'sistema';
        }
        
        if (str_contains($error, 'manifiesto') || str_contains($error, 'operación') || str_contains($error, 'proceso')) {
            return 'flujo';
        }

        return 'otros';
    }
}