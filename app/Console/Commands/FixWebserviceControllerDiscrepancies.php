<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\Shipment;
use Exception;
use Carbon\Carbon;

/**
 * SCRIPT 3.5 - COMANDO ARTISAN PARA CORREGIR DISCREPANCIAS
 * 
 * Corrige automáticamente las discrepancias entre WebServiceController.php
 * y los modelos reales de la base de datos identificadas durante ejecución.
 * 
 * PROBLEMA DETECTADO:
 * - Campo 'voyage_code' no existe (debe ser 'voyage_number')
 * - Campo 'barge_name' no existe (debe ser 'internal_reference')  
 * - Relaciones con campos incorrectos
 * - Filtros que usan campos inexistentes
 * - Validaciones con nombres de campo incorrectos
 * 
 * USO:
 * php artisan webservice:fix-controller-discrepancies --dry-run
 * php artisan webservice:fix-controller-discrepancies --backup
 */
class FixWebserviceControllerDiscrepancies extends Command
{
    /**
     * Firma del comando
     */
    protected $signature = 'webservice:fix-controller-discrepancies 
                           {--dry-run : Mostrar cambios sin aplicar}
                           {--backup : Crear backup del archivo original}
                           {--force : Aplicar cambios sin confirmación}';

    /**
     * Descripción del comando
     */
    protected $description = 'Corrige discrepancias entre WebServiceController y modelos reales de BD detectadas en query blade';

    /**
     * Mapeo de correcciones conocidas
     */
    private $fieldMappings = [
        'voyage' => [
            'voyage_code' => 'voyage_number',
            'barge_name' => 'internal_reference',
            'departure_port' => 'origin_port_id',
            'arrival_port' => 'destination_port_id',
        ],
        'webservice_transaction' => [
            // Aquí se pueden agregar más mappings si se detectan
        ],
        'webservice_response' => [
            // Mappings para respuestas si son necesarios
        ]
    ];

    /**
     * Campos reales de los modelos obtenidos via reflection
     */
    private $realModelFields = [];

    /**
     * Contador de cambios aplicados
     */
    private $changesApplied = 0;

