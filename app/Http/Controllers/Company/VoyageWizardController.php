<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Port;
use App\Models\Client;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador del Wizard para Crear Viajes Completos
 * 
 * Guía paso a paso para crear un viaje desde cero:
 * 1. Información básica del viaje (origen, destino, fechas)
 * 2. Configuración de embarcaciones y tripulación
 * 3. Clientes y conocimientos de embarque
 * 4. Revisión y finalización
 * 
 * PERMISOS REQUERIDOS:
 * - Rol empresa: "Cargas"
 * - Permiso: "view_cargas" para acceso
 * - Company admin: CRUD completo
 * - Operadores: Solo sus propios viajes
 */
class VoyageWizardController extends Controller
{
    use UserHelper;

    /**
     * Mostrar página principal del wizard
     */
    public function index()
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas para gestionar viajes.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Obtener estadísticas básicas para la vista
        $stats = $this->getWizardStats($company);

        return view('company.voyages.wizard', compact('company', 'stats'));
    }

    /**
     * Paso 1: Información básica del viaje
     */
    public function step1(Request $request)
    {
        // Verificar permisos
        if (!$this->canPerform('view_cargas') || !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        $company = $this->getUserCompany();

        if ($request->isMethod('post')) {
            // Procesar formulario del paso 1
            $validated = $request->validate([
                'origin_port_id' => 'required|exists:ports,id',
                'destination_port_id' => 'required|exists:ports,id|different:origin_port_id',
                'departure_date' => 'required|date|after_or_equal:today',
                'arrival_date' => 'required|date|after:departure_date',
                'voyage_number' => 'nullable|string|max:50|unique:voyages,voyage_number',
                'internal_reference' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:500'
            ]);

            // Generar número de viaje automático si no se proporcionó
            if (empty($validated['voyage_number'])) {
                $validated['voyage_number'] = $this->generateVoyageNumber($company);
            }

            // Guardar datos en sesión y redirigir al paso 2
            session(['wizard_step1' => $validated]);
            
            return redirect()->route('company.voyages.wizard.step2')
                ->with('success', 'Información básica guardada. Continúe con la configuración de embarcaciones.');
        }

        // Mostrar formulario del paso 1
        $formData = $this->getStep1FormData($company);
        $step1Data = session('wizard_step1', []);

        return view('company.voyages.wizard-step1', compact('formData', 'step1Data', 'company'));
    }

    /**
     * Paso 2: Configuración de embarcaciones y tripulación
     */
    public function step2(Request $request)
    {
        // Verificar que se completó el paso 1
        if (!session('wizard_step1')) {
            return redirect()->route('company.voyages.wizard.step1')
                ->with('error', 'Debe completar la información básica primero.');
        }

        // Verificar permisos
        if (!$this->canPerform('view_cargas') || !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        $company = $this->getUserCompany();

        if ($request->isMethod('post')) {
            // Procesar formulario del paso 2
            $validated = $request->validate([
                'lead_vessel_id' => 'required|exists:vessels,id',
                'captain_id' => 'required|exists:captains,id',
                'additional_vessels' => 'nullable|array',
                'additional_vessels.*' => 'exists:vessels,id'
            ]);

            // Guardar datos en sesión
            session(['wizard_step2' => $validated]);
            
            return redirect()->route('company.voyages.wizard.step3')
                ->with('success', 'Configuración de embarcaciones guardada. Continúe con los conocimientos.');
        }

        // Mostrar formulario del paso 2
        $formData = $this->getStep2FormData($company);
        $step2Data = session('wizard_step2', []);

        return view('company.voyages.wizard-step2', compact('formData', 'step2Data', 'company'));
    }

    /**
     * Paso 3: Clientes y conocimientos de embarque
     */
    public function step3(Request $request)
    {
        // Verificar pasos anteriores
        if (!session('wizard_step1') || !session('wizard_step2')) {
            return redirect()->route('company.voyages.wizard.step1')
                ->with('error', 'Debe completar los pasos anteriores.');
        }

        // Verificar permisos
        if (!$this->canPerform('view_cargas') || !$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para crear viajes.');
        }

        $company = $this->getUserCompany();

        if ($request->isMethod('post')) {
            // Procesar formulario del paso 3
            $validated = $request->validate([
                'bl_creation_mode' => 'required|in:basic,complete,import,skip',
                'shipper_id' => 'nullable|exists:clients,id',
                'consignee_id' => 'nullable|exists:clients,id',
                'cargo_type_id' => 'nullable|exists:cargo_types,id',
                'packaging_type_id' => 'nullable|exists:packaging_types,id'
            ]);

            // Guardar datos en sesión
            session(['wizard_step3' => $validated]);
            
            return redirect()->route('company.voyages.wizard.step4')
                ->with('success', 'Configuración de conocimientos guardada. Revise y finalice.');
        }

        // Mostrar formulario del paso 3
        $formData = $this->getStep3FormData($company);
        $step3Data = session('wizard_step3', []);

        return view('company.voyages.wizard-step3', compact('formData', 'step3Data', 'company'));
    }

    /**
     * Paso 4: Revisión y finalización
     */
    public function step4(Request $request)
    {
        // Verificar todos los pasos anteriores
        if (!session('wizard_step1') || !session('wizard_step2') || !session('wizard_step3')) {
            return redirect()->route('company.voyages.wizard.step1')
                ->with('error', 'Debe completar todos los pasos anteriores.');
        }

        $company = $this->getUserCompany();

        if ($request->isMethod('post')) {
            // Crear el viaje y datos relacionados
            $result = $this->createCompleteVoyage();
            
            if ($result['success']) {
                // Limpiar sesión del wizard
                session()->forget(['wizard_step1', 'wizard_step2', 'wizard_step3']);
                
                return redirect()->route('company.voyages.show', $result['voyage'])
                    ->with('success', 'Viaje creado exitosamente. ' . $result['message']);
            } else {
                return back()->with('error', 'Error creando el viaje: ' . $result['message']);
            }
        }

        // Mostrar resumen para revisión
        $summaryData = $this->prepareSummaryData();

        return view('company.voyages.wizard-step4', compact('summaryData', 'company'));
    }

    /**
     * Cancelar wizard y limpiar sesión
     */
    public function cancel()
    {
        session()->forget(['wizard_step1', 'wizard_step2', 'wizard_step3']);
        
        return redirect()->route('company.voyages.index')
            ->with('info', 'Wizard cancelado.');
    }

    // =============================================
    // MÉTODOS HELPER PRIVADOS
    // =============================================

    /**
     * Obtener estadísticas para el wizard
     */
    private function getWizardStats($company)
    {
        return [
            'voyages_this_month' => Voyage::where('company_id', $company->id)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'active_vessels' => Vessel::where('company_id', $company->id)
                ->where('active', true)
                ->count(),
            'active_captains' => Captain::where('company_id', $company->id)
                ->where('active', true)
                ->count(),
            'recent_clients' => Client::whereHas('companyRelations', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->count()
        ];
    }

    /**
     * Generar número de viaje automático
     */
    private function generateVoyageNumber($company)
    {
        $prefix = strtoupper(substr($company->name, 0, 3));
        $year = date('Y');
        $sequence = Voyage::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return $prefix . '-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener datos del formulario para paso 1
     */
    private function getStep1FormData($company)
    {
        return [
            'ports' => Port::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'country_id']),
            'countries' => \App\Models\Country::orderBy('name')->get(['id', 'name', 'iso_code'])
        ];
    }

    /**
     * Obtener datos del formulario para paso 2
     */
    private function getStep2FormData($company)
    {
        return [
            'vessels' => Vessel::where('company_id', $company->id)
                ->where('active', true)
                ->with('vesselType')
                ->orderBy('name')
                ->get(),
            'captains' => Captain::where('company_id', $company->id)
                ->where('active', true)
                ->orderBy('full_name')
                ->get()
        ];
    }

    /**
     * Obtener datos del formulario para paso 3
     */
    private function getStep3FormData($company)
    {
        return [
            'clients' => Client::whereHas('companyRelations', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->orderBy('legal_name')->get(),
            'cargo_types' => CargoType::where('active', true)->orderBy('name')->get(),
            'packaging_types' => PackagingType::where('active', true)->orderBy('name')->get()
        ];
    }

    /**
     * Preparar datos de resumen para el paso 4
     */
    private function prepareSummaryData()
    {
        $step1 = session('wizard_step1');
        $step2 = session('wizard_step2');
        $step3 = session('wizard_step3');

        return [
            'voyage_info' => [
                'origin_port' => Port::find($step1['origin_port_id']),
                'destination_port' => Port::find($step1['destination_port_id']),
                'departure_date' => $step1['departure_date'],
                'arrival_date' => $step1['arrival_date'],
                'voyage_number' => $step1['voyage_number'],
                'description' => $step1['description'] ?? null
            ],
            'vessels_info' => [
                'lead_vessel' => Vessel::find($step2['lead_vessel_id']),
                'captain' => Captain::find($step2['captain_id']),
                'additional_vessels' => isset($step2['additional_vessels']) 
                    ? Vessel::whereIn('id', $step2['additional_vessels'])->get()
                    : collect()
            ],
            'bl_info' => [
                'creation_mode' => $step3['bl_creation_mode'],
                'shipper' => isset($step3['shipper_id']) ? Client::find($step3['shipper_id']) : null,
                'consignee' => isset($step3['consignee_id']) ? Client::find($step3['consignee_id']) : null,
                'cargo_type' => isset($step3['cargo_type_id']) ? CargoType::find($step3['cargo_type_id']) : null,
                'packaging_type' => isset($step3['packaging_type_id']) ? PackagingType::find($step3['packaging_type_id']) : null
            ]
        ];
    }

    /**
     * Crear viaje completo con todos los datos del wizard
     */
    private function createCompleteVoyage()
    {
        try {
            DB::beginTransaction();

            $step1 = session('wizard_step1');
            $step2 = session('wizard_step2');
            $step3 = session('wizard_step3');
            $company = $this->getUserCompany();

            // 1. Crear el viaje principal
            $voyage = Voyage::create([
                'company_id' => $company->id,
                'voyage_number' => $step1['voyage_number'],
                'internal_reference' => $step1['internal_reference'] ?? null,
                'description' => $step1['description'] ?? null,
                'origin_port_id' => $step1['origin_port_id'],
                'destination_port_id' => $step1['destination_port_id'],
                'departure_date' => $step1['departure_date'],
                'arrival_date' => $step1['arrival_date'],
                'lead_vessel_id' => $step2['lead_vessel_id'],
                'captain_id' => $step2['captain_id'],
                'status' => 'planning',
                'created_by_user_id' => Auth::id()
            ]);

            $createdItems = [];

            // 2. Crear shipment principal
            $mainShipment = $voyage->shipments()->create([
                'company_id' => $company->id,
                'vessel_id' => $step2['lead_vessel_id'],
                'captain_id' => $step2['captain_id'],
                'shipment_number' => $voyage->voyage_number . '-01',
                'sequence_in_voyage' => 1,
                'vessel_role' => 'lead',
                'status' => 'planning',
                'created_by_user_id' => Auth::id()
            ]);

            $createdItems[] = 'Shipment principal';

            // 3. Crear conocimiento básico si se solicitó
            if ($step3['bl_creation_mode'] === 'basic' || $step3['bl_creation_mode'] === 'complete') {
                $billOfLading = $mainShipment->billsOfLading()->create([
                    'bill_number' => $voyage->voyage_number . '-BL-001',
                    'shipper_id' => $step3['shipper_id'] ?? $step3['consignee_id'], // Fallback
                    'consignee_id' => $step3['consignee_id'] ?? $step3['shipper_id'], // Fallback
                    'loading_port_id' => $step1['origin_port_id'],
                    'discharge_port_id' => $step1['destination_port_id'],
                    'primary_cargo_type_id' => $step3['cargo_type_id'] ?? 1, // Default
                    'primary_packaging_type_id' => $step3['packaging_type_id'] ?? 1, // Default
                    'bl_date' => now(),
                    'status' => 'draft'
                ]);

                $createdItems[] = 'Conocimiento de embarque básico';
            }

            DB::commit();

            return [
                'success' => true,
                'voyage' => $voyage,
                'message' => 'Elementos creados: ' . implode(', ', $createdItems)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating voyage in wizard', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return [
                'success' => false,
                'message' => 'Error interno del sistema. Intente nuevamente.'
            ];
        }
    }
}