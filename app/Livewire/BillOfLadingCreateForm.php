<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\Port;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\CustomOffice;
use App\Models\BillOfLading;
use App\Traits\UserHelper;

class BillOfLadingCreateForm extends Component
{
    use UserHelper;

    // === DATOS BÁSICOS ===
    public $shipment_id = '';
    public $bill_number = '';
    public $bill_date = '';
    public $loading_date = '';
    public $origin_location;
    public $origin_country_code;
    public $origin_loading_date;
    public $destination_country_code;
    public $discharge_customs_code;
    public $operational_discharge_code;
    public $discharge_date = '';
    public $freight_terms = 'prepaid';
    public $payment_terms = 'cash';
    public $currency_code = 'USD';
    public $incoterms = '';

    // === PARTES INVOLUCRADAS ===
    public $shipper_id = '';
    public $consignee_id = '';
    public $notify_party_id = '';
    public $cargo_owner_id = '';

    // === PUERTOS Y RUTAS ===
    public $loading_port_id = '';
    public $discharge_port_id = '';
    public $transshipment_port_id = '';
    public $final_destination_port_id = '';
    public $loading_customs_id = '';
    public $discharge_customs_id = '';

    // === MERCANCÍAS ===
    public $primary_cargo_type_id = '';
    public $primary_packaging_type_id = '';
    public $cargo_description = '';
    public $cargo_marks = '';
    public $commodity_code = '';

    // === CANTIDADES Y PESOS ===
    public $total_packages = 1;
    public $gross_weight_kg = 0;
    public $net_weight_kg = 0;
    public $volume_m3 = 0;
    public $measurement_unit = 'KG';

    // === CARACTERÍSTICAS ESPECIALES ===
    public $contains_dangerous_goods = false;
    public $un_number = '';
    public $imdg_class = '';
    public $requires_refrigeration = false;
    public $is_perishable = false;
    public $is_priority = false;

    // === CONSOLIDACIÓN ===
    public $is_consolidated = false;
    public $is_master_bill = false;
    public $is_house_bill = false;
    public $master_bill_number = '';

    // === DOCUMENTACIÓN ===
    public $original_released = false;
    public $documentation_complete = false;
    public $requires_inspection = false;

    // Colecciones para selectores
    public $clients;
    public $availableShipments;
    public $loadingPorts;
    public $dischargePorts;
    public $transshipmentPorts;
    public $finalDestinationPorts;
    public $customsOffices;
    public $cargoTypes;
    public $packagingTypes;
    public $countries;

    // manejo de BL padre/hijo
    public $availableMasterBills = [];

    // Control de UI
    public $loading = false;
    public $preventNavigation = false;

    // Modal para crear cliente
    public $showCreateClientModal = false;
    public $clientType = ''; // 'shipper', 'consignee', 'notify', 'cargo_owner'
    public $new_legal_name = '';
    public $new_tax_id = '';
    public $new_country_id = '';
    public $new_email = '';
    public $new_phone = '';
    public $new_address = '';
    public $new_city = '';

    // Listeners para componentes de búsqueda de clientes
    protected $listeners = [
        'clientSelected' => 'handleClientSelected',
        'clientCreated' => 'refreshClients',
        'openCreateModal' => 'openCreateClientModal'
    ];

   public function handleClientSelected($data)
{
    // El componente SearchClient envía los datos como array
    $fieldName = $data['fieldName'] ?? $data[0] ?? null;
    $clientId = $data['clientId'] ?? $data[1] ?? null;
    
    if ($fieldName && $clientId) {
        $this->$fieldName = $clientId;
        $this->resetErrorBag($fieldName);
        $this->preventNavigation = true;
    }
}

    public function openCreateClientModal($type)
    {
        $this->clientType = $type;
        $this->showCreateClientModal = true;
        $this->resetNewClientData();
    }

    public function resetNewClientData()
    {
        $this->new_legal_name = '';
        $this->new_tax_id = '';
        $this->new_country_id = '';
        $this->new_email = '';
        $this->new_phone = '';
        $this->new_address = '';
        $this->new_city = '';
    }

