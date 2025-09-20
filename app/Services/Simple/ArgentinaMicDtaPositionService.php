<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Services\Simple\SimpleXmlGenerator;
use App\Services\Webservice\SoapClientService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * SERVICIO GPS SIMPLE - SIN HERENCIA COMPLEJA
 * 
 * Reutiliza la infraestructura WSAA existente del MIC/DTA
 * pero sin las complicaciones de BaseWebserviceService
 */
class ArgentinaMicDtaPositionService
{
    private Company $company;
    private User $user;
    private array $config;

    /**
     * Puntos de control AFIP para hidrovía Paraná
     */
    private const CONTROL_POINTS = [
        'ARBUE' => [
            'name' => 'Puerto Buenos Aires',
            'lat' => -34.6118,
            'lng' => -58.3960,
            'radius_km' => 5,
        ],
        'ARROS' => [
            'name' => 'Puerto Rosario', 
            'lat' => -32.9442,
            'lng' => -60.6505,
            'radius_km' => 3,
        ],
        'PYASU' => [
            'name' => 'Puerto Asunción',
            'lat' => -25.2637,
            'lng' => -57.5759,
            'radius_km' => 4,
        ],
        'PYTVT' => [
            'name' => 'Terminal Villeta',
            'lat' => -25.5097,
            'lng' => -57.5522,
            'radius_km' => 2,
        ],
    ];

    public function __construct(Company $company, User $user, array $config = [])
    {
        $this->company = $company;
        $this->user = $user;
        $this->config = array_merge([
            'environment' => 'testing',
            'webservice_type' => 'micdta_position',
            'min_update_interval_minutes' => 15,
            'max_daily_updates' => 96,
            'position_tolerance_meters' => 50,
        ], $config);
    }

