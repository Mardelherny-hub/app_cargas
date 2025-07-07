<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CompanyController extends Controller
{
    use UserHelper;

    /**
     * Mostrar lista de empresas.
     */
    public function index(Request $request)
    {
        $query = Company::with(['user', 'operators']);

        // Filtros
        if ($request->has('filter')) {
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
                case 'active':
                    $query->where('active', true);
                    break;
                case 'inactive':
                    $query->where('active', false);
                    break;
                case 'argentina':
                    $query->where('country', 'AR');
                    break;
                case 'paraguay':
                    $query->where('country', 'PY');
                    break;
            }
        }

        // Búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $companies = $query->orderBy('created_at', 'desc')->paginate(15);

        // Estadísticas para el header
        $stats = [
            'total' => Company::count(),
            'active' => Company::where('active', true)->count(),
            'with_certificates' => Company::whereNotNull('certificate_path')->count(),
            'expired_certificates' => Company::whereNotNull('certificate_expires_at')
                ->where('certificate_expires_at', '<', now())
                ->count(),
        ];

        return view('admin.companies.index', compact('companies', 'stats'));
    }

    /**
     * Mostrar formulario para crear empresa.
     */
    public function create()
    {
        return view('admin.companies.create');
    }

    /**
     * Crear nueva empresa.
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
            'ws_environment' => 'required|in:testing,production',
            'ws_active' => 'boolean',
            'active' => 'boolean',
        ]);

        try {
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
                'ws_environment' => $request->ws_environment,
                'ws_active' => $request->boolean('ws_active', false),
                'active' => $request->boolean('active', true),
                'created_date' => now(),
            ]);

            return redirect()->route('admin.companies.index')
                ->with('success', 'Empresa creada correctamente.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error al crear la empresa: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles de la empresa.
     */
    public function show(Company $company)
    {
        $company->load(['user', 'operators.user']);

        // Estadísticas de la empresa
        $stats = [
            'total_operators' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'recent_operators' => $company->operators()->where('created_at', '>=', now()->subDays(30))->count(),
            'certificate_status' => $this->getCertificateStatus($company),
        ];

        return view('admin.companies.show', compact('company', 'stats'));
    }

    /**
     * Mostrar formulario para editar empresa.
     */
    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    /**
     * Actualizar empresa.
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
            'ws_environment' => 'required|in:testing,production',
            'ws_active' => 'boolean',
            'active' => 'boolean',
        ]);

        try {
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
     * Eliminar empresa.
     */
    public function destroy(Company $company)
    {
        try {
            // Verificar que no tenga operadores activos
            $activeOperators = $company->operators()->where('active', true)->count();
            if ($activeOperators > 0) {
                return back()->with('error', 'No se puede eliminar una empresa con operadores activos.');
            }

            // Verificar que no tenga un usuario administrador
            if ($company->user) {
                return back()->with('error', 'No se puede eliminar una empresa que tiene un usuario administrador. Elimine primero el usuario.');
            }

            // Eliminar certificado físico si existe
            if ($company->certificate_path && Storage::exists($company->certificate_path)) {
                Storage::delete($company->certificate_path);
            }

            $company->delete();

            return redirect()->route('admin.companies.index')
                ->with('success', 'Empresa eliminada correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar la empresa: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar gestión de certificados.
     */
    public function certificates(Company $company)
    {
        $certificateInfo = null;

        if ($company->certificate_path) {
            $certificateInfo = [
                'path' => $company->certificate_path,
                'expires_at' => $company->certificate_expires_at,
                'alias' => $company->certificate_alias,
                'status' => $this->getCertificateStatus($company),
                'exists' => Storage::exists($company->certificate_path),
            ];
        }

        return view('admin.companies.certificates', compact('company', 'certificateInfo'));
    }

    /**
     * Subir certificado.
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

            // Subir nuevo certificado
            $path = $request->file('certificate')->store('certificates', 'local');

            $company->update([
                'certificate_path' => $path,
                'certificate_password' => $request->password, // Se encripta automáticamente en el mutator
                'certificate_alias' => $request->alias,
                'certificate_expires_at' => $request->expires_at,
            ]);

            return back()->with('success', 'Certificado subido correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al subir el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar certificado.
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
     * Obtener estado del certificado.
     */
    private function getCertificateStatus($company)
    {
        if (!$company->certificate_path) {
            return [
                'status' => 'none',
                'message' => 'Sin certificado',
                'color' => 'gray',
            ];
        }

        if (!$company->certificate_expires_at) {
            return [
                'status' => 'unknown',
                'message' => 'Fecha de vencimiento desconocida',
                'color' => 'yellow',
            ];
        }

        $expiresAt = Carbon::parse($company->certificate_expires_at);
        $now = Carbon::now();
        $daysToExpiry = $now->diffInDays($expiresAt, false);

        if ($daysToExpiry < 0) {
            return [
                'status' => 'expired',
                'message' => 'Vencido hace ' . abs($daysToExpiry) . ' días',
                'color' => 'red',
                'days' => $daysToExpiry,
            ];
        } elseif ($daysToExpiry <= 30) {
            return [
                'status' => 'warning',
                'message' => 'Vence en ' . $daysToExpiry . ' días',
                'color' => 'yellow',
                'days' => $daysToExpiry,
            ];
        } else {
            return [
                'status' => 'valid',
                'message' => 'Válido por ' . $daysToExpiry . ' días',
                'color' => 'green',
                'days' => $daysToExpiry,
            ];
        }
    }

    /**
     * Mostrar operadores de la empresa.
     */
    public function operators(Company $company)
    {
        $operators = $company->operators()->with('user')->paginate(15);

        return view('admin.companies.operators', compact('company', 'operators'));
    }

    /**
     * Probar webservice de la empresa.
     */
    public function testWebservice(Company $company)
    {
        // TODO: Implementar cuando esté el módulo de webservices
        return back()->with('info', 'Funcionalidad de prueba de webservices en desarrollo.');
    }
}