    public function createClient()
{
    $this->validate([
        'new_legal_name' => 'required|string|max:255',
        'new_tax_id' => 'required|string|max:50',
        'new_country_id' => 'required|exists:countries,id',
        'new_email' => 'nullable|email|max:255',
        'new_phone' => 'nullable|string|max:50',
        'new_address' => 'nullable|string|max:255',
        'new_city' => 'nullable|string|max:100',
    ]);

    $company = auth()->user()->userable_type === 'App\\Models\\Company' 
        ? auth()->user()->userable 
        : auth()->user()->userable->company;

    try {
        \DB::beginTransaction();

        // Verificar cliente existente
        $existingClient = Client::where('tax_id', $this->new_tax_id)
                            ->where('country_id', $this->new_country_id)
                            ->first();

        if ($existingClient) {
            $client = $existingClient;
            $message = 'Cliente existente vinculado exitosamente.';
        } else {
            // Obtener document_type_id válido
            $documentTypeId = \App\Models\DocumentType::where('country_id', $this->new_country_id)
                                                    ->where('active', true)
                                                    ->first()?->id ?? 1;

            // Crear nuevo cliente
            $client = Client::create([
                'legal_name' => $this->new_legal_name,
                'tax_id' => $this->new_tax_id,
                'country_id' => $this->new_country_id,
                'document_type_id' => $documentTypeId,
                'email' => $this->new_email,
                'address' => $this->new_address,
                'status' => 'active',
                'created_by_company_id' => $company->id,
                'created_by_user_id' => auth()->id(),
            ]);

            $message = 'Cliente creado exitosamente.';
        }

        \DB::commit();

        // Asignar cliente al campo correspondiente
        switch ($this->clientType) {
            case 'shipper':
                $this->shipper_id = $client->id;
                break;
            case 'consignee':
                $this->consignee_id = $client->id;
                break;
            case 'notify':
                $this->notify_party_id = $client->id;
                break;
            case 'cargo_owner':
                $this->cargo_owner_id = $client->id;
                break;
        }

        $this->refreshClients();
        $this->showCreateClientModal = false;
        
        session()->flash('message', $message);

    } catch (\Exception $e) {
        \DB::rollBack();
        session()->flash('error', 'Error al crear el cliente: ' . $e->getMessage());
    }
}

    public function cancelCreateClient()
    {
        $this->showCreateClientModal = false;
        $this->resetNewClientData();
    }

    public function refreshClients()
    {
        $this->loadFormData();
    }

