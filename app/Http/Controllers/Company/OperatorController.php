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
use Spatie\Permission\Models\Role;

class OperatorController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de operadores de la empresa.
     */
    public function index(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Construir query base
        $query = $company->operators()->with(['user.roles']);

        // Filtros de búsqueda
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

        // Filtro por permisos
        if ($request->filled('permission')) {
            $permission = $request->permission;
            $query->where($permission, true);
        }

        // Ordenamiento
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSorts = ['first_name', 'last_name', 'position', 'created_at', 'last_access'];
        if (in_array($sortField, $allowedSorts)) {
            if ($sortField === 'last_access') {
                $query->leftJoin('users', 'operators.id', '=', 'users.userable_id')
                      ->where('users.userable_type', 'App\\Models\\Operator')
                      ->orderBy('users.last_access', $sortDirection)
                      ->select('operators.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        }

        $operators = $query->paginate(15)->withQueryString();

        // Estadísticas
        $stats = [
            'total' => $company->operators()->count(),
            'active' => $company->operators()->where('active', true)->count(),
            'with_import' => $company->operators()->where('can_import', true)->count(),
            'with_export' => $company->operators()->where('can_export', true)->count(),
            'with_transfer' => $company->operators()->where('can_transfer', true)->count(),
        ];

        return view('company.operators.index', compact('operators', 'stats', 'company'));
    }

    /**
     * Mostrar formulario para crear operador.
     */
    public function create()
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        return view('company.operators.create', compact('company'));
    }

    /**
     * Crear nuevo operador.
     */
    public function store(Request $request)
    {
        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
            'active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Crear operador
            $operator = Operator::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
                'company_id' => $company->id,
                'type' => 'external',
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
                'active' => $request->boolean('active', true),
                'created_date' => now(),
            ]);

            // Crear usuario asociado
            $user = User::create([
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'userable_type' => 'App\\Models\\Operator',
                'userable_id' => $operator->id,
                'active' => $request->boolean('active', true),
                'timezone' => 'America/Argentina/Buenos_Aires',
            ]);

            // Asignar rol de operador externo
            $user->assignRole('external-operator');

            DB::commit();

            return redirect()->route('company.operators.index')
                ->with('success', 'Operador creado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al crear el operador: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles del operador.
     */
    public function show(Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para ver este operador.');
        }

        $operator->load(['user.roles', 'company']);

        // Estadísticas del operador
        $stats = [
            'total_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'recent_shipments' => 0, // TODO: Implementar cuando esté el módulo de cargas
            'total_trips' => 0, // TODO: Implementar cuando esté el módulo de viajes
            'last_activity' => $operator->user?->last_access,
            'days_since_creation' => $operator->created_at->diffInDays(now()),
        ];

        // Actividad reciente del operador
        $recentActivity = $this->getOperatorActivity($operator);

        return view('company.operators.show', compact('operator', 'stats', 'recentActivity'));
    }

    /**
     * Mostrar formulario para editar operador.
     */
    public function edit(Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar este operador.');
        }

        $operator->load(['user']);

        return view('company.operators.edit', compact('operator', 'company'));
    }

    /**
     * Actualizar operador.
     */
    public function update(Request $request, Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para editar este operador.');
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
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

        try {
            DB::beginTransaction();

            // Actualizar operador
            $operator->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'document_number' => $request->document_number,
                'phone' => $request->phone,
                'position' => $request->position,
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
                ->with('success', 'Operador actualizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al actualizar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar operador.
     */
    public function destroy(Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para eliminar este operador.');
        }

        try {
            DB::beginTransaction();

            // Verificar si tiene cargas o viajes asociados
            // TODO: Implementar verificaciones cuando estén los módulos de cargas y viajes
            /*
            if ($operator->shipments()->exists()) {
                return back()->with('error', 'No se puede eliminar el operador porque tiene cargas asociadas.');
            }

            if ($operator->trips()->exists()) {
                return back()->with('error', 'No se puede eliminar el operador porque tiene viajes asociados.');
            }
            */

            // Eliminar usuario asociado
            if ($operator->user) {
                $operator->user->delete();
            }

            $operator->delete();

            DB::commit();

            return redirect()->route('company.operators.index')
                ->with('success', 'Operador eliminado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar permisos del operador.
     */
    public function permissions(Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para gestionar este operador.');
        }

        $operator->load(['user.roles', 'user.permissions']);

        // Permisos específicos del operador
        $operatorPermissions = [
            'can_import' => $operator->can_import,
            'can_export' => $operator->can_export,
            'can_transfer' => $operator->can_transfer,
        ];

        // Permisos especiales adicionales
        $specialPermissions = $operator->special_permissions ?? [];

        return view('company.operators.permissions', compact(
            'operator',
            'operatorPermissions',
            'specialPermissions'
        ));
    }

    /**
     * Actualizar permisos del operador.
     */
    public function updatePermissions(Request $request, Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
            abort(403, 'No tiene permisos para gestionar este operador.');
        }

        $request->validate([
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_transfer' => 'boolean',
            'special_permissions' => 'array',
            'special_permissions.*' => 'string',
        ]);

        try {
            $operator->update([
                'can_import' => $request->boolean('can_import', false),
                'can_export' => $request->boolean('can_export', false),
                'can_transfer' => $request->boolean('can_transfer', false),
                'special_permissions' => $request->special_permissions ?? [],
            ]);

            return back()->with('success', 'Permisos actualizados correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar los permisos: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar estado activo/inactivo del operador.
     */
    public function toggleStatus(Operator $operator)
    {
        $company = $this->getUserCompany();

        // Verificar que el operador pertenece a la empresa
        if (!$company || $operator->company_id !== $company->id) {
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
            return back()->with('success', "Operador {$status} correctamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cambiar el estado del operador.');
        }
    }

    /**
     * Obtener actividad reciente del operador.
     */
    private function getOperatorActivity($operator)
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

        // Creación del operador
        $activities[] = [
            'type' => 'created',
            'message' => 'Operador creado',
            'date' => $operator->created_at,
            'icon' => 'user-plus',
            'color' => 'blue',
        ];

        // Cambios en permisos (si se puede rastrear)
        if ($operator->updated_at->diffInDays($operator->created_at) > 0) {
            $activities[] = [
                'type' => 'updated',
                'message' => 'Información actualizada',
                'date' => $operator->updated_at,
                'icon' => 'edit',
                'color' => 'yellow',
            ];
        }

        // TODO: Agregar actividad de cargas cuando esté implementado
        // TODO: Agregar actividad de viajes cuando esté implementado
        // TODO: Agregar actividad de importaciones cuando esté implementado

        // Ordenar por fecha
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, 10);
    }
}
