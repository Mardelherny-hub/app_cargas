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
 * SEEDER CORREGIDO - Clientes con client_roles JSON array
 * 
 * CORRECCIÓN CRÍTICA:
 * - ✅ CORREGIDO: client_type → client_roles (JSON array)
 * - ✅ ADAPTADO: Base de datos compartida (sin ClientCompanyRelation)
 * - ✅ MANTIENE: shipper, consignee, notify_party
 * - ✅ CASOS DE PRUEBA: Clientes con múltiples roles
 */
class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando clientes para base de datos compartida...');

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
            
            // Crear casos de prueba específicos (incluyendo múltiples roles)
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
        
        if (Country::count() === 0) {
            $errors[] = '❌ No hay países registrados';
        }
        
        if (DocumentType::count() === 0) {
            $errors[] = '❌ No hay tipos de documento registrados';
        }
        
        if (Company::where('active', true)->count() === 0) {
            $errors[] = '❌ No hay empresas activas registradas';
        }

        if (!empty($errors)) {
            $this->command->error('Faltan datos requeridos:');
            foreach ($errors as $error) {
                $this->command->line("  {$error}");
            }
            $this->command->info('💡 Ejecute primero: php artisan db:seed --class=BaseCatalogsSeeder');
            return false;
        }

        return true;
    }

    /**
     * Crear clientes argentinos realistas con múltiples roles.
     */
    private function createArgentinianClients($country, $documentTypes, $ports, $customs, $companies): void
    {
        $this->command->info('🇦🇷 Creando clientes argentinos...');

        $argentinianClients = [
            [
                'tax_id' => '30712345678',
                'legal_name' => 'MAERSK ARGENTINA S.A.',
                'client_roles' => ['shipper', 'consignee'], // Múltiples roles
                'verified' => true,
                'notes' => 'Línea naviera internacional - múltiples roles'
            ],
            [
                'tax_id' => '20123456780',
                'legal_name' => 'TERMINAL 4 S.A.',
                'client_roles' => ['consignee'],
                'verified' => true,
                'notes' => 'Terminal portuaria especializada'
            ],
            [
                'tax_id' => '30587654321',
                'legal_name' => 'CARGILL S.A.C.I.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'notes' => 'Exportador de granos'
            ],
            [
                'tax_id' => '27456789012',
                'legal_name' => 'LOGÍSTICA FEDERAL S.R.L.',
                'client_roles' => ['shipper', 'notify_party'], // Combinación común
                'verified' => true,
                'notes' => 'Operador logístico regional'
            ],
            [
                'tax_id' => '30345678901',
                'legal_name' => 'IMPORTACIONES DEL SUR S.A.',
                'client_roles' => ['consignee', 'notify_party'],
                'verified' => true,
                'notes' => 'Importador especializado'
            ],
            [
                'tax_id' => '20987654321',
                'legal_name' => 'AGENCIA MARÍTIMA BUENOS AIRES S.A.',
                'client_roles' => ['shipper', 'consignee', 'notify_party'], // Todos los roles
                'verified' => true,
                'notes' => 'Agencia marítima full service'
            ],
            [
                'tax_id' => '30234567890',
                'legal_name' => 'FRIGORÍFICO EXPORTADOR S.A.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'notes' => 'Exportador de carne'
            ],
            [
                'tax_id' => '27345678901',
                'legal_name' => 'CONTAINERS DEL PLATA S.R.L.',
                'client_roles' => ['consignee'],
                'verified' => true,
                'notes' => 'Especialista en contenedores'
            ],
            [
                'tax_id' => '30456789012',
                'legal_name' => 'TEXTIL INTERNACIONAL S.A.',
                'client_roles' => ['shipper', 'consignee'],
                'verified' => false,
                'notes' => 'Pendiente de verificación AFIP'
            ],
            [
                'tax_id' => '20567890123',
                'legal_name' => 'QUÍMICA ARGENTINA S.A.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'notes' => 'Exportador de productos químicos'
            ]
        ];

        foreach ($argentinianClients as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info("✓ " . count($argentinianClients) . " clientes argentinos creados");
    }

    /**
     * Crear clientes paraguayos realistas con múltiples roles.
     */
    private function createParaguayanClients($country, $documentTypes, $ports, $customs, $companies): void
    {
        $this->command->info('🇵🇾 Creando clientes paraguayos...');

        $paraguayanClients = [
            [
                'tax_id' => '80012345-7',
                'legal_name' => 'NAVIERA DEL PARAGUAY S.A.',
                'client_roles' => ['shipper', 'consignee'],
                'verified' => true,
                'notes' => 'Naviera fluvial principal'
            ],
            [
                'tax_id' => '80023456-8',
                'legal_name' => 'TERMINAL VILLETA S.A.',
                'client_roles' => ['consignee', 'notify_party'],
                'verified' => true,
                'notes' => 'Terminal fluvial especializada'
            ],
            [
                'tax_id' => '80034567-9',
                'legal_name' => 'SOJA PARAGUAYA S.A.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'notes' => 'Exportador de oleaginosas'
            ],
            [
                'tax_id' => '80045678-0',
                'legal_name' => 'LOGÍSTICA HIDROVÍA S.R.L.',
                'client_roles' => ['shipper', 'consignee', 'notify_party'],
                'verified' => true,
                'notes' => 'Operador integral hidrovía'
            ],
            [
                'tax_id' => '80056789-1',
                'legal_name' => 'MADERAS DEL CHACO S.A.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'notes' => 'Exportador forestal'
            ],
            [
                'tax_id' => '80067890-2',
                'legal_name' => 'IMPORTADORA ASUNCIÓN S.A.',
                'client_roles' => ['consignee'],
                'verified' => false,
                'notes' => 'Pendiente verificación SET'
            ],
            [
                'tax_id' => '80078901-3',
                'legal_name' => 'CARGA GENERAL LTDA.',
                'client_roles' => ['shipper', 'notify_party'],
                'verified' => true,
                'notes' => 'Carga general multipropósito'
            ],
            [
                'tax_id' => '80089012-4',
                'legal_name' => 'AGENCIA FLUVIAL PARAGUAY S.A.',
                'client_roles' => ['consignee', 'notify_party'],
                'verified' => true,
                'notes' => 'Agencia especializada río Paraguay'
            ]
        ];

        foreach ($paraguayanClients as $clientData) {
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info("✓ " . count($paraguayanClients) . " clientes paraguayos creados");
    }

    /**
     * Crear casos de prueba específicos.
     */
    private function createTestCases($argentina, $paraguay, $documentTypes, $ports, $customs, $companies): void
    {
        $this->command->info('🧪 Creando casos de prueba...');

        $testCases = [
            // Caso 1: Cliente con datos mínimos
            [
                'tax_id' => '30999999990',
                'legal_name' => 'CASO PRUEBA MÍNIMO S.A.',
                'client_roles' => ['shipper'],
                'verified' => false,
                'country' => $argentina,
                'notes' => 'Caso de prueba: datos mínimos requeridos'
            ],
            // Caso 2: Cliente con TODOS los roles
            [
                'tax_id' => '30888888881',
                'legal_name' => 'MULTI ROL COMPLETO S.A.',
                'client_roles' => ['shipper', 'consignee', 'notify_party'],
                'verified' => true,
                'country' => $argentina,
                'notes' => 'Cliente con todos los roles posibles'
            ],
            // Caso 3: Cliente paraguayo con múltiples roles
            [
                'tax_id' => '80999888-7',
                'legal_name' => 'MULTIROL TRANSPORT S.A.',
                'client_roles' => ['shipper', 'consignee'],
                'verified' => true,
                'country' => $paraguay,
                'notes' => 'Cliente paraguayo múltiples roles'
            ],
            // Caso 4: Cliente suspendido
            [
                'tax_id' => '20888777666',
                'legal_name' => 'EMPRESA SUSPENDIDA S.R.L.',
                'client_roles' => ['shipper'],
                'verified' => true,
                'status' => 'suspended',
                'country' => $argentina,
                'notes' => 'Suspendido por documentación vencida'
            ],
            // Caso 5: Solo notify_party
            [
                'tax_id' => '30777666555',
                'legal_name' => 'NOTIFICACIONES ESPECIALES S.A.',
                'client_roles' => ['notify_party'],
                'verified' => true,
                'country' => $argentina,
                'notes' => 'Cliente especializado en notificaciones'
            ]
        ];

        foreach ($testCases as $clientData) {
            $country = $clientData['country'];
            unset($clientData['country']);
            $this->createClient($clientData, $country, $documentTypes, $ports, $customs, $companies);
        }

        $this->command->info('✓ Casos de prueba creados');
    }

    /**
     * Crear un cliente individual con la nueva estructura.
     */
    private function createClient(array $data, $country, $documentTypes, $ports, $customs, $companies): void
    {
        try {
            // Limpiar CUIT/RUC (solo números)
            $cleanTaxId = preg_replace('/[^0-9]/', '', $data['tax_id']);

            // Verificar que no exista
            if (Client::where('tax_id', $cleanTaxId)->where('country_id', $country->id)->exists()) {
                $this->command->warn("⚠️ Cliente {$cleanTaxId} ya existe, omitiendo...");
                return;
            }

            // Obtener tipo de documento apropiado para el país
            $documentType = $documentTypes->where('country_id', $country->id)->first();
            if (!$documentType) {
                $this->command->error("❌ No hay tipos de documento para {$country->name}");
                return;
            }

            // Seleccionar empresa creadora aleatoria
            $createdByCompany = $companies->random();

            // Preparar datos del cliente
            $clientData = [
                'tax_id' => $cleanTaxId,
                'country_id' => $country->id,
                'document_type_id' => $documentType->id,
                'client_roles' => $data['client_roles'], // ✅ CORRECCIÓN: JSON array
                'legal_name' => $data['legal_name'],
                'primary_port_id' => $ports->random()->id ?? null,
                'customs_offices_id' => $customs->where('country_id', $country->id)->random()->id ?? null,
                'status' => $data['status'] ?? 'active',
                'created_by_company_id' => $createdByCompany->id,
                'verified_at' => ($data['verified'] ?? false) ? now() : null,
                'notes' => $data['notes'] ?? null,
            ];

            // Crear cliente
            Client::create($clientData);

            $rolesStr = implode(', ', $data['client_roles']);
            $this->command->line("  ✓ {$data['legal_name']} ({$cleanTaxId}) - Roles: {$rolesStr}");

        } catch (\Exception $e) {
            $this->command->error("❌ Error creando cliente {$data['legal_name']}: " . $e->getMessage());
        }
    }
}