<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class TestRelationshipsCommand extends Command
{
    protected $signature = 'test:relationships';
    protected $description = 'Probar las relaciones polimórficas y consultas';

    public function handle()
    {
        $this->info('🧪 Probando relaciones y consultas...');
        $this->line('');

        // Test 1: Usuarios con relaciones polimórficas
        $this->info('👥 Test 1: Usuarios con relaciones polimórficas');
        try {
            $users = User::with(['userable', 'roles'])->get();
            $this->info("  ✅ Usuarios encontrados: {$users->count()}");

            foreach ($users as $user) {
                $userableType = $user->userable_type ? class_basename($user->userable_type) : 'None';
                $roles = $user->roles->pluck('name')->implode(', ') ?: 'Sin roles';
                $this->info("    - {$user->name} ({$user->email}): {$userableType} | Roles: {$roles}");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 2: Empresas con usuarios
        $this->info('🏢 Test 2: Empresas con usuarios');
        try {
            $companies = Company::with('user')->get();
            $this->info("  ✅ Empresas encontradas: {$companies->count()}");

            foreach ($companies as $company) {
                $hasUser = $company->user ? 'SÍ' : 'NO';
                $userName = $company->user ? $company->user->name : 'N/A';
                $this->info("    - {$company->business_name}: Usuario: {$hasUser} ({$userName})");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 3: Operadores con usuarios y empresas
        $this->info('👷 Test 3: Operadores con usuarios y empresas');
        try {
            $operators = Operator::with(['user', 'company'])->get();
            $this->info("  ✅ Operadores encontrados: {$operators->count()}");

            foreach ($operators as $operator) {
                $hasUser = $operator->user ? 'SÍ' : 'NO';
                $userName = $operator->user ? $operator->user->name : 'N/A';
                $companyName = $operator->company ? $operator->company->business_name : 'Sin empresa';
                $this->info("    - {$operator->full_name} ({$operator->type_name}): Usuario: {$hasUser} ({$userName}) | Empresa: {$companyName}");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 4: Consulta específica que estaba fallando
        $this->info('🔍 Test 4: Consulta específica (usuarios inactivos con empresas activas)');
        try {
            $inactiveUsersWithActiveCompanies = User::where('active', false)
            ->where('userable_type', 'App\\Models\\Company')
            ->whereHas('userable', function ($query) {
                $query->where('active', true);
            })
            ->with(['userable', 'roles'])
            ->get();

            $this->info("  ✅ Usuarios inactivos con empresas activas: {$inactiveUsersWithActiveCompanies->count()}");

            foreach ($inactiveUsersWithActiveCompanies as $user) {
                $companyName = $user->userable ? $user->userable->business_name : 'N/A';
                $this->info("    - {$user->name}: {$companyName}");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 5: Certificados vencidos
        $this->info('📜 Test 5: Certificados vencidos');
        try {
            $expiredCertificates = Company::whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '<', now())
            ->where('active', true)
            ->get();

            $this->info("  ✅ Empresas con certificados vencidos: {$expiredCertificates->count()}");

            foreach ($expiredCertificates as $company) {
                $daysExpired = now()->diffInDays($company->certificate_expires_at);
                $this->info("    - {$company->business_name}: Vencido hace {$daysExpired} días");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 6: Usuarios por rol
        $this->info('🎭 Test 6: Usuarios por rol');
        try {
            $usersByRole = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name as role', \DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get();

            $this->info("  ✅ Distribución de usuarios por rol:");
            foreach ($usersByRole as $role) {
                $this->info("    - {$role->role}: {$role->count} usuarios");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        // Test 7: Verificar traits y métodos
        $this->info('🔧 Test 7: Verificar traits y métodos');
        try {
            $testUser = User::first();
            if ($testUser) {
                $this->info("  ✅ Usuario de prueba: {$testUser->name}");

                // Verificar trait HasRoles
                $hasRolesMethod = method_exists($testUser, 'hasRole');
                $this->info("    - Método hasRole(): " . ($hasRolesMethod ? 'SÍ' : 'NO'));

                // Verificar relación userable
                $hasUserableMethod = method_exists($testUser, 'userable');
                $this->info("    - Método userable(): " . ($hasUserableMethod ? 'SÍ' : 'NO'));

                // Verificar si tiene roles
                $rolesCount = $testUser->roles->count();
                $this->info("    - Roles asignados: {$rolesCount}");

                if ($rolesCount > 0) {
                    $firstRole = $testUser->roles->first()->name;
                    $hasFirstRole = $testUser->hasRole($firstRole);
                    $this->info("    - hasRole('{$firstRole}'): " . ($hasFirstRole ? 'TRUE' : 'FALSE'));
                }
            } else {
                $this->warn("  ⚠️ No hay usuarios para probar");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error: {$e->getMessage()}");
        }
        $this->line('');

        $this->info('✅ Pruebas de relaciones completadas');
        return Command::SUCCESS;
    }
}
