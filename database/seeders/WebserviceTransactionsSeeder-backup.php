<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WebserviceTransaction;
use App\Models\Voyage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Seeder de Transacciones Webservice
 * 
 * Seeder que crea transacciones webservice realistas para testing
 * basadas en los viajes creados por VoyagesFromParanaSeeder.
 * 
 * TIPOS DE TRANSACCIONES CREADAS:
 * - Información Anticipada (anticipada): Para todos los viajes activos
 * - MIC/DTA (micdta): Para viajes en tránsito y completados  
 * - Transbordos (transbordos): Para viajes con barcazas
 * - Paraguay Customs (paraguay): Para destinos paraguayos
 * 
 * ESTADOS REALISTAS:
 * - success: Transacciones exitosas con confirmation_number
 * - pending: Transacciones pendientes de envío
 * - error: Transacciones con errores (para testing)
 * - retry: Transacciones en reintento
 * 
 * PROPÓSITO:
 * - Demostrar funcionalidad completa del sistema webservices
 * - Proporcionar datos para testing de vistas e interfaces
 * - Simular escenarios reales de uso del sistema
 * - Permitir testing de filtros, búsquedas y estadísticas
 */
class WebserviceTransactionsSeeder extends Seeder
{
    /**
     * Patrones de transaction_id realistas
     */
    private const TRANSACTION_PATTERNS = [
        'anticipada' => 'ANT{company}{timestamp}{random}',
        'micdta' => 'MIC{company}{timestamp}{random}', 
        'transbordos' => 'TRA{company}{timestamp}{random}',
        'paraguay' => 'PAR{company}{timestamp}{random}',
    ];

    /**
     * Códigos de error realistas Argentina AFIP
     */
    private const AFIP_ERROR_CODES = [
        'AFIP_001' => 'Error de autenticación con ticket',
        'AFIP_002' => 'Certificado digital vencido',
        'AFIP_003' => 'Datos de empresa inválidos',
        'AFIP_004' => 'Formato de XML incorrecto',
        'AFIP_005' => 'Viaje ya registrado anteriormente',
        'AFIP_006' => 'Contenedor duplicado en otro manifiesto',
        'AFIP_007' => 'Puerto de origen no autorizado',
        'AFIP_008' => 'Timeout en respuesta del webservice',
    ];

    /**
     * Ejecutar el seeder
     */
    public function run(): void
    {
        $this->command->info('📡 Iniciando seeder de transacciones webservice...');

        DB::beginTransaction();

        try {
            // 1. Obtener empresa MAERSK y sus viajes
            $company = $this->getMaerskCompany();
            $user = $this->getTestUser();
            $voyages = $this->getVoyagesForTransactions($company);
            
            $this->command->info("✅ Empresa: {$company->legal_name}");
            $this->command->info("✅ Usuario: {$user->name}");
            $this->command->info("✅ Viajes encontrados: {$voyages->count()}");

            // 2. Limpiar transacciones existentes de testing
            $this->cleanExistingTestTransactions($company);

            // 3. Crear transacciones para cada tipo de webservice
            $totalCreated = 0;

            // Información Anticipada (todos los viajes)
            $anticipadaCount = $this->createAnticipadaTransactions($voyages, $company, $user);
            $totalCreated += $anticipadaCount;
            $this->command->info("✅ Transacciones Información Anticipada: {$anticipadaCount}");

            // MIC/DTA (viajes con carga confirmada)
            $micdtaCount = $this->createMicDtaTransactions($voyages, $company, $user);
            $totalCreated += $micdtaCount;
            $this->command->info("✅ Transacciones MIC/DTA: {$micdtaCount}");

            // Transbordos (viajes con barcazas)
            $transshipmentCount = $this->createTransshipmentTransactions($voyages, $company, $user);
            $totalCreated += $transshipmentCount;
            $this->command->info("✅ Transacciones Transbordos: {$transshipmentCount}");

            // Paraguay Customs (destinos paraguayos)
            $paraguayCount = $this->createParaguayTransactions($voyages, $company, $user);
            $totalCreated += $paraguayCount;
            $this->command->info("✅ Transacciones Paraguay: {$paraguayCount}");

            // 4. Crear algunas transacciones históricas con variedad de estados
            $historicalCount = $this->createHistoricalTransactions($company, $user);
            $totalCreated += $historicalCount;
            $this->command->info("✅ Transacciones históricas: {$historicalCount}");

            DB::commit();

            $this->command->info("🎉 Seeder de transacciones webservice completado!");
            $this->command->info("📊 Total transacciones creadas: {$totalCreated}");
            $this->command->info("🔗 Para empresa: {$company->legal_name}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Error en seeder: " . $e->getMessage());
            $this->command->error("📍 Archivo: " . $e->getFile() . " línea " . $e->getLine());
            throw $e;
        }
    }

