<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Http\Requests\BillOfLading\CreateBillOfLadingRequest;
use App\Http\Requests\BillOfLading\UpdateBillOfLadingRequest;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Models\ShipmentItem;

/**
 * MÓDULO 4 - PARTE 1: GESTIÓN DE DATOS PARA MANIFIESTOS
 * 
 * Controlador de Conocimientos de Embarque para Company Admin y Users
 * Maneja CRUD completo con control de acceso por rol "Cargas"
 * 
 * PERMISOS REQUERIDOS:
 * - Rol empresa: "Cargas" (CRUD completo)
 * - Rol empresa: "Desconsolidador" (Solo visualización + títulos hijo)
 * - Rol empresa: "Transbordos" (Solo visualización)
 * 
 * ACTUALIZACIÓN: Compatible con campos de consolidación, webservices AR/PY y nuevas funcionalidades
 */
class BillOfLadingController extends Controller
{
    use UserHelper;
    use AuthorizesRequests;

    /**
     * Mostrar lista de conocimientos de embarque con filtros avanzados
     */
    public function index(Request $request)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->hasCompanyRole('Desconsolidador') && !$this->hasCompanyRole('Transbordos')) {
            abort(403, 'No tiene permisos para ver conocimientos de embarque.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // Construir consulta base - filtrar por empresa
        $query = BillOfLading::with([
            'shipment.voyage:id,voyage_number,company_id',
            'shipper:id,legal_name,tax_id',
            'consignee:id,legal_name,tax_id',
            'loadingPort:id,name,country_id',
            'dischargePort:id,name,country_id',
            'primaryCargoType:id,name',
            'createdByUser:id,name'
        ])
        ->whereHas('shipment.voyage', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        // Aplicar filtros adicionales según el rol del usuario
        if ($this->isUser() && $this->isOperator() && !$this->hasCompanyRole('Cargas')) {
            // Los usuarios operadores sin rol Cargas solo ven conocimientos que ellos crearon
            $query->where('created_by_user_id', Auth::id());
        }

        // === FILTROS DE BÚSQUEDA BÁSICOS ===

        // Filtro por texto (número de conocimiento, cargador, consignatario)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->search($search);
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->byStatus($request->get('status'));
        }

        // Filtro por envío específico
        if ($request->filled('shipment_id')) {
            $query->where('shipment_id', $request->get('shipment_id'));
        }

        // Filtro por cargador
        if ($request->filled('shipper_id')) {
            $query->where('shipper_id', $request->get('shipper_id'));
        }

        // Filtro por consignatario
        if ($request->filled('consignee_id')) {
            $query->where('consignee_id', $request->get('consignee_id'));
        }

        // Filtro por puerto de carga
        if ($request->filled('loading_port_id')) {
            $query->where('loading_port_id', $request->get('loading_port_id'));
        }

        // Filtro por puerto de descarga
        if ($request->filled('discharge_port_id')) {
            $query->where('discharge_port_id', $request->get('discharge_port_id'));
        }

        // === FILTROS NUEVOS AGREGADOS ===

        // Filtro por tipo de conocimiento
        if ($request->filled('bill_type')) {
            $query->where('bill_type', $request->get('bill_type'));
        }

        // Filtro por nivel de prioridad
        if ($request->filled('priority_level')) {
            $query->where('priority_level', $request->get('priority_level'));
        }

        // Filtros de consolidación
        if ($request->filled('is_consolidated')) {
            $query->where('is_consolidated', $request->boolean('is_consolidated'));
        }

        if ($request->filled('is_master_bill')) {
            $query->where('is_master_bill', $request->boolean('is_master_bill'));
        }

        if ($request->filled('is_house_bill')) {
            $query->where('is_house_bill', $request->boolean('is_house_bill'));
        }

        // Filtros de webservices Argentina/Paraguay
        if ($request->filled('argentina_status')) {
            $query->where('argentina_status', $request->get('argentina_status'));
        }

        if ($request->filled('paraguay_status')) {
            $query->where('paraguay_status', $request->get('paraguay_status'));
        }

        // Filtro por documentación completa
        if ($request->filled('documentation_complete')) {
            $query->where('documentation_complete', $request->boolean('documentation_complete'));
        }

        // Filtro por originales liberados
        if ($request->filled('original_released')) {
            $query->where('original_released', $request->boolean('original_released'));
        }

        // Filtro por conocimientos listos para entrega
        if ($request->filled('ready_for_delivery')) {
            $query->where('ready_for_delivery', $request->boolean('ready_for_delivery'));
        }

        // === FILTROS DE FECHAS ===

        // Filtro por rango de fechas del conocimiento
        if ($request->filled('date_from')) {
            $query->where('bill_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('bill_date', '<=', $request->get('date_to'));
        }

        // Filtro por fechas de carga
        if ($request->filled('loading_date_from')) {
            $query->where('loading_date', '>=', $request->get('loading_date_from'));
        }
        if ($request->filled('loading_date_to')) {
            $query->where('loading_date', '<=', $request->get('loading_date_to'));
        }

        // Filtro por conocimientos vencidos (tiempo libre)
        if ($request->filled('expired')) {
            if ($request->boolean('expired')) {
                $query->expired();
            }
        }

        // Filtro por conocimientos próximos a vencer
        if ($request->filled('expiring_soon')) {
            if ($request->boolean('expiring_soon')) {
                $query->expiringSoon($request->get('expiring_days', 3));
            }
        }

        // === FILTROS ESPECIALES EXISTENTES ===
        if ($request->filled('dangerous_goods')) {
            $query->dangerousGoods();
        }
        if ($request->filled('refrigerated')) {
            $query->refrigerated();
        }
        if ($request->filled('pending_verification')) {
            $query->pendingVerification();
        }

        // === FILTROS DE VERIFICACIÓN ===
        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->verified();
            } else {
                $query->whereNull('verified_at');
            }
        }

        // === ORDENAMIENTO ===
        $sortBy = $request->get('sort', 'bill_date');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = [
            'bill_date', 'bill_number', 'created_at', 'gross_weight_kg', 
            'total_packages', 'loading_date', 'discharge_date', 'priority_level',
            'status', 'verified_at', 'argentina_sent_at', 'paraguay_sent_at'
        ];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Paginación
        $perPage = $request->get('per_page', 25);
        $billsOfLading = $query->paginate($perPage)->appends($request->query());

        // Datos adicionales para filtros
        $filterData = $this->getFilterData($company);

        // Estadísticas rápidas para el dashboard
        $stats = $this->getIndexStats($company);

        return view('company.bills-of-lading.index', compact(
            'billsOfLading',
            'filterData',
            'stats',
            'company'
        ));
    }

    /**
     * Mostrar formulario para crear nuevo conocimiento de embarque
     */
    /**
     * Mostrar formulario de creación
     */
    public function create(Request $request)
    {
        // Verificar permisos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para crear conocimientos de embarque.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada.');
        }

        // ===== MANEJAR SHIPMENT_ID PRESELECCIONADO =====
        $preselectedShipmentId = $request->get('shipment_id');
        $preselectedShipment = null;

        if ($preselectedShipmentId) {
            $preselectedShipment = Shipment::with(['voyage'])
                ->whereHas('voyage', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->find($preselectedShipmentId);

            if (!$preselectedShipment) {
                $preselectedShipmentId = null;
            }
        }

        // Obtener datos para el formulario - Ahora para el componente Livewire
        $formData = $this->getFormData($company, $request);
        
        // Agregar preselecciones al formData
        $formData['preselectedShipmentId'] = $preselectedShipmentId;
        $formData['preselectedShipment'] = $preselectedShipment;

        // Pre-poblar puertos si hay shipment preseleccionado
        if ($preselectedShipment && $preselectedShipment->voyage) {
            $voyage = $preselectedShipment->voyage;
            $formData['preselectedLoadingPortId'] = $voyage->origin_port_id;
            $formData['preselectedDischargePortId'] = $voyage->destination_port_id;
        }

        // ===== PREPARAR VARIABLES PARA EL COMPONENTE LIVEWIRE =====
        $componentData = [
            'shipmentId' => $preselectedShipmentId,
            'preselectedLoadingPortId' => $formData['preselectedLoadingPortId'] ?? null,
            'preselectedDischargePortId' => $formData['preselectedDischargePortId'] ?? null,
        ];

        return view('company.bills-of-lading.create', compact('formData', 'company', 'componentData'));
    }

    /**
     * Almacenar nuevo conocimiento de embarque
     */
    public function store(CreateBillOfLadingRequest $request)
    {
        dd($request->all());
        
        // DEBUG: Ver si llegamos al store y qué datos validados tenemos
        \Log::info('=== BILL OF LADING STORE DEBUG ===', [
            'validated_data' => $request->validated(),
            'all_request' => $request->all()
        ]); 
        
        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // Preparar datos para crear el conocimiento
            $data = $request->validated();
            $data['created_by_user_id'] = Auth::id();
            $data['last_updated_by_user_id'] = Auth::id();
            $data['status'] = 'draft';

            // Manejar campos que no pueden ser null
            if (empty($data['bill_type'])) {
                $data['bill_type'] = 'original'; // Valor por defecto
            }

            if (empty($data['measurement_unit'])) {
                $data['measurement_unit'] = 'KG';
            }

            if (empty($data['currency_code'])) {
                $data['currency_code'] = 'USD';
            }

            // Generar número de conocimiento si no se proporcionó
            if (empty($data['bill_number'])) {
                $data['bill_number'] = $this->generateBillNumber($company);
            }

            // Establecer valores por defecto para campos de consolidación
            $data['is_consolidated'] = $data['is_consolidated'] ?? false;
            $data['is_master_bill'] = $data['is_master_bill'] ?? false;
            $data['is_house_bill'] = $data['is_house_bill'] ?? false;

            // Crear el conocimiento de embarque
            $billOfLading = BillOfLading::create($data);

            DB::commit();

            Log::info('Conocimiento de embarque creado', [
                'bill_id' => $billOfLading->id,
                'bill_number' => $billOfLading->bill_number,
                'company_id' => $company->id,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('success', "Conocimiento de embarque {$billOfLading->bill_number} creado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear conocimiento de embarque', [
                'error' => $e->getMessage(),
                'company_id' => $company->id,
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al crear el conocimiento de embarque: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles de un conocimiento de embarque
     */
    public function show(BillOfLading $billOfLading)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->hasCompanyRole('Desconsolidador') && !$this->hasCompanyRole('Transbordos')) {
            abort(403, 'No tiene permisos para ver conocimientos de embarque.');
        }

        // Verificar que el conocimiento pertenece a la empresa del usuario
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para ver este conocimiento.');
        }

        // Verificar si el usuario puede ver este conocimiento específico
        if ($this->isUser() && $this->isOperator() && !$this->hasCompanyRole('Cargas')) {
            if ($billOfLading->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para ver este conocimiento.');
            }
        }

        // Cargar relaciones necesarias
        $billOfLading->load([
            'shipment.voyage:id,voyage_number,company_id',
            'shipment.vessel:id,name',
            'shipment.shipmentItems' => function ($q) {
                $q->with(['cargoType:id,name', 'packagingType:id,name']);
            },
            'shipper:id,legal_name,tax_id,country_id,status',
            'consignee:id,legal_name,tax_id,country_id,status', 
            'notifyParty:id,legal_name,tax_id,country_id',
            'cargoOwner:id,legal_name,tax_id,country_id',
            'loadingPort:id,name,code,country_id',
            'dischargePort:id,name,code,country_id',
            'transshipmentPort:id,name,code,country_id',  
            'finalDestinationPort:id,name,code,country_id',
            'loadingCustoms:id,name,code',
            'dischargeCustoms:id,name,code',
            'primaryCargoType:id,name,description',
            'primaryPackagingType:id,name,description',
            //'attachments',
            'createdByUser:id,name',
            'verifiedByUser:id,name', 
            'lastUpdatedByUser:id,name'
        ]);

        // Verificar permisos de acciones
        $permissions = $this->getBillPermissions($billOfLading);

        // Obtener el shipment relacionado al BL
        $shipment = $billOfLading->shipment;

        return view('company.bills-of-lading.show', compact(
            'billOfLading',
            'permissions',
            'shipment',
        ));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(BillOfLading $billOfLading)
    {
        // Verificar permisos para editar conocimientos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar conocimientos de embarque.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que el conocimiento pertenece a la empresa del usuario
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para editar este conocimiento.');
        }

        // Verificar que puede ser editado
        if (!$billOfLading->canBeEdited()) {
            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('error', 'Este conocimiento no puede ser editado en su estado actual.');
        }

        $company = $this->getUserCompany();
        

        // ✅ MEJORADO: Cargar relaciones más completas como en show()
        $billOfLading->load([
            'shipment.voyage:id,voyage_number,company_id',
            'shipment.vessel:id,name',
            'shipper:id,legal_name,tax_id',
            'consignee:id,legal_name,tax_id',
            'notifyParty:id,legal_name,tax_id',
            'cargoOwner:id,legal_name,tax_id',
            'loadingPort:id,name,code,country_id',
            'dischargePort:id,name,code,country_id',
            'transshipmentPort:id,name,code,country_id',
            'finalDestinationPort:id,name,code,country_id',
            'loadingCustoms:id,name,code',
            'dischargeCustoms:id,name,code',
            'primaryCargoType:id,name,description',
            'primaryPackagingType:id,name,description'
        ]);

        // ✅ MEJORADO: Obtener formData igual que en create()
        $formData = $this->getFormData($company, request(), $billOfLading);

        // ✅ AGREGAR: Datos preseleccionados para el formulario (como en create cuando viene shipment_id)
        $defaultValues = [
            'shipment_id' => $billOfLading->shipment_id,
            'bill_number' => $billOfLading->bill_number,
            'shipper_id' => $billOfLading->shipper_id,
            'consignee_id' => $billOfLading->consignee_id,
            'notify_party_id' => $billOfLading->notify_party_id,
            'cargo_owner_id' => $billOfLading->cargo_owner_id,
            'loading_port_id' => $billOfLading->loading_port_id,
            'discharge_port_id' => $billOfLading->discharge_port_id,
            'transshipment_port_id' => $billOfLading->transshipment_port_id,
            'final_destination_port_id' => $billOfLading->final_destination_port_id,
            'loading_customs_id' => $billOfLading->loading_customs_id,
            'discharge_customs_id' => $billOfLading->discharge_customs_id,
            'primary_cargo_type_id' => $billOfLading->primary_cargo_type_id,
            'primary_packaging_type_id' => $billOfLading->primary_packaging_type_id,
            'payment_terms' => $billOfLading->payment_terms ?? 'cash',
            'measurement_unit' => $billOfLading->measurement_unit ?? 'KG',
        ];

        // Cargar BL maestros disponibles del mismo shipment
        $availableMasterBills = BillOfLading::where('shipment_id', $billOfLading->shipment_id)
            ->where('is_master_bill', true)
            ->where('id', '!=', $billOfLading->id) // Excluir el mismo BL
            ->select('id', 'bill_number', 'cargo_description')
            ->get();

        return view('company.bills-of-lading.edit', compact(
            'billOfLading',
            'formData', 
            'company',
            'defaultValues',
            'availableMasterBills'
        ));
    }

    /**
     * Actualizar conocimiento de embarque
     */
    public function update(UpdateBillOfLadingRequest $request, BillOfLading $billOfLading)
    {
        //dd($request->all());
        // Verificar que el conocimiento pertenece a la empresa del usuario
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para editar este conocimiento.');
        }
        //dd($request->all());
        try {
            DB::beginTransaction();

            // ✅ MEJORADO: Preparar datos igual que en store()
            $data = $request->validated();
            $data['last_updated_by_user_id'] = Auth::id();
            
            // ✅ AGREGAR: Mantener campos que no deben cambiar en update
            // No sobrescribir created_by_user_id, status si es verificado, etc.
            if ($billOfLading->status === 'verified') {
                unset($data['status']); // No permitir cambio de status si ya está verificado
            }

            // ✅ AGREGAR: Establecer valores por defecto para campos de consolidación (como en store)
           // ✅ CORREGIR: Manejar checkboxes HTML correctamente
$data['is_consolidated'] = isset($data['is_consolidated']) && $data['is_consolidated'] == '1';
$data['is_master_bill'] = isset($data['is_master_bill']) && $data['is_master_bill'] == '1';
$data['is_house_bill'] = isset($data['is_house_bill']) && $data['is_house_bill'] == '1';
            
            $billOfLading->update($data);

            DB::commit();

            Log::info('Conocimiento de embarque actualizado', [
                'bill_id' => $billOfLading->id,
                'bill_number' => $billOfLading->bill_number,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('success', "Conocimiento de embarque {$billOfLading->bill_number} actualizado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar conocimiento de embarque', [
                'bill_id' => $billOfLading->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el conocimiento de embarque: ' . $e->getMessage());
        }
    }

    /**
     * Verificar si un BL tiene items asociados (AJAX)
     */
    public function checkItems(BillOfLading $billOfLading)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') || !$this->hasCompanyRole('Cargas')) {
            return response()->json(['error' => 'Sin permisos'], 403);
        }
        
        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            return response()->json(['error' => 'Sin permisos'], 403);
        }
        
        $itemsCount = $billOfLading->shipmentItems()->count();
        
        return response()->json([
            'hasItems' => $itemsCount > 0,
            'itemsCount' => $itemsCount,
            'canBeDeleted' => $billOfLading->canBeDeleted()
        ]);
    }

    /**
     * Eliminar conocimiento de embarque
     */
    public function destroy(BillOfLading $billOfLading)
    {
        // Verificar permisos para eliminar conocimientos
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para eliminar conocimientos de embarque.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        // Verificar que el conocimiento pertenece a la empresa del usuario
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para eliminar este conocimiento.');
        }

        // Verificar que puede ser eliminado
        // Verificar que puede ser eliminado
        if (!$billOfLading->canBeDeleted()) {
            $itemsCount = $billOfLading->shipmentItems()->count();
            $errorMessage = 'Este conocimiento no puede ser eliminado.';
            
            if ($itemsCount > 0) {
                $errorMessage .= " Tiene {$itemsCount} item(s) asociados. Elimine primero todos los items.";
            } elseif ($billOfLading->status !== 'draft') {
                $errorMessage .= " Solo se pueden eliminar conocimientos en estado borrador.";
            } elseif ($billOfLading->webservice_sent_at || $billOfLading->argentina_sent_at || $billOfLading->paraguay_sent_at) {
                $errorMessage .= " Ya fue enviado a webservices.";
            }
            
            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('error', $errorMessage);
        }

        try {
            DB::beginTransaction();

            $billNumber = $billOfLading->bill_number;
            
            // Eliminar el conocimiento (soft delete)
            $billOfLading->delete();

            DB::commit();

            Log::info('Conocimiento de embarque eliminado', [
                'bill_id' => $billOfLading->id,
                'bill_number' => $billNumber,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.index')
                ->with('success', "Conocimiento de embarque {$billNumber} eliminado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar conocimiento de embarque', [
                'bill_id' => $billOfLading->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->with('error', 'Error al eliminar el conocimiento de embarque: ' . $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS AUXILIARES ACTUALIZADOS
    // ========================================

    /**
     * Verificar acceso a recurso por empresa
     */
    private function canAccessCompanyResource($resource, $companyPath): bool
    {
        $company = $this->getUserCompany();
        if (!$company) return false;

        // Navegar por la relación usando dot notation
        $resourceCompanyId = data_get($resource, $companyPath);
        
        return $resourceCompanyId === $company->id;
    }

    /**
     * Obtener permisos específicos para un conocimiento
     */
    private function getBillPermissions(BillOfLading $billOfLading): array
    {
        return [
            'canEdit' => $this->hasCompanyRole('Cargas') && $billOfLading->canBeEdited(),
            'canDelete' => $this->hasCompanyRole('Cargas') && $billOfLading->canBeDeleted(),
            'canVerify' => $this->hasCompanyRole('Cargas') && !$billOfLading->verified_at && $billOfLading->status === 'draft',
            'canSendToArgentina' => $this->hasCompanyRole('Cargas') && $billOfLading->isReadyForWebservice() && !$billOfLading->argentina_sent_at,
            'canSendToParaguay' => $this->hasCompanyRole('Cargas') && $billOfLading->isReadyForWebservice() && !$billOfLading->paraguay_sent_at,
            'canReleaseOriginal' => $this->hasCompanyRole('Cargas') && !$billOfLading->original_released && $billOfLading->status === 'verified',
            'canDuplicate' => $this->hasCompanyRole('Cargas'),
            'canGeneratePdf' => true,
            'canManageAttachments' => $this->hasCompanyRole('Cargas'),
        ];
    }

    /**
     * Generar número de conocimiento automático
     */
    private function generateBillNumber($company): string
    {
        $prefix = strtoupper(substr($company->legal_name, 0, 3));
        $year = date('Y');
        $sequence = BillOfLading::whereHas('shipment.voyage', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereYear('created_at', $year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    /**
     * Obtener estadísticas para el índice
     */
    private function getIndexStats($company): array
    {
        $baseQuery = BillOfLading::whereHas('shipment.voyage', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        return [
            'total' => $baseQuery->count(),
            'draft' => $baseQuery->where('status', 'draft')->count(),
            'verified' => $baseQuery->whereNotNull('verified_at')->count(),
            'expired' => $baseQuery->expired()->count(),
            'dangerous_goods' => $baseQuery->where('contains_dangerous_goods', true)->count(),
            'consolidated' => $baseQuery->where('is_consolidated', true)->count(),
            'sent_to_argentina' => $baseQuery->whereNotNull('argentina_sent_at')->count(),
            'sent_to_paraguay' => $baseQuery->whereNotNull('paraguay_sent_at')->count(),
        ];
    }

    /**
     * Obtener datos para formularios (CORREGIDO - SIN CLIENT_ROLES)
     */
    private function getFormData($company, Request $request, ?BillOfLading $billOfLading = null): array
    {
        return [
            'shipments' => Shipment::whereHas('voyage', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })->with('voyage:id,voyage_number')->get(['id', 'shipment_number', 'voyage_id']),
            
            // CORREGIDO: Todos los clientes pueden ser cualquier cosa
            'shippers' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'tax_id']),

            'consignees' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'tax_id']),

            'notifyParties' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'tax_id']),
            
            'cargoOwners' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'tax_id']),
            
            'loadingPorts' => Port::where('active', true)
                ->with('country:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'country_id']),
            
            'dischargePorts' => Port::where('active', true)
                ->with('country:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'country_id']),
            
            'transshipmentPorts' => Port::where('active', true)
                ->with('country:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'country_id']),
            
            'finalDestinationPorts' => Port::where('active', true)
                ->with('country:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'country_id']),
            
            'customsOffices' => CustomOffice::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            
            'cargoTypes' => CargoType::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            
            'packagingTypes' => PackagingType::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description']),

            // NUEVAS OPCIONES PARA FORMULARIOS
            'billTypes' => [
                'original' => 'Original',
                'copy' => 'Copia',
                'duplicate' => 'Duplicado',
                'amendment' => 'Enmienda',
            ],

            'priorityLevels' => [
                'low' => 'Baja',
                'normal' => 'Normal',
                'high' => 'Alta',
                'urgent' => 'Urgente',
            ],

            'freightTerms' => [
                'prepaid' => 'Flete Pagado',
                'collect' => 'Flete por Cobrar',
                'prepaid_advance' => 'Flete Pagado Anticipado',
            ],

            'paymentTerms' => [
                'cash' => 'Efectivo',
                'credit_card' => 'Tarjeta de Crédito',
                'bank_transfer' => 'Transferencia Bancaria',
                'check' => 'Cheque',
                'letter_of_credit' => 'Carta de Crédito',
                'payment_on_delivery' => 'Pago Contra Entrega',
                'advance_payment' => 'Pago Anticipado',
                'consignment' => 'Consignación',
            ],

            'incotermsList' => [
                'EXW' => 'Ex Works',
                'FCA' => 'Free Carrier',
                'CPT' => 'Carriage Paid To',
                'CIP' => 'Carriage and Insurance Paid To',
                'DAP' => 'Delivered at Place',
                'DPU' => 'Delivered at Place Unloaded',
                'DDP' => 'Delivered Duty Paid',
                'FAS' => 'Free Alongside Ship',
                'FOB' => 'Free on Board',
                'CFR' => 'Cost and Freight',
                'CIF' => 'Cost, Insurance and Freight',
            ],

            'currencies' => [
                'USD' => 'Dólar Estadounidense (USD)',
                'EUR' => 'Euro (EUR)',
                'ARS' => 'Peso Argentino (ARS)',
                'PYG' => 'Guaraní Paraguayo (PYG)',
                'BRL' => 'Real Brasileño (BRL)',
                'UYU' => 'Peso Uruguayo (UYU)',
            ],

            'measurementUnits' => [
                'KG' => 'Kilogramos',
                'TON' => 'Toneladas',
                'LB' => 'Libras',
                'CBM' => 'Metros Cúbicos',
                'CFT' => 'Pies Cúbicos',
                'LTR' => 'Litros',
                'PCS' => 'Piezas',
                'PKG' => 'Bultos',
            ],

            'defaultValues' => [
                'bill_date' => now()->format('Y-m-d'),
                'loading_date' => null,
                'arrival_date' => null,
                'freight_terms' => 'prepaid',
                'bill_type' => 'original',
                'priority_level' => 'normal',
                'currency_code' => 'USD',
                'measurement_unit' => 'KG',
                'requires_inspection' => false,
                'contains_dangerous_goods' => false,
                'requires_refrigeration' => false,
                'is_transhipment' => false,
                'is_partial_shipment' => false,
                'allows_partial_delivery' => true,
                'requires_documents_on_arrival' => false,
                'is_consolidated' => false,
                'is_master_bill' => false,
                'is_house_bill' => false,
                'requires_surrender' => false,
                'payment_terms' => 'cash',
            ],

            'paymentMethods' => [
                'cash' => 'Efectivo',
                'credit' => 'Crédito',
                'wire_transfer' => 'Transferencia',
                'check' => 'Cheque',
                'letter_of_credit' => 'Carta de Crédito',
            ],

            'deliveryTerms' => [
                'door_to_door' => 'Puerta a Puerta',
                'port_to_port' => 'Puerto a Puerto',
                'door_to_port' => 'Puerta a Puerto',
                'port_to_door' => 'Puerto a Puerta',
            ],

            'masterBills' => BillOfLading::whereHas('shipment.voyage', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->where('is_master_bill', true)
            ->where('status', '!=', 'cancelled')
            ->orderBy('bill_number')
            ->get(['id', 'bill_number'])
            ->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'display_name' => $bill->bill_number
                ];
            }),        
                    
        ];
    }

    /**
     * Obtener datos para filtros (CORREGIDO - SIN CLIENT_ROLES)
     */
    private function getFilterData($company): array
    {
        return [
            'shipments' => Shipment::whereHas('voyage', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })->with('voyage:id,voyage_number')->get(['id', 'shipment_number', 'voyage_id']),
            
            // CORREGIDO: Todos los clientes pueden ser cualquier cosa
            'shippers' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            
            'consignees' => Client::where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            
            'loadingPorts' => Port::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'country_id']),
            
            'dischargePorts' => Port::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'country_id']),
            
            'statuses' => [
                'draft' => 'Borrador',
                'pending_review' => 'Pendiente Revisión',
                'verified' => 'Verificado',
                'sent_to_customs' => 'Enviado a Aduana',
                'accepted' => 'Aceptado',
                'rejected' => 'Rechazado',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
            ],

            // NUEVOS FILTROS AGREGADOS
            'billTypes' => [
                'original' => 'Original',
                'copy' => 'Copia',
                'duplicate' => 'Duplicado',
                'amendment' => 'Enmienda',
            ],

            'priorityLevels' => [
                'low' => 'Baja',
                'normal' => 'Normal',
                'high' => 'Alta',
                'urgent' => 'Urgente',
            ],

            'argentinaStatuses' => [
                'pending' => 'Pendiente',
                'sent' => 'Enviado',
                'approved' => 'Aprobado',
                'rejected' => 'Rechazado',
                'error' => 'Error',
            ],

            'paraguayStatuses' => [
                'pending' => 'Pendiente',
                'sent' => 'Enviado',
                'approved' => 'Aprobado',
                'rejected' => 'Rechazado',
                'error' => 'Error',
            ],

            'consolidationOptions' => [
                'all' => 'Todos',
                'consolidated' => 'Solo Consolidados',
                'master_bills' => 'Solo Conocimientos Madre',
                'house_bills' => 'Solo Conocimientos Hijo',
                'non_consolidated' => 'Solo No Consolidados',
            ],

            'documentationStatus' => [
                'complete' => 'Documentación Completa',
                'incomplete' => 'Documentación Incompleta',
                'original_released' => 'Original Liberado',
                'original_pending' => 'Original Pendiente',
            ],
        ];
    }

    // ========================================
    // MÉTODOS DE NEGOCIO NUEVOS
    // ========================================

    /**
     * Verificar conocimiento de embarque
     */
    public function verify(Request $request, BillOfLading $billOfLading)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para verificar conocimientos de embarque.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para verificar este conocimiento.');
        }

        // Verificar que puede ser verificado
        if ($billOfLading->verified_at) {
            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('error', 'Este conocimiento ya está verificado.');
        }

        if ($billOfLading->status !== 'draft') {
            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('error', 'Solo se pueden verificar conocimientos en estado borrador.');
        }

        // Validar campos requeridos
        if (!$billOfLading->hasRequiredFields()) {
            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('error', 'El conocimiento no tiene todos los campos requeridos completados.');
        }

        try {
            DB::beginTransaction();

            // Verificar el conocimiento
            $billOfLading->verify(Auth::user());

            DB::commit();

            Log::info('Conocimiento de embarque verificado', [
                'bill_id' => $billOfLading->id,
                'bill_number' => $billOfLading->bill_number,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('success', "Conocimiento {$billOfLading->bill_number} verificado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al verificar conocimiento de embarque', [
                'bill_id' => $billOfLading->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->with('error', 'Error al verificar el conocimiento: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar estado del conocimiento
     */
    public function updateStatus(Request $request, BillOfLading $billOfLading)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para cambiar el estado de conocimientos.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para modificar este conocimiento.');
        }

        $request->validate([
            'status' => 'required|in:draft,pending_review,verified,sent_to_customs,accepted,rejected,completed,cancelled',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $billOfLading->status;
            $newStatus = $request->get('status');

            // Validar transición de estado
            if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
                return redirect()->back()
                    ->with('error', "No se puede cambiar el estado de {$oldStatus} a {$newStatus}.");
            }

            $billOfLading->update([
                'status' => $newStatus,
                'last_updated_by_user_id' => Auth::id(),
                'internal_notes' => $billOfLading->internal_notes . "\n" . 
                    "[" . now()->format('Y-m-d H:i') . "] Estado cambiado de {$oldStatus} a {$newStatus} por " . Auth::user()->name . 
                    ($request->get('notes') ? ": " . $request->get('notes') : "")
            ]);

            DB::commit();

            Log::info('Estado de conocimiento actualizado', [
                'bill_id' => $billOfLading->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.show', $billOfLading)
                ->with('success', "Estado del conocimiento actualizado a {$billOfLading->status_label}.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    /**
     * Duplicar conocimiento de embarque
     */
    public function duplicate(Request $request, BillOfLading $billOfLading)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para duplicar conocimientos.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para duplicar este conocimiento.');
        }

        try {
            DB::beginTransaction();

            $company = $this->getUserCompany();

            // Preparar datos para el duplicado
            $duplicateData = $billOfLading->toArray();
            
            // Limpiar campos que no deben duplicarse
            unset($duplicateData['id']);
            unset($duplicateData['created_at']);
            unset($duplicateData['updated_at']);
            unset($duplicateData['deleted_at']);
            
            // Campos específicos a resetear
            $duplicateData['bill_number'] = $this->generateBillNumber($company);
            $duplicateData['bill_type'] = 'duplicate';
            $duplicateData['status'] = 'draft';
            $duplicateData['verified_at'] = null;
            $duplicateData['verified_by_user_id'] = null;
            $duplicateData['created_by_user_id'] = Auth::id();
            $duplicateData['last_updated_by_user_id'] = Auth::id();
            $duplicateData['internal_reference'] = $duplicateData['internal_reference'] . '-DUP';
            
            // Limpiar campos de webservices
            $duplicateData['webservice_status'] = null;
            $duplicateData['webservice_reference'] = null;
            $duplicateData['webservice_sent_at'] = null;
            $duplicateData['webservice_response_at'] = null;
            $duplicateData['webservice_error_message'] = null;
            $duplicateData['argentina_bill_id'] = null;
            $duplicateData['paraguay_bill_id'] = null;
            $duplicateData['argentina_status'] = null;
            $duplicateData['paraguay_status'] = null;
            $duplicateData['argentina_sent_at'] = null;
            $duplicateData['paraguay_sent_at'] = null;
            
            // Limpiar campos de documentación
            $duplicateData['original_released'] = false;
            $duplicateData['original_release_date'] = null;
            $duplicateData['documentation_complete'] = false;
            $duplicateData['ready_for_delivery'] = false;

            // Crear el duplicado
            $duplicate = BillOfLading::create($duplicateData);

            DB::commit();

            Log::info('Conocimiento de embarque duplicado', [
                'original_id' => $billOfLading->id,
                'duplicate_id' => $duplicate->id,
                'original_number' => $billOfLading->bill_number,
                'duplicate_number' => $duplicate->bill_number,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('company.bills-of-lading.show', $duplicate)
                ->with('success', "Conocimiento duplicado exitosamente. Nuevo número: {$duplicate->bill_number}");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al duplicar conocimiento', [
                'bill_id' => $billOfLading->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->with('error', 'Error al duplicar el conocimiento: ' . $e->getMessage());
        }
    }

    /**
     * Generar PDF del conocimiento (CORREGIDO)
     */
    public function generatePdf(BillOfLading $billOfLading)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->hasCompanyRole('Desconsolidador') && !$this->hasCompanyRole('Transbordos')) {
            abort(403, 'No tiene permisos para generar PDF.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para generar PDF de este conocimiento.');
        }

        // ✅ CORRECCIÓN: Cargar relaciones a través de la jerarquía correcta
        $billOfLading->load([
            'shipment.voyage.vessel',
            'shipment.voyage.company',
            'shipper.country',
            'consignee.country',
            'notifyParty',
            'cargoOwner',
            'loadingPort.country',
            'dischargePort.country',
            'transshipmentPort.country',
            'finalDestinationPort.country',
            'loadingCustoms',
            'dischargeCustoms',
            'primaryCargoType',
            'primaryPackagingType',
            
            // ✅ CORRECCIÓN: Acceder a shipmentItems a través del shipment
            'shipment.shipmentItems.cargoType',
            'shipment.shipmentItems.packagingType',
            
            'createdByUser',
            'verifiedByUser'
        ]);

        // TODO: Implementar generación de PDF
        // Por ahora retornamos una vista que puede ser convertida a PDF
        return view('company.bills-of-lading.pdf', compact('billOfLading'));
    }

    /**
     * Vista de impresión (CORREGIDO)
     */
    public function print(BillOfLading $billOfLading)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->hasCompanyRole('Desconsolidador') && !$this->hasCompanyRole('Transbordos')) {
            abort(403, 'No tiene permisos para imprimir.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para imprimir este conocimiento.');
        }

        // ✅ CORRECCIÓN: Cargar relaciones a través de la jerarquía correcta
        $billOfLading->load([
            'shipment.voyage.vessel',
            'shipment.voyage.company',
            'shipper',
            'consignee',
            'notifyParty',
            'cargoOwner',
            'loadingPort',
            'dischargePort',
            'transshipmentPort',
            'finalDestinationPort',
            'primaryCargoType',
            'primaryPackagingType',
            
            // ✅ CORRECCIÓN: Acceder a shipmentItems a través del shipment
            'shipment.shipmentItems.cargoType',
            'shipment.shipmentItems.packagingType'
        ]);

        return view('company.bills-of-lading.print', compact('billOfLading'));
    }



    /**
     * Gestionar archivos adjuntos
     */
    public function attachments(BillOfLading $billOfLading)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para gestionar adjuntos.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para gestionar adjuntos de este conocimiento.');
        }

        $attachments = $billOfLading->attachments()->orderBy('created_at', 'desc')->get();

        return view('company.bills-of-lading.attachments', compact('billOfLading', 'attachments'));
    }

    /**
     * Subir archivo adjunto
     */
    public function uploadAttachment(Request $request, BillOfLading $billOfLading)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para subir adjuntos.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para subir adjuntos a este conocimiento.');
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'description' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            // TODO: Implementar lógica de subida de archivos
            // Esto dependerá del sistema de archivos configurado
            
            DB::commit();

            return redirect()->route('company.bills-of-lading.attachments', $billOfLading)
                ->with('success', 'Archivo subido exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Error al subir el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar archivo adjunto
     */
    public function deleteAttachment(BillOfLading $billOfLading, $attachmentId)
    {
        // Verificar permisos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para eliminar adjuntos.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para eliminar adjuntos de este conocimiento.');
        }

        try {
            DB::beginTransaction();

            // TODO: Implementar eliminación de archivos
            // $attachment = $billOfLading->attachments()->findOrFail($attachmentId);
            // $attachment->delete();
            
            DB::commit();

            return redirect()->route('company.bills-of-lading.attachments', $billOfLading)
                ->with('success', 'Archivo eliminado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Error al eliminar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Búsqueda de conocimientos
     */
    public function search(Request $request)
    {
        // Esta es la vista del formulario de búsqueda
        return view('company.bills-of-lading.search');
    }

    /**
     * Resultados de búsqueda
     */
    public function searchResults(Request $request)
    {
        // Redirigir al índice con los parámetros de búsqueda
        return redirect()->route('company.bills-of-lading.index', $request->all());
    }

    /**
     * Exportar conocimientos
     */
    public function export(Request $request)
    {
        // TODO: Implementar exportación
        return redirect()->back()
            ->with('info', 'Función de exportación en desarrollo.');
    }

    /**
     * Exportar en formato específico
     */
    public function exportByFormat(Request $request, $format)
    {
        // TODO: Implementar exportación por formato
        return redirect()->back()
            ->with('info', 'Función de exportación en desarrollo.');
    }

    /**
     * Historial de cambios
     */
    public function history(BillOfLading $billOfLading)
    {
        // Verificar permisos básicos
        if (!$this->canPerform('view_cargas') && !$this->hasCompanyRole('Desconsolidador') && !$this->hasCompanyRole('Transbordos')) {
            abort(403, 'No tiene permisos para ver el historial.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para ver el historial de este conocimiento.');
        }

        // TODO: Implementar historial de cambios
        // Esto requeriría un sistema de auditoría
        
        return view('company.bills-of-lading.history', compact('billOfLading'));
    }

    /**
     * Log de auditoría
     */
    public function auditLog(BillOfLading $billOfLading)
    {
        // Verificar permisos administrativos
        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'No tiene permisos para ver el log de auditoría.');
        }

        // Verificar que pertenece a la empresa
        if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
            abort(403, 'No tiene permisos para ver la auditoría de este conocimiento.');
        }

        // TODO: Implementar log de auditoría
        
        return view('company.bills-of-lading.audit', compact('billOfLading'));
    }

    // ========================================
    // MÉTODOS AUXILIARES PARA VALIDACIONES
    // ========================================

    /**
     * Validar transición de estado
     */
    private function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $validTransitions = [
            'draft' => ['pending_review', 'verified', 'cancelled'],
            'pending_review' => ['draft', 'verified', 'rejected'],
            'verified' => ['sent_to_customs', 'rejected'],
            'sent_to_customs' => ['accepted', 'rejected'],
            'accepted' => ['completed'],
            'rejected' => ['draft', 'pending_review'],
            'completed' => [], // Estado final
            'cancelled' => [], // Estado final
        ];

        return in_array($toStatus, $validTransitions[$fromStatus] ?? []);
    }

/**
 * Descargar plantilla Excel para items del BL
 * ACTUALIZADO: Template completo con todos los campos y ejemplos
 */
public function downloadTemplate(BillOfLading $billOfLading)
{
    // Verificar permisos usando métodos existentes
    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'No tiene permisos para descargar plantillas.');
    }

    // Verificar que pertenece a la empresa
    if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
        abort(403, 'No tiene permisos para acceder a este conocimiento.');
    }

    $filename = "items_template_BL_{$billOfLading->bill_number}_" . date('Y-m-d') . ".csv";
    
    // Headers COMPLETOS basados en campos reales de ShipmentItem
    $headers = [
        'line_number',               // 0
        'item_reference',            // 1 
        'item_description',          // 2
        'cargo_type_id',            // 3
        'packaging_type_id',        // 4
        'package_quantity',         // 5
        'gross_weight_kg',          // 6
        'net_weight_kg',            // 7
        'volume_m3',                // 8
        'declared_value',           // 9
        'currency_code',            // 10
        'unit_of_measure',          // 11
        'country_of_origin',        // 12
        'cargo_marks',              // 13
        'commodity_code',           // 14
        'brand',                    // 15
        'model',                    // 16
        'manufacturer',             // 17
        'lot_number',               // 18
        'is_dangerous_goods',       // 19
        'is_perishable',            // 20
        'requires_refrigeration',   // 21
        'un_number',                // 22
        'imdg_class',               // 23
        'temperature_min',          // 24
        'temperature_max',          // 25
        'renar',                    // 26                      
        'operador_logistico_seguro' // 27
    ];
    
    // Obtener datos de catálogos para ejemplos
    $cargoTypes = CargoType::where('active', true)->orderBy('id')->take(3)->get();
    $packagingTypes = PackagingType::where('active', true)->orderBy('id')->take(3)->get();
    
    // Datos de ejemplo COMPLETOS con todos los campos
    $nextLineNumber = $billOfLading->shipmentItems()->max('line_number') + 1 ?? 1;
    $sampleData = [
        // Fila 1: Ejemplo completo con soja
        [
            $nextLineNumber,                    // line_number
            'SOJA-001',                        // item_reference
            'Soja en grano para exportación calidad industrial', // item_description
            $cargoTypes->first()?->id ?? '1',  // cargo_type_id
            $packagingTypes->first()?->id ?? '1', // packaging_type_id
            40,                                // package_quantity
            2000.00,                          // gross_weight_kg
            1980.00,                          // net_weight_kg
            2.5,                              // volume_m3
            1200.00,                          // declared_value
            'USD',                            // currency_code
            'KG',                             // unit_of_measure
            'AR',                             // country_of_origin
            'SOJA ARG 2025 | HANDLE WITH CARE | KEEP DRY', // cargo_marks
            '120100',                         // commodity_code
            'SOJA ARGENTINA',                 // brand
            '',                               // model
            'Cooperativa San José',           // manufacturer
            'LT240801',                       // lot_number
            0,                                // is_dangerous_goods (0/1)
            0,                                // is_perishable (0/1)
            0,                                // requires_refrigeration (0/1)
            '',                               // un_number
            '',                               // imdg_class
            '',                               // temperature_min
            '',                               // temperature_max
            'N',                              // renar
            'N'                               // operador_logistico_seguro
        ],
        // Fila 2: Ejemplo con mercadería general
        [
            $nextLineNumber + 1,               // line_number
            'ITEM-002',                        // item_reference
            'Descripción completa del segundo item de mercadería', // item_description
            $cargoTypes->get(1)?->id ?? '1',   // cargo_type_id
            $packagingTypes->get(1)?->id ?? '1', // packaging_type_id
            25,                                // package_quantity
            1500.50,                          // gross_weight_kg
            1485.00,                          // net_weight_kg
            1.8,                              // volume_m3
            800.75,                           // declared_value
            'USD',                            // currency_code
            'PCS',                            // unit_of_measure
            'AR',                             // country_of_origin
            'FRAGILE | THIS SIDE UP',         // cargo_marks
            '854430',                         // commodity_code
            'MARCA EJEMPLO',                  // brand
            'MOD-123',                        // model
            'Fabricante Ejemplo SA',          // manufacturer
            'L2025001',                       // lot_number
            0,                                // is_dangerous_goods
            1,                                // is_perishable
            1,                                // requires_refrigeration
            '',                               // un_number
            '',                               // imdg_class
            2.0,                              // temperature_min
            8.0,                              // temperature_max
            'N',                              // renar
            'N'                               // operador_logistico_seguro
        ]
    ];
    
    return \Illuminate\Support\Facades\Response::streamDownload(function() use ($headers, $sampleData) {
        $handle = fopen('php://output', 'w');
        
        // Escribir BOM para UTF-8 (para Excel)
        fwrite($handle, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($handle, $headers, ',', '"');
        
        // Datos de ejemplo
        foreach ($sampleData as $row) {
            fputcsv($handle, $row, ',', '"');
        }
        
        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ]);
}

