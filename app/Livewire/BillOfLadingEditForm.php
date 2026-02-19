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
use App\Models\Country;
use App\Models\AfipCustomsOffice;
use App\Models\AfipOperativeLocation;
use App\Traits\UserHelper;
use Illuminate\Validation\Rule;
use Carbon\Carbon;


class BillOfLadingEditForm extends Component
{
    use UserHelper;

    public $billOfLading;
    public $loading = false;

    // === DATOS BÁSICOS ===
    public $shipment_id = '';
    public $bill_number = '';
    public $bill_date = '';
    public $loading_date = '';
    public $discharge_date = '';
    // === CAMPOS AFIP ORIGEN/DESTINO ===
    public $origin_location = '';
    public $origin_country_code = '';
    public $origin_loading_date = '';
    public $destination_country_code = '';
    public $discharge_customs_code = '';
    public $operational_discharge_code = '';

    // === CÓDIGOS AFIP CASCADA ===
    public $origin_customs_code = '';
    public $origin_operative_code = '';

    // Colecciones para selectores AFIP cascada
    public $afipCustomsOfficesOrigin = [];
    public $afipCustomsOfficesDischarge = [];
    public $afipLocationsOrigin = [];
    public $afipLocationsDischarge = [];
    public $freight_terms = 'prepaid';
    public $payment_terms = 'cash';
    public $currency_code = 'USD';
    public $incoterms = '';
    // === CAMPOS WEBSERVICES ===
    public $permiso_embarque = ''; // TRP - Permiso de embarque (obligatorio AFIP)
    public $id_decla = ''; // Identificador Destinación Aduanera AFIP

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

    // === DIRECCIONES ESPECÍFICAS (igual que en CREATE) ===
    // Shipper
    public $shipper_use_specific = false;
    public $shipper_specific_address_1 = '';
    public $shipper_specific_address_2 = '';
    public $shipper_specific_city = '';
    public $shipper_specific_state = '';
    public $shipper_specific_postal_code = '';
    public $shipper_specific_country = '';
    public $shipper_specific_phone = '';
    public $shipper_specific_email = '';

    // Consignee
    public $consignee_use_specific = false;
    public $consignee_specific_address_1 = '';
    public $consignee_specific_address_2 = '';
    public $consignee_specific_city = '';
    public $consignee_specific_state = '';
    public $consignee_specific_postal_code = '';
    public $consignee_specific_country = '';
    public $consignee_specific_phone = '';
    public $consignee_specific_email = '';

    // Notify Party
    public $notify_use_specific = false;
    public $notify_specific_address_1 = '';
    public $notify_specific_address_2 = '';
    public $notify_specific_city = '';
    public $notify_specific_state = '';
    public $notify_specific_postal_code = '';
    public $notify_specific_country = '';
    public $notify_specific_phone = '';
    public $notify_specific_email = '';

    // === CREACIÓN RÁPIDA DE CLIENTES ===
    public $showCreateClientModal = false;
    public $clientType = '';
    public $new_legal_name = '';
    public $new_tax_id = '';
    public $new_country_id = '';
    public $new_email = '';
    public $new_phone = '';
    public $new_address = '';
    public $new_city = '';

    // === CONSOLIDACIÓN ===
    public $is_consolidated = false;
    public $is_master_bill = false;
    public $is_house_bill = false;
    public $master_bill_number = '';

    // === TRANSPORTE PROPIO ===
    public $is_own_transport = false;

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

    /**
     * Reglas de validación (copiadas del componente original)
     */
   /* protected $rules = [
        'shipment_id' => 'required|exists:shipments,id',
        'bill_number' => 'required|string|max:100',
        'bill_date' => 'required|date',
        'loading_date' => 'required|date',
        'discharge_date' => 'nullable|date|after_or_equal:loading_date',
        // Campos AFIP origen/destino
        'origin_location' => ['nullable','string','max:50'],
        'origin_country_code' => ['nullable','string','size:3'],
        'origin_loading_date' => ['nullable','date'],
        'destination_country_code' => ['nullable','string','size:3'],
        'discharge_customs_code' => ['nullable','string','max:3'],
        'operational_discharge_code' => ['nullable','string','max:5'],
        'freight_terms' => 'required|in:prepaid,collect,third_party',
        'payment_terms' => 'required|in:cash,credit,letter_of_credit,other',
        'currency_code' => 'required|in:USD,ARS,EUR,BRL',
        'incoterms' => 'nullable|string|max:10',
        'shipper_id' => 'required|exists:clients,id,status,active',
        'consignee_id' => 'required|exists:clients,id,status,active',
        'notify_party_id' => 'nullable|exists:clients,id,status,active',
        'cargo_owner_id' => 'nullable|exists:clients,id,status,active',
        'loading_port_id' => 'required|exists:ports,id,active,1',
        'discharge_port_id' => 'required|exists:ports,id,active,1',
        'transshipment_port_id' => 'nullable|exists:ports,id,active,1',
        'final_destination_port_id' => 'nullable|exists:ports,id,active,1',
        'loading_customs_id' => 'nullable|exists:custom_offices,id,active,1',
        'discharge_customs_id' => 'nullable|exists:custom_offices,id,active,1',
        'primary_cargo_type_id' => 'required|exists:cargo_types,id,active,1',
        'primary_packaging_type_id' => 'required|exists:packaging_types,id,active,1',
        'cargo_description' => 'required|string|max:3000',
        'cargo_marks' => 'nullable|string|max:1000',
        'commodity_code' => 'nullable|string|max:50',
        'total_packages' => 'required|integer|min:1',
        'gross_weight_kg' => 'required|numeric|min:0.01',
        'net_weight_kg' => 'nullable|numeric|min:0',
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
    */
    protected $casts = [
        'contains_dangerous_goods' => 'boolean',
        'requires_refrigeration' => 'boolean',
        'is_perishable' => 'boolean',
        'is_priority' => 'boolean',
        'requires_inspection' => 'boolean',
        'is_consolidated' => 'boolean',
        'is_master_bill' => 'boolean',
        'is_house_bill' => 'boolean',
        'original_released' => 'boolean',
        'documentation_complete' => 'boolean',
    ];

