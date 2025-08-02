<?php

namespace App\Traits;

use App\Models\Company;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

trait UserHelper
{
    /**
     * Obtener el usuario autenticado actual.
     */
    protected function getCurrentUser()
    {
        return Auth::user();
    }

    /**
     * Verificar si el usuario es super admin.
     */
    protected function isSuperAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('super-admin');
    }

    /**
     * Verificar si el usuario es administrador de empresa.
     */
    protected function isCompanyAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('company-admin');
    }

    /**
     * Verificar si el usuario es usuario regular.
     */
    protected function isUser(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->hasRole('user');
    }

    /**
     * Verificar si el usuario es operador (independiente del rol).
     */
    protected function isOperator(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->userable_type === 'App\Models\Operator';
    }

    /**
     * Obtener el tipo de usuario para mostrar en la interfaz.
     */
    protected function getUserType(): string
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return 'Invitado';
        }

        if ($user->hasRole('super-admin')) {
            return 'Super Administrador';
        }

        if ($user->hasRole('company-admin')) {
            return 'Administrador de Empresa';
        }

        if ($user->hasRole('user')) {
            // Si es un operador, especificarlo
            if ($user->userable_type === 'App\Models\Operator') {
                return 'Operador';
            }
            return 'Usuario';
        }

        return 'Usuario';
    }

    /**
     * Obtener empresa del usuario según su rol.
     */
    protected function getUserCompany(): ?Company
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return null;
        }

        // Company admin: empresa directamente asociada
        if ($user->hasRole('company-admin') && $user->userable_type === 'App\Models\Company') {
            return $user->userable;
        }

        // User: empresa a través del operador
        if ($user->hasRole('user') && $user->userable_type === 'App\Models\Operator') {
            return $user->userable->company ?? null;
        }

        return null;
    }

    /**
     * Verificar si el usuario tiene un rol específico de empresa.
     */
    protected function hasCompanyRole(string $businessRole): bool
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return false;
        }

        $companyRoles = $company->company_roles ?? [];
        return in_array($businessRole, $companyRoles);
    }

    /**
     * Verificar si el usuario puede acceder a una empresa específica.
     */
    protected function canAccessCompany(int $companyId): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede acceder a todas las empresas
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $userCompany = $this->getUserCompany();

        if (!$userCompany) {
            return false;
        }

        // Puede acceder solo a su propia empresa
        return $userCompany->id === $companyId;
    }

    /**
     * Verificar si el usuario puede realizar una acción específica.
     * ⚠️ IMPORTANTE: Para reportes, usar hasCompanyRole() directamente
     */
    protected function canPerform(string $action): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        // Super admin puede hacer todo
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin puede hacer todo en su empresa
        if ($user->hasRole('company-admin')) {
            switch ($action) {
                // Acceso básico
                case 'dashboard_access':
                case 'view_reports':
                case 'access_trips':
                case 'access_shipments':
                    return true;

                // Gestión administrativa
                case 'manage_operators':
                case 'manage_certificates':
                case 'manage_settings':
                case 'manage_webservices':
                    return true;

                // Acceso a módulos específicos
                case 'access_import':
                case 'access_export':
                    return true;

                // Acciones de importación/exportación/transferencia
                case 'import':
                case 'export':
                case 'transfer':
                    return true;

                // Visualización por roles de empresa
                case 'view_cargas':
                    return $this->hasCompanyRole('Cargas');
                case 'view_deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                case 'view_transfers':
                    return $this->hasCompanyRole('Transbordos');

                // ⚠️ REPORTES: Casos específicos agregados para evitar 41 cambios
                case 'reports.manifests':
                case 'reports.bills_of_lading':
                case 'reports.micdta':
                case 'reports.arrival_notices':
                case 'reports.trips':
                    return $this->hasCompanyRole('Cargas');
                    
                case 'reports.deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                    
                case 'reports.transshipment':
                    return $this->hasCompanyRole('Transbordos');
                    
                case 'reports.customs':
                    return $this->hasCompanyRole('Cargas') || 
                           $this->hasCompanyRole('Desconsolidador') || 
                           $this->hasCompanyRole('Transbordos');
                           
                case 'reports.shipments':
                    return $this->hasCompanyRole('Cargas') || 
                           $this->hasCompanyRole('Desconsolidador');
                           
                case 'reports.operators':
                    return true; // Solo company-admin puede llegar aquí
                    
                case 'reports.export':
                    return true; // Todos los que lleguen aquí pueden exportar

                default:
                    return false;
            }
        }

        // Users tienen permisos limitados según sus roles de empresa y operador
        if ($user->hasRole('user')) {
            switch ($action) {
                // Acceso básico para users
                case 'dashboard_access':
                case 'view_reports':
                    return true;

                // Acciones de operador (requieren permisos específicos)
                case 'import':
                    return $this->isOperator() && $user->userable->can_import;
                case 'export':
                    return $this->isOperator() && $user->userable->can_export;
                case 'transfer':
                    return $this->isOperator() && $user->userable->can_transfer;

                // Acceso a módulos según permisos de operador
                case 'access_import':
                    return $this->isOperator() && $user->userable->can_import;
                case 'access_export':
                    return $this->isOperator() && $user->userable->can_export;

                // Acceso a secciones según roles de empresa
                case 'access_trips':
                case 'access_shipments':
                    return $this->hasCompanyRole('Cargas');

                case 'view_cargas':
                    return $this->hasCompanyRole('Cargas');
                case 'view_deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                case 'view_transfers':
                    return $this->hasCompanyRole('Transbordos');

                // Gestión administrativa (NO permitida para users)
                case 'manage_operators':
                case 'manage_certificates':
                case 'manage_settings':
                case 'manage_webservices':
                    return false;

                // ⚠️ REPORTES: Casos específicos para users según roles de empresa
                case 'reports.manifests':
                case 'reports.bills_of_lading':
                case 'reports.micdta':
                case 'reports.arrival_notices':
                case 'reports.trips':
                    return $this->hasCompanyRole('Cargas');
                    
                case 'reports.deconsolidation':
                    return $this->hasCompanyRole('Desconsolidador');
                    
                case 'reports.transshipment':
                    return $this->hasCompanyRole('Transbordos');
                    
                case 'reports.customs':
                    return $this->hasCompanyRole('Cargas') || 
                           $this->hasCompanyRole('Desconsolidador') || 
                           $this->hasCompanyRole('Transbordos');
                           
                case 'reports.shipments':
                    return $this->hasCompanyRole('Cargas') || 
                           $this->hasCompanyRole('Desconsolidador');
                           
                case 'reports.operators':
                    return false; // Users NO pueden ver reportes de operadores
                    
                case 'reports.export':
                    return true; // Users pueden exportar sus reportes permitidos

                // ⚠️ REPORTES: Usar hasCompanyRole() directamente en ReportController
                // NO agregar casos de reports.* aquí para mantener simplicidad

                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Obtener los roles de empresa del usuario.
     */
    protected function getUserCompanyRoles(): array
    {
        $company = $this->getUserCompany();
        return $company ? ($company->company_roles ?? []) : [];
    }

    /**
     * Obtener los permisos de operador del usuario.
     */
    protected function getUserOperatorPermissions(): array
    {
        $user = $this->getCurrentUser();

        if (!$user || $user->userable_type !== 'App\Models\Operator' || !$user->userable) {
            return [
                'can_import' => false,
                'can_export' => false,
                'can_transfer' => false,
            ];
        }

        return [
            'can_import' => $user->userable->can_import ?? false,
            'can_export' => $user->userable->can_export ?? false,
            'can_transfer' => $user->userable->can_transfer ?? false,
        ];
    }

    /**
     * Verificar si el usuario puede importar.
     */
    public function canImport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_import;
    }

    /**
     * Verificar si el usuario puede exportar.
     */
    public function canExport()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_export;
    }

    /**
     * Verificar si el usuario puede transferir.
     */
    public function canTransfer()
    {
        $operator = $this->getUserOperator();
        return $operator && $operator->can_transfer;
    }

    /**
     * Obtener el operador del usuario.
     */
    public function getUserOperator()
    {
        $user = $this->getCurrentUser();
        if ($user && $user->userable_type === 'App\Models\Operator') {
            return $user->userable;
        }
        return null;
    }

    /**
     * Verificar si el usuario tiene permisos de operador válidos.
     */
    protected function hasValidOperatorPermissions(): bool
    {
        if (!$this->isUser()) {
            return true; // Los company-admin no necesitan esta verificación
        }

        $operator = $this->getUserOperator();
        if (!$operator) {
            return false; // Los users deben tener un operador asociado
        }

        // Verificar que el operador esté activo
        if (!$operator->active) {
            return false;
        }

        // Verificar que tenga al menos un permiso
        return $operator->can_import || $operator->can_export || $operator->can_transfer;
    }

    /**
     * Obtener estado del certificado de la empresa.
     */
    protected function getCertificateStatus(Company $company): array
    {
        if (!$company->certificate_path) {
            return [
                'has_certificate' => false,
                'is_expired' => false,
                'expires_at' => null,
                'needs_renewal' => true,
                'days_until_expiry' => null,
            ];
        }

        $expiresAt = $company->certificate_expires_at;
        $now = Carbon::now();

        if (!$expiresAt) {
            return [
                'has_certificate' => true,
                'is_expired' => false,
                'expires_at' => null,
                'needs_renewal' => false,
                'days_until_expiry' => null,
            ];
        }

        $daysUntilExpiry = $now->diffInDays($expiresAt, false);
        $isExpired = $expiresAt->isPast();
        $needsRenewal = $isExpired || $daysUntilExpiry <= 30;

        return [
            'has_certificate' => true,
            'is_expired' => $isExpired,
            'expires_at' => $expiresAt,
            'needs_renewal' => $needsRenewal,
            'days_until_expiry' => $daysUntilExpiry,
        ];
    }

    /**
     * Obtener información resumida del usuario para mostrar en la interfaz.
     */
    public function getUserSummaryInfo(): array
    {
        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        if (!$user) {
            return [
                'name' => 'Invitado',
                'email' => null,
                'type' => 'Invitado',
                'company' => null,
                'roles' => [],
                'permissions' => []
            ];
        }

        $info = [
            'name' => $user->name,
            'email' => $user->email,
            'type' => $this->getUserType(),
            'company' => $company ? $company->legal_name : null,
            'roles' => $user->roles->pluck('name')->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ];

        // Agregar información específica de empresa si aplica
        if ($company) {
            $info['company_id'] = $company->id;
            $info['company_roles'] = $company->company_roles ?? [];
            $info['company_active'] = $company->active;
        }

        // Agregar información específica de operador si aplica
        if ($user->userable_type === 'App\Models\Operator' && $user->userable) {
            $operator = $user->userable;
            $info['operator_permissions'] = [
                'can_import' => $operator->can_import ?? false,
                'can_export' => $operator->can_export ?? false,
                'can_transfer' => $operator->can_transfer ?? false,
            ];
            $info['operator_active'] = $operator->active;
        }

        return $info;
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PARA DASHBOARD (faltantes)
    // =========================================================================

    /**
     * Obtener actividad reciente de la empresa.
     */
    protected function getRecentActivity(Company $company): array
    {
        try {
            $recentActivity = [];

            // Obtener logins recientes de usuarios de la empresa
            $recentLogins = \App\Models\User::whereHas('userable', function($query) use ($company) {
                    $query->where('company_id', $company->id);
                })
                ->whereNotNull('last_access')
                ->where('last_access', '>=', now()->subDays(7))
                ->orderBy('last_access', 'desc')
                ->take(5)
                ->get(['name', 'last_access']);

            foreach ($recentLogins as $user) {
                $recentActivity[] = [
                    'type' => 'login',
                    'description' => "Login de {$user->name}",
                    'timestamp' => $user->last_access,
                    'user' => $user->name,
                ];
            }

            // Obtener operaciones recientes (viajes creados)
            if (class_exists(\App\Models\Voyage::class)) {
                $recentVoyages = \App\Models\Voyage::where('company_id', $company->id)
                    ->with('createdByUser')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->take(3)
                    ->get();

                foreach ($recentVoyages as $voyage) {
                    $recentActivity[] = [
                        'type' => 'voyage_created',
                        'description' => "Viaje {$voyage->voyage_number} creado",
                        'timestamp' => $voyage->created_at,
                        'user' => $voyage->createdByUser->name ?? 'Sistema',
                    ];
                }
            }

            // Obtener transacciones de webservice recientes
            if (class_exists(\App\Models\WebserviceTransaction::class)) {
                $recentWebservices = \App\Models\WebserviceTransaction::where('company_id', $company->id)
                    ->with('user')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->take(3)
                    ->get();

                foreach ($recentWebservices as $ws) {
                    $recentActivity[] = [
                        'type' => 'webservice',
                        'description' => "Webservice {$ws->webservice_type} - " . ucfirst($ws->status),
                        'timestamp' => $ws->created_at,
                        'user' => $ws->user->name ?? 'Sistema',
                    ];
                }
            }

            // Ordenar por timestamp más reciente
            usort($recentActivity, function($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });

            return array_slice($recentActivity, 0, 10);

        } catch (\Exception $e) {
            return [
                [
                    'type' => 'error',
                    'description' => 'Error cargando actividad reciente',
                    'timestamp' => now(),
                    'user' => 'Sistema',
                ]
            ];
        }
    }

    /**
     * Obtener tareas pendientes de la empresa.
     */
    protected function getPendingTasks(Company $company): array
    {
        $tasks = [];

        try {
            // Verificar certificado
            $certStatus = $this->getCertificateStatus($company);
            if ($certStatus['needs_renewal']) {
                $tasks[] = [
                    'title' => 'Renovar certificado digital',
                    'priority' => $certStatus['is_expired'] ? 'high' : 'medium',
                    'due_date' => $certStatus['expires_at'],
                    'route' => route('company.certificates.index'),
                ];
            }

            // Verificar operadores inactivos
            $inactiveOperators = $company->operators()->where('active', false)->count();
            if ($inactiveOperators > 0) {
                $tasks[] = [
                    'title' => "Revisar {$inactiveOperators} operador(es) inactivo(s)",
                    'priority' => 'low',
                    'due_date' => null,
                    'route' => route('company.operators.index'),
                ];
            }

            // Verificar viajes con retraso (solo si existe el modelo)
            if (class_exists(\App\Models\Voyage::class)) {
                $delayedVoyages = \App\Models\Voyage::where('company_id', $company->id)
                    ->whereDate('departure_date', '<', now())
                    ->whereIn('status', ['pending', 'planning'])
                    ->count();

                if ($delayedVoyages > 0) {
                    $tasks[] = [
                        'title' => "Actualizar {$delayedVoyages} viaje(s) con retraso",
                        'priority' => 'high',
                        'due_date' => now(),
                        'route' => route('company.voyages.index'),
                    ];
                }
            }

            // Verificar transacciones de webservice fallidas (solo si existe el modelo)
            if (class_exists(\App\Models\WebserviceTransaction::class)) {
                $failedTransactions = \App\Models\WebserviceTransaction::where('company_id', $company->id)
                    ->where('status', 'error')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();

                if ($failedTransactions > 0) {
                    $tasks[] = [
                        'title' => "Revisar {$failedTransactions} transacción(es) fallida(s)",
                        'priority' => 'medium',
                        'due_date' => now()->addDays(3),
                        'route' => route('company.webservices.history'),
                    ];
                }
            }

            // Verificar conocimientos pendientes de verificación (solo si existe el modelo)
            if (class_exists(\App\Models\BillOfLading::class)) {
                $pendingBills = \App\Models\BillOfLading::whereHas('shipment.voyage', function($query) use ($company) {
                    $query->where('company_id', $company->id);
                })
                ->where('status', 'draft')
                ->whereNull('verified_at')
                ->count();

                if ($pendingBills > 0) {
                    $tasks[] = [
                        'title' => "Verificar {$pendingBills} conocimiento(s) de embarque",
                        'priority' => 'medium',
                        'due_date' => now()->addDays(2),
                        'route' => route('company.bills-of-lading.index'),
                    ];
                }
            }

            return $tasks;

        } catch (\Exception $e) {
            return [
                [
                    'title' => 'Error cargando tareas pendientes',
                    'priority' => 'low',
                    'due_date' => null,
                    'route' => '#',
                ]
            ];
        }
    }

    /**
     * Obtener estadísticas personales del usuario.
     */
    protected function getPersonalStats($user): array
    {
        if (!$this->isUser()) {
            return [];
        }

        return [
            'permissions_summary' => [
                'can_import' => $this->canImport(),
                'can_export' => $this->canExport(),
                'can_transfer' => $this->canTransfer(),
            ],
            'activity_summary' => [
                'last_login' => $user->last_access,
                'total_operations' => 0, // TODO: Implementar cuando estén los módulos
                'recent_operations' => 0, // TODO: Implementar cuando estén los módulos
            ],
            'operator_info' => [
                'type' => $this->getUserOperator()?->type ?? 'unknown',
                'company_roles' => $this->getUserCompanyRoles(),
                'active' => $this->getUserOperator()?->active ?? false,
            ],
        ];
    }
}