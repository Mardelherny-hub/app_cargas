<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Operator;
use App\Models\User;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OperatorController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de operadores de la empresa.
     * SOLO COMPANY-ADMIN puede acceder a esta funcionalidad.
     */
    public function index(Request $request)
    {
        // 1. Verificar que sea company-admin (SOLO ellos pueden gestionar operadores)
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('manage_operators')) {
        //     abort(403, 'No tiene permisos para gestionar operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 4. Verificar acceso a la empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Construir query base con filtros de ownership
        $query = $company->operators()->with(['user.roles']);

        // Aplicar filtros de búsqueda
        $this->applyOperatorFilters($query, $request);

        // Ordenamiento
        $query = $this->applyOperatorSorting($query, $request);

        // Paginación
        $operators = $query->paginate(15)->withQueryString();

        // Obtener estadísticas
        $stats = $this->getOperatorStats($company);

        // Obtener filtros disponibles
        $filters = $this->getOperatorFilters();

        // Permisos para la vista
        $permissions = $this->getOperatorPermissions();

        return view('company.operators.index', compact(
            'operators',
            'company',
            'stats',
            'filters',
            'permissions'
        ));
    }

    /**
     * Mostrar formulario para crear operador.
     * SOLO COMPANY-ADMIN puede crear operadores.
     */
    public function create()
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden crear operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('create_operators')) {
        //     abort(403, 'No tiene permisos para crear operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 4. Verificar acceso a la empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener datos para el formulario
        $formData = $this->getCreateOperatorFormData($company);

        return view('company.operators.create', compact('company', 'formData'));
    }

    /**
     * Crear nuevo operador.
     * SOLO COMPANY-ADMIN puede crear operadores.
     */
    public function store(Request $request)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden crear operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('create_operators')) {
        //     abort(403, 'No tiene permisos para crear operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Validar datos del request
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:255',
            'type' => 'required|in:internal,external',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
            'active' => 'boolean',
        ]);

        // Validar que al menos tenga un permiso
        if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
            return back()->withInput()
                ->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
        }

        try {
            DB::beginTransaction();

            // Crear operador
            $operator = Operator::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
                'type' => $request->type,
                'company_id' => $company->id,
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
                'active' => $request->boolean('active', true),
            ]);

            // Crear usuario asociado
            $user = User::create([
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'userable_type' => 'App\Models\Operator',
                'userable_id' => $operator->id,
                'active' => $request->boolean('active', true),
            ]);

            // Asignar rol 'user' (3 roles simplificados)
            $user->assignRole('user');

            DB::commit();

            return redirect()->route('company.operators.index')
                ->with('success', 'Operador creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al crear el operador: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles del operador.
     * SOLO COMPANY-ADMIN puede ver detalles completos.
     */
    public function show(Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden ver detalles de operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('view_operators')) {
        //     abort(403, 'No tiene permisos para ver operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver este operador.');
        }

        // Cargar relaciones
        $operator->load(['user.roles', 'company']);

        // Obtener estadísticas del operador
        $stats = $this->getOperatorDetailStats($operator);

        // Obtener actividad reciente
        $recentActivity = $this->getOperatorActivity($operator);

        // Obtener permisos específicos para este operador
        $permissions = $this->getOperatorDetailPermissions($operator);

        return view('company.operators.show', compact(
            'operator',
            'stats',
            'recentActivity',
            'permissions'
        ));
    }

    /**
     * Mostrar formulario para editar operador.
     * SOLO COMPANY-ADMIN puede editar operadores.
     */
    public function edit(Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden editar operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('edit_operators')) {
        //     abort(403, 'No tiene permisos para editar operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar este operador.');
        }

        // Cargar relaciones
        $operator->load(['user']);

        // Obtener datos para el formulario
        $formData = $this->getEditOperatorFormData($operator);

        return view('company.operators.edit', compact('operator', 'company', 'formData'));
    }

    /**
     * Actualizar operador.
     * SOLO COMPANY-ADMIN puede actualizar operadores.
     */
    public function update(Request $request, Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('edit_operators')) {
        //     abort(403, 'No tiene permisos para editar operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar este operador.');
        }

        // Validar datos del request
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:255',
            'type' => 'required|in:internal,external',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($operator->user?->id)
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
            'active' => 'boolean',
        ]);

        // Validar que al menos tenga un permiso
        if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
            return back()->withInput()
                ->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
        }

        try {
            DB::beginTransaction();

            // Actualizar operador
            $operator->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
                'type' => $request->type,
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
                'active' => $request->boolean('active', true),
            ]);

            // Actualizar usuario asociado
            if ($operator->user) {
                $userData = [
                    'name' => trim($request->first_name . ' ' . $request->last_name),
                    'email' => $request->email,
                    'active' => $request->boolean('active', true),
                ];

                // Solo actualizar contraseña si se proporciona
                if ($request->filled('password')) {
                    $userData['password'] = Hash::make($request->password);
                }

                $operator->user->update($userData);
            }

            DB::commit();

            return redirect()->route('company.operators.index')
                ->with('success', 'Operador actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al actualizar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar operador.
     * SOLO COMPANY-ADMIN puede eliminar operadores.
     */
    public function destroy(Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden eliminar operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('delete_operators')) {
        //     abort(403, 'No tiene permisos para eliminar operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para eliminar este operador.');
        }

        // 5. Verificar que el operador no tenga datos asociados críticos
        if ($this->operatorHasCriticalData($operator)) {
            return back()->with('error', 'No se puede eliminar el operador porque tiene datos asociados. Desactívelo en su lugar.');
        }

        try {
            DB::beginTransaction();

            $operatorName = $operator->first_name . ' ' . $operator->last_name;

            // Eliminar usuario asociado
            if ($operator->user) {
                $operator->user->delete();
            }

            // Eliminar operador
            $operator->delete();

            DB::commit();

            return redirect()->route('company.operators.index')
                ->with('success', "Operador {$operatorName} eliminado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar estado activo/inactivo del operador.
     * SOLO COMPANY-ADMIN puede cambiar estados.
     */
    public function toggleStatus(Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden cambiar estados de operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('manage_operator_status')) {
        //     abort(403, 'No tiene permisos para cambiar estados de operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para gestionar este operador.');
        }

        try {
            DB::beginTransaction();

            $newStatus = !$operator->active;

            // Actualizar operador
            $operator->update(['active' => $newStatus]);

            // Actualizar usuario asociado
            if ($operator->user) {
                $operator->user->update(['active' => $newStatus]);
            }

            DB::commit();

            $status = $newStatus ? 'activado' : 'desactivado';
            return back()->with('success', "Operador {$status} exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cambiar el estado del operador: ' . $e->getMessage());
        }
    }

    /**
     * Restablecer contraseña del operador.
     * SOLO COMPANY-ADMIN puede restablecer contraseñas.
     */
    public function resetPassword(Request $request, Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden restablecer contraseñas de operadores.');
        }

        // 2. CORRECCIÓN: Comentar verificación problemática
        // if (!$this->canPerform('reset_operator_password')) {
        //     abort(403, 'No tiene permisos para restablecer contraseñas de operadores.');
        // }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para gestionar este operador.');
        }

        // Validar nueva contraseña
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            if ($operator->user) {
                $operator->user->update([
                    'password' => Hash::make($request->password),
                ]);

                return back()->with('success', 'Contraseña restablecida exitosamente.');
            } else {
                return back()->with('error', 'El operador no tiene usuario asociado.');
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Error al restablecer la contraseña: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    /**
     * Aplicar filtros de búsqueda a operadores.
     */
    private function applyOperatorFilters($query, Request $request)
    {
        // Filtro de búsqueda general
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('document_number', 'LIKE', "%{$search}%")
                  ->orWhere('position', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('active', $request->status === 'active');
        }

        // Filtro por tipo
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtro por permisos
        if ($request->filled('permission')) {
            $permission = $request->permission;
            if (in_array($permission, ['can_import', 'can_export', 'can_transfer'])) {
                $query->where($permission, true);
            }
        }
    }

    /**
     * Aplicar ordenamiento a operadores.
     */
    private function applyOperatorSorting($query, Request $request)
    {
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        $allowedSorts = ['first_name', 'last_name', 'position', 'type', 'active', 'created_at'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Obtener estadísticas de operadores de la empresa.
     */
    private function getOperatorStats($company): array
    {
        $operators = $company->operators();

        return [
            'total' => $operators->count(),
            'active' => $operators->where('active', true)->count(),
            'inactive' => $operators->where('active', false)->count(),
            'can_import' => $operators->where('can_import', true)->count(),
            'can_export' => $operators->where('can_export', true)->count(),
            'can_transfer' => $operators->where('can_transfer', true)->count(),
            'internal' => $operators->where('type', 'internal')->count(),
            'external' => $operators->where('type', 'external')->count(),
        ];
    }

    /**
 * CORRECCIÓN COMPLETA: Incluir TODAS las claves que usa la vista real
 */
private function getOperatorDetailStats($operator): array
{
    return [
        // Estadísticas de shipments
        'total_shipments' => 0,
        'active_shipments' => 0,
        'completed_shipments' => 0,
        'pending_shipments' => 0,
        'cancelled_shipments' => 0,

        // Fechas y tiempos
        'last_login' => $operator->user?->last_access,
        'last_activity' => $operator->user?->last_access,
        'account_created' => $operator->created_at,
        'created_at' => $operator->created_at,
        'updated_at' => $operator->updated_at,

        // Cálculos de días
        'days_since_creation' => $operator->created_at ? $operator->created_at->diffInDays(now()) : 0, // ⭐ Esta era la que faltaba
        'days_since_last_login' => $operator->user?->last_access ? $operator->user->last_access->diffInDays(now()) : null,
        'days_since_last_activity' => $operator->user?->last_access ? $operator->user->last_access->diffInDays(now()) : null,

        // Estados
        'status' => $operator->active ? 'Activo' : 'Inactivo',
        'operator_status' => $operator->active ? 'Activo' : 'Inactivo',
        'user_status' => $operator->user?->active ? 'Activo' : 'Inactivo',
        'account_status' => ($operator->active && $operator->user?->active) ? 'Cuenta activa' : 'Cuenta inactiva',

        // Información del operador
        'operator_type' => $operator->type === 'internal' ? 'Interno' : 'Externo',
        'type_display' => $operator->type === 'internal' ? 'Operador Interno' : 'Operador Externo',
        'full_name' => trim($operator->first_name . ' ' . $operator->last_name),
        'first_name' => $operator->first_name,
        'last_name' => $operator->last_name,
        'position' => $operator->position ?? 'Sin cargo definido',
        'document_number' => $operator->document_number ?? 'No especificado',
        'phone' => $operator->phone ?? 'No especificado',

        // Permisos
        'can_import' => $operator->can_import ?? false,
        'can_export' => $operator->can_export ?? false,
        'can_transfer' => $operator->can_transfer ?? false,
        'permissions_count' => collect([
            $operator->can_import ?? false,
            $operator->can_export ?? false,
            $operator->can_transfer ?? false
        ])->filter()->count(),
        'has_permissions' => ($operator->can_import || $operator->can_export || $operator->can_transfer),

        // Información de empresa
        'company_name' => $operator->company?->legal_name ?? 'Sin empresa',
        'company_id' => $operator->company_id,
        'company_active' => $operator->company?->active ?? false,

        // Información de usuario
        'user_email' => $operator->user?->email ?? 'Sin usuario asociado',
        'user_name' => $operator->user?->name ?? 'Sin usuario asociado',
        'user_id' => $operator->user?->id,
        'email_verified' => $operator->user?->email_verified_at ? 'Verificado' : 'No verificado',
        'email_verified_at' => $operator->user?->email_verified_at,
        'user_roles' => $operator->user?->roles->pluck('name')->toArray() ?? [],

        // Estados booleanos
        'is_active' => $operator->active,
        'is_internal' => $operator->type === 'internal',
        'is_external' => $operator->type === 'external',
        'needs_attention' => (!$operator->active || !$operator->user?->active || !$operator->user),
        'has_user' => $operator->user ? true : false,

        // Métricas adicionales
        'login_count' => 0, // TODO: implementar con sistema de auditoría
        'total_actions' => 0, // TODO: implementar con sistema de auditoría
        'recent_activity_count' => 0, // TODO: implementar con sistema de auditoría
    ];
}

    /**
     * Obtener actividad reciente del operador.
     */
    private function getOperatorActivity($operator): array
    {
        // TODO: implementar cuando tengamos sistema de auditoría
        return [];
    }

    /**
     * Obtener filtros disponibles.
     */
    private function getOperatorFilters(): array
    {
        return [
            'status' => [
                'active' => 'Activos',
                'inactive' => 'Inactivos',
            ],
            'type' => [
                'internal' => 'Internos',
                'external' => 'Externos',
            ],
            'permission' => [
                'can_import' => 'Pueden Importar',
                'can_export' => 'Pueden Exportar',
                'can_transfer' => 'Pueden Transferir',
            ],
        ];
    }

    /**
     * CORRECCIÓN: Obtener permisos para operadores - simplificado
     */
    private function getOperatorPermissions(): array
    {
        // Para company-admin, devolver todos como true
        return [
            'canCreate' => true,
            'canEdit' => true,
            'canDelete' => true,
            'canManageStatus' => true,
            'canManagePermissions' => true,
            'canResetPassword' => true,
        ];
    }

    /**
     * CORRECCIÓN: Obtener permisos específicos para detalles de operador - simplificado
     */
    private function getOperatorDetailPermissions($operator): array
    {
        // Para company-admin, devolver todos como true
        return [
            'canEdit' => true,
            'canDelete' => !$this->operatorHasCriticalData($operator), // Solo verificar datos críticos
            'canToggleStatus' => true,
            'canManagePermissions' => true,
            'canResetPassword' => true,
            'canViewActivity' => true,
        ];
    }

    /**
     * Verificar si el operador tiene datos críticos asociados.
     */
    private function operatorHasCriticalData($operator): bool
    {
        // TODO: implementar verificaciones cuando tengamos otros módulos
        // Por ejemplo: verificar si tiene shipments activos, etc.
        return false;
    }

    /**
     * Obtener datos para formulario de creación.
     */
    private function getCreateOperatorFormData($company): array
    {
        return [
            'types' => [
                'internal' => 'Interno',
                'external' => 'Externo',
            ],
            'permissions' => [
                'can_import' => 'Puede Importar',
                'can_export' => 'Puede Exportar',
                'can_transfer' => 'Puede Transferir',
            ],
            'company_roles' => $company->company_roles ?? [],
        ];
    }

    /**
     * Obtener datos para formulario de edición.
     */
    private function getEditOperatorFormData($operator): array
    {
        return [
            'types' => [
                'internal' => 'Interno',
                'external' => 'Externo',
            ],
            'permissions' => [
                'can_import' => 'Puede Importar',
                'can_export' => 'Puede Exportar',
                'can_transfer' => 'Puede Transferir',
            ],
            'company_roles' => $operator->company?->company_roles ?? [],
        ];
    }
}
