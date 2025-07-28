<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use App\Models\User;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Seeder de Dependencias Básicas CORREGIDO
 * 
 * Seeder que crea las dependencias básicas necesarias para los seeders
 * de viajes y transacciones webservice del módulo 4.
 * 
 * ✅ CORRECCIÓN: Usa SOLO campos que existen en las tablas reales
 * ✅ VERIFICADO: Campos basados en fillable de modelos reales
 * 
 * DEPENDENCIAS CREADAS:
 * - Países: Argentina (AR) y Paraguay (PY)
 * - Puertos: ARBUE (Buenos Aires) y PYTVT (Terminal Villeta)
 * - Empresa: MAERSK LINE ARGENTINA S.A.
 * - Usuario admin para testing
 */
class WebserviceBasicDependenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔧 Creando dependencias básicas para módulo webservices...');

        DB::beginTransaction();

        try {
            // 1. Crear países Argentina y Paraguay
            $countries = $this->createCountries();
            $this->command->info('✅ Países creados: Argentina y Paraguay');

            // 2. Crear puertos principales de la ruta
            $ports = $this->createPorts($countries);
            $this->command->info('✅ Puertos creados: ARBUE y PYTVT');

            // 3. Crear empresa MAERSK
            $maersk = $this->createMaerskCompany();
            $this->command->info('✅ Empresa MAERSK creada: ' . $maersk->legal_name);

           
             // 5. Crear usuarios MAERSK específicos
            $maerskUsers = $this->createMaerskUsers($maersk);
            $this->command->info('✅ Usuarios MAERSK específicos: ' . count($maerskUsers));


            DB::commit();

            $this->command->info('🎉 Dependencias básicas creadas exitosamente!');
            $this->displaySummary($countries, $ports, $maersk, $maerskUsers);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error creando dependencias: ' . $e->getMessage());
            $this->command->error('📍 Archivo: ' . $e->getFile() . ' línea ' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Crear países Argentina y Paraguay usando SOLO campos reales
     */
    private function createCountries(): array
    {
        // Argentina - usando SOLO campos del fillable real
        $argentina = Country::updateOrCreate(
            ['alpha2_code' => 'AR'], // Buscar por alpha2_code que es lo que verifica el comando
            [
                'iso_code' => 'AR',
                'alpha2_code' => 'AR',
                'numeric_code' => '032',
                'name' => 'Argentina',
                'official_name' => 'República Argentina',
                'nationality' => 'argentino',
                'customs_code' => 'AR',
                'senasa_code' => 'AR',
                'document_format' => '99-99999999-9', // Solo el formato, no un JSON
                'currency_code' => 'ARS',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'primary_language' => 'es',
                'allows_import' => true,
                'allows_export' => true,
                'allows_transit' => true,
                'requires_visa' => false,
                'active' => true,
                'display_order' => 1,
                'is_primary' => true,
                'created_date' => now(),
            ]
        );

        // Paraguay - usando SOLO campos del fillable real
        $paraguay = Country::updateOrCreate(
            ['alpha2_code' => 'PY'], // Buscar por alpha2_code que es lo que verifica el comando
            [
                'iso_code' => 'PY',
                'alpha2_code' => 'PY',
                'numeric_code' => '600',
                'name' => 'Paraguay',
                'official_name' => 'República del Paraguay',
                'nationality' => 'paraguayo',
                'customs_code' => 'PY',
                'senasa_code' => 'PY',
                'document_format' => '99999999-9', // Solo el formato, no un JSON
                'currency_code' => 'PYG',
                'timezone' => 'America/Asuncion',
                'primary_language' => 'es',
                'allows_import' => true,
                'allows_export' => true,
                'allows_transit' => true,
                'requires_visa' => false,
                'active' => true,
                'display_order' => 2,
                'is_primary' => true,
                'created_date' => now(),
            ]
        );

        return [
            'argentina' => $argentina,
            'paraguay' => $paraguay
        ];
    }

    /**
     * Crear puertos usando SOLO campos reales del fillable
     */
    private function createPorts(array $countries): array
    {
        // Puerto Buenos Aires (ARBUE)
        $arbue = Port::updateOrCreate(
            ['code' => 'ARBUE'],
            [
                'name' => 'Puerto de Buenos Aires',
                'short_name' => 'Buenos Aires',
                'local_name' => 'Puerto de Buenos Aires',
                'country_id' => $countries['argentina']->id,
                'city' => 'Buenos Aires',
                'province_state' => 'Ciudad Autónoma de Buenos Aires',
                'address' => 'Av. Ramón Castilla 1',
                'postal_code' => 'C1104AAA',
                'latitude' => -34.6118,
                'longitude' => -58.3960,
                'water_depth' => 10.0,
                'port_type' => 'river',
                'port_category' => 'major',
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_passengers' => false,
                'handles_dangerous_goods' => true,
                'has_customs_office' => true,
                'max_vessel_length' => 300.0,
                'max_draft' => 10.0,
                'berths_count' => 15,
                'storage_area' => 50000,
                'has_crane' => true,
                'has_warehouse' => true,
                'timezone' => 'America/Argentina/Buenos_Aires',
                'webservice_code' => 'ARBUE',
                'webservice_config' => [
                    'afip_code' => 'ARBUE',
                    'customs_office_id' => '001'
                ],
                'supports_anticipada' => true,
                'supports_micdta' => true,
                'supports_manifest' => true,
                'operating_hours' => '24/7',
                'operates_24h' => true,
                'has_pilot_service' => true,
                'has_tugboat_service' => true,
                'has_fuel_service' => true,
                'has_fresh_water' => true,
                'has_waste_disposal' => true,
                'available_services' => [
                    'pilotaje', 'remolque', 'combustible', 'agua'
                ],
                'phone' => '+54 11 4310-9200',
                'email' => 'info@puertobuenosaires.gov.ar',
                'website' => 'https://www.puertobuenosaires.gov.ar',
                'vhf_channel' => '16',
                'tariff_structure' => ['basic' => 100.0, 'premium' => 150.0],
                'currency_code' => 'ARS',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 1,
                'established_date' => '1826-01-01',
                'created_date' => now(),
            ]
        );

        // Terminal Villeta Paraguay (PYTVT)
        $pytvt = Port::updateOrCreate(
            ['code' => 'PYTVT'],
            [
                'name' => 'Terminal Villeta',
                'short_name' => 'Villeta',
                'local_name' => 'Terminal Portuario Villeta',
                'country_id' => $countries['paraguay']->id,
                'city' => 'Villeta',
                'province_state' => 'Central',
                'address' => 'Ruta 1, Km 37',
                'postal_code' => '2770',
                'latitude' => -25.5097,
                'longitude' => -57.5659,
                'port_type' => 'river',
                'port_category' => 'major',
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_passengers' => false,
                'handles_dangerous_goods' => false,
                'has_customs_office' => true,
                'max_vessel_length' => 200.0,
                'max_draft' => 8.0,
                'berths_count' => 8,
                'storage_area' => 25000,
                'has_crane' => true,
                'has_warehouse' => true,
                'timezone' => 'America/Asuncion',
                'webservice_code' => 'PYTVT',
                'webservice_config' => [
                    'dna_code' => 'VILLETA',
                    'customs_office_id' => 'VIL001'
                ],
                'supports_anticipada' => false,
                'supports_micdta' => false,
                'supports_manifest' => true,
                'operating_hours' => '06:00-22:00',
                'operates_24h' => false,
                'has_pilot_service' => true,
                'has_tugboat_service' => false,
                'has_fuel_service' => true,
                'has_fresh_water' => true,
                'has_waste_disposal' => true,
                'available_services' => [
                    'pilotaje', 'combustible', 'agua'
                ],
                'phone' => '+595 25 222-333',
                'email' => 'terminal@villeta.com.py',
                'website' => 'https://www.terminalvilleta.com.py',
                'vhf_channel' => '12',
                'tariff_structure' => ['basic' => 50.0, 'premium' => 75.0],
                'currency_code' => 'PYG',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 2,
                'established_date' => '1995-06-15',
                'created_date' => now(),
            ]
        );

        return [
            'arbue' => $arbue,
            'pytvt' => $pytvt
        ];
    }

    /**
     * Crear empresa MAERSK usando SOLO campos reales del fillable
     */
    private function createMaerskCompany(): Company
    {
        return Company::updateOrCreate(
            ['tax_id' => '30688415531'],
            [
                'legal_name' => 'MAERSK LINE ARGENTINA S.A.',
                'commercial_name' => 'MAERSK LINE ARGENTINA',
                'country' => 'AR',
                'email' => 'operations@maersk.com.ar',
                'phone' => '+54 11 4878-3000',
                'address' => 'Av. Libertador 1969, Piso 10',
                'city' => 'Buenos Aires',
                'postal_code' => 'C1425FTE',
                'company_roles' => ['Cargas', 'Transbordos'],
                'roles_config' => [
                    'Cargas' => [
                        'enabled' => true,
                        'webservices' => ['anticipada', 'micdta']
                    ],
                    'Transbordos' => [
                        'enabled' => true,
                        'webservices' => ['transbordos']
                    ]
                ],
                'certificate_path' => '/certificates/maersk_test.p12',
                'certificate_password' => encrypt('test_password'),
                'certificate_alias' => 'maersk_test',
                'certificate_expires_at' => now()->addYear(),
                'ws_config' => [
                    'argentina' => [
                        'afip_enabled' => true,
                        'webservices' => ['anticipada', 'micdta', 'transbordos']
                    ],
                    'paraguay' => [
                        'dna_enabled' => true,
                        'webservices' => ['manifiestos', 'consultas']
                    ]
                ],
                'ws_active' => true,
                'ws_environment' => 'testing',
                'active' => true,
                'created_date' => now(),
                'last_access' => now(),
            ]
        );
    }

     /**
     * Crear usuarios específicos para MAERSK
     */
    private function createMaerskUsers(Company $maersk): array
    {
        $users = [];

        // ===== 1. COMPANY ADMIN MAERSK =====
        $adminUser = User::updateOrCreate(
            ['email' => 'admin.maersk@cargas.com'],
            [
                'name' => 'Admin MAERSK',
                'password' => Hash::make('password123!'),
                'userable_type' => 'App\Models\Company',
                'userable_id' => $maersk->id,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        // Asignar rol company-admin
        if (!$adminUser->hasRole('company-admin')) {
            $adminUser->assignRole('company-admin');
        }
        
        $users['admin'] = $adminUser;

        // ===== 2. OPERADOR MAERSK - IMPORT/EXPORT =====
        $operatorCarga = Operator::updateOrCreate(
            ['document_number' => 'MAE001'],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Martínez',
                'position' => 'Operador de Cargas MAERSK',
                'phone' => '+54 11 4878-3001',
                'company_id' => $maersk->id,
                'type' => 'external',
                'can_import' => true,
                'can_export' => true,
                'can_transfer' => false,
                'active' => true,
            ]
        );

        $userCarga = User::updateOrCreate(
            ['email' => 'carlos.martinez@maersk.com.ar'],
            [
                'name' => 'Carlos Martínez',
                'password' => Hash::make('password123!'),
                'userable_type' => 'App\Models\Operator',
                'userable_id' => $operatorCarga->id,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (!$userCarga->hasRole('user')) {
            $userCarga->assignRole('user');
        }

        $users['operator1'] = $userCarga;

        // ===== 3. OPERADOR MAERSK - PERMISOS COMPLETOS =====
        $operatorSenior = Operator::updateOrCreate(
            ['document_number' => 'MAE002'],
            [
                'first_name' => 'Ana',
                'last_name' => 'González',
                'position' => 'Operador Senior MAERSK',
                'phone' => '+54 11 4878-3002',
                'company_id' => $maersk->id,
                'type' => 'external',
                'can_import' => true,
                'can_export' => true,
                'can_transfer' => true,
                'active' => true,
            ]
        );

        $userSenior = User::updateOrCreate(
            ['email' => 'ana.gonzalez@maersk.com.ar'],
            [
                'name' => 'Ana González',
                'password' => Hash::make('password123!'),
                'userable_type' => 'App\Models\Operator',
                'userable_id' => $operatorSenior->id,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (!$userSenior->hasRole('user')) {
            $userSenior->assignRole('user');
        }

        $users['operator2'] = $userSenior;

        // ===== 4. USUARIO TESTING (para seeders automáticos) =====
        $testingUser = User::updateOrCreate(
            ['email' => 'testing.maersk@cargas.com'],
            [
                'name' => 'Testing MAERSK',
                'password' => Hash::make('password123!'),
                'userable_type' => 'App\Models\Company',
                'userable_id' => $maersk->id,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (!$testingUser->hasRole('company-admin')) {
            $testingUser->assignRole('company-admin');
        }

        $users['testing'] = $testingUser;

        return $users;
    }

    /**
     * Mostrar resumen de lo creado
     */
    private function displaySummary(array $countries, array $ports, Company $maersk, array $maerskUsers): void
    {
        $this->command->line('');
        $this->command->info('📋 RESUMEN DE DEPENDENCIAS CREADAS:');
        $this->command->line('');
        
        $this->command->info('🌍 PAÍSES:');
        $this->command->line("   • Argentina (AR): {$countries['argentina']->name}");
        $this->command->line("   • Paraguay (PY): {$countries['paraguay']->name}");
        
        $this->command->info('🚢 PUERTOS:');
        $this->command->line("   • ARBUE: {$ports['arbue']->name}");
        $this->command->line("   • PYTVT: {$ports['pytvt']->name}");
        
        $this->command->info('🏢 EMPRESA:');
        $this->command->line("   • {$maersk->legal_name}");
        $this->command->line("   • CUIT: {$maersk->tax_id}");
        $this->command->line("   • Roles WS: " . implode(', ', $maersk->company_roles));
        
        $this->command->info('👤 USUARIO ADMIN:');
        $this->command->line("   • {$maerskUsers['admin']->name}");
        $this->command->line("   • Email: {$maerskUsers['admin']->email}");

        $this->command->info('👥 USUARIOS MAERSK AGREGADOS:');
        $this->command->line("   • Admin: {$maerskUsers['admin']->email} (company-admin)");
        $this->command->line("   • Operador 1: {$maerskUsers['operator1']->email} (import/export)");
        $this->command->line("   • Operador 2: {$maerskUsers['operator2']->email} (completo)");
        $this->command->line("   • Testing: {$maerskUsers['testing']->email} (seeders)");
        
        $this->command->line('');
        $this->command->info('🎯 PRÓXIMO PASO: Ejecutar seeders corregidos de viajes y webservices');
        $this->command->line('');
        $this->command->info('📋 CREDENCIALES PARA EL CLIENTE:');
        $this->command->line("   • Email: admin.maersk@cargas.com");
        $this->command->line("   • Password: Maersk2025!");
        $this->command->line("   • Rol: Administrador de empresa MAERSK");
    
        
        $this->command->line('');
        $this->command->info('🎯 PRÓXIMO PASO: Ejecutar seeders corregidos de viajes y webservices');
    }
}