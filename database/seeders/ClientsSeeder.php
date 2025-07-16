<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentsType;
use App\Models\CustomOffice;
use App\Models\Port;
use App\Models\ClientCompanyRelation;

/**
 * Seeder for creating realistic client data for Argentina and Paraguay
 *
 * Creates clients representing real shipping companies, cargo owners,
 * consignees and notify parties commonly found in river and maritime transport
 */
class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating clients for Argentina and Paraguay...');

        // Get existing companies for relationships
        $companies = Company::where('active', true)->get();

        if ($companies->isEmpty()) {
            $this->command->error('❌ No active companies found. Please run TestUsersSeeder first.');
            return;
        }

        // Get countries
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Countries not found. Please run catalogs seeder first.');
            return;
        }

        // Create Argentine clients
        $this->createArgentineClients($companies);

        // Create Paraguayan clients
        $this->createParaguayanClients($companies);

        // Create special test cases
        $this->createTestCaseClients($companies);

        // Create company-client relationships
        $this->createClientCompanyRelations();

        $this->command->info('✅ Clients seeder completed successfully!');
        $this->displaySummary();
    }

    /**
     * Create realistic Argentine clients
     */
    private function createArgentineClients($companies): void
    {
        $this->command->info('📍 Creating Argentine clients...');

        // Major Argentine shipping/cargo companies
        $argentineClients = [
            [
                'business_name' => 'ALUAR ALUMINIO ARGENTINO S.A.I.C.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonths(6),
                'notes' => 'Principal exportador de aluminio de Argentina'
            ],
            [
                'business_name' => 'SIDERAR S.A.I.C.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonths(3),
                'notes' => 'Siderúrgica - exportación de acero'
            ],
            [
                'business_name' => 'TENARIS S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonth(),
                'notes' => 'Tubos de acero sin costura para petróleo'
            ],
            [
                'business_name' => 'MOLINOS RIO DE LA PLATA S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subWeeks(2),
                'notes' => 'Productos alimentarios y oleaginosas'
            ],
            [
                'business_name' => 'BUNGE ARGENTINA S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subWeek(),
                'notes' => 'Aceites vegetales y commodities'
            ],
            [
                'business_name' => 'CARGILL S.A.C.I.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(3),
                'notes' => 'Trading de granos y oleaginosas'
            ],
            [
                'business_name' => 'DREYFUS ARGENTINA S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(5),
                'notes' => 'Commodities agrícolas'
            ],
            [
                'business_name' => 'ARCELOR MITTAL ACINDAR S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(10),
                'notes' => 'Productos siderúrgicos'
            ],
            [
                'business_name' => 'TERMINAL 6 S.A.',
                'client_type' => 'consignee',
                'status' => 'active',
                'verified_at' => now()->subDays(15),
                'notes' => 'Terminal portuaria Buenos Aires'
            ],
            [
                'business_name' => 'EXOLGAN S.A.',
                'client_type' => 'consignee',
                'status' => 'active',
                'verified_at' => now()->subDays(20),
                'notes' => 'Terminal de contenedores'
            ],
            [
                'business_name' => 'SERVICIOS PORTUARIOS S.A.',
                'client_type' => 'notify_party',
                'status' => 'active',
                'verified_at' => now()->subMonth(),
                'notes' => 'Servicios de handling portuario'
            ],
            [
                'business_name' => 'LOGISTICA INTEGRAL S.R.L.',
                'client_type' => 'owner',
                'status' => 'active',
                'verified_at' => now()->subDays(7),
                'notes' => 'Servicios logísticos integrales'
            ],
            [
                'business_name' => 'TRANSPORTES FLUVIALES DEL PLATA S.A.',
                'client_type' => 'owner',
                'status' => 'active',
                'verified_at' => now()->subDays(12),
                'notes' => 'Transporte fluvial de cargas'
            ],
            [
                'business_name' => 'MULTIMODAL S.A.',
                'client_type' => 'consignee',
                'status' => 'suspended',
                'verified_at' => null,
                'notes' => 'Suspendido por verificación de documentación'
            ],
            [
                'business_name' => 'DEPOSITOS FISCALES S.A.',
                'client_type' => 'notify_party',
                'status' => 'inactive',
                'verified_at' => now()->subYear(),
                'notes' => 'Inactivo - empresa cesó operaciones'
            ]
        ];

        foreach ($argentineClients as $clientData) {
            $client = Client::factory()
                ->argentina()
                ->state($clientData)
                ->create([
                    'created_by_company_id' => $companies->random()->id
                ]);

            $this->command->line("  ✓ {$client->business_name} ({$client->tax_id})");
        }
    }

    /**
     * Create realistic Paraguayan clients
     */
    private function createParaguayanClients($companies): void
    {
        $this->command->info('📍 Creating Paraguayan clients...');

        // Major Paraguayan shipping/cargo companies
        $paraguayanClients = [
            [
                'business_name' => 'PETROPAR S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonths(4),
                'notes' => 'Petróleos del Paraguay - combustibles'
            ],
            [
                'business_name' => 'COPACO S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonths(2),
                'notes' => 'Compañía Paraguaya de Comunicaciones'
            ],
            [
                'business_name' => 'INC S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subMonth(),
                'notes' => 'Industrias Nucleares del Paraguay'
            ],
            [
                'business_name' => 'ACEPAR S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subWeeks(3),
                'notes' => 'Aceros del Paraguay'
            ],
            [
                'business_name' => 'CAPIATÁ S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subWeek(),
                'notes' => 'Productos textiles'
            ],
            [
                'business_name' => 'FRIGOMERC S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(4),
                'notes' => 'Frigorífico y productos cárnicos'
            ],
            [
                'business_name' => 'CONTI PARAGUAY S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(8),
                'notes' => 'Grupo Continental - oleaginosas'
            ],
            [
                'business_name' => 'MINERVA FOODS PARAGUAY S.A.',
                'client_type' => 'shipper',
                'status' => 'active',
                'verified_at' => now()->subDays(12),
                'notes' => 'Frigorífico exportador de carne'
            ],
            [
                'business_name' => 'TERMINAL PORTUARIA ASUNCION S.A.',
                'client_type' => 'consignee',
                'status' => 'active',
                'verified_at' => now()->subDays(16),
                'notes' => 'Terminal de contenedores Asunción'
            ],
            [
                'business_name' => 'PUERTO LIBRE CDE S.A.',
                'client_type' => 'consignee',
                'status' => 'active',
                'verified_at' => now()->subDays(20),
                'notes' => 'Puerto libre Ciudad del Este'
            ],
            [
                'business_name' => 'NAVEGACION PARAGUAYA S.A.',
                'client_type' => 'owner',
                'status' => 'active',
                'verified_at' => now()->subDays(25),
                'notes' => 'Armador nacional paraguayo'
            ],
            [
                'business_name' => 'SERVICIOS LOGISTICOS DEL ESTE S.R.L.',
                'client_type' => 'notify_party',
                'status' => 'active',
                'verified_at' => now()->subDays(6),
                'notes' => 'Servicios de logística y almacenaje'
            ],
            [
                'business_name' => 'TRANSPORTES MULTIMODALES PY S.A.',
                'client_type' => 'owner',
                'status' => 'active',
                'verified_at' => now()->subDays(18),
                'notes' => 'Transporte terrestre y fluvial'
            ],
            [
                'business_name' => 'ZONA FRANCA GLOBAL S.A.',
                'client_type' => 'consignee',
                'status' => 'suspended',
                'verified_at' => null,
                'notes' => 'Suspendido por renovación de permisos'
            ],
            [
                'business_name' => 'COMERCIAL DEL PARANA S.A.',
                'client_type' => 'notify_party',
                'status' => 'inactive',
                'verified_at' => now()->subMonths(8),
                'notes' => 'Inactivo - cambio de razón social'
            ]
        ];

        foreach ($paraguayanClients as $clientData) {
            $client = Client::factory()
                ->paraguay()
                ->state($clientData)
                ->create([
                    'created_by_company_id' => $companies->random()->id
                ]);

            $this->command->line("  ✓ {$client->business_name} ({$client->tax_id})");
        }
    }

    /**
     * Create special test case clients
     */
    private function createTestCaseClients($companies): void
    {
        $this->command->info('🧪 Creating test case clients...');

        // Test case 1: Client with document data variations
        $aluar = Client::factory()
            ->argentina()
            ->shipper()
            ->verified()
            ->create([
                'business_name' => 'ALUAR S.A.',
                'notes' => 'Cliente de prueba para datos variables de documentos',
                'created_by_company_id' => $companies->first()->id
            ]);

        $this->command->line("  ✓ Test case: {$aluar->business_name} (document data variations)");

        // Test case 2: Recently created, unverified client
        $newClient = Client::factory()
            ->argentina()
            ->consignee()
            ->unverified()
            ->create([
                'business_name' => 'NUEVA EMPRESA TEST S.A.',
                'status' => 'active',
                'notes' => 'Cliente recién creado sin verificar',
                'created_by_company_id' => $companies->last()->id
            ]);

        $this->command->line("  ✓ Test case: {$newClient->business_name} (unverified)");

        // Test case 3: Client with multiple roles (via relations)
        $multiRole = Client::factory()
            ->paraguay()
            ->owner()
            ->verified()
            ->create([
                'business_name' => 'MULTIROL TRANSPORT S.A.',
                'notes' => 'Cliente con múltiples roles en diferentes empresas',
                'created_by_company_id' => $companies->random()->id
            ]);

        $this->command->line("  ✓ Test case: {$multiRole->business_name} (multi-role)");

        // Test case 4: Suspended client with reason
        $suspended = Client::factory()
            ->argentina()
            ->suspended()
            ->create([
                'business_name' => 'EMPRESA SUSPENDIDA S.R.L.',
                'notes' => 'Suspendido por documentación vencida - caso de prueba',
                'created_by_company_id' => $companies->random()->id
            ]);

        $this->command->line("  ✓ Test case: {$suspended->business_name} (suspended)");

        // Test case 5: Client ready for webservices
        $webserviceReady = Client::factory()
            ->argentina()
            ->shipper()
            ->verified()
            ->active()
            ->create([
                'business_name' => 'WEBSERVICE READY S.A.',
                'notes' => 'Cliente completamente configurado para webservices',
                'created_by_company_id' => $companies->random()->id
            ]);

        $this->command->line("  ✓ Test case: {$webserviceReady->business_name} (webservice ready)");
    }

    /**
     * Create client-company relationships
     */
    private function createClientCompanyRelations(): void
    {
        $this->command->info('🔗 Creating client-company relationships...');

        $clients = Client::all();
        $companies = Company::where('active', true)->get();

        $relationshipCount = 0;

        foreach ($clients->take(20) as $client) { // First 20 clients for relationships
            // 70% chance of additional company relationship
            if (rand(1, 100) <= 70) {
                $otherCompanies = $companies->where('id', '!=', $client->created_by_company_id);

                if ($otherCompanies->isNotEmpty()) {
                    $relationCompany = $otherCompanies->random();

                    ClientCompanyRelation::create([
                        'client_id' => $client->id,
                        'company_id' => $relationCompany->id,
                        'relation_type' => $this->getRandomRelationType(),
                        'can_edit' => rand(1, 100) <= 60, // 60% can edit
                        'active' => true,
                        'credit_limit' => $this->getRandomCreditLimit(),
                        'internal_code' => $this->generateInternalCode($relationCompany, $client),
                        'priority' => $this->getRandomPriority(),
                        'relation_config' => $this->getRandomRelationConfig(),
                        'created_by_user_id' => 1, // Assume admin user
                        'last_activity_at' => now()->subDays(rand(1, 30))
                    ]);

                    $relationshipCount++;
                }
            }
        }

        $this->command->line("  ✓ Created {$relationshipCount} client-company relationships");
    }

    /**
     * Get random relation type
     */
    private function getRandomRelationType(): string
    {
        $types = ['customer', 'provider', 'both'];
        return $types[array_rand($types)];
    }

    /**
     * Get random credit limit
     */
    private function getRandomCreditLimit(): ?float
    {
        // 50% chance of having credit limit
        if (rand(1, 100) <= 50) {
            return rand(10000, 500000);
        }
        return null;
    }

    /**
     * Generate internal code for company-client relation
     */
    private function generateInternalCode(Company $company, Client $client): string
    {
        $companyPrefix = strtoupper(substr($company->commercial_name, 0, 3));
        $clientNumber = str_pad($client->id, 4, '0', STR_PAD_LEFT);
        return $companyPrefix . '-' . $clientNumber;
    }

    /**
     * Get random priority
     */
    private function getRandomPriority(): string
    {
        $priorities = ['low', 'normal', 'high', 'critical'];
        return $priorities[array_rand($priorities)];
    }

    /**
     * Get random relation configuration
     */
    private function getRandomRelationConfig(): array
    {
        $configs = [
            ['auto_approve' => true, 'notification_email' => true],
            ['auto_approve' => false, 'requires_authorization' => true],
            ['billing_contact' => 'financiero@empresa.com', 'payment_terms' => 30],
            ['preferred_port' => 'ARBUE', 'special_handling' => true],
            []
        ];

        return $configs[array_rand($configs)];
    }

    /**
     * Display summary of created data
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('=== 📊 CLIENTS SUMMARY ===');

        $totalClients = Client::count();
        $argentineClients = Client::whereHas('country', fn($q) => $q->where('iso_code', 'AR'))->count();
        $paraguayanClients = Client::whereHas('country', fn($q) => $q->where('iso_code', 'PY'))->count();

        $activeClients = Client::where('status', 'active')->count();
        $verifiedClients = Client::whereNotNull('verified_at')->count();
        $suspendedClients = Client::where('status', 'suspended')->count();
        $inactiveClients = Client::where('status', 'inactive')->count();

        $shippers = Client::where('client_type', 'shipper')->count();
        $consignees = Client::where('client_type', 'consignee')->count();
        $notifyParties = Client::where('client_type', 'notify_party')->count();
        $owners = Client::where('client_type', 'owner')->count();

        $totalRelations = ClientCompanyRelation::count();

        $this->command->info("Total Clients Created: {$totalClients}");
        $this->command->info("  🇦🇷 Argentina: {$argentineClients}");
        $this->command->info("  🇵🇾 Paraguay: {$paraguayanClients}");
        $this->command->info('');

        $this->command->info('By Status:');
        $this->command->info("  ✅ Active: {$activeClients}");
        $this->command->info("  ✅ Verified: {$verifiedClients}");
        $this->command->info("  ⏸️  Suspended: {$suspendedClients}");
        $this->command->info("  ❌ Inactive: {$inactiveClients}");
        $this->command->info('');

        $this->command->info('By Type:');
        $this->command->info("  📦 Shippers: {$shippers}");
        $this->command->info("  📥 Consignees: {$consignees}");
        $this->command->info("  📧 Notify Parties: {$notifyParties}");
        $this->command->info("  🚢 Owners: {$owners}");
        $this->command->info('');

        $this->command->info("Client-Company Relations: {$totalRelations}");
        $this->command->info('');

        $this->command->info('=== 🧪 TEST CASES CREATED ===');
        $this->command->info('• ALUAR S.A. - Document data variations');
        $this->command->info('• NUEVA EMPRESA TEST S.A. - Unverified client');
        $this->command->info('• MULTIROL TRANSPORT S.A. - Multi-role client');
        $this->command->info('• EMPRESA SUSPENDIDA S.R.L. - Suspended client');
        $this->command->info('• WEBSERVICE READY S.A. - Ready for webservices');
        $this->command->info('');

        $this->command->info('=== 🔧 USAGE EXAMPLES ===');
        $this->command->info('# Find Argentine shippers:');
        $this->command->info('Client::argentina()->shippers()->get()');
        $this->command->info('');
        $this->command->info('# Find verified clients:');
        $this->command->info('Client::verified()->get()');
        $this->command->info('');
        $this->command->info('# Find clients by company:');
        $this->command->info('$company->clients()->get()');
        $this->command->info('');
        $this->command->info('✅ Clients seeder completed successfully!');
    }
}
