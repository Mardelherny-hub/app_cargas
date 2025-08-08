<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de usuarios.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'userable']);

        // Filtros
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'inactive_with_active_companies':
                    $query->where('active', false)
                          ->where('userable_type', 'App\\Models\\Company')
                          ->whereHas('userable', function ($q) {
                              $q->where('active', true);
                          });
                    break;
                case 'inactive_access':
                    $query->where('active', true)
                          ->where(function ($q) {
                              $q->whereNull('last_access')
                                ->orWhere('last_access', '<', now()->subDays(90));
                          });
                    break;
                case 'active':
                    $query->where('active', true);
                    break;
                case 'inactive':
                    $query->where('active', false);
                    break;
            }
        }

        // Búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por rol
        if ($request->has('role') && !empty($request->role)) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Mostrar formulario para crear usuario.
     */
    public function create()
    {
        $roles = Role::all();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();

        return view('admin.users.create', compact('roles', 'companies'));
    }

    /**
     * Crear nuevo usuario.
     */
    public function store(Request $request)
    {
        // 1. AGREGAR DEBUG: Ver qué datos llegan
        \Log::info('AdminUserController store() - Datos recibidos:', [
            'role' => $request->role,
            'operator_type' => $request->operator_type,
            'company_id' => $request->company_id,
            'operator_company_id' => $request->operator_company_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'all_request' => $request->all()
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:super-admin,company-admin,user',
            'active' => 'boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        // Validaciones específicas por rol
        if ($request->role === 'company-admin') {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);
        } elseif ($request->role === 'user') {
            \Log::info('AdminUserController - Validando rol user');
            
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'document_number' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'position' => 'required|string|max:255',
                'operator_type' => 'required|in:external',
                'can_import' => 'boolean',
                'can_export' => 'boolean',
                'can_transfer' => 'boolean',
            ]);

            if ($request->operator_type === 'external') {
                $request->validate([
                    'operator_company_id' => 'required|exists:companies,id',
                ]);
            }

            if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
                \Log::warning('AdminUserController - Sin permisos seleccionados');
                return back()->withInput()
                    ->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
            }
        }

        try {
            \Log::info('AdminUserController - Iniciando creación de usuario');
            
            // Crear usuario
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'active' => $request->boolean('active', true),
                'timezone' => $request->timezone ?? 'America/Argentina/Buenos_Aires',
            ]);

            \Log::info('AdminUserController - Usuario creado:', ['user_id' => $user->id]);

            // Asignar rol
            $user->assignRole($request->role);
            \Log::info('AdminUserController - Rol asignado:', ['role' => $request->role]);

            // Crear entidad relacionada según el rol
            if ($request->role === 'company-admin') {
                $user->update([
                    'userable_type' => 'App\\Models\\Company',
                    'userable_id' => $request->company_id,
                ]);
                \Log::info('AdminUserController - Company admin configurado');

            } elseif ($request->role === 'user') {
                \Log::info('AdminUserController - Creando operador para user');
                
                // DATOS PARA CREAR OPERADOR
                $operatorData = [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'document_number' => $request->document_number,
                    'phone' => $request->phone,
                    'position' => $request->position,
                    'company_id' => $request->operator_type === 'external' ? $request->operator_company_id : null,
                    'type' => $request->operator_type,
                    'can_import' => $request->boolean('can_import', false),
                    'can_export' => $request->boolean('can_export', false),
                    'can_transfer' => $request->boolean('can_transfer', false),
                    'active' => true,
                    'created_date' => now(),
                ];

                \Log::info('AdminUserController - Datos para crear operador:', $operatorData);

                // CREAR OPERADOR
                $operator = Operator::create($operatorData);
                \Log::info('AdminUserController - Operador creado:', ['operator_id' => $operator->id]);

                // CONECTAR USUARIO CON OPERADOR
                $user->update([
                    'userable_type' => 'App\\Models\\Operator',
                    'userable_id' => $operator->id,
                ]);
                \Log::info('AdminUserController - Usuario conectado con operador');
            }

            \Log::info('AdminUserController - Proceso completado exitosamente');

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario creado correctamente.');

        } catch (\Exception $e) {
            \Log::error('AdminUserController - Error en creación:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()
                ->with('error', 'Error al crear el usuario: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles del usuario.
     */
    public function show(User $user)
    {
        $user->load(['roles', 'userable']);

        return view('admin.users.show', compact('user'));
    }

    /**
     * Mostrar formulario para editar usuario.
     */
    public function edit(User $user)
    {
        $user->load(['roles', 'userable']);
        $roles = Role::all();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'companies'));
    }

    /**
     * Actualizar usuario.
     */
public function update(Request $request, User $user)
{
    \Log::info('AdminUserController update() - Datos recibidos:', [
        'user_id' => $user->id,
        'current_role' => $user->roles->first()?->name,
        'new_role' => $request->role,
        'userable_type' => $user->userable_type,
        'has_password' => $request->filled('password'),
        'password_length' => $request->password ? strlen($request->password) : 0,
        'confirmation_length' => $request->password_confirmation ? strlen($request->password_confirmation) : 0,
    ]);

    // LIMPIAR CAMPOS DE CONTRASEÑA SI ESTÁN VACÍOS
    if (!$request->filled('password')) {
        $request->merge([
            'password' => null,
            'password_confirmation' => null
        ]);
        \Log::info('AdminUserController update() - Campos de contraseña limpiados');
    }

    // Validaciones básicas (SIN password aquí)
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
        'role' => 'required|in:super-admin,company-admin,user',
        'active' => 'boolean',
        'timezone' => 'nullable|string|max:50',
    ];

    // VALIDACIÓN CONDICIONAL DE PASSWORD
    if ($request->filled('password')) {
        $rules['password'] = 'required|string|min:8|confirmed';
        \Log::info('AdminUserController update() - Validando contraseña nueva');
    } else {
        \Log::info('AdminUserController update() - Sin cambio de contraseña');
    }

    $request->validate($rules);

    // Validaciones específicas por rol (igual que store)
    if ($request->role === 'company-admin') {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);
    } elseif ($request->role === 'user') {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:255',
            'operator_type' => 'required|in:external',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
        ]);

        $request->validate([
            'operator_company_id' => 'required|exists:companies,id',
        ]);

        if (!$request->boolean('can_import') && !$request->boolean('can_export') && !$request->boolean('can_transfer')) {
            return back()->withInput()
                ->with('error', 'El operador debe tener al menos un permiso (importar, exportar o transferir).');
        }
    }

    try {
        DB::beginTransaction();
        
        \Log::info('AdminUserController update() - Iniciando actualización');

        // Preparar datos para actualizar usuario
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'active' => $request->boolean('active'),
            'timezone' => $request->timezone,
        ];

        // Agregar contraseña solo si se proporciona
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
            \Log::info('AdminUserController update() - Contraseña incluida en actualización');
        }

        // Actualizar datos del usuario
        $user->update($userData);

        // Actualizar rol si cambió
        $currentRole = $user->roles->first()?->name;
        if ($currentRole !== $request->role) {
            $user->syncRoles([$request->role]);
            \Log::info('AdminUserController update() - Rol cambiado', [
                'from' => $currentRole,
                'to' => $request->role
            ]);
        }

        // MANEJO DE ENTIDADES RELACIONADAS
        $this->handleUserEntity($user, $request);

        DB::commit();
        \Log::info('AdminUserController update() - Actualización completada');

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('AdminUserController update() - Error:', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return back()->withInput()
            ->with('error', 'Error al actualizar el usuario: ' . $e->getMessage());
    }
}

