<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class SystemController extends Controller
{
    /**
     * Vista de configuración del sistema.
     */
    public function settings()
    {
        // Configuración general del sistema
        $generalSettings = [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone'),
            'app_locale' => config('app.locale'),
        ];

        // Configuración de la base de datos
        $databaseSettings = [
            'connection' => config('database.default'),
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
        ];

        // Configuración de cache
        $cacheSettings = [
            'default_driver' => config('cache.default'),
            'stores' => config('cache.stores'),
        ];

        // Configuración de sesión
        $sessionSettings = [
            'driver' => config('session.driver'),
            'lifetime' => config('session.lifetime'),
            'secure' => config('session.secure'),
            'http_only' => config('session.http_only'),
        ];

        // Estado del sistema
        $systemStatus = $this->getSystemStatus();

        // Información del servidor
        $serverInfo = $this->getServerInfo();

        // Espacio en disco
        $diskSpace = $this->getDiskSpace();

        return view('admin.system.settings', compact(
            'generalSettings',
            'databaseSettings',
            'cacheSettings',
            'sessionSettings',
            'systemStatus',
            'serverInfo',
            'diskSpace'
        ));
    }

    /**
     * Actualizar configuraciones del sistema.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_timezone' => 'required|string',
            'app_locale' => 'required|string|in:es,en',
            'session_lifetime' => 'required|integer|min:1|max:1440',
        ]);

        try {
            // Aquí iría la lógica para actualizar el archivo .env
            // Por seguridad, solo actualizamos configuraciones permitidas

            // Actualizar cache de configuración
            Cache::put('app.name', $request->app_name, now()->addHours(24));
            Cache::put('app.timezone', $request->app_timezone, now()->addHours(24));
            Cache::put('app.locale', $request->app_locale, now()->addHours(24));

            return back()->with('success', 'Configuración actualizada correctamente.');

        } catch (Exception $e) {
            return back()->with('error', 'Error al actualizar la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Vista de mantenimiento del sistema.
     */
    public function maintenance()
    {
        $maintenanceMode = app()->isDownForMaintenance();

        // Información del último mantenimiento
        $lastMaintenance = Cache::get('system.last_maintenance');

        // Tareas de mantenimiento programadas
        $scheduledTasks = [
            'cache_clear' => 'Limpiar cache del sistema',
            'optimize' => 'Optimizar aplicación',
            'backup' => 'Crear backup de base de datos',
            'logs_clean' => 'Limpiar logs antiguos',
        ];

        // Estadísticas de mantenimiento
        $maintenanceStats = [
            'cache_size' => $this->getCacheSize(),
            'logs_count' => $this->getLogsCount(),
            'temp_files' => $this->getTempFilesCount(),
            'last_backup' => $this->getLastBackupDate(),
        ];

        return view('admin.system.maintenance', compact(
            'maintenanceMode',
            'lastMaintenance',
            'scheduledTasks',
            'maintenanceStats'
        ));
    }

    /**
     * Activar modo mantenimiento.
     */
    public function enableMaintenance(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string|max:500',
            'retry' => 'nullable|integer|min:60|max:7200',
        ]);

        try {
            $options = [];

            if ($request->filled('message')) {
                $options['message'] = $request->message;
            }

            if ($request->filled('retry')) {
                $options['retry'] = $request->retry;
            }

            Artisan::call('down', $options);

            Cache::put('system.maintenance_enabled_at', now());
            Cache::put('system.maintenance_enabled_by', auth()->user()->name);

            return back()->with('success', 'Modo mantenimiento activado correctamente.');

        } catch (Exception $e) {
            return back()->with('error', 'Error al activar modo mantenimiento: ' . $e->getMessage());
        }
    }

    /**
     * Desactivar modo mantenimiento.
     */
    public function disableMaintenance()
    {
        try {
            Artisan::call('up');

            Cache::put('system.last_maintenance', [
                'ended_at' => now(),
                'duration' => Cache::get('system.maintenance_enabled_at') ?
                    now()->diffInMinutes(Cache::get('system.maintenance_enabled_at')) : 0,
                'ended_by' => auth()->user()->name,
            ]);

            Cache::forget('system.maintenance_enabled_at');
            Cache::forget('system.maintenance_enabled_by');

            return back()->with('success', 'Modo mantenimiento desactivado correctamente.');

        } catch (Exception $e) {
            return back()->with('error', 'Error al desactivar modo mantenimiento: ' . $e->getMessage());
        }
    }

    /**
     * Vista de auditoría del sistema.
     */
    public function audit()
    {
        // Auditoría de usuarios (últimos cambios)
        $userAudit = DB::table('users')
            ->select('name', 'email', 'created_at', 'last_access', 'active')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        // Auditoría de empresas (últimos cambios)
        $companyAudit = DB::table('companies')
            ->select('legal_name', 'tax_id', 'created_date', 'last_access', 'active')
            ->orderBy('created_date', 'desc')
            ->take(20)
            ->get();

        // Estadísticas de base de datos
        $dbStats = [
            'users_count' => DB::table('users')->count(),
            'companies_count' => DB::table('companies')->count(),
            'operators_count' => DB::table('operators')->count(),
            'roles_count' => DB::table('roles')->count(),
            'permissions_count' => DB::table('permissions')->count(),
        ];

        // Tamaño de tablas principales
        $tableSizes = $this->getTableSizes();

        // Conexiones activas
        $activeConnections = $this->getActiveConnections();

        // Integridad de datos
        $dataIntegrity = $this->checkDataIntegrity();

        return view('admin.system.audit', compact(
            'userAudit',
            'companyAudit',
            'dbStats',
            'tableSizes',
            'activeConnections',
            'dataIntegrity'
        ));
    }

    /**
     * Vista de logs del sistema.
     */
    public function logs(Request $request)
    {
        $logFile = $request->get('file', 'laravel.log');
        $lines = $request->get('lines', 100);

        try {
            $logPath = storage_path('logs/' . $logFile);

            if (!File::exists($logPath)) {
                throw new Exception('Archivo de log no encontrado');
            }

            // Leer las últimas líneas del archivo
            $logContent = $this->readLastLines($logPath, $lines);

            // Procesar logs para mostrar de forma estructurada
            $logs = $this->parseLogs($logContent);

            // Lista de archivos de log disponibles
            $logFiles = collect(File::files(storage_path('logs')))
                ->map(function ($file) {
                    return [
                        'name' => $file->getFilename(),
                        'size' => $this->formatBytes($file->getSize()),
                        'modified' => Carbon::createFromTimestamp($file->getMTime()),
                    ];
                })
                ->sortByDesc('modified');

            return view('admin.system.logs', compact('logs', 'logFiles', 'logFile', 'lines'));

        } catch (Exception $e) {
            return back()->with('error', 'Error al leer logs: ' . $e->getMessage());
        }
    }

    /**
     * Limpiar logs del sistema.
     */
    public function clearLogs(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'string',
        ]);

        try {
            $cleared = 0;

            foreach ($request->files as $file) {
                $logPath = storage_path('logs/' . $file);

                if (File::exists($logPath) && File::isFile($logPath)) {
                    File::put($logPath, '');
                    $cleared++;
                }
            }

            return back()->with('success', "Se limpiaron {$cleared} archivo(s) de log correctamente.");

        } catch (Exception $e) {
            return back()->with('error', 'Error al limpiar logs: ' . $e->getMessage());
        }
    }

    /**
     * Vista de gestión de backups.
     */
    public function backups()
    {
        // Lista de backups existentes
        $backups = collect(Storage::disk('local')->files('backups'))
            ->filter(function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
            })
            ->map(function ($file) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes(Storage::disk('local')->size($file)),
                    'created' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file)),
                ];
            })
            ->sortByDesc('created');

        // Configuración de backups
        $backupConfig = [
            'auto_backup' => config('backup.auto_backup', false),
            'retention_days' => config('backup.retention_days', 30),
            'max_backups' => config('backup.max_backups', 10),
        ];

        // Estadísticas de backups
        $backupStats = [
            'total_backups' => $backups->count(),
            'total_size' => $this->formatBytes($backups->sum(function($backup) {
                return Storage::disk('local')->size($backup['path']);
            })),
            'oldest_backup' => $backups->last()['created'] ?? null,
            'newest_backup' => $backups->first()['created'] ?? null,
        ];

        return view('admin.system.backups', compact('backups', 'backupConfig', 'backupStats'));
    }

    /**
     * Crear backup de la base de datos.
     */
    public function createBackup()
    {
        try {
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/' . $filename);

            // Crear directorio si no existe
            if (!File::exists(storage_path('app/backups'))) {
                File::makeDirectory(storage_path('app/backups'), 0755, true);
            }

            // Ejecutar mysqldump
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                config('database.connections.mysql.database'),
                $backupPath
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Error al ejecutar mysqldump');
            }

            // Verificar que el archivo se creó correctamente
            if (!File::exists($backupPath) || File::size($backupPath) === 0) {
                throw new Exception('El backup no se creó correctamente');
            }

            return back()->with('success', 'Backup creado correctamente: ' . $filename);

        } catch (Exception $e) {
            return back()->with('error', 'Error al crear backup: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar backup.
     */
    public function deleteBackup($backup)
    {
        try {
            $backupPath = 'backups/' . $backup;

            if (!Storage::disk('local')->exists($backupPath)) {
                throw new Exception('Backup no encontrado');
            }

            Storage::disk('local')->delete($backupPath);

            return back()->with('success', 'Backup eliminado correctamente.');

        } catch (Exception $e) {
            return back()->with('error', 'Error al eliminar backup: ' . $e->getMessage());
        }
    }

    /**
     * Vista de comandos del sistema.
     */
    public function commands()
    {
        // Comandos disponibles
        $availableCommands = [
            'cache:clear' => 'Limpiar cache de la aplicación',
            'config:clear' => 'Limpiar cache de configuración',
            'route:clear' => 'Limpiar cache de rutas',
            'view:clear' => 'Limpiar cache de vistas',
            'optimize' => 'Optimizar aplicación',
            'optimize:clear' => 'Limpiar optimizaciones',
            'queue:restart' => 'Reiniciar workers de cola',
            'storage:link' => 'Crear enlace simbólico de storage',
        ];

        // Últimos comandos ejecutados
        $commandHistory = Cache::get('system.command_history', []);

        // Estado de los servicios
        $serviceStatus = [
            'cache' => $this->checkCacheStatus(),
            'database' => $this->checkDatabaseStatus(),
            'storage' => $this->checkStorageStatus(),
            'queue' => $this->checkQueueStatus(),
        ];

        return view('admin.system.commands', compact(
            'availableCommands',
            'commandHistory',
            'serviceStatus'
        ));
    }

    /**
     * Ejecutar comando de verificación de usuarios.
     */
    public function verifyUsers()
    {
        try {
            Artisan::call('users:verify');
            $output = Artisan::output();

            $this->logCommand('users:verify', $output, true);

            return back()->with('success', 'Comando ejecutado correctamente.')
                        ->with('command_output', $output);

        } catch (Exception $e) {
            $this->logCommand('users:verify', $e->getMessage(), false);
            return back()->with('error', 'Error al ejecutar comando: ' . $e->getMessage());
        }
    }

    /**
     * Optimizar sistema.
     */
    public function optimize()
    {
        try {
            $commands = ['config:cache', 'route:cache', 'view:cache', 'optimize'];
            $outputs = [];

            foreach ($commands as $command) {
                Artisan::call($command);
                $outputs[$command] = Artisan::output();
            }

            $this->logCommand('optimize', 'Sistema optimizado correctamente', true);

            return back()->with('success', 'Sistema optimizado correctamente.')
                        ->with('command_outputs', $outputs);

        } catch (Exception $e) {
            $this->logCommand('optimize', $e->getMessage(), false);
            return back()->with('error', 'Error al optimizar sistema: ' . $e->getMessage());
        }
    }

    // Métodos auxiliares privados

    private function getSystemStatus()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'cache_status' => $this->checkCacheStatus(),
            'database_status' => $this->checkDatabaseStatus(),
        ];
    }

    private function getServerInfo()
    {
        return [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }

    private function getDiskSpace()
    {
        $bytes = disk_free_space('/');
        $total = disk_total_space('/');

        return [
            'free' => $this->formatBytes($bytes),
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($total - $bytes),
            'percentage' => round((($total - $bytes) / $total) * 100, 2),
        ];
    }

    private function getCacheSize()
    {
        try {
            $cacheDir = storage_path('framework/cache');
            return $this->formatBytes($this->getDirectorySize($cacheDir));
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function getLogsCount()
    {
        try {
            return count(File::files(storage_path('logs')));
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getTempFilesCount()
    {
        try {
            return count(File::files(storage_path('framework/sessions')));
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getLastBackupDate()
    {
        try {
            $backups = Storage::disk('local')->files('backups');
            if (empty($backups)) {
                return null;
            }

            $latest = collect($backups)->map(function($file) {
                return Storage::disk('local')->lastModified($file);
            })->max();

            return Carbon::createFromTimestamp($latest);
        } catch (Exception $e) {
            return null;
        }
    }

    private function getTableSizes()
    {
        try {
            return DB::select("
                SELECT
                    table_name AS 'table',
                    round(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
                FROM information_schema.tables
                WHERE table_schema = ?
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ", [config('database.connections.mysql.database')]);
        } catch (Exception $e) {
            return [];
        }
    }

    private function getActiveConnections()
    {
        try {
            return DB::select("SHOW PROCESSLIST");
        } catch (Exception $e) {
            return [];
        }
    }

    private function checkDataIntegrity()
    {
        $issues = [];

        try {
            // Verificar usuarios sin roles
            $usersWithoutRoles = DB::table('users')
                ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->whereNull('model_has_roles.model_id')
                ->count();

            if ($usersWithoutRoles > 0) {
                $issues[] = "{$usersWithoutRoles} usuarios sin roles asignados";
            }

            // Verificar empresas sin operadores
            $companiesWithoutOperators = DB::table('companies')
                ->leftJoin('operators', 'companies.id', '=', 'operators.company_id')
                ->whereNull('operators.company_id')
                ->count();

            if ($companiesWithoutOperators > 0) {
                $issues[] = "{$companiesWithoutOperators} empresas sin operadores";
            }

        } catch (Exception $e) {
            $issues[] = "Error al verificar integridad: " . $e->getMessage();
        }

        return $issues;
    }

    private function readLastLines($file, $lines)
    {
        $handle = fopen($file, 'r');
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }

        fclose($handle);
        return array_reverse($text);
    }

    private function parseLogs($logContent)
    {
        $logs = [];

        foreach ($logContent as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                ];
            }
        }

        return $logs;
    }

    private function checkCacheStatus()
    {
        try {
            Cache::put('test_key', 'test_value', 60);
            $value = Cache::get('test_key');
            Cache::forget('test_key');
            return $value === 'test_value' ? 'OK' : 'ERROR';
        } catch (Exception $e) {
            return 'ERROR';
        }
    }

    private function checkDatabaseStatus()
    {
        try {
            DB::connection()->getPdo();
            return 'OK';
        } catch (Exception $e) {
            return 'ERROR';
        }
    }

    private function checkStorageStatus()
    {
        try {
            $testFile = 'test_' . time() . '.txt';
            Storage::disk('local')->put($testFile, 'test');
            $exists = Storage::disk('local')->exists($testFile);
            Storage::disk('local')->delete($testFile);
            return $exists ? 'OK' : 'ERROR';
        } catch (Exception $e) {
            return 'ERROR';
        }
    }

    private function checkQueueStatus()
    {
        try {
            // Verificar si hay workers corriendo
            $output = shell_exec('ps aux | grep "queue:work" | grep -v grep');
            return !empty($output) ? 'OK' : 'STOPPED';
        } catch (Exception $e) {
            return 'UNKNOWN';
        }
    }

    private function getDirectorySize($directory)
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function logCommand($command, $output, $success)
    {
        $history = Cache::get('system.command_history', []);

        array_unshift($history, [
            'command' => $command,
            'output' => $output,
            'success' => $success,
            'executed_at' => now(),
            'executed_by' => auth()->user()->name,
        ]);

        // Mantener solo los últimos 50 comandos
        $history = array_slice($history, 0, 50);

        Cache::put('system.command_history', $history, now()->addDays(7));
    }
}
