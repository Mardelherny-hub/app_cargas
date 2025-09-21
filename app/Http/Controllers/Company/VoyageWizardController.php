<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Country;
use App\Models\Port;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * WIZARD DE VIAJES COMPLETOS - AFIP 100%
 * 
 * Controlador multi-paso para crear viajes con todos los campos AFIP obligatorios.
 * Evita rechazos en webservices mediante captura progresiva de datos.
 * 
 * ESTRUCTURA:
 * - PASO 1: Datos del Viaje (voyage + vessel + captain)
 * - PASO 2: Conocimientos de Embarque (bills_of_lading) 
 * - PASO 3: Mercadería y Contenedores (shipment_items + containers)
 * 
 * FLUJO:
 * - Datos guardados en SESSION entre pasos
 * - Validación en cada paso
 * - Solo se persiste en BD al completar todo
 * - Navegación fluida Anterior/Siguiente
 */
class VoyageWizardController extends Controller
{
    use UserHelper;

    /**
     * PASO 1: Mostrar formulario datos del viaje
     */
    public function step1(Request $request)
    {
        // 1. Verificar permisos
        if (!$this->canPerform('access_trips')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // 2. Obtener empresa del usuario
        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.voyages.index')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // 3. Obtener datos para formulario
        $formData = $this->getStep1FormData($company);

        // 4. Recuperar datos guardados en session si existen
        $savedData = session('voyage_wizard.step1', []);

        // 5. Log para tracking
        Log::info('VoyageWizard: Iniciando PASO 1', [
            'user_id' => Auth::id(),
            'company_id' => $company->id,
            'has_saved_data' => !empty($savedData),
        ]);

        return view('company.voyage-wizard.step1', compact('formData', 'savedData'));
    }

    /**
     * PASO 1: Guardar datos del viaje y avanzar
     */
    public function storeStep1(Request $request)
    {
        // 1. Validación específica PASO 1
        $validated = $request->validate([
            // Datos básicos del viaje
            'voyage_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('voyages', 'voyage_number')->where(function ($query) {
                    return $query->where('company_id', $this->getUserCompany()->id);
                }),
            ],
            'internal_reference' => 'nullable|string|max:100',
            'departure_date' => 'required|date|after_or_equal:today',
            'estimated_arrival_date' => 'required|date|after:departure_date',

            // Puertos y países
            'origin_country_id' => 'required|exists:countries,id',
            'origin_port_id' => 'required|exists:ports,id',
            'destination_country_id' => 'required|exists:countries,id',
            'destination_port_id' => 'required|exists:ports,id|different:origin_port_id',

            // Embarcación y capitán
            'lead_vessel_id' => 'required|exists:vessels,id',
            'captain_id' => 'required|exists:captains,id',

            // Características del viaje
            'voyage_type' => 'required|in:single_vessel,convoy,fleet',
            'cargo_type' => 'required|in:export,import,transit,transshipment,cabotage',
            'is_convoy' => 'boolean',
            'vessel_count' => 'required|integer|min:1|max:20',

            // CAMPOS AFIP NUEVOS (opcionales en UI, valores por defecto en backend)
            'is_empty_transport' => 'boolean',
            'has_cargo_onboard' => 'boolean',

            // Notas opcionales
            'special_instructions' => 'nullable|string|max:500',
            'operational_notes' => 'nullable|string|max:500',
        ], [
            // Mensajes personalizados
            'voyage_number.unique' => 'El número de viaje ya existe en su empresa.',
            'estimated_arrival_date.after' => 'La fecha de llegada debe ser posterior a la salida.',
            'destination_port_id.different' => 'El puerto de destino debe ser diferente al de origen.',
            'vessel_count.max' => 'Máximo 20 embarcaciones por convoy.',
        ]);

        // 2. Validaciones adicionales de negocio
        $this->validateStep1Business($validated);

        // 3. Guardar en session
        session(['voyage_wizard.step1' => $validated]);
        session(['voyage_wizard.current_step' => 1]);
        session(['voyage_wizard.completed_steps' => [1]]);

        // 4. Log del progreso
        Log::info('VoyageWizard: PASO 1 completado', [
            'user_id' => Auth::id(),
            'voyage_number' => $validated['voyage_number'],
            'vessel_id' => $validated['lead_vessel_id'],
            'captain_id' => $validated['captain_id'],
        ]);

        // 5. Redirect al PASO 2
        return redirect()->route('company.voyage-wizard.step2')
            ->with('success', 'Datos del viaje guardados. Continúe con los conocimientos de embarque.');
    }

    /**
     * PASO 2: Placeholder (será implementado después)
     */
    public function step2(Request $request)
    {
        // Verificar que PASO 1 esté completado
        if (!session('voyage_wizard.step1')) {
            return redirect()->route('company.voyage-wizard.step1')
                ->with('warning', 'Debe completar el Paso 1 primero.');
        }

        return view('company.voyage-wizard.step2', [
            'step1Data' => session('voyage_wizard.step1'),
        ]);
    }

    /**
     * PASO 3: Placeholder (será implementado después)
     */
    public function step3(Request $request)
    {
        // Verificar que PASO 1 y 2 estén completados
        if (!session('voyage_wizard.step1') || !session('voyage_wizard.step2')) {
            return redirect()->route('company.voyage-wizard.step1')
                ->with('warning', 'Debe completar los pasos anteriores primero.');
        }

        return view('company.voyage-wizard.step3', [
            'step1Data' => session('voyage_wizard.step1'),
            'step2Data' => session('voyage_wizard.step2'),
        ]);
    }

    /**
     * Cancelar wizard y limpiar session
     */
    public function cancel()
    {
        // Limpiar datos del wizard
        session()->forget('voyage_wizard');

        return redirect()->route('company.voyages.index')
            ->with('info', 'Creación de viaje cancelada.');
    }

    /**
     * Página de inicio del wizard
     */
    public function start()
    {
        // Verificar permisos
        if (!$this->canPerform('access_trips')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Limpiar session anterior si existe
        session()->forget('voyage_wizard');

        return view('company.voyage-wizard.start');
    }

    /**
     * Obtener datos para formulario PASO 1
     */
    private function getStep1FormData($company): array
    {
        return [
            // Embarcaciones disponibles de la empresa
            'vessels' => Vessel::where('active', true)
                ->where('available_for_charter', true)
                ->where('operational_status', 'active')
                ->where('company_id', $company->id)
                ->with(['vesselType:id,name', 'primaryCaptain:id,full_name'])
                ->select('id', 'name', 'vessel_type_id', 'primary_captain_id', 'imo_number')
                ->orderBy('name')
                ->get(),

            // Capitanes disponibles
            'captains' => Captain::where('active', true)
                ->where('available_for_hire', true)
                ->where('license_status', 'valid')
                ->where(function($query) {
                    $query->whereNull('license_expires_at')
                        ->orWhere('license_expires_at', '>', now());
                })
                ->select('id', 'full_name', 'license_number', 'nationality')
                ->orderBy('full_name')
                ->get(),

            // Países activos
            'countries' => Country::where('active', true)
                ->where(function($query) {
                    $query->where('allows_import', true)
                        ->orWhere('allows_export', true);
                })
                ->select('id', 'name', 'iso_code')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),

            // Puertos por país (será cargado via AJAX)
            'ports' => Port::where('active', true)
                ->where('accepts_new_vessels', true)  // ← CAMPO REAL
                ->with('country:id,name,iso_code')
                ->select('id', 'name', 'code', 'country_id', 'port_type')
                ->orderBy('country_id')
                ->orderBy('name')
                ->get(),

            // Opciones para selects
            'voyageTypes' => [
                'single_vessel' => 'Embarcación única',
                'convoy' => 'Convoy (remolcador + barcazas)',
                'fleet' => 'Flota coordinada',
            ],

            'cargoTypes' => [
                'export' => 'Exportación',
                'import' => 'Importación',
                'transit' => 'Tránsito',
                'transshipment' => 'Transbordo',
                'cabotage' => 'Cabotaje',
            ],

            // Valores por defecto
            'defaults' => [
                'voyage_type' => 'single_vessel',
                'cargo_type' => 'export',
                'vessel_count' => 1,
                'is_convoy' => false,
                'is_empty_transport' => false,
                'has_cargo_onboard' => true,
            ],
        ];
    }

    /**
     * Validaciones de negocio específicas PASO 1
     */
    private function validateStep1Business(array $data): void
    {
        $company = $this->getUserCompany();

        // 1. Verificar que vessel pertenece a la empresa
        $vessel = Vessel::find($data['lead_vessel_id']);
        if (!$vessel || $vessel->company_id !== $company->id) {
            throw new \Exception('La embarcación seleccionada no pertenece a su empresa.');
        }

        // 2. Verificar que el vessel esté disponible
        if (!$vessel->isAvailable()) {
            throw new \Exception('La embarcación seleccionada no está disponible.');
        }

        // 3. Verificar que captain existe y está disponible
        $captain = Captain::find($data['captain_id']);
        if (!$captain || !$captain->active || !$captain->available_for_hire) {
            throw new \Exception('El capitán seleccionado no está disponible.');
        }

        // 4. Verificar que puertos pertenecen a países correctos
        $originPort = Port::with('country')->find($data['origin_port_id']);
        $destPort = Port::with('country')->find($data['destination_port_id']);

        if ($originPort->country_id != $data['origin_country_id']) {
            throw new \Exception('El puerto de origen no pertenece al país seleccionado.');
        }

        if ($destPort->country_id != $data['destination_country_id']) {
            throw new \Exception('El puerto de destino no pertenece al país seleccionado.');
        }

        // 5. Validar fechas lógicas
        $departure = \Carbon\Carbon::parse($data['departure_date']);
        $arrival = \Carbon\Carbon::parse($data['estimated_arrival_date']);

        if ($arrival->diffInDays($departure) > 30) {
            throw new \Exception('El viaje no puede durar más de 30 días.');
        }

        // 6. Validar convoy settings
        if ($data['is_convoy'] && $data['vessel_count'] < 2) {
            throw new \Exception('Un convoy debe tener al menos 2 embarcaciones.');
        }

        if (!$data['is_convoy'] && $data['vessel_count'] > 1) {
            throw new \Exception('Viaje de embarcación única no puede tener múltiples embarcaciones.');
        }
    }
}