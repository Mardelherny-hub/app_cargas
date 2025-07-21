<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VesselOwner;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Carbon;

class VesselOwnersTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando propietarios de embarcaciones de prueba...');

        // Obtener países usando alpha2_code como en BaseCatalogsSeeder
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();
        
        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Faltan países Argentina (AR) o Paraguay (PY).');
            $this->command->info('Por favor ejecute primero: php artisan db:seed --class=BaseCatalogsSeeder');
            return;
        }

        // Obtener empresas con rol "Cargas"
        $companies = Company::whereJsonContains('company_roles', 'Cargas')
            ->where('active', true)
            ->get();

        if ($companies->isEmpty()) {
            $this->command->error('❌ No hay empresas activas con rol "Cargas".');
            $this->command->info('Por favor ejecute primero:');
            $this->command->info('  php artisan db:seed --class=BaseCatalogsSeeder');
            $this->command->info('  php artisan db:seed --class=TestUsersSeeder');
            return;
        }

        // Obtener un usuario para auditoría
        $adminUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'company-admin');
        })->first();

        // Datos de propietarios de prueba
        $vesselOwnersData = [
            // Propietarios argentinos
            [
                'tax_id' => '30707654321',
                'legal_name' => 'Naviera Río de la Plata S.A.',
                'commercial_name' => 'Naviera RDP',
                'country_id' => $argentina->id,
                'transportista_type' => 'O', // Operador
                'email' => 'info@navierardp.com.ar',
                'phone' => '+54 11 4567-8900',
                'address' => 'Av. Madero 900, Puerto Madero',
                'city' => 'Buenos Aires',
                'postal_code' => 'C1106',
                'status' => 'active',
                'webservice_authorized' => true,
                'tax_id_verified_at' => Carbon::now()->subMonths(6),
                'notes' => 'Principal operador de barcazas en el Río de la Plata',
            ],
            [
                'tax_id' => '30698765432',
                'legal_name' => 'Transporte Fluvial Paraná S.R.L.',
                'commercial_name' => 'TFP',
                'country_id' => $argentina->id,
                'transportista_type' => 'R', // Representante
                'email' => 'operaciones@tfp.com.ar',
                'phone' => '+54 341 456-7890',
                'address' => 'Av. Belgrano 1850',
                'city' => 'Rosario',
                'postal_code' => 'S2000',
                'status' => 'active',
                'webservice_authorized' => true,
                'tax_id_verified_at' => Carbon::now()->subMonths(3),
                'notes' => 'Representante de embarcaciones extranjeras',
            ],
            [
                'tax_id' => '30612345678',
                'legal_name' => 'Barcazas del Litoral S.A.',
                'commercial_name' => 'Barlitoral',
                'country_id' => $argentina->id,
                'transportista_type' => 'O',
                'email' => 'contacto@barlitoral.com',
                'phone' => '+54 343 422-3344',
                'address' => 'Ruta 168 Km 5',
                'city' => 'Santa Fe',
                'postal_code' => 'S3000',
                'status' => 'active',
                'webservice_authorized' => false,
                'notes' => 'Especializado en transporte de granos',
            ],
            [
                'tax_id' => '33654789012',
                'legal_name' => 'Navegación Norte S.A.',
                'commercial_name' => 'NavNorte',
                'country_id' => $argentina->id,
                'transportista_type' => 'O',
                'email' => 'info@navnorte.com.ar',
                'phone' => '+54 3624 123456',
                'address' => 'Puerto Barranqueras, Zona Portuaria',
                'city' => 'Resistencia',
                'postal_code' => 'H3500',
                'status' => 'active',
                'webservice_authorized' => true,
                'tax_id_verified_at' => Carbon::now()->subMonths(4),
                'notes' => 'Opera principalmente en el norte argentino',
            ],

            // Propietarios paraguayos
            [
                'tax_id' => '800234567',
                'legal_name' => 'Navegación Paraguay S.A.',
                'commercial_name' => 'NavPy',
                'country_id' => $paraguay->id,
                'transportista_type' => 'O',
                'email' => 'info@navpy.com.py',
                'phone' => '+595 21 234-5678',
                'address' => 'Av. Costanera 1234',
                'city' => 'Asunción',
                'postal_code' => '1001',
                'status' => 'active',
                'webservice_authorized' => true,
                'tax_id_verified_at' => Carbon::now()->subMonths(1),
                'notes' => 'Principal operador paraguayo en la Hidrovía',
            ],
            [
                'tax_id' => '800345678',
                'legal_name' => 'Transporte Guaraní S.R.L.',
                'commercial_name' => 'TG Fluvial',
                'country_id' => $paraguay->id,
                'transportista_type' => 'O',
                'email' => 'operaciones@tguarani.py',
                'phone' => '+595 61 234-567',
                'address' => 'Puerto Villeta, Zona Industrial',
                'city' => 'Villeta',
                'postal_code' => '2680',
                'status' => 'suspended',
                'webservice_authorized' => false,
                'notes' => 'Suspendido temporalmente por renovación de flota',
            ],
            [
                'tax_id' => '800456789',
                'legal_name' => 'Barcazas del Paraguay S.A.',
                'commercial_name' => null,
                'country_id' => $paraguay->id,
                'transportista_type' => 'R',
                'email' => 'info@barcazaspy.com.py',
                'phone' => '+595 21 345-6789',
                'address' => 'Terminal Portuaria Asunción',
                'city' => 'Asunción',
                'postal_code' => '1209',
                'status' => 'pending_verification',
                'webservice_authorized' => false,
                'notes' => 'Nuevo operador en proceso de habilitación',
            ],

            // Propietarios paraguayos
            [
                'tax_id' => '80023456789',
                'legal_name' => 'Navegación Paraguay S.A.',
                'commercial_name' => 'NavPy',
                'country_id' => $paraguay->id,
                'transportista_type' => 'O',
                'email' => 'info@navpy.com.py',
                'phone' => '+595 21 234-5678',
                'address' => 'Av. Costanera 1234',
                'city' => 'Asunción',
                'postal_code' => '1001',
                'status' => 'active',
                'webservice_authorized' => true,
                'tax_id_verified_at' => Carbon::now()->subMonths(1),
                'notes' => 'Principal operador paraguayo en la Hidrovía',
            ],
            [
                'tax_id' => '80034567890',
                'legal_name' => 'Transporte Guaraní S.R.L.',
                'commercial_name' => null,
                'country_id' => $paraguay->id,
                'transportista_type' => 'O',
                'email' => 'operaciones@tguarani.py',
                'phone' => '+595 61 234-567',
                'address' => 'Puerto Villeta, Zona Industrial',
                'city' => 'Villeta',
                'postal_code' => '2680',
                'status' => 'suspended',
                'webservice_authorized' => false,
                'notes' => 'Suspendido temporalmente por renovación de flota',
            ],
        ];

        // Crear propietarios distribuyéndolos entre las empresas
        foreach ($vesselOwnersData as $index => $ownerData) {
            // Asignar empresa de forma rotativa
            $company = $companies[$index % $companies->count()];
            
            $ownerData['company_id'] = $company->id;
            $ownerData['created_by_user_id'] = $adminUser ? $adminUser->id : null;
            $ownerData['last_activity_at'] = Carbon::now()->subDays(rand(1, 30));

            // Configuración de webservice si está autorizado
            if ($ownerData['webservice_authorized']) {
                $ownerData['webservice_config'] = [
                    'api_key' => 'test_' . strtolower(str_replace(' ', '_', $ownerData['commercial_name'] ?? $ownerData['legal_name'])),
                    'endpoint_preference' => rand(0, 1) ? 'primary' : 'backup',
                    'timeout_seconds' => 30,
                    'retry_attempts' => 3,
                ];
            }

            $owner = VesselOwner::create($ownerData);
            
            $this->command->info("  ✅ Creado: {$owner->legal_name} - {$owner->tax_id}");
        }

        $this->command->info('');
        $this->command->info('📊 Resumen de propietarios creados:');
        $this->command->info('  Total: ' . VesselOwner::count());
        $this->command->info('  Activos: ' . VesselOwner::where('status', 'active')->count());
        $this->command->info('  Con WebService: ' . VesselOwner::where('webservice_authorized', true)->count());
        $this->command->info('  Operadores: ' . VesselOwner::where('transportista_type', 'O')->count());
        $this->command->info('  Representantes: ' . VesselOwner::where('transportista_type', 'R')->count());
    }
}