    protected $rules = [
        'shipment_id' => 'required|exists:shipments,id',
        'shipper_id' => 'required|exists:clients,id',
        'consignee_id' => 'required|exists:clients,id|different:shipper_id',
        'notify_party_id' => 'nullable|exists:clients,id',
        'cargo_owner_id' => 'nullable|exists:clients,id',
        'loading_port_id' => 'required|exists:ports,id',
        'discharge_port_id' => 'required|exists:ports,id|different:loading_port_id',
        'transshipment_port_id' => 'nullable|exists:ports,id',
        'final_destination_port_id' => 'nullable|exists:ports,id',
        'loading_customs_id' => 'nullable|exists:customs_offices,id',
        'discharge_customs_id' => 'nullable|exists:customs_offices,id',
        'primary_cargo_type_id' => 'required|exists:cargo_types,id',
        'primary_packaging_type_id' => 'required|exists:packaging_types,id',
        'bill_number' => 'required|string|max:100',
        'bill_date' => 'required|date|before_or_equal:today',
        'loading_date' => 'required|date|after_or_equal:bill_date',
        'discharge_date' => 'nullable|date|after_or_equal:loading_date',
        'origin_location' => 'nullable|string|max:50',
        'origin_country_code' => 'nullable|string|size:3',
        'origin_loading_date' => 'nullable|date',
        'destination_country_code' => 'nullable|string|size:3',
        'discharge_customs_code' => 'nullable|string|max:3',
        'operational_discharge_code' => 'nullable|string|max:5',
        'freight_terms' => 'required|in:prepaid,collect',
        'payment_terms' => 'required|in:cash,credit,advance',
        'currency_code' => 'required|in:USD,ARS,EUR,BRL',
        'incoterms' => 'nullable|in:EXW,FCA,FAS,FOB,CFR,CIF,CPT,CIP,DAP,DPU,DDP',
        'cargo_description' => 'required|string|max:2000',
        'cargo_marks' => 'nullable|string|max:1000',
        'commodity_code' => 'nullable|string|max:50',
        'total_packages' => 'required|integer|min:1',
        'gross_weight_kg' => 'required|numeric|min:0.01',
        'net_weight_kg' => 'nullable|numeric|min:0|lte:gross_weight_kg',
        'volume_m3' => 'nullable|numeric|min:0',
        'measurement_unit' => 'nullable|string|max:20',
        'contains_dangerous_goods' => 'boolean',
        'un_number' => 'nullable|string|max:10|required_if:contains_dangerous_goods,true',
        'imdg_class' => 'nullable|string|max:10|required_if:contains_dangerous_goods,true',
        'requires_refrigeration' => 'boolean',
        'is_perishable' => 'boolean',
        'is_priority' => 'boolean',
        'requires_inspection' => 'boolean',
        'is_consolidated' => 'boolean',
        'is_master_bill' => 'boolean',
        'is_house_bill' => 'boolean',
        'master_bill_number' => 'nullable|string|max:50|required_if:is_house_bill,true',
        'original_released' => 'boolean',
        'documentation_complete' => 'boolean',
    ];

    public function mount($shipmentId = null, $preselectedLoadingPortId = null, $preselectedDischargePortId = null)
    {
        \Log::info('=== MOUNT DEBUG ===');
        \Log::info('shipmentId parameter: ' . ($shipmentId ?: 'NULL'));
        \Log::info('URL parameters: ', request()->all());
        // Cargar datos del formulario primero
        $this->loadFormData();
        
        // Si viene con shipment preseleccionado desde parámetros del componente
        if ($shipmentId) {
            $this->shipment_id = $shipmentId;
            $this->setShipmentDefaults();
            $this->loadMasterBills();
        } elseif (request()->has('shipment_id')) {
            // También revisar si viene como query parameter
            $this->shipment_id = request()->get('shipment_id');
            $this->setShipmentDefaults();
        }
        
        // Si vienen puertos preseleccionados, aplicarlos
        if ($preselectedLoadingPortId) {
            $this->loading_port_id = $preselectedLoadingPortId;
        }
        
        if ($preselectedDischargePortId) {
            $this->discharge_port_id = $preselectedDischargePortId;
        }
        
        // Inicializar valores por defecto
        $this->initializeDefaults();
            \Log::info('shipment_id final en mount: ' . ($this->shipment_id ?: 'NULL'));
    }

    public function render()
    {
        return view('livewire.bill-of-lading-create-form');
    }