/**
 * Manejar la entidad relacionada del usuario según su rol.
 */
private function handleUserEntity(User $user, Request $request)
{
    $newRole = $request->role;
    $currentEntityType = $user->userable_type;

    \Log::info('AdminUserController - Manejando entidad relacionada', [
        'new_role' => $newRole,
        'current_entity' => $currentEntityType
    ]);

    if ($newRole === 'super-admin') {
        // Super admin no tiene entidad relacionada
        if ($user->userable) {
            $user->userable->delete(); // Eliminar entidad anterior
        }
        $user->update([
            'userable_type' => null,
            'userable_id' => null,
        ]);

    } elseif ($newRole === 'company-admin') {
        // Administrador de empresa
        if ($currentEntityType === 'App\\Models\\Operator') {
            // Cambio de operador a company-admin: eliminar operador
            $user->userable->delete();
        }
        
        $user->update([
            'userable_type' => 'App\\Models\\Company',
            'userable_id' => $request->company_id,
        ]);

    } elseif ($newRole === 'user') {
        // Usuario operador
        if ($currentEntityType === 'App\\Models\\Operator') {
            // Ya es operador: actualizar datos
            $this->updateOperator($user->userable, $request);
        } else {
            // No es operador: crear nuevo operador
            if ($user->userable && $currentEntityType === 'App\\Models\\Company') {
                // Era company-admin, no eliminamos la empresa
            }
            
            $operator = $this->createOperator($request);
            $user->update([
                'userable_type' => 'App\\Models\\Operator',
                'userable_id' => $operator->id,
            ]);
        }
    }
}

