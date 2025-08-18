<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Voyage;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * COMANDO: MigrateVoyageWebserviceStatuses
 * 
 * Migra los campos únicos argentina_status/paraguay_status 
 * al nuevo sistema de estados independientes por webservice.
 * 
 * FUNCIONALIDAD:
 * - Convierte argentina_status → múltiples estados (anticipada, micdta, desconsolidado, transbordo)
 * - Convierte paraguay_status → estado manifiesto
 * - Preserva fechas y referencias de webservice_transactions existentes
 * - Mantiene integridad de datos durante la migración
 * - Permite rollback seguro
 * 
 * USO:
 * php artisan migrate:voyage-webservice-statuses
 * php artisan migrate:voyage-webservice-statuses --dry-run (simulación)
 * php artisan migrate:voyage-webservice-statuses --rollback (revertir)
 */
class MigrateVoyageWebserviceStatuses extends Command
{
    protected $signature = 'migrate:voyage-webservice-statuses 
                            {--dry-run : Simular migración sin hacer cambios}
                            {--rollback : Revertir migración}
                            {--batch-size=100 : Tamaño del lote para procesamiento}';

    protected $description = 'Migrar campos argentina_status/paraguay_status al nuevo sistema de estados por webservice';

    private int $processedCount = 0;
    private int $errorCount = 0;
    private array $migrationLog = [];

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        $this->info('🚀 Iniciando migración de estados de webservice...');
        $this->newLine();

