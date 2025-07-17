<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CORRECCIÓN 2 - SEEDER DE CLIENTES MODIFICADO
 * 
 * Cambios realizados:
 * - ❌ REMOVIDO: client_type 'owner' 
 * - ❌ REMOVIDO: relaciones ClientCompanyRelation (base compartida)
 * - ✅ MANTIENE: shipper, consignee, notify_party
 * - ✅ CONVIERTE: clientes en base de datos compartida
 * - ✅ COMPATIBLE: con VesselOwner separado
 */
class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando clientes de carga (sin propietarios de embarcaciones)...');

        // Verificar datos relacionados necesarios
        if (!$this->checkRequiredData()) {
            return;
        }

        // Obtener datos relacionados
        $argentinaCountry = Country::where('iso_code', 'AR')->first();
        $paraguayCountry = Country::where('iso_code', 'PY')->first();
        $argentinaCountry = Country::where('alpha2_code', 'AR')->first();
        $paraguayCountry = Country::where('alpha2_code', 'PY')->first();
        $documentTypes = DocumentType::all();
        $ports = Port::all();
        $customs = CustomOffice::all();
        $users = User::where('active', true)->get();
        $companies = Company::where('active', true)->get();

        DB::transaction(function () use (
            $argentinaCountry, 
            $paraguayCountry, 
            $documentTypes, 
            $ports, 
            $customs, 
            $users, 
            $companies
        ) {
            // Crear clientes argentinos realistas
            $this->createArgentinianClients($argentinaCountry, $documentTypes, $ports, $customs, $users, $companies);
            
            // Crear clientes paraguayos realistas
            $this->createParaguayanClients($paraguayCountry, $documentTypes, $ports, $customs, $users, $companies);
            
            // Crear casos de prueba específicos
            $this->createTestCases($argentinaCountry, $paraguayCountry, $documentTypes, $ports, $customs, $users, $companies);
        });

        $this->command->info('✅ Clientes de carga creados exitosamente (base de datos compartida)');
    }

    /**
     * Verificar que existan los datos relacionados necesarios.
     */
    private function checkRequiredData(): bool
    {
        $argentinaCountry = Country::where('iso_code', 'AR')->first();
        $paraguayCountry = Country::where('iso_code', 'PY')->first();
        $argentinaCountry = Country::where('alpha2_code', 'AR')->first();
        $paraguayCountry = Country::where('alpha2_code', 'PY')->first();
        $users = User::where('active', true)->get();
        $companies = Company::where('active', true)->get();

        if (!$argentinaCountry || !$paraguayCountry) {
            $this->command->error('❌ Países Argentina/Paraguay no encontrados. Ejecute BaseCatalogsSeeder primero.');
            return false;
        }

        if ($users->isEmpty()) {
            $this->command->error('❌ No hay usuarios activos. Ejecute TestUsersSeeder primero.');
            return false;
        }

        if ($companies->isEmpty()) {
            $this->command->error('❌ No hay empresas activas. Datos requeridos para auditoría.');
            return false;
        }

        return true;
    }

    /**
     * Crear clientes argentinos realistas.
     */
    private function createArgentinianClients($country, $documentTypes, $ports, $customs, $users, $companies): void
    {
        $this->command->info('🇦🇷 Creando clientes argentinos...');

        // Grandes exportadores de granos
        $argentinianClients = [
            [
                'tax_id' => '30123456789',
                'legal_name' => 'CARGILL S.A.C.I.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Exportador principal de granos y oleaginosas'
            ],
            [
                'tax_id' => '30234567890',
                'legal_name' => 'BUNGE ARGENTINA S.A.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Procesamiento y exportación de soja'
            ],
            [
                'tax_id' => '30345678901',
                'legal_name' => 'MOLINOS RÍO DE LA PLATA S.A.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Alimentos y aceites vegetales'
            ],
            [
                'tax_id' => '30456789012',
                'legal_name' => 'TERMINAL 6 S.A.',
                'client_type' => 'consignee',
                'verified' => true,
                'notes' => 'Terminal portuaria especializada en granos'
            ],
            [
                'tax_id' => '30567890123',
                'legal_name' => 'RENOVA S.A.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Aceites vegetales y biocombustibles'
            ],
            [
                'tax_id' => '30678901234',
                'legal_name' => 'DREYFUS ARGENTINA S.A.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Trading y procesamiento de commodities'
            ],
            [
                'tax_id' => '30789012345',
                'legal_name' => 'NIDERA S.A.',
                'client_type' => 'consignee',
                'verified' => true,
                'notes' => 'Importación de fertilizantes'
            ],
            [
                'tax_id' => '30890123456',
                'legal_name' => 'VICENTÍN S.A.I.C.',
                'client_type' => 'shipper',
                'verified' => false,
                'notes' => 'Aceites y subproductos - pendiente verificación'
            ]
        ];

        foreach ($argentinianClients as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $users, $companies);
        }

        // Notificatarios argentinos
        $notifyParties = [
            [
                'tax_id' => '27123456789',
                'legal_name' => 'DESPACHANTE ADUANERO BUENOS AIRES S.R.L.',
                'client_type' => 'notify_party',
                'verified' => true,
                'notes' => 'Servicios aduaneros especializados'
            ],
            [
                'tax_id' => '27234567890',
                'legal_name' => 'FORWARDER INTERNACIONAL S.A.',
                'client_type' => 'notify_party',
                'verified' => true,
                'notes' => 'Agente de cargas internacional'
            ]
        ];

        foreach ($notifyParties as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $users, $companies);
        }
    }

    /**
     * Crear clientes paraguayos realistas.
     */
    private function createParaguayanClients($country, $documentTypes, $ports, $customs, $users, $companies): void
    {
        $this->command->info('🇵🇾 Creando clientes paraguayos...');

        $paraguayanClients = [
            [
                'tax_id' => '80012345-7',
                'legal_name' => 'CARGILL DEL PARAGUAY S.A.',
                'client_type' => 'consignee',
                'verified' => true,
                'notes' => 'Importación de granos y fertilizantes'
            ],
            [
                'tax_id' => '80023456-8',
                'legal_name' => 'TERMINAL PORTUARIA ASUNCIÓN S.A.',
                'client_type' => 'consignee',
                'verified' => true,
                'notes' => 'Terminal de contenedores'
            ],
            [
                'tax_id' => '80034567-9',
                'legal_name' => 'AGRO SERVICIOS DEL ESTE S.A.',
                'client_type' => 'shipper',
                'verified' => true,
                'notes' => 'Exportación de productos agrícolas'
            ],
            [
                'tax_id' => '80045678-0',
                'legal_name' => 'FERTILIZANTES PARAGUAY S.R.L.',
                'client_type' => 'consignee',
                'verified' => true,
                'notes' => 'Importación y distribución de fertilizantes'
            ],
            [
                'tax_id' => '80056789-1',
                'legal_name' => 'SOJERO EXPORT S.A.',
                'client_type' => 'shipper',
                'verified' => false,
                'notes' => 'Nuevo exportador de soja - verificación pendiente'
            ]
        ];

        foreach ($paraguayanClients as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $users, $companies);
        }

        // Notificatarios paraguayos
        $notifyParties = [
            [
                'tax_id' => '80067890-2',
                'legal_name' => 'DESPACHANTES PARAGUAY S.A.',
                'client_type' => 'notify_party',
                'verified' => true,
                'notes' => 'Agente aduanero nacional'
            ]
        ];

        foreach ($notifyParties as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $users, $companies);
        }
    }

    /**
     * Crear casos de prueba específicos.
     */
    private function createTestCases($argentinaCountry, $paraguayCountry, $documentTypes, $ports, $customs, $users, $companies): void
    {
        $this->command->info('🧪 Creando casos de prueba...');

        // Test case 1: Cliente con datos mínimos (solo CUIT)
        $minimalClient = [
            'tax_id' => '20999888777',
            'legal_name' => 'CLIENTE MÍNIMO S.A.',
            'client_type' => 'shipper',
            'verified' => false,
            'notes' => 'Caso de prueba: datos mínimos requeridos'
        ];
        $this->createClient($minimalClient, $argentinaCountry, $documentTypes, $ports, $customs, $users, $companies);

        // Test case 2: Cliente recién creado sin verificar
        $newClient = [
            'tax_id' => '30999888777',
            'legal_name' => 'NUEVA EMPRESA TEST S.A.',
            'client_type' => 'consignee',
            'verified' => false,
            'notes' => 'Cliente recién creado sin verificar'
        ];
        $this->createClient($newClient, $argentinaCountry, $documentTypes, $ports, $customs, $users, $companies);

        // Test case 3: Cliente de múltiples roles (misma empresa, diferentes documentos)
        $multiRole = [
            'tax_id' => '80999888-7',
            'legal_name' => 'MULTIROL TRANSPORT S.A.',
            'client_type' => 'shipper', // En base compartida, el tipo es orientativo
            'verified' => true,
            'notes' => 'Cliente que actúa como shipper/consignee según el documento'
        ];
        $this->createClient($multiRole, $paraguayCountry, $documentTypes, $ports, $customs, $users, $companies);

        // Test case 4: Cliente suspendido
        $suspended = [
            'tax_id' => '20888777666',
            'legal_name' => 'EMPRESA SUSPENDIDA S.R.L.',
            'client_type' => 'shipper',
            'verified' => true,
            'status' => 'suspended',
            'notes' => 'Suspendido por documentación vencida - caso de prueba'
        ];
        $this->createClient($suspended, $argentinaCountry, $documentTypes, $ports, $customs, $users, $companies);

        // Test case 5: Cliente listo para webservices
        $webserviceReady = [
            'tax_id' => '30777666555',
            'legal_name' => 'WEBSERVICE READY S.A.',
            'client_type' => 'shipper',
            'verified' => true,
            'notes' => 'Cliente completamente configurado para webservices'
        ];
        $this->createClient($webserviceReady, $argentinaCountry, $documentTypes, $ports, $customs, $users, $companies);

        $this->command->info('✓ Casos de prueba creados');
    }

    /**
     * Crear un cliente individual.
     */
    private function createClient(array $clientData, $country, $documentTypes, $ports, $customs, $users, $companies): void
    {
        // Verificar si ya existe
        $existing = Client::where('tax_id', $clientData['tax_id'])->first();
        if ($existing) {
            $this->command->warn("⚠️  Cliente {$clientData['legal_name']} ya existe");
            return;
        }

        // Seleccionar datos relacionados aleatorios
        $documentType = $documentTypes->random();
        $primaryPort = $ports->where('country_id', $country->id)->random();
        $customOffice = $customs->where('country_id', $country->id)->random();
        $user = $users->random();
        $company = $companies->random(); // Para auditoría de creación

        // Crear cliente
        $client = Client::create([
            'tax_id' => $clientData['tax_id'],
            'country_id' => $country->id,
            'document_type_id' => $documentType->id,
            'client_type' => $clientData['client_type'],
            'legal_name' => $clientData['legal_name'],
            'primary_port_id' => $primaryPort?->id,
            'customs_offices_id' => $customOffice?->id,
            'status' => $clientData['status'] ?? 'active',
            'created_by_company_id' => $company->id, // Solo para auditoría
            'verified_at' => $clientData['verified'] ? now()->subDays(rand(30, 365)) : null,
            'notes' => $clientData['notes'],
        ]);

        $this->command->line("  ✓ {$client->legal_name} ({$client->client_type})");
    }

    /**
     * Crear clientes adicionales usando factory (sin owner).
     */
    private function createFactoryClients($countries): void
    {
        $this->command->info('🏭 Creando clientes adicionales con factory...');

        // Solo usar tipos permitidos: shipper, consignee, notify_party
        $allowedTypes = ['shipper', 'consignee', 'notify_party'];

        // Crear clientes argentinos adicionales
        for ($i = 0; $i < 5; $i++) {
            $client = Client::create([
                'tax_id' => '20' . str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'country_id' => $countries['argentina']->id,
                'document_type_id' => 1, // Asumiendo que existe
                'client_type' => $allowedTypes[array_rand($allowedTypes)],
                'legal_name' => 'EMPRESA FACTORY ' . ($i + 1) . ' S.A.',
                'status' => 'active',
                'verified_at' => rand(0, 1) ? now()->subDays(rand(10, 100)) : null,
                'notes' => 'Cliente generado por factory',
                'created_by_company_id' => 1, // Empresa por defecto
            ]);

            $this->command->line("  ✓ Factory: {$client->legal_name}");
        }

        // Crear clientes paraguayos adicionales
        for ($i = 0; $i < 3; $i++) {
            $client = Client::create([
                'tax_id' => '80' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT) . '-' . rand(0, 9),
                'country_id' => $countries['paraguay']->id,
                'document_type_id' => 1,
                'client_type' => $allowedTypes[array_rand($allowedTypes)],
                'legal_name' => 'EMPRESA FACTORY PY ' . ($i + 1) . ' S.A.',
                'status' => 'active',
                'verified_at' => rand(0, 1) ? now()->subDays(rand(10, 100)) : null,
                'notes' => 'Cliente paraguayo generado por factory',
                'created_by_company_id' => 1,
            ]);

            $this->command->line("  ✓ Factory PY: {$client->legal_name}");
        }
    }
}