<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * COMANDO DE VERIFICACIÓN PRE-TESTING MÓDULO 4
 * 
 * Verifica el estado del sistema antes del testing del flujo webservices
 * usando únicamente los modelos y datos existentes en el repositorio.
 * 
 * UBICACIÓN: app/Console/Commands/TestingVerificationCommand.php
 * 
 * USO:
 * php artisan testing:verify-module4
 * php artisan testing:verify-module4 --company=1006
 */
class TestingVerificationCommand extends Command
{
    protected $signature = 'testing:verify-module4 
                           {--company=1006 : ID de empresa para testing}';

    protected $description = 'Verificar estado del sistema antes del testing de webservices (Módulo 4)';

    public function handle(): int
    {
        $this->info('🔍 VERIFICACIÓN PRE-TESTING - MÓDULO 4 WEBSERVICES');
        $this->info('================================================');
        $this->newLine();

        $companyId = $this->option('company');
        $allGood = true;

        try {
            // 1. VERIFICAR EMPRESA DE TESTING
            $this->info('1️⃣ Verificando empresa de testing...');
            $company = Company::find($companyId);
            
            if (!$company) {
                $this->error("❌ Empresa {$companyId} no encontrada");
                return Command::FAILURE;
            }

            $this->info("✅ Empresa encontrada: {$company->legal_name}");
            
            // Verificar certificado
            if ($company->certificate_path) {
                $this->info("✅ Certificado configurado: " . basename($company->certificate_path));
                
                // Verificar si el archivo existe
                if (Storage::exists($company->certificate_path)) {
                    $this->info("✅ Archivo de certificado existe");
                } else {
                    $this->warn("⚠️  Archivo de certificado no existe en storage");
                }
            } else {
                $this->warn("⚠️  No hay certificado configurado");
            }

            $this->newLine();

            // 2. VERIFICAR TABLAS DE WEBSERVICES
            $this->info('2️⃣ Verificando tablas de webservices...');
            
            $tables = ['webservice_transactions', 'webservice_logs', 'webservice_responses'];
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $count = \DB::table($table)->count();
                    $this->info("✅ Tabla {$table}: {$count} registros");
                } else {
                    $this->error("❌ Tabla {$table} no existe");
                    $allGood = false;
                }
            }

            $this->newLine();

            // 3. VERIFICAR VIAJES DISPONIBLES PARA TESTING
            $this->info('3️⃣ Verificando viajes disponibles...');
            
            $voyages = Voyage::where('company_id', $company->id)
                ->whereHas('shipments')
                ->get();

            if ($voyages->count() > 0) {
                $this->info("✅ {$voyages->count()} viajes encontrados con shipments");
                
                // Mostrar algunos ejemplos
                $voyages->take(3)->each(function($voyage) {
                    $shipmentsCount = $voyage->shipments()->count();
                    $this->line("   • Viaje {$voyage->voyage_number}: {$shipmentsCount} shipments");
                });
            } else {
                $this->warn("⚠️  No hay viajes con shipments para testing");
            }

            $this->newLine();

            // 4. VERIFICAR TRANSACCIONES WEBSERVICE EXISTENTES
            $this->info('4️⃣ Verificando historial de webservices...');
            
            $transactions = WebserviceTransaction::where('company_id', $company->id)->get();
            
            if ($transactions->count() > 0) {
                $this->info("✅ {$transactions->count()} transacciones en historial");
                
                // Estadísticas por estado
                $stats = $transactions->groupBy('status')->map->count();
                foreach ($stats as $status => $count) {
                    $icon = match($status) {
                        'success' => '✅',
                        'pending' => '⏳',
                        'error' => '❌',
                        default => '📊'
                    };
                    $this->line("   {$icon} {$status}: {$count}");
                }
            } else {
                $this->info("ℹ️  No hay transacciones previas (perfecto para testing limpio)");
            }

            $this->newLine();

            // 5. VERIFICAR USUARIOS DE LA EMPRESA
            $this->info('5️⃣ Verificando usuarios de la empresa...');
            
            $totalUsers = 0;
            
            // Administradores de empresa (relación directa)
            $companyUsers = User::where('userable_type', 'App\Models\Company')
                ->where('userable_id', $company->id)
                ->get();
            
            if ($companyUsers->count() > 0) {
                $this->info("✅ {$companyUsers->count()} administradores de empresa:");
                $companyUsers->each(function($user) {
                    $roles = $user->roles->pluck('name')->join(', ');
                    $this->line("   👔 {$user->name} ({$user->email}) - {$roles}");
                });
                $totalUsers += $companyUsers->count();
            }
            
            // Operadores de la empresa (a través de tabla operators)
            $operatorUsers = collect();
            try {
                if (Schema::hasTable('operators')) {
                    $operatorIds = \DB::table('operators')
                        ->where('company_id', $company->id)
                        ->pluck('id');
                    
                    if ($operatorIds->count() > 0) {
                        $operatorUsers = User::where('userable_type', 'App\Models\Operator')
                            ->whereIn('userable_id', $operatorIds)
                            ->get();
                        
                        if ($operatorUsers->count() > 0) {
                            $this->info("✅ {$operatorUsers->count()} operadores de la empresa:");
                            $operatorUsers->each(function($user) {
                                $roles = $user->roles->pluck('name')->join(', ');
                                // Obtener nombre del operador directamente de la BD
                                $operator = \DB::table('operators')->find($user->userable_id);
                                $operatorName = $operator ? "{$operator->first_name} {$operator->last_name}" : 'N/A';
                                $this->line("   👨‍💻 {$user->name} ({$operatorName}) - {$roles}");
                            });
                            $totalUsers += $operatorUsers->count();
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->warn("   ⚠️  Error consultando operadores: " . $e->getMessage());
            }
            
            if ($totalUsers === 0) {
                $this->warn("⚠️  No hay usuarios relacionados con la empresa");
            } else {
                $this->info("📊 Total de usuarios: {$totalUsers}");
            }

            $this->newLine();

            // 6. VERIFICAR ARCHIVOS DE CONTROLADORES
            $this->info('6️⃣ Verificando controladores del Módulo 4...');
            
            $controllers = [
                'WebServiceController' => 'app/Http/Controllers/Company/WebServiceController.php',
                'ManifestCustomsController' => 'app/Http/Controllers/Company/Manifests/ManifestCustomsController.php',
                'CertificateController' => 'app/Http/Controllers/Company/CertificateController.php'
            ];

            foreach ($controllers as $name => $path) {
                if (file_exists(base_path($path))) {
                    $this->info("✅ {$name} existe");
                } else {
                    $this->error("❌ {$name} no encontrado en {$path}");
                    $allGood = false;
                }
            }

            $this->newLine();

            // 7. RESUMEN FINAL
            $this->info('📋 RESUMEN DE VERIFICACIÓN');
            $this->info('===========================');
            
            if ($allGood) {
                $this->info('🎉 SISTEMA LISTO PARA TESTING');
                $this->info('');
                $this->info('Próximos pasos sugeridos:');
                $this->info('1. Acceder al dashboard: /company/webservices');
                $this->info('2. Verificar certificados: /company/certificates');
                $this->info('3. Probar envío: /company/manifests/customs');
                $this->info('4. Ver historial: /company/webservices/history');
            } else {
                $this->warn('⚠️  ALGUNOS PROBLEMAS DETECTADOS');
                $this->info('Revisar los errores antes de continuar con testing');
            }

            return $allGood ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ Error durante verificación: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}