    /**
 * Actualizar posición GPS en AFIP - SIN VALIDACIONES EXCESIVAS
 */
public function actualizarPosicion(Voyage $voyage, float $latitude, float $longitude, array $options = []): array
{
    try {
        $this->logInfo('Iniciando actualización GPS', [
            'voyage_id' => $voyage->id,
            'coordinates' => ['lat' => $latitude, 'lng' => $longitude]
        ]);

        // 1. BUSCAR cualquier MIC/DTA (sin validaciones estrictas)
        $micDtaRef = $this->buscarCualquierMicDta($voyage);
        if (!$micDtaRef) {
            // Solo falla si NO HAY NINGÚN MIC/DTA, pero sin tanto texto
            return [
                'success' => false,
                'error' => 'No se encontró ningún MIC/DTA para este voyage',
                'voyage_number' => $voyage->voyage_number,
            ];
        }

        // 2. Validar coordenadas básicas
        if (!$this->validarCoordenadas($latitude, $longitude)) {
            return [
                'success' => false,
                'error' => 'Coordenadas GPS inválidas',
                'voyage_number' => $voyage->voyage_number,
            ];
        }

        // 3. DIRECTO A AFIP - sin verificar intervalo ni distancia
        $transaction = $this->crearTransaccion($voyage, $latitude, $longitude, $micDtaRef, $options);
        $xml = $this->generarXmlGps($micDtaRef, $latitude, $longitude);
        $result = $this->enviarSoap($xml, $transaction);

        // 4. QUE AFIP RESPONDA LO QUE QUIERA
        return $this->procesarResultado($result, $transaction, $voyage, $latitude, $longitude, ['distance_moved' => 0, 'time_since_last' => 0]);

    } catch (Exception $e) {
        $this->logError('Error en actualización GPS', [
            'voyage_id' => $voyage->id,
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => 'Error interno: ' . $e->getMessage(),
            'voyage_number' => $voyage->voyage_number,
        ];
    }
}

private function buscarCualquierMicDta(Voyage $voyage): ?string
{
    // DEBUG: Log de la búsqueda
    Log::info("DEBUG GPS - Iniciando búsqueda MIC/DTA", [
        'voyage_id' => $voyage->id,
        'company_id' => $this->company->id,
    ]);

    // 1. Buscar DIRECTAMENTE en WebserviceResponse sin relaciones
    $responses = WebserviceResponse::where('reference_number', '!=', '')
        ->whereNotNull('reference_number')
        ->where('response_type', 'success')
        ->get();

    Log::info("DEBUG GPS - Todas las WebserviceResponse con reference_number", [
        'total_found' => $responses->count(),
        'responses' => $responses->map(function($r) {
            return [
                'id' => $r->id,
                'transaction_id' => $r->transaction_id,
                'reference_number' => $r->reference_number,
                'response_type' => $r->response_type,
            ];
        })->toArray()
    ]);

    // 2. Buscar transacciones del voyage 11
    $transactions = WebserviceTransaction::where('voyage_id', $voyage->id)
        ->where('webservice_type', 'micdta')
        ->get();

    Log::info("DEBUG GPS - Transacciones MIC/DTA del voyage", [
        'voyage_id' => $voyage->id,
        'total_transactions' => $transactions->count(),
        'transactions' => $transactions->map(function($t) {
            return [
                'id' => $t->id,
                'status' => $t->status,
                'external_reference' => $t->external_reference,
                'company_id' => $t->company_id,
            ];
        })->toArray()
    ]);

    // 3. Buscar si hay relación entre alguna response y alguna transaction del voyage
    foreach ($transactions as $transaction) {
        $response = WebserviceResponse::where('transaction_id', $transaction->id)
            ->whereNotNull('reference_number')
            ->where('reference_number', '!=', '')
            ->first();
            
        if ($response) {
            Log::info("DEBUG GPS - ¡ENCONTRADA! Relación transaction->response", [
                'transaction_id' => $transaction->id,
                'response_id' => $response->id,
                'reference_number' => $response->reference_number,
            ]);
            
            return $response->reference_number;
        }
    }

    Log::error("DEBUG GPS - NO se encontró ninguna referencia válida");
    // Agregar ANTES del return null:

// 4. Verificar a qué voyage pertenece la response exitosa "SIM_6_280687"
$responseWithRef = WebserviceResponse::where('reference_number', 'SIM_6_280687')->first();
if ($responseWithRef) {
    $relatedTransaction = WebserviceTransaction::find($responseWithRef->transaction_id);
    
    Log::info("DEBUG GPS - Response SIM_6_280687 pertenece a:", [
        'response_id' => $responseWithRef->id,
        'transaction_id' => $responseWithRef->transaction_id,
        'transaction_voyage_id' => $relatedTransaction?->voyage_id,
        'transaction_company_id' => $relatedTransaction?->company_id,
        'transaction_status' => $relatedTransaction?->status,
        'current_voyage_id' => $voyage->id,
    ]);
}

return null;
    return null;
}
    /**
     * Obtener referencia MIC/DTA válida
     */
    private function obtenerReferenciaValida(Voyage $voyage): ?string
    {
        $transaction = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('company_id', $this->company->id)
            ->where('webservice_type', 'micdta')
            ->where('status', 'sent')
            ->whereNotNull('external_reference')
            ->where('external_reference', '!=', '')
            ->latest('sent_at')
            ->first();

        return $transaction?->external_reference;
    }

    /**
     * Validar coordenadas GPS
     */
    private function validarCoordenadas(float $lat, float $lng): bool
    {
        // Validación básica
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        // Validación hidrovía Paraná (aproximada) - solo warning
        if ($lat < -35 || $lat > -20 || $lng < -62 || $lng > -54) {
            $this->logInfo('Coordenadas fuera del rango típico de hidrovía Paraná', [
                'lat' => $lat, 'lng' => $lng
            ]);
        }

        return true;
    }

    /**
     * Verificar si necesita actualización
     */
    private function necesitaActualizacion(Voyage $voyage, float $lat, float $lng): array
    {
        $ultimaActualizacion = WebserviceTransaction::where('voyage_id', $voyage->id)
            ->where('company_id', $this->company->id)
            ->where('webservice_type', 'micdta_position')
            ->where('status', 'sent')
            ->latest('sent_at')
            ->first();

        if (!$ultimaActualizacion) {
            return [
                'debe_actualizar' => true,
                'razon' => 'Primera actualización GPS',
                'distance_moved' => 0,
                'time_since_last' => null,
            ];
        }

        // Verificar intervalo mínimo
        $minutosDesdeUltima = $ultimaActualizacion->sent_at->diffInMinutes(now());
        $intervaloMinimo = $this->config['min_update_interval_minutes'];
        
        if ($minutosDesdeUltima < $intervaloMinimo) {
            return [
                'debe_actualizar' => false,
                'razon' => "Debe esperar {$intervaloMinimo} minutos entre actualizaciones (faltan " . ($intervaloMinimo - $minutosDesdeUltima) . " min)",
                'distance_moved' => 0,
                'time_since_last' => $minutosDesdeUltima,
            ];
        }

        // Verificar distancia mínima
        if (isset($ultimaActualizacion->additional_metadata['coordinates'])) {
            $coordsPrevias = $ultimaActualizacion->additional_metadata['coordinates'];
            $distanciaMetros = $this->calcularDistanciaHaversine(
                $coordsPrevias['lat'], $coordsPrevias['lng'],
                $lat, $lng
            ) * 1000;

            if ($distanciaMetros < $this->config['position_tolerance_meters']) {
                return [
                    'debe_actualizar' => false,
                    'razon' => "Movimiento insuficiente ({$distanciaMetros}m < {$this->config['position_tolerance_meters']}m)",
                    'distance_moved' => $distanciaMetros,
                    'time_since_last' => $minutosDesdeUltima,
                ];
            }

            return [
                'debe_actualizar' => true,
                'razon' => 'Actualización válida',
                'distance_moved' => $distanciaMetros,
                'time_since_last' => $minutosDesdeUltima,
            ];
        }

        return [
            'debe_actualizar' => true,
            'razon' => 'Actualización válida',
            'distance_moved' => 0,
            'time_since_last' => $minutosDesdeUltima,
        ];
    }

    /**
     * Crear transacción
     */
    private function crearTransaccion(Voyage $voyage, float $lat, float $lng, string $micDtaRef, array $options): WebserviceTransaction
    {
        return WebserviceTransaction::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'voyage_id' => $voyage->id,
            'transaction_id' => 'GPS_' . $voyage->voyage_number . '_' . time(),
            'webservice_type' => 'micdta_position',
            'country' => 'AR',
            'status' => 'pending',
            'method_name' => 'ActualizarPosicion',
            'soap_action' => 'Ar.Gob.Afip.Dga.wgesregsintia2/ActualizarPosicion',
            'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'environment' => $this->config['environment'],
            'additional_metadata' => [
                'coordinates' => ['lat' => $lat, 'lng' => $lng],
                'micdta_reference' => $micDtaRef,
                'source' => $options['source'] ?? 'manual',
                'control_point_detected' => $this->detectarPuntoControl($lat, $lng),
            ],
        ]);
    }

    /**
     * Generar XML GPS (reutilizando SimpleXmlGenerator)
     */
    private function generarXmlGps(string $micDtaRef, float $lat, float $lng): string
    {
        $xmlGenerator = new SimpleXmlGenerator($this->company, $this->config);
        
        return $xmlGenerator->generateActualizarPosicionXml([
            'external_reference' => $micDtaRef,
            'latitude' => $lat,
            'longitude' => $lng,
            'timestamp' => now()->toISOString(),
            'observations' => 'Actualización GPS automática - Sistema Simple'
        ]);
    }

    /**
     * Enviar SOAP
     */
    private function enviarSoap(string $xml, WebserviceTransaction $transaction): array
    {
        try {
            $soapClient = new SoapClientService($this->company);
            
            $transaction->update([
                'status' => 'sending',
                'sent_at' => now(),
                'request_xml' => $xml
            ]);

            $result = $soapClient->sendRequest($transaction, 'ActualizarPosicion', [
                'xmlParam' => $xml
            ]);

            $transaction->update([
                'response_at' => now(),
                'response_xml' => $result['response_xml'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $transaction->update([
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Procesar resultado
     */
    private function procesarResultado(array $result, WebserviceTransaction $transaction, Voyage $voyage, float $lat, float $lng, array $updateInfo): array
    {
        if ($result['success'] ?? false) {
            $transaction->update([
                'status' => 'sent',
                'completed_at' => now()
            ]);

            // Crear respuesta exitosa
            WebserviceResponse::create([
                'webservice_transaction_id' => $transaction->id,
                'response_code' => '200',
                'response_message' => 'Posición actualizada exitosamente',
                'response_data' => $result,
                'is_success' => true,
                'processed_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Posición GPS actualizada exitosamente en AFIP',
                'voyage_number' => $voyage->voyage_number,
                'transaction_id' => $transaction->id,
                'coordinates' => ['lat' => $lat, 'lng' => $lng],
                'control_point_detected' => $this->detectarPuntoControl($lat, $lng),
                'distance_moved_meters' => $updateInfo['distance_moved'],
                'time_since_last_update' => $updateInfo['time_since_last'],
            ];
        } else {
            $transaction->update([
                'status' => 'error',
                'error_message' => $result['error_message'] ?? 'Error desconocido'
            ]);

            // Crear respuesta de error
            WebserviceResponse::create([
                'webservice_transaction_id' => $transaction->id,
                'response_code' => '500',
                'response_message' => $result['error_message'] ?? 'Error en AFIP',
                'response_data' => $result,
                'is_success' => false,
                'processed_at' => now(),
            ]);

            return [
                'success' => false,
                'error' => $result['error_message'] ?? 'Error en comunicación con AFIP',
                'voyage_number' => $voyage->voyage_number,
                'transaction_id' => $transaction->id,
            ];
        }
    }

    /**
     * Detectar punto de control AFIP
     */
    private function detectarPuntoControl(float $lat, float $lng): ?array
    {
        foreach (self::CONTROL_POINTS as $codigo => $punto) {
            $distanciaKm = $this->calcularDistanciaHaversine(
                $lat, $lng, 
                $punto['lat'], $punto['lng']
            );

            if ($distanciaKm <= $punto['radius_km']) {
                return [
                    'codigo' => $codigo,
                    'nombre' => $punto['name'],
                    'distancia_km' => round($distanciaKm, 2),
                ];
            }
        }
        return null;
    }

    /**
     * Calcular distancia Haversine
     */
    private function calcularDistanciaHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $radioTierra * $c;
    }

    /**
     * Logging helpers
     */
    private function logInfo(string $message, array $context = []): void
    {
        Log::info("[GPS Position Service] {$message}", array_merge($context, [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]));
    }

    private function logError(string $message, array $context = []): void
    {
        Log::error("[GPS Position Service] {$message}", array_merge($context, [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]));
    }
}