/**
 * Procesar importación masiva de items desde archivo
 * ACTUALIZADO: Implementación completa con integridad de datos
 */
public function importItems(Request $request, BillOfLading $billOfLading)
{
    // Verificar permisos
    if (!$this->hasCompanyRole('Cargas')) {
        abort(403, 'No tiene permisos para importar items.');
    }

    if (!$this->canAccessCompanyResource($billOfLading, 'shipment.voyage.company_id')) {
        abort(403, 'No tiene permisos para modificar este conocimiento.');
    }

    // Validar estado del BL
    if ($billOfLading->status !== 'draft') {
        return back()
            ->with('error', 'Solo se pueden importar items a conocimientos en estado borrador.');
    }

    // Validar archivo
    $request->validate([
        'import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        'has_headers' => 'boolean',
        'start_row' => 'nullable|integer|min:1|max:100',
    ], [
        'import_file.required' => 'Debe seleccionar un archivo.',
        'import_file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.',
        'import_file.max' => 'El archivo no puede ser mayor a 10MB.',
    ]);

    $file = $request->file('import_file');
    $hasHeaders = $request->boolean('has_headers', true);
    $startRow = $request->integer('start_row', $hasHeaders ? 2 : 1);

    try {
        DB::beginTransaction();

        // ✅ VERIFICAR INTEGRIDAD ANTES DE PROCESAR
        $this->ensureDataIntegrity($billOfLading);
        
        // ✅ LOCK DEL BILL OF LADING para evitar modificaciones concurrentes
        $billOfLading = BillOfLading::lockForUpdate()->find($billOfLading->id);
        
        if (!$billOfLading) {
            throw new \Exception('BillOfLading no encontrado o bloqueado.');
        }

        // Procesar archivo según extensión
        $extension = strtolower($file->getClientOriginalExtension());
        $results = match($extension) {
            'csv' => $this->processItemsCsv($file, $billOfLading, $hasHeaders, $startRow),
            'xlsx', 'xls' => $this->processItemsExcel($file, $billOfLading, $hasHeaders, $startRow),
            default => throw new \Exception('Formato de archivo no soportado: ' . $extension)
        };

        // ✅ VERIFICAR INTEGRIDAD DESPUÉS DE IMPORTAR
        $this->verifyImportIntegrity($billOfLading, $results);

        DB::commit();

        $message = "Importación completada: {$results['imported']} items importados correctamente";
        if ($results['errors'] > 0) {
            $message .= ", {$results['errors']} errores encontrados";
        }

        // ✅ RECALCULAR ESTADÍSTICAS DESPUÉS DEL COMMIT
        $billOfLading->fresh()->recalculateItemStats();

        return redirect()->route('company.bills-of-lading.show', $billOfLading)
            ->with('success', $message)
            ->with('import_details', $results);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error en importación de items', [
            'bill_id' => $billOfLading->id,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return back()
            ->withInput()
            ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
    }
}

