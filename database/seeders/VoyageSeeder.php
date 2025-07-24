<?php

namespace Database\Seeders;

use App\Models\Voyage;
use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use App\Models\CustomsOffice;
use App\Models\Captain;
use App\Models\Vessel;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * VoyageSeeder - MÓDULO 3: VIAJES Y CARGAS
 * 
 * Seeder para viajes del sistema de transporte fluvial AR/PY
 * 
 * DATOS REALES DEL SISTEMA:
 * - Ruta principal: ARBUE → PYTVT (Buenos Aires → Paraguay Terminal Villeta)
 * - Empresas: Rio de la Plata Transport S.A., Navegación Paraguay S.A.
 * - Viajes: V022NB, V023NB, etc. (formato real del sistema)
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Terminal: TERPORT VILLETA
 * 
 * Contexto: Sistema real de transporte fluvial con datos del manifiesto PARANA.xlsx
 */
class VoyageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando viajes para transporte fluvial AR/PY...');

        // Verificar dependencias
        if (!$this->verifyDependencies()) {
            return;
        }

        // Obtener referencias necesarias
        $references = $this->getReferences();

        // Limpiar tabla existente
        DB::table('voyages')->truncate();

        //
        // === VIAJES HISTÓRICOS COMPLETADOS ===
        //
        $this->createHistoricalVoyages($references);

        //
        // === VIAJES EN CURSO Y FUTUROS ===
        //
        $this->createCurrentVoyages($references);

        //
        // === VIAJES PLANIFICADOS ===
        //
        $this->createPlannedVoyages($references);

        $this->command->info('✅ Viajes creados exitosamente para transporte fluvial AR/PY');
        $this->command->info('');
        $this->showCreatedSummary();
    }

    /**
     * Verificar que las dependencias existan
     */
    private function verifyDependencies(): bool
    {
        $dependencies = [
            ['model' => Country::class, 'condition' => ['iso_code' => 'AR'], 'name' => 'Argentina'],
            ['model' => Country::class, 'condition' => ['iso_code' => 'PY'], 'name' => 'Paraguay'],
            ['model' => Company::class, 'condition' => ['tax_id' => '20123456789'], 'name' => 'Rio de la Plata'],
            ['model' => Captain::class, 'condition' => ['active' => true], 'name' => 'Capitanes activos'],
            ['model' => Port::class, 'condition' => ['code' => 'ARBUE'], 'name' => 'Puerto Buenos Aires'],
        ];

        foreach ($dependencies as $dep) {
            if (!$dep['model']::where($dep['condition'])->exists()) {
                $this->command->error("❌ {$dep['name']} no encontrado. Ejecutar seeders previos.");
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener referencias necesarias para los viajes
     */
    private function getReferences(): array
    {
        return [
            // Países
            'argentina' => Country::where('iso_code', 'AR')->first(),
            'paraguay' => Country::where('iso_code', 'PY')->first(),
            
            // Empresas
            'rio_plata' => Company::where('tax_id', '20123456789')->first(),
            'navegacion_py' => Company::where('tax_id', '80987654321')->first(),
            'logistica_integral' => Company::where('tax_id', '30555666777')->first(),
            
            // Puertos
            'puerto_buenos_aires' => Port::where('code', 'ARBUE')->first(),
            'terminal_villeta' => Port::where('code', 'PYTVT')->first(),
            'puerto_rosario' => Port::where('code', 'ARROS')->first(),
            'puerto_asuncion' => Port::where('code', 'PYASU')->first(),
            
            // Aduanas
            'aduana_buenos_aires' => CustomsOffice::where('code', '001')->first(),
            'aduana_rosario' => CustomsOffice::where('code', '002')->first(),
            'aduana_villeta' => CustomsOffice::where('code', 'PY001')->first(),
            'aduana_asuncion' => CustomsOffice::where('code', 'PY002')->first(),
            
            // Capitanes
            'captains_ar' => Captain::whereHas('country', fn($q) => $q->where('iso_code', 'AR'))->get(),
            'captains_py' => Captain::whereHas('country', fn($q) => $q->where('iso_code', 'PY'))->get(),
            
            // Embarcaciones (simuladas - en un sistema real vendrían de la tabla vessels)
            'vessels' => collect([
                ['name' => 'PAR13001', 'capacity' => 1200, 'containers' => 48],
                ['name' => 'GUARAN F', 'capacity' => 1100, 'containers' => 44],
                ['name' => 'REINA DEL PARANA', 'capacity' => 950, 'containers' => 38],
            ]),
        ];
    }

    /**
     * Crear viajes históricos completados (diciembre 2024)
     */
    private function createHistoricalVoyages(array $refs): void
    {
        $this->command->info('📅 Creando viajes históricos completados...');

        $historicalVoyages = [
            [
                'voyage_number' => 'V022NB',
                'internal_reference' => 'RIO-2024-022',
                'company_id' => $refs['rio_plata']->id,
                'captain_id' => $refs['captains_ar']->where('license_class', 'master')->first()?->id,
                'origin_country_id' => $refs['argentina']->id,
                'origin_port_id' => $refs['puerto_buenos_aires']->id,
                'destination_country_id' => $refs['paraguay']->id,
                'destination_port_id' => $refs['terminal_villeta']->id,
                'origin_customs_id' => $refs['aduana_buenos_aires']->id,
                'destination_customs_id' => $refs['aduana_villeta']->id,
                'departure_date' => '2024-12-15 08:00:00',
                'estimated_arrival_date' => '2024-12-18 16:00:00',
                'actual_arrival_date' => '2024-12-18 15:30:00',
                'customs_clearance_deadline' => '2024-12-19 12:00:00',
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 1200.00,
                'total_container_capacity' => 48,
                'total_cargo_weight_loaded' => 1150.50,
                'total_containers_loaded' => 46,
                'capacity_utilization_percentage' => 95.88,
                'status' => 'completed',
                'priority_level' => 'normal',
                'requires_escort' => false,
                'requires_pilot' => true,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => false,
                'oversized_cargo' => false,
                'weather_conditions' => 'Favorable, vientos del SE 15 km/h',
                'route_conditions' => 'Navegación normal, nivel de río adecuado',
                'special_instructions' => 'Entrega directa Terminal TERPORT VILLETA',
                'operational_notes' => 'Viaje completado exitosamente, sin incidentes',
                'estimated_cost' => 75000.00,
                'actual_cost' => 72500.00,
                'cost_currency' => 'USD',
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => true,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => '2024-12-14 14:00:00',
                'customs_approval_date' => '2024-12-14 16:30:00',
                'environmental_approval_date' => '2024-12-14 10:00:00',
                'active' => false,
                'archived' => true,
                'requires_follow_up' => false,
            ],
            [
                'voyage_number' => 'V021SB',
                'internal_reference' => 'NAV-PY-2024-021',
                'company_id' => $refs['navegacion_py']->id,
                'captain_id' => $refs['captains_py']->where('license_class', 'master')->first()?->id,
                'origin_country_id' => $refs['paraguay']->id,
                'origin_port_id' => $refs['terminal_villeta']->id,
                'destination_country_id' => $refs['argentina']->id,
                'destination_port_id' => $refs['puerto_buenos_aires']->id,
                'origin_customs_id' => $refs['aduana_villeta']->id,
                'destination_customs_id' => $refs['aduana_buenos_aires']->id,
                'departure_date' => '2024-12-10 09:30:00',
                'estimated_arrival_date' => '2024-12-13 18:00:00',
                'actual_arrival_date' => '2024-12-13 17:15:00',
                'customs_clearance_deadline' => '2024-12-14 12:00:00',
                'voyage_type' => 'convoy',
                'cargo_type' => 'import',
                'is_convoy' => true,
                'vessel_count' => 2,
                'total_cargo_capacity_tons' => 2300.00,
                'total_container_capacity' => 92,
                'total_cargo_weight_loaded' => 2180.75,
                'total_containers_loaded' => 89,
                'capacity_utilization_percentage' => 94.83,
                'status' => 'completed',
                'priority_level' => 'high',
                'requires_escort' => true,
                'requires_pilot' => true,
                'hazardous_cargo' => true,
                'refrigerated_cargo' => false,
                'oversized_cargo' => false,
                'weather_conditions' => 'Condiciones adversas iniciales, mejoró durante viaje',
                'route_conditions' => 'Tráfico intenso en aproximación Buenos Aires',
                'special_instructions' => 'Convoy GUARAN F + barcaza de apoyo, carga peligrosa clase 9',
                'operational_notes' => 'Convoy arribó adelantado, excelente coordinación',
                'estimated_cost' => 145000.00,
                'actual_cost' => 148200.00,
                'cost_currency' => 'USD',
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => true,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => '2024-12-09 11:00:00',
                'customs_approval_date' => '2024-12-09 15:20:00',
                'environmental_approval_date' => '2024-12-09 09:30:00',
                'active' => false,
                'archived' => true,
                'requires_follow_up' => false,
            ],
            [
                'voyage_number' => 'V020NB',
                'internal_reference' => 'RIO-2024-020',
                'company_id' => $refs['rio_plata']->id,
                'captain_id' => $refs['captains_ar']->where('license_class', 'chief_officer')->first()?->id,
                'origin_country_id' => $refs['argentina']->id,
                'origin_port_id' => $refs['puerto_rosario']->id,
                'destination_country_id' => $refs['paraguay']->id,
                'destination_port_id' => $refs['puerto_asuncion']->id,
                'origin_customs_id' => $refs['aduana_rosario']->id,
                'destination_customs_id' => $refs['aduana_asuncion']->id,
                'departure_date' => '2024-12-05 07:45:00',
                'estimated_arrival_date' => '2024-12-08 14:00:00',
                'actual_arrival_date' => '2024-12-08 16:30:00',
                'customs_clearance_deadline' => '2024-12-09 10:00:00',
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 950.00,
                'total_container_capacity' => 38,
                'total_cargo_weight_loaded' => 920.25,
                'total_containers_loaded' => 37,
                'capacity_utilization_percentage' => 96.87,
                'status' => 'completed',
                'priority_level' => 'normal',
                'requires_escort' => false,
                'requires_pilot' => false,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => true,
                'oversized_cargo' => false,
                'weather_conditions' => 'Excelente, cielo despejado',
                'route_conditions' => 'Navegación fluida, sin demoras',
                'special_instructions' => 'Carga refrigerada productos alimentarios, temperatura -18°C',
                'operational_notes' => 'Retraso menor por inspección adicional carga refrigerada',
                'estimated_cost' => 68000.00,
                'actual_cost' => 71200.00,
                'cost_currency' => 'USD',
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => true,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => '2024-12-04 13:30:00',
                'customs_approval_date' => '2024-12-04 17:00:00',
                'environmental_approval_date' => '2024-12-04 11:00:00',
                'active' => false,
                'archived' => true,
                'requires_follow_up' => true,
                'follow_up_reason' => 'Retraso de 2.5 horas por inspección adicional',
            ],
        ];

        foreach ($historicalVoyages as $voyageData) {
            $this->createVoyage($voyageData);
        }
    }

    /**
     * Crear viajes en curso y futuros inmediatos
     */
    private function createCurrentVoyages(array $refs): void
    {
        $this->command->info('🚢 Creando viajes en curso y próximos...');

        $currentVoyages = [
            [
                'voyage_number' => 'V023NB',
                'internal_reference' => 'RIO-2025-001',
                'company_id' => $refs['rio_plata']->id,
                'captain_id' => $refs['captains_ar']->where('license_class', 'master')->skip(1)->first()?->id,
                'origin_country_id' => $refs['argentina']->id,
                'origin_port_id' => $refs['puerto_buenos_aires']->id,
                'destination_country_id' => $refs['paraguay']->id,
                'destination_port_id' => $refs['terminal_villeta']->id,
                'origin_customs_id' => $refs['aduana_buenos_aires']->id,
                'destination_customs_id' => $refs['aduana_villeta']->id,
                'departure_date' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'estimated_arrival_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'customs_clearance_deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 1200.00,
                'total_container_capacity' => 48,
                'total_cargo_weight_loaded' => 1100.00,
                'total_containers_loaded' => 44,
                'capacity_utilization_percentage' => 91.67,
                'status' => 'in_transit',
                'priority_level' => 'normal',
                'requires_escort' => false,
                'requires_pilot' => true,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => false,
                'oversized_cargo' => false,
                'weather_conditions' => 'Condiciones favorables, visibilidad buena',
                'route_conditions' => 'Navegación normal, sin obstáculos reportados',
                'special_instructions' => 'Viaje regular Buenos Aires - Terminal Villeta',
                'operational_notes' => 'Viaje en curso, progreso normal según cronograma',
                'estimated_cost' => 78000.00,
                'cost_currency' => 'USD',
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => false,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'customs_approval_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'environmental_approval_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
                'active' => true,
                'archived' => false,
                'requires_follow_up' => false,
            ],
            [
                'voyage_number' => 'V024SB',
                'internal_reference' => 'NAV-PY-2025-001',
                'company_id' => $refs['navegacion_py']->id,
                'captain_id' => $refs['captains_py']->where('license_class', 'master')->skip(1)->first()?->id,
                'origin_country_id' => $refs['paraguay']->id,
                'origin_port_id' => $refs['terminal_villeta']->id,
                'destination_country_id' => $refs['argentina']->id,
                'destination_port_id' => $refs['puerto_buenos_aires']->id,
                'origin_customs_id' => $refs['aduana_villeta']->id,
                'destination_customs_id' => $refs['aduana_buenos_aires']->id,
                'departure_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'estimated_arrival_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'customs_clearance_deadline' => now()->addDays(6)->format('Y-m-d H:i:s'),
                'voyage_type' => 'convoy',
                'cargo_type' => 'import',
                'is_convoy' => true,
                'vessel_count' => 3,
                'total_cargo_capacity_tons' => 3450.00,
                'total_container_capacity' => 138,
                'total_cargo_weight_loaded' => 3200.00,
                'total_containers_loaded' => 128,
                'capacity_utilization_percentage' => 92.75,
                'status' => 'approved',
                'priority_level' => 'high',
                'requires_escort' => true,
                'requires_pilot' => true,
                'hazardous_cargo' => true,
                'refrigerated_cargo' => true,
                'oversized_cargo' => false,
                'weather_conditions' => 'Pronóstico favorable para próximos días',
                'route_conditions' => 'Ruta despejada, tráfico normal esperado',
                'special_instructions' => 'Convoy GUARAN F + 2 barcazas, carga mixta peligrosa/refrigerada',
                'operational_notes' => 'Preparación final, salida programada en 2 días',
                'estimated_cost' => 195000.00,
                'cost_currency' => 'USD',
                'safety_approved' => true,
                'customs_cleared_origin' => true,
                'customs_cleared_destination' => false,
                'documentation_complete' => true,
                'environmental_approved' => true,
                'safety_approval_date' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'customs_approval_date' => now()->format('Y-m-d H:i:s'),
                'environmental_approval_date' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'active' => true,
                'archived' => false,
                'requires_follow_up' => false,
            ],
        ];

        foreach ($currentVoyages as $voyageData) {
            $this->createVoyage($voyageData);
        }
    }

    /**
     * Crear viajes planificados
     */
    private function createPlannedVoyages(array $refs): void
    {
        $this->command->info('📋 Creando viajes planificados...');

        $plannedVoyages = [
            [
                'voyage_number' => 'V025NB',
                'internal_reference' => 'RIO-2025-002',
                'company_id' => $refs['rio_plata']->id,
                'captain_id' => $refs['captains_ar']->where('license_class', 'chief_officer')->skip(1)->first()?->id,
                'origin_country_id' => $refs['argentina']->id,
                'origin_port_id' => $refs['puerto_buenos_aires']->id,
                'destination_country_id' => $refs['paraguay']->id,
                'destination_port_id' => $refs['terminal_villeta']->id,
                'origin_customs_id' => $refs['aduana_buenos_aires']->id,
                'destination_customs_id' => $refs['aduana_villeta']->id,
                'departure_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'estimated_arrival_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'customs_clearance_deadline' => now()->addDays(11)->format('Y-m-d H:i:s'),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 1100.00,
                'total_container_capacity' => 44,
                'total_cargo_weight_loaded' => 0.00, // Sin cargar aún
                'total_containers_loaded' => 0,
                'capacity_utilization_percentage' => 0.00,
                'status' => 'planning',
                'priority_level' => 'normal',
                'requires_escort' => false,
                'requires_pilot' => true,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => false,
                'oversized_cargo' => false,
                'special_instructions' => 'Viaje en planificación, pendiente asignación de carga',
                'operational_notes' => 'Planificación inicial, capacidad disponible completa',
                'estimated_cost' => 82000.00,
                'cost_currency' => 'USD',
                'safety_approved' => false,
                'customs_cleared_origin' => false,
                'customs_cleared_destination' => false,
                'documentation_complete' => false,
                'environmental_approved' => false,
                'active' => true,
                'archived' => false,
                'requires_follow_up' => false,
            ],
            [
                'voyage_number' => 'V026SB',
                'internal_reference' => 'LOG-INT-2025-001',
                'company_id' => $refs['logistica_integral']->id,
                'captain_id' => $refs['captains_ar']->where('license_class', 'officer')->first()?->id,
                'origin_country_id' => $refs['paraguay']->id,
                'origin_port_id' => $refs['puerto_asuncion']->id,
                'destination_country_id' => $refs['argentina']->id,
                'destination_port_id' => $refs['puerto_rosario']->id,
                'origin_customs_id' => $refs['aduana_asuncion']->id,
                'destination_customs_id' => $refs['aduana_rosario']->id,
                'departure_date' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'estimated_arrival_date' => now()->addDays(13)->format('Y-m-d H:i:s'),
                'customs_clearance_deadline' => now()->addDays(14)->format('Y-m-d H:i:s'),
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'transshipment',
                'is_convoy' => false,
                'vessel_count' => 1,
                'total_cargo_capacity_tons' => 950.00,
                'total_container_capacity' => 38,
                'total_cargo_weight_loaded' => 0.00,
                'total_containers_loaded' => 0,
                'capacity_utilization_percentage' => 0.00,
                'status' => 'planning',
                'priority_level' => 'normal',
                'requires_escort' => false,
                'requires_pilot' => false,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => false,
                'oversized_cargo' => false,
                'special_instructions' => 'Operación de desconsolidación, múltiples destinos finales',
                'operational_notes' => 'Especializado en operaciones de desconsolidación',
                'estimated_cost' => 65000.00,
                'cost_currency' => 'USD',
                'safety_approved' => false,
                'customs_cleared_origin' => false,
                'customs_cleared_destination' => false,
                'documentation_complete' => false,
                'environmental_approved' => false,
                'active' => true,
                'archived' => false,
                'requires_follow_up' => false,
            ],
            [
                'voyage_number' => 'V027NB',
                'internal_reference' => 'NAV-PY-2025-002',
                'company_id' => $refs['navegacion_py']->id,
                'captain_id' => $refs['captains_py']->where('license_class', 'chief_officer')->first()?->id,
                'origin_country_id' => $refs['argentina']->id,
                'origin_port_id' => $refs['puerto_rosario']->id,
                'destination_country_id' => $refs['paraguay']->id,
                'destination_port_id' => $refs['terminal_villeta']->id,
                'transshipment_port_id' => $refs['puerto_asuncion']->id,
                'origin_customs_id' => $refs['aduana_rosario']->id,
                'destination_customs_id' => $refs['aduana_villeta']->id,
                'transshipment_customs_id' => $refs['aduana_asuncion']->id,
                'departure_date' => now()->addDays(14)->format('Y-m-d H:i:s'),
                'estimated_arrival_date' => now()->addDays(18)->format('Y-m-d H:i:s'),
                'customs_clearance_deadline' => now()->addDays(19)->format('Y-m-d H:i:s'),
                'voyage_type' => 'convoy',
                'cargo_type' => 'transit',
                'is_convoy' => true,
                'vessel_count' => 2,
                'total_cargo_capacity_tons' => 2100.00,
                'total_container_capacity' => 84,
                'total_cargo_weight_loaded' => 0.00,
                'total_containers_loaded' => 0,
                'capacity_utilization_percentage' => 0.00,
                'status' => 'planning',
                'priority_level' => 'high',
                'requires_escort' => true,
                'requires_pilot' => true,
                'hazardous_cargo' => false,
                'refrigerated_cargo' => false,
                'oversized_cargo' => true,
                'special_instructions' => 'Viaje con transbordo en Asunción, carga sobredimensionada',
                'operational_notes' => 'Convoy especializado con transbordo intermedio',
                'estimated_cost' => 165000.00,
                'cost_currency' => 'USD',
                'safety_approved' => false,
                'customs_cleared_origin' => false,
                'customs_cleared_destination' => false,
                'documentation_complete' => false,
                'environmental_approved' => false,
                'active' => true,
                'archived' => false,
                'requires_follow_up' => false,
            ],
        ];

        foreach ($plannedVoyages as $voyageData) {
            $this->createVoyage($voyageData);
        }
    }

    /**
     * Crear un viaje individual
     */
    private function createVoyage(array $data): void
    {
        // Datos base comunes
        $baseData = [
            'created_date' => now(),
            'last_updated_date' => now(),
            'created_by_user_id' => 1, // Admin
            'last_updated_by_user_id' => 1,
        ];

        // Convertir fechas string a Carbon
        $dateFields = [
            'departure_date', 'estimated_arrival_date', 'actual_arrival_date',
            'customs_clearance_deadline', 'safety_approval_date',
            'customs_approval_date', 'environmental_approval_date'
        ];

        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = Carbon::parse($data[$field]);
            }
        }

        Voyage::create(array_merge($baseData, $data));
    }

    /**
     * Mostrar resumen de viajes creados
     */
    private function showCreatedSummary(): void
    {
        $totalVoyages = Voyage::count();
        $completedVoyages = Voyage::where('status', 'completed')->count();
        $inTransitVoyages = Voyage::where('status', 'in_transit')->count();
        $approvedVoyages = Voyage::where('status', 'approved')->count();
        $planningVoyages = Voyage::where('status', 'planning')->count();
        $convoyVoyages = Voyage::where('is_convoy', true)->count();
        $singleVesselVoyages = Voyage::where('is_convoy', false)->count();
        $exportVoyages = Voyage::where('cargo_type', 'export')->count();
        $importVoyages = Voyage::where('cargo_type', 'import')->count();

        $this->command->info('=== 🚢 RESUMEN DE VIAJES CREADOS ===');
        $this->command->info('');
        $this->command->info("📊 Total viajes: {$totalVoyages}");
        $this->command->info('');
        $this->command->info('📈 Por estado:');
        $this->command->info("   • Completados: {$completedVoyages}");
        $this->command->info("   • En tránsito: {$inTransitVoyages}");
        $this->command->info("   • Aprobados: {$approvedVoyages}");
        $this->command->info("   • En planificación: {$planningVoyages}");
        $this->command->info('');
        $this->command->info('🚢 Por tipo de viaje:');
        $this->command->info("   • Convoy: {$convoyVoyages}");
        $this->command->info("   • Embarcación única: {$singleVesselVoyages}");
        $this->command->info('');
        $this->command->info('📦 Por tipo de carga:');
        $this->command->info("   • Exportación: {$exportVoyages}");
        $this->command->info("   • Importación: {$importVoyages}");
        $this->command->info('');
        $this->command->info('🛳️ VIAJES DESTACADOS CREADOS:');
        $this->command->info('   • V022NB - Completado (PAR13001, Buenos Aires → Villeta)');
        $this->command->info('   • V021SB - Convoy completado (GUARAN F + barcaza, carga peligrosa)');
        $this->command->info('   • V023NB - En tránsito actual (Buenos Aires → Villeta)');
        $this->command->info('   • V024SB - Convoy aprobado (3 embarcaciones, carga mixta)');
        $this->command->info('   • V027NB - Planificado con transbordo (Rosario → Villeta vía Asunción)');
        $this->command->info('');
        $this->command->info('🌍 RUTAS PRINCIPALES:');
        $this->command->info('   • ARBUE → PYTVT (Buenos Aires → Terminal Villeta)');
        $this->command->info('   • PYTVT → ARBUE (Terminal Villeta → Buenos Aires)');
        $this->command->info('   • ARROS → PYASU (Rosario → Asunción)');
        $this->command->info('');
        $this->command->info('✅ Datos coherentes con sistema real PARANA.xlsx');
        $this->command->info('✅ Capitanes asignados de CaptainSeeder');
        $this->command->info('✅ Empresas vinculadas del sistema existente');
    }
}