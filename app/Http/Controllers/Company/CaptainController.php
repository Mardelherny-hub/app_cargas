<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Captain;
use App\Models\Country;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * MÓDULO CAPITANES - Company Controller
 * 
 * Controlador para gestión de capitanes en el ámbito de empresa.
 * Sigue el patrón: Controlador → Vista Blade → Componente Livewire
 * 
 * PERMISOS:
 * - company-admin: CRUD completo
 * - user: Solo visualización
 * 
 * CAMPOS BASADOS EN create_captains_table.php:
 * - Información personal: first_name, last_name, full_name, birth_date, gender, nationality
 * - Contacto: email, phone, mobile_phone, address
 * - Licencia: license_number, license_class, license_issued_at, license_expires_at, license_country_id
 * - Profesional: years_of_experience, employment_status, daily_rate, rate_currency
 * - Relaciones: country_id, primary_company_id
 * - Auditoría: created_by_user_id, last_updated_by_user_id
 */
class CaptainController extends Controller
{
    use UserHelper;
    use AuthorizesRequests;

    /**
     * Display a listing of captains.
     * Preparado para Livewire component
     */
    public function index(Request $request)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->canPerform('view_vessels')) {
            abort(403, 'No tiene permisos para ver capitanes.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Preparar datos para el componente Livewire
        $countries = Country::active()
            ->orderBy('name')
            ->get(['id', 'name', 'alpha2_code']);

        $companies = collect([$company]); // Solo la empresa actual para company users

        // Opciones para filtros
        $filterOptions = [
            'employment_status' => [
                'employed' => 'Empleado',
                'freelance' => 'Freelance',
                'contract' => 'Contratista',
                'retired' => 'Retirado'
            ],
            'license_class' => [
                'master' => 'Capitán (Master)',
                'chief_officer' => 'Primer Oficial',
                'officer' => 'Oficial',
                'pilot' => 'Piloto'
            ],
            'active' => [
                true => 'Activos',
                false => 'Inactivos'
            ]
        ];

        return view('company.captains.index', compact(
            'countries',
            'companies', 
            'filterOptions'
        ));
    }

}