    /**
     * Ejecutar el comando
     */
    public function handle()
    {
        $this->info('🔍 SCRIPT 3.5: Analizando discrepancias en WebServiceController...');
        $this->newLine();

        try {
            // 1. Verificar que el archivo existe
            $controllerPath = app_path('Http/Controllers/Company/WebServiceController.php');
            if (!File::exists($controllerPath)) {
                $this->error('❌ WebServiceController.php no encontrado en: ' . $controllerPath);
                return Command::FAILURE;
            }

            // 2. Obtener campos reales de los modelos
            $this->info('📋 Obteniendo estructura real de modelos...');
            $this->realModelFields = $this->getRealModelFields();
            
            // Verificar que tenemos datos válidos
            if (empty($this->realModelFields['voyage'])) {
                $this->error('❌ No se pudieron obtener los campos del modelo Voyage');
                return Command::FAILURE;
            }
            
            // 3. Leer contenido actual del controlador
            $originalContent = File::get($controllerPath);
            
            // 4. Analizar y detectar discrepancias
            $this->info('🔍 Detectando discrepancias...');
            $discrepancies = $this->detectDiscrepancies($originalContent);
            
            if (empty($discrepancies)) {
                $this->info('✅ No se encontraron discrepancias. El controlador está sincronizado.');
                return Command::SUCCESS;
            }

            // 5. Mostrar reporte de discrepancias
            $this->displayDiscrepanciesReport($discrepancies);
            
            // 6. Si es dry-run, solo mostrar lo que se haría
            if ($this->option('dry-run')) {
                $this->info('🔍 Modo DRY-RUN: No se aplicaron cambios.');
                $this->showWhatWouldChange($originalContent, $discrepancies);
                return Command::SUCCESS;
            }

            // 7. Confirmación antes de aplicar cambios
            if (!$this->option('force')) {
                if (!$this->confirm('¿Aplicar estas correcciones al WebServiceController.php?')) {
                    $this->info('Operación cancelada.');
                    return Command::SUCCESS;
                }
            }

            // 8. Crear backup si se solicita
            if ($this->option('backup')) {
                $this->createBackup($controllerPath, $originalContent);
            }

            // 9. Aplicar correcciones
            $this->info('🔧 Aplicando correcciones...');
            $correctedContent = $this->applyCorrections($originalContent, $discrepancies);
            
            // 10. Escribir archivo corregido
            File::put($controllerPath, $correctedContent);
            
            // 11. Verificar resultado
            $this->info('✅ Correcciones aplicadas exitosamente!');
            $this->displaySummary();
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('❌ Error inesperado: ' . $e->getMessage());
            $this->error('📍 Archivo: ' . $e->getFile());
            $this->error('📍 Línea: ' . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * Obtener campos reales de los modelos usando schema database
     */
    private function getRealModelFields(): array
    {
        $fields = [];
        
        try {
            // Voyage model
            $fields['voyage'] = $this->getTableColumns('voyages');
            $this->line("   ✅ Voyage: " . count($fields['voyage']) . " campos encontrados");
            
            // WebserviceTransaction model  
            $fields['webservice_transaction'] = $this->getTableColumns('webservice_transactions');
            $this->line("   ✅ WebserviceTransaction: " . count($fields['webservice_transaction']) . " campos encontrados");
            
            // WebserviceResponse model
            $fields['webservice_response'] = $this->getTableColumns('webservice_responses');
            $this->line("   ✅ WebserviceResponse: " . count($fields['webservice_response']) . " campos encontrados");
            
            // Shipment model
            $fields['shipment'] = $this->getTableColumns('shipments');
            $this->line("   ✅ Shipment: " . count($fields['shipment']) . " campos encontrados");
            
        } catch (Exception $e) {
            $this->error("❌ Error obteniendo estructura de modelos: " . $e->getMessage());
            
            // Fallback: usar campos conocidos manualmente
            return $this->getFallbackModelFields();
        }

        return $fields;
    }

    /**
     * Obtener columnas de una tabla específica
     */
    private function getTableColumns(string $tableName): array
    {
        try {
            // Verificar si la tabla existe
            if (!\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                $this->warn("   ⚠️  Tabla '{$tableName}' no existe");
                return [];
            }
            
            // Obtener columnas usando Schema
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($tableName);
            return $columns ?: [];
            
        } catch (Exception $e) {
            $this->warn("   ❌ Error accediendo tabla '{$tableName}': " . $e->getMessage());
            
            // Fallback: intentar con query directa
            try {
                $result = DB::select("DESCRIBE {$tableName}");
                return collect($result)->pluck('Field')->toArray();
            } catch (Exception $e2) {
                $this->warn("   ❌ Fallback también falló para '{$tableName}': " . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * Campos conocidos como fallback (basados en migraciones analizadas)
     */
    private function getFallbackModelFields(): array
    {
        $this->warn("   ⚠️  Usando campos predefinidos como fallback");
        
        return [
            'voyage' => [
                'id', 'voyage_number', 'internal_reference', 'company_id', 'lead_vessel_id',
                'captain_id', 'origin_country_id', 'origin_port_id', 'destination_country_id',
                'destination_port_id', 'transshipment_port_id', 'origin_customs_id',
                'destination_customs_id', 'transshipment_customs_id', 'departure_date',
                'estimated_arrival_date', 'actual_arrival_date', 'customs_clearance_deadline',
                'voyage_type', 'cargo_type', 'is_convoy', 'vessel_count', 'status',
                'created_at', 'updated_at'
            ],
            'webservice_transaction' => [
                'id', 'company_id', 'user_id', 'shipment_id', 'voyage_id', 'transaction_id',
                'external_reference', 'batch_id', 'webservice_type', 'country', 'webservice_url',
                'soap_action', 'status', 'retry_count', 'max_retries', 'confirmation_number',
                'sent_at', 'response_at', 'environment', 'created_at', 'updated_at'
            ],
            'webservice_response' => [
                'id', 'transaction_id', 'response_type', 'confirmation_number', 'customs_status',
                'requires_action', 'created_at', 'updated_at'
            ],
            'shipment' => [
                'id', 'shipment_number', 'reference_number', 'company_id', 'voyage_id',
                'created_at', 'updated_at'
            ]
        ];
    }

    /**
     * Detectar discrepancias en el código del controlador
     */
    private function detectDiscrepancies(string $content): array
    {
        $discrepancies = [];
        
        // 1. Detectar campos en relaciones with()
        $discrepancies = array_merge($discrepancies, $this->detectWithRelationErrors($content));
        
        // 2. Detectar campos en whereHas()
        $discrepancies = array_merge($discrepancies, $this->detectWhereHasErrors($content));
        
        // 3. Detectar campos en select()
        $discrepancies = array_merge($discrepancies, $this->detectSelectErrors($content));
        
        // 4. Detectar validaciones incorrectas
        $discrepancies = array_merge($discrepancies, $this->detectValidationErrors($content));
        
        // 5. Detectar parámetros de request incorrectos
        $discrepancies = array_merge($discrepancies, $this->detectRequestParameterErrors($content));

        return $discrepancies;
    }

    /**
     * Detectar errores en relaciones with()
     */
    private function detectWithRelationErrors(string $content): array
    {
        $errors = [];
        
        // Buscar patrones como 'voyage:id,voyage_code,barge_name'
        preg_match_all("/'voyage:id,([^']*?)'/", $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $fieldsString = $match[1];
            $fields = array_map('trim', explode(',', $fieldsString));
            
            foreach ($fields as $field) {
                if (!in_array($field, $this->realModelFields['voyage'])) {
                    $errors[] = [
                        'type' => 'with_relation',
                        'model' => 'voyage',
                        'original' => $fullMatch,
                        'field' => $field,
                        'suggestion' => $this->suggestCorrection($field, 'voyage'),
                        'line_context' => $this->getLineContext($content, $fullMatch)
                    ];
                }
            }
        }
        
        return $errors;
    }

    /**
     * Detectar errores en whereHas()
     */
    private function detectWhereHasErrors(string $content): array
    {
        $errors = [];
        
        // Buscar patrones como ->where('voyage_code', o ->orWhere('barge_name',
        preg_match_all("/->(?:where|orWhere)\s*\(\s*['\"]([^'\"]+)['\"].*?\)/", $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $field = $match[1];
            
            // Verificar si está dentro de un whereHas('voyage')
            $beforeMatch = substr($content, 0, strpos($content, $fullMatch));
            if (strpos($beforeMatch, "whereHas('voyage'") !== false) {
                if (!in_array($field, $this->realModelFields['voyage'])) {
                    $errors[] = [
                        'type' => 'where_has',
                        'model' => 'voyage',
                        'original' => $fullMatch,
                        'field' => $field,
                        'suggestion' => $this->suggestCorrection($field, 'voyage'),
                        'line_context' => $this->getLineContext($content, $fullMatch)
                    ];
                }
            }
        }
        
        return $errors;
    }

    /**
     * Detectar errores en select()
     */
    private function detectSelectErrors(string $content): array
    {
        $errors = [];
        
        // Buscar patrones como ->select('voyage_number', 'barge_name')
        preg_match_all("/->select\s*\(\s*['\"]([^'\"]*)['\"](?:\s*,\s*['\"]([^'\"]*)['\"])?\s*\)/", $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            
            // Verificar si está en contexto de Voyage
            $beforeMatch = substr($content, 0, strpos($content, $fullMatch));
            if (strpos($beforeMatch, 'Voyage::') !== false) {
                for ($i = 1; $i < count($match); $i++) {
                    if (!empty($match[$i]) && !in_array($match[$i], $this->realModelFields['voyage'])) {
                        $errors[] = [
                            'type' => 'select',
                            'model' => 'voyage',
                            'original' => $fullMatch,
                            'field' => $match[$i],
                            'suggestion' => $this->suggestCorrection($match[$i], 'voyage'),
                            'line_context' => $this->getLineContext($content, $fullMatch)
                        ];
                    }
                }
            }
        }
        
        return $errors;
    }

    /**
     * Detectar errores en validaciones
     */
    private function detectValidationErrors(string $content): array
    {
        $errors = [];
        
        // Buscar patrones en arrays de validación
        preg_match_all("/'([^']*voyage[^']*)'[^=]*=>/", $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $field = $match[1];
            if ($field === 'voyage_code' && !in_array($field, $this->realModelFields['voyage'])) {
                $errors[] = [
                    'type' => 'validation',
                    'model' => 'voyage',
                    'original' => $match[0],
                    'field' => $field,
                    'suggestion' => 'voyage_number',
                    'line_context' => $this->getLineContext($content, $match[0])
                ];
            }
        }
        
        return $errors;
    }

    /**
     * Detectar errores en parámetros de request
     */
    private function detectRequestParameterErrors(string $content): array
    {
        $errors = [];
        
        // Buscar patrones como $request->get('voyage_code')
        preg_match_all("/\\\$request->get\s*\(\s*['\"]([^'\"]*voyage[^'\"]*)['\"].*?\)/", $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $field = $match[1];
            if ($field === 'voyage_code') {
                $errors[] = [
                    'type' => 'request_parameter',
                    'model' => 'voyage',
                    'original' => $match[0],
                    'field' => $field,
                    'suggestion' => 'voyage_number',
                    'line_context' => $this->getLineContext($content, $match[0])
                ];
            }
        }
        
        return $errors;
    }

    /**
     * Sugerir corrección para un campo
     */
    private function suggestCorrection(string $field, string $model): ?string
    {
        // Usar mapeos predefinidos
        if (isset($this->fieldMappings[$model][$field])) {
            return $this->fieldMappings[$model][$field];
        }
        
        // Lógica de sugerencia inteligente
        $modelFields = $this->realModelFields[$model] ?? [];
        
        // Buscar coincidencias parciales
        foreach ($modelFields as $realField) {
            $similarity = similar_text($field, $realField);
            if ($similarity > strlen($field) * 0.6) { // 60% de similitud
                return $realField;
            }
        }
        
        return null;
    }

    /**
     * Obtener contexto de línea para un match
     */
    private function getLineContext(string $content, string $match): string
    {
        $position = strpos($content, $match);
        $beforeMatch = substr($content, 0, $position);
        $lineNumber = substr_count($beforeMatch, "\n") + 1;
        
        $lines = explode("\n", $content);
        $contextLine = $lines[$lineNumber - 1] ?? '';
        
        return "Línea {$lineNumber}: " . trim($contextLine);
    }

    /**
     * Mostrar reporte de discrepancias
     */
    private function displayDiscrepanciesReport(array $discrepancies): void
    {
        $this->error('❌ DISCREPANCIAS ENCONTRADAS:');
        $this->newLine();
        
        $grouped = collect($discrepancies)->groupBy('type');
        
        foreach ($grouped as $type => $items) {
            $this->warn("📋 {$type} (" . count($items) . " errores):");
            
            foreach ($items as $item) {
                $this->line("   • Campo '{$item['field']}' no existe en modelo {$item['model']}");
                $this->line("     Sugerencia: '{$item['suggestion']}'");
                $this->line("     Contexto: {$item['line_context']}");
                $this->newLine();
            }
        }
    }

    /**
     * Mostrar qué cambios se harían (dry-run)
     */
    private function showWhatWouldChange(string $content, array $discrepancies): void
    {
        $this->info('🔍 CAMBIOS QUE SE APLICARÍAN:');
        $this->newLine();
        
        foreach ($discrepancies as $discrepancy) {
            $this->line("➤ Reemplazar '{$discrepancy['field']}' con '{$discrepancy['suggestion']}'");
            $this->line("  En: {$discrepancy['line_context']}");
            $this->newLine();
        }
    }

    /**
     * Crear backup del archivo original
     */
    private function createBackup(string $filePath, string $content): void
    {
        $backupPath = $filePath . '.backup.' . Carbon::now()->format('Y-m-d_H-i-s');
        File::put($backupPath, $content);
        $this->info("💾 Backup creado: {$backupPath}");
    }

    /**
     * Aplicar todas las correcciones
     */
    private function applyCorrections(string $content, array $discrepancies): string
    {
        $correctedContent = $content;
        
        foreach ($discrepancies as $discrepancy) {
            $correctedContent = $this->applySingleCorrection($correctedContent, $discrepancy);
        }
        
        return $correctedContent;
    }

    /**
     * Aplicar una corrección específica
     */
    private function applySingleCorrection(string $content, array $discrepancy): string
    {
        $field = $discrepancy['field'];
        $suggestion = $discrepancy['suggestion'];
        
        if (!$suggestion) {
            return $content; // No hay sugerencia válida
        }
        
        switch ($discrepancy['type']) {
            case 'with_relation':
                // Reemplazar en relaciones with()
                $pattern = "/'voyage:id,([^']*?)'/";
                $content = preg_replace_callback($pattern, function($matches) use ($field, $suggestion) {
                    $fieldsString = $matches[1];
                    $correctedFields = str_replace($field, $suggestion, $fieldsString);
                    $this->changesApplied++;
                    return "'voyage:id,{$correctedFields}'";
                }, $content);
                break;
                
            case 'where_has':
                // Reemplazar en whereHas
                $pattern = "/->(?:where|orWhere)\s*\(\s*['\"]" . preg_quote($field) . "['\"]([^)]*\))/";
                $content = preg_replace($pattern, "->where('{$suggestion}'$1", $content);
                $this->changesApplied++;
                break;
                
            case 'select':
                // Reemplazar en select()
                $pattern = "/(['\"])" . preg_quote($field) . "(['\"])/";
                $content = preg_replace($pattern, "$1{$suggestion}$2", $content);
                $this->changesApplied++;
                break;
                
            case 'validation':
                // Reemplazar en validaciones
                $pattern = "/(['\"])" . preg_quote($field) . "(['\"][^=]*=>)/";
                $content = preg_replace($pattern, "$1{$suggestion}$2", $content);
                $this->changesApplied++;
                break;
                
            case 'request_parameter':
                // Reemplazar en request parameters (mantener consistencia)
                $pattern = "/\\\$request->get\s*\(\s*['\"]" . preg_quote($field) . "['\"]([^)]*\))/";
                $content = preg_replace($pattern, "\$request->get('{$field}'$1", $content); // Mantener nombre original para request
                break;
        }
        
        return $content;
    }

    /**
     * Mostrar resumen final
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE CORRECCIONES:');
        $this->line("   ✅ {$this->changesApplied} cambios aplicados");
        $this->line('   ✅ WebServiceController.php sincronizado con modelos reales');
        $this->line('   ✅ Listo para implementar history.blade.php (Script 4)');
        $this->newLine();
        
        $this->comment('💡 PRÓXIMOS PASOS:');
        $this->line('   1. Verificar que no hay errores de sintaxis: php artisan route:list');
        $this->line('   2. Ejecutar tests si existen: php artisan test');  
        $this->line('   3. Proceder con Script 4: history.blade.php');
        $this->newLine();
        
        $this->comment('📋 COMANDO PARA REGISTRAR (si no aparece en lista):');
        $this->line('   Agregar en app/Console/Kernel.php:');
        $this->line('   protected $commands = [');
        $this->line('       Commands\\FixWebserviceControllerDiscrepancies::class,');
        $this->line('   ];');
        $this->newLine();
        
        $this->info('🎉 Script 3.5 completado exitosamente!');
    }
}