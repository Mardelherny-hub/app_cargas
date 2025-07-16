<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\ClientCompanyRelation;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Comando para verificar el estado completo del módulo de clientes
 * antes y después de ejecutar los seeders
 */
class VerifyClientsModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'clients:verify
                           {--run-seeders : Ejecutar seeders automáticamente si está todo listo}
                           {--force : Forzar ejecución aunque falten dependencias}';

    /**
     * The console command description.
     */
    protected $description = 'Verifica el estado del módulo de clientes y ejecuta seeders si está listo';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        // Paso 1: Verificar estructura de base de datos
        $structureOk = $this->verifyDatabaseStructure();

        // Paso 2: Verificar catálogos base (Fase 0)
        $catalogsOk = $this->verifyCatalogs();

        // Paso 3: Verificar empresas existentes
        $companiesOk = $this->verifyCompanies();

        // Paso 4: Verificar datos existentes de clientes
        $this->verifyExistingData();

        // Evaluación general
        $allReady = $structureOk && $catalogsOk && $companiesOk;

        if ($allReady) {
            $this->components->info('✅ MÓDULO LISTO PARA SEEDERS');

            if ($this->option('run-seeders')) {
                return $this->runSeeders();
            } else {
                $this->showNextSteps();
            }
        } else {
            $this->components->error('❌ FALTAN DEPENDENCIAS - Ver errores arriba');

            if ($this->option('force')) {
                $this->components->warn('⚠️ FORZANDO EJECUCIÓN...');
                return $this->runSeeders();
            }
        }

        return $allReady ? 0 : 1;
    }

    /**
     * Mostrar header del comando
     */
    private function displayHeader(): void
    {
        $this->line('');
        $this->components->info('🔍 VERIFICACIÓN MÓDULO CLIENTES - FASE 1');
        $this->line('======================================================');
        $this->line('');
    }

    /**
     * Verificar estructura de base de datos
     */
    private function verifyDatabaseStructure(): bool
    {
        $this->components->info('📋 Verificando estructura de base de datos...');

        $tables = [
            'clients' => [
                'required_columns' => [
                    'id', 'tax_id', 'country_id', 'document_type_id',
                    'client_type', 'business_name', 'status', 'created_by_company_id'
                ]
            ],
            'client_company_relations' => [
                'required_columns' => [
                    'id', 'client_id', 'company_id', 'relation_type', 'can_edit', 'active'
                ]
            ]
        ];

        $allOk = true;

        foreach ($tables as $table => $config) {
            if (!Schema::hasTable($table)) {
                $this->components->error("  ❌ Tabla '{$table}' no existe");
                $allOk = false;
                continue;
            }

            $this->line("  ✓ Tabla '{$table}' existe");

            // Verificar columnas principales
            foreach ($config['required_columns'] as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $this->components->error("    ❌ Columna '{$column}' falta en '{$table}'");
                    $allOk = false;
                } else {
                    $this->line("    ✓ Columna '{$column}' ok");
                }
            }
        }

        // Verificar índices críticos
        $this->verifyIndexes();

        return $allOk;
    }

    /**
     * Verificar índices importantes
     */
    private function verifyIndexes(): void
    {
        $this->line('');
        $this->components->info('🔍 Verificando índices optimizados...');

        try {
            // Verificar índice único CUIT por país
            $indexes = collect(DB::select("SHOW INDEX FROM clients WHERE Key_name = 'unique_tax_id_per_country'"));

            if ($indexes->isNotEmpty()) {
                $this->line('  ✓ Índice único CUIT por país configurado');
            } else {
                $this->components->warn('  ⚠️ Índice único CUIT no encontrado');
            }

            // Verificar algunos índices compuestos importantes
            $keyIndexes = [
                'idx_company_status',
                'idx_tax_id_search',
                'idx_webservice_ready'
            ];

            foreach ($keyIndexes as $indexName) {
                $found = collect(DB::select("SHOW INDEX FROM clients WHERE Key_name = ?", [$indexName]));
                if ($found->isNotEmpty()) {
                    $this->line("  ✓ Índice '{$indexName}' configurado");
                } else {
                    $this->line("  - Índice '{$indexName}' no encontrado (no crítico)");
                }
            }

        } catch (\Exception $e) {
            $this->components->warn('  ⚠️ No se pudieron verificar índices: ' . $e->getMessage());
        }
    }

    /**
     * Verificar catálogos base (Fase 0)
     */
    private function verifyCatalogs(): bool
    {
        $this->line('');
        $this->components->info('📚 Verificando catálogos base (Fase 0)...');

        $catalogs = [
            'countries' => Country::class,
            'document_types' => DocumentType::class,
            'ports' => Port::class,
            'custom_offices' => CustomOffice::class,
        ];

        $allOk = true;

        foreach ($catalogs as $name => $model) {
            if (!class_exists($model)) {
                $this->components->error("  ❌ Modelo {$model} no existe");
                $allOk = false;
                continue;
            }

            try {
                $count = $model::count();
                if ($count > 0) {
                    $this->line("  ✓ {$name}: {$count} registros");
                } else {
                    $this->components->error("  ❌ {$name}: SIN DATOS");
                    $allOk = false;
                }
            } catch (\Exception $e) {
                $this->components->error("  ❌ {$name}: Error - " . $e->getMessage());
                $allOk = false;
            }
        }

        // Verificar datos específicos críticos
        $this->verifySpecificCatalogData();

        return $allOk;
    }

    /**
     * Verificar datos específicos de catálogos
     */
    private function verifySpecificCatalogData(): void
    {
        $this->line('');
        $this->components->info('🎯 Verificando datos específicos...');

        // Verificar Argentina y Paraguay
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if ($argentina) {
            $this->line("  ✓ Argentina encontrada (ID: {$argentina->id})");

            // Verificar datos relacionados con Argentina
            $arDocTypes = DocumentType::where('country_id', $argentina->id)->count();
            $arPorts = Port::where('country_id', $argentina->id)->count();
            $arCustoms = CustomOffice::where('country_id', $argentina->id)->count();

            $this->line("    - Tipos documento: {$arDocTypes}");
            $this->line("    - Puertos: {$arPorts}");
            $this->line("    - Aduanas: {$arCustoms}");
        } else {
            $this->components->error('  ❌ Argentina no encontrada');
        }

        if ($paraguay) {
            $this->line("  ✓ Paraguay encontrado (ID: {$paraguay->id})");

            // Verificar datos relacionados con Paraguay
            $pyDocTypes = DocumentType::where('country_id', $paraguay->id)->count();
            $pyPorts = Port::where('country_id', $paraguay->id)->count();
            $pyCustoms = CustomOffice::where('country_id', $paraguay->id)->count();

            $this->line("    - Tipos documento: {$pyDocTypes}");
            $this->line("    - Puertos: {$pyPorts}");
            $this->line("    - Aduanas: {$pyCustoms}");
        } else {
            $this->components->error('  ❌ Paraguay no encontrado');
        }
    }

    /**
     * Verificar empresas existentes
     */
    private function verifyCompanies(): bool
    {
        $this->line('');
        $this->components->info('🏢 Verificando empresas existentes...');

        try {
            $totalCompanies = Company::count();
            $activeCompanies = Company::where('active', true)->count();

            if ($totalCompanies > 0) {
                $this->line("  ✓ Total empresas: {$totalCompanies}");
                $this->line("  ✓ Empresas activas: {$activeCompanies}");

                if ($activeCompanies === 0) {
                    $this->components->warn('  ⚠️ No hay empresas activas');
                    return false;
                }

                return true;
            } else {
                $this->components->error('  ❌ No hay empresas en el sistema');
                return false;
            }
        } catch (\Exception $e) {
            $this->components->error('  ❌ Error verificando empresas: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar datos existentes de clientes
     */
    private function verifyExistingData(): void
    {
        $this->line('');
        $this->components->info('👥 Estado actual de clientes...');

        try {
            $clientCount = Client::count();
            $relationCount = ClientCompanyRelation::count();

            if ($clientCount > 0) {
                $this->line("  📊 Clientes existentes: {$clientCount}");

                // Estadísticas por país
                $arClients = Client::argentina()->count();
                $pyClients = Client::paraguay()->count();
                $this->line("    🇦🇷 Argentina: {$arClients}");
                $this->line("    🇵🇾 Paraguay: {$pyClients}");

                // Estadísticas por estado
                $active = Client::where('status', 'active')->count();
                $verified = Client::whereNotNull('verified_at')->count();
                $this->line("    ✅ Activos: {$active}");
                $this->line("    ✅ Verificados: {$verified}");

                // Relaciones
                $this->line("  🔗 Relaciones empresa-cliente: {$relationCount}");

                $this->components->warn('  ⚠️ YA EXISTEN DATOS - Los seeders pueden duplicar información');
            } else {
                $this->line('  📊 No hay clientes - Listo para seeders');
            }
        } catch (\Exception $e) {
            $this->components->error('  ❌ Error verificando datos: ' . $e->getMessage());
        }
    }

    /**
     * Ejecutar seeders
     */
    private function runSeeders(): int
    {
        $this->line('');
        $this->components->info('🚀 Ejecutando seeders del módulo clientes...');

        try {
            // Ejecutar seeder principal
            $this->call('db:seed', [
                '--class' => 'ClientsSeeder'
            ]);

            $this->line('');
            $this->components->info('✅ SEEDERS EJECUTADOS CORRECTAMENTE');

            // Mostrar resumen final
            $this->showFinalSummary();

            return 0;
        } catch (\Exception $e) {
            $this->components->error('❌ Error ejecutando seeders: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Mostrar próximos pasos
     */
    private function showNextSteps(): void
    {
        $this->line('');
        $this->components->info('📋 PRÓXIMOS PASOS:');
        $this->line('');
        $this->line('1. Ejecutar seeders manualmente:');
        $this->line('   php artisan db:seed --class=ClientsSeeder');
        $this->line('');
        $this->line('2. O ejecutar con este comando:');
        $this->line('   php artisan clients:verify --run-seeders');
        $this->line('');
        $this->line('3. Verificar resultado:');
        $this->line('   php artisan clients:verify');
    }

    /**
     * Mostrar resumen final
     */
    private function showFinalSummary(): void
    {
        $this->line('');
        $this->line('=== 📊 RESUMEN FINAL DEL MÓDULO ===');

        try {
            $totalClients = Client::count();
            $totalRelations = ClientCompanyRelation::count();
            $activeClients = Client::where('status', 'active')->count();
            $verifiedClients = Client::whereNotNull('verified_at')->count();

            $arClients = Client::argentina()->count();
            $pyClients = Client::paraguay()->count();

            $shippers = Client::where('client_type', 'shipper')->count();
            $consignees = Client::where('client_type', 'consignee')->count();

            $this->line("Total Clientes: {$totalClients}");
            $this->line("  🇦🇷 Argentina: {$arClients}");
            $this->line("  🇵🇾 Paraguay: {$pyClients}");
            $this->line("  ✅ Activos: {$activeClients}");
            $this->line("  ✅ Verificados: {$verifiedClients}");
            $this->line("  📦 Cargadores: {$shippers}");
            $this->line("  📥 Consignatarios: {$consignees}");
            $this->line("Relaciones: {$totalRelations}");

            $this->line('');
            $this->components->info('🎯 FASE 1 COMPLETADA - Listo para Fase 2');

        } catch (\Exception $e) {
            $this->components->error('Error generando resumen: ' . $e->getMessage());
        }
    }
}
