<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class OptimizeSystemCommand extends Command
{
    protected $signature = 'system:optimize';
    protected $description = 'Optimizar el sistema completo (cache, configuración, permisos)';

    public function handle()
    {
        $this->info('🚀 Optimizando el sistema...');
        $this->line('');

        // Limpiar todas las cachés
        $this->info('🧹 Limpiando cachés...');
        $this->call('optimize:clear');
        $this->info('  ✅ Cachés limpiadas');
        $this->line('');

        // Limpiar caché de permisos específicamente
        $this->info('🔐 Limpiando caché de permisos...');
        $this->call('permission:cache-reset');
        $this->info('  ✅ Caché de permisos limpiada');
        $this->line('');

        // Regenerar caché de configuración
        $this->info('⚙️ Regenerando caché de configuración...');
        $this->call('config:cache');
        $this->info('  ✅ Caché de configuración regenerada');
        $this->line('');

        // Regenerar caché de rutas
        $this->info('🛣️ Regenerando caché de rutas...');
        $this->call('route:cache');
        $this->info('  ✅ Caché de rutas regenerada');
        $this->line('');

        // Regenerar caché de vistas
        $this->info('👁️ Regenerando caché de vistas...');
        $this->call('view:cache');
        $this->info('  ✅ Caché de vistas regenerada');
        $this->line('');

        // Optimizar autoloader
        $this->info('📦 Optimizando autoloader...');
        $this->call('optimize');
        $this->info('  ✅ Autoloader optimizado');
        $this->line('');

        // Verificar estado del sistema
        $this->info('🔍 Verificando estado del sistema...');
        $this->call('about');
        $this->line('');

        $this->info('✅ Sistema optimizado correctamente');
        $this->line('');

        $this->info('💡 Recomendaciones adicionales:');
        $this->info('  - Reiniciar el servidor: php artisan serve');
        $this->info('  - Verificar permisos: php artisan permissions:verify');
        $this->info('  - Probar relaciones: php artisan test:relationships');

        return Command::SUCCESS;
    }
}
