<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VesselOwner;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VesselOwnersSeeder extends Seeder
{
    /**
     * CORRECCIÓN 1 - SEEDER PROPIETARIOS DE EMBARCACIONES
     * 
     * Seeder que usa datos reales existentes de las tablas relacionadas:
     * - Companies existentes
     * - Countries existentes (AR/PY)
     * - Users existentes
     * 
     * Crea propietarios de embarcaciones realistas para pruebas
     */
    public function run(): void
    {
        // Verificar que existan datos relacionados
        $companies = Company::where('active', true)->get();
        $argentinaCountry = Country::where('iso_code', 'AR')->first();
        $paraguayCountry = Country::where('iso_code', 'PY')->first();
        $argentinaCountry = Country::where('alpha2_code', 'AR')->first();
        $paraguayCountry = Country::where('alpha2_code', 'PY')->first();
        $users = User::where('active', true)->limit(5)->get();

        if ($companies->isEmpty()) {
            $this->command->warn('⚠️  No hay empresas activas. Ejecuta primero el seeder de empresas.');
            return;
        }

        if (!$argentinaCountry || !$paraguayCountry) {
            $this->command->warn('⚠️  No se encontraron países Argentina/Paraguay. Ejecuta primero el seeder de países.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->warn('⚠️  No hay usuarios activos. Ejecuta primero el seeder de usuarios.');
            return;
        }

        $this->command->info('🚢 Creando propietarios de embarcaciones...');

        // Propietarios realistas para Argentina
        $argentinianOwners = [
            [
                'tax_id' => '20123456789',
                'legal_name' => 'Armadores del Río de la Plata S.A.',
                'commercial_name' => 'ARP Navegación',
                'transportista_type' => 'O',
                'email' => 'info@arpnavegacion.com.ar',
                'phone' => '+54 11 4321-5678',
                'address' => 'Puerto Madero, Dique 4, Local 123',
                'city' => 'Buenos Aires',
                'postal_code' => '1107',
                'status' => 'active',
                'webservice_authorized' => true,
                'notes' => 'Propietario principal de barcazas para transporte de granos',
            ],
            [
                'tax_id' => '20234567890',
                'legal_name' => 'Navegación Fluvial del Paraná S.R.L.',
                'commercial_name' => 'NavParaná',
                'transportista_type' => 'R',
                'email' => 'operaciones@navparana.com.ar',
                'phone' => '+54 341 555-0123',
                'address' => 'Av. Belgrano 1234',
                'city' => 'Rosario',
                'postal_code' => '2000',
                'status' => 'active',
                'webservice_authorized' => true,
                'notes' => 'Especializado en contenedores y carga general',
            ],
            [
                'tax_id' => '20345678901',
                'legal_name' => 'Transportes Marítimos del Sur S.A.',
                'commercial_name' => 'TMS Logística',
                'transportista_type' => 'O',
                'email' => 'contacto@tmslogistica.com.ar',
                'phone' => '+54 221 444-7890',
                'address' => 'Zona Portuaria, Muelle 7',
                'city' => 'La Plata',
                'postal_code' => '1900',
                'status' => 'active',
                'webservice_authorized' => false,
                'notes' => 'En proceso de autorización para webservices',
            ],
        ];

        // Propietarios realistas para Paraguay
        $paraguayanOwners = [
            [
                'tax_id' => '80012345',
                'legal_name' => 'Navegación Paraguay S.A.',
                'commercial_name' => 'NavPy',
                'transportista_type' => 'O',
                'email' => 'info@navpy.com.py',
                'phone' => '+595 21 555-123',
                'address' => 'Puerto de Asunción, Terminal 2',
                'city' => 'Asunción',
                'postal_code' => '1209',
                'status' => 'active',
                'webservice_authorized' => true,
                'notes' => 'Principal transportista fluvial paraguayo',
            ],
            [
                'tax_id' => '80023456',
                'legal_name' => 'Hidrovía del Paraguay S.R.L.',
                'commercial_name' => 'HidroParaguay',
                'transportista_type' => 'R',
                'email' => 'operaciones@hidroparaguay.com.py',
                'phone' => '+595 61 333-456',
                'address' => 'Zona Industrial, Manzana 15',
                'city' => 'Ciudad del Este',
                'postal_code' => '7000',
                'status' => 'active',
                'webservice_authorized' => true,
                'notes' => 'Especializado en transbordos internacionales',
            ],
        ];

        // Crear propietarios argentinos
        foreach ($argentinianOwners as $ownerData) {
            $this->createVesselOwner(
                $ownerData,
                $argentinaCountry,
                $companies->where('country', 'AR')->first() ?? $companies->first(),
                $users->random()
            );
        }

        // Crear propietarios paraguayos
        foreach ($paraguayanOwners as $ownerData) {
            $this->createVesselOwner(
                $ownerData,
                $paraguayCountry,
                $companies->where('country', 'PY')->first() ?? $companies->first(),
                $users->random()
            );
        }

        // Crear algunos propietarios adicionales distribuidos entre empresas
        $this->createAdditionalOwners($companies, $argentinaCountry, $paraguayCountry, $users);

        $this->command->info('✅ Propietarios de embarcaciones creados exitosamente');
    }

    /**
     * Crear un propietario de embarcación con datos relacionados.
     */
    private function createVesselOwner(
        array $ownerData,
        Country $country,
        Company $company,
        User $user
    ): void {
        // Verificar si ya existe
        $existing = VesselOwner::where('tax_id', $ownerData['tax_id'])->first();
        if ($existing) {
            $this->command->warn("⚠️  Propietario {$ownerData['legal_name']} ya existe");
            return;
        }

        VesselOwner::create([
            'tax_id' => $ownerData['tax_id'],
            'legal_name' => $ownerData['legal_name'],
            'commercial_name' => $ownerData['commercial_name'] ?? null,
            'company_id' => $company->id,
            'country_id' => $country->id,
            'transportista_type' => $ownerData['transportista_type'],
            'email' => $ownerData['email'],
            'phone' => $ownerData['phone'],
            'address' => $ownerData['address'],
            'city' => $ownerData['city'],
            'postal_code' => $ownerData['postal_code'],
            'status' => $ownerData['status'],
            'tax_id_verified_at' => $ownerData['status'] === 'active' ? now()->subDays(rand(30, 365)) : null,
            'webservice_authorized' => $ownerData['webservice_authorized'],
            'webservice_config' => $ownerData['webservice_authorized'] ? $this->getWebserviceConfig() : null,
            'notes' => $ownerData['notes'],
            'created_by_user_id' => $user->id,
            'last_activity_at' => now()->subDays(rand(1, 30)),
        ]);

        $this->command->info("✓ Creado: {$ownerData['legal_name']} ({$ownerData['transportista_type']})");
    }

    /**
     * Crear propietarios adicionales distribuidos entre empresas.
     */
    private function createAdditionalOwners(
        $companies,
        Country $argentina,
        Country $paraguay,
        $users
    ): void {
        $additionalOwners = [
            // Argentina
            [
                'tax_id' => '20456789012',
                'legal_name' => 'Barcazas Argentinas S.A.',
                'transportista_type' => 'O',
                'country' => $argentina,
                'phone' => '+54 11 5555-9999',
                'city' => 'Buenos Aires',
            ],
            [
                'tax_id' => '20567890123',
                'legal_name' => 'Fluvial Paraná S.R.L.',
                'transportista_type' => 'R',
                'country' => $argentina,
                'phone' => '+54 341 777-8888',
                'city' => 'Rosario',
            ],
            // Paraguay
            [
                'tax_id' => '80034567',
                'legal_name' => 'Transporte Fluvial del Este S.A.',
                'transportista_type' => 'O',
                'country' => $paraguay,
                'phone' => '+595 61 666-777',
                'city' => 'Ciudad del Este',
            ],
        ];

        foreach ($additionalOwners as $index => $ownerData) {
            $company = $companies->where('country', $ownerData['country']->iso_code)->random() ?? $companies->random();
            
            VesselOwner::create([
                'tax_id' => $ownerData['tax_id'],
                'legal_name' => $ownerData['legal_name'],
                'commercial_name' => null,
                'company_id' => $company->id,
                'country_id' => $ownerData['country']->id,
                'transportista_type' => $ownerData['transportista_type'],
                'email' => 'info' . ($index + 1) . '@' . strtolower(str_replace(' ', '', $ownerData['legal_name'])) . '.com',
                'phone' => $ownerData['phone'],
                'address' => 'Dirección ' . ($index + 1),
                'city' => $ownerData['city'],
                'postal_code' => rand(1000, 9999),
                'status' => 'active',
                'tax_id_verified_at' => now()->subDays(rand(60, 200)),
                'webservice_authorized' => rand(0, 1) === 1,
                'webservice_config' => rand(0, 1) === 1 ? $this->getWebserviceConfig() : null,
                'notes' => 'Propietario adicional para pruebas',
                'created_by_user_id' => $users->random()->id,
                'last_activity_at' => now()->subDays(rand(1, 15)),
            ]);

            $this->command->info("✓ Creado adicional: {$ownerData['legal_name']}");
        }
    }

    /**
     * Generar configuración de webservice típica.
     */
    private function getWebserviceConfig(): array
    {
        return [
            'max_retries' => 3,
            'timeout_seconds' => 60,
            'auto_verify_responses' => true,
            'preferred_environment' => 'production',
            'notification_email' => null,
            'custom_headers' => [
                'X-Client-Version' => '2.0',
                'X-Auth-Method' => 'certificate',
            ],
            'validation_rules' => [
                'strict_tax_id' => true,
                'verify_vessel_registration' => true,
                'require_captain_data' => true,
            ],
        ];
    }
}