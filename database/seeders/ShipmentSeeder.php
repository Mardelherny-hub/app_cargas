<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shipment;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShipmentSeeder extends Seeder
{
    /**
     * SEEDER DE SHIPMENTS - ENVÍOS INDIVIDUALES
     * 
     * Crea envíos realistas usando datos existentes del sistema:
     * - Voyages existentes
     * - Vessels existentes 
     * - Captains existentes
     * - Datos coherentes con el sistema de transporte fluvial AR/PY
     * 
     * TIPOS DE SHIPMENTS:
     * - Embarcación única (single vessel)
     * - Convoy con remolcador/empujador + barcazas
     * - Flota coordinada
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando envíos (shipments) de ejemplo...');

        // Verificar datos necesarios
        if (!$this->verifyRequiredData()) {
            return;
        }

        // Obtener datos base
        $voyages = Voyage::where('active', true)->get();
        $vessels = Vessel::where('active', true)->get();
        $captains = Captain::where('active', true)->get();
        $adminUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'company-admin');
        })->first();

        $this->command->info("📊 Datos disponibles:");
        $this->command->info("   - Viajes: {$voyages->count()}");
        $this->command->info("   - Embarcaciones: {$vessels->count()}");
        $this->command->info("   - Capitanes: {$captains->count()}");

        // Crear shipments para cada viaje
        foreach ($voyages as $voyage) {
            $this->createShipmentsForVoyage($voyage, $vessels, $captains, $adminUser);
        }

        $this->command->info('✅ Envíos creados exitosamente');
        $this->command->info("📊 Total envíos creados: " . Shipment::count());
    }

    /**
     * Verificar que existan los datos necesarios
     */
    private function verifyRequiredData(): bool
    {
        // Verificar voyages
        if (Voyage::count() === 0) {
            $this->command->error('❌ No hay viajes disponibles.');
            $this->command->info('Ejecute primero: php artisan db:seed --class=VoyageSeeder');
            return false;
        }

        // Verificar vessels
        if (Vessel::count() === 0) {
            $this->command->error('❌ No hay embarcaciones disponibles.');
            $this->command->info('Ejecute primero: php artisan db:seed --class=VesselSeeder');
            return false;
        }

        // Verificar captains
        if (Captain::count() === 0) {
            $this->command->warn('⚠️  No hay capitanes disponibles. Los envíos se crearán sin capitán asignado.');
        }

        return true;
    }

    /**
     * Crear shipments para un viaje específico
     */
    private function createShipmentsForVoyage(
        Voyage $voyage, 
        $vessels, 
        $captains, 
        ?User $adminUser
    ): void {
        // Determinar tipo de viaje y cantidad de shipments
        $shipmentType = $this->determineShipmentType($voyage);
        $shipmentCount = $this->getShipmentCount($shipmentType);

        $this->command->info("🛳️  Creando {$shipmentCount} envío(s) para viaje: {$voyage->voyage_number}");

        // Seleccionar embarcaciones disponibles para este viaje
        $selectedVessels = $vessels->random(min($shipmentCount, $vessels->count()));

        for ($i = 0; $i < $shipmentCount; $i++) {
            $vessel = $selectedVessels[$i] ?? $selectedVessels->first();
            $captain = $captains->isNotEmpty() ? $captains->random() : null;

            $this->createShipment(
                $voyage,
                $vessel,
                $captain,
                $i + 1, // sequence
                $shipmentCount,
                $shipmentType,
                $adminUser
            );
        }
    }

    /**
     * Determinar el tipo de shipment basado en el viaje
     */
    private function determineShipmentType(Voyage $voyage): string
    {
        // Mapear voyage_type a shipment pattern
        switch ($voyage->voyage_type) {
            case 'single_vessel':
                return 'single';
            case 'convoy':
                return 'convoy';
            case 'fleet':
                return 'fleet';
            default:
                return 'single';
        }
    }

    /**
     * Obtener cantidad de shipments según el tipo
     */
    private function getShipmentCount(string $type): int
    {
        switch ($type) {
            case 'single':
                return 1;
            case 'convoy':
                return rand(2, 4); // 1 empujador + 1-3 barcazas
            case 'fleet':
                return rand(2, 3); // 2-3 embarcaciones coordinadas
            default:
                return 1;
        }
    }

    /**
     * Crear un shipment individual
     */
    private function createShipment(
        Voyage $voyage,
        Vessel $vessel,
        ?Captain $captain,
        int $sequence,
        int $totalShipments,
        string $shipmentType,
        ?User $adminUser
    ): void {
        // Determinar rol de la embarcación
        $vesselRole = $this->determineVesselRole($sequence, $totalShipments, $shipmentType);
        $isLeadVessel = ($sequence === 1 && $totalShipments > 1);
        $convoyPosition = $totalShipments > 1 ? $sequence : null;

        // Generar número de shipment
        $shipmentNumber = $this->generateShipmentNumber($voyage, $sequence);

        // Datos de capacidad basados en el vessel
        $cargoCapacity = $vessel->cargo_capacity_tons ?? rand(500, 2000);
        $containerCapacity = $vessel->container_capacity ?? 0;

        // Datos de carga (simulados)
        $cargoLoaded = $cargoCapacity * (rand(60, 95) / 100); // 60-95% de capacidad
        $containersLoaded = $containerCapacity > 0 ? rand(0, $containerCapacity) : 0;

        // Status basado en el voyage status
        $status = $this->determineShipmentStatus($voyage->status);

        // Fechas coherentes con el viaje
        $dates = $this->generateShipmentDates($voyage, $status);

        // Crear el shipment
        $shipment = Shipment::create([
            'voyage_id' => $voyage->id,
            'vessel_id' => $vessel->id,
            'captain_id' => $captain?->id,
            'shipment_number' => $shipmentNumber,
            'sequence_in_voyage' => $sequence,
            'vessel_role' => $vesselRole,
            'convoy_position' => $convoyPosition,
            'is_lead_vessel' => $isLeadVessel,
            'cargo_capacity_tons' => $cargoCapacity,
            'container_capacity' => $containerCapacity,
            'cargo_weight_loaded' => $cargoLoaded,
            'containers_loaded' => $containersLoaded,
            'utilization_percentage' => ($cargoLoaded / $cargoCapacity) * 100,
            'status' => $status,
            'departure_time' => $dates['departure_time'],
            'arrival_time' => $dates['arrival_time'],
            'loading_start_time' => $dates['loading_start_time'],
            'loading_end_time' => $dates['loading_end_time'],
            'discharge_start_time' => $dates['discharge_start_time'],
            'discharge_end_time' => $dates['discharge_end_time'],
            'safety_approved' => in_array($status, ['ready', 'departed', 'in_transit', 'arrived', 'completed']),
            'customs_cleared' => in_array($status, ['departed', 'in_transit', 'arrived', 'completed']),
            'documentation_complete' => in_array($status, ['approved', 'ready', 'departed', 'in_transit', 'arrived', 'completed']),
            'cargo_inspected' => in_array($status, ['ready', 'departed', 'in_transit', 'arrived', 'completed']),
            'special_instructions' => $this->generateSpecialInstructions($vesselRole, $voyage),
            'handling_notes' => $this->generateHandlingNotes($vessel, $cargoLoaded),
            'delay_reason' => $status === 'delayed' ? $this->generateDelayReason() : null,
            'delay_minutes' => $status === 'delayed' ? rand(30, 480) : 0,
            'active' => true,
            'requires_attention' => $status === 'delayed' || rand(0, 10) < 2, // 20% require atención
            'has_delays' => $status === 'delayed',
            'created_date' => now(),
            'created_by_user_id' => $adminUser?->id,
        ]);

        $roleText = $this->getVesselRoleText($vesselRole);
        $statusText = $this->getStatusText($status);
        
        $this->command->info("   ✓ {$shipmentNumber} - {$vessel->name} ({$roleText}) - {$statusText}");
    }

    /**
     * Determinar el rol de la embarcación en el convoy
     */
    private function determineVesselRole(int $sequence, int $totalShipments, string $shipmentType): string
    {
        if ($totalShipments === 1) {
            return 'single';
        }

        if ($shipmentType === 'convoy') {
            if ($sequence === 1) {
                return rand(0, 1) ? 'lead' : 'lead'; // Primer vehículo siempre es lead
            } else {
                return rand(0, 1) ? 'pushed' : 'towed';
            }
        }

        if ($shipmentType === 'fleet') {
            return $sequence === 1 ? 'lead' : 'escort';
        }

        return 'single';
    }

    /**
     * Generar número de shipment
     */
    private function generateShipmentNumber(Voyage $voyage, int $sequence): string
    {
        $voyageNum = str_replace(['VYG-', '-'], ['', ''], $voyage->voyage_number);
        return "SHP-{$voyageNum}-" . str_pad($sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Determinar status del shipment basado en el voyage
     */
    private function determineShipmentStatus(string $voyageStatus): string
    {
        $statusMapping = [
            'planning' => 'planning',
            'approved' => rand(0, 1) ? 'approved' : 'loading',
            'in_transit' => rand(0, 2) === 0 ? 'in_transit' : (rand(0, 1) ? 'departed' : 'arrived'),
            'at_destination' => rand(0, 1) ? 'arrived' : 'discharging',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'delayed' => 'delayed'
        ];

        return $statusMapping[$voyageStatus] ?? 'planning';
    }

    /**
     * Generar fechas coherentes para el shipment
     */
    private function generateShipmentDates(Voyage $voyage, string $status): array
    {
        $dates = [
            'departure_time' => null,
            'arrival_time' => null,
            'loading_start_time' => null,
            'loading_end_time' => null,
            'discharge_start_time' => null,
            'discharge_end_time' => null,
        ];

        $baseDate = $voyage->departure_date ?? now();

        // Loading dates (antes de la partida)
        if (in_array($status, ['loading', 'ready', 'departed', 'in_transit', 'arrived', 'completed'])) {
            $dates['loading_start_time'] = $baseDate->copy()->subHours(rand(2, 8));
            $dates['loading_end_time'] = $dates['loading_start_time']->copy()->addHours(rand(2, 6));
        }

        // Departure date
        if (in_array($status, ['departed', 'in_transit', 'arrived', 'completed'])) {
            $dates['departure_time'] = $baseDate->copy()->addMinutes(rand(-60, 60));
        }

        // Arrival date
        if (in_array($status, ['arrived', 'discharging', 'completed'])) {
            $departureTime = $dates['departure_time'] ?? $baseDate;
            $dates['arrival_time'] = $departureTime->copy()->addHours(rand(12, 72));
        }

        // Discharge dates (después de la llegada)
        if (in_array($status, ['discharging', 'completed'])) {
            $arrivalTime = $dates['arrival_time'] ?? $baseDate->copy()->addDay();
            $dates['discharge_start_time'] = $arrivalTime->copy()->addHours(rand(1, 4));
            $dates['discharge_end_time'] = $dates['discharge_start_time']->copy()->addHours(rand(3, 8));
        }

        return $dates;
    }

    /**
     * Generar instrucciones especiales
     */
    private function generateSpecialInstructions(string $vesselRole, Voyage $voyage): ?string
    {
        $instructions = [];

        if ($vesselRole === 'lead') {
            $instructions[] = 'Embarcación líder del convoy - mantener comunicación constante';
        }

        if ($voyage->requires_pilot) {
            $instructions[] = 'Requiere piloto para navegación en tramo específico';
        }

        if ($voyage->cargo_type === 'dangerous_goods') {
            $instructions[] = 'Carga peligrosa - cumplir protocolos de seguridad especiales';
        }

        if (rand(0, 3) === 0) {
            $instructions[] = 'Verificar documentación aduanera antes de la partida';
        }

        return $instructions ? implode('. ', $instructions) : null;
    }

    /**
     * Generar notas de manejo
     */
    private function generateHandlingNotes(Vessel $vessel, float $cargoLoaded): ?string
    {
        $notes = [];

        if ($cargoLoaded > 1500) {
            $notes[] = 'Carga pesada - verificar distribución del peso';
        }

        $utilizationPercent = ($vessel->cargo_capacity_tons > 0) ? 
            ($cargoLoaded / $vessel->cargo_capacity_tons) * 100 : 0;

        if ($utilizationPercent > 90) {
            $notes[] = 'Capacidad casi completa - verificar límites de carga';
        }

        if (rand(0, 4) === 0) {
            $notes[] = 'Revisar estado de amarre y distribución de contenedores';
        }

        return $notes ? implode('. ', $notes) : null;
    }

    /**
     * Generar razón de demora
     */
    private function generateDelayReason(): string
    {
        $reasons = [
            'Condiciones climáticas adversas',
            'Demora en la carga de mercadería',
            'Trámites aduaneros extendidos',
            'Mantenimiento menor de la embarcación',
            'Congestión en el puerto',
            'Documentación pendiente',
            'Inspección adicional de seguridad'
        ];

        return $reasons[array_rand($reasons)];
    }

    /**
     * Obtener texto legible para rol de embarcación
     */
    private function getVesselRoleText(string $role): string
    {
        $roles = [
            'single' => 'Única',
            'lead' => 'Líder',
            'towed' => 'Remolcada',
            'pushed' => 'Empujada',
            'escort' => 'Escolta'
        ];

        return $roles[$role] ?? $role;
    }

    /**
     * Obtener texto legible para status
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'planning' => 'Planificación',
            'approved' => 'Aprobado',
            'loading' => 'Cargando',
            'ready' => 'Listo',
            'departed' => 'Partió',
            'in_transit' => 'En Tránsito',
            'arrived' => 'Arribó',
            'discharging' => 'Descargando',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'delayed' => 'Demorado'
        ];

        return $statuses[$status] ?? $status;
    }
}