        try {
            if ($this->option('rollback')) {
                return $this->handleRollback();
            }

            return $this->handleMigration();

        } catch (Exception $e) {
            $this->error("❌ Error durante la migración: {$e->getMessage()}");
            $this->line("Stack trace: {$e->getTraceAsString()}");
            return 1;
        }
    }

    /**
     * Manejar migración principal
     */
    private function handleMigration(): int
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($isDryRun) {
            $this->warn('🧪 MODO SIMULACIÓN - No se realizarán cambios reales');
            $this->newLine();
        }

        // Verificar prerequisitos
        if (!$this->checkPrerequisites()) {
            return 1;
        }

        // Obtener voyages que necesitan migración
        $voyagesToMigrate = $this->getVoyagesToMigrate();
        $totalVoyages = $voyagesToMigrate->count();

        if ($totalVoyages === 0) {
            $this->info('✅ No hay voyages para migrar. Todos los estados ya están actualizados.');
            return 0;
        }

        $this->info("📊 Encontrados {$totalVoyages} voyages para migrar");
        $this->newLine();

        if (!$isDryRun) {
            if (!$this->confirm('¿Continuar con la migración?')) {
                $this->info('❌ Migración cancelada por el usuario');
                return 0;
            }
        }

        // Procesar en lotes
        $progressBar = $this->output->createProgressBar($totalVoyages);
        $progressBar->start();

        $voyagesToMigrate->chunk($batchSize, function ($voyages) use ($isDryRun, $progressBar) {
            if (!$isDryRun) {
                DB::beginTransaction();
            }

            try {
                foreach ($voyages as $voyage) {
                    $this->migrateVoyage($voyage, $isDryRun);
                    $progressBar->advance();
                }

                if (!$isDryRun) {
                    DB::commit();
                }

            } catch (Exception $e) {
                if (!$isDryRun) {
                    DB::rollBack();
                }
                
                $this->migrationLog[] = [
                    'type' => 'error',
                    'message' => "Error en lote: {$e->getMessage()}",
                    'voyage_ids' => $voyages->pluck('id')->toArray()
                ];
                
                $this->errorCount += $voyages->count();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->showMigrationSummary($isDryRun);

        return $this->errorCount > 0 ? 1 : 0;
    }

    /**
     * Verificar prerequisitos
     */
    private function checkPrerequisites(): bool
    {
        // 1. Verificar que existe la nueva tabla
        if (!DB::getSchemaBuilder()->hasTable('voyage_webservice_statuses')) {
            $this->error('❌ La tabla voyage_webservice_statuses no existe. Ejecuta la migración primero.');
            return false;
        }

        // 2. Verificar que existe la tabla voyages con los campos antiguos
        if (!DB::getSchemaBuilder()->hasColumn('voyages', 'argentina_status')) {
            $this->warn('⚠️  El campo argentina_status no existe en la tabla voyages. Puede que ya esté migrado.');
        }

        // 3. Verificar integridad de webservice_transactions
        $orphanTransactions = WebserviceTransaction::whereNotNull('voyage_id')
            ->whereDoesntHave('voyage')
            ->count();

        if ($orphanTransactions > 0) {
            $this->warn("⚠️  Encontradas {$orphanTransactions} transacciones huérfanas sin voyage asociado");
        }

        return true;
    }

    /**
     * Obtener voyages que necesitan migración
     */
    private function getVoyagesToMigrate()
    {
        return Voyage::select(['id', 'company_id', 'voyage_number', 'argentina_status', 'paraguay_status', 'argentina_sent_at', 'paraguay_sent_at'])
            ->with(['webserviceTransactions' => function ($query) {
                $query->select(['id', 'voyage_id', 'webservice_type', 'country', 'status', 'confirmation_number', 'external_reference', 'sent_at', 'response_at', 'created_at']);
            }])
            ->where(function ($query) {
                $query->whereNotNull('argentina_status')
                      ->orWhereNotNull('paraguay_status')
                      ->orWhereHas('webserviceTransactions');
            })
            ->whereDoesntHave('webserviceStatuses'); // Solo voyages sin estados migrados
    }

    /**
     * Migrar un voyage específico
     */
    private function migrateVoyage(Voyage $voyage, bool $isDryRun): void
    {
        $this->migrationLog[] = [
            'type' => 'start',
            'voyage_id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'argentina_status' => $voyage->argentina_status,
            'paraguay_status' => $voyage->paraguay_status,
        ];

        try {
            // 1. Migrar estados de Argentina
            if ($voyage->argentina_status) {
                $this->migrateArgentinaStatus($voyage, $isDryRun);
            }

            // 2. Migrar estados de Paraguay
            if ($voyage->paraguay_status) {
                $this->migrateParaguayStatus($voyage, $isDryRun);
            }

            // 3. Migrar desde webservice_transactions existentes
            $this->migrateFromTransactions($voyage, $isDryRun);

            // 4. Crear estados pendientes basados en roles de empresa
            $this->createPendingStates($voyage, $isDryRun);

            $this->processedCount++;

        } catch (Exception $e) {
            $this->errorCount++;
            $this->migrationLog[] = [
                'type' => 'error',
                'voyage_id' => $voyage->id,
                'message' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Migrar estado de Argentina
     */
    private function migrateArgentinaStatus(Voyage $voyage, bool $isDryRun): void
    {
        $status = $this->mapOldStatusToNew($voyage->argentina_status);
        
        // Argentina puede tener múltiples webservices, crear todos como pendientes inicialmente
        $argentineWebservices = ['anticipada', 'micdta'];
        
        // Si la empresa tiene roles específicos, agregar esos webservices
        $company = $voyage->company;
        if ($company && $company->hasRole('desconsolidador')) {
            $argentineWebservices[] = 'desconsolidado';
        }
        if ($company && $company->hasRole('transbordos')) {
            $argentineWebservices[] = 'transbordo';
        }

        foreach ($argentineWebservices as $webserviceType) {
            $data = [
                'company_id' => $voyage->company_id,
                'voyage_id' => $voyage->id,
                'country' => 'AR',
                'webservice_type' => $webserviceType,
                'status' => $status,
                'can_send' => in_array($status, ['pending', 'error']),
                'is_required' => true,
                'retry_count' => 0,
                'max_retries' => 3,
            ];

            // Agregar fechas si existen
            if ($voyage->argentina_sent_at) {
                $data['first_sent_at'] = $voyage->argentina_sent_at;
                $data['last_sent_at'] = $voyage->argentina_sent_at;
            }

            if ($status === 'approved') {
                $data['approved_at'] = $voyage->argentina_sent_at; // Aproximación
            }

            if (!$isDryRun) {
                VoyageWebserviceStatus::create($data);
            }

            $this->migrationLog[] = [
                'type' => 'created',
                'voyage_id' => $voyage->id,
                'country' => 'AR',
                'webservice_type' => $webserviceType,
                'status' => $status,
                'dry_run' => $isDryRun,
            ];
        }
    }

    /**
     * Migrar estado de Paraguay
     */
    private function migrateParaguayStatus(Voyage $voyage, bool $isDryRun): void
    {
        $status = $this->mapOldStatusToNew($voyage->paraguay_status);
        
        $data = [
            'company_id' => $voyage->company_id,
            'voyage_id' => $voyage->id,
            'country' => 'PY',
            'webservice_type' => 'manifiesto',
            'status' => $status,
            'can_send' => in_array($status, ['pending', 'error']),
            'is_required' => true,
            'retry_count' => 0,
            'max_retries' => 3,
        ];

        // Agregar fechas si existen
        if ($voyage->paraguay_sent_at) {
            $data['first_sent_at'] = $voyage->paraguay_sent_at;
            $data['last_sent_at'] = $voyage->paraguay_sent_at;
        }

        if ($status === 'approved') {
            $data['approved_at'] = $voyage->paraguay_sent_at; // Aproximación
        }

        if (!$isDryRun) {
            VoyageWebserviceStatus::create($data);
        }

        $this->migrationLog[] = [
            'type' => 'created',
            'voyage_id' => $voyage->id,
            'country' => 'PY',
            'webservice_type' => 'manifiesto',
            'status' => $status,
            'dry_run' => $isDryRun,
        ];
    }

    /**
     * Migrar desde transacciones existentes
     */
    private function migrateFromTransactions(Voyage $voyage, bool $isDryRun): void
    {
        foreach ($voyage->webserviceTransactions as $transaction) {
            // Verificar si ya existe un estado para este webservice
            $existingStatus = !$isDryRun ? VoyageWebserviceStatus::where([
                'voyage_id' => $voyage->id,
                'country' => $transaction->country,
                'webservice_type' => $transaction->webservice_type,
            ])->first() : null;

            if ($existingStatus && !$isDryRun) {
                // Actualizar con datos de la transacción
                $this->updateStatusFromTransaction($existingStatus, $transaction);
            } elseif (!$existingStatus) {
                // Crear nuevo estado desde transacción
                $this->createStatusFromTransaction($voyage, $transaction, $isDryRun);
            }
        }
    }

    /**
     * Crear estados pendientes basados en roles de empresa
     */
    private function createPendingStates(Voyage $voyage, bool $isDryRun): void
    {
        $company = $voyage->company;
        if (!$company) return;

        $roles = $company->getRoles() ?? [];
        
        // Mapear roles a webservices
        $roleWebserviceMap = [
            'cargas' => [
                ['country' => 'AR', 'type' => 'anticipada'],
                ['country' => 'AR', 'type' => 'micdta'],
            ],
            'desconsolidador' => [
                ['country' => 'AR', 'type' => 'desconsolidado'],
            ],
            'transbordos' => [
                ['country' => 'AR', 'type' => 'transbordo'],
                ['country' => 'PY', 'type' => 'transbordo'],
            ],
        ];

        foreach ($roles as $role) {
            if (!isset($roleWebserviceMap[$role])) continue;

            foreach ($roleWebserviceMap[$role] as $webservice) {
                // Verificar si ya existe
                $exists = !$isDryRun ? VoyageWebserviceStatus::where([
                    'voyage_id' => $voyage->id,
                    'country' => $webservice['country'],
                    'webservice_type' => $webservice['type'],
                ])->exists() : false;

                if (!$exists) {
                    $data = [
                        'company_id' => $voyage->company_id,
                        'voyage_id' => $voyage->id,
                        'country' => $webservice['country'],
                        'webservice_type' => $webservice['type'],
                        'status' => 'pending',
                        'can_send' => true,
                        'is_required' => true,
                        'retry_count' => 0,
                        'max_retries' => 3,
                    ];

                    if (!$isDryRun) {
                        VoyageWebserviceStatus::create($data);
                    }

                    $this->migrationLog[] = [
                        'type' => 'created_from_role',
                        'voyage_id' => $voyage->id,
                        'country' => $webservice['country'],
                        'webservice_type' => $webservice['type'],
                        'role' => $role,
                        'dry_run' => $isDryRun,
                    ];
                }
            }
        }
    }

    /**
     * Actualizar estado desde transacción
     */
    private function updateStatusFromTransaction(VoyageWebserviceStatus $status, WebserviceTransaction $transaction): void
    {
        $updates = [];

        // Actualizar estado basado en transacción
        if ($transaction->status === 'success' && $status->status !== 'approved') {
            $updates['status'] = 'approved';
            $updates['approved_at'] = $transaction->response_at;
        }

        // Actualizar referencias
        if ($transaction->confirmation_number) {
            $updates['confirmation_number'] = $transaction->confirmation_number;
        }

        if ($transaction->external_reference) {
            $updates['external_voyage_number'] = $transaction->external_reference;
        }

        // Actualizar fechas
        if ($transaction->sent_at) {
            $updates['first_sent_at'] = $updates['first_sent_at'] ?? $transaction->sent_at;
            $updates['last_sent_at'] = $transaction->sent_at;
        }

        if (!empty($updates)) {
            $status->update($updates);
        }
    }

    /**
     * Crear estado desde transacción
     */
    private function createStatusFromTransaction(Voyage $voyage, WebserviceTransaction $transaction, bool $isDryRun): void
    {
        $status = $this->mapTransactionStatusToNew($transaction->status);
        
        $data = [
            'company_id' => $voyage->company_id,
            'voyage_id' => $voyage->id,
            'country' => $transaction->country,
            'webservice_type' => $transaction->webservice_type,
            'status' => $status,
            'can_send' => in_array($status, ['pending', 'error']),
            'is_required' => true,
            'last_transaction_id' => $transaction->transaction_id,
            'confirmation_number' => $transaction->confirmation_number,
            'external_voyage_number' => $transaction->external_reference,
            'retry_count' => $transaction->retry_count ?? 0,
            'max_retries' => $transaction->max_retries ?? 3,
        ];

        // Agregar fechas
        if ($transaction->sent_at) {
            $data['first_sent_at'] = $transaction->sent_at;
            $data['last_sent_at'] = $transaction->sent_at;
        }

        if ($transaction->response_at && $status === 'approved') {
            $data['approved_at'] = $transaction->response_at;
        }

        if (!$isDryRun) {
            VoyageWebserviceStatus::create($data);
        }

        $this->migrationLog[] = [
            'type' => 'created_from_transaction',
            'voyage_id' => $voyage->id,
            'transaction_id' => $transaction->id,
            'country' => $transaction->country,
            'webservice_type' => $transaction->webservice_type,
            'status' => $status,
            'dry_run' => $isDryRun,
        ];
    }

    /**
     * Mapear estado antiguo a nuevo
     */
    private function mapOldStatusToNew(string $oldStatus): string
    {
        return match($oldStatus) {
            'sent' => 'sent',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'error' => 'error',
            'pending' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Mapear estado de transacción a nuevo
     */
    private function mapTransactionStatusToNew(string $transactionStatus): string
    {
        return match($transactionStatus) {
            'success' => 'approved',
            'sent' => 'sent',
            'error' => 'error',
            'retry' => 'retry',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'pending',
        };
    }

    /**
     * Mostrar resumen de migración
     */
    private function showMigrationSummary(bool $isDryRun): void
    {
        $this->info('📊 RESUMEN DE MIGRACIÓN:');
        $this->line("   - Voyages procesados: {$this->processedCount}");
        $this->line("   - Errores: {$this->errorCount}");
        
        $createdCount = collect($this->migrationLog)->where('type', 'created')->count();
        $updatedCount = collect($this->migrationLog)->where('type', 'updated')->count();
        
        $this->line("   - Estados creados: {$createdCount}");
        $this->line("   - Estados actualizados: {$updatedCount}");
        
        if ($isDryRun) {
            $this->warn('🧪 SIMULACIÓN COMPLETADA - No se realizaron cambios reales');
        } else {
            $this->info('✅ MIGRACIÓN COMPLETADA');
        }
        
        if ($this->errorCount > 0) {
            $this->error("⚠️  Se encontraron {$this->errorCount} errores durante la migración");
        }
    }

    /**
     * Manejar rollback
     */
    private function handleRollback(): int
    {
        $this->warn('🔄 INICIANDO ROLLBACK...');
        
        if (!$this->confirm('¿Estás seguro de que quieres eliminar todos los estados migrados?')) {
            $this->info('❌ Rollback cancelado');
            return 0;
        }

        try {
            $deletedCount = VoyageWebserviceStatus::count();
            VoyageWebserviceStatus::truncate();
            
            $this->info("✅ Rollback completado. Se eliminaron {$deletedCount} estados.");
            return 0;
            
        } catch (Exception $e) {
            $this->error("❌ Error durante rollback: {$e->getMessage()}");
            return 1;
        }
    }
}