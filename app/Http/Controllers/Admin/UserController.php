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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'active' => 'boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        // Validaciones específicas por rol
        if ($request->role === 'company-admin') {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);
        } elseif ($request->role === 'external-operator') {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'document_number' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'position' => 'nullable|string|max:255',
                'can_import' => 'boolean',
                'can_export' => 'boolean',
                'can_transfer' => 'boolean',
            ]);
        } elseif ($request->role === 'internal-operator') {
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'document_number' => 'nullable|string|max:50',
                'phone' => 'nullable|string|max:20',
                'position' => 'nullable|string|max:255',
            ]);
        }

        try {
            // Crear usuario
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'active' => $request->boolean('active', true),
                'timezone' => $request->timezone ?? 'America/Argentina/Buenos_Aires',
            ]);

            // Asignar rol
            $user->assignRole($request->role);

            // Crear entidad relacionada según el rol
            if ($request->role === 'company-admin') {
                // El usuario es el administrador de la empresa
                $user->update([
                    'userable_type' => 'App\\Models\\Company',
                    'userable_id' => $request->company_id,
                ]);
            } elseif (in_array($request->role, ['external-operator', 'internal-operator'])) {
                // Crear operador
                $operator = Operator::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'document_number' => $request->document_number,
                    'phone' => $request->phone,
                    'position' => $request->position,
                    'company_id' => $request->role === 'external-operator' ? $request->company_id : null,
                    'type' => $request->role === 'external-operator' ? 'external' : 'internal',
                    'can_import' => $request->boolean('can_import', false),
                    'can_export' => $request->boolean('can_export', false),
                    'can_transfer' => $request->boolean('can_transfer', false),
                    'active' => true,
                    'created_date' => now(),
                ]);

                $user->update([
                    'userable_type' => 'App\\Models\\Operator',
                    'userable_id' => $operator->id,
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario creado correctamente.');

        } catch (\Exception $e) {
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'active' => 'boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        try {
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'active' => $request->boolean('active'),
                'timezone' => $request->timezone,
            ]);

            // Actualizar entidad relacionada si existe
            if ($user->userable) {
                if ($user->userable_type === 'App\\Models\\Operator') {
                    $request->validate([
                        'first_name' => 'required|string|max:255',
                        'last_name' => 'required|string|max:255',
                        'phone' => 'nullable|string|max:20',
                        'position' => 'nullable|string|max:255',
                        'can_import' => 'boolean',
                        'can_export' => 'boolean',
                        'can_transfer' => 'boolean',
                    ]);

                    $user->userable->update([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'phone' => $request->phone,
                        'position' => $request->position,
                        'can_import' => $request->boolean('can_import'),
                        'can_export' => $request->boolean('can_export'),
                        'can_transfer' => $request->boolean('can_transfer'),
                        'active' => $request->boolean('active'),
                    ]);
                }
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario actualizado correctamente.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al actualizar el usuario: ' . $e->getMessage());
        }
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