    /**
     * Obtener empresa MAERSK
     */
    private function getMaerskCompany(): Company
    {
        $company = Company::where('tax_id', '30688415531')
            ->orWhere('legal_name', 'LIKE', '%MAERSK%')
            ->first();

        if (!$company) {
            throw new \Exception('Empresa MAERSK no encontrada. Ejecute VoyagesFromParanaSeeder primero.');
        }

        return $company;
    }

    /**
     * Obtener usuario de testing
     */
    private function getTestUser(): User
    {
        return User::where('email', 'LIKE', '%admin%')
            ->orWhere('email', 'LIKE', '%testing%')
            ->first() ?? User::first();
    }

    /**
     * Obtener viajes para crear transacciones
     */
    private function getVoyagesForTransactions(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        return Voyage::where('company_id', $company->id)
            ->where('voyage_number', 'LIKE', 'V0%NB') // Solo viajes del seeder PARANA
            ->orderBy('departure_date', 'desc')
            ->get();
    }

    /**
     * Limpiar transacciones de testing existentes
     */
    private function cleanExistingTestTransactions(Company $company): void
    {
        $deleted = WebserviceTransaction::where('company_id', $company->id)
            ->where('additional_metadata->created_by_seeder', true)
            ->delete();

        if ($deleted > 0) {
            $this->command->info("🧹 Eliminadas {$deleted} transacciones de testing existentes");
        }
    }

