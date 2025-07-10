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
        // ===== SUPER ADMIN =====
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@cargas.com',
            'userable_type' => null,
            'userable_id' => null,
        ])->assignRole('super-admin');

        // ===== EMPRESA ARGENTINA - RIO DE LA PLATA =====
        $companyAR = Company::factory()->create([
            'business_name' => 'Rio de la Plata Transport S.A.',
            'commercial_name' => 'Rio Transport',
            'tax_id' => '20123456789',
            'email' => 'admin@riotransport.com.ar',
            'country' => 'AR',
            'city' => 'Buenos Aires',
            'phone' => '+54 11 4567-8900',
            'address' => 'Av. Corrientes 1234, Buenos Aires',
            'active' => true,
            'ws_active' => true,
            'ws_environment' => 'production',
            'certificate_expires_at' => now()->addMonths(6),
            'company_roles' => ['Cargas', 'Desconsolidador'], // Roles de empresa
        ]);

        // Company Admin Argentina
        User::factory()->create([
            'name' => 'Argentina Admin',
            'email' => 'argentina@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyAR->id,
        ])->assignRole('company-admin');

        // Operador Externa AR - María (solo import)
        $operatorMaria = Operator::factory()->create([
            'first_name' => 'María',
            'last_name' => 'González',
            'document_number' => '11223344',
            'position' => 'Operador de Cargas',
            'phone' => '+54 11 9876-5432',
            'company_id' => $companyAR->id,
            'type' => 'external',
            'can_import' => true,
            'can_export' => false,
            'can_transfer' => false,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'María González',
            'email' => 'maria@riotransport.com.ar',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorMaria->id,
        ])->assignRole('user');

        // Operador Externa AR - Ana (import y export)
        $operatorAna = Operator::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Martínez',
            'document_number' => '22334455',
            'position' => 'Operador de Cargas y Exportación',
            'phone' => '+54 11 8765-4321',
            'company_id' => $companyAR->id,
            'type' => 'external',
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => false,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'Ana Martínez',
            'email' => 'ana@riotransport.com.ar',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorAna->id,
        ])->assignRole('user');

        // ===== EMPRESA PARAGUAY - NAVEGACIÓN PY =====
        $companyPY = Company::factory()->create([
            'business_name' => 'Navegación Paraguay S.A.',
            'commercial_name' => 'NavePY',
            'tax_id' => '80987654321',
            'email' => 'admin@navegacionpy.com.py',
            'country' => 'PY',
            'city' => 'Asunción',
            'phone' => '+595 21 567-890',
            'address' => 'Av. España 456, Asunción',
            'active' => true,
            'ws_active' => true,
            'ws_environment' => 'production',
            'certificate_expires_at' => now()->addMonths(12),
            'company_roles' => ['Cargas', 'Transbordos'], // Roles de empresa
        ]);

        // Company Admin Paraguay
        User::factory()->create([
            'name' => 'Paraguay Admin',
            'email' => 'paraguay@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyPY->id,
        ])->assignRole('company-admin');

        // Operador Externo PY - Carlos (export y transfer)
        $operatorCarlos = Operator::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Rodríguez',
            'document_number' => '33445566',
            'position' => 'Operador de Exportación',
            'phone' => '+595 21 654-321',
            'company_id' => $companyPY->id,
            'type' => 'external',
            'can_import' => false,
            'can_export' => true,
            'can_transfer' => true,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'Carlos Rodríguez',
            'email' => 'carlos@navegacionpy.com.py',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorCarlos->id,
        ])->assignRole('user');

        // Operador Externo PY - Roberto (todos los permisos)
        $operatorRoberto = Operator::factory()->create([
            'first_name' => 'Roberto',
            'last_name' => 'Silva',
            'document_number' => '44556677',
            'position' => 'Operador Senior',
            'phone' => '+595 21 789-012',
            'company_id' => $companyPY->id,
            'type' => 'external',
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'Roberto Silva',
            'email' => 'roberto@navegacionpy.com.py',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorRoberto->id,
        ])->assignRole('user');

        // ===== OPERADORES INTERNOS =====
        // Operador Interno - Operator (todos los permisos)
        $operatorInternal = Operator::factory()->create([
            'first_name' => 'Operador',
            'last_name' => 'Interno',
            'document_number' => '55667788',
            'position' => 'Operador Interno',
            'phone' => '+54 11 5555-5555',
            'company_id' => null, // Operador interno sin empresa específica
            'type' => 'internal',
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'Operador Interno',
            'email' => 'operator@cargas.com',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorInternal->id,
        ])->assignRole('user');

        // Operador Interno - Pedro (todos los permisos)
        $operatorPedro = Operator::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'López',
            'document_number' => '66778899',
            'position' => 'Operador Interno Senior',
            'phone' => '+54 11 6666-6666',
            'company_id' => null, // Operador interno sin empresa específica
            'type' => 'internal',
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
            'active' => true,
        ]);

        User::factory()->create([
            'name' => 'Pedro López',
            'email' => 'pedro@cargas.com',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operatorPedro->id,
        ])->assignRole('user');

        // ===== EMPRESA ESPECIALIZADA - DESCONSOLIDADOR =====
        $companyDesconsolidador = Company::factory()->create([
            'business_name' => 'Logística Integral S.A.',
            'commercial_name' => 'LogiIntegral',
            'tax_id' => '30555666777',
            'email' => 'admin@logiintegral.com',
            'country' => 'AR',
            'city' => 'Rosario',
            'phone' => '+54 341 123-4567',
            'address' => 'Av. Pellegrini 1500, Rosario',
            'active' => true,
            'ws_active' => false,
            'ws_environment' => 'test',
            'certificate_expires_at' => now()->addMonths(12),
            'company_roles' => ['Desconsolidador'], // Solo desconsolidación
        ]);

        User::factory()->create([
            'name' => 'Desconsolidador Admin',
            'email' => 'desconsolidador@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyDesconsolidador->id,
        ])->assignRole('company-admin');

        // ===== EMPRESA ESPECIALIZADA - TRANSBORDOS =====
        $companyTransbordos = Company::factory()->create([
            'business_name' => 'Transbordos del Rio S.A.',
            'commercial_name' => 'TransRio',
            'tax_id' => '30777888999',
            'email' => 'admin@transrio.com',
            'country' => 'PY',
            'city' => 'Ciudad del Este',
            'phone' => '+595 61 987-654',
            'address' => 'Av. Monseñor Rodríguez 123, Ciudad del Este',
            'active' => true,
            'ws_active' => true,
            'ws_environment' => 'test',
            'certificate_expires_at' => now()->addMonths(9),
            'company_roles' => ['Transbordos'], // Solo transbordos
        ]);

        User::factory()->create([
            'name' => 'Transbordos Admin',
            'email' => 'transbordos@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyTransbordos->id,
        ])->assignRole('company-admin');

        // ===== DATOS PARA PRUEBAS NEGATIVAS =====
        // Usuario inactivo
        User::factory()->create([
            'name' => 'Usuario Inactivo',
            'email' => 'inactive@cargas.com',
            'userable_type' => null,
            'userable_id' => null,
            'active' => false,
        ])->assignRole('user');

        // Empresa inactiva
        $inactiveCompany = Company::factory()->create([
            'business_name' => 'Empresa Inactiva S.A.',
            'commercial_name' => 'Inactiva',
            'tax_id' => '30111222333',
            'email' => 'inactive@inactive.com',
            'country' => 'AR',
            'city' => 'Córdoba',
            'phone' => '+54 351 111-2222',
            'address' => 'Av. Vélez Sarsfield 1000, Córdoba',
            'active' => false,
            'ws_active' => false,
            'ws_environment' => 'test',
            'certificate_expires_at' => now()->subDays(30), // Certificado expirado
            'company_roles' => ['Cargas'],
        ]);

        User::factory()->create([
            'name' => 'Admin Inactivo',
            'email' => 'admin.inactive@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $inactiveCompany->id,
            'active' => false,
        ])->assignRole('company-admin');

        // ===== MENSAJES INFORMATIVOS =====
        $this->command->info('✅ Test users created successfully with 3 simplified roles');
        $this->command->info('');
        $this->command->info('=== 🔐 LOGIN CREDENTIALS ===');
        $this->command->info('All passwords: password');
        $this->command->info('');
        $this->command->info('SUPER ADMIN:');
        $this->command->info('  admin@cargas.com');
        $this->command->info('');
        $this->command->info('COMPANY ADMINS:');
        $this->command->info('  argentina@cargas.com (Rio de la Plata - Cargas, Desconsolidador)');
        $this->command->info('  paraguay@cargas.com (Navegación PY - Cargas, Transbordos)');
        $this->command->info('  desconsolidador@cargas.com (Logística Integral - Desconsolidador)');
        $this->command->info('  transbordos@cargas.com (Transbordos del Rio - Transbordos)');
        $this->command->info('');
        $this->command->info('USERS (operators):');
        $this->command->info('  maria@riotransport.com.ar (External - Import: Yes, Export: No, Transfer: No)');
        $this->command->info('  carlos@navegacionpy.com.py (External - Import: No, Export: Yes, Transfer: Yes)');
        $this->command->info('  ana@riotransport.com.ar (External - Import: Yes, Export: Yes, Transfer: No)');
        $this->command->info('  roberto@navegacionpy.com.py (External - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->command->info('  operator@cargas.com (Internal - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->command->info('  pedro@cargas.com (Internal - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->command->info('');
        $this->command->info('INACTIVE USERS:');
        $this->command->info('  inactive@cargas.com (Inactive user)');
        $this->command->info('  admin.inactive@cargas.com (Inactive company admin)');
        $this->command->info('');
        $this->command->info('=== 🏢 COMPANIES CREATED ===');
        $this->command->info('Active:');
        $this->command->info('  Rio de la Plata Transport S.A. (AR) - Roles: Cargas, Desconsolidador');
        $this->command->info('  Navegación Paraguay S.A. (PY) - Roles: Cargas, Transbordos');
        $this->command->info('  Logística Integral S.A. (AR) - Roles: Desconsolidador');
        $this->command->info('  Transbordos del Rio S.A. (PY) - Roles: Transbordos');
        $this->command->info('Inactive:');
        $this->command->info('  Empresa Inactiva S.A. (AR) - Roles: Cargas');
        $this->command->info('');
        $this->command->info('=== 📋 ROLES STRUCTURE ===');
        $this->command->info('1. super-admin: Full system access');
        $this->command->info('2. company-admin: Full company access');
        $this->command->info('3. user: Limited access based on:');
        $this->command->info('   - Company business roles (Cargas, Desconsolidador, Transbordos)');
        $this->command->info('   - Operator permissions (can_import, can_export, can_transfer)');
    }
}
