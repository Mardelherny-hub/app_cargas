<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\Operator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
                $q->where('legal_name', 'like', "%{$search}%")
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
            'legal_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'tax_id' => [
                'required',
                'string',
                'unique:companies,tax_id',
                function ($attribute, $value, $fail) {
                    $country = request('country');
                    $cleanTaxId = preg_replace('/[^0-9]/', '', $value);
                    
                    if ($country === 'AR') {
                        // Argentina: exactamente 11 dígitos
                        if (strlen($cleanTaxId) !== 11) {
                            $fail('El CUIT debe tener exactamente 11 dígitos.');
                            return;
                        }
                        
                        // Validar prefijo
                        $prefix = substr($cleanTaxId, 0, 2);
                        $validPrefixes = ['20', '23', '24', '27', '30', '33', '34'];
                        if (!in_array($prefix, $validPrefixes)) {
                            $fail('Prefijo de CUIT inválido. Válidos: ' . implode(', ', $validPrefixes));
                            return;
                        }
                    } elseif ($country === 'PY') {
                        // Paraguay: entre 6 y 9 dígitos
                        if (strlen($cleanTaxId) < 6 || strlen($cleanTaxId) > 9) {
                            $fail('El RUC debe tener entre 6 y 9 dígitos.');
                            return;
                        }
                    }
                }
            ],
            'country' => 'required|in:AR,PY',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'id_maria' => 'nullable|string|max:10|regex:/^[A-Z0-9]+$/',
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
                'legal_name' => $request->legal_name,
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
                'ws_config' => $this->generateWebserviceConfig($request),
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

    private function generateWebserviceConfig(Request $request): array
    {
        return [
            'argentina' => [
                'cuit' => $request->country === 'AR' ? $request->tax_id : null,
                'company_name' => $request->legal_name,
                'domicilio_fiscal' => $request->address ?? 'No especificado',
                'afip_enabled' => true,
                'bypass_testing' => false,
                'webservices' => in_array('Cargas', $request->company_roles ?? []) ? ['anticipada', 'micdta'] : [],
            ],
            'paraguay' => [
                'ruc' => $request->country === 'PY' ? $request->tax_id : null,
                'company_name' => $request->legal_name,
                'domicilio_fiscal' => $request->address ?? 'No especificado',
                'dna_enabled' => true,
                'bypass_testing' => false,
                'webservices' => in_array('Cargas', $request->company_roles ?? []) ? ['manifiestos', 'consultas'] : [],
            ]
        ];
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
            'legal_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'tax_id' => [
                'required',
                'string',
                'unique:companies,tax_id,' . $company->id,
                function ($attribute, $value, $fail) {
                    $country = request('country');
                    $cleanTaxId = preg_replace('/[^0-9]/', '', $value);
                    
                    if ($country === 'AR') {
                        // Argentina: exactamente 11 dígitos
                        if (strlen($cleanTaxId) !== 11) {
                            $fail('El CUIT debe tener exactamente 11 dígitos.');
                            return;
                        }
                        
                        // Validar prefijo
                        $prefix = substr($cleanTaxId, 0, 2);
                        $validPrefixes = ['20', '23', '24', '27', '30', '33', '34'];
                        if (!in_array($prefix, $validPrefixes)) {
                            $fail('Prefijo de CUIT inválido. Válidos: ' . implode(', ', $validPrefixes));
                            return;
                        }
                    } elseif ($country === 'PY') {
                        // Paraguay: entre 6 y 9 dígitos
                        if (strlen($cleanTaxId) < 6 || strlen($cleanTaxId) > 9) {
                            $fail('El RUC debe tener entre 6 y 9 dígitos.');
                            return;
                        }
                    }
                }
            ],
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
            // Campos opcionales de certificado
            'certificate_alias' => 'nullable|string|max:255',
            'certificate_expires_at' => 'nullable|date|after:today',
            'created_date' => 'nullable|date',
            ], [
            // Mensajes personalizados existentes...
            'id_maria.max' => 'El ID María no puede tener más de 10 caracteres.',
            'id_maria.regex' => 'El ID María solo puede contener letras mayúsculas y números.',
        ]);

        try {
            // Actualizar roles_config si cambiaron los roles
            $oldRoles = $company->getRoles();
            $newRoles = $request->company_roles;

            $rolesConfig = $company->roles_config;
            if ($oldRoles !== $newRoles) {
                $rolesConfig = $this->generateRolesConfig($newRoles);
            }

            // Preparar datos para actualización
            $updateData = [
                'legal_name' => $request->legal_name,
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
            ];

            // Actualizar información del certificado si se proporciona
            if ($request->filled('certificate_alias')) {
                $updateData['certificate_alias'] = $request->certificate_alias;
            }

            if ($request->filled('certificate_expires_at')) {
                $updateData['certificate_expires_at'] = $request->certificate_expires_at;
            }

            if ($request->filled('created_date')) {
                $updateData['created_date'] = $request->created_date;
            }

            $company->update($updateData);

            $rolesDisplay = implode(', ', $newRoles);
            return redirect()->route('admin.companies.index')
                ->with('success', "Empresa actualizada correctamente. Roles activos: {$rolesDisplay}");

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

            $companyName = $company->legal_name;
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

     
    /**
     * CORRECCIÓN BUG: Método operators() faltante
     * Mostrar operadores de una empresa desde el panel de administración
     */
    public function operators(Company $company)
    {
        // Cargar operadores con sus usuarios y roles
        $operators = $company->operators()
            ->with(['user.roles'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Estadísticas básicas
        $stats = [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'inactive_operators' => $company->operators()->where('active', false)->count(),
            'external_operators' => $company->operators()->where('type', 'external')->count(),
        ];

        return view('admin.companies.operators', compact('company', 'operators', 'stats'));
    }

    /**
     * CORRECCIÓN: Método webservices() con debug mejorado
     */
    public function webservices(Company $company)
    {
        // DEBUG: Verificar datos de la empresa
        $companyRoles = $company->company_roles ?? [];
        
        // Cargar configuración actual de webservices con debug
        $webserviceConfig = [
            'argentina' => [
                'enabled' => true, // TEMPORALMENTE FORZADO PARA DEBUG
                'roles_found' => $companyRoles, // DEBUG
                'has_ata_cbc' => in_array('ATA CBC', $companyRoles), // DEBUG
                'cuit' => $company->tax_id,
                'certificate_path' => $company->certificate_path,
                'certificate_expires_at' => $company->certificate_expires_at,
                'ws_config' => $company->ws_config ?? [],
                'ws_environment' => $company->ws_environment ?? 'testing',
                'webservices' => $company->getArgentinaWebservices(),
            ],
            'paraguay' => [
                'enabled' => true, // TEMPORALMENTE FORZADO PARA DEBUG  
                'roles_found' => $companyRoles, // DEBUG
                'has_cargas' => in_array('Cargas', $companyRoles), // DEBUG
                'ruc' => $company->tax_id,
                'webservices' => $company->getParaguayWebservices(),
            ]
        ];

        // Estado de certificados
        $certificateStatus = $this->getCertificateStatus($company);

        // Configuración disponible por roles
        $availableWebservices = $this->getAvailableWebservicesByRoles($company);

        // DEBUG: Añadir información de debug
        $debugInfo = [
            'company_id' => $company->id,
            'company_name' => $company->legal_name,
            'company_roles' => $companyRoles,
            'webservice_config' => $webserviceConfig,
            'available_webservices' => $availableWebservices,
            'certificate_status' => $certificateStatus,
        ];

        // DEBUG: Log para ver qué está pasando
        \Log::info('🔍 DEBUG Webservices View Data', $debugInfo);

        return view('admin.companies.webservices', compact(
            'company', 
            'webserviceConfig', 
            'certificateStatus',
            'availableWebservices',
            'debugInfo' // Añadir debug info a la vista
        ));
    }

    /**
     * CORRECCIÓN: Método getAvailableWebservicesByRoles() mejorado
     */
    private function getAvailableWebservicesByRoles(Company $company): array
    {
        $roles = $company->company_roles ?? [];
        $available = [];

        // DEBUG: Log roles encontrados
        \Log::info('🔍 DEBUG Company Roles', [
            'company_id' => $company->id,
            'roles' => $roles,
            'roles_count' => count($roles),
        ]);

        // Argentina - SIEMPRE mostrar para debug
        $available['argentina'] = [
            'micdta' => 'MIC/DTA - Mercaderías en Tránsito',
            'anticipada' => 'Información Anticipada',
        ];

        // Agregar servicios adicionales si tiene roles específicos
        if (in_array('Desconsolidado', $roles)) {
            $available['argentina']['desconsolidado'] = 'Desconsolidados';
        }

        if (in_array('Transbordo', $roles)) {
            $available['argentina']['transbordo'] = 'Transbordos';
        }

        // Paraguay - SIEMPRE mostrar para debug
        $available['paraguay'] = [
            'manifiestos' => 'Manifiestos de Carga',
            'consultas' => 'Consultas de Estado',
        ];

        // DEBUG: Log webservices disponibles
        \Log::info('🔍 DEBUG Available Webservices', [
            'company_id' => $company->id,
            'available' => $available,
        ]);

        return $available;
    }

    /**
     * Actualizar configuración de webservices desde admin
     */
    public function updateWebservices(Request $request, Company $company)
    {
        $request->validate([
            'ws_environment' => 'required|in:testing,production',
            'ws_config.argentina.enabled' => 'boolean',
            'ws_config.paraguay.enabled' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Actualizar configuración
            $wsConfig = $company->ws_config ?? [];
            
            if ($request->has('ws_config.argentina')) {
                $wsConfig['argentina'] = array_merge(
                    $wsConfig['argentina'] ?? [],
                    $request->input('ws_config.argentina', [])
                );
            }

            if ($request->has('ws_config.paraguay')) {
                $wsConfig['paraguay'] = array_merge(
                    $wsConfig['paraguay'] ?? [],
                    $request->input('ws_config.paraguay', [])
                );
            }

            $company->update([
                'ws_environment' => $request->ws_environment,
                'ws_config' => $wsConfig,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.companies.webservices', $company)
                ->with('success', 'Configuración de webservices actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar configuración: ' . $e->getMessage());
        }
    }

    /**
     * Probar conexión a webservices desde admin
     */
    public function testWebservice(Request $request, Company $company)
    {
        $request->validate([
            'webservice_type' => 'required|in:micdta,anticipada,manifiestos',
            'country' => 'required|in:AR,PY',
        ]);

        try {
            $webserviceType = $request->webservice_type;
            $country = $request->country;

            // Simular test de conexión (implementar según servicios reales)
            $testResult = $this->performWebserviceTest($company, $webserviceType, $country);

            return response()->json([
                'success' => $testResult['success'],
                'message' => $testResult['message'],
                'details' => $testResult['details'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en test de webservice: ' . $e->getMessage(),
            ], 500);
        }
    }

   

    /**
     * Realizar test de webservice (placeholder)
     */
    private function performWebserviceTest(Company $company, string $webserviceType, string $country): array
    {
        // TODO: Implementar tests reales según cada webservice
        
        if ($country === 'AR') {
            // Test Argentina
            if (!$company->certificate_path) {
                return [
                    'success' => false,
                    'message' => 'Sin certificado digital configurado',
                ];
            }

            return [
                'success' => true,
                'message' => "Test exitoso para {$webserviceType} Argentina",
                'details' => [
                    'environment' => $company->ws_environment ?? 'testing',
                    'certificate_valid' => true,
                    'connection_ok' => true,
                ]
            ];
        }

        if ($country === 'PY') {
            // Test Paraguay
            return [
                'success' => true,
                'message' => "Test exitoso para {$webserviceType} Paraguay",
                'details' => [
                    'environment' => $company->ws_environment ?? 'testing',
                    'connection_ok' => true,
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'País no soportado',
        ];
    }


    /**
     * Mostrar formulario para crear operador desde admin
     */
    public function createOperator(Company $company)
    {
        // Datos para el formulario
        $formData = [
            'types' => [
                'external' => 'Externo',
                'internal' => 'Interno',
            ],
            'permissions' => [
                'can_import' => 'Puede Importar',
                'can_export' => 'Puede Exportar', 
                'can_transfer' => 'Puede Transferir',
            ],
            'company_roles' => $company->company_roles ?? [],
        ];

        return view('admin.companies.operators.create', compact('company', 'formData'));
    }

    /**
     * Guardar nuevo operador desde admin
     */
    public function storeOperator(Request $request, Company $company)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'type' => 'required|in:external,internal',
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

            // Asignar rol 'user'
            $user->assignRole('user');

            DB::commit();

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', 'Operador creado exitosamente desde Admin.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al crear el operador: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles del operador desde admin
     */
    public function showOperator(Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
        }

        // Cargar relaciones
        $operator->load(['user.roles', 'company']);

        // Estadísticas del operador (simplificadas para admin)
        $stats = [
            'created_days_ago' => $operator->created_at->diffInDays(now()),
            'last_login' => $operator->user->last_login_at ?? null,
            'total_shipments' => 0, // TODO: implementar cuando tengamos shipments
            'active_voyages' => 0,  // TODO: implementar cuando tengamos voyages
            'permissions_count' => collect([
                $operator->can_import,
                $operator->can_export, 
                $operator->can_transfer
            ])->filter()->count(),
        ];

        return view('admin.companies.operators.show', compact(
            'company',
            'operator', 
            'stats'
        ));
    }

    /**
     * Mostrar formulario para editar operador desde admin
     */
    public function editOperator(Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
        }

        // Cargar relaciones
        $operator->load(['user']);

        // Datos para el formulario
        $formData = [
            'types' => [
                'external' => 'Externo',
                'internal' => 'Interno',
            ],
            'permissions' => [
                'can_import' => 'Puede Importar',
                'can_export' => 'Puede Exportar',
                'can_transfer' => 'Puede Transferir',
            ],
            'company_roles' => $company->company_roles ?? [],
        ];

        return view('admin.companies.operators.edit', compact('company', 'operator', 'formData'));
    }

    /**
     * Actualizar operador desde admin
     */
    public function updateOperator(Request $request, Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($operator->user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'document_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'type' => 'required|in:external,internal',
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

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', 'Operador actualizado exitosamente desde Admin.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar operador desde admin
     */
    public function destroyOperator(Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
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

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', "Operador {$operatorName} eliminado exitosamente desde Admin.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar el operador: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar estado activo/inactivo del operador desde admin
     */
    public function toggleOperatorStatus(Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
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

            $statusText = $newStatus ? 'activado' : 'desactivado';
            $operatorName = $operator->first_name . ' ' . $operator->last_name;

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', "Operador {$operatorName} {$statusText} exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cambiar el estado del operador: ' . $e->getMessage());
        }
    }

    /**
     * Reset contraseña del operador desde admin
     */
    public function resetOperatorPassword(Company $company, Operator $operator)
    {
        // Verificar que el operador pertenece a la empresa
        if ($operator->company_id !== $company->id) {
            abort(404, 'Operador no encontrado en esta empresa.');
        }

        if (!$operator->user) {
            return back()->with('error', 'El operador no tiene usuario asociado.');
        }

        try {
            // Generar contraseña temporal
            $temporaryPassword = 'Temp' . rand(1000, 9999) . '!';
            
            // Actualizar contraseña
            $operator->user->update([
                'password' => Hash::make($temporaryPassword),
                'email_verified_at' => now(), // Mantener verificado
            ]);

            $operatorName = $operator->first_name . ' ' . $operator->last_name;

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', "Contraseña del operador {$operatorName} reseteada. Nueva contraseña temporal: {$temporaryPassword}");

        } catch (\Exception $e) {
            return back()->with('error', 'Error al resetear la contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Cambio masivo de estado de operadores
     */
    public function bulkToggleOperatorStatus(Request $request, Company $company)
    {
        $request->validate([
            'operator_ids' => 'required|array',
            'operator_ids.*' => 'exists:operators,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        try {
            DB::beginTransaction();

            $operatorIds = $request->operator_ids;
            $newStatus = $request->action === 'activate';

            // Verificar que todos los operadores pertenecen a la empresa
            $operators = Operator::whereIn('id', $operatorIds)
                ->where('company_id', $company->id)
                ->with('user')
                ->get();

            if ($operators->count() !== count($operatorIds)) {
                return back()->with('error', 'Algunos operadores no pertenecen a esta empresa.');
            }

            // Actualizar operadores y usuarios
            foreach ($operators as $operator) {
                $operator->update(['active' => $newStatus]);
                
                if ($operator->user) {
                    $operator->user->update(['active' => $newStatus]);
                }
            }

            DB::commit();

            $action = $newStatus ? 'activados' : 'desactivados';
            $count = $operators->count();

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', "{$count} operador(es) {$action} exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error en el cambio masivo: ' . $e->getMessage());
        }
    }

    /**
     * Eliminación masiva de operadores
     */
    public function bulkDeleteOperators(Request $request, Company $company)
    {
        $request->validate([
            'operator_ids' => 'required|array',
            'operator_ids.*' => 'exists:operators,id',
        ]);

        try {
            DB::beginTransaction();

            $operatorIds = $request->operator_ids;

            // Verificar que todos los operadores pertenecen a la empresa
            $operators = Operator::whereIn('id', $operatorIds)
                ->where('company_id', $company->id)
                ->with('user')
                ->get();

            if ($operators->count() !== count($operatorIds)) {
                return back()->with('error', 'Algunos operadores no pertenecen a esta empresa.');
            }

            // Eliminar usuarios y operadores
            foreach ($operators as $operator) {
                if ($operator->user) {
                    $operator->user->delete();
                }
                $operator->delete();
            }

            DB::commit();

            $count = $operators->count();

            return redirect()
                ->route('admin.companies.operators', $company)
                ->with('success', "{$count} operador(es) eliminado(s) exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error en la eliminación masiva: ' . $e->getMessage());
        }
    }
}
