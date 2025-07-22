<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vessel;
use App\Models\VesselOwner;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VesselSeeder extends Seeder
{
    /**
     * Seeder para embarcaciones específicas.
     * 
     * Crea embarcaciones realistas usando datos existentes:
     * - Companies con rol "Cargas"
     * - VesselOwners existentes
     * - Countries (AR/PY)
     * - VesselTypes (si existen)
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando embarcaciones de ejemplo...');

        // Verificar datos necesarios
        $companies = Company::whereJsonContains('company_roles', 'Cargas')
            ->where('active', true)
            ->get();

        if ($companies->isEmpty()) {
            $this->command->error('❌ No hay empresas activas con rol "Cargas".');
            $this->command->info('Ejecute primero: php artisan db:seed --class=TestUsersSeeder');
            return;
        }

        $vesselOwners = VesselOwner::where('status', 'active')->get();
        if ($vesselOwners->isEmpty()) {
            $this->command->error('❌ No hay propietarios de embarcaciones.');
            $this->command->info('Ejecute primero: php artisan db:seed --class=VesselOwnersSeeder');
            return;
        }

        // Países
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Faltan países Argentina (AR) o Paraguay (PY).');
            return;
        }

        // Usuario para auditoría
        $adminUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'company-admin');
        })->first();

        // Verificar si existe tabla vessel_types
        $vesselTypeId = $this->getVesselTypeId();

        // Crear embarcaciones de ejemplo
        $vesselsData = $this->getVesselData();

        foreach ($vesselsData as $vesselData) {
            $this->createVessel(
                $vesselData,
                $companies->random(),
                $vesselOwners->random(),
                $vesselData['country'] === 'AR' ? $argentina : $paraguay,
                $vesselTypeId,
                $adminUser
            );
        }

        $this->command->info('✅ Embarcaciones creadas exitosamente');
        $this->command->info("📊 Total creadas: " . Vessel::count());
    }

    /**
     * Obtener ID de tipo de embarcación (crear uno genérico si no existe).
     */
    private function getVesselTypeId(): int
    {
        // Verificar si existe la tabla vessel_types
        if (!DB::getSchemaBuilder()->hasTable('vessel_types')) {
            $this->command->warn('⚠️  Tabla vessel_types no existe. Usando ID genérico 1.');
            return 1;
        }

        // Intentar obtener un tipo de barcaza
        $vesselType = DB::table('vessel_types')
            ->where('active', true)
            ->first();

        if ($vesselType) {
            return $vesselType->id;
        }

        // Crear tipo genérico si no existe
        $this->command->warn('⚠️  No hay tipos de embarcación. Creando tipo genérico...');
        
        $typeId = DB::table('vessel_types')->insertGetId([
            'code' => 'BARGE_001',
            'name' => 'Barcaza Estándar',
            'short_name' => 'Barcaza',
            'description' => 'Barcaza estándar para transporte de carga general',
            'category' => 'barge',
            'propulsion_type' => 'pushed',
            'active' => true,
            'is_common' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✓ Tipo de embarcación genérico creado con ID: {$typeId}");
        return $typeId;
    }

    /**
     * Datos de embarcaciones de ejemplo.
     */
    private function getVesselData(): array
    {
        return [
            // Barcazas argentinas
            [
                'name' => 'Río Paraná I',
                'registration_number' => 'AR-001-2024',
                'imo_number' => null, // Barcazas no tienen IMO
                'call_sign' => 'LU4BRC',
                'mmsi_number' => '701000001',
                'length_meters' => 60.00,
                'beam_meters' => 10.50,
                'draft_meters' => 3.20,
                'depth_meters' => 4.80,
                'gross_tonnage' => 850.00,
                'deadweight_tons' => 1200.00,
                'cargo_capacity_tons' => 1000.00,
                'max_cargo_capacity' => 1000.00,
                'operational_status' => 'active',
                'country' => 'AR',
            ],
            [
                'name' => 'Buenos Aires Express',
                'registration_number' => 'AR-002-2024',
                'call_sign' => 'LU4BRD',
                'mmsi_number' => '701000002',
                'length_meters' => 65.00,
                'beam_meters' => 11.00,
                'draft_meters' => 3.40,
                'depth_meters' => 5.00,
                'gross_tonnage' => 920.00,
                'deadweight_tons' => 1350.00,
                'cargo_capacity_tons' => 1200.00,
                'max_cargo_capacity' => 1200.00,
                'operational_status' => 'active',
                'country' => 'AR',
            ],
            [
                'name' => 'Rosario Trader',
                'registration_number' => 'AR-003-2024',
                'call_sign' => 'LU4BRE',
                'mmsi_number' => '701000003',
                'length_meters' => 55.00,
                'beam_meters' => 10.00,
                'draft_meters' => 3.00,
                'depth_meters' => 4.50,
                'gross_tonnage' => 780.00,
                'deadweight_tons' => 1100.00,
                'cargo_capacity_tons' => 950.00,
                'max_cargo_capacity' => 950.00,
                'operational_status' => 'maintenance',
                'country' => 'AR',
            ],

            // Embarcaciones paraguayas
            [
                'name' => 'Asunción Star',
                'registration_number' => 'PY-101-2024',
                'call_sign' => 'ZP4ASU',
                'mmsi_number' => '755000001',
                'length_meters' => 58.00,
                'beam_meters' => 10.20,
                'draft_meters' => 3.10,
                'depth_meters' => 4.70,
                'gross_tonnage' => 820.00,
                'deadweight_tons' => 1150.00,
                'cargo_capacity_tons' => 1000.00,
                'max_cargo_capacity' => 1000.00,
                'operational_status' => 'active',
                'country' => 'PY',
            ],
            [
                'name' => 'Paraguay Navigator',
                'registration_number' => 'PY-102-2024',
                'call_sign' => 'ZP4NAV',
                'mmsi_number' => '755000002',
                'length_meters' => 62.00,
                'beam_meters' => 10.80,
                'draft_meters' => 3.30,
                'depth_meters' => 4.90,
                'gross_tonnage' => 880.00,
                'deadweight_tons' => 1280.00,
                'cargo_capacity_tons' => 1100.00,
                'max_cargo_capacity' => 1100.00,
                'operational_status' => 'active',
                'country' => 'PY',
            ],

            // Remolcador argentino
            [
                'name' => 'Tango Pusher',
                'registration_number' => 'AR-TUG-001',
                'call_sign' => 'LU4TUG',
                'mmsi_number' => '701000010',
                'length_meters' => 28.00,
                'beam_meters' => 8.50,
                'draft_meters' => 2.80,
                'depth_meters' => 3.50,
                'gross_tonnage' => 180.00,
                'deadweight_tons' => 45.00,
                'cargo_capacity_tons' => 0.00,
                'max_cargo_capacity' => 0.00, // Remolcador no carga
                'operational_status' => 'active',
                'country' => 'AR',
            ],

            // Barcaza grande para granos
            [
                'name' => 'Grain Master',
                'registration_number' => 'AR-004-2024',
                'call_sign' => 'LU4GRN',
                'mmsi_number' => '701000004',
                'length_meters' => 72.00,
                'beam_meters' => 12.00,
                'draft_meters' => 3.80,
                'depth_meters' => 5.50,
                'gross_tonnage' => 1150.00,
                'deadweight_tons' => 1800.00,
                'cargo_capacity_tons' => 1600.00,
                'max_cargo_capacity' => 1600.00,
                'operational_status' => 'active',
                'country' => 'AR',
            ],
        ];
    }

    /**
     * Crear una embarcación.
     */
    private function createVessel(
        array $vesselData,
        Company $company,
        VesselOwner $owner,
        Country $country,
        int $vesselTypeId,
        ?User $user
    ): void {
        // Verificar si ya existe
        $existing = Vessel::where('registration_number', $vesselData['registration_number'])->first();
        if ($existing) {
            $this->command->warn("⚠️  Embarcación {$vesselData['name']} ya existe");
            return;
        }

        $vessel = Vessel::create([
            'name' => $vesselData['name'],
            'registration_number' => $vesselData['registration_number'],
            'imo_number' => $vesselData['imo_number'] ?? null,
            'call_sign' => $vesselData['call_sign'],
            'mmsi_number' => $vesselData['mmsi_number'],
            'company_id' => $company->id,
            'owner_id' => $owner->id,
            'vessel_type_id' => $vesselTypeId,
            'flag_country_id' => $country->id,
            'home_port_id' => null, // TODO: Agregar cuando existan puertos
            'current_port_id' => null, // TODO: Agregar cuando existan puertos
            'primary_captain_id' => null, // TODO: Agregar cuando existan capitanes
            'length_meters' => $vesselData['length_meters'],
            'beam_meters' => $vesselData['beam_meters'],
            'draft_meters' => $vesselData['draft_meters'],
            'depth_meters' => $vesselData['depth_meters'],
            'gross_tonnage' => $vesselData['gross_tonnage'],
            'net_tonnage' => $vesselData['gross_tonnage'] * 0.7, // Estimación
            'deadweight_tons' => $vesselData['deadweight_tons'],
            'cargo_capacity_tons' => $vesselData['cargo_capacity_tons'],
            'max_cargo_capacity' => $vesselData['max_cargo_capacity'],
            'operational_status' => $vesselData['operational_status'],
            'available_for_charter' => in_array($vesselData['operational_status'], ['active', 'charter']),
            'charter_rate' => rand(500, 2000), // USD por día
            'next_inspection_due' => now()->addMonths(rand(3, 12)),
            'next_maintenance_due' => now()->addMonths(rand(6, 18)),
            'insurance_expires' => now()->addYear(),
            'safety_certificate_expires' => now()->addMonths(rand(6, 24)),
            'active' => true,
            'verified' => rand(0, 1) == 1,
            'inspection_current' => $vesselData['operational_status'] !== 'maintenance',
            'insurance_current' => true,
            'certificates_current' => $vesselData['operational_status'] !== 'maintenance',
            'created_by_user_id' => $user?->id,
            'last_updated_by_user_id' => $user?->id,
        ]);

        $this->command->info("✓ Creada: {$vesselData['name']} ({$vesselData['registration_number']})");
    }
}
