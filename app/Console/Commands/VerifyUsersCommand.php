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
    protected $description = 'Verify the installation of the users and permissions module';

    public function handle()
    {
        $this->info('=== USERS AND PERMISSIONS MODULE VERIFICATION ===');
        $this->newLine();

        // 1. Verify tables
        $this->verifyTables();

        // 2. Verify roles
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
        $this->info('2. Verifying roles...');

        $expectedRoles = [
            'super-admin' => 'Super Administrator',
            'company-admin' => 'Company Administrator',
            'internal-operator' => 'Internal Operator',
            'external-operator' => 'External Operator',
        ];

        foreach ($expectedRoles as $role => $name) {
            $exists = Role::where('name', $role)->exists();
            if ($exists) {
                $users = User::role($role)->count();
                $this->info("  ✅ {$name}: {$users} users");
            } else {
                $this->error("  ❌ {$name}: Does not exist");
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

        // Verify specific test users
        $testUsers = [
            'admin@cargas.com' => 'Super Admin',
            'argentina@cargas.com' => 'Argentina Admin',
            'paraguay@cargas.com' => 'Paraguay Admin',
            'operator@cargas.com' => 'Internal Operator',
            'maria@riotransport.com.ar' => 'Argentina External Operator',
            'carlos@navegacionpy.com.py' => 'Paraguay External Operator',
        ];

        foreach ($testUsers as $email => $name) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $roles = $user->roles->pluck('name')->implode(', ');
                $this->info("  ✅ {$name}: {$roles}");
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
        $withUsers = Company::whereHas('user')->count();

        $this->info("  ✅ Total companies: {$total}");
        $this->info("  ✅ Active companies: {$active}");
        $this->info("  ✅ Argentina companies: {$argentina}");
        $this->info("  ✅ Paraguay companies: {$paraguay}");
        $this->info("  ✅ With certificates: {$withCertificates}");
        $this->info("  ✅ With users: {$withUsers}");

        // Verify specific test companies
        $testCompanies = [
            '20123456789' => 'Rio de la Plata Transport S.A.',
            '80987654321' => 'Navegación Paraguay S.A.',
        ];

        foreach ($testCompanies as $taxId => $name) {
            $company = Company::where('tax_id', $taxId)->first();
            if ($company) {
                $status = $company->active ? 'Active' : 'Inactive';
                $this->info("  ✅ {$name}: {$status}");
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

    private function displaySummary()
    {
        $this->newLine();
        $this->info('=== SUMMARY ===');

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
        $this->info('=== TEST CREDENTIALS ===');
        $this->info('All passwords are: password');
        $this->info('Super Admin: admin@cargas.com');
        $this->info('Argentina Admin: argentina@cargas.com');
        $this->info('Paraguay Admin: paraguay@cargas.com');
        $this->info('Internal Operator: operator@cargas.com');
        $this->info('External Operators: maria@riotransport.com.ar, carlos@navegacionpy.com.py');
    }
}