    /**
     * Crear transacciones de Información Anticipada
     */
    private function createAnticipadaTransactions(\Illuminate\Database\Eloquent\Collection $voyages, Company $company, User $user): int
    {
        $count = 0;

        foreach ($voyages as $voyage) {
            // Solo crear para viajes que tienen sentido (no muy antiguos)
            if ($voyage->departure_date->diffInDays(now()) > 90) {
                continue;
            }

            $status = $this->determineTransactionStatus($voyage, 'anticipada');
            $transactionId = $this->generateTransactionId($company->id, 'anticipada');
            
            $transaction = WebserviceTransaction::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'webservice_type' => 'anticipada',
                'country' => 'AR',
                'environment' => 'testing',
                'status' => $status,
                'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
                'external_reference' => "ANTIC-{$voyage->voyage_number}",
                'internal_reference' => $voyage->internal_reference,
                'confirmation_number' => $status === 'success' ? $this->generateConfirmationNumber() : null,
                'sent_at' => $status !== 'pending' ? $voyage->departure_date->copy()->subDays(rand(3, 7)) : null,
                'response_at' => $status === 'success' ? $voyage->departure_date->copy()->subDays(rand(2, 6)) : null,
                'error_code' => $status === 'error' ? array_rand(self::AFIP_ERROR_CODES) : null,
                'error_message' => $status === 'error' ? self::AFIP_ERROR_CODES[array_rand(self::AFIP_ERROR_CODES)] : null,
                'retry_count' => $status === 'retry' ? rand(1, 2) : 0,
                'max_retries' => 3,
                'container_count' => $voyage->total_containers ?? 0,
                'total_weight_kg' => rand(50000, 150000),
                'currency_code' => 'USD',
                'ip_address' => '192.168.1.' . rand(100, 200),
                'user_agent' => 'Mozilla/5.0 (WebserviceSeeder)',
                'additional_metadata' => [
                    'created_by_seeder' => true,
                    'seeder_type' => 'anticipada',
                    'voyage_number' => $voyage->voyage_number,
                    'barge_name' => $voyage->barge_name,
                    'route' => "{$voyage->origin_port} → {$voyage->destination_port}",
                    'webservice_eligible' => true,
                ],
                'created_at' => $voyage->departure_date->copy()->subDays(rand(5, 10)),
                'updated_at' => $status === 'success' ? $voyage->departure_date->copy()->subDays(rand(2, 6)) : now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Crear transacciones MIC/DTA
     */
    private function createMicDtaTransactions(\Illuminate\Database\Eloquent\Collection $voyages, Company $company, User $user): int
    {
        $count = 0;

        foreach ($voyages as $voyage) {
            // MIC/DTA solo para viajes con carga confirmada (no todos)
            if (rand(1, 10) <= 3) { // 30% de probabilidad
                continue;
            }

            if ($voyage->departure_date->diffInDays(now()) > 60) {
                continue;
            }

            $status = $this->determineTransactionStatus($voyage, 'micdta');
            $transactionId = $this->generateTransactionId($company->id, 'micdta');

            WebserviceTransaction::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'webservice_type' => 'micdta',
                'country' => 'AR',
                'environment' => 'testing',
                'status' => $status,
                'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
                'external_reference' => "MIC-{$voyage->voyage_number}",
                'internal_reference' => $voyage->internal_reference,
                'confirmation_number' => $status === 'success' ? $this->generateConfirmationNumber() : null,
                'sent_at' => $status !== 'pending' ? $voyage->departure_date->copy()->subDays(rand(1, 3)) : null,
                'response_at' => $status === 'success' ? $voyage->departure_date->copy()->subDays(rand(1, 2)) : null,
                'error_code' => $status === 'error' ? array_rand(self::AFIP_ERROR_CODES) : null,
                'error_message' => $status === 'error' ? self::AFIP_ERROR_CODES[array_rand(self::AFIP_ERROR_CODES)] : null,
                'retry_count' => $status === 'retry' ? rand(1, 2) : 0,
                'max_retries' => 3,
                'container_count' => $voyage->total_containers ?? 0,
                'total_weight_kg' => rand(60000, 180000),
                'currency_code' => 'USD',
                'ip_address' => '192.168.1.' . rand(100, 200),
                'user_agent' => 'Mozilla/5.0 (WebserviceSeeder)',
                'additional_metadata' => [
                    'created_by_seeder' => true,
                    'seeder_type' => 'micdta',
                    'voyage_number' => $voyage->voyage_number,
                    'containers_detail' => [
                        'total' => $voyage->total_containers,
                        'types' => ['40HC', '20GP', '40GP'],
                        'weights' => ['4988', '12500', '8750'],
                    ],
                ],
                'created_at' => $voyage->departure_date->copy()->subDays(rand(2, 5)),
                'updated_at' => $status === 'success' ? $voyage->departure_date->copy()->subDays(1) : now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Crear transacciones de Transbordos
     */
    private function createTransshipmentTransactions(\Illuminate\Database\Eloquent\Collection $voyages, Company $company, User $user): int
    {
        $count = 0;

        foreach ($voyages as $voyage) {
            // Transbordos solo para viajes con barcazas específicas
            if (!str_contains($voyage->barge_name ?? '', 'PAR')) {
                continue;
            }

            if (rand(1, 10) <= 4) { // 40% de probabilidad
                continue;
            }

            $status = $this->determineTransactionStatus($voyage, 'transbordos');
            $transactionId = $this->generateTransactionId($company->id, 'transbordos');

            WebserviceTransaction::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'webservice_type' => 'transbordos',
                'country' => 'AR',
                'environment' => 'testing',
                'status' => $status,
                'webservice_url' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgestransbordo/wgestransbordo.asmx',
                'external_reference' => "TRB-{$voyage->barge_name}",
                'internal_reference' => $voyage->internal_reference,
                'confirmation_number' => $status === 'success' ? $this->generateConfirmationNumber() : null,
                'sent_at' => $status !== 'pending' ? $voyage->departure_date->copy()->subHours(rand(6, 24)) : null,
                'response_at' => $status === 'success' ? $voyage->departure_date->copy()->subHours(rand(2, 12)) : null,
                'error_code' => $status === 'error' ? array_rand(self::AFIP_ERROR_CODES) : null,
                'error_message' => $status === 'error' ? self::AFIP_ERROR_CODES[array_rand(self::AFIP_ERROR_CODES)] : null,
                'retry_count' => 0,
                'max_retries' => 3,
                'container_count' => $voyage->total_containers ?? 0,
                'total_weight_kg' => rand(40000, 120000),
                'currency_code' => 'USD',
                'ip_address' => '192.168.1.' . rand(100, 200),
                'user_agent' => 'Mozilla/5.0 (WebserviceSeeder)',
                'additional_metadata' => [
                    'created_by_seeder' => true,
                    'seeder_type' => 'transbordos',
                    'barge_info' => [
                        'name' => $voyage->barge_name,
                        'capacity' => '1500 TEU',
                        'gps_tracking' => true,
                    ],
                    'division_cargas' => [
                        'madre_containers' => $voyage->total_containers,
                        'hijo_containers' => rand(10, 20),
                    ],
                ],
                'created_at' => $voyage->departure_date->copy()->subDays(1),
                'updated_at' => $status === 'success' ? $voyage->departure_date->copy()->subHours(6) : now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Crear transacciones Paraguay
     */
    private function createParaguayTransactions(\Illuminate\Database\Eloquent\Collection $voyages, Company $company, User $user): int
    {
        $count = 0;

        foreach ($voyages as $voyage) {
            // Solo viajes con destino Paraguay
            if ($voyage->destination_country !== 'PY') {
                continue;
            }

            if (rand(1, 10) <= 3) { // 30% de probabilidad
                continue;
            }

            $status = $this->determineTransactionStatus($voyage, 'paraguay');
            $transactionId = $this->generateTransactionId($company->id, 'paraguay');

            WebserviceTransaction::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'webservice_type' => 'paraguay',
                'country' => 'PY',
                'environment' => 'testing',
                'status' => $status,
                'webservice_url' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
                'external_reference' => "PY-{$voyage->voyage_number}",
                'internal_reference' => $voyage->internal_reference,
                'confirmation_number' => $status === 'success' ? $this->generateParaguayConfirmation() : null,
                'sent_at' => $status !== 'pending' ? $voyage->estimated_arrival_date->copy()->subHours(rand(12, 48)) : null,
                'response_at' => $status === 'success' ? $voyage->estimated_arrival_date->copy()->subHours(rand(6, 24)) : null,
                'error_code' => $status === 'error' ? 'PY_ERROR_' . rand(100, 999) : null,
                'error_message' => $status === 'error' ? 'Error en webservice Paraguay: Datos incompletos' : null,
                'retry_count' => 0,
                'max_retries' => 3,
                'container_count' => $voyage->total_containers ?? 0,
                'total_weight_kg' => rand(55000, 165000),
                'currency_code' => 'USD',
                'ip_address' => '192.168.1.' . rand(100, 200),
                'user_agent' => 'Mozilla/5.0 (WebserviceSeeder)',
                'additional_metadata' => [
                    'created_by_seeder' => true,
                    'seeder_type' => 'paraguay',
                    'destination_terminal' => 'Terminal Villeta',
                    'paraguay_specific' => [
                        'dna_registration' => true,
                        'gdsf_compatible' => true,
                    ],
                ],
                'created_at' => $voyage->estimated_arrival_date->copy()->subHours(rand(24, 72)),
                'updated_at' => $status === 'success' ? $voyage->estimated_arrival_date->copy()->subHours(12) : now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Crear transacciones históricas para estadísticas
     */
    private function createHistoricalTransactions(Company $company, User $user): int
    {
        $count = 0;
        $types = ['anticipada', 'micdta', 'transbordos', 'paraguay'];

        for ($i = 0; $i < 15; $i++) {
            $type = $types[array_rand($types)];
            $status = ['success', 'success', 'success', 'error', 'retry'][array_rand(['success', 'success', 'success', 'error', 'retry'])]; // Más éxitos que errores
            $createdDate = now()->subDays(rand(30, 120));

            WebserviceTransaction::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'transaction_id' => $this->generateTransactionId($company->id, $type),
                'webservice_type' => $type,
                'country' => in_array($type, ['anticipada', 'micdta', 'transbordos']) ? 'AR' : 'PY',
                'environment' => 'testing',
                'status' => $status,
                'webservice_url' => $this->getWebserviceUrl($type),
                'external_reference' => "HIST-{$type}-" . rand(1000, 9999),
                'confirmation_number' => $status === 'success' ? $this->generateConfirmationNumber() : null,
                'sent_at' => $createdDate->copy()->addHours(rand(1, 24)),
                'response_at' => $status === 'success' ? $createdDate->copy()->addHours(rand(2, 48)) : null,
                'error_code' => $status === 'error' ? array_rand(self::AFIP_ERROR_CODES) : null,
                'error_message' => $status === 'error' ? self::AFIP_ERROR_CODES[array_rand(self::AFIP_ERROR_CODES)] : null,
                'retry_count' => $status === 'retry' ? rand(1, 3) : 0,
                'max_retries' => 3,
                'container_count' => rand(15, 45),
                'total_weight_kg' => rand(30000, 200000),
                'currency_code' => 'USD',
                'ip_address' => '192.168.1.' . rand(100, 200),
                'user_agent' => 'Mozilla/5.0 (WebserviceSeeder)',
                'additional_metadata' => [
                    'created_by_seeder' => true,
                    'seeder_type' => 'historical',
                    'historical_data' => true,
                ],
                'created_at' => $createdDate,
                'updated_at' => $status === 'success' ? $createdDate->copy()->addHours(rand(2, 48)) : $createdDate->copy()->addDays(rand(1, 5)),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Determinar estado de transacción según viaje y tipo
     */
    private function determineTransactionStatus(Voyage $voyage, string $type): string
    {
        $now = now();
        $daysDiff = $now->diffInDays($voyage->departure_date, false);

        // Lógica realista según estado del viaje
        if ($daysDiff > 7) {
            return 'pending'; // Viajes muy futuros
        } elseif ($daysDiff > 0) {
            return rand(1, 10) <= 8 ? 'success' : 'pending'; // 80% éxito para viajes próximos
        } elseif ($daysDiff >= -7) {
            return rand(1, 10) <= 9 ? 'success' : 'error'; // 90% éxito para viajes recientes
        } else {
            return rand(1, 10) <= 7 ? 'success' : 'error'; // 70% éxito para viajes antiguos
        }
    }

    /**
     * Generar transaction_id realista
     */
    private function generateTransactionId(int $companyId, string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $companyCode = str_pad($companyId, 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$companyCode}{$timestamp}{$random}";
    }

    /**
     * Generar número de confirmación AFIP
     */
    private function generateConfirmationNumber(): string
    {
        return 'AFIP' . now()->format('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generar confirmación Paraguay
     */
    private function generateParaguayConfirmation(): string
    {
        return 'PY' . now()->format('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener URL del webservice según tipo
     */
    private function getWebserviceUrl(string $type): string
    {
        $urls = [
            'anticipada' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            'micdta' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesregsintia2/wgesregsintia2.asmx',
            'transbordos' => 'https://wsaduhomoext.afip.gob.ar/DIAV2/wgestransbordo/wgestransbordo.asmx',
            'paraguay' => 'https://securetest.aduana.gov.py/wsdl/gdsf/serviciogdsf',
        ];

        return $urls[$type] ?? 'https://webservice.testing.com';
    }
}