/**
 * Actualizar operador existente.
 */
private function updateOperator(Operator $operator, Request $request)
{
    \Log::info('AdminUserController - Actualizando operador existente', ['operator_id' => $operator->id]);

    $operator->update([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'document_number' => $request->document_number,
        'phone' => $request->phone,
        'position' => $request->position,
        'company_id' => $request->$request->operator_company_id,
        'type' => $request->operator_type,
        'can_import' => $request->boolean('can_import', false),
        'can_export' => $request->boolean('can_export', false),
        'can_transfer' => $request->boolean('can_transfer', false),
        'active' => $request->boolean('active', true),
    ]);
}

/**
 * Crear nuevo operador.
 */
private function createOperator(Request $request): Operator
{
    \Log::info('AdminUserController - Creando nuevo operador');

    return Operator::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'document_number' => $request->document_number,
        'phone' => $request->phone,
        'position' => $request->position,
        'company_id' => $request->operator_type === 'external' ? $request->operator_company_id : null,
        'type' => $request->operator_type,
        'can_import' => $request->boolean('can_import', false),
        'can_export' => $request->boolean('can_export', false),
        'can_transfer' => $request->boolean('can_transfer', false),
        'active' => true,
        'created_date' => now(),
    ]);
}
    /**
     * Eliminar usuario.
     */
    public function destroy(User $user)
    {
        try {
            // No permitir eliminar super-admin si es el único
            if ($user->hasRole('super-admin')) {
                $superAdminCount = User::whereHas('roles', function ($query) {
                    $query->where('name', 'super-admin');
                })->where('active', true)->count();

                if ($superAdminCount <= 1) {
                    return back()->with('error', 'No se puede eliminar el último super administrador.');
                }
            }

            // Eliminar entidad relacionada si existe
            if ($user->userable) {
                $user->userable->delete();
            }

            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario eliminado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar el usuario: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar estado activo/inactivo del usuario.
     */
    public function toggleStatus(User $user)
    {
        try {
            $user->update(['active' => !$user->active]);

            $status = $user->active ? 'activado' : 'desactivado';
            return back()->with('success', "Usuario {$status} correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', 'Error al cambiar el estado del usuario.');
        }
    }

    /**
     * Resetear contraseña del usuario.
     */
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return back()->with('success', 'Contraseña actualizada correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar la contraseña.');
        }
    }

    /**
     * Mostrar permisos del usuario.
     */
    public function permissions(User $user)
    {
        $user->load(['roles.permissions', 'permissions']);

        return view('admin.users.permissions', compact('user'));
    }

    /**
     * Actualizar permisos del usuario.
     */
    public function updatePermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $user->syncPermissions($request->permissions ?? []);

            return back()->with('success', 'Permisos actualizados correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar los permisos.');
        }
    }
}
