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
        // 1. Super Admin
        User::factory()->superAdmin()->create();

        // 2. Argentina Company with admin and operators
        $companyAR = Company::factory()->argentina()->withValidCertificate()->create([
            'business_name' => 'Rio de la Plata Transport S.A.',
            'commercial_name' => 'Rio Transport',
            'tax_id' => '20123456789',
            'email' => 'admin@riotransport.com.ar',
            'city' => 'Buenos Aires',
            'phone' => '+54 11 4567-8900',
            'address' => 'Av. Corrientes 1234, Buenos Aires',
        ]);

        User::factory()->create([
            'name' => 'Argentina Admin',
            'email' => 'argentina@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyAR->id,
        ])->assignRole('company-admin');

        // 3. Paraguay Company with admin and operators
        $companyPY = Company::factory()->paraguay()->withValidCertificate()->create([
            'business_name' => 'Navegación Paraguay S.A.',
            'commercial_name' => 'NavePY',
            'tax_id' => '80987654321',
            'email' => 'admin@navegacionpy.com.py',
            'city' => 'Asunción',
            'phone' => '+595 21 567-890',
            'address' => 'Av. España 567, Asunción',
        ]);

        User::factory()->create([
            'name' => 'Paraguay Admin',
            'email' => 'paraguay@cargas.com',
            'userable_type' => 'App\Models\Company',
            'userable_id' => $companyPY->id,
        ])->assignRole('company-admin');

        // 4. Internal Operator
        $internalOperator = Operator::factory()->internal()->create([
            'first_name' => 'Juan Carlos',
            'last_name' => 'Rodriguez',
            'document_number' => '12345678',
            'position' => 'System Operator',
            'phone' => '+54 11 9876-5432',
        ]);

        User::factory()->create([
            'name' => 'Juan Carlos Rodriguez',
            'email' => 'operator@cargas.com',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $internalOperator->id,
        ])->assignRole('internal-operator');

        // 5. External Operator for Argentina Company
        $externalOperatorAR = Operator::factory()->external()->create([
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'document_number' => '23456789',
            'position' => 'Cargo Operator',
            'phone' => '+54 11 1234-5678',
            'company_id' => $companyAR->id,
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => false,
        ]);

        User::factory()->create([
            'name' => 'Maria Garcia',
            'email' => 'maria@riotransport.com.ar',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $externalOperatorAR->id,
        ])->assignRole('external-operator');

        // 6. External Operator for Paraguay Company
        $externalOperatorPY = Operator::factory()->external()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
            'document_number' => '34567890',
            'position' => 'Cargo Operator',
            'phone' => '+595 21 123-456',
            'company_id' => $companyPY->id,
            'can_import' => true,
            'can_export' => false,
            'can_transfer' => true,
        ]);

        User::factory()->create([
            'name' => 'Carlos Mendoza',
            'email' => 'carlos@navegacionpy.com.py',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $externalOperatorPY->id,
        ])->assignRole('external-operator');

        // 7. Additional Argentina Company Operator
        $additionalOperatorAR = Operator::factory()->forCompany($companyAR)->create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'document_number' => '45678901',
            'position' => 'Data Entry Operator',
            'can_import' => true,
            'can_export' => false,
            'can_transfer' => false,
        ]);

        User::factory()->create([
            'name' => 'Ana Lopez',
            'email' => 'ana@riotransport.com.ar',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $additionalOperatorAR->id,
        ])->assignRole('external-operator');

        // 8. Additional Paraguay Company Operator
        $additionalOperatorPY = Operator::factory()->forCompany($companyPY)->create([
            'first_name' => 'Roberto',
            'last_name' => 'Silva',
            'document_number' => '56789012',
            'position' => 'Senior Operator',
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
            'special_permissions' => ['advanced_reports'],
        ]);

        User::factory()->create([
            'name' => 'Roberto Silva',
            'email' => 'roberto@navegacionpy.com.py',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $additionalOperatorPY->id,
        ])->assignRole('external-operator');

        // 9. Additional Internal Operator
        $additionalInternalOperator = Operator::factory()->internal()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Martinez',
            'document_number' => '67890123',
            'position' => 'Senior System Operator',
            'phone' => '+54 11 8765-4321',
        ]);

        User::factory()->create([
            'name' => 'Pedro Martinez',
            'email' => 'pedro@cargas.com',
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $additionalInternalOperator->id,
        ])->assignRole('internal-operator');

        // 10. Additional test companies and users
        User::factory()->count(3)->companyAdmin()->create();
        User::factory()->count(2)->internalOperator()->create();
        User::factory()->count(5)->externalOperator()->create();

        // 11. Inactive users for testing
        User::factory()->inactive()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@cargas.com',
        ]);

        $this->command->info('Test users created successfully');
        $this->command->info('');
        $this->command->info('=== LOGIN CREDENTIALS ===');
        $this->command->info('Super Admin: admin@cargas.com / password');
        $this->command->info('Argentina Admin: argentina@cargas.com / password');
        $this->command->info('Paraguay Admin: paraguay@cargas.com / password');
        $this->command->info('Internal Operator: operator@cargas.com / password');
        $this->command->info('Argentina External Operator: maria@riotransport.com.ar / password');
        $this->command->info('Paraguay External Operator: carlos@navegacionpy.com.py / password');
        $this->command->info('Additional Argentina Operator: ana@riotransport.com.ar / password');
        $this->command->info('Additional Paraguay Operator: roberto@navegacionpy.com.py / password');
        $this->command->info('Additional Internal Operator: pedro@cargas.com / password');
        $this->command->info('');
        $this->command->info('=== COMPANIES CREATED ===');
        $this->command->info('Argentina: Rio de la Plata Transport S.A. (20123456789)');
        $this->command->info('Paraguay: Navegación Paraguay S.A. (80987654321)');
        $this->command->info('+ 3 additional random companies');
        $this->command->info('');
        $this->command->info('=== OPERATORS CREATED ===');
        $this->command->info('Internal: 3 operators (Juan Carlos, Pedro, + 2 random)');
        $this->command->info('External: 8 operators (2 per main company + 5 random)');
        $this->command->info('');
        $this->command->info('All users have password: password');
    }
}