// ========================================
// MÉTODOS HELPER PARA IMPORTACIÓN DE ITEMS
// ========================================

/**
 * Verificar integridad de datos del BillOfLading
 */
private function ensureDataIntegrity(BillOfLading $billOfLading): void
{
    // Verificar que todas las relaciones existen
    $checks = [
        'shipment' => $billOfLading->shipment,
        'voyage' => $billOfLading->shipment?->voyage,
        'company' => $billOfLading->shipment?->voyage?->company
    ];
    
    foreach ($checks as $relation => $model) {
        if (!$model) {
            throw new \Exception("Relación {$relation} no encontrada. Integridad comprometida.");
        }
    }
    
    // Verificar permisos de empresa a través de voyage
    $voyageCompanyId = $billOfLading->shipment->voyage->company_id;
    $userCompanyId = $this->getUserCompany()->id;
    
    if ($voyageCompanyId !== $userCompanyId) {
        throw new \Exception('No tiene permisos para modificar este BillOfLading.');
    }
}

/**
 * Verificar integridad post-importación
 */
private function verifyImportIntegrity(BillOfLading $billOfLading, array $results): void
{
    // Verificar que todos los items creados tienen las relaciones correctas
    $itemsCount = ShipmentItem::where('bill_of_lading_id', $billOfLading->id)->count();
    
    if ($itemsCount < $results['imported']) {
        throw new \Exception("Inconsistencia: Se reportaron {$results['imported']} items pero se encontraron {$itemsCount} en BD.");
    }
    
    // Verificar que no hay items huérfanos
    $orphanItems = ShipmentItem::where('bill_of_lading_id', $billOfLading->id)
                              ->whereDoesntHave('billOfLading')
                              ->count();
                              
    if ($orphanItems > 0) {
        throw new \Exception("Se detectaron {$orphanItems} items huérfanos sin relación válida.");
    }
}

