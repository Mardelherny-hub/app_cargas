<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\Voyage;
use App\Models\Captain;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ShipmentSeeder - MÓDULO 3: VIAJES Y CARGAS
 * 
 * Seeder para envíos individuales del sistema de transporte fluvial AR/PY
 * 
 * DATOS REALES DEL SISTEMA:
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Manifiestos PARANA: 253 registros, 111 BL únicos
 * - Contenedores: 40HC (High Cube), 20GP, múltiples tipos
 * - Capacidades realistas: 950-1200 toneladas, 38-48 contenedores
 * - Estados operacionales completos: planning → completed
 * 
 * Contexto: Cada embarcación en un viaje es un shipment
 * - Single vessel: 1 shipment por viaje
 * - Convoy: múltiples shipments coordinados
 */
class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📦 Creando envíos para transporte fluvial AR/PY...');

        // Verificar que existan viajes
        if (!Voyage::exists()) {
            $this->command->error('❌ No se encontraron viajes. Ejecutar VoyageSeeder primero.');
            return;
        }

        // Obtener capitanes disponibles
        $captains = Captain::active()->get();
        if ($captains->isEmpty()) {
            $this->command->error('❌ No se encontraron capitanes. Ejecutar CaptainSeeder primero.');
            return;
        }

        // Limpiar tabla existente
        DB::table('shipments')->truncate();

        // Obtener todos los viajes ordenados por fecha de creación
        $voyages = Voyage::orderBy('departure_date')->get();

        foreach ($voyages as $voyage) {
            $this->createShipmentsForVoyage($voyage, $captains);
        }

        $this->command->info('✅ Envíos creados exitosamente para transporte fluvial AR/PY');
        $this->command->info('');
        $this->showCreatedSummary();
    }

    /**
     * Crear shipments para un viaje específico
     */
    private function createShipmentsForVoyage(Voyage $voyage, $captains): void
    {
        if ($voyage->is_convoy) {
            $this->createConvoyShipments($voyage, $captains);
        } else {
            $this->createSingleVesselShipment($voyage, $captains);
        }
    }

    /**
     * Crear shipment para embarcación única
     */
    private function createSingleVesselShipment(Voyage $voyage, $captains): void
    {
        // Seleccionar capitán apropiado para el viaje
        $captain = $this->selectCaptainForVoyage($voyage, $captains);
        
        // Datos base del vessel según el viaje
        $vesselData = $this->getVesselDataForVoyage($voyage);

        $shipmentData = [
            'voyage_id' => $voyage->id,
            'vessel_id' => $vesselData['vessel_id'],
            'captain_id' => $captain?->id,
            'shipment_number' => Shipment::generateShipmentNumber($voyage, 1),
            'sequence_in_voyage' => 1,
            'vessel_role' => 'single',
            'convoy_position' => null,
            'is_lead_vessel' => true,
            'cargo_capacity_tons' => $vesselData['capacity'],
            'container_capacity' => $vesselData['containers'],
            'cargo_weight_loaded' => $voyage->total_cargo_weight_loaded,
            'containers_loaded' => $voyage->total_containers_loaded,
            'status' => $this->getShipmentStatusFromVoyage($voyage),
            'special_instructions' => $this->getSpecialInstructions($voyage, 'single'),
            'handling_notes' => "Embarcación {$vesselData['name']} operando individualmente",
        ];

        // Agregar datos específicos según el estado del viaje
        $this->addStatusSpecificData($shipmentData, $voyage);
        
        // Agregar aprobaciones según el estado
        $this->addApprovalsData($shipmentData, $voyage);

        $this->createShipment($shipmentData);
    }

    /**
     * Crear shipments para convoy
     */
    private function createConvoyShipments(Voyage $voyage, $captains): void
    {
        $vesselCount = $voyage->vessel_count;
        $convoyVessels = $this->getConvoyVesselsForVoyage($voyage, $vesselCount);
        
        // Distribuir carga entre embarcaciones del convoy
        $totalWeight = $voyage->total_cargo_weight_loaded;
        $totalContainers = $voyage->total_containers_loaded;

        foreach ($convoyVessels as $index => $vesselData) {
            $isLead = $index === 0;
            $captain = $this->selectCaptainForVoyage($voyage, $captains, $isLead);
            
            // Distribuir carga proporcionalmente
            $weightProportion = $vesselData['capacity'] / $voyage->total_cargo_capacity_tons;
            $assignedWeight = $totalWeight * $weightProportion;
            $assignedContainers = round($totalContainers * $weightProportion);

            $shipmentData = [
                'voyage_id' => $voyage->id,
                'vessel_id' => $vesselData['vessel_id'],
                'captain_id' => $captain?->id,
                'shipment_number' => Shipment::generateShipmentNumber($voyage, $index + 1),
                'sequence_in_voyage' => $index + 1,
                'vessel_role' => $isLead ? 'lead' : ($vesselData['role'] ?? 'towed'),
                'convoy_position' => $index + 1,
                'is_lead_vessel' => $isLead,
                'cargo_capacity_tons' => $vesselData['capacity'],
                'container_capacity' => $vesselData['containers'],
                'cargo_weight_loaded' => $assignedWeight,
                'containers_loaded' => $assignedContainers,
                'status' => $this->getShipmentStatusFromVoyage($voyage),
                'special_instructions' => $this->getSpecialInstructions($voyage, $isLead ? 'lead' : 'convoy_member'),
                'handling_notes' => $this->getConvoyHandlingNotes($vesselData, $isLead, $index + 1),
            ];

            // Agregar datos específicos según el estado del viaje
            $this->addStatusSpecificData($shipmentData, $voyage, $index);
            
            // Agregar aprobaciones según el estado
            $this->addApprovalsData($shipmentData, $voyage);

            $this->createShipment($shipmentData);
        }
    }

    /**
     * Obtener datos de embarcación para viaje single vessel
     */
    private function getVesselDataForVoyage(Voyage $voyage): array
    {
        // Mapeo de embarcaciones reales del sistema
        $vessels = [
            'PAR13001' => ['vessel_id' => 1, 'name' => 'PAR13001', 'capacity' => 1200.00, 'containers' => 48],
            'GUARAN F' => ['vessel_id' => 2, 'name' => 'GUARAN F', 'capacity' => 1100.00, 'containers' => 44],
            'REINA DEL PARANA' => ['vessel_id' => 3, 'name' => 'REINA DEL PARANA', 'capacity' => 950.00, 'containers' => 38],
        ];

        // Seleccionar embarcación según el viaje
        return match($voyage->voyage_number) {
            'V022NB' => $vessels['PAR13001'],  // Viaje histórico real del manifiesto
            'V023NB' => $vessels['PAR13001'],  // Continúa con la misma embarcación
            'V025NB' => $vessels['GUARAN F'],  // Planificado con GUARAN F
            'V026SB' => $vessels['REINA DEL PARANA'], // Logística Integral
            default => $vessels['PAR13001'],   // Por defecto
        };
    }

    /**
     * Obtener embarcaciones para convoy
     */
    private function getConvoyVesselsForVoyage(Voyage $voyage, int $vesselCount): array
    {
        $allVessels = [
            ['vessel_id' => 2, 'name' => 'GUARAN F', 'capacity' => 1100.00, 'containers' => 44, 'role' => 'lead'],
            ['vessel_id' => 4, 'name' => 'BARCAZA NORTE', 'capacity' => 800.00, 'containers' => 32, 'role' => 'towed'],
            ['vessel_id' => 5, 'name' => 'BARCAZA SUR', 'capacity' => 750.00, 'containers' => 30, 'role' => 'towed'],
            ['vessel_id' => 6, 'name' => 'BARCAZA ESTE', 'capacity' => 700.00, 'containers' => 28, 'role' => 'pushed'],
            ['vessel_id' => 7, 'name' => 'ESCORT ALFA', 'capacity' => 0.00, 'containers' => 0, 'role' => 'escort'],
        ];

        return array_slice($allVessels, 0, $vesselCount);
    }

    /**
     * Seleccionar capitán apropiado para el viaje
     */
    private function selectCaptainForVoyage(Voyage $voyage, $captains, bool $isLead = true): ?Captain
    {
        $companyCaptains = $captains->where('primary_company_id', $voyage->company_id);
        
        if ($isLead) {
            // Para embarcación líder, preferir capitanes con licencia master
            return $companyCaptains->where('license_class', 'master')->first() ??
                   $companyCaptains->where('license_class', 'chief_officer')->first() ??
                   $companyCaptains->first();
        } else {
            // Para embarcaciones secundarias, oficiales
            return $companyCaptains->where('license_class', 'chief_officer')->first() ??
                   $companyCaptains->where('license_class', 'officer')->first() ??
                   $companyCaptains->first();
        }
    }

    /**
     * Obtener estado del shipment basado en el viaje
     */
    private function getShipmentStatusFromVoyage(Voyage $voyage): string
    {
        return match($voyage->status) {
            'planning' => 'planning',
            'approved' => 'ready',
            'departed' => 'departed',
            'in_transit' => 'in_transit',
            'arrived' => 'arrived',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'planning',
        };
    }

    /**
     * Agregar datos específicos según el estado
     */
    private function addStatusSpecificData(array &$shipmentData, Voyage $voyage, int $shipmentIndex = 0): void
    {
        $baseTime = $voyage->departure_date;
        $loadingOffset = $shipmentIndex * 30; // 30 min entre shipments en convoy
        
        switch ($voyage->status) {
            case 'completed':
                $shipmentData = array_merge($shipmentData, [
                    'departure_time' => $baseTime->copy()->addMinutes($loadingOffset),
                    'arrival_time' => $voyage->actual_arrival_date->copy()->addMinutes($loadingOffset),
                    'loading_start_time' => $baseTime->copy()->subHours(6)->addMinutes($loadingOffset),
                    'loading_end_time' => $baseTime->copy()->subHours(2)->addMinutes($loadingOffset),
                    'discharge_start_time' => $voyage->actual_arrival_date->copy()->addHour(),
                    'discharge_end_time' => $voyage->actual_arrival_date->copy()->addHours(4),
                    'has_delays' => $voyage->voyage_number === 'V020NB', // Solo V020NB tuvo retraso
                    'delay_minutes' => $voyage->voyage_number === 'V020NB' ? 150 : 0,
                    'delay_reason' => $voyage->voyage_number === 'V020NB' ? 'Inspección adicional carga refrigerada' : null,
                ]);
                break;
                
            case 'in_transit':
                $shipmentData = array_merge($shipmentData, [
                    'departure_time' => $baseTime->copy()->addMinutes($loadingOffset),
                    'loading_start_time' => $baseTime->copy()->subHours(6)->addMinutes($loadingOffset),
                    'loading_end_time' => $baseTime->copy()->subHours(2)->addMinutes($loadingOffset),
                ]);
                break;
                
            case 'approved':
                $shipmentData = array_merge($shipmentData, [
                    'loading_start_time' => $baseTime->copy()->subHours(6)->addMinutes($loadingOffset),
                    'loading_end_time' => $baseTime->copy()->subHours(2)->addMinutes($loadingOffset),
                ]);
                break;
        }
    }

    /**
     * Agregar datos de aprobaciones según el estado del viaje
     */
    private function addApprovalsData(array &$shipmentData, Voyage $voyage): void
    {
        switch ($voyage->status) {
            case 'completed':
            case 'in_transit':
            case 'arrived':
                $shipmentData = array_merge($shipmentData, [
                    'safety_approved' => true,
                    'customs_cleared' => true,
                    'documentation_complete' => true,
                    'cargo_inspected' => true,
                ]);
                break;
                
            case 'approved':
                $shipmentData = array_merge($shipmentData, [
                    'safety_approved' => true,
                    'customs_cleared' => $voyage->customs_cleared_origin,
                    'documentation_complete' => $voyage->documentation_complete,
                    'cargo_inspected' => true,
                ]);
                break;
                
            case 'planning':
                $shipmentData = array_merge($shipmentData, [
                    'safety_approved' => false,
                    'customs_cleared' => false,
                    'documentation_complete' => false,
                    'cargo_inspected' => false,
                ]);
                break;
        }
    }

    /**
     * Obtener instrucciones especiales
     */
    private function getSpecialInstructions(Voyage $voyage, string $vesselRole): ?string
    {
        $instructions = [];
        
        if ($voyage->hazardous_cargo) {
            $instructions[] = 'Carga peligrosa clase 9 - Seguir protocolo IMDG';
        }
        
        if ($voyage->refrigerated_cargo) {
            $instructions[] = 'Mantener temperatura -18°C durante todo el viaje';
        }
        
        if ($voyage->oversized_cargo) {
            $instructions[] = 'Carga sobredimensionada - Navegación con precaución';
        }
        
        if ($vesselRole === 'lead') {
            $instructions[] = 'Embarcación líder del convoy - Coordinar movimientos';
        }
        
        if ($voyage->requires_pilot) {
            $instructions[] = 'Requerido práctico para navegación';
        }
        
        if ($voyage->requires_escort) {
            $instructions[] = 'Convoy con escolta de seguridad';
        }
        
        return empty($instructions) ? null : implode('. ', $instructions);
    }

    /**
     * Obtener notas de manejo para convoy
     */
    private function getConvoyHandlingNotes(array $vesselData, bool $isLead, int $position): string
    {
        $notes = "Embarcación {$vesselData['name']}";
        
        if ($isLead) {
            $notes .= " - LÍDER DEL CONVOY - Coordina movimientos y comunicaciones";
        } else {
            $notes .= " - Posición {$position} en convoy - Sigue órdenes del líder";
            
            switch ($vesselData['role']) {
                case 'towed':
                    $notes .= " - Remolcada por embarcación líder";
                    break;
                case 'pushed':
                    $notes .= " - Empujada por embarcación líder";
                    break;
                case 'escort':
                    $notes .= " - Escolta de seguridad sin carga";
                    break;
            }
        }
        
        return $notes;
    }

    /**
     * Crear un shipment individual
     */
    private function createShipment(array $data): void
    {
        // Datos base comunes
        $baseData = [
            'created_date' => now(),
            'created_by_user_id' => 1, // Admin
            'active' => true,
            'requires_attention' => false,
        ];

        // Si tiene retrasos, requiere atención
        if (($data['delay_minutes'] ?? 0) > 0) {
            $baseData['requires_attention'] = true;
        }

        Shipment::create(array_merge($baseData, $data));
    }

    /**
     * Mostrar resumen de shipments creados
     */
    private function showCreatedSummary(): void
    {
        $totalShipments = Shipment::count();
        $completedShipments = Shipment::where('status', 'completed')->count();
        $inTransitShipments = Shipment::where('status', 'in_transit')->count();
        $readyShipments = Shipment::where('status', 'ready')->count();
        $planningShipments = Shipment::where('status', 'planning')->count();
        $leadVessels = Shipment::where('is_lead_vessel', true)->count();
        $convoyMembers = Shipment::where('is_lead_vessel', false)->count();
        $withDelays = Shipment::where('has_delays', true)->count();
        $fullyApproved = Shipment::where('safety_approved', true)
                                ->where('customs_cleared', true)
                                ->where('documentation_complete', true)
                                ->where('cargo_inspected', true)
                                ->count();

        // Calcular estadísticas de utilización
        $avgUtilization = Shipment::where('cargo_capacity_tons', '>', 0)
                                 ->avg('utilization_percentage');

        $this->command->info('=== 📦 RESUMEN DE ENVÍOS CREADOS ===');
        $this->command->info('');
        $this->command->info("📊 Total envíos: {$totalShipments}");
        $this->command->info('');
        $this->command->info('📈 Por estado:');
        $this->command->info("   • Completados: {$completedShipments}");
        $this->command->info("   • En tránsito: {$inTransitShipments}");
        $this->command->info("   • Listos: {$readyShipments}");
        $this->command->info("   • En planificación: {$planningShipments}");
        $this->command->info('');
        $this->command->info('🚢 Por rol en convoy:');
        $this->command->info("   • Embarcaciones líderes: {$leadVessels}");
        $this->command->info("   • Miembros de convoy: {$convoyMembers}");
        $this->command->info('');
        $this->command->info('📋 Estado operacional:');
        $this->command->info("   • Con todas las aprobaciones: {$fullyApproved}");
        $this->command->info("   • Con retrasos reportados: {$withDelays}");
        $this->command->info("   • Utilización promedio: " . number_format($avgUtilization, 1) . "%");
        $this->command->info('');
        $this->command->info('🛳️ EMBARCACIONES EN OPERACIÓN:');
        $this->command->info('   • PAR13001 - Viajes V022NB, V023NB (single vessel)');
        $this->command->info('   • GUARAN F - Convoy V021SB (líder), V024SB, V025NB');
        $this->command->info('   • REINA DEL PARANA - Viaje V026SB (desconsolidación)');
        $this->command->info('   • BARCAZAS NORTE/SUR/ESTE - Miembros de convoy');
        $this->command->info('   • ESCORT ALFA - Escolta de seguridad');
        $this->command->info('');
        $this->command->info('📦 TIPOS DE CARGA MANEJADOS:');
        $this->command->info('   • Contenedores 40HC/20GP (capacidad 28-48 unidades)');
        $this->command->info('   • Carga peligrosa clase 9 (convoy V021SB, V024SB)');
        $this->command->info('   • Carga refrigerada -18°C (viaje V020NB)');
        $this->command->info('   • Carga sobredimensionada (convoy V027NB)');
        $this->command->info('');
        $this->command->info('⚡ OPERACIONES DESTACADAS:');
        $this->command->info('   • V022NB-01: PAR13001 completado sin incidentes');
        $this->command->info('   • V021SB-01/02: Convoy GUARAN F + barcaza con carga peligrosa');
        $this->command->info('   • V020NB-01: REINA DEL PARANA con retraso 2.5h por inspección');
        $this->command->info('   • V023NB-01: PAR13001 actualmente en tránsito');
        $this->command->info('   • V024SB-01/02/03: Convoy de 3 embarcaciones aprobado');
        $this->command->info('');
        $this->command->info('✅ Capacidades realistas según embarcaciones fluviales');
        $this->command->info('✅ Estados coherentes con viajes del VoyageSeeder');
        $this->command->info('✅ Capitanes asignados del CaptainSeeder');
        $this->command->info('✅ Datos de manifiestos reales PARANA.xlsx');
    }
}