    private function loadFormData()
    {
        $company = $this->getUserCompany();

        $this->clients = Client::where('created_by_company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get();

        $this->availableShipments = Shipment::whereHas('voyage', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->with(['voyage'])
        ->orderBy('shipment_number')
        ->get();

        // --- PUERTOS (activos) - FILTRADOS por Argentina y Paraguay
        $argentina = \App\Models\Country::where('alpha2_code', 'AR')->first();
        $paraguay = \App\Models\Country::where('alpha2_code', 'PY')->first();
        $countryIds = collect([$argentina?->id, $paraguay?->id])->filter()->values();

        $portsBase = Port::where('active', true)
            ->whereIn('country_id', $countryIds)
            ->select('id', 'name', 'code', 'city', 'country_id')
            ->orderBy('name')
            ->get();

        // Reutilizar para todos los selects de puertos
        $this->loadingPorts = $portsBase;
        $this->dischargePorts = $portsBase;
        $this->transshipmentPorts = $portsBase;
        $this->finalDestinationPorts = $portsBase;

        // Hacer países disponibles para la vista
        $this->countries = collect([$argentina, $paraguay])->filter();

        $this->customsOffices = CustomOffice::where('active', true)
            ->orderBy('name')
            ->get();

        $this->cargoTypes = CargoType::where('active', true)
            ->orderBy('name')
            ->get();

        $this->packagingTypes = PackagingType::where('active', true)
            ->orderBy('name')
            ->get();

        $this->countries = \App\Models\Country::where('active', true)
            ->orderBy('name')
            ->get();
    }

    private function initializeDefaults()
    {
        $this->bill_date = now()->format('Y-m-d');
        $this->loading_date = now()->format('Y-m-d');
        $this->origin_location = null;
        $this->origin_country_code = null;
        $this->origin_loading_date = null;
        $this->destination_country_code = null;
        $this->discharge_customs_code = null;
        $this->operational_discharge_code = null;
        $this->generateBillNumber();
    }

    private function setShipmentDefaults()
    {
        if ($this->shipment_id) {
            $shipment = Shipment::with('voyage')->find($this->shipment_id);
            if ($shipment && $shipment->voyage) {
                $this->loading_port_id = $shipment->voyage->origin_port_id;
                $this->discharge_port_id = $shipment->voyage->destination_port_id;
            }
        }
    }

    private function generateBillNumber()
    {
        $company = $this->getUserCompany();
        $date = now()->format('Ymd');
        $sequence = BillOfLading::whereHas('shipment.voyage', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->whereDate('created_at', now()->toDateString())->count() + 1;
        
        $this->bill_number = "BL-{$date}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function updatedShipmentId()
    {
        $this->setShipmentDefaults();
        $this->generateBillNumber();
        $this->resetErrorBag('shipment_id');
        $this->preventNavigation = true;
        $this->loadMasterBills(); // Cargar BL maestros al cambiar shipment_id
    }

    public function updatedContainsDangerousGoods()
    {
        if (!$this->contains_dangerous_goods) {
            $this->un_number = '';
            $this->imdg_class = '';
        }
        $this->resetErrorBag(['un_number', 'imdg_class']);
        $this->preventNavigation = true;
    }

    // Nuevo método para cargar BL maestros
    public function loadMasterBills() 
    {
        if ($this->shipment_id) {
            // Debug paso a paso
            \Log::info('=== LOAD MASTER BILLS DEBUG ===');
            \Log::info('Shipment ID buscado: ' . $this->shipment_id);
            
            // Consulta paso a paso
            $query = BillOfLading::where('shipment_id', $this->shipment_id);
            \Log::info('Paso 1 - BLs del shipment: ' . $query->count());
            
            $query = $query->where('is_master_bill', true);
            \Log::info('Paso 2 - BLs maestros: ' . $query->count());
            
            $this->availableMasterBills = $query->select('id', 'bill_number', 'cargo_description')->get();
            
            \Log::info('Resultado final: ' . $this->availableMasterBills->count());
            \Log::info('Datos encontrados: ' . json_encode($this->availableMasterBills->toArray()));
        } else {
            \Log::info('=== LOAD MASTER BILLS DEBUG ===');
            \Log::info('No hay shipment_id definido');
        }
    }

    // Actualizar cuando cambia is_house_bill
    public function updatedIsHouseBill()
{
    \Log::info('=== UPDATED IS HOUSE BILL INICIO ===');
    \Log::info('is_house_bill: ' . ($this->is_house_bill ? 'true' : 'false'));
    \Log::info('shipment_id EN updatedIsHouseBill: ' . ($this->shipment_id ?: 'NULL'));
    
    if ($this->is_house_bill) {
        \Log::info('Llamando a loadMasterBills desde updatedIsHouseBill');
        $this->loadMasterBills();
    }
    
    if (!$this->is_house_bill) {
        $this->master_bill_number = '';
    }
    $this->resetErrorBag('master_bill_number');
    $this->preventNavigation = true;
    
    \Log::info('=== UPDATED IS HOUSE BILL FINAL ===');
}

    // Método para detectar cualquier cambio en el formulario
    public function updated($propertyName)
    {
        $this->preventNavigation = true;
    }

    public function submit()
    {
         
        $this->loading = true;

        try {
            $this->validate();

            DB::beginTransaction();

            $data = [
                'shipment_id' => $this->shipment_id,
                'shipper_id' => $this->shipper_id,
                'consignee_id' => $this->consignee_id,
                'notify_party_id' => $this->notify_party_id ?: null,
                'cargo_owner_id' => $this->cargo_owner_id ?: null,
                'loading_port_id' => $this->loading_port_id,
                'discharge_port_id' => $this->discharge_port_id,
                'transshipment_port_id' => $this->transshipment_port_id ?: null,
                'final_destination_port_id' => $this->final_destination_port_id ?: null,
                'loading_customs_id' => $this->loading_customs_id ?: null,
                'discharge_customs_id' => $this->discharge_customs_id ?: null,
                'primary_cargo_type_id' => $this->primary_cargo_type_id,
                'primary_packaging_type_id' => $this->primary_packaging_type_id,
                'bill_number' => $this->bill_number,
                'bill_date' => $this->bill_date,
                'loading_date' => $this->loading_date,
                'origin_location' => $this->origin_location ?: null,
                'origin_country_code' => $this->origin_country_code ?: null,
                'origin_loading_date' => $this->origin_loading_date ?: null,
                'discharge_date' => $this->discharge_date ?: null,
                // Campos AFIP origen/destino
                'origin_location' => $this->origin_location ?: null,
                'origin_country_code' => $this->origin_country_code ?: null,
                'origin_loading_date' => $this->origin_loading_date ?: null,
                'destination_country_code' => $this->destination_country_code ?: null,
                'discharge_customs_code' => $this->discharge_customs_code ?: null,
                'operational_discharge_code' => $this->operational_discharge_code ?: null,
                'destination_country_code' => $this->destination_country_code ?: null,
                'discharge_customs_code' => $this->discharge_customs_code ?: null,
                'operational_discharge_code' => $this->operational_discharge_code ?: null,
                'discharge_date' => $this->discharge_date ?: null,
                'freight_terms' => $this->freight_terms,
                'payment_terms' => $this->payment_terms,
                'currency_code' => $this->currency_code,
                'incoterms' => $this->incoterms ?: null,
                'cargo_description' => $this->cargo_description,
                'cargo_marks' => $this->cargo_marks ?: null,
                'commodity_code' => $this->commodity_code ?: null,
                'total_packages' => $this->total_packages,
                'gross_weight_kg' => $this->gross_weight_kg,
                'net_weight_kg' => $this->net_weight_kg ?: null,
                'volume_m3' => $this->volume_m3 ?: null,
                'measurement_unit' => $this->measurement_unit ?: 'KG',
                'contains_dangerous_goods' => $this->contains_dangerous_goods,
                'un_number' => $this->contains_dangerous_goods ? $this->un_number : null,
                'imdg_class' => $this->contains_dangerous_goods ? $this->imdg_class : null,
                'requires_refrigeration' => $this->requires_refrigeration,
                'requires_inspection' => $this->requires_inspection,
                'is_consolidated' => $this->is_consolidated,
                'is_master_bill' => $this->is_master_bill,
                'is_house_bill' => $this->is_house_bill,
                'master_bill_number' => $this->is_house_bill ? $this->master_bill_number : null,
                'original_released' => $this->original_released,
                'documentation_complete' => $this->documentation_complete,
                'status' => 'draft',
                'created_by_user_id' => auth()->id(),
                'last_updated_by_user_id' => auth()->id(),
            ];


            $billOfLading = BillOfLading::create($data);

            DB::commit();

            session()->flash('message', 'Conocimiento de embarque creado exitosamente.');
            
            return redirect()->route('company.bills-of-lading.show', $billOfLading);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->loading = false;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->loading = false;
            
            session()->flash('error', 'Error al crear el conocimiento: ' . $e->getMessage());
                    dd('Error en submit: ' . $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }




    }