/**
 * Procesar archivo CSV de items
 */
private function processItemsCsv($file, BillOfLading $billOfLading, bool $hasHeaders, int $startRow): array
{
    $handle = fopen($file->getPathname(), 'r');
    if (!$handle) {
        throw new \Exception('No se puede abrir el archivo CSV.');
    }

    $imported = 0;
    $errors = 0;
    $errorDetails = [];
    $rowNumber = 0;

    // Obtener el próximo line_number disponible
    $nextLineNumber = $billOfLading->shipmentItems()->max('line_number') + 1 ?? 1;

    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;

        // Saltar filas anteriores al inicio
        if ($rowNumber < $startRow) {
            continue;
        }

        // Saltar fila de headers si aplica
        if ($hasHeaders && $rowNumber === 1) {
            continue;
        }

        // Procesar fila
        try {
            $itemData = $this->parseItemRowFromCsv($row, $billOfLading, $nextLineNumber);
            if ($itemData) {
                $this->createShipmentItemFromData($itemData, $billOfLading);
                $imported++;
                $nextLineNumber++;
            }
        } catch (\Exception $e) {
            $errors++;
            $errorDetails[] = "Fila {$rowNumber}: " . $e->getMessage();
            
            Log::warning('Error procesando fila CSV', [
                'row' => $rowNumber,
                'bill_id' => $billOfLading->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    fclose($handle);

    return [
        'imported' => $imported,
        'errors' => $errors,
        'error_details' => $errorDetails,
        'total_rows' => $rowNumber - ($hasHeaders ? 1 : 0)
    ];
}

/**
 * Procesar archivo Excel de items usando Maatwebsite/Excel
 */
private function processItemsExcel($file, BillOfLading $billOfLading, bool $hasHeaders, int $startRow): array
{
    $imported = 0;
    $errors = 0;
    $errorDetails = [];
    
    // Obtener el próximo line_number disponible
    $nextLineNumber = $billOfLading->shipmentItems()->max('line_number') + 1 ?? 1;
    
    try {
        $rows = Excel::toArray(null, $file)[0]; // Primera hoja
        $totalRows = count($rows);
        $actualStartRow = $hasHeaders ? max(1, $startRow - 1) : $startRow - 1; // Array es 0-indexed
        
        for ($i = $actualStartRow; $i < $totalRows; $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1; // Para mostrar número real de fila
            
            try {
                $itemData = $this->parseItemRowFromArray($row, $billOfLading, $nextLineNumber);
                if ($itemData) {
                    $this->createShipmentItemFromData($itemData, $billOfLading);
                    $imported++;
                    $nextLineNumber++;
                }
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = "Fila {$rowNumber}: " . $e->getMessage();
                
                Log::warning('Error procesando fila Excel', [
                    'row' => $rowNumber,
                    'bill_id' => $billOfLading->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
    } catch (\Exception $e) {
        throw new \Exception('Error leyendo archivo Excel: ' . $e->getMessage());
    }

    return [
        'imported' => $imported,
        'errors' => $errors,
        'error_details' => $errorDetails,
        'total_rows' => $totalRows - ($hasHeaders ? 1 : 0)
    ];
}

/**
 * Parsear fila CSV a datos de item
 */
private function parseItemRowFromCsv(array $row, BillOfLading $billOfLading, int $lineNumber): ?array
{
    // Verificar que la fila tenga datos válidos
    $cleanRow = array_filter($row, fn($cell) => !empty(trim($cell ?? '')));
    if (empty($cleanRow)) {
        return null; // Fila vacía, saltar sin error
    }

    // Mapeo de columnas basado en template generado en downloadTemplate()
    $mapping = [
        'line_number' => 0,
        'item_reference' => 1,
        'item_description' => 2,
        'cargo_type_id' => 3,
        'packaging_type_id' => 4,
        'package_quantity' => 5,
        'gross_weight_kg' => 6,
        'net_weight_kg' => 7,
        'volume_m3' => 8,
        'declared_value' => 9,
        'currency_code' => 10,
        'unit_of_measure' => 11,
        'country_of_origin' => 12,
        'cargo_marks' => 13,
        'commodity_code' => 14,
        'brand' => 15,
        'model' => 16,
        'manufacturer' => 17,
        'lot_number' => 18,
        'is_dangerous_goods' => 19,
        'is_perishable' => 20,
        'requires_refrigeration' => 21,
        'un_number' => 22,
        'imdg_class' => 23,
        'temperature_min' => 24,
        'temperature_max' => 25,
        'renar' => 26,
        'operador_logistico_seguro' => 27
    ];

    return $this->extractItemDataFromRow($row, $mapping, $billOfLading, $lineNumber);
}

/**
 * Parsear fila de array (Excel) a datos de item
 */
private function parseItemRowFromArray(array $row, BillOfLading $billOfLading, int $lineNumber): ?array
{
    // Verificar que la fila tenga datos válidos
    $hasData = false;
    foreach ($row as $cell) {
        if (!empty(trim($cell ?? ''))) {
            $hasData = true;
            break;
        }
    }

    if (!$hasData) {
        return null; // Fila vacía, saltar sin error
    }

    // Usar mismo mapeo que CSV
    $mapping = [
        'line_number' => 0,
        'item_reference' => 1,
        'item_description' => 2,
        'cargo_type_id' => 3,
        'packaging_type_id' => 4,
        'package_quantity' => 5,
        'gross_weight_kg' => 6,
        'net_weight_kg' => 7,
        'volume_m3' => 8,
        'declared_value' => 9,
        'currency_code' => 10,
        'unit_of_measure' => 11,
        'country_of_origin' => 12,
        'cargo_marks' => 13,
        'commodity_code' => 14,
        'brand' => 15,
        'model' => 16,
        'manufacturer' => 17,
        'lot_number' => 18,
        'is_dangerous_goods' => 19,
        'is_perishable' => 20,
        'requires_refrigeration' => 21,
        'un_number' => 22,
        'imdg_class' => 23,
        'temperature_min' => 24,
        'temperature_max' => 25,
        'renar' => 26,
        'operador_logistico_seguro' => 27
    ];

    return $this->extractItemDataFromRow($row, $mapping, $billOfLading, $lineNumber);
}

/**
 * Extraer datos del item desde fila parseada
 */
private function extractItemDataFromRow(array $row, array $mapping, BillOfLading $billOfLading, int $lineNumber): array
{
    // Función helper para obtener valor seguro
    $getValue = function($index, $default = null) use ($row) {
        return isset($row[$index]) ? trim($row[$index] ?? '') : $default;
    };

    // Validar campos obligatorios
    $description = $getValue($mapping['item_description']);
    if (empty($description)) {
        throw new \Exception('Descripción del item es obligatoria');
    }

    // Resolver IDs de tipos
    $cargoTypeId = $this->resolveCargoTypeId($getValue($mapping['cargo_type_id']));
    $packagingTypeId = $this->resolvePackagingTypeId($getValue($mapping['packaging_type_id']));

    // Validar pesos
    $grossWeight = floatval($getValue($mapping['gross_weight_kg'], 0));
    if ($grossWeight <= 0) {
        throw new \Exception('Peso bruto debe ser mayor a 0');
    }

    $netWeight = floatval($getValue($mapping['net_weight_kg'], 0));
    if ($netWeight <= 0) {
        $netWeight = $grossWeight * 0.85; // Estimación automática
    }

    return [
        'bill_of_lading_id' => $billOfLading->id,
        'line_number' => $lineNumber,
        'item_reference' => $getValue($mapping['item_reference'], 'ITEM-' . $lineNumber),
        'item_description' => $description,
        'cargo_type_id' => $cargoTypeId,
        'packaging_type_id' => $packagingTypeId,
        'package_quantity' => intval($getValue($mapping['package_quantity'], 1)),
        'gross_weight_kg' => $grossWeight,
        'net_weight_kg' => $netWeight,
        'volume_m3' => floatval($getValue($mapping['volume_m3'], 0)) ?: null,
        'declared_value' => floatval($getValue($mapping['declared_value'], 0)) ?: null,
        'currency_code' => $getValue($mapping['currency_code'], 'USD'),
        'unit_of_measure' => $getValue($mapping['unit_of_measure'], 'KG'),
        'country_of_origin' => strtoupper($getValue($mapping['country_of_origin'], 'AR')),
        'cargo_marks' => $getValue($mapping['cargo_marks']),
        'commodity_code' => $getValue($mapping['commodity_code']),
        'brand' => $getValue($mapping['brand']),
        'model' => $getValue($mapping['model']),
        'manufacturer' => $getValue($mapping['manufacturer']),
        'lot_number' => $getValue($mapping['lot_number']),
        'is_dangerous_goods' => $this->parseBooleanValue($getValue($mapping['is_dangerous_goods'], '0')),
        'is_perishable' => $this->parseBooleanValue($getValue($mapping['is_perishable'], '0')),
        'requires_refrigeration' => $this->parseBooleanValue($getValue($mapping['requires_refrigeration'], '0')),
        'un_number' => $getValue($mapping['un_number']),
        'imdg_class' => $getValue($mapping['imdg_class']),
        'temperature_min' => floatval($getValue($mapping['temperature_min'], 0)) ?: null,
        'temperature_max' => floatval($getValue($mapping['temperature_max'], 0)) ?: null,
        'renar' => $getValue($mapping['renar'], 'N'),
        'operador_logistico_seguro' => $getValue($mapping['operador_logistico_seguro'], 'N'),
        'status' => 'draft',
        'created_date' => now(),
        'created_by_user_id' => Auth::id(),
        'last_updated_date' => now(),
        'last_updated_by_user_id' => Auth::id(),
    ];
}

/**
 * Resolver ID de tipo de carga
 */
private function resolveCargoTypeId($value): int
{
    if (empty($value)) {
        return CargoType::where('active', true)->first()?->id ?? 1;
    }

    // Si es numérico, buscar por ID
    if (is_numeric($value)) {
        $cargoType = CargoType::where('id', $value)->where('active', true)->first();
        if ($cargoType) {
            return $cargoType->id;
        }
    }

    // Buscar por nombre
    $cargoType = CargoType::where('name', 'LIKE', '%' . $value . '%')
        ->where('active', true)
        ->first();

    if ($cargoType) {
        return $cargoType->id;
    }

    // Retornar el primero disponible como fallback
    return CargoType::where('active', true)->first()?->id ?? 1;
}

/**
 * Resolver ID de tipo de embalaje
 */
private function resolvePackagingTypeId($value): int
{
    if (empty($value)) {
        return PackagingType::where('active', true)->first()?->id ?? 1;
    }

    // Si es numérico, buscar por ID
    if (is_numeric($value)) {
        $packagingType = PackagingType::where('id', $value)->where('active', true)->first();
        if ($packagingType) {
            return $packagingType->id;
        }
    }

    // Buscar por nombre
    $packagingType = PackagingType::where('name', 'LIKE', '%' . $value . '%')
        ->where('active', true)
        ->first();

    if ($packagingType) {
        return $packagingType->id;
    }

    // Retornar el primero disponible como fallback
    return PackagingType::where('active', true)->first()?->id ?? 1;
}

/**
 * Parsear valor booleano desde Excel/CSV
 */
private function parseBooleanValue($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $value = strtolower(trim($value ?? ''));
    return in_array($value, ['1', 'true', 'yes', 'sí', 'si', 'y', 's']);
}

/**
 * Crear ShipmentItem desde datos parseados con validación de integridad
 */
private function createShipmentItemFromData(array $data, BillOfLading $billOfLading): ShipmentItem
{
    // ✅ VERIFICAR que line_number no existe
    $existingItem = ShipmentItem::where('bill_of_lading_id', $billOfLading->id)
                                ->where('line_number', $data['line_number'])
                                ->first();
    
    if ($existingItem) {
        throw new \Exception("Ya existe un item con line_number {$data['line_number']} en este BL.");
    }
    
    // Validar datos antes de crear
    $validator = Validator::make($data, [
        'bill_of_lading_id' => 'required|exists:bills_of_lading,id',
        'line_number' => 'required|integer|min:1',
        'item_description' => 'required|string|max:2000',
        'cargo_type_id' => 'required|exists:cargo_types,id',
        'packaging_type_id' => 'required|exists:packaging_types,id',
        'package_quantity' => 'required|integer|min:1',
        'gross_weight_kg' => 'required|numeric|min:0.01',
        'country_of_origin' => 'required|string|size:2',
    ]);

    if ($validator->fails()) {
        throw new \Exception('Datos inválidos: ' . implode(', ', $validator->errors()->all()));
    }

    // ✅ CREAR CON VERIFICACIÓN DE FOREIGN KEYS
    try {
        $item = ShipmentItem::create($data);
        
        // ✅ VERIFICAR que se creó correctamente
        if (!$item->billOfLading) {
            throw new \Exception('Error: Item creado pero relación BillOfLading no establecida.');
        }
        
        return $item;
        
    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->getCode() === '23000') { // Foreign key constraint
            throw new \Exception('Error de integridad: Datos relacionados no válidos.');
        }
        throw $e;
    }
}

}

// ========================================
// NOTAS PARA DESARROLLO FUTURO:
// ========================================

/*
FUNCIONALIDADES PENDIENTES DE IMPLEMENTAR:

1. WEBSERVICES ARGENTINA/PARAGUAY:
   - sendToArgentina() method
   - sendToParaguay() method
   - Integration with actual APIs
   - Response handling and status updates

2. CONSOLIDACIÓN AVANZADA:
   - markAsConsolidated() method
   - Master/House bill relationships
   - Consolidation management

3. LIBERACIÓN DE ORIGINALES:
   - releaseOriginal() method
   - Document tracking
   - Original document workflow

4. ARCHIVOS Y DOCUMENTOS:
   - File upload system
   - Document management
   - PDF generation with proper library

5. AUDITORÍA Y HISTORIAL:
   - Change tracking system
   - Audit logs
   - User activity monitoring

6. EXPORTACIÓN:
   - Excel export
   - CSV export
   - Custom format exports

7. NOTIFICACIONES:
   - Email notifications
   - System alerts
   - Workflow notifications

8. REPORTES:
   - Manifest reports
   - Statistical reports
   - Custom reports

CAMPOS DE Form Requests A ACTUALIZAR:

CreateBillOfLadingRequest:
- Agregar validaciones para todos los campos nuevos
- Validaciones condicionales para consolidación
- Validaciones para mercancías peligrosas
- Validaciones financieras

UpdateBillOfLadingRequest:
- Mismas validaciones que Create
- Validaciones de estado para edición
- Restricciones según webservice status

*/
