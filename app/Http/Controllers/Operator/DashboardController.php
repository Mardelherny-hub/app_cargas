<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Operator;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use UserHelper;

    /**
     * Mostrar el dashboard del Operador Externo.
     */
    public function index()
    {
        $user = Auth::user();
        $company = $this->getUserCompany();
        $operator = $this->getUserOperator();

        if (!$company || !$operator) {
            return redirect()->route('dashboard')
            ->with('error', 'No se encontró la información del operador o empresa asociada.');
        }

        // Estadísticas personales del operador
        $stats = [
            'my_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'pending_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'completed_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'my_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'active_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'completed_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'last_activity' => $user->last_access,
        ];

        // Permisos del operador
        $permissions = [
            'can_import' => $operator->can_import,
            'can_export' => $operator->can_export,
            'can_transfer' => $operator->can_transfer,
            'special_permissions' => $operator->special_permissions ?? [],
        ];

        // Estado de la empresa
        $companyStatus = $this->getCompanyStatus($company);

        // Actividad reciente del operador
        $recentActivity = $this->getOperatorActivity($operator);

        // Acciones rápidas disponibles
        $quickActions = $this->getQuickActions($operator);

        // Alertas personales
        $personalAlerts = $this->getPersonalAlerts($operator, $company);

        // Resumen de trabajo del día
        $todaysSummary = $this->getTodaysSummary($operator);

        return view('operator.dashboard', compact(
            'company',
            'operator',
            'stats',
            'permissions',
            'companyStatus',
            'recentActivity',
            'quickActions',
            'personalAlerts',
            'todaysSummary'
        ));
    }

    /**
     * Obtener el operador del usuario actual.
     */
    private function getUserOperator()
    {
        $user = Auth::user();

        if ($user->userable_type === 'App\\Models\\Operator') {
            return $user->userable;
        }

        return null;
    }

    /**
     * Obtener estado de la empresa.
     */
    private function getCompanyStatus($company)
    {
        $status = [
            'name' => $company->business_name,
            'country' => $company->country,
            'has_certificate' => !empty($company->certificate_path),
            'certificate_expires_at' => $company->certificate_expires_at,
            'certificate_status' => 'none',
            'ws_active' => $company->ws_active,
            'ws_environment' => $company->ws_environment,
        ];

        // Estado del certificado
        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();
            $daysToExpiry = $now->diffInDays($expiresAt, false);

            if ($daysToExpiry < 0) {
                $status['certificate_status'] = 'expired';
            } elseif ($daysToExpiry <= 30) {
                $status['certificate_status'] = 'warning';
            } else {
                $status['certificate_status'] = 'valid';
            }
        }

        return $status;
    }

    /**
     * Obtener actividad reciente del operador.
     */
    private function getOperatorActivity($operator)
    {
        $activities = [];

        // Últimos accesos
        $user = $operator->user;
        if ($user && $user->last_access) {
            $activities[] = [
                'type' => 'login',
                'message' => 'Último acceso al sistema',
                'date' => $user->last_access,
                'icon' => 'login',
                'color' => 'green',
            ];
        }

        // TODO: Agregar actividad de cargas cuando esté implementado
        // TODO: Agregar actividad de viajes cuando esté implementado
        // TODO: Agregar actividad de importaciones cuando esté implementado

        // Cambios en permisos (si se puede rastrear)
        if ($operator->updated_at->diffInDays(Carbon::now()) <= 30) {
            $activities[] = [
                'type' => 'permissions_updated',
                'message' => 'Permisos actualizados',
                'date' => $operator->updated_at,
                'icon' => 'shield',
                'color' => 'blue',
            ];
        }

        return array_slice($activities, 0, 10);
    }

    /**
     * Obtener acciones rápidas disponibles.
     */
    private function getQuickActions($operator)
    {
        $actions = [];

        // Siempre disponible
        $actions[] = [
            'name' => 'Nueva Carga',
            'description' => 'Crear una nueva carga',
            'route' => 'operator.shipments.create',
            'icon' => 'plus-circle',
            'color' => 'blue',
            'available' => true,
        ];

        $actions[] = [
            'name' => 'Nuevo Viaje',
            'description' => 'Crear un nuevo viaje',
            'route' => 'operator.trips.create',
            'icon' => 'truck',
            'color' => 'green',
            'available' => true,
        ];

        $actions[] = [
            'name' => 'Mis Cargas',
            'description' => 'Ver todas mis cargas',
            'route' => 'operator.shipments.index',
            'icon' => 'list',
            'color' => 'gray',
            'available' => true,
        ];

        // Condicionales según permisos
        if ($operator->can_import) {
            $actions[] = [
                'name' => 'Importar Datos',
                'description' => 'Importar desde Excel/XML',
                'route' => 'operator.import.index',
                'icon' => 'upload',
                'color' => 'purple',
                'available' => true,
            ];
        }

        if ($operator->can_export) {
            $actions[] = [
                'name' => 'Exportar Datos',
                'description' => 'Exportar reportes',
                'route' => 'operator.reports.index',
                'icon' => 'download',
                'color' => 'orange',
                'available' => true,
            ];
        }

        if ($operator->can_transfer) {
            $actions[] = [
                'name' => 'Transferir Cargas',
                'description' => 'Transferir cargas entre empresas',
                'route' => 'operator.transfers.index',
                'icon' => 'exchange',
                'color' => 'teal',
                'available' => true,
            ];
        }

        return $actions;
    }

    /**
     * Obtener alertas personales del operador.
     */
    private function getPersonalAlerts($operator, $company)
    {
        $alerts = [];

        // Certificado de la empresa
        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();
            $daysToExpiry = $now->diffInDays($expiresAt, false);

            if ($daysToExpiry < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'El certificado de la empresa está vencido',
                    'action' => null, // Los operadores no pueden gestionar certificados
                ];
            } elseif ($daysToExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "El certificado de la empresa vence en {$daysToExpiry} días",
                    'action' => null,
                ];
            }
        }

        // Sin certificado
        if (!$company->certificate_path) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'La empresa no tiene certificado digital configurado',
                'action' => null,
            ];
        }

        // Webservices inactivos
        if (!$company->ws_active) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Los webservices están desactivados',
                'action' => null,
            ];
        }

        // Permisos limitados
        if (!$operator->can_import && !$operator->can_export && !$operator->can_transfer) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Tiene permisos básicos. Contacte al administrador para más funcionalidades.',
                'action' => null,
            ];
        }

        return $alerts;
    }

    /**
     * Obtener resumen del trabajo del día.
     */
    private function getTodaysSummary($operator)
    {
        $today = Carbon::today();

        return [
            'date' => $today,
            'shipments_created' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'shipments_updated' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'trips_created' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'imports_performed' => 0, // TODO: Implementar cuando esté el módulo de importaciones
            'reports_generated' => 0, // TODO: Implementar cuando esté el módulo de reportes
            'time_spent' => 0, // TODO: Implementar tracking de tiempo
        ];
    }

    /**
     * Configuración personal del operador.
     */
    public function settings()
    {
        $user = Auth::user();
        $operator = $this->getUserOperator();
        $company = $this->getUserCompany();

        if (!$operator || !$company) {
            return redirect()->route('operator.dashboard')
            ->with('error', 'No se encontró la información del operador o empresa asociada.');
        }

        return view('operator.settings', compact('user', 'operator', 'company'));
    }

    /**
     * Actualizar perfil del operador.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $operator = $this->getUserOperator();

        if (!$operator) {
            return redirect()->route('operator.dashboard')
            ->with('error', 'No se encontró la información del operador.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
        ]);

        // Actualizar usuario
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        // Actualizar operador
        $operator->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'position' => $request->position,
        ]);

        return redirect()->route('operator.settings')
        ->with('success', 'Perfil actualizado correctamente.');
    }

    /**
     * Actualizar contraseña del operador.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!\Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual es incorrecta.']);
        }

        $user->update([
            'password' => \Hash::make($request->password),
        ]);

        return redirect()->route('operator.settings')
        ->with('success', 'Contraseña actualizada correctamente.');
    }

    /**
     * Actualizar preferencias del operador.
     */
    public function updatePreferences(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'timezone' => 'required|string|max:50',
            'language' => 'required|string|max:10',
        ]);

        $user->update([
            'timezone' => $request->timezone,
            // language se puede guardar en un campo adicional o en JSON
        ]);

        return redirect()->route('operator.settings')
        ->with('success', 'Preferencias actualizadas correctamente.');
    }

    /**
     * Actualizar configuración de notificaciones.
     */
    public function updateNotifications(Request $request)
    {
        // TODO: Implementar sistema de notificaciones
        return redirect()->route('operator.settings')
        ->with('success', 'Configuración de notificaciones actualizada.');
    }
}
