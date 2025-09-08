<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebserviceTrack;
use App\Services\Webservice\ArgentinaMicDtaService;
use Exception;

/**
 * SESIÓN 2: COMANDO TESTING TRACKs - SOLO AFIP REAL
 * 
 * ARCHIVO: app/Console/Commands/TestTracksFlow.php
 */
class TestTracksFlow extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'afip:test-tracks 
                           {--company-id= : ID de empresa específica}
                           {--shipment-id= : ID de shipment específico}
                           {--stats : Mostrar solo estadísticas}
                           {--clean : Limpiar TRACKs de testing previos}';

    /**
     * The console command description.
     */
    protected $description = 'Testing sistema TRACKs AFIP: RegistrarTitEnvios + MIC/DTA - SOLO conexiones reales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 TESTING SISTEMA TRACKs AFIP - CONEXIONES REALES');
        $this->info('===================================================');

        try {
            // 1. Limpiar TRACKs de testing si se solicita
            if ($this->option('clean')) {
                $this->cleanTestingTracks();
                return;
            }

            // 2. Mostrar solo estadísticas si se solicita
            if ($this->option('stats')) {
                $this->showTracksStatistics();
                return;
            }

            // 3. Obtener empresa y usuario para testing
            $company = $this->getTestingCompany();
            $user = $this->getTestingUser($company);

            // 4. Obtener shipment para testing
            $shipment = $this->getTestingShipment($company);

            // 5. Ejecutar flujo completo TRACKs
            $this->executeTracksFlow($company, $user, $shipment);

        } catch (Exception $e) {
            $this->error('❌ Error en testing TRACKs: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }

        return 0;
    }

    /**
     * Obtener empresa para testing
     */
    private function getTestingCompany(): Company
    {
        $companyId = $this->option('company-id');

        if ($companyId) {
            $company = Company::find($companyId);
            if (!$company) {
                throw new Exception("Empresa ID {$companyId} no encontrada");
            }
        } else {
            // Buscar MAERSK o primera empresa activa con webservices
            $company = Company::where('legal_name', 'LIKE', '%MAERSK%')
                ->where('active', true)
                ->where('ws_active', true)
                ->first();

            if (!$company) {
                $company = Company::where('active', true)
                    ->where('ws_active', true)
                    ->first();
            }

            if (!$company) {
                throw new Exception('No se encontró empresa activa con webservices habilitados');
            }
        }

        $this->info("✅ Empresa: {$company->legal_name} (ID: {$company->id})");
        $this->info("   CUIT: {$company->tax_id}");

        return $company;
    }

    /**
     * Obtener usuario para testing
     */
    /**
 * Obtener usuario para testing
 */
private function getTestingUser(Company $company): User
{
    // Buscar usuario asociado a la empresa (relación polimórfica)
    $user = User::where('userable_type', 'App\Models\Company')
        ->where('userable_id', $company->id)
        ->where('active', true)
        ->first();

    if (!$user) {
        // Buscar cualquier usuario activo como fallback
        $user = User::where('active', true)->first();
        
        if (!$user) {
            // Crear usuario de testing temporal
            $user = User::create([
                'name' => 'Testing TRACKs',
                'email' => 'testing.tracks@' . strtolower(str_replace(' ', '', $company->name)) . '.test',
                'password' => bcrypt('testing123'),
                'active' => true,
                'userable_type' => 'App\Models\Company',
                'userable_id' => $company->id,
            ]);
            
            $this->info("Usuario temporal creado para testing");
        } else {
            $this->warn("Usando usuario existente (no asociado específicamente a la empresa)");
        }
    }

    $this->info("Usuario: {$user->name} (ID: {$user->id})");
    
    // Mostrar información de la relación
    if ($user->userable_type === 'App\Models\Company' && $user->userable_id == $company->id) {
        $this->info("   Relación: Directamente asociado a la empresa");
    } else {
        $this->info("   Relación: Usuario general del sistema");
    }

    return $user;
}

    /**
     * Obtener shipment para testing
     */
    private function getTestingShipment(Company $company): Shipment
    {
        $shipmentId = $this->option('shipment-id');

        if ($shipmentId) {
            $shipment = Shipment::find($shipmentId);
            if (!$shipment) {
                throw new Exception("Shipment ID {$shipmentId} no encontrado");
            }
        } else {
            // Buscar shipment activo básico primero
            $shipment = Shipment::whereHas('voyage', function($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereHas('vessel')
            ->where('active', true)
            ->latest()
            ->first();

            if (!$shipment) {
                // Buscar shipment sin restricciones de voyage/vessel
                $shipment = Shipment::where('active', true)
                    ->latest()
                    ->first();
            }

            if (!$shipment) {
                throw new Exception('No se encontró shipment válido para testing');
            }
        }

        $this->info("Shipment: {$shipment->shipment_number} (ID: {$shipment->id})");
        
        // Verificar relaciones disponibles sin asumir que existen
        try {
            if ($shipment->voyage) {
                $this->info("   Voyage: {$shipment->voyage->voyage_number}");
            } else {
                $this->warn("   Voyage: No asignado");
            }
        } catch (Exception $e) {
            $this->warn("   Voyage: Error verificando relación");
        }

        try {
            if ($shipment->vessel) {
                $this->info("   Vessel: {$shipment->vessel->name}");
            } else {
                $this->warn("   Vessel: No asignado");
            }
        } catch (Exception $e) {
            $this->warn("   Vessel: Error verificando relación");
        }

        // Verificar containers solo si la relación existe
        try {
            if (method_exists($shipment, 'containers') && $shipment->containers) {
                $this->info("   Containers: " . $shipment->containers->count());
            } else {
                $this->info("   Containers: Relación no disponible");
            }
        } catch (Exception $e) {
            $this->info("   Containers: 0 (relación no definida)");
        }

        // Verificar bills of lading solo si la relación existe
        try {
            if (method_exists($shipment, 'billsOfLading') && $shipment->billsOfLading) {
                $this->info("   Bills: " . $shipment->billsOfLading->count());
            } else {
                $this->info("   Bills: Relación no disponible");
            }
        } catch (Exception $e) {
            $this->info("   Bills: 0 (relación no definida)");
        }

        return $shipment;
    }

    /**
     * Ejecutar flujo completo TRACKs
     */
    private function executeTracksFlow(Company $company, User $user, Shipment $shipment): void
{
    $this->info("\n🔄 EJECUTANDO FLUJO TRACKs - AFIP REAL");
    $this->info("=====================================");

    // 1. Validar configuración antes de continuar
    if (!$this->validateTestingConfiguration($company)) {
        $this->error('❌ Configuración inválida - no se puede continuar');
        return;
    }

    // 2. Mostrar info debug si es necesario
    $this->showDebugInfo($company, $shipment);

    // Configurar para conexión real AFIP
    $config = ['environment' => 'testing']; // Ambiente homologación AFIP
    $this->info("🔗 Conectando a ambiente REAL AFIP (homologación)");
    $this->info("⚠️  ATENCIÓN: Se realizarán envíos REALES al webservice AFIP");

    if (!$this->confirm('¿Continuar con envío real a AFIP?', false)) {
        $this->info('Testing cancelado por el usuario');
        return;
    }

    // Crear servicio MIC/DTA
    $micDtaService = new ArgentinaMicDtaService($company, $user, $config);

    // PASO 1: RegistrarTitEnvios
    $this->info("\n📋 PASO 1: RegistrarTitEnvios REAL");
    $this->info("----------------------------------");
    
    $titEnviosResult = $micDtaService->registrarTitEnvios($shipment);
    
    if (!$titEnviosResult['success']) {
        $this->error("❌ Error en RegistrarTitEnvios:");
        foreach ($titEnviosResult['errors'] as $error) {
            $this->error("   • {$error}");
        }
        return;
    }

    $this->info("✅ RegistrarTitEnvios exitoso");
    $this->info("   Transaction ID: {$titEnviosResult['transaction_id']}");
    $this->info("   TRACKs generados: " . count($titEnviosResult['tracks']));
    
    foreach ($titEnviosResult['tracks'] as $track) {
        $this->info("   • {$track}");
    }

    // PASO 2: RegistrarMicDta con TRACKs
    $this->info("\n📋 PASO 2: RegistrarMicDta con TRACKs REAL");
    $this->info("------------------------------------------");

    $micDtaResult = $micDtaService->sendMicDtaWithTracks($shipment, $titEnviosResult['tracks']);

    if (!$micDtaResult['success']) {
        $this->error("❌ Error en MIC/DTA:");
        foreach ($micDtaResult['errors'] as $error) {
            $this->error("   • {$error}");
        }
        return;
    }

    $this->info("✅ MIC/DTA exitoso");
    $this->info("   Transaction ID: {$micDtaResult['transaction_id']}");
    $this->info("   TRACKs usados: " . count($micDtaResult['tracks_used'] ?? []));

    // PASO 3: Verificar estado de TRACKs
    $this->verifyTracksStatus($titEnviosResult['tracks']);

    // PASO 4: Mostrar estadísticas
    $this->showServiceStatistics($micDtaService);
}

/**
 * SESIÓN 2: MÉTODOS FALTANTES DEL COMANDO TestTracksFlow
 * 
 * ARCHIVO: app/Console/Commands/TestTracksFlow.php
 * INSTRUCCIONES: Agregar estos métodos al final de la clase, antes del último }
 */

    /**
     * Verificar estado de TRACKs después del flujo
     */
    private function verifyTracksStatus(array $trackNumbers): void
    {
        $this->info("\n🔍 VERIFICACIÓN ESTADO TRACKs");
        $this->info("==============================");

        if (empty($trackNumbers)) {
            $this->warn("No hay TRACKs para verificar");
            return;
        }

        foreach ($trackNumbers as $trackNumber) {
            $track = WebserviceTrack::where('track_number', $trackNumber)->first();
            
            if ($track) {
                $info = $track->getTrackingInfo();
                $this->info("📦 {$trackNumber}:");
                $this->info("   Estado: {$info['status_description']}");
                $this->info("   Generado: {$info['generated_at']->format('Y-m-d H:i:s')}");
                
                if ($info['used_at']) {
                    $this->info("   Usado: {$info['used_at']->format('Y-m-d H:i:s')}");
                }
                
                if ($info['completed_at']) {
                    $this->info("   Completado: {$info['completed_at']->format('Y-m-d H:i:s')}");
                }
                
                $this->info("   Proceso: " . implode(' → ', $info['process_chain']));
                $this->info("   Referencia: {$info['reference']['type']} - {$info['reference']['number']}");
                
                // Verificar si está expirado
                if ($track->isExpired()) {
                    $this->warn("   ⚠️  TRACK EXPIRADO (más de 24 horas)");
                }
            } else {
                $this->error("❌ TRACK {$trackNumber} no encontrado en BD");
            }
        }
    }

    /**
     * Mostrar estadísticas del servicio
     */
    private function showServiceStatistics(ArgentinaMicDtaService $service): void
    {
        $this->info("\n📊 ESTADÍSTICAS SERVICIO");
        $this->info("=========================");

        try {
            // Estadísticas generales MIC/DTA
            $micDtaStats = $service->getCompanyStatistics();
            $this->displayStats('MIC/DTA', $micDtaStats);

            // Estadísticas TRACKs
            $tracksStats = $service->getTracksStatistics();
            $this->displayStats('TRACKs', $tracksStats);

        } catch (Exception $e) {
            $this->error("Error obteniendo estadísticas: " . $e->getMessage());
        }
    }

    /**
     * Mostrar estadísticas TRACKs del sistema
     */
    private function showTracksStatistics(): void
    {
        $this->info("📊 ESTADÍSTICAS GLOBALES TRACKs");
        $this->info("================================");

        try {
            $stats = [
                'total_tracks' => WebserviceTrack::count(),
                'tracks_generated' => WebserviceTrack::where('status', 'generated')->count(),
                'tracks_used_micdta' => WebserviceTrack::where('status', 'used_in_micdta')->count(),
                'tracks_completed' => WebserviceTrack::where('status', 'completed')->count(),
                'tracks_expired' => WebserviceTrack::where('status', 'generated')
                    ->where('generated_at', '<', now()->subHours(24))
                    ->count(),
                'tracks_error' => WebserviceTrack::where('status', 'error')->count(),
            ];

            foreach ($stats as $key => $value) {
                $label = str_replace('_', ' ', ucwords(str_replace('_', ' ', $key)));
                $this->info("   {$label}: {$value}");
            }

            // TRACKs por tipo
            $this->info("\n   Por tipo:");
            $byType = WebserviceTrack::selectRaw('track_type, count(*) as count')
                ->groupBy('track_type')
                ->pluck('count', 'track_type');

            foreach ($byType as $type => $count) {
                $this->info("     " . ucwords(str_replace('_', ' ', $type)) . ": {$count}");
            }

            // TRACKs por método webservice
            $this->info("\n   Por método:");
            $byMethod = WebserviceTrack::selectRaw('webservice_method, count(*) as count')
                ->groupBy('webservice_method')
                ->pluck('count', 'webservice_method');

            foreach ($byMethod as $method => $count) {
                $this->info("     {$method}: {$count}");
            }

            // TRACKs recientes
            $this->info("\n   TRACKs recientes (últimos 10):");
            $recent = WebserviceTrack::with(['shipment', 'webserviceTransaction'])
                ->latest()
                ->limit(10)
                ->get();

            if ($recent->count() > 0) {
                foreach ($recent as $track) {
                    $shipmentNumber = $track->shipment?->shipment_number ?? 'N/A';
                    $transactionId = $track->webserviceTransaction?->id ?? 'N/A';
                    $createdAt = $track->created_at->format('m-d H:i');
                    
                    $this->info("     {$track->track_number} ({$track->status}) - Ship: {$shipmentNumber} - TX: {$transactionId} - {$createdAt}");
                }
            } else {
                $this->info("     No hay TRACKs registrados");
            }

            // Empresas con más TRACKs
            $this->info("\n   Top empresas:");
            $topCompanies = WebserviceTrack::join('webservice_transactions', 'webservice_tracks.webservice_transaction_id', '=', 'webservice_transactions.id')
                ->join('companies', 'webservice_transactions.company_id', '=', 'companies.id')
                ->selectRaw('companies.legal_name, count(*) as tracks_count')
                ->groupBy('companies.id', 'companies.legal_name')
                ->orderByDesc('tracks_count')
                ->limit(5)
                ->get();

            foreach ($topCompanies as $company) {
                $this->info("     {$company->legal_name}: {$company->tracks_count} TRACKs");
            }

        } catch (Exception $e) {
            $this->error("Error obteniendo estadísticas: " . $e->getMessage());
        }
    }

    /**
     * Limpiar TRACKs de testing
     */
    private function cleanTestingTracks(): void
    {
        $this->info("🧹 LIMPIANDO TRACKs DE TESTING");
        $this->info("===============================");

        try {
            // Mostrar qué se va a eliminar
            $recentCount = WebserviceTrack::where('created_at', '>', now()->subDay())->count();
            $this->info("TRACKs encontrados (últimas 24h): {$recentCount}");

            if ($recentCount === 0) {
                $this->info("No hay TRACKs recientes para eliminar");
                return;
            }

            if (!$this->confirm('¿Eliminar TRACKs generados en las últimas 24 horas?')) {
                $this->info('Operación cancelada');
                return;
            }

            // Opción adicional: eliminar solo TRACKs de testing específicos
            if ($this->confirm('¿Eliminar solo TRACKs con prefijo TEST/TRACK?', true)) {
                $count = WebserviceTrack::where('created_at', '>', now()->subDay())
                    ->where(function ($query) {
                        $query->where('track_number', 'LIKE', 'TEST_%')
                              ->orWhere('track_number', 'LIKE', 'TRACK_%')
                              ->orWhere('track_number', 'LIKE', 'DEV_%');
                    })
                    ->delete();
                
                $this->info("✅ {$count} TRACKs de testing eliminados");
            } else {
                // Eliminar todos los TRACKs recientes
                $count = WebserviceTrack::where('created_at', '>', now()->subDay())->delete();
                $this->info("✅ {$count} TRACKs recientes eliminados");
            }

            // Opción para limpiar TRACKs huérfanos
            if ($this->confirm('¿Limpiar también TRACKs huérfanos (sin transacción válida)?')) {
                $orphanCount = WebserviceTrack::whereNotExists(function ($query) {
                    $query->select('id')
                          ->from('webservice_transactions')
                          ->whereColumn('webservice_transactions.id', 'webservice_tracks.webservice_transaction_id');
                })->delete();
                
                $this->info("✅ {$orphanCount} TRACKs huérfanos eliminados");
            }

        } catch (Exception $e) {
            $this->error("Error limpiando TRACKs: " . $e->getMessage());
        }
    }

    /**
     * Mostrar estadísticas formateadas
     */
    private function displayStats(string $type, array $stats): void
    {
        $this->info("\n{$type}:");
        
        if (empty($stats)) {
            $this->warn("   No hay estadísticas disponibles");
            return;
        }

        foreach ($stats as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $this->info("   {$key}: (vacío)");
                    continue;
                }
                
                $this->info("   {$key}:");
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        $this->info("     {$subKey}: " . json_encode($subValue));
                    } else {
                        $this->info("     {$subKey}: {$subValue}");
                    }
                }
            } elseif (is_bool($value)) {
                $this->info("   {$key}: " . ($value ? 'Sí' : 'No'));
            } elseif (is_null($value)) {
                $this->info("   {$key}: (null)");
            } else {
                $this->info("   {$key}: {$value}");
            }
        }
    }

    /**
     * Validar configuración antes del testing
     */
    private function validateTestingConfiguration(Company $company): bool
    {
        $this->info("\n🔍 VALIDANDO CONFIGURACIÓN");
        $this->info("===========================");

        $errors = [];
        $warnings = [];

        // 1. Verificar empresa activa
        if (!$company->active) {
            $errors[] = "Empresa inactiva";
        }

        if (!$company->ws_active) {
            $errors[] = "Webservices deshabilitados";
        }

        // 2. Verificar CUIT
        $cuit = preg_replace('/[^0-9]/', '', $company->tax_id ?? '');
        if (strlen($cuit) !== 11) {
            $errors[] = "CUIT inválido (debe tener 11 dígitos)";
        }

        // 3. Verificar usuarios asociados a la empresa
        $companyUsers = $this->getCompanyUsers($company);
        if ($companyUsers->isEmpty()) {
            $warnings[] = "No hay usuarios específicamente asociados a esta empresa";
        } else {
            $this->info("   Usuarios de empresa encontrados: " . $companyUsers->count());
        }

        // 4. Verificar configuración Argentina
        try {
            $argentinaData = $company->getArgentinaWebserviceData();
            if (empty($argentinaData['cuit'])) {
                $warnings[] = "CUIT Argentina no configurado específicamente";
            }
        } catch (Exception $e) {
            $warnings[] = "Error obteniendo configuración Argentina: " . $e->getMessage();
        }

        // 5. Verificar certificados (básico)
        if (empty($company->certificate_alias)) {
            $warnings[] = "Alias de certificado no configurado";
        }

        // Mostrar resultados
        if (!empty($errors)) {
            $this->error("❌ Errores encontrados:");
            foreach ($errors as $error) {
                $this->error("   • {$error}");
            }
            return false;
        }

        if (!empty($warnings)) {
            $this->warn("⚠️  Advertencias:");
            foreach ($warnings as $warning) {
                $this->warn("   • {$warning}");
            }
        }

        $this->info("✅ Configuración básica válida");
        return true;
    }

    /**
     * Mostrar información de debugging
     */
    private function showDebugInfo(Company $company, Shipment $shipment): void
{
    if (!$this->option('verbose')) {
        return;
    }

    $this->info("\n🐛 INFORMACIÓN DEBUG");
    $this->info("====================");

    $this->info("Empresa:");
    $this->info("   ID: {$company->id}");
    $this->info("   Legal Name: {$company->legal_name}");
    $this->info("   Tax ID: {$company->tax_id}");
    $this->info("   WS Active: " . ($company->ws_active ? 'Sí' : 'No'));

    $this->info("\nShipment:");
    $this->info("   ID: {$shipment->id}");
    $this->info("   Number: {$shipment->shipment_number}");
    $this->info("   Active: " . ($shipment->active ? 'Sí' : 'No'));

    // Verificar voyage de forma segura
    try {
        if ($shipment->voyage) {
            $this->info("\nVoyage:");
            $this->info("   Number: {$shipment->voyage->voyage_number}");
            
            if (isset($shipment->voyage->originPort)) {
                $this->info("   Departure Port: " . ($shipment->voyage->originPort->code ?? 'No configurado'));
            }
            
            if (isset($shipment->voyage->destinationPort)) {
                $this->info("   Arrival Port: " . ($shipment->voyage->destinationPort->code ?? 'No configurado'));
            }
        }
    } catch (Exception $e) {
        $this->warn("   Voyage: Error accediendo a datos - " . $e->getMessage());
    }

    // Verificar vessel de forma segura
    try {
        if ($shipment->vessel) {
            $this->info("\nVessel:");
            $this->info("   Name: {$shipment->vessel->name}");
            
            if (isset($shipment->vessel->vessel_code)) {
                $this->info("   Code: {$shipment->vessel->vessel_code}");
            }
        }
    } catch (Exception $e) {
        $this->warn("   Vessel: Error accediendo a datos - " . $e->getMessage());
    }
}

    /**
     * Obtener usuarios de una empresa específica
     */
    private function getCompanyUsers(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('userable_type', 'App\Models\Company')
            ->where('userable_id', $company->id)
            ->where('active', true)
            ->get();
    }
}