<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class VerifyUsersCommand extends Command
{
    protected $signature = 'users:verify';
    protected $description = 'Verify the installation of the users and permissions module (3 simplified roles)';

    public function handle()
    {
        $this->info('=== USERS AND PERMISSIONS MODULE VERIFICATION ===');
        $this->info('✨ Verification for 3 simplified roles system');
        $this->newLine();

        // 1. Verify tables
        $this->verifyTables();

        // 2. Verify roles (3 simplified)
        $this->verifyRoles();

        // 3. Verify permissions
        $this->verifyPermissions();

        // 4. Verify users
        $this->verifyUsers();

        // 5. Verify companies
        $this->verifyCompanies();

        // 6. Verify operators
        $this->verifyOperators();

        // 7. Verify polymorphic relationships
        $this->verifyPolymorphism();

        // 8. Verify permissions assignment
        $this->verifyRolePermissions();

        // 9. Verify company roles functionality
        $this->verifyCompanyRoles();

        // 10. Verify operator permissions
        $this->verifyOperatorPermissions();

        $this->newLine();
        $this->info('✅ Verification completed successfully');

        // Summary
        $this->displaySummary();
    }

    private function verifyTables()
    {
        $this->info('1. Verifying tables...');

        $tables = [
            'users' => 'Users',
            'companies' => 'Companies',
            'operators' => 'Operators',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'model_has_permissions' => 'Model permissions',
            'model_has_roles' => 'Model roles',
            'role_has_permissions' => 'Role permissions',
        ];

        foreach ($tables as $table => $name) {
            try {
                $count = \DB::table($table)->count();
                $this->info("  ✅ {$name}: {$count} records");
            } catch (\Exception $e) {
                $this->error("  ❌ {$name}: Table does not exist");
            }
        }
    }

    private function verifyRoles()
    {
        $this->info('2. Verifying roles (3 simplified)...');

        $expectedRoles = [
            'super-admin' => 'Super Administrator',
            'company-admin' => 'Company Administrator',
            'user' => 'User (includes all operators)',
        ];

        foreach ($expectedRoles as $role => $name) {
            $roleObj = Role::where('name', $role)->first();
            if ($roleObj) {
                $users = User::role($role)->count();
                $permissions = $roleObj->permissions()->count();
                $this->info("  ✅ {$name}: {$users} users, {$permissions} permissions");
            } else {
                $this->error("  ❌ {$name}: Does not exist");
            }
        }

        // Check for old roles that should not exist
        $oldRoles = ['internal-operator', 'external-operator'];
        foreach ($oldRoles as $oldRole) {
            if (Role::where('name', $oldRole)->exists()) {
                $this->warn("  ⚠️ Old role '{$oldRole}' still exists - should be removed");
            }
        }
    }

    private function verifyPermissions()
    {
        $this->info('3. Verifying permissions...');

        $permissionCategories = [
            'users' => 'User management',
            'companies' => 'Company management',
            'trips' => 'Trip management',
            'shipments' => 'Shipment management',
            'containers' => 'Container management',
            'transshipments' => 'Transshipment management',
            'deconsolidations' => 'Deconsolidation management',
            'attachments' => 'Attachment management',
            'import' => 'Import functions',
            'export' => 'Export functions',
            'webservices' => 'Webservice integration',
            'reports' => 'Report generation',
            'admin' => 'System administration',
        ];

        foreach ($permissionCategories as $category => $name) {
            $count = Permission::where('name', 'like', "{$category}.%")->count();
            if ($count > 0) {
                $this->info("  ✅ {$name}: {$count} permissions");
            } else {
                $this->warn("  ⚠️ {$name}: No permissions found");
            }
        }
    }

    private function verifyUsers()
    {
        $this->info('4. Verifying users...');

        $total = User::count();
        $active = User::where('active', true)->count();
        $withRoles = User::has('roles')->count();
        $withEntities = User::whereNotNull('userable_type')->count();

        $this->info("  ✅ Total users: {$total}");
        $this->info("  ✅ Active users: {$active}");
        $this->info("  ✅ Users with roles: {$withRoles}");
        $this->info("  ✅ Users with entities: {$withEntities}");

        // Verify users by role
        $superAdmins = User::role('super-admin')->count();
        $companyAdmins = User::role('company-admin')->count();
        $users = User::role('user')->count();

        $this->info("  ✅ Super Admins: {$superAdmins}");
        $this->info("  ✅ Company Admins: {$companyAdmins}");
        $this->info("  ✅ Users: {$users}");

        // Verify specific test users
        $testUsers = [
            'admin@cargas.com' => 'Super Admin',
            'argentina@cargas.com' => 'Argentina Company Admin',
            'paraguay@cargas.com' => 'Paraguay Company Admin',
            'desconsolidador@cargas.com' => 'Desconsolidador Company Admin',
            'transbordos@cargas.com' => 'Transbordos Company Admin',
            'operator@cargas.com' => 'Internal Operator User',
            'pedro@cargas.com' => 'Internal Operator User',
            'maria@riotransport.com.ar' => 'Argentina External User',
            'carlos@navegacionpy.com.py' => 'Paraguay External User',
            'ana@riotransport.com.ar' => 'Argentina External User',
            'roberto@navegacionpy.com.py' => 'Paraguay External User',
        ];

        foreach ($testUsers as $email => $name) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $roles = $user->roles->pluck('name')->implode(', ');
                $status = $user->active ? 'Active' : 'Inactive';
                $this->info("  ✅ {$name}: {$roles} ({$status})");
            } else {
                $this->warn("  ⚠️ {$name}: Not found");
            }
        }
    }

    private function verifyCompanies()
    {
        $this->info('5. Verifying companies...');

        $total = Company::count();
        $active = Company::where('active', true)->count();
        $argentina = Company::where('country', 'AR')->count();
        $paraguay = Company::where('country', 'PY')->count();
        $withCertificates = Company::whereNotNull('certificate_expires_at')->count();
        $withUsers = Company::whereHas('users')->count();

        $this->info("  ✅ Total companies: {$total}");
        $this->info("  ✅ Active companies: {$active}");
        $this->info("  ✅ Argentina companies: {$argentina}");
        $this->info("  ✅ Paraguay companies: {$paraguay}");
        $this->info("  ✅ With certificates: {$withCertificates}");
        $this->info("  ✅ With users: {$withUsers}");

        // Verify specific test companies
        $testCompanies = [
            'Rio de la Plata Transport S.A.' => '20123456789',
            'Navegación Paraguay S.A.' => '80987654321',
            'Logística Integral S.A.' => '30555666777',
            'Transbordos del Rio S.A.' => '30777888999',
        ];

        foreach ($testCompanies as $name => $taxId) {
            $company = Company::where('tax_id', $taxId)->first();
            if ($company) {
                $status = $company->active ? 'Active' : 'Inactive';
                $companyRoles = is_array($company->company_roles) ? implode(', ', $company->company_roles) : $company->company_roles;
                $this->info("  ✅ {$name}: {$status} - Roles: {$companyRoles}");
            } else {
                $this->warn("  ⚠️ {$name}: Not found");
            }
        }
    }

    private function verifyOperators()
    {
        $this->info('6. Verifying operators...');

        $total = Operator::count();
        $active = Operator::where('active', true)->count();
        $internal = Operator::where('type', 'internal')->count();
        $external = Operator::where('type', 'external')->count();
        $withUsers = Operator::whereHas('user')->count();
        $canImport = Operator::where('can_import', true)->count();
        $canExport = Operator::where('can_export', true)->count();
        $canTransfer = Operator::where('can_transfer', true)->count();

        $this->info("  ✅ Total operators: {$total}");
        $this->info("  ✅ Active operators: {$active}");
        $this->info("  ✅ Internal operators: {$internal}");
        $this->info("  ✅ External operators: {$external}");
        $this->info("  ✅ With users: {$withUsers}");
        $this->info("  ✅ Can import: {$canImport}");
        $this->info("  ✅ Can export: {$canExport}");
        $this->info("  ✅ Can transfer: {$canTransfer}");
    }

    private function verifyPolymorphism()
    {
        $this->info('7. Verifying polymorphic relationships...');

        $usersCompany = User::where('userable_type', 'App\Models\Company')->count();
        $usersOperator = User::where('userable_type', 'App\Models\Operator')->count();
        $usersWithoutEntity = User::whereNull('userable_type')->count();

        $this->info("  ✅ Users → Company: {$usersCompany}");
        $this->info("  ✅ Users → Operator: {$usersOperator}");
        $this->info("  ✅ Users without entity: {$usersWithoutEntity}");

        // Verify integrity of relationships
        $brokenRelations = User::whereNotNull('userable_type')
            ->whereNotNull('userable_id')
            ->whereDoesntHave('userable')
            ->count();

        if ($brokenRelations > 0) {
            $this->error("  ❌ Broken relationships: {$brokenRelations}");
        } else {
            $this->info("  ✅ All relationships are valid");
        }
    }

    private function verifyRolePermissions()
    {
        $this->info('8. Verifying role permissions...');

        $roles = Role::with('permissions')->get();

        foreach ($roles as $role) {
            $permissionCount = $role->permissions->count();
            $this->info("  ✅ {$role->name}: {$permissionCount} permissions");
        }
    }

    private function verifyCompanyRoles()
    {
        $this->info('9. Verifying company roles functionality...');

        $companiesWithRoles = Company::whereNotNull('company_roles')->count();
        $this->info("  ✅ Companies with roles: {$companiesWithRoles}");

        // Verify specific company roles
        $roleTypes = ['Cargas', 'Desconsolidador', 'Transbordos'];
        foreach ($roleTypes as $roleType) {
            $count = Company::whereJsonContains('company_roles', $roleType)->count();
            $this->info("  ✅ Companies with '{$roleType}' role: {$count}");
        }

        // Verify companies with multiple roles
        $multipleRoles = Company::whereRaw('JSON_LENGTH(company_roles) > 1')->count();
        $this->info("  ✅ Companies with multiple roles: {$multipleRoles}");
    }

    private function verifyOperatorPermissions()
    {
        $this->info('10. Verifying operator permissions...');

        // Verify permission combinations
        $importOnly = Operator::where('can_import', true)
            ->where('can_export', false)
            ->where('can_transfer', false)
            ->count();

        $exportOnly = Operator::where('can_import', false)
            ->where('can_export', true)
            ->where('can_transfer', false)
            ->count();

        $transferOnly = Operator::where('can_import', false)
            ->where('can_export', false)
            ->where('can_transfer', true)
            ->count();

        $allPermissions = Operator::where('can_import', true)
            ->where('can_export', true)
            ->where('can_transfer', true)
            ->count();

        $this->info("  ✅ Import only: {$importOnly}");
        $this->info("  ✅ Export only: {$exportOnly}");
        $this->info("  ✅ Transfer only: {$transferOnly}");
        $this->info("  ✅ All permissions: {$allPermissions}");
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('=== 📊 SYSTEM SUMMARY ===');

        $summary = [
            'Total Users' => User::count(),
            'Total Companies' => Company::count(),
            'Total Operators' => Operator::count(),
            'Total Roles' => Role::count(),
            'Total Permissions' => Permission::count(),
            'Active Users' => User::where('active', true)->count(),
            'Active Companies' => Company::where('active', true)->count(),
            'Companies with Certificates' => Company::whereNotNull('certificate_expires_at')->count(),
            'Internal Operators' => Operator::where('type', 'internal')->count(),
            'External Operators' => Operator::where('type', 'external')->count(),
        ];

        foreach ($summary as $label => $value) {
            $this->info("  {$label}: {$value}");
        }

        $this->newLine();
        $this->info('=== 🔐 TEST CREDENTIALS ===');
        $this->info('All passwords are: password');
        $this->newLine();
        $this->info('SUPER ADMIN:');
        $this->info('  admin@cargas.com');
        $this->newLine();
        $this->info('COMPANY ADMINS:');
        $this->info('  argentina@cargas.com (Rio de la Plata - Cargas, Desconsolidador)');
        $this->info('  paraguay@cargas.com (Navegación PY - Cargas, Transbordos)');
        $this->info('  desconsolidador@cargas.com (Logística Integral - Desconsolidador)');
        $this->info('  transbordos@cargas.com (Transbordos del Rio - Transbordos)');
        $this->newLine();
        $this->info('USERS (operators):');
        $this->info('  maria@riotransport.com.ar (External - Import: Yes, Export: No, Transfer: No)');
        $this->info('  carlos@navegacionpy.com.py (External - Import: No, Export: Yes, Transfer: Yes)');
        $this->info('  ana@riotransport.com.ar (External - Import: Yes, Export: Yes, Transfer: No)');
        $this->info('  roberto@navegacionpy.com.py (External - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->info('  operator@cargas.com (Internal - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->info('  pedro@cargas.com (Internal - Import: Yes, Export: Yes, Transfer: Yes)');
        $this->newLine();
        $this->info('=== 🏢 COMPANY ROLES DISTRIBUTION ===');
        $this->info('  Cargas: ' . Company::whereJsonContains('company_roles', 'Cargas')->count() . ' companies');
        $this->info('  Desconsolidador: ' . Company::whereJsonContains('company_roles', 'Desconsolidador')->count() . ' companies');
        $this->info('  Transbordos: ' . Company::whereJsonContains('company_roles', 'Transbordos')->count() . ' companies');
        $this->newLine();
        $this->info('=== 📋 ROLE STRUCTURE (Roberto\'s 3 simplified roles) ===');
        $this->info('1. super-admin: Creates companies & assigns company roles');
        $this->info('2. company-admin: Manages users within their company');
        $this->info('3. user: Can do EVERYTHING their company roles allow');
        $this->info('   - Filtered by company business roles (Cargas, Desconsolidador, Transbordos)');
        $this->info('   - Filtered by operator permissions (can_import, can_export, can_transfer)');
    }
}
