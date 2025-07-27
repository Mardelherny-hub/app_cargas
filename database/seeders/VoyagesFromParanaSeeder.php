<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voyage;
use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Seeder de Viajes desde PARANA.csv DEFINITIVAMENTE CORREGIDO
 * 
 * ✅ CORREGIDO: Usa ÚNICAMENTE campos que REALMENTE existen en migración corregida
 * ✅ VERIFICADO: Todos los campos validados contra create_voyages_table.php corregida
 * ✅ COMPATIBILIDAD: 100% coherente con fillable y migración actualizados
 * ✅ DEPENDENCIAS: Compatible con WebserviceBasicDependenciesSeeder
 * 
 * DATOS REALES UTILIZADOS:
 * - MAERSK LINE ARGENTINA S.A. (tax_id: 30688415531)
 * - Voyage Numbers: V022NB, V023NB, V024NB, etc.
 * - Rutas: ARBUE → PYTVT (Buenos Aires → Terminal Villeta Paraguay)
 * - Estados realistas para testing de webservices
 */
class VoyagesFromParanaSeeder extends Seeder
{
    /**
     * Datos reales extraídos de PARANA.csv
     */
    private const PARANA_VOYAGES = [
        [
            'voyage_number' => 'V022NB',
            'internal_reference' => 'PAR13001-V022', 
            'vessel_name' => 'PAR13001',
            'containers_count' => 28,
            'cargo_weight' => 950.5,
        ],
        [
            'voyage_number' => 'V023NB',
            'internal_reference' => 'PAR13002-V023',
            'vessel_name' => 'PAR13002', 
            'containers_count' => 32,
            'cargo_weight' => 1085.2,
        ],
        [
            'voyage_number' => 'V024NB',
            'internal_reference' => 'PAR13003-V024',
            'vessel_name' => 'PAR13003',
            'containers_count' => 24,
            'cargo_weight' => 876.8,
        ],
        [
            'voyage_number' => 'V025NB',
            'internal_reference' => 'PAR13004-V025',
            'vessel_name' => 'PAR13004',
            'containers_count' => 30,
            'cargo_weight' => 1150.0,
        ],
        [
            'voyage_number' => 'V026NB', 
            'internal_reference' => 'PAR13005-V026',
            'vessel_name' => 'PAR13005',
            'containers_count' => 26,
            'cargo_weight' => 925.4,
        ],
        [
            'voyage_number' => 'V027NB',
            'internal_reference' => 'PAR13006-V027', 
            'vessel_name' => 'PAR13006',
            'containers_count' => 35,
            'cargo_weight' => 1250.6,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando viajes desde datos PARANA.csv (CAMPOS CORREGIDOS)...');

        DB::beginTransaction();

        try {
            // 1. Verificar dependencias
            if (!$this->verifyDependencies()) {
                return;
            }

            // 2. Obtener referencias necesarias
            $references = $this->getReferences();
            $this->command->info("✅ Referencias obtenidas correctamente");

            // 3. Crear capitanes básicos si no existen
            $captains = $this->createBasicCaptainsIfNeeded($references);
            $this->command->info("✅ Capitanes verificados/creados");

            // 4. Limpiar viajes existentes de testing
            $this->cleanExistingTestVoyages();

            // 5. Crear embarcaciones si no existen
            $vessels = $this->createVesselsIfNeeded($references);
            $this->command->info("✅ Embarcaciones verificadas/creadas");

            // 6. Crear viajes desde datos PARANA
            $createdVoyages = [];
            foreach (self::PARANA_VOYAGES as $index => $voyageData) {
                $voyage = $this->createVoyageFromParanaData($voyageData, $references, $vessels, $captains, $index);
                $createdVoyages[] = $voyage;
                
                $this->command->info("✅ Viaje creado: {$voyage->voyage_number} - {$voyage->internal_reference}");
            }

            // 7. Crear algunos viajes históricos adicionales
            $historicalCount = $this->createHistoricalVoyages($references, $vessels, $captains);
            $this->command->info("✅ Creados {$historicalCount} viajes históricos");

            // 8. Crear algunos viajes futuros
            $futureCount = $this->createFutureVoyages($references, $vessels, $captains);
            $this->command->info("✅ Creados {$futureCount} viajes futuros");

            DB::commit();

            $totalVoyages = count($createdVoyages) + $historicalCount + $futureCount;
            $this->command->info("🎉 Seeder completado exitosamente!");
            $this->command->info("📊 Total de viajes creados: {$totalVoyages}");
            $this->displaySummary($createdVoyages);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Error en seeder: " . $e->getMessage());
            $this->command->error("📍 Archivo: " . $e->getFile() . " línea " . $e->getLine());
            throw $e;
        }
    }

    /**
     * Verificar dependencias necesarias (CORREGIDO: usa alpha2_code)
     */
    private function verifyDependencies(): bool
    {
        $dependencies = [
            ['model' => Country::class, 'condition' => ['alpha2_code' => 'AR'], 'name' => 'Argentina'],
            ['model' => Country::class, 'condition' => ['alpha2_code' => 'PY'], 'name' => 'Paraguay'],
            ['model' => Company::class, 'condition' => ['tax_id' => '30688415531'], 'name' => 'MAERSK'],
            ['model' => Port::class, 'condition' => ['code' => 'ARBUE'], 'name' => 'Puerto Buenos Aires'],
            ['model' => Port::class, 'condition' => ['code' => 'PYTVT'], 'name' => 'Terminal Villeta'],
        ];

        foreach ($dependencies as $dep) {
            if (!$dep['model']::where($dep['condition'])->exists()) {
                $this->command->error("❌ {$dep['name']} no encontrado. Ejecutar WebserviceBasicDependenciesSeeder primero.");
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener referencias necesarias (CORREGIDO: usa alpha2_code)
     */
    private function getReferences(): array
    {
        return [
            'maersk' => Company::where('tax_id', '30688415531')->first(),
            'argentina' => Country::where('alpha2_code', 'AR')->first(),
            'paraguay' => Country::where('alpha2_code', 'PY')->first(),
            'arbue' => Port::where('code', 'ARBUE')->first(),
            'pytvt' => Port::where('code', 'PYTVT')->first(),
            'admin_user' => User::where('email', 'admin.webservices@maersk.com.ar')->first() ?? User::first(),
        ];
    }

    /**
     * Crear capitanes básicos si no existen
     */
    private function createBasicCaptainsIfNeeded(array $references): \Illuminate\Database\Eloquent\Collection
    {
        // Verificar si ya existen capitanes
        $existingCaptains = Captain::where('active', true)->get();
        
        if ($existingCaptains->count() >= 3) {
            return $existingCaptains;
        }

        // Crear capitanes básicos para testing
        $captainsData = [
            [
                'first_name' => 'Carlos',
                'last_name' => 'Rodriguez',
                'email' => 'carlos.rodriguez@maersk.com.ar',
                'country_id' => $references['argentina']->id,
                'license_class' => 'master',
            ],
            [
                'first_name' => 'Miguel',
                'last_name' => 'Santos',
                'email' => 'miguel.santos@maersk.com.ar',
                'country_id' => $references['argentina']->id,
                'license_class' => 'chief_officer',
            ],
            [
                'first_name' => 'Roberto',
                'last_name' => 'Fernandez',
                'email' => 'roberto.fernandez@navegacion.com.py',
                'country_id' => $references['paraguay']->id,
                'license_class' => 'master',
            ],
        ];

        foreach ($captainsData as $data) {
            Captain::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'birth_date' => '1975-01-01',
                    'gender' => 'male',
                    'phone' => '+54 11 1234-5678',
                    'license_number' => 'LIC' . rand(100000, 999999),
                    'license_issued_date' => now()->subYears(5),
                    'license_expires_date' => now()->addYears(5),
                    'experience_years' => 15,
                    'active' => true,
                    'available' => true,
                    'created_date' => now(),
                ])
            );
        }

        return Captain::where('active', true)->get();
    }

    /**
     * Limpiar viajes de testing existentes
     */
    private function cleanExistingTestVoyages(): void
    {
        $deletedCount = Voyage::where('voyage_number', 'LIKE', 'V0%NB')
            ->orWhere('internal_reference', 'LIKE', 'PAR13%')
            ->delete();

        if ($deletedCount > 0) {
            $this->command->info("🧹 Eliminados {$deletedCount} viajes de testing existentes");
        }
    }

    /**
     * Crear embarcaciones si no existen (usando campos REALES de Vessel)
     */
    private function createVesselsIfNeeded(array $references): array
    {
        $vesselsData = [
            'PAR13001' => ['name' => 'PAR13001', 'registration' => 'PAR13001', 'capacity' => 1200.00, 'containers' => 48],
            'PAR13002' => ['name' => 'PAR13002', 'registration' => 'PAR13002', 'capacity' => 1100.00, 'containers' => 44],
            'PAR13003' => ['name' => 'PAR13003', 'registration' => 'PAR13003', 'capacity' => 950.00, 'containers' => 38],
            'PAR13004' => ['name' => 'PAR13004', 'registration' => 'PAR13004', 'capacity' => 1000.00, 'containers' => 40],
            'PAR13005' => ['name' => 'PAR13005', 'registration' => 'PAR13005', 'capacity' => 1150.00, 'containers' => 46],
            'PAR13006' => ['name' => 'PAR13006', 'registration' => 'PAR13006', 'capacity' => 1300.00, 'containers' => 52],
        ];

        $vessels = [];
        $vesselTypeId = 1; // Asumiendo que existe vessel_type con ID 1

        foreach ($vesselsData as $vesselName => $data) {
            $vessel = Vessel::updateOrCreate(
                ['registration_number' => $data['registration']],
                [
                    'name' => $data['name'],
                    'company_id' => $references['maersk']->id,
                    'vessel_type_id' => $vesselTypeId,
                    'flag_country_id' => $references['argentina']->id,
                    'home_port_id' => $references['arbue']->id,
                    'current_port_id' => $references['arbue']->id,
                    'length_meters' => 50.0,
                    'beam_meters' => 12.0,
                    'draft_meters' => 3.5,
                    'depth_meters' => 8.0,
                    'gross_tonnage' => $data['capacity'],
                    'net_tonnage' => $data['capacity'] * 0.8,
                    'deadweight_tons' => $data['capacity'],
                    'cargo_capacity_tons' => $data['capacity'],
                    'container_capacity' => $data['containers'],
                    'max_cargo_capacity' => $data['capacity'],
                    'operational_status' => 'active',
                    'available_for_charter' => true,
                    'charter_rate' => 1500.00,
                    'active' => true,
                    'verified' => true,
                    'inspection_current' => true,
                    'insurance_current' => true,
                    'certificates_current' => true,
                    'created_by_user_id' => $references['admin_user']->id,
                    'last_updated_by_user_id' => $references['admin_user']->id,
                    'created_date' => now(),
                    'last_updated_date' => now(),
                ]
            );

            $vessels[$vesselName] = $vessel;
        }

        return $vessels;
    }

    /**
     * Crear viaje individual desde datos PARANA (CORREGIDO: usa TODOS los campos de migración)
     */
    private function createVoyageFromParanaData(array $data, array $references, array $vessels, $captains, int $index): Voyage
    {
        // Calcular fechas realistas
        $baseDate = now()->subDays(30)->addDays($index * 7);
        $departureDate = $baseDate->copy();
        $estimatedArrival = $departureDate->copy()->addHours(48);
        
        // Determinar estado según fecha
        $status = $this->determineVoyageStatus($departureDate);
        $actualArrival = null;
        
        if (in_array($status, ['at_destination', 'completed'])) {
            $actualArrival = $estimatedArrival->copy()->addMinutes(rand(-60, 120));
        }

        // Obtener embarcación y capitán
        $vessel = $vessels[$data['vessel_name']] ?? $vessels['PAR13001'];
        $captain = $captains->random();

        return Voyage::create([
            // ✅ BÁSICOS
            'voyage_number' => $data['voyage_number'],
            'internal_reference' => $data['internal_reference'],
            'company_id' => $references['maersk']->id,
            'lead_vessel_id' => $vessel->id,
            'captain_id' => $captain->id,
            'origin_country_id' => $references['argentina']->id,
            'origin_port_id' => $references['arbue']->id,
            'destination_country_id' => $references['paraguay']->id,
            'destination_port_id' => $references['pytvt']->id,

            // ✅ FECHAS - TODOS LOS CAMPOS DE LA MIGRACIÓN
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrival,
            'actual_arrival_date' => $actualArrival,
            'customs_clearance_date' => $status === 'completed' ? $estimatedArrival->copy()->subHours(6) : null,
            'customs_clearance_deadline' => $estimatedArrival->copy()->addHours(24), // ✅ SÍ EXISTE!
            'cargo_loading_start' => $departureDate->copy()->subHours(4),
            'cargo_loading_end' => $departureDate->copy()->subHours(1),
            'cargo_discharge_start' => $actualArrival?->copy(),
            'cargo_discharge_end' => $actualArrival?->copy()->addHours(3),

            // ✅ TIPO Y CARACTERÍSTICAS - TODOS LOS CAMPOS
            'voyage_type' => 'single_vessel',
            'cargo_type' => 'export',
            'is_consolidated' => false,
            'has_transshipment' => false,
            'requires_pilot' => true,
            'is_convoy' => false, // ✅ SÍ EXISTE!
            'vessel_count' => 1,  // ✅ SÍ EXISTE!

            // ✅ STATUS
            'status' => $status,
            'priority_level' => 'normal', // ✅ SÍ EXISTE!

            // ✅ CAPACIDADES Y ESTADÍSTICAS - CAMPOS CRÍTICOS
            'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons ?? 1200,
            'total_container_capacity' => $data['containers_count'],
            'total_cargo_weight_loaded' => $data['cargo_weight'],
            'total_containers_loaded' => $data['containers_count'],
            'capacity_utilization_percentage' => min(100, ($data['cargo_weight'] / ($vessel->cargo_capacity_tons ?? 1200)) * 100),

            // ✅ RESUMEN DE CARGA (ALIAS)
            'total_containers' => $data['containers_count'],
            'total_cargo_weight' => $data['cargo_weight'],
            'total_cargo_volume' => $data['cargo_weight'] * 1.2,
            'total_bills_of_lading' => rand(1, 3),
            'total_clients' => rand(1, 2),

            // ✅ REQUERIMIENTOS ESPECIALES - TODOS LOS CAMPOS
            'requires_escort' => false,      // ✅ SÍ EXISTE!
            'hazardous_cargo' => false,      // ✅ SÍ EXISTE!
            'refrigerated_cargo' => false,   // ✅ SÍ EXISTE!
            'oversized_cargo' => false,      // ✅ SÍ EXISTE!
            'dangerous_cargo' => false,      // ✅ ALIAS

            // ✅ WEBSERVICE INTEGRATION
            'argentina_voyage_id' => null,
            'paraguay_voyage_id' => null,
            'argentina_status' => 'pending',
            'paraguay_status' => 'pending',
            'argentina_sent_at' => null,
            'paraguay_sent_at' => null,

            // ✅ INFORMACIÓN FINANCIERA
            'estimated_cost' => rand(75000, 95000),
            'actual_cost' => $status === 'completed' ? rand(78000, 98000) : null,
            'cost_currency' => 'USD',
            'estimated_freight_cost' => rand(75000, 95000), // ✅ ALIAS
            'actual_freight_cost' => $status === 'completed' ? rand(78000, 98000) : null,
            'fuel_cost' => rand(15000, 25000),
            'port_charges' => rand(5000, 8000),
            'total_voyage_cost' => null,
            'currency_code' => 'USD', // ✅ ALIAS

            // ✅ CONDICIONES CLIMÁTICAS Y NOTAS
            'weather_conditions' => ['condition' => 'favorable', 'wind' => '15 km/h SE'],
            'route_conditions' => ['river_level' => 'normal', 'traffic' => 'light'],
            'river_conditions' => ['nivel' => 'normal', 'corriente' => 'favorable'], // ✅ ALIAS
            'special_instructions' => "Viaje {$data['voyage_number']} - Embarcación {$data['vessel_name']}",
            'operational_notes' => "Datos reales del manifiesto PARANA - {$data['containers_count']} contenedores",
            'voyage_notes' => "Carga estándar de contenedores", // ✅ ALIAS
            'delays_explanation' => null,

            // ✅ DOCUMENTOS Y APROBACIONES - TODOS LOS CAMPOS
            'required_documents' => ['manifest', 'crew_list', 'customs_declaration'],
            'uploaded_documents' => $status !== 'planning' ? ['manifest'] : [],
            'customs_approved' => in_array($status, ['in_transit', 'at_destination', 'completed']),
            'port_authority_approved' => in_array($status, ['in_transit', 'at_destination', 'completed']),
            'all_documents_ready' => !in_array($status, ['planning']),
            'safety_approved' => !in_array($status, ['planning']),      // ✅ SÍ EXISTE!
            'customs_cleared_origin' => in_array($status, ['in_transit', 'at_destination', 'completed']),
            'customs_cleared_destination' => in_array($status, ['at_destination', 'completed']),
            'documentation_complete' => !in_array($status, ['planning']),
            'environmental_approved' => true,

            // ✅ FECHAS DE APROBACIÓN
            'safety_approval_date' => !in_array($status, ['planning']) ? $baseDate->copy()->subDays(2) : null,
            'customs_approval_date' => in_array($status, ['in_transit', 'at_destination', 'completed']) ? $baseDate->copy()->subDays(1) : null,
            'environmental_approval_date' => $baseDate->copy()->subDays(3),

            // ✅ EMERGENCIA Y SEGURIDAD
            'emergency_contacts' => [
                ['name' => 'Centro Control Maersk', 'phone' => '+54 11 4878-3000']
            ],
            'safety_equipment' => ['life_jackets', 'fire_extinguisher', 'emergency_beacon'],
            'safety_notes' => 'Equipamiento de seguridad verificado',

            // ✅ SEGUIMIENTO DE RENDIMIENTO
            'distance_nautical_miles' => 245.5,
            'average_speed_knots' => 8.5,
            'transit_time_hours' => $status === 'completed' ? 48 : null,
            'fuel_consumption' => $status === 'completed' ? 2850.0 : null,
            'fuel_efficiency' => $status === 'completed' ? 3.2 : null,

            // ✅ COMUNICACIÓN
            'communication_frequency' => 'VHF-16',
            'reporting_schedule' => ['times' => ['08:00', '14:00', '20:00']],
            'last_position_report' => $status === 'in_transit' ? now()->subHours(2) : null,

            // ✅ FLAGS DE ESTADO
            'active' => !in_array($status, ['completed', 'cancelled']),
            'archived' => $status === 'completed',
            'requires_follow_up' => false,
            'follow_up_reason' => null,
            'has_incidents' => false,

            // ✅ AUDITORÍA
            'created_date' => $baseDate->copy()->subDays(3),
            'created_by_user_id' => $references['admin_user']->id,
            'last_updated_date' => now(),
            'last_updated_by_user_id' => $references['admin_user']->id,
        ]);
    }

    /**
     * Determinar estado de viaje según fecha
     */
    private function determineVoyageStatus(Carbon $departureDate): string
    {
        $now = now();
        $daysDiff = $now->diffInDays($departureDate, false);
        
        if ($daysDiff > 2) {
            return 'planning';
        } elseif ($daysDiff > 0) {
            return 'approved';
        } elseif ($daysDiff >= -2) {
            return 'in_transit';
        } elseif ($daysDiff >= -5) {
            return 'at_destination';
        } else {
            return 'completed';
        }
    }

    /**
     * Crear viajes históricos adicionales
     */
    private function createHistoricalVoyages(array $references, array $vessels, $captains): int
    {
        $historicalCount = 0;
        $baseVoyageNumber = 15;
        
        for ($i = 0; $i < 3; $i++) {
            $voyageNum = str_pad($baseVoyageNumber - $i, 3, '0', STR_PAD_LEFT);
            $vessel = collect($vessels)->random();
            $captain = $captains->random();
            
            $departureDate = now()->subDays(60 + ($i * 7));
            $arrivalDate = $departureDate->copy()->addHours(48 + rand(-6, 12));
            
            Voyage::create([
                'voyage_number' => "V{$voyageNum}NB",
                'internal_reference' => "HIST-{$voyageNum}",
                'company_id' => $references['maersk']->id,
                'lead_vessel_id' => $vessel->id,
                'captain_id' => $captain->id,
                'origin_country_id' => $references['argentina']->id,
                'origin_port_id' => $references['arbue']->id,
                'destination_country_id' => $references['paraguay']->id,
                'destination_port_id' => $references['pytvt']->id,
                'departure_date' => $departureDate,
                'estimated_arrival_date' => $departureDate->copy()->addHours(48),
                'actual_arrival_date' => $arrivalDate,
                'customs_clearance_date' => $arrivalDate->copy()->subHours(6),
                'customs_clearance_deadline' => $arrivalDate->copy()->addHours(12),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_consolidated' => false,
                'has_transshipment' => false,
                'requires_pilot' => true,
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 1200,
                'total_container_capacity' => rand(20, 40),
                'total_cargo_weight_loaded' => rand(800, 1100),
                'total_containers_loaded' => rand(20, 40),
                'capacity_utilization_percentage' => rand(80, 95),
                'status' => 'completed',
                'priority_level' => 'normal',
                'active' => false,
                'archived' => true,
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => true,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => $departureDate->copy()->subDays(2),
                'customs_approval_date' => $departureDate->copy()->subDays(1),
                'environmental_approval_date' => $departureDate->copy()->subDays(3),
                'created_date' => $departureDate->copy()->subDays(5),
                'created_by_user_id' => $references['admin_user']->id,
                'last_updated_date' => $arrivalDate,
                'last_updated_by_user_id' => $references['admin_user']->id,
            ]);
            
            $historicalCount++;
        }
        
        return $historicalCount;
    }

    /**
     * Crear viajes futuros
     */
    private function createFutureVoyages(array $references, array $vessels, $captains): int
    {
        $futureCount = 0;
        $baseVoyageNumber = 28;
        
        for ($i = 1; $i <= 2; $i++) {
            $voyageNum = str_pad($baseVoyageNumber + $i, 3, '0', STR_PAD_LEFT);
            $vessel = collect($vessels)->random();
            $captain = $captains->random();
            
            $departureDate = now()->addDays($i * 7);
            
            Voyage::create([
                'voyage_number' => "V{$voyageNum}NB",
                'internal_reference' => "FUTURE-{$voyageNum}",
                'company_id' => $references['maersk']->id,
                'lead_vessel_id' => $vessel->id,
                'captain_id' => $captain->id,
                'origin_country_id' => $references['argentina']->id,
                'origin_port_id' => $references['arbue']->id,
                'destination_country_id' => $references['paraguay']->id,
                'destination_port_id' => $references['pytvt']->id,
                'departure_date' => $departureDate,
                'estimated_arrival_date' => $departureDate->copy()->addHours(48),
                'customs_clearance_deadline' => $departureDate->copy()->addHours(72),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_consolidated' => false,
                'has_transshipment' => false,
                'requires_pilot' => true,
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 1200,
                'total_container_capacity' => rand(25, 35),
                'total_cargo_weight_loaded' => 0,
                'total_containers_loaded' => 0,
                'capacity_utilization_percentage' => 0,
                'status' => $i <= 1 ? 'approved' : 'planning',
                'priority_level' => 'normal',
                'active' => true,
                'archived' => false,
                'safety_approved' => $i <= 1,
                'customs_cleared_origin' => false,
                'customs_cleared_destination' => false,
                'documentation_complete' => $i <= 1,
                'environmental_approved' => $i <= 1,
                'safety_approval_date' => $i <= 1 ? now()->subDays(rand(1, 3)) : null,
                'customs_approval_date' => null,
                'environmental_approval_date' => $i <= 1 ? now()->subDays(rand(2, 5)) : null,
                'created_date' => now()->subDays(rand(1, 5)),
                'created_by_user_id' => $references['admin_user']->id,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => $references['admin_user']->id,
            ]);
            
            $futureCount++;
        }
        
        return $futureCount;
    }

    /**
     * Mostrar resumen de viajes creados
     */
    private function displaySummary(array $createdVoyages): void
    {
        $this->command->line('');
        $this->command->info('📋 RESUMEN DE VIAJES CREADOS:');
        $this->command->line('');
        
        $this->command->info('🚢 VIAJES PRINCIPALES (PARANA.csv):');
        foreach ($createdVoyages as $voyage) {
            $this->command->line("   • {$voyage->voyage_number}: {$voyage->status} - {$voyage->total_containers_loaded} contenedores");
        }
        
        $this->command->line('');
        $this->command->info('✅ COMPLETAMENTE CORREGIDO: Usa TODOS los campos de migración');
        $this->command->info('✅ COMPATIBLE: Con WebserviceBasicDependenciesSeeder');
        $this->command->info('🎯 LISTO PARA WEBSERVICES: Todos los viajes tienen datos compatibles');
        $this->command->info('📡 PRÓXIMO PASO: Ejecutar WebserviceTransactionsSeeder');
    }
}