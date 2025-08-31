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
 * SEEDER SIMPLIFICADO - Clientes sin roles
 * 
 * SIMPLIFICACIÓN APLICADA:
 * - ❌ REMOVIDO: client_roles (según feedback del cliente)
 * - ✅ SIMPLIFICADO: Los clientes son solo empresas propietarias de mercadería
 * - ✅ MANTIENE: Base de datos compartida
 * - ✅ NUEVOS CAMPOS: commercial_name, address, email básicos
 */
class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando clientes simplificados para base de datos compartida...');

        // Verificar datos relacionados necesarios
        if (!$this->checkRequiredData()) {
            return;
        }

        // Obtener datos relacionados
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();
        $documentTypes = DocumentType::all();
        $ports = Port::all();
        $customs = CustomOffice::all();
        $companies = Company::where('active', true)->get();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Países no encontrados. Ejecute BaseCatalogsSeeder primero.');
            return;
        }

        DB::transaction(function () use (
            $argentina, 
            $paraguay, 
            $documentTypes, 
            $ports, 
            $customs, 
            $companies
        ) {
            // Crear clientes argentinos realistas
            $this->createArgentinianClients($argentina, $documentTypes, $ports, $customs, $companies);
            
            // Crear clientes paraguayos realistas
            $this->createParaguayanClients($paraguay, $documentTypes, $ports, $customs, $companies);
            
            // Crear casos de prueba específicos
            $this->createTestCases($argentina, $paraguay, $documentTypes, $ports, $customs, $companies);
        });

        $this->command->info('✅ Clientes creados exitosamente en base de datos compartida');
    }

    /**
     * Verificar que existan los datos relacionados necesarios.
     */
    private function checkRequiredData(): bool
    {
        $errors = [];

        if (!Country::exists()) {
            $errors[] = 'Countries no encontrados. Ejecute BaseCatalogsSeeder.';
        }

        if (!DocumentType::exists()) {
            $errors[] = 'DocumentTypes no encontrados. Ejecute BaseCatalogsSeeder.';
        }

        if (!Company::exists()) {
            $errors[] = 'Companies no encontradas. Ejecute CompaniesSeeder.';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->command->error('❌ ' . $error);
            }
            return false;
        }

        return true;
    }

    /**
     * Crear clientes argentinos realistas
     */
    private function createArgentinianClients(
        Country $argentina,
        $documentTypes,
        $ports,
        $customs,
        $companies
    ): void {
        $this->command->info('🇦🇷 Creando clientes argentinos...');

        $argentinianClients = [
            [
                'tax_id' => '20123456781',
                'legal_name' => 'Agropecuaria San Miguel S.A.',
                'commercial_name' => 'San Miguel Agro',
                'address' => 'Av. del Libertador 1234, CABA',
                'email' => 'contacto@sanmiguelagro.com.ar;operaciones@sanmiguelagro.com.ar',
            ],
            [
                'tax_id' => '30234567892',
                'legal_name' => 'Frigorífico del Norte S.A.',
                'commercial_name' => 'Frigorífico Norte',
                'address' => 'Ruta Nacional 9 Km 45, Rosario',
                'email' => 'info@frigonorte.com.ar;logistica@frigonorte.com.ar',
            ],
            [
                'tax_id' => '27345678903',
                'legal_name' => 'Cereales Río de la Plata S.R.L.',
                'commercial_name' => 'Cereales Río Plata',
                'address' => 'Puerto de Buenos Aires, Dock Sud',
                'email' => 'cereales@rioplata.com.ar;exportaciones@rioplata.com.ar',
            ],
            [
                'tax_id' => '33456789014',
                'legal_name' => 'Logística Integral del Sur S.A.',
                'commercial_name' => 'LIS Logística',
                'address' => 'Av. Corrientes 3456, Buenos Aires',
                'email' => 'contacto@lislogistica.com.ar',
            ],
            [
                'tax_id' => '20567890125',
                'legal_name' => 'Exportadora Buenos Aires S.A.',
                'commercial_name' => 'Exportadora BA',
                'address' => 'Florida 567, Microcentro, CABA',
                'email' => 'ventas@exportadoraba.com.ar;admin@exportadoraba.com.ar',
            ],
            [
                'tax_id' => '30678901236',
                'legal_name' => 'Terminal Portuaria del Plata S.A.',
                'commercial_name' => 'TP Plata',
                'address' => 'Puerto de Rosario, Terminal 3',
                'email' => 'terminal@tpplata.com.ar;operaciones@tpplata.com.ar;gerencia@tpplata.com.ar',
            ],
            [
                'tax_id' => '27789012347',
                'legal_name' => 'Containers Argentina S.R.L.',
                'commercial_name' => 'Containers AR',
                'address' => 'Av. Madero 789, Puerto Madero',
                'email' => 'info@containersarg.com.ar',
            ],
            [
                'tax_id' => '33890123458',
                'legal_name' => 'Naviera del Paraná S.A.',
                'commercial_name' => 'Naviera Paraná',
                'address' => 'Costanera Norte 1234, Buenos Aires',
                'email' => 'contacto@navparana.com.ar;flota@navparana.com.ar',
            ],
            [
                'tax_id' => '20901234569',
                'legal_name' => 'Importadora del Litoral S.A.',
                'commercial_name' => 'Importadora Litoral',
                'address' => 'San Martín 2345, Santa Fe',
                'email' => 'importaciones@litoral.com.ar;admin@litoral.com.ar',
            ],
            [
                'tax_id' => '30012345670',
                'legal_name' => 'Agencia Marítima del Río S.R.L.',
                'commercial_name' => 'AM Río',
                'address' => 'Dársena Norte, Puerto de Buenos Aires',
                'email' => 'agencia@amrio.com.ar;capitania@amrio.com.ar',
            ],
        ];

        foreach ($argentinianClients as $clientData) {
            $this->createClient($clientData, $argentina, $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info('  ✅ ' . count($argentinianClients) . ' clientes argentinos creados');
    }

    /**
     * Crear clientes paraguayos realistas
     */
    private function createParaguayanClients(
        Country $paraguay,
        $documentTypes,
        $ports,
        $customs,
        $companies
    ): void {
        $this->command->info('🇵🇾 Creando clientes paraguayos...');

        $paraguayanClients = [
            [
                'tax_id' => '80123456781',
                'legal_name' => 'Agroindustrias Paraguay S.A.',
                'commercial_name' => 'Agro Paraguay',
                'address' => 'Av. Mariscal López 1234, Asunción',
                'email' => 'contacto@agroparaguay.com.py;ventas@agroparaguay.com.py',
            ],
            [
                'tax_id' => '80234567892',
                'legal_name' => 'Frigorífico Guaraní S.A.',
                'commercial_name' => 'Frigorífico Guaraní',
                'address' => 'Ruta 1 Km 25, San Lorenzo',
                'email' => 'info@frigoguarani.com.py;exportaciones@frigoguarani.com.py',
            ],
            [
                'tax_id' => '80345678903',
                'legal_name' => 'Exportadora del Paraguay S.R.L.',
                'commercial_name' => 'Export Paraguay',
                'address' => 'Centro de Asunción, Paraguay',
                'email' => 'export@paraguay.com.py;admin@paraguay.com.py',
            ],
            [
                'tax_id' => '80456789014',
                'legal_name' => 'Terminal Villeta S.A.',
                'commercial_name' => 'Terminal Villeta',
                'address' => 'Puerto de Villeta, Paraguay',
                'email' => 'terminal@villeta.com.py;operaciones@villeta.com.py;puerto@villeta.com.py',
            ],
            [
                'tax_id' => '80567890125',
                'legal_name' => 'Logística del Paraguay S.A.',
                'commercial_name' => 'Logi Paraguay',
                'address' => 'Av. España 567, Asunción',
                'email' => 'logistica@paraguay.com.py',
            ],
            [
                'tax_id' => '80678901236',
                'legal_name' => 'Cereales de la Región S.R.L.',
                'commercial_name' => 'Cereales Región',
                'address' => 'Ciudad del Este, Paraguay',
                'email' => 'cereales@region.com.py;ventas@region.com.py',
            ],
            [
                'tax_id' => '80789012347',
                'legal_name' => 'Naviera Paraguay S.A.',
                'commercial_name' => 'Naviera PY',
                'address' => 'Puerto de Asunción, Paraguay',
                'email' => 'naviera@paraguay.com.py',
            ],
            [
                'tax_id' => '80890123458',
                'legal_name' => 'Importadora Guaraní S.A.',
                'commercial_name' => 'Import Guaraní',
                'address' => 'Av. Artigas 890, Asunción',
                'email' => 'import@guarani.com.py;contacto@guarani.com.py',
            ],
        ];

        foreach ($paraguayanClients as $clientData) {
            $this->createClient($clientData, $paraguay, $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info('  ✅ ' . count($paraguayanClients) . ' clientes paraguayos creados');
    }

    /**
     * Crear casos de prueba específicos
     */
    private function createTestCases(
        Country $argentina,
        Country $paraguay,
        $documentTypes,
        $ports,
        $customs,
        $companies
    ): void {
        $this->command->info('🧪 Creando casos de prueba...');

        $testCases = [
            [
                'tax_id' => '20999888777',
                'legal_name' => 'Cliente de Prueba Argentina S.A.',
                'commercial_name' => 'Test Cliente AR',
                'address' => 'Dirección de Prueba 123, Buenos Aires',
                'email' => 'test@cliente.com.ar',
                'country' => $argentina,
            ],
            [
                'tax_id' => '80999888777',
                'legal_name' => 'Cliente de Prueba Paraguay S.A.',
                'commercial_name' => 'Test Cliente PY',
                'address' => 'Dirección de Prueba 456, Asunción',
                'email' => 'test@cliente.com.py;admin@cliente.com.py',
                'country' => $paraguay,
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->createClient($testCase, $testCase['country'], $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info('  ✅ ' . count($testCases) . ' casos de prueba creados');
    }

    /**
     * Crear un cliente individual
     */
    /**
     * Crear un cliente individual
     * CORREGIDO: Sin actualizar primary_contact_data_id que no existe
     */
    private function createClient(
        array $clientData,
        Country $country,
        $documentTypes,
        $ports,
        $customs,
        $companies
    ): void {
        try {
            // Verificar si ya existe
            $existing = Client::where('tax_id', $clientData['tax_id'])
                             ->where('country_id', $country->id)
                             ->first();

            if ($existing) {
                $this->command->warn("  ⚠️ Cliente {$clientData['legal_name']} ya existe");
                return;
            }

            // Obtener tipo de documento del país
            $documentType = $documentTypes->where('country_id', $country->id)->first();
            if (!$documentType) {
                $this->command->error("  ❌ No se encontró tipo de documento para {$country->name}");
                return;
            }

            // Seleccionar empresa creadora aleatoria
            $creatorCompany = $companies->random();

            // Preparar datos del cliente
            $clientRecord = [
                'tax_id' => $clientData['tax_id'],
                'country_id' => $country->id,
                'document_type_id' => $documentType->id,
                'legal_name' => $clientData['legal_name'],
                'commercial_name' => $clientData['commercial_name'] ?? null,
                'primary_port_id' => $ports->where('country_id', $country->id)->random()->id ?? null,
                'customs_offices_id' => $customs->where('country_id', $country->id)->random()->id ?? null,
                'status' => 'active',
                'created_by_company_id' => $creatorCompany->id,
                'verified_at' => now()->subDays(rand(1, 365)),
                'notes' => 'Cliente creado por seeder - datos de prueba',
            ];

            // CREAR EL CLIENTE
            $client = Client::create($clientRecord);

            // CREAR CONTACTO PRINCIPAL (ya no se vincula a primary_contact_data_id)
            $emails = isset($clientData['email']) ? explode(';', $clientData['email']) : [];
            $primaryEmail = $emails[0] ?? null;
            $secondaryEmail = $emails[1] ?? null;

            $contact = $client->contactData()->create([
                'email' => $primaryEmail,
                'secondary_email' => $secondaryEmail,
                'address_line_1' => $clientData['address'] ?? null,
                'city' => $country->name === 'Argentina' ? 'Buenos Aires' : 'Asunción',
                'state_province' => $country->name === 'Argentina' ? 'CABA' : 'Asunción',
                'postal_code' => $country->name === 'Argentina' ? 'C1000' : '1001',
                'is_primary' => true,
                'active' => true,
                'created_by_user_id' => User::first()->id,
            ]);

            // ❌ REMOVIDO: No actualizar primary_contact_data_id porque no existe en la tabla clients
            // $client->primary_contact_data_id = $contact->id;
            // $client->save();

            $this->command->line("  ✓ {$clientData['legal_name']}");

        } catch (\Exception $e) {
            $this->command->error("  ❌ Error al crear {$clientData['legal_name']}: " . $e->getMessage());
        }
    }
}