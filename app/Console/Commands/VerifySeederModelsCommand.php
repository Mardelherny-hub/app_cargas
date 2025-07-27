<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\Captain;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Verificación de Modelos para Seeders
 * 
 * Comando para verificar la estructura real de los modelos Voyage y WebserviceTransaction
 * antes de corregir los seeders del módulo 4.
 * 
 * PROPÓSITO:
 * - Verificar campos reales de las tablas vs seeders
 * - Detectar discrepancias entre código y base de datos
 * - Validar dependencias necesarias para los seeders
 * - Generar reporte de campos faltantes o incorrectos
 */
class VerifySeederModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'webservices:verify-models
                           {--show-all : Mostrar todos los campos disponibles}
                           {--check-data : Verificar datos existentes}
                           {--fix-suggestions : Mostrar sugerencias de corrección}';

    /**
     * The console command description.
     */
    protected $description = 'Verifica la estructura de modelos para seeders del módulo webservices';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        try {
            // 1. Verificar estructura de tablas
            $this->info('🔍 PASO 1: Verificando estructura de tablas...');
            $tablesStructure = $this->verifyTablesStructure();

            // 2. Verificar campos de los seeders vs modelos reales
            $this->info('📋 PASO 2: Comparando seeders con modelos reales...');
            $fieldComparison = $this->compareSeederFields($tablesStructure);

            // 3. Verificar dependencias
            $this->info('🔗 PASO 3: Verificando dependencias...');
            $dependencies = $this->verifyDependencies();

            // 4. Verificar datos existentes (opcional)
            if ($this->option('check-data')) {
                $this->info('📊 PASO 4: Verificando datos existentes...');
                $this->verifyExistingData();
            }

            // 5. Mostrar resumen y sugerencias
            $this->displaySummary($tablesStructure, $fieldComparison, $dependencies);

            if ($this->option('fix-suggestions')) {
                $this->displayFixSuggestions($fieldComparison);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error verificando modelos: ' . $e->getMessage());
            $this->error('📍 Archivo: ' . $e->getFile() . ' línea ' . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * Mostrar encabezado del comando
     */
    private function displayHeader(): void
    {
        $this->line('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║              🔍 VERIFICACIÓN MODELOS SEEDERS                 ║');
        $this->info('║                    MÓDULO 4: WEBSERVICES                    ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    /**
     * Verificar estructura de las tablas principales
     */
    private function verifyTablesStructure(): array
    {
        $tables = [
            'voyages' => 'Viajes',
            'webservice_transactions' => 'Transacciones Webservice',
            'companies' => 'Empresas',
            'countries' => 'Países',
            'ports' => 'Puertos',
            'vessels' => 'Embarcaciones',
            'captains' => 'Capitanes',
        ];

        $structure = [];

        foreach ($tables as $tableName => $displayName) {
            $this->line("   Verificando tabla: {$displayName} ({$tableName})");

            if (!Schema::hasTable($tableName)) {
                $this->warn("   ❌ Tabla '{$tableName}' NO EXISTE");
                $structure[$tableName] = ['exists' => false, 'columns' => []];
                continue;
            }

            $columns = Schema::getColumnListing($tableName);
            $structure[$tableName] = [
                'exists' => true,
                'columns' => $columns,
                'count' => count($columns)
            ];

            $this->info("   ✅ Tabla '{$tableName}' existe con " . count($columns) . " campos");

            if ($this->option('show-all')) {
                $this->line("      Campos: " . implode(', ', array_slice($columns, 0, 10)) . (count($columns) > 10 ? '...' : ''));
            }
        }

        return $structure;
    }

    /**
     * Comparar campos de seeders con estructura real
     */
    private function compareSeederFields(array $tablesStructure): array
    {
        $comparison = [];

        // Campos usados en VoyagesFromParanaSeeder
        $voyageSeederFields = [
            'voyage_number', 'internal_reference', 'barge_name', 'company_id',
            'created_by_user_id', 'last_updated_by_user_id', 'origin_port',
            'origin_port_name', 'destination_port', 'destination_port_name',
            'origin_country', 'destination_country', 'departure_date',
            'estimated_arrival_date', 'actual_arrival_date', 'created_date',
            'last_updated_date', 'status', 'manifest_type', 'active', 'archived',
            'total_containers', 'estimated_duration_hours', 'webservice_data'
        ];

        // Campos usados en WebserviceTransactionsSeeder
        $webserviceSeederFields = [
            'company_id', 'user_id', 'voyage_id', 'transaction_id', 'webservice_type',
            'country', 'environment', 'status', 'webservice_url', 'external_reference',
            'internal_reference', 'confirmation_number', 'sent_at', 'response_at',
            'error_code', 'error_message', 'retry_count', 'max_retries',
            'container_count', 'total_weight_kg', 'currency_code', 'ip_address',
            'user_agent', 'additional_metadata'
        ];

        // Verificar campos de Voyage
        if (isset($tablesStructure['voyages']['columns'])) {
            $realVoyageFields = $tablesStructure['voyages']['columns'];
            $comparison['voyages'] = [
                'seeder_fields' => $voyageSeederFields,
                'real_fields' => $realVoyageFields,
                'missing_in_db' => array_diff($voyageSeederFields, $realVoyageFields),
                'not_used_by_seeder' => array_diff($realVoyageFields, $voyageSeederFields),
                'matches' => array_intersect($voyageSeederFields, $realVoyageFields)
            ];
        }

        // Verificar campos de WebserviceTransaction
        if (isset($tablesStructure['webservice_transactions']['columns'])) {
            $realWebserviceFields = $tablesStructure['webservice_transactions']['columns'];
            $comparison['webservice_transactions'] = [
                'seeder_fields' => $webserviceSeederFields,
                'real_fields' => $realWebserviceFields,
                'missing_in_db' => array_diff($webserviceSeederFields, $realWebserviceFields),
                'not_used_by_seeder' => array_diff($realWebserviceFields, $webserviceSeederFields),
                'matches' => array_intersect($webserviceSeederFields, $realWebserviceFields)
            ];
        }

        return $comparison;
    }

    /**
     * Verificar dependencias necesarias para los seeders
     */
    private function verifyDependencies(): array
    {
        $dependencies = [
            'companies' => [
                'model' => Company::class,
                'required_data' => 'MAERSK',
                'check' => function() {
                    return Company::where('legal_name', 'LIKE', '%MAERSK%')->exists();
                }
            ],
            'countries' => [
                'model' => Country::class,
                'required_data' => 'Argentina (AR) y Paraguay (PY)',
                'check' => function() {
                    return Country::whereIn('iso_code', ['AR', 'PY'])->count() >= 2;
                }
            ],
            'ports' => [
                'model' => Port::class,
                'required_data' => 'ARBUE y PYTVT',
                'check' => function() {
                    return Port::whereIn('code', ['ARBUE', 'PYTVT'])->count() >= 2;
                }
            ],
            'vessels' => [
                'model' => Vessel::class,
                'required_data' => 'Al menos 1 embarcación',
                'check' => function() {
                    return Vessel::count() > 0;
                }
            ],
            'captains' => [
                'model' => Captain::class,
                'required_data' => 'Al menos 1 capitán activo',
                'check' => function() {
                    return Captain::where('active', true)->count() > 0;
                }
            ]
        ];

        $results = [];
        foreach ($dependencies as $key => $dependency) {
            try {
                $exists = $dependency['check']();
                $results[$key] = [
                    'exists' => $exists,
                    'required' => $dependency['required_data'],
                    'model' => $dependency['model']
                ];

                if ($exists) {
                    $this->info("   ✅ {$dependency['required_data']} - Disponible");
                } else {
                    $this->warn("   ❌ {$dependency['required_data']} - FALTANTE");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error verificando {$key}: " . $e->getMessage());
                $results[$key] = ['exists' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Verificar datos existentes
     */
    private function verifyExistingData(): void
    {
        try {
            $voyagesCount = Voyage::count();
            $webservicesCount = WebserviceTransaction::count();
            $companiesCount = Company::count();

            $this->line("   📊 Datos actuales:");
            $this->line("      - Viajes: {$voyagesCount}");
            $this->line("      - Transacciones WS: {$webservicesCount}");
            $this->line("      - Empresas: {$companiesCount}");

            if ($voyagesCount > 0) {
                $recentVoyage = Voyage::latest()->first();
                $this->line("      - Último viaje: " . ($recentVoyage && $recentVoyage->voyage_number ? $recentVoyage->voyage_number : 'N/A'));
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error verificando datos existentes: " . $e->getMessage());
        }
    }

    /**
     * Mostrar resumen de verificación
     */
    private function displaySummary(array $structure, array $comparison, array $dependencies): void
    {
        $this->line('');
        $this->info('📋 RESUMEN DE VERIFICACIÓN:');
        $this->line('');

        // Resumen de tablas
        $tablesOk = array_filter($structure, fn($table) => $table['exists']);
        $this->info('📊 TABLAS: ' . count($tablesOk) . '/' . count($structure) . ' disponibles');

        // Resumen de dependencias  
        $depsOk = array_filter($dependencies, fn($dep) => $dep['exists']);
        $this->info('🔗 DEPENDENCIAS: ' . count($depsOk) . '/' . count($dependencies) . ' satisfechas');

        // Resumen de campos problemáticos
        $this->line('');
        $this->warn('⚠️ PROBLEMAS DETECTADOS:');

        foreach ($comparison as $table => $comp) {
            if (!empty($comp['missing_in_db'])) {
                $this->error("   {$table}: " . count($comp['missing_in_db']) . " campos del seeder NO EXISTEN en DB");
                $this->line("      Faltantes: " . implode(', ', array_slice($comp['missing_in_db'], 0, 5)) . 
                           (count($comp['missing_in_db']) > 5 ? '...' : ''));
            }
        }
    }

    /**
     * Mostrar sugerencias de corrección
     */
    private function displayFixSuggestions(array $comparison): void
    {
        $this->line('');
        $this->info('🔧 SUGERENCIAS DE CORRECCIÓN:');
        $this->line('');

        foreach ($comparison as $table => $comp) {
            if (!empty($comp['missing_in_db'])) {
                $this->warn("📝 Para tabla '{$table}':");

                // Sugerencias específicas basadas en campos conocidos
                $suggestions = $this->generateSpecificSuggestions($table, $comp['missing_in_db'], $comp['real_fields']);
                
                foreach ($suggestions as $suggestion) {
                    $this->line("   • {$suggestion}");
                }
                $this->line('');
            }
        }
    }

    /**
     * Generar sugerencias específicas de corrección
     */
    private function generateSpecificSuggestions(string $table, array $missing, array $real): array
    {
        $suggestions = [];

        if ($table === 'voyages') {
            foreach ($missing as $field) {
                switch ($field) {
                    case 'barge_name':
                        $suggestions[] = "'{$field}' → Usar 'lead_vessel_id' + relación con tabla vessels";
                        break;
                    case 'origin_port':
                        $suggestions[] = "'{$field}' → Cambiar a 'origin_port_id'";
                        break;
                    case 'destination_port':
                        $suggestions[] = "'{$field}' → Cambiar a 'destination_port_id'";
                        break;
                    case 'total_containers':
                        $suggestions[] = "'{$field}' → Cambiar a 'total_containers_loaded'";
                        break;
                    case 'webservice_data':
                        if (in_array('additional_metadata', $real)) {
                            $suggestions[] = "'{$field}' → Probablemente debería ser 'additional_metadata'";
                        }
                        break;
                    default:
                        $suggestions[] = "'{$field}' → Verificar si existe campo similar en: " . implode(', ', array_slice($real, 0, 3));
                }
            }
        }

        if ($table === 'webservice_transactions') {
            foreach ($missing as $field) {
                $similarFields = array_filter($real, fn($realField) => 
                    str_contains($realField, $field) || str_contains($field, $realField)
                );
                
                if (!empty($similarFields)) {
                    $suggestions[] = "'{$field}' → Posible reemplazo: " . implode(', ', $similarFields);
                } else {
                    $suggestions[] = "'{$field}' → Verificar si es necesario o si hay campo equivalente";
                }
            }
        }

        return $suggestions;
    }
}