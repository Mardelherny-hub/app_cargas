<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompanyController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de empresas con filtros actualizados para Roberto.
     */
    public function index(Request $request)
    {
        $query = Company::withCount([
            'users',
            'users as admin_count' => function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'company-admin');
                });
            }
        ]);

        // NUEVO: Filtro por roles de empresa (Roberto's key requirement)
        if ($request->filled('role')) {
            $query->whereJsonContains('company_roles', $request->role);
        }

        // Filtro por país
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Filtro por estado general
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('active', true);
                    break;
                case 'inactive':
                    $query->where('active', false);
                    break;
                case 'cert_expired':
                    $query->whereNotNull('certificate_expires_at')
                          ->where('certificate_expires_at', '<', now())
                          ->where('active', true);
                    break;
                case 'no_cert':
                    $query->where('active', true)->whereNull('certificate_path');
                    break;
            }
        }

        // Filtros legacy (para compatibilidad)
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'expired_certificates':
                    $query->whereNotNull('certificate_expires_at')
                          ->where('certificate_expires_at', '<', now())
                          ->where('active', true);
                    break;
                case 'without_certificates':
                    $query->where('active', true)->whereNull('certificate_path');
                    break;
                case 'expiring_soon':
                    $query->whereNotNull('certificate_expires_at')
                          ->where('certificate_expires_at', '>=', now())
                          ->where('certificate_expires_at', '<=', now()->addDays(30));
                    break;
                case 'argentina':
                    $query->where('country', 'AR');
                    break;
                case 'paraguay':
                    $query->where('country', 'PY');
                    break;
            }
        }

        // Búsqueda general
        if ($request->filled('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $companies = $query->orderBy('created_at', 'desc')->paginate(15);

        // NUEVO: Estadísticas actualizadas para Roberto
        $stats = $this->getCompaniesStats();

        return view('admin.companies.index', compact('companies', 'stats'));
    }

    /**
     * NUEVO: Obtener estadísticas de empresas para la vista.
     */
    private function getCompaniesStats(): array
    {
        return [
            'total' => Company::count(),
            'active' => Company::where('active', true)->count(),
            'cert_expiring' => Company::whereNotNull('certificate_expires_at')
                                     ->where('certificate_expires_at', '>=', now())
                                     ->where('certificate_expires_at', '<=', now()->addDays(30))
                                     ->count(),
            'cert_expired' => Company::whereNotNull('certificate_expires_at')
                                    ->where('certificate_expires_at', '<', now())
                                    ->count(),
            'with_certificates' => Company::whereNotNull('certificate_path')->count(),
            'without_certificates' => Company::where('active', true)
                                            ->whereNull('certificate_path')
                                            ->count(),
            'roles_stats' => $this->getRolesStats(),
        ];
    }

    /**
     * NUEVO: Obtener estadísticas por roles de empresa.
     */
    private function getRolesStats(): array
    {
        $companies = Company::where('active', true)->get();

        $stats = [
            'Cargas' => 0,
            'Desconsolidador' => 0,
            'Transbordos' => 0,
            'multiple_roles' => 0,
            'no_roles' => 0,
        ];

        foreach ($companies as $company) {
            $roles = $company->getRoles();

            if (empty($roles)) {
                $stats['no_roles']++;
                continue;
            }

            if (count($roles) > 1) {
                $stats['multiple_roles']++;
                continue;
            }

            $role = $roles[0];
            if (isset($stats[$role])) {
                $stats[$role]++;
            }
        }

        return $stats;
    }

    /**
     * Mostrar formulario para crear empresa.
     */
    public function create()
    {
        $availableRoles = Company::getAvailableRoles();
        return view('admin.companies.create', compact('availableRoles'));
    }

    /**
     * Crear nueva empresa con roles de negocio.
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'tax_id' => 'required|string|size:11|unique:companies,tax_id',
            'country' => 'required|in:AR,PY',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'company_roles' => 'required|array|min:1',
            'company_roles.*' => 'in:Cargas,Desconsolidador,Transbordos',
            'ws_environment' => 'required|in:testing,production',
            'ws_active' => 'boolean',
            'active' => 'boolean',
        ]);

        try {
            // NUEVO: Configurar roles_config automáticamente según los roles seleccionados
            $rolesConfig = $this->generateRolesConfig($request->company_roles);

            $company = Company::create([
                'business_name' => $request->business_name,
                'commercial_name' => $request->commercial_name,
                'tax_id' => $request->tax_id,
                'country' => $request->country,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'company_roles' => $request->company_roles,
                'roles_config' => $rolesConfig,
                'ws_environment' => $request->ws_environment,
                'ws_active' => $request->boolean('ws_active', false),
                'active' => $request->boolean('active', true),
                'created_date' => now(),
            ]);

            return redirect()->route('admin.companies.index')
                ->with('success', 'Empresa creada correctamente con roles: ' . implode(', ', $request->company_roles));

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al crear la empresa: ' . $e->getMessage());
        }
    }

    /**
     * NUEVO: Generar configuración automática según roles seleccionados.
     */
    private function generateRolesConfig(array $roles): array
    {
        $config = [
            'webservices' => [],
            'features' => [],
        ];

        foreach ($roles as $role) {
            switch ($role) {
                case 'Cargas':
                    $config['webservices'] = array_merge($config['webservices'], ['anticipada', 'micdta']);
                    $config['features'] = array_merge($config['features'], ['contenedores', 'manifiestos']);
                    break;
                case 'Desconsolidador':
                    $config['webservices'][] = 'desconsolidados';
                    $config['features'] = array_merge($config['features'], ['titulos_madre', 'titulos_hijos']);
                    break;
                case 'Transbordos':
                    $config['webservices'][] = 'transbordos';
                    $config['features'] = array_merge($config['features'], ['barcazas', 'tracking_posicion']);
                    break;
            }
        }

        $config['webservices'] = array_unique($config['webservices']);
        $config['features'] = array_unique($config['features']);

        return $config;
    }

    /**
     * Mostrar detalles de la empresa con información de roles.
     */
    public function show(Company $company)
    {
        $company->load(['users.roles']);

        // Estadísticas de la empresa actualizadas
        $stats = [
            'total_users' => $company->users()->count(),
            'active_users' => $company->users()->where('active', true)->count(),
            'admin_users' => $company->users()->whereHas('roles', function ($query) {
                $query->where('name', 'company-admin');
            })->count(),
            'regular_users' => $company->users()->whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })->count(),
            'certificate_status' => $this->getCertificateStatus($company),
            'roles_info' => $this->getCompanyRolesInfo($company),
        ];

        return view('admin.companies.show', compact('company', 'stats'));
    }

    /**
     * NUEVO: Obtener información detallada de los roles de la empresa.
     */
    private function getCompanyRolesInfo(Company $company): array
    {
        $roles = $company->getRoles();
        $webservices = $company->getAvailableWebservices();
        $features = $company->getAvailableFeatures();
        $operations = $company->getAvailableOperations();

        return [
            'roles' => $roles,
            'webservices' => $webservices,
            'features' => $features,
            'operations' => $operations,
            'can_transfer' => $company->canTransferToCompany(),
            'is_ready' => $company->isReadyToOperate(),
            'errors' => $company->validateRoleConfiguration(),
        ];
    }

    /**
     * Mostrar formulario para editar empresa.
     */
    public function edit(Company $company)
    {
        $availableRoles = Company::getAvailableRoles();
        return view('admin.companies.edit', compact('company', 'availableRoles'));
    }

    /**
     * Actualizar empresa con soporte para roles.
     */
    public function update(Request $request, Company $company)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'tax_id' => 'required|string|size:11|unique:companies,tax_id,' . $company->id,
            'country' => 'required|in:AR,PY',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'company_roles' => 'required|array|min:1',
            'company_roles.*' => 'in:Cargas,Desconsolidador,Transbordos',
            'ws_environment' => 'required|in:testing,production',
            'ws_active' => 'boolean',
            'active' => 'boolean',
        ]);

        try {
            // NUEVO: Actualizar roles_config si cambiaron los roles
            $oldRoles = $company->getRoles();
            $newRoles = $request->company_roles;

            $rolesConfig = $company->roles_config;
            if ($oldRoles !== $newRoles) {
                $rolesConfig = $this->generateRolesConfig($newRoles);
            }

            $company->update([
                'business_name' => $request->business_name,
                'commercial_name' => $request->commercial_name,
                'tax_id' => $request->tax_id,
                'country' => $request->country,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'company_roles' => $newRoles,
                'roles_config' => $rolesConfig,
                'ws_environment' => $request->ws_environment,
                'ws_active' => $request->boolean('ws_active'),
                'active' => $request->boolean('active'),
            ]);

            return redirect()->route('admin.companies.index')
                ->with('success', 'Empresa actualizada correctamente.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al actualizar la empresa: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar empresa (solo si no tiene usuarios).
     */
    public function destroy(Company $company)
    {
        try {
            // Verificar que no tenga usuarios asociados
            if ($company->users()->count() > 0) {
                return back()->with('error', 'No se puede eliminar una empresa con usuarios asociados.');
            }

            // Eliminar certificado si existe
            if ($company->certificate_path && Storage::exists($company->certificate_path)) {
                Storage::delete($company->certificate_path);
            }

            $companyName = $company->business_name;
            $company->delete();

            return redirect()->route('admin.companies.index')
                ->with('success', "Empresa '{$companyName}' eliminada correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar la empresa: ' . $e->getMessage());
        }
    }

    /**
     * NUEVO: Cambiar estado activo/inactivo.
     */
    public function toggleStatus(Company $company)
    {
        try {
            $company->update(['active' => !$company->active]);

            $status = $company->active ? 'activada' : 'desactivada';
            return back()->with('success', "Empresa {$status} correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', 'Error al cambiar el estado de la empresa.');
        }
    }

    /**
     * NUEVO: Actualizar roles de empresa.
     */
    public function updateRoles(Request $request, Company $company)
    {
        $request->validate([
            'company_roles' => 'required|array|min:1',
            'company_roles.*' => 'in:Cargas,Desconsolidador,Transbordos',
        ]);

        try {
            $newRoles = $request->company_roles;
            $rolesConfig = $this->generateRolesConfig($newRoles);

            $company->update([
                'company_roles' => $newRoles,
                'roles_config' => $rolesConfig,
            ]);

            return back()->with('success', 'Roles de empresa actualizados: ' . implode(', ', $newRoles));

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar los roles: ' . $e->getMessage());
        }
    }

    /**
     * Gestión de certificados (mantener funcionalidad existente).
     */
    public function certificates(Company $company)
    {
        return view('admin.companies.certificates', compact('company'));
    }

    /**
     * Subir certificado digital.
     */
    public function uploadCertificate(Request $request, Company $company)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string',
            'alias' => 'nullable|string|max:255',
            'expires_at' => 'required|date|after:today',
        ]);

        try {
            // Eliminar certificado anterior si existe
            if ($company->certificate_path && Storage::exists($company->certificate_path)) {
                Storage::delete($company->certificate_path);
            }

            // Guardar nuevo certificado
            $path = $request->file('certificate')->store('certificates');

            $company->update([
                'certificate_path' => $path,
                'certificate_password' => $request->password, // Se encripta automáticamente
                'certificate_alias' => $request->alias,
                'certificate_expires_at' => $request->expires_at,
            ]);

            return back()->with('success', 'Certificado subido correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al subir el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar certificado digital.
     */
    public function deleteCertificate(Company $company)
    {
        try {
            $company->deleteCertificate();
            return back()->with('success', 'Certificado eliminado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estado del certificado (método helper).
     */
    private function getCertificateStatus(Company $company): array
    {
        return [
            'has_certificate' => $company->has_certificate,
            'is_expired' => $company->is_certificate_expired,
            'is_expiring_soon' => $company->is_certificate_expiring_soon,
            'status' => $company->certificate_status,
            'days_to_expiry' => $company->certificate_days_to_expiry,
            'expires_at' => $company->certificate_expires_at,
        ];
    }
}
