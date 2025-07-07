<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class VerifySystemCommand extends Command
{
    protected $signature = 'system:verify {--routes : Verificar solo rutas} {--views : Verificar solo vistas} {--data : Verificar solo datos}';
    protected $description = 'Verificar que el sistema esté completo y funcionando';

    public function handle()
    {
        $this->info('🔍 Verificando el sistema completo...');
        $this->line('');

        $checks = [];

        if (!$this->option('views') && !$this->option('data')) {
            $checks[] = 'routes';
        }

        if (!$this->option('routes') && !$this->option('data')) {
            $checks[] = 'views';
        }

        if (!$this->option('routes') && !$this->option('views')) {
            $checks[] = 'data';
        }

        // Si se especifica una opción, solo verificar esa
        if ($this->option('routes')) $checks = ['routes'];
        if ($this->option('views')) $checks = ['views'];
        if ($this->option('data')) $checks = ['data'];

        $errors = 0;
        $warnings = 0;

        foreach ($checks as $check) {
            switch ($check) {
                case 'routes':
                    [$routeErrors, $routeWarnings] = $this->verifyRoutes();
                    $errors += $routeErrors;
                    $warnings += $routeWarnings;
                    break;
                case 'views':
                    [$viewErrors, $viewWarnings] = $this->verifyViews();
                    $errors += $viewErrors;
                    $warnings += $viewWarnings;
                    break;
                case 'data':
                    [$dataErrors, $dataWarnings] = $this->verifyData();
                    $errors += $dataErrors;
                    $warnings += $dataWarnings;
                    break;
            }
        }

        $this->line('');
        $this->info('📊 Resumen de verificación:');

        if ($errors === 0 && $warnings === 0) {
            $this->info('  ✅ Sistema verificado correctamente - Sin problemas detectados');
        } else {
            if ($errors > 0) {
                $this->error("  ❌ Errores encontrados: {$errors}");
            }
            if ($warnings > 0) {
                $this->warn("  ⚠️ Advertencias encontradas: {$warnings}");
            }
        }

        $this->line('');
        $this->info('💡 Recomendaciones:');
        $this->info('  - Ejecutar: php artisan route:cache');
        $this->info('  - Ejecutar: php artisan view:cache');
        $this->info('  - Ejecutar: php artisan config:cache');

        return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function verifyRoutes()
    {
        $this->info('🛣️ Verificando rutas...');
        $errors = 0;
        $warnings = 0;

        // Rutas críticas que deben existir
        $criticalRoutes = [
            // Públicas
            'welcome',
            'login',
            'dashboard',

            // Admin
            'admin.dashboard',
            'admin.users.index',
            'admin.companies.index',

            // Company
            'company.dashboard',
            'company.shipments.index',
            'company.trips.index',
            'company.operators.index',

            // Internal
            'internal.dashboard',

            // Operator
            'operator.dashboard',
            'operator.shipments.index',
            'operator.trips.index',
        ];

        foreach ($criticalRoutes as $routeName) {
            if (Route::has($routeName)) {
                $this->info("  ✅ Ruta '{$routeName}' existe");
            } else {
                $this->error("  ❌ Ruta '{$routeName}' NO existe");
                $errors++;
            }
        }

        // Verificar archivos de rutas
        $routeFiles = [
            'routes/web.php',
            'routes/admin.php',
            'routes/company.php',
            'routes/internal.php',
            'routes/operator.php',
        ];

        $this->line('');
        $this->info('📁 Verificando archivos de rutas...');

        foreach ($routeFiles as $file) {
            if (File::exists(base_path($file))) {
                $this->info("  ✅ Archivo '{$file}' existe");
            } else {
                $this->error("  ❌ Archivo '{$file}' NO existe");
                $errors++;
            }
        }

        return [$errors, $warnings];
    }

    private function verifyViews()
    {
        $this->info('👁️ Verificando vistas...');
        $errors = 0;
        $warnings = 0;

        // Vistas críticas que deben existir
        $criticalViews = [
            // Layouts
            'layouts.app',
            'layouts.guest',

            // Públicas
            'welcome',
            'dashboard',
            'auth.login',

            // Dashboards
            'admin.dashboard',
            'company.dashboard',
            'internal.dashboard',
            'operator.dashboard',

            // Listas principales
            'admin.users.index',
            'admin.companies.index',
            'company.shipments.index',
            'company.trips.index',
            'company.operators.index',
            'operator.shipments.index',
            'operator.trips.index',
        ];

        foreach ($criticalViews as $viewName) {
            if (View::exists($viewName)) {
                $this->info("  ✅ Vista '{$viewName}' existe");
            } else {
                $this->error("  ❌ Vista '{$viewName}' NO existe");
                $errors++;
            }
        }

        // Verificar componente de navegación
        $this->line('');
        $this->info('🧩 Verificando componentes...');

        if (View::exists('components.navigation')) {
            $this->info("  ✅ Componente 'navigation' existe");
        } else {
            $this->warn("  ⚠️ Componente 'navigation' NO existe - La navegación podría no funcionar");
            $warnings++;
        }

        return [$errors, $warnings];
    }

    private function verifyData()
    {
        $this->info('💾 Verificando datos del sistema...');
        $errors = 0;
        $warnings = 0;

        // Verificar usuarios
        $totalUsers = User::count();
        $activeUsers = User::where('active', true)->count();
        $superAdmins = User::whereHas('roles', fn($q) => $q->where('name', 'super-admin'))->count();

        $this->info("  📊 Usuarios: {$totalUsers} total, {$activeUsers} activos");

        if ($totalUsers === 0) {
            $this->error("  ❌ No hay usuarios en el sistema");
            $errors++;
        } elseif ($superAdmins === 0) {
            $this->error("  ❌ No hay super administradores");
            $errors++;
        } else {
            $this->info("  ✅ Sistema con {$superAdmins} super administrador(es)");
        }

        // Verificar empresas
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('active', true)->count();
        $companiesWithCerts = Company::whereNotNull('certificate_path')->count();

        $this->info("  🏢 Empresas: {$totalCompanies} total, {$activeCompanies} activas");

        if ($totalCompanies === 0) {
            $this->warn("  ⚠️ No hay empresas registradas");
            $warnings++;
        } else {
            $this->info("  ✅ Sistema con empresas registradas");
            if ($companiesWithCerts === 0) {
                $this->warn("  ⚠️ Ninguna empresa tiene certificados configurados");
                $warnings++;
            } else {
                $this->info("  ✅ {$companiesWithCerts} empresa(s) con certificados");
            }
        }

        // Verificar operadores
        $totalOperators = Operator::count();
        $externalOperators = Operator::where('type', 'external')->count();
        $internalOperators = Operator::where('type', 'internal')->count();

        $this->info("  👷 Operadores: {$totalOperators} total ({$externalOperators} externos, {$internalOperators} internos)");

        // Verificar relaciones polimórficas
        $usersWithRelations = User::whereNotNull('userable_type')->count();
        $this->info("  🔗 Relaciones: {$usersWithRelations} usuarios con relaciones polimórficas");

        if ($usersWithRelations === 0 && $totalUsers > 0) {
            $this->warn("  ⚠️ Hay usuarios sin relaciones polimórficas configuradas");
            $warnings++;
        }

        // Verificar roles y permisos
        try {
            $rolesCount = \Spatie\Permission\Models\Role::count();
            $permissionsCount = \Spatie\Permission\Models\Permission::count();

            $this->info("  🎭 Permisos: {$rolesCount} roles, {$permissionsCount} permisos");

            if ($rolesCount === 0) {
                $this->error("  ❌ No hay roles configurados");
                $errors++;
            }

            if ($permissionsCount === 0) {
                $this->error("  ❌ No hay permisos configurados");
                $errors++;
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error verificando roles y permisos: " . $e->getMessage());
            $errors++;
        }

        // Verificar certificados próximos a vencer
        $expiringSoon = Company::whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '<=', now()->addDays(30))
            ->where('certificate_expires_at', '>=', now())
            ->count();

        $expired = Company::whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '<', now())
            ->count();

        if ($expired > 0) {
            $this->warn("  ⚠️ {$expired} certificado(s) vencido(s)");
            $warnings++;
        }

        if ($expiringSoon > 0) {
            $this->warn("  ⚠️ {$expiringSoon} certificado(s) vencen en los próximos 30 días");
            $warnings++;
        }

        return [$errors, $warnings];
    }
}
