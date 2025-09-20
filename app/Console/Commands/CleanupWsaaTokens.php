<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WsaaToken;

class CleanupWsaaTokens extends Command
{
    /**
     * Comando para limpiar tokens WSAA antiguos
     */
    protected $signature = 'wsaa:cleanup 
                           {--dry-run : Mostrar qué se eliminaría sin ejecutar}
                           {--company= : Limpiar solo una empresa específica}';

    protected $description = 'Limpiar tokens WSAA antiguos según política de retención';

    /**
     * Ejecutar comando
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $companyId = $this->option('company');
        
        $this->info('🧹 Iniciando limpieza de tokens WSAA...');
        
        if ($isDryRun) {
            $this->warn('🔍 MODO DRY-RUN: Solo mostrando qué se eliminaría');
        }
        
        try {
            // Construir query base
            $query = WsaaToken::where(function($q) {
                $q->where(function($subq) {
                    // Tokens expirados > 7 días
                    $subq->where('status', 'expired')
                        ->where('updated_at', '<', now()->subDays(7));
                })->orWhere(function($subq) {
                    // Tokens revocados > 24 horas  
                    $subq->where('status', 'revoked')
                        ->where('updated_at', '<', now()->subHours(24));
                })->orWhere(function($subq) {
                    // Tokens con error > 24 horas
                    $subq->where('status', 'error')
                        ->where('updated_at', '<', now()->subHours(24));
                });
            });
            
            // Filtrar por empresa si se especifica
            if ($companyId) {
                $query->where('company_id', $companyId);
                $this->info("🔍 Filtrando solo empresa ID: {$companyId}");
            }
            
            // Obtener tokens a eliminar
            $tokensToDelete = $query->get();
            $count = $tokensToDelete->count();
            
            if ($count === 0) {
                $this->info('✅ No hay tokens antiguos para limpiar');
                return 0;
            }
            
            // Mostrar estadísticas
            $this->info("📊 Tokens encontrados para eliminar: {$count}");
            $this->table(['Empresa', 'Servicio', 'Ambiente', 'Estado', 'Actualizado'], 
                $tokensToDelete->map(function($token) {
                    return [
                        $token->company_id,
                        $token->service_name,
                        $token->environment,
                        $token->status,
                        $token->updated_at->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );
            
            if ($isDryRun) {
                $this->warn("🔍 DRY-RUN: Se eliminarían {$count} tokens");
                return 0;
            }
            
            // Confirmar eliminación
            if (!$this->confirm("¿Eliminar {$count} tokens antiguos?")) {
                $this->info('❌ Operación cancelada');
                return 1;
            }
            
            // Ejecutar eliminación
            $deleted = $query->delete();
            
            $this->info("✅ Limpieza completada: {$deleted} tokens eliminados");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error en limpieza: ' . $e->getMessage());
            return 1;
        }
    }
}