    public function rules()
{
    return [
        'shipment_id' => ['required', Rule::exists('shipments', 'id')],

        'bill_number' => ['bail','required','string','max:100'],
        'bill_date' => ['required','date_format:Y-m-d'],
        'loading_date' => ['required','date_format:Y-m-d'],
        'discharge_date' => ['nullable','date_format:Y-m-d','after_or_equal:loading_date'],

        'freight_terms' => ['required','in:prepaid,collect,third_party'],
        'payment_terms' => ['required','in:cash,credit,letter_of_credit,other'],
        'currency_code' => ['required','in:USD,ARS,EUR,BRL'],
        'incoterms' => ['nullable','string','max:10'],

        // clients activos
        'shipper_id' => ['required', Rule::exists('clients','id')->where(fn($q)=>$q->where('status','active'))],
        'consignee_id' => ['required', Rule::exists('clients','id')->where(fn($q)=>$q->where('status','active'))],
        'notify_party_id' => ['nullable', Rule::exists('clients','id')->where(fn($q)=>$q->where('status','active'))],
        'cargo_owner_id' => ['nullable', Rule::exists('clients','id')->where(fn($q)=>$q->where('status','active'))],

        // puertos/aduanas activos
        'loading_port_id' => ['required', Rule::exists('ports','id')->where(fn($q)=>$q->where('active',1))],
        'discharge_port_id' => ['required', Rule::exists('ports','id')->where(fn($q)=>$q->where('active',1))],
        'transshipment_port_id' => ['nullable', Rule::exists('ports','id')->where(fn($q)=>$q->where('active',1))],
        'final_destination_port_id' => ['nullable', Rule::exists('ports','id')->where(fn($q)=>$q->where('active',1))],
        'loading_customs_id' => ['nullable', Rule::exists('custom_offices','id')->where(fn($q)=>$q->where('active',1))],
        'discharge_customs_id' => ['nullable', Rule::exists('custom_offices','id')->where(fn($q)=>$q->where('active',1))],

        // tipos activos
        'primary_cargo_type_id' => ['required', Rule::exists('cargo_types','id')->where(fn($q)=>$q->where('active',1))],
        'primary_packaging_type_id' => ['required', Rule::exists('packaging_types','id')->where(fn($q)=>$q->where('active',1))],

        // detalle
        'cargo_description' => ['required','string','max:3000'],
        'cargo_marks' => ['nullable','string','max:1000'],
        'commodity_code' => ['nullable','string','max:50'],
        'total_packages' => ['required','integer','min:1'],
        'gross_weight_kg' => ['required','numeric','min:0.01'],
        'net_weight_kg' => ['nullable','numeric','min:0'],
        'volume_m3' => ['nullable','numeric','min:0'],
        'measurement_unit' => ['nullable','string','max:20'],

        // flags
        'contains_dangerous_goods' => ['boolean'],
        'un_number' => ['nullable','string','max:10','required_if:contains_dangerous_goods,true'],
        'imdg_class' => ['nullable','string','max:10','required_if:contains_dangerous_goods,true'],
        'requires_refrigeration' => ['boolean'],
        'is_perishable' => ['boolean'],
        'is_priority' => ['boolean'],
        'requires_inspection' => ['boolean'],
        'is_consolidated' => ['boolean'],
        'is_master_bill' => ['boolean'],
        'is_house_bill' => ['boolean'],
        'master_bill_number' => ['nullable','string','max:50','required_if:is_house_bill,true'],
        'original_released' => ['boolean'],
        'documentation_complete' => ['boolean'],
    ];
}

    public function mount($billOfLading)
    {
        \Log::info('MOUNT START');
        
        $this->billOfLading = $billOfLading;
        \Log::info('MOUNT - billOfLading set');
        
        $this->loadBillOfLadingData();
        \Log::info('MOUNT - loadBillOfLadingData done');

        $this->loadFormData();
        \Log::info('MOUNT - loadFormData done');
        
        // Inicializar selectores AFIP con datos existentes
        $this->initializeAfipSelectors();
        \Log::info('MOUNT - initializeAfipSelectors done');

        $this->bill_date = optional($this->billOfLading->bill_date)?->format('Y-m-d') ?? '';
        $this->loading_date = optional($this->billOfLading->loading_date)?->format('Y-m-d') ?? '';
        $this->discharge_date = optional($this->billOfLading->discharge_date)?->format('Y-m-d') ?? '';
        $this->id_decla = $this->billOfLading->id_decla ?? '';

        // Auto-detectar transporte propio si shipper == consignee
        if ($this->shipper_id && $this->consignee_id && $this->shipper_id == $this->consignee_id) {
            $this->is_own_transport = true;
        }

    }
    /**
     * Cargar colecciones para selectores (copiado del create)
     */
    public function loadFormData()
{
    // --- CLIENTES (activos) - traer todos, es tabla manejable
    $this->clients = Client::where('status', 'active')
        ->select('id', 'legal_name')
        ->orderBy('legal_name')
        ->get();

    // --- VIAJES (solo de la compañía del usuario) - traer todos de la empresa
    $company = $this->getUserCompany();
    $this->availableShipments = Shipment::whereHas('voyage', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->select('id', 'shipment_number', 'voyage_id')
        ->orderBy('shipment_number')
        ->get();

    // --- PUERTOS (activos) - SOLO AQUÍ aplicar límite por performance + incluir seleccionados
    $LIMIT = 100; // Límite solo para puertos
    
    $argentina = Country::where('alpha2_code', 'AR')->first();
    $paraguay = Country::where('alpha2_code', 'PY')->first();
    $countryIds = collect([$argentina?->id, $paraguay?->id])->filter()->values();

    // Puertos base (limitados a AR/PY)
    $portsBase = Port::where('active', true)
        ->whereIn('country_id', $countryIds)
        ->select('id', 'name', 'code', 'city', 'country_id')
        ->orderBy('name')
        ->limit($LIMIT)
        ->get();

    // ASEGURAR que los puertos seleccionados en el BL estén incluidos
    $selectedPortIds = collect([
        $this->loading_port_id,
        $this->discharge_port_id,
        $this->transshipment_port_id,
        $this->final_destination_port_id,
    ])->filter()->unique()->values();

    if ($selectedPortIds->isNotEmpty()) {
        $missing = $selectedPortIds->diff($portsBase->pluck('id'));
        if ($missing->isNotEmpty()) {
            $extra = Port::whereIn('id', $missing)
                ->select('id','name','code','city','country_id')
                ->get();
            $portsBase = $portsBase->concat($extra)
                ->sortBy('name')
                ->values();
        }
    }

    // Reutilizar para todos los selects de puertos
    $this->loadingPorts = $portsBase;
    $this->dischargePorts = $portsBase;
    $this->transshipmentPorts = $portsBase;
    $this->finalDestinationPorts = $portsBase;

    // --- ADUANAS (activas) - traer todas, tabla manejable
    $this->customsOffices = CustomOffice::where('active', true)
        ->select('id','name')
        ->orderBy('name')
        ->get();

    // --- TIPOS - traer todos, tablas pequeñas
    $this->cargoTypes = CargoType::where('active', true)
        ->select('id','name')
        ->orderBy('name')
        ->get();

    $this->packagingTypes = PackagingType::where('active', true)
        ->select('id','name')
        ->orderBy('name')
        ->get();

    // --- PAÍSES - traer todos, tabla manejable
    $this->countries = Country::where('active', true)
        ->select('id','name')
        ->orderBy('name')
        ->get();
}


    /**
     * Cargar datos del BL existente en las propiedades del componente
     */
    private function loadBillOfLadingData()
    {
        $bl = $this->billOfLading;

        // Datos básicos
        $this->shipment_id = $bl->shipment_id;
        $this->bill_number = $bl->bill_number;
        $this->bill_date = $bl->bill_date ? $bl->bill_date->format('Y-m-d') : '';
        $this->loading_date = $bl->loading_date ? $bl->loading_date->format('Y-m-d') : '';
        $this->discharge_date = $bl->discharge_date ? $bl->discharge_date->format('Y-m-d') : '';
        // Campos AFIP origen/destino
        $this->origin_location = $bl->origin_location ?? '';
        $this->origin_country_code = $bl->origin_country_code ?? '';
        $this->origin_loading_date = $bl->origin_loading_date ? $bl->origin_loading_date->format('Y-m-d\TH:i') : '';
        $this->origin_customs_code = $bl->origin_customs_code ?? '';
        $this->origin_operative_code = $bl->origin_operative_code ?? '';
        $this->destination_country_code = $bl->destination_country_code ?? '';
        $this->discharge_customs_code = $bl->discharge_customs_code ?? '';
        $this->operational_discharge_code = $bl->operational_discharge_code ?? '';
        $this->freight_terms = $bl->freight_terms ?? 'prepaid';
        $this->payment_terms = $bl->payment_terms ?? 'cash';
        $this->currency_code = $bl->currency_code ?? 'USD';
        $this->incoterms = $bl->incoterms ?? '';
        $this->permiso_embarque = $bl->permiso_embarque ?? '';
        $this->id_decla = $this->billOfLading->id_decla ?? '';

        // Partes
        $this->shipper_id = $bl->shipper_id;
        $this->consignee_id = $bl->consignee_id;
        $this->notify_party_id = $bl->notify_party_id;
        $this->cargo_owner_id = $bl->cargo_owner_id;

        // Puertos
        $this->loading_port_id = $bl->loading_port_id ?? '';
        $this->discharge_port_id = $bl->discharge_port_id ?? '';
        $this->transshipment_port_id = $bl->transshipment_port_id ?? '';
        $this->final_destination_port_id = $bl->final_destination_port_id ?? '';
        $this->loading_customs_id = $bl->loading_customs_id ?? '';
        $this->discharge_customs_id = $bl->discharge_customs_id ?? '';

        // Mercancías
        $this->primary_cargo_type_id = $bl->primary_cargo_type_id ?? '';
        $this->primary_packaging_type_id = $bl->primary_packaging_type_id ?? '';
        $this->cargo_description = $bl->cargo_description ?? '';
        $this->cargo_marks = $bl->cargo_marks ?? '';
        $this->commodity_code = $bl->commodity_code ?? '';

        // Cantidades
        $this->total_packages = $bl->total_packages ?? 1;
        $this->gross_weight_kg = $bl->gross_weight_kg ?? 0;
        $this->net_weight_kg = $bl->net_weight_kg ?? 0;
        $this->volume_m3 = $bl->volume_m3 ?? 0;
        $this->measurement_unit = $bl->measurement_unit ?? 'KG';

        // Características especiales
        $this->contains_dangerous_goods = $bl->contains_dangerous_goods ?? false;
        $this->un_number = $bl->un_number ?? '';
        $this->imdg_class = $bl->imdg_class ?? '';
        $this->requires_refrigeration = $bl->requires_refrigeration ?? false;
        $this->is_perishable = $bl->is_perishable ?? false;
        $this->is_priority = ($bl->priority_level ?? 'normal') !== 'normal';

        // Consolidación
        $this->is_consolidated = $bl->is_consolidated ?? false;
        $this->is_master_bill = $bl->is_master_bill ?? false;
        $this->is_house_bill = $bl->is_house_bill ?? false;
        $this->master_bill_number = $bl->master_bill_number ?? '';

        // Documentación
        $this->original_released = $bl->original_released ?? false;
        $this->documentation_complete = $bl->documentation_complete ?? false;
        $this->requires_inspection = $bl->requires_inspection ?? false;

        // Cargar direcciones específicas si existen
        $this->loadSpecificAddresses();
    }

    /**
     * Cargar direcciones específicas existentes
     */
    private function loadSpecificAddresses()
    {
        $specificContacts = $this->billOfLading->specificContacts()->get();

        foreach ($specificContacts as $contact) {
            switch ($contact->role) {
                case 'shipper':
                    $this->loadSpecificContactData($contact, 'shipper');
                    break;
                case 'consignee':
                    $this->loadSpecificContactData($contact, 'consignee');
                    break;
                case 'notify':
                    $this->loadSpecificContactData($contact, 'notify');
                    break;
            }
        }
    }

    /**
     * Cargar datos de contacto específico
     */
    private function loadSpecificContactData($contact, $prefix)
    {
        $this->{$prefix . '_use_specific'} = $contact->use_specific_data;
        $this->{$prefix . '_specific_address_1'} = $contact->specific_address_line_1 ?? '';
        $this->{$prefix . '_specific_address_2'} = $contact->specific_address_line_2 ?? '';
        $this->{$prefix . '_specific_city'} = $contact->specific_city ?? '';
        $this->{$prefix . '_specific_state'} = $contact->specific_state_province ?? '';
        $this->{$prefix . '_specific_postal_code'} = $contact->specific_postal_code ?? '';
        $this->{$prefix . '_specific_country'} = $contact->specific_country ?? '';
        $this->{$prefix . '_specific_phone'} = $contact->specific_phone ?? '';
        $this->{$prefix . '_specific_email'} = $contact->specific_email ?? '';
    }

    /**
     * Métodos para modal de creación de clientes (copiados del create)
     */
    public function openCreateClientModal($type)
    {
        $this->clientType = $type;
        $this->showCreateClientModal = true;
        $this->resetClientModalData();
    }

    public function cancelCreateClient()
    {
        $this->showCreateClientModal = false;
        $this->resetClientModalData();
    }

    private function resetClientModalData()
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
        \Log::info('createClient llamado', [
            'new_legal_name' => $this->new_legal_name,
            'new_tax_id' => $this->new_tax_id,
            'new_country_id' => $this->new_country_id,
        ]);

        $this->validate([
            'new_legal_name' => 'required|string|min:3|max:255',
            'new_tax_id' => 'required|string|max:15',
            'new_country_id' => 'required|exists:countries,id',
            'new_email' => 'nullable|email|max:255',
            'new_phone' => 'nullable|string|max:20',
            'new_address' => 'nullable|string|max:255',
            'new_city' => 'nullable|string|max:100',
        ]);

        \Log::info('createClient - validación pasó');

        try {
            DB::beginTransaction();

            $company = $this->getUserCompany();
            \Log::info('createClient - company', ['company_id' => $company->id]);

            // Obtener document_type_id primario para el país seleccionado
            $documentTypeId = \App\Models\DocumentType::where('country_id', $this->new_country_id)
                ->where('active', true)
                ->where('is_primary', true)
                ->first()?->id;

            // Si no hay primario, buscar cualquier tipo de documento activo del país
            if (!$documentTypeId) {
                $documentTypeId = \App\Models\DocumentType::where('country_id', $this->new_country_id)
                    ->where('active', true)
                    ->first()?->id ?? 1; // Fallback a ID 1 si no hay ninguno
            }

            $client = Client::create([
                'legal_name' => $this->new_legal_name,
                'tax_id' => $this->new_tax_id,
                'country_id' => $this->new_country_id,
                'document_type_id' => $documentTypeId,
                'status' => 'active',
                'client_type' => 'both',
                'created_by_company_id' => $company->id,
                'created_by_user_id' => auth()->id(),
            ]);

            \Log::info('createClient - cliente creado', ['client_id' => $client->id]);

            // Crear datos de contacto si se proporcionaron
            if ($this->new_email || $this->new_phone || $this->new_address) {
                $client->contactData()->create([
                    'email' => $this->new_email,
                    'phone' => $this->new_phone,
                    'address_line_1' => $this->new_address,
                    'city' => $this->new_city,
                    'country_id' => $this->new_country_id,
                    'is_primary' => true,
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            // Asignar al campo correspondiente
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

            // Recargar clientes
            $this->loadFormData();

            DB::commit();

            \Log::info('createClient - éxito total');

            session()->flash('message', 'Cliente creado exitosamente.');
            $this->showCreateClientModal = false;
            $this->resetClientModalData();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('createClient - error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Error al crear cliente: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar direcciones específicas
     */
    private function updateSpecificContacts()
    {
        // Eliminar contactos específicos existentes
        $this->billOfLading->specificContacts()->delete();

        $contactsToCreate = [];

        // Shipper
        if ($this->shipper_use_specific && $this->shipper_id) {
            $shipperContactData = $this->getClientPrimaryContactData($this->shipper_id);
            if ($shipperContactData) {
                $contactsToCreate[] = [
                    'client_contact_data_id' => $shipperContactData->id,
                    'role' => 'shipper',
                    'use_specific_data' => true,
                    'specific_address_line_1' => $this->shipper_specific_address_1,
                    'specific_address_line_2' => $this->shipper_specific_address_2,
                    'specific_city' => $this->shipper_specific_city,
                    'specific_state_province' => $this->shipper_specific_state,
                    'specific_postal_code' => $this->shipper_specific_postal_code,
                    'specific_country' => $this->shipper_specific_country,
                    'specific_phone' => $this->shipper_specific_phone,
                    'specific_email' => $this->shipper_specific_email,
                    'created_by_user_id' => auth()->id(),
                ];
            }
        }

        // Consignee
        if ($this->consignee_use_specific && $this->consignee_id) {
            $consigneeContactData = $this->getClientPrimaryContactData($this->consignee_id);
            if ($consigneeContactData) {
                $contactsToCreate[] = [
                    'client_contact_data_id' => $consigneeContactData->id,
                    'role' => 'consignee',
                    'use_specific_data' => true,
                    'specific_address_line_1' => $this->consignee_specific_address_1,
                    'specific_address_line_2' => $this->consignee_specific_address_2,
                    'specific_city' => $this->consignee_specific_city,
                    'specific_state_province' => $this->consignee_specific_state,
                    'specific_postal_code' => $this->consignee_specific_postal_code,
                    'specific_country' => $this->consignee_specific_country,
                    'specific_phone' => $this->consignee_specific_phone,
                    'specific_email' => $this->consignee_specific_email,
                    'created_by_user_id' => auth()->id(),
                ];
            }
        }

        // Notify Party
        if ($this->notify_use_specific && $this->notify_party_id) {
            $notifyContactData = $this->getClientPrimaryContactData($this->notify_party_id);
            if ($notifyContactData) {
                $contactsToCreate[] = [
                    'client_contact_data_id' => $notifyContactData->id,
                    'role' => 'notify_party',
                    'use_specific_data' => true,
                    'specific_address_line_1' => $this->notify_specific_address_1,
                    'specific_address_line_2' => $this->notify_specific_address_2,
                    'specific_city' => $this->notify_specific_city,
                    'specific_state_province' => $this->notify_specific_state,
                    'specific_postal_code' => $this->notify_specific_postal_code,
                    'specific_country' => $this->notify_specific_country,
                    'specific_phone' => $this->notify_specific_phone,
                    'specific_email' => $this->notify_specific_email,
                    'created_by_user_id' => auth()->id(),
                ];
            }
        }

        // Crear los contactos específicos
        foreach ($contactsToCreate as $contactData) {
            $this->billOfLading->specificContacts()->create($contactData);
        }
    }

    /**
     * Obtener el ContactData principal de un cliente
     */
    private function getClientPrimaryContactData($clientId)
    {
        return \App\Models\Client::find($clientId)
            ?->contactData()
            ->where('is_primary', true)
            ->first();
    }

    // Si se marca transporte propio, copiar shipper a consignee
    public function updatedIsOwnTransport($value)
    {
        if ($value && $this->shipper_id) {
            $this->consignee_id = $this->shipper_id;
        }
    }

    /**
     * Actualizar conocimiento
     */
    public function submit()
    {
       
        $this->loading = true;
         \Log::info('SUBMIT START');
        
        $this->loading = true;
        \Log::info('SUBMIT - loading set to true');
            
        try {
            // Normalizar fechas a Y-m-d para que el validador no “adivine”
            $this->bill_date = $this->normalizeDate($this->bill_date);
            $this->loading_date = $this->normalizeDate($this->loading_date);
            $this->discharge_date = $this->normalizeDate($this->discharge_date);

            \Log::info('SUBMIT - about to validate');
           // $this->validate();
            \Log::info('SUBMIT - validation passed');

            \Log::info('SUBMIT - starting transaction');
            DB::beginTransaction();
            \Log::info('SUBMIT - transaction started');           

            $data = [
                'shipment_id' => $this->shipment_id,
                'bill_number' => $this->bill_number,
                'bill_date' => $this->bill_date ? \Carbon\Carbon::parse($this->bill_date) : null,
                'loading_date' => $this->loading_date ? \Carbon\Carbon::parse($this->loading_date) : null,
                'discharge_date' => $this->discharge_date ? \Carbon\Carbon::parse($this->discharge_date) : null,
                // Campos AFIP origen/destino
                'origin_location' => $this->origin_location ?: null,
                'origin_country_code' => $this->origin_country_code ?: null,
                'origin_loading_date' => $this->origin_loading_date ? \Carbon\Carbon::parse($this->origin_loading_date) : null,
                'origin_customs_code' => $this->origin_customs_code ?: null,
                'origin_operative_code' => $this->origin_operative_code ?: null,
                'destination_country_code' => $this->destination_country_code ?: null,
                'discharge_customs_code' => $this->discharge_customs_code ?: null,
                'operational_discharge_code' => $this->operational_discharge_code ?: null,
                'freight_terms' => $this->freight_terms,
                'payment_terms' => $this->payment_terms,
                'currency_code' => $this->currency_code,
                'incoterms' => $this->incoterms ?: null,
                'permiso_embarque' => $this->permiso_embarque ?: null,
                'id_decla' => $this->id_decla,
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
                'cargo_description' => $this->cargo_description,
                'cargo_marks' => $this->cargo_marks ?: null,
                'commodity_code' => $this->commodity_code ?: null,
                'total_packages' => $this->total_packages,
                'gross_weight_kg' => $this->gross_weight_kg,
                'net_weight_kg' => $this->net_weight_kg ?: null,
                'volume_m3' => $this->volume_m3 ?: null,
                'measurement_unit' => $this->measurement_unit,
                'contains_dangerous_goods' => $this->contains_dangerous_goods,
                'un_number' => $this->contains_dangerous_goods ? $this->un_number : null,
                'imdg_class' => $this->contains_dangerous_goods ? $this->imdg_class : null,
                'requires_refrigeration' => $this->requires_refrigeration,
                'priority_level' => $this->is_priority ? 'high' : 'normal',
                'is_consolidated' => $this->is_consolidated,
                'is_master_bill' => $this->is_master_bill,
                'is_house_bill' => $this->is_house_bill,
                'master_bill_number' => $this->is_house_bill ? $this->master_bill_number : null,
                'original_released' => $this->original_released,
                'documentation_complete' => $this->documentation_complete,
                'requires_inspection' => $this->requires_inspection,
                'last_updated_by_user_id' => auth()->id(),
            ];

            $this->billOfLading->update($data);

            // Actualizar direcciones específicas
            $this->updateSpecificContacts();

            DB::commit();

            session()->flash('message', 'Conocimiento de embarque actualizado exitosamente.');
            
            return redirect()->route('company.bills-of-lading.show', $this->billOfLading);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->loading = false;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->loading = false;
            
            session()->flash('error', 'Error al actualizar el conocimiento: ' . $e->getMessage());
        }
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) return null;

        // Aceptamos 3 formatos comunes y devolvemos Y-m-d
        // 1) Y-m-d (nativo HTML date)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        // 2) d/m/Y (muy usado por usuarios)
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        // 3) Intento “last resort” con Carbon::parse, pero encapsulado
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Obtener nombre completo del puerto con país
     */
    private function getPortDisplayName($port)
    {
        $countryCode = $port->country_id == Country::where('alpha2_code', 'AR')->first()?->id ? '🇦🇷' : '🇵🇾';
        return "{$countryCode} {$port->name} {$port->code}";
    }

    // =========================================================================
    // MÉTODOS CASCADA AFIP - ORIGEN
    // =========================================================================

    /**
     * Cuando cambia el puerto de carga, cargar aduanas AFIP vinculadas
     */
    public function updatedLoadingPortId($value)
    {
        // Resetear selecciones dependientes
        $this->origin_customs_code = '';
        $this->origin_operative_code = '';
        $this->afipCustomsOfficesOrigin = [];
        $this->afipLocationsOrigin = [];

        if ($value) {
            $port = Port::with(['afipCustomsOffices', 'country'])->find($value);
            
            if (!$port) {
                return;
            }

            // Detectar si es puerto argentino o extranjero
            $isArgentina = $port->country && $port->country->alpha2_code === 'AR';

            if ($isArgentina) {
                // Puerto argentino: buscar en tabla pivote port_afip_customs
                if ($port->afipCustomsOffices->count() > 0) {
                    $this->afipCustomsOfficesOrigin = $port->afipCustomsOffices
                        ->where('is_active', true)
                        ->map(fn($office) => [
                            'code' => $office->code,
                            'name' => $office->name,
                            'is_default' => $office->pivot->is_default ?? false,
                        ])
                        ->values()
                        ->toArray();

                    $default = collect($this->afipCustomsOfficesOrigin)->firstWhere('is_default', true);
                    if ($default) {
                        $this->origin_customs_code = $default['code'];
                        $this->loadAfipLocationsOrigin();
                    }
                }
            } else {
                // Puerto extranjero: cargar lugares directamente de afip_operative_locations
                $this->afipLocationsOrigin = AfipOperativeLocation::where('country_id', $port->country_id)
                    ->where('is_foreign', true)
                    ->where('is_active', true)
                    ->orderBy('location_code')
                    ->get()
                    ->map(fn($loc) => [
                        'customs_code' => $loc->customs_code,
                        'code' => $loc->location_code,
                        'description' => $loc->description,
                    ])
                    ->toArray();
            }
        }
    }

    /**
     * Cuando cambia la aduana de origen, cargar lugares operativos
     */
    public function updatedOriginCustomsCode($value)
    {
        $this->origin_operative_code = '';
        
        // Solo recargar lugares si es puerto argentino (cascada)
        // Para extranjeros, los lugares ya están cargados en updatedLoadingPortId
        if ($value && $this->loading_port_id) {
            $port = Port::with('country')->find($this->loading_port_id);
            $isArgentina = $port?->country?->alpha2_code === 'AR';
            
            if ($isArgentina) {
                $this->loadAfipLocationsOrigin();
            }
        } else {
            $this->afipLocationsOrigin = [];
        }
    }

    /**
     * Cargar lugares operativos para el código de aduana origen seleccionado
     */
    private function loadAfipLocationsOrigin()
    {
        if (!$this->origin_customs_code) {
            return;
        }

        // Obtener país del puerto de carga
        $port = Port::with('country')->find($this->loading_port_id);
        $countryId = $port?->country_id;

        $this->afipLocationsOrigin = AfipOperativeLocation::where('customs_code', $this->origin_customs_code)
            ->where('is_active', true)
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('location_code')
            ->get()
            ->map(fn($loc) => [
                'customs_code' => $loc->customs_code,
                'code' => $loc->location_code,
                'description' => $loc->description,
            ])
            ->toArray();
    }

    /**
     * Seleccionar lugar operativo extranjero para ORIGEN
     * Setea tanto el código de lugar como el de aduana
     */
    public function selectForeignLocationOrigin($locationCode)
    {
        $this->origin_operative_code = $locationCode;
        
        // Buscar el customs_code correspondiente
        $location = collect($this->afipLocationsOrigin)->firstWhere('code', $locationCode);
        if ($location) {
            $this->origin_customs_code = $location['customs_code'];
        }
    }

    /**
     * Seleccionar lugar operativo extranjero para DESTINO
     * Setea tanto el código de lugar como el de aduana
     */
    public function selectForeignLocationDischarge($locationCode)
    {
        $this->operational_discharge_code = $locationCode;
        
        // Buscar el customs_code correspondiente
        $location = collect($this->afipLocationsDischarge)->firstWhere('code', $locationCode);
        if ($location) {
            $this->discharge_customs_code = $location['customs_code'];
        }
    }

    // =========================================================================
    // MÉTODOS CASCADA AFIP - DESTINO
    // =========================================================================

    /**
     * Cuando cambia el puerto de descarga, cargar aduanas AFIP vinculadas
     */
    public function updatedDischargePortId($value)
    {
        // Resetear selecciones dependientes
        $this->discharge_customs_code = '';
        $this->operational_discharge_code = '';
        $this->afipCustomsOfficesDischarge = [];
        $this->afipLocationsDischarge = [];

        if ($value) {
            $port = Port::with(['afipCustomsOffices', 'country'])->find($value);
            
            if (!$port) {
                return;
            }

            // Detectar si es puerto argentino o extranjero
            $isArgentina = $port->country && $port->country->alpha2_code === 'AR';

            if ($isArgentina) {
                // Puerto argentino: buscar en tabla pivote port_afip_customs
                if ($port->afipCustomsOffices->count() > 0) {
                    $this->afipCustomsOfficesDischarge = $port->afipCustomsOffices
                        ->where('is_active', true)
                        ->map(fn($office) => [
                            'code' => $office->code,
                            'name' => $office->name,
                            'is_default' => $office->pivot->is_default ?? false,
                        ])
                        ->values()
                        ->toArray();

                    $default = collect($this->afipCustomsOfficesDischarge)->firstWhere('is_default', true);
                    if ($default) {
                        $this->discharge_customs_code = $default['code'];
                        $this->loadAfipLocationsDischarge();
                    }
                }
            } else {
                // Puerto extranjero: cargar lugares directamente de afip_operative_locations
                $this->afipLocationsDischarge = AfipOperativeLocation::where('country_id', $port->country_id)
                    ->where('is_foreign', true)
                    ->where('is_active', true)
                    ->orderBy('location_code')
                    ->get()
                    ->map(fn($loc) => [
                        'customs_code' => $loc->customs_code,
                        'code' => $loc->location_code,
                        'description' => $loc->description,
                    ])
                    ->toArray();
            }
        }
    }

    /**
     * Cuando cambia la aduana de descarga, cargar lugares operativos
     */
    public function updatedDischargeCustomsCode($value)
    {
        $this->operational_discharge_code = '';
        
        // Solo recargar lugares si es puerto argentino (cascada)
        // Para extranjeros, los lugares ya están cargados en updatedDischargePortId
        if ($value && $this->discharge_port_id) {
            $port = Port::with('country')->find($this->discharge_port_id);
            $isArgentina = $port?->country?->alpha2_code === 'AR';
            
            if ($isArgentina) {
                $this->loadAfipLocationsDischarge();
            }
        } else {
            $this->afipLocationsDischarge = [];
        }
    }

    /**
     * Cargar lugares operativos para el código de aduana descarga seleccionado
     */
    private function loadAfipLocationsDischarge()
    {
        if (!$this->discharge_customs_code) {
            return;
        }

        // Obtener país del puerto de descarga
        $port = Port::with('country')->find($this->discharge_port_id);
        $countryId = $port?->country_id;

        $this->afipLocationsDischarge = AfipOperativeLocation::where('customs_code', $this->discharge_customs_code)
            ->where('is_active', true)
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('location_code')
            ->get()
            ->map(fn($loc) => [
                'customs_code' => $loc->customs_code,
                'code' => $loc->location_code,
                'description' => $loc->description,
            ])
            ->toArray();
    }

    /**
 * Inicializar selectores AFIP con datos existentes del BL
 */
private function initializeAfipSelectors()
{
    // Cargar para puerto de ORIGEN
    if ($this->loading_port_id) {
        $port = Port::with(['afipCustomsOffices', 'country'])->find($this->loading_port_id);
        
        if ($port) {
            $isArgentina = $port->country && $port->country->alpha2_code === 'AR';
            
            if ($isArgentina) {
                // Puerto argentino: cargar aduanas
                if ($port->afipCustomsOffices->count() > 0) {
                    $this->afipCustomsOfficesOrigin = $port->afipCustomsOffices
                        ->where('is_active', true)
                        ->map(fn($office) => [
                            'code' => $office->code,
                            'name' => $office->name,
                            'is_default' => $office->pivot->is_default ?? false,
                        ])
                        ->values()
                        ->toArray();
                    
                    // Cargar lugares si ya hay aduana seleccionada
                    if ($this->origin_customs_code) {
                        $this->loadAfipLocationsOrigin();
                    }
                }
            } else {
                // Puerto extranjero: cargar lugares directamente
                $this->afipLocationsOrigin = AfipOperativeLocation::where('country_id', $port->country_id)
                    ->where('is_foreign', true)
                    ->where('is_active', true)
                    ->orderBy('location_code')
                    ->get()
                    ->map(fn($loc) => [
                        'customs_code' => $loc->customs_code,
                        'code' => $loc->location_code,
                        'description' => $loc->description,
                    ])
                    ->toArray();
            }
        }
    }
    
    // Cargar para puerto de DESTINO
    if ($this->discharge_port_id) {
        $port = Port::with(['afipCustomsOffices', 'country'])->find($this->discharge_port_id);
        
        if ($port) {
            $isArgentina = $port->country && $port->country->alpha2_code === 'AR';
            
            if ($isArgentina) {
                // Puerto argentino: cargar aduanas
                if ($port->afipCustomsOffices->count() > 0) {
                    $this->afipCustomsOfficesDischarge = $port->afipCustomsOffices
                        ->where('is_active', true)
                        ->map(fn($office) => [
                            'code' => $office->code,
                            'name' => $office->name,
                            'is_default' => $office->pivot->is_default ?? false,
                        ])
                        ->values()
                        ->toArray();
                    
                    // Cargar lugares si ya hay aduana seleccionada
                    if ($this->discharge_customs_code) {
                        $this->loadAfipLocationsDischarge();
                    }
                }
            } else {
                // Puerto extranjero: cargar lugares directamente
                $this->afipLocationsDischarge = AfipOperativeLocation::where('country_id', $port->country_id)
                    ->where('is_foreign', true)
                    ->where('is_active', true)
                    ->orderBy('location_code')
                    ->get()
                    ->map(fn($loc) => [
                        'customs_code' => $loc->customs_code,
                        'code' => $loc->location_code,
                        'description' => $loc->description,
                    ])
                    ->toArray();
            }
        }
    }
}


    public function render()
    {
        return view('livewire.bill-of-lading-edit-form');
    }
}