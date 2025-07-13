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

        // 2. Verificar permisos específicos para gestionar operadores
        if (!$this->canPerform('manage_operators')) {
            abort(403, 'No tiene permisos para gestionar operadores.');
        }

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

        // 2. Verificar permisos específicos
        if (!$this->canPerform('create_operators')) {
            abort(403, 'No tiene permisos para crear operadores.');
        }

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
     * Actualizar operador existente.
     * SOLO COMPANY-ADMIN puede actualizar operadores.
     */
    public function update(Request $request, Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar operadores.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('edit_operators')) {
            abort(403, 'No tiene permisos para editar operadores.');
        }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar este operador.');
        }

        // CORREGIDO: Validar datos del request (solo external)
        $validationRules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:255',
            'type' => 'required|in:external', // CORREGIDO: Solo external
            'email' => 'required|email|unique:users,email,' . $operator->user->id,
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
            'active' => 'boolean',
        ];

        // Si se proporciona contraseña, validarla
        if ($request->filled('password')) {
            $validationRules['password'] = 'string|min:8|confirmed';
        }

        $request->validate($validationRules);

        // Validar que al menos tenga un permiso
        if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
            return back()->withInput()
                ->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
        }

        try {
            DB::beginTransaction();

            // CORREGIDO: Actualizar operador (mantener external y company_id)
            $operator->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
                'type' => 'external', // CORREGIDO: Mantener external
                // company_id no se actualiza, debe mantenerse el mismo
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
                'active' => $request->boolean('active', true),
            ]);

            // Actualizar usuario asociado
            $userUpdateData = [
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'email' => $request->email,
                'active' => $request->boolean('active', true),
            ];

            // Solo actualizar contraseña si se proporciona
            if ($request->filled('password')) {
                $userUpdateData['password'] = Hash::make($request->password);
            }

            $operator->user->update($userUpdateData);

            DB::commit();

            return redirect()->route('company.operators.show', $operator)
                ->with('success', 'Operador actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al actualizar el operador: ' . $e->getMessage());
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

        // 2. Verificar permisos específicos
        if (!$this->canPerform('view_operators')) {
            abort(403, 'No tiene permisos para ver operadores.');
        }

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

        // 2. Verificar permisos específicos
        if (!$this->canPerform('edit_operators')) {
            abort(403, 'No tiene permisos para editar operadores.');
        }

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
     * Crear nuevo operador.
     * SOLO COMPANY-ADMIN puede crear operadores.
     */
    public function store(Request $request)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden crear operadores.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('create_operators')) {
            abort(403, 'No tiene permisos para crear operadores.');
        }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // CORREGIDO: Validar datos del request (solo external)
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:255',
            'type' => 'required|in:external', // CORREGIDO: Solo external
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

            // CORREGIDO: Crear operador (siempre external con company_id)
            $operator = Operator::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
                'type' => 'external', // CORREGIDO: Siempre external
                'company_id' => $company->id, // CORREGIDO: Siempre tiene empresa
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

            // Asignar rol 'user'
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
     * Eliminar operador.
     * SOLO COMPANY-ADMIN puede eliminar operadores.
     */
    public function destroy(Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden eliminar operadores.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('delete_operators')) {
            abort(403, 'No tiene permisos para eliminar operadores.');
        }

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

        // 2. Verificar permisos específicos
        if (!$this->canPerform('manage_operator_status')) {
            abort(403, 'No tiene permisos para cambiar estados de operadores.');
        }

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
     * Actualizar permisos específicos del operador.
     * SOLO COMPANY-ADMIN puede actualizar permisos.
     */
    public function updatePermissions(Request $request, Operator $operator)
    {
        // 1. Verificar que sea company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar permisos de operadores.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('manage_operator_permissions')) {
            abort(403, 'No tiene permisos para gestionar permisos de operadores.');
        }

        $company = $this->getUserCompany();

        // 3. Verificar empresa y acceso
        if (!$company || !$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para gestionar este operador.');
        }

        // Validar datos del request
        $request->validate([
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
        ]);

        // Validar que al menos tenga un permiso
        if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
            return back()->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
        }

        try {
            $operator->update([
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
            ]);

            return back()->with('success', 'Permisos actualizados exitosamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar los permisos: ' . $e->getMessage());
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
            abort(403, 'Solo los administradores de empresa pueden restablecer contraseñas.');
        }

        // 2. Verificar permisos específicos
        if (!$this->canPerform('reset_operator_password')) {
            abort(403, 'No tiene permisos para restablecer contraseñas.');
        }

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
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSorts = ['first_name', 'last_name', 'position', 'type', 'created_at', 'last_access'];

        if (in_array($sortField, $allowedSorts)) {
            if ($sortField === 'last_access') {
                $query->leftJoin('users', function ($join) {
                    $join->on('operators.id', '=', 'users.userable_id')
                         ->where('users.userable_type', 'App\Models\Operator');
                })
                ->orderBy('users.last_access', $sortDirection)
                ->select('operators.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        }

        return $query;
    }

    /**
     * Obtener estadísticas de operadores.
     */
    private function getOperatorStats($company): array
    {
        $operators = $company->operators();

        return [
            'total' => $operators->count(),
            'active' => $operators->where('active', true)->count(),
            'inactive' => $operators->where('active', false)->count(),
            'internal' => $operators->where('type', 'internal')->count(),
            'external' => $operators->where('type', 'external')->count(),
            'with_import' => $operators->where('can_import', true)->count(),
            'with_export' => $operators->where('can_export', true)->count(),
            'with_transfer' => $operators->where('can_transfer', true)->count(),
            'recent_logins' => $operators->whereHas('user', function ($q) {
                $q->where('last_access', '>=', now()->subDays(7));
            })->count(),
        ];
    }

    /**
     * Obtener estadísticas detalladas de un operador.
     */
    private function getOperatorDetailStats($operator): array
    {
        return [
            'total_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'recent_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'total_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'total_imports' => 0, // TODO: Implementar cuando esté el módulo de importaciones
            'total_exports' => 0, // TODO: Implementar cuando esté el módulo de exportaciones
            'last_activity' => $operator->user?->last_access,
            'days_since_creation' => $operator->created_at->diffInDays(now()),
            'account_status' => $operator->active ? 'Activo' : 'Inactivo',
            'user_status' => $operator->user?->active ? 'Activo' : 'Inactivo',
        ];
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
     * Obtener permisos para operadores.
     */
    private function getOperatorPermissions(): array
    {
        return [
            'canCreate' => $this->canPerform('create_operators'),
            'canEdit' => $this->canPerform('edit_operators'),
            'canDelete' => $this->canPerform('delete_operators'),
            'canManageStatus' => $this->canPerform('manage_operator_status'),
            'canManagePermissions' => $this->canPerform('manage_operator_permissions'),
            'canResetPassword' => $this->canPerform('reset_operator_password'),
        ];
    }

    /**
     * Obtener permisos específicos para detalles de operador.
     */
    private function getOperatorDetailPermissions($operator): array
    {
        return [
            'canEdit' => $this->canPerform('edit_operators'),
            'canDelete' => $this->canPerform('delete_operators') && !$this->operatorHasCriticalData($operator),
            'canToggleStatus' => $this->canPerform('manage_operator_status'),
            'canManagePermissions' => $this->canPerform('manage_operator_permissions'),
            'canResetPassword' => $this->canPerform('reset_operator_password'),
            'canViewActivity' => $this->canPerform('view_operator_activity'),
        ];
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
            'company_roles' => $operator->company->company_roles ?? [],
        ];
    }

    /**
     * Obtener actividad reciente del operador.
     */
    private function getOperatorActivity($operator): array
    {
        $activities = [];

        // Último acceso
        if ($operator->user && $operator->user->last_access) {
            $activities[] = [
                'type' => 'login',
                'message' => 'Último acceso al sistema',
                'date' => $operator->user->last_access,
                'icon' => 'login',
                'color' => 'green',
            ];
        }

        // Cambios recientes
        if ($operator->updated_at->diffInDays($operator->created_at) > 0) {
            $activities[] = [
                'type' => 'updated',
                'message' => 'Información actualizada',
                'date' => $operator->updated_at,
                'icon' => 'edit',
                'color' => 'yellow',
            ];
        }

        // Creación del operador
        $activities[] = [
            'type' => 'created',
            'message' => 'Operador creado',
            'date' => $operator->created_at,
            'icon' => 'user-plus',
            'color' => 'blue',
        ];

        // TODO: Agregar actividad de cargas cuando esté implementado
        // TODO: Agregar actividad de viajes cuando esté implementado
        // TODO: Agregar actividad de importaciones cuando esté implementado
        // TODO: Agregar actividad de exportaciones cuando esté implementado

        // Ordenar por fecha descendente
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Verificar si el operador tiene datos críticos asociados.
     */
    private function operatorHasCriticalData($operator): bool
    {
        // TODO: Implementar verificación real cuando estén los módulos
        // Por ejemplo:
        // - Cargas asignadas
        // - Viajes en progreso
        // - Importaciones pendientes
        // - Exportaciones pendientes

        return false; // Por ahora, permitir eliminación
    }
}
