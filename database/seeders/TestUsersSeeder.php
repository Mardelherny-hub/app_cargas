<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin (el único que puede crear empresas)
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@cargas.com',
            'userable_type' => null, // Super admin no tiene empresa asociada
            'userable_id' => null,
        ])->assignRole('super-admin');

        // 2. Empresa Argentina - CARGAS + TRANSBORDOS
        $companyAR = Company::factory()->create([
            'business_name' => 'Rio de la Plata Transport S.A.',
            'commercial_name' => 'Rio Transport',
            'tax_id' => '20123456789',
            'country' => 'AR',
            'email' => 'admin@riotransport.com.ar',
            'city' => 'Buenos Aires',
            'phone' => '+54 11 4567-8900',
            'address' => 'Av. Corrientes 1234, Buenos Aires',
            // NUEVO: Roles de empresa (términos de Roberto) - SIMPLIFICADO
            'company_roles' => ['Cargas', 'Transbordos'],
            'roles_config' => [
                'webservices' => ['anticipada', 'micdta', 'transbordos'],
                'features' => ['contenedores', 'barcazas']
            ],
            'certificate_path' => storage_path('certificates/riotransport.p12'),
            'certificate_password' => 'cert123', // Sin encrypt() - el mutator lo hace automáticamente
            'certificate_expires_at' => now()->addYear(),
            'ws_active' => true,
            'ws_environment' => 'testing',
            'active' => true,
        ]);

        // Administrador de la empresa Argentina
        User::factory()->create([
            'name' => 'Carlos Rodriguez (Admin AR)',
            'email' => 'argentina@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyAR->id,
        ])->assignRole('company-admin');

        // Usuario común de la empresa Argentina
        User::factory()->create([
            'name' => 'Maria Gonzalez',
            'email' => 'maria@riotransport.com.ar',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyAR->id,
        ])->assignRole('user');

        // 3. Empresa Paraguay - SOLO CARGAS
        $companyPY = Company::factory()->create([
            'business_name' => 'Navegación Paraguay S.A.',
            'commercial_name' => 'NavePY',
            'tax_id' => '80987654321',
            'country' => 'PY',
            'email' => 'admin@navegacionpy.com.py',
            'city' => 'Asunción',
            'phone' => '+595 21 567-890',
            'address' => 'Av. España 567, Asunción',
            // NUEVO: Solo rol Cargas (término de Roberto) - SIMPLIFICADO
            'company_roles' => ['Cargas'],
            'roles_config' => [
                'webservices' => ['anticipada', 'micdta'],
                'features' => ['contenedores']
            ],
            'certificate_path' => storage_path('certificates/navegacionpy.p12'),
            'certificate_password' => 'cert456', // Sin encrypt() - el mutator lo hace automáticamente
            'certificate_expires_at' => now()->addMonths(6),
            'ws_active' => true,
            'ws_environment' => 'testing',
            'active' => true,
        ]);

        // Administrador de la empresa Paraguay
        User::factory()->create([
            'name' => 'Roberto Silva (Admin PY)',
            'email' => 'paraguay@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyPY->id,
        ])->assignRole('company-admin');

        // Usuario común de la empresa Paraguay
        User::factory()->create([
            'name' => 'Carlos Mendez',
            'email' => 'carlos@navegacionpy.com.py',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyPY->id,
        ])->assignRole('user');

        // 4. Empresa SOLO DESCONSOLIDADOR
        $companyDescon = Company::factory()->create([
            'business_name' => 'Consolidados del Sur S.A.',
            'commercial_name' => 'ConsolSur',
            'tax_id' => '30555666777',
            'country' => 'AR',
            'email' => 'admin@consolsur.com.ar',
            'city' => 'Rosario',
            'phone' => '+54 341 123-4567',
            'address' => 'Av. Belgrano 890, Rosario',
            // NUEVO: Solo Desconsolidador (término de Roberto) - SIMPLIFICADO
            'company_roles' => ['Desconsolidador'],
            'roles_config' => [
                'webservices' => ['desconsolidados'],
                'features' => ['titulos_madre', 'titulos_hijos']
            ],
            'certificate_path' => storage_path('certificates/consolsur.p12'),
            'certificate_password' => 'cert789', // Sin encrypt() - el mutator lo hace automáticamente
            'certificate_expires_at' => now()->addMonths(8),
            'ws_active' => true,
            'ws_environment' => 'production',
            'active' => true,
        ]);

        // Administrador desconsolidador
        User::factory()->create([
            'name' => 'Ana Martinez (Admin Descon)',
            'email' => 'desconsolidador@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyDescon->id,
        ])->assignRole('company-admin');

        // 5. Empresa SOLO TRANSBORDOS
        $companyTransb = Company::factory()->create([
            'business_name' => 'Transbordos Río S.A.',
            'commercial_name' => 'TransRío',
            'tax_id' => '30777888999',
            'country' => 'AR',
            'email' => 'admin@transrio.com.ar',
            'city' => 'Zárate',
            'phone' => '+54 3487 123-456',
            'address' => 'Puerto de Zárate, Zárate',
            // NUEVO: Solo Transbordos (término de Roberto) - SIMPLIFICADO
            'company_roles' => ['Transbordos'],
            'roles_config' => [
                'webservices' => ['transbordos'],
                'features' => ['division_barcazas', 'tracking_posicion']
            ],
            'certificate_path' => storage_path('certificates/transrio.p12'),
            'certificate_password' => 'cert999', // Sin encrypt() - el mutator lo hace automáticamente
            'certificate_expires_at' => now()->addMonths(10),
            'ws_active' => true,
            'ws_environment' => 'testing',
            'active' => true,
        ]);

        // Administrador transbordos
        User::factory()->create([
            'name' => 'Pedro Ramirez (Admin Trans)',
            'email' => 'transbordos@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyTransb->id,
        ])->assignRole('company-admin');

        // Usuario común transbordos
        User::factory()->create([
            'name' => 'Luis Gomez',
            'email' => 'luis@transrio.com.ar',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyTransb->id,
        ])->assignRole('user');

        // 6. Empresa inactiva para testing
        $inactiveCompany = Company::factory()->create([
            'business_name' => 'Empresa Inactiva S.A.',
            'commercial_name' => 'Inactiva',
            'tax_id' => '20999888777',
            'country' => 'AR',
            'company_roles' => ['Cargas'],
            'active' => false, // Inactiva
        ]);

        $this->command->info('✅ Test users and companies created successfully');
        $this->command->info('');
        $this->command->info('=== 🔑 LOGIN CREDENTIALS ===');
        $this->command->info('Super Admin: admin@cargas.com / password');
        $this->command->info('Argentina Admin: argentina@cargas.com / password');
        $this->command->info('Paraguay Admin: paraguay@cargas.com / password');
        $this->command->info('Desconsolidador Admin: desconsolidador@cargas.com / password');
        $this->command->info('Transbordos Admin: transbordos@cargas.com / password');
        $this->command->info('');
        $this->command->info('=== 🏢 COMPANIES CREATED ===');
        $this->command->info('🇦🇷 Rio de la Plata Transport (20123456789) - CARGAS + TRANSBORDOS');
        $this->command->info('🇵🇾 Navegación Paraguay (80987654321) - SOLO CARGAS');
        $this->command->info('🇦🇷 Consolidados del Sur (30555666777) - SOLO DESCONSOLIDADOR');
        $this->command->info('🇦🇷 Transbordos Río (30777888999) - SOLO TRANSBORDOS');
        $this->command->info('🇦🇷 Empresa Inactiva (20999888777) - INACTIVA');
        $this->command->info('');
        $this->command->info('=== 👥 USER TYPES ===');
        $this->command->info('• Super Admin: 1 user (creates companies)');
        $this->command->info('• Company Admins: 4 users (manage company users)');
        $this->command->info('• Regular Users: 3 users (use company features)');
        $this->command->info('');
        $this->command->info('=== 🎯 COMPANY ROLES ===');
        $this->command->info('• Cargas: anticipada + micdta webservices');
        $this->command->info('• Desconsolidador: desconsolidados webservice');
        $this->command->info('• Transbordos: transbordos webservice');
        $this->command->info('• Companies can have multiple roles');
    }
}
