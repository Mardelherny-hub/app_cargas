<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Http\Controllers\Company\ShipmentItemController;
use App\Services\ClientValidationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentItemCreateForm extends Component
{
    // Props que vienen del controlador
    public $shipment;
    public $billOfLading;
    public $needsToCreateBL;
    public $defaultBLData;
    public $cargoTypes;
    public $packagingTypes;
    public $clients;
    public $ports;
    public $countries;
    public $nextLineNumber;
    public $continueAdding = true;

    // Estado del componente
    public $step = 1; // 1 = Configurar BL (si es necesario), 2 = Agregar Item
    public $showBLSection = false;
    public $blCreated = false;

    // NUEVO: Propiedades para búsqueda y modal de clientes
    public $showClientModal = false;
    public $clientSearchField = ''; // Identifica qué campo disparó el modal (bl_shipper_id, bl_consignee_id, bl_notify_party_id)
    
    // Búsqueda de clientes
    public $shipperSearch = '';
    public $consigneeSearch = '';
    public $notifyPartySearch = '';
    public $filteredShippers = [];
    public $filteredConsignees = [];
    public $filteredNotifyParties = [];

    // NUEVO: Datos del modal de creación rápida
    #[Validate('required|string|max:15')]
    public $modal_tax_id = '';
    
    #[Validate('required|string|min:3|max:255')]
    public $modal_legal_name = '';
    
    #[Validate('required|exists:countries,id')]
    public $modal_country_id = '';
    
    #[Validate('nullable|exists:document_types,id')]
    public $modal_document_type_id = '';
    
    #[Validate('nullable|string|max:255')]
    public $modal_commercial_name = '';
    
    #[Validate('nullable|string|max:500')]
    public $modal_address = '';
    
    #[Validate('nullable|string|max:100')]
    public $modal_phone = '';
    
    #[Validate('nullable|email|max:100')]
    public $modal_email = '';

    // NUEVO: Campos para dirección específica del BL en el modal
    public $modal_use_specific_address = false;
    
    #[Validate('nullable|string|max:500')]
    public $modal_specific_address_1 = '';
    
    #[Validate('nullable|string|max:500')]
    public $modal_specific_address_2 = '';
    
    #[Validate('nullable|string|max:100')]
    public $modal_specific_city = '';
    
    #[Validate('nullable|string|max:100')]
    public $modal_specific_state = '';

    // Lista de document types para el país seleccionado
    public $availableDocumentTypes = [];

    // Datos del Bill of Lading (sección 1)
    #[Validate('required|exists:clients,id,status,active')]
    public $bl_shipper_id = null;

    #[Validate('required|exists:clients,id,status,active|different:bl_shipper_id')]
    public $bl_consignee_id = null;

    #[Validate('nullable|exists:clients,id,status,active')]
    public $bl_notify_party_id = null;

    #[Validate('required|exists:ports,id,active,1')]
    public $bl_loading_port_id = null;

    #[Validate('required|exists:ports,id,active,1|different:bl_loading_port_id')]
    public $bl_discharge_port_id = null;

    #[Validate('required|exists:cargo_types,id,active,1')]
    public $bl_primary_cargo_type_id = null;

    #[Validate('required|exists:packaging_types,id,active,1')]
    public $bl_primary_packaging_type_id = null;

    #[Validate('required|string|max:100')]
    public $bl_bill_number = '';

    #[Validate('required|date')]
    public $bl_bill_date = '';

    #[Validate('required|date')]
    public $bl_loading_date = '';

    #[Validate('required|in:prepaid,collect')]
    public $bl_freight_terms = 'prepaid';

    #[Validate('required|in:cash,credit,advance')]
    public $bl_payment_terms = 'cash';

    #[Validate('required|in:USD,ARS,EUR')]
    public $bl_currency_code = 'USD';

    // Direcciones específicas para el Bill of Lading
    public $bl_shipper_use_specific = false;
    public $bl_shipper_address_1 = '';
    public $bl_shipper_address_2 = '';
    public $bl_shipper_city = '';
    public $bl_shipper_state = '';

    public $bl_consignee_use_specific = false;
    public $bl_consignee_address_1 = '';
    public $bl_consignee_address_2 = '';
    public $bl_consignee_city = '';
    public $bl_consignee_state = '';

    public $bl_notify_use_specific = false;
    public $bl_notify_address_1 = '';
    public $bl_notify_address_2 = '';
    public $bl_notify_city = '';
    public $bl_notify_state = '';

    // Datos del Shipment Item (sección 2)
    #[Validate('required|string|max:100')]
    public $item_reference = '';

    #[Validate('required|string|max:2000')]
    public $item_description = '';

    #[Validate('required|exists:cargo_types,id,active,1')]
    public $cargo_type_id = null;

    #[Validate('required|exists:packaging_types,id,active,1')]
    public $packaging_type_id = null;

    #[Validate('required|integer|min:1')]
    public $package_quantity = 1;

    #[Validate('required|numeric|min:0.01')]
    public $gross_weight_kg = '';

    #[Validate('nullable|numeric|min:0')]
    public $net_weight_kg = '';

    #[Validate('nullable|numeric|min:0')]
    public $volume_m3 = '';

    #[Validate('nullable|numeric|min:0')]
    public $declared_value = '';

    #[Validate('required|string|size:2')]
    public $country_of_origin = '';

    #[Validate('nullable|string|max:20')]
    public $hs_code = '';

    #[Validate('nullable|string|max:20')]
    public $commodity_code = '';

    #[Validate('nullable|string|max:100')]
    public $brand_model = '';

    #[Validate('nullable|string|max:500')]
    public $cargo_marks = '';

    #[Validate('nullable|string|max:500')]
    public $package_description = '';

    #[Validate('nullable|string|max:50')]
    public $lot_number = '';

    public $is_dangerous_goods = false;
    public $is_perishable = false;
    public $requires_refrigeration = false;

    #[Validate('nullable|string|max:500')]
    public $special_instructions = '';

    #[Validate('nullable|date')]
    public $expiry_date = '';

    #[Validate('nullable|string|max:20')]
    public $inspection_type = '';

    // CAMPOS AFIP
    #[Validate('required|string|min:7|max:15')]
    public $tariff_position = '';

    #[Validate('required|in:S,N')]
    public $is_secure_logistics_operator = 'N';

    #[Validate('required|in:S,N')]
    public $is_monitored_transit = 'N';

    #[Validate('required|in:S,N')]
    public $is_renar = 'N';

    #[Validate('required|string|max:70')]
    public $foreign_forwarder_name = '';

    #[Validate('nullable|string|max:35')]
    public $foreign_forwarder_tax_id = '';

    #[Validate('nullable|string|size:3')]
    public $foreign_forwarder_country = '';

    // CAMPOS AFIP ADICIONALES
    #[Validate('nullable|in:H,P')]
    public $container_condition = '';

    #[Validate('nullable|string|max:100')]
    public $package_numbers = '';

    #[Validate('nullable|string|max:1')]
    public $packaging_type_code = '';

    // CAMPOS AFIP - CÓDIGOS ADUANEROS Y DESTINATARIO
    #[Validate('required|string|size:3')]
    public $discharge_customs_code = '';

    #[Validate('required|string|max:5')]
    public $operational_discharge_code = '';

    #[Validate('nullable|string|max:60')]
    public $comments = '';

    #[Validate('nullable|string|max:4')]
    public $consignee_document_type = '';

    #[Validate('nullable|string|max:11')]
    public $consignee_tax_id = '';

    // MODIFICADO: Campos de contenedor - Ahora para múltiples contenedores
    public $showContainerFields = false;
    public $containers = []; // Array para múltiples contenedores
    public $containerTypes = [];

    protected $listeners = [
        'clientCreated' => 'handleClientCreated',
    ];

    // NUEVO: Métodos para búsqueda y creación de clientes (sin modificar del original)
    public function updatedShipperSearch()
    {
        $this->filteredShippers = $this->searchClients($this->shipperSearch);
    }

    public function updatedConsigneeSearch()
    {
        $this->filteredConsignees = $this->searchClients($this->consigneeSearch);
    }

    public function updatedNotifyPartySearch()
    {
        $this->filteredNotifyParties = $this->searchClients($this->notifyPartySearch);
    }

    private function searchClients($search)
    {
        if (strlen($search) < 2) {
            return [];
        }

        return Client::where('status', 'active')
            ->where(function($query) use ($search) {
                $query->where('legal_name', 'like', "%{$search}%")
                      ->orWhere('tax_id', 'like', "%{$search}%")
                      ->orWhere('commercial_name', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function selectShipper($clientId)
    {
        $this->bl_shipper_id = $clientId;
        $client = Client::find($clientId);
        $this->shipperSearch = $client ? $client->legal_name . ' - ' . $client->tax_id : '';
        $this->filteredShippers = [];
    }

    public function selectConsignee($clientId)
    {
        $this->bl_consignee_id = $clientId;
        $client = Client::find($clientId);
        $this->consigneeSearch = $client ? $client->legal_name . ' - ' . $client->tax_id : '';
        $this->filteredConsignees = [];
    }

    public function selectNotifyParty($clientId)
    {
        $this->bl_notify_party_id = $clientId;
        $client = Client::find($clientId);
        $this->notifyPartySearch = $client ? $client->legal_name . ' - ' . $client->tax_id : '';
        $this->filteredNotifyParties = [];
    }

    public function openClientModal($fieldName)
    {
        $this->clientSearchField = $fieldName;
        $this->showClientModal = true;
        $this->resetModalFields();
    }

    public function closeClientModal()
    {
        $this->showClientModal = false;
        $this->clientSearchField = '';
        $this->resetModalFields();
    }

    private function resetModalFields()
    {
        $this->modal_tax_id = '';
        $this->modal_legal_name = '';
        $this->modal_country_id = '';
        $this->modal_document_type_id = '';
        $this->modal_commercial_name = '';
        $this->modal_address = '';
        $this->modal_phone = '';
        $this->modal_email = '';
        $this->modal_use_specific_address = false;
        $this->modal_specific_address_1 = '';
        $this->modal_specific_address_2 = '';
        $this->modal_specific_city = '';
        $this->modal_specific_state = '';
        $this->availableDocumentTypes = [];
    }

    public function updatedModalCountryId()
    {
        if ($this->modal_country_id) {
            $this->availableDocumentTypes = DocumentType::where('country_id', $this->modal_country_id)
                ->where('active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
                ->toArray();
                
            $primaryDocType = DocumentType::where('country_id', $this->modal_country_id)
                ->where('for_tax_purposes', true)
                ->where('is_primary', true)
                ->first();
                
            if ($primaryDocType) {
                $this->modal_document_type_id = $primaryDocType->id;
            }
        } else {
            $this->availableDocumentTypes = [];
            $this->modal_document_type_id = '';
        }
    }

    public function createQuickClient()
    {
        $this->validate([
            'modal_tax_id' => 'required|string|max:15',
            'modal_legal_name' => 'required|string|min:3|max:255',
            'modal_country_id' => 'required|exists:countries,id',
            'modal_document_type_id' => 'nullable|exists:document_types,id',
            'modal_commercial_name' => 'nullable|string|max:255',
            'modal_address_1' => 'nullable|string|max:500',
            'modal_email' => 'nullable|string|max:500',
        ]);

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // ✅ CORRECCIÓN 1: Verificar cliente existente
            $existingClient = \App\Models\Client::where('tax_id', $this->modal_tax_id)
                                            ->where('country_id', $this->modal_country_id)
                                            ->first();

            if ($existingClient) {
                // Cliente existe - verificar relación
                if (!$company->hasClientRelation($existingClient)) {
                    $company->addClient($existingClient, [
                        'relation_type' => 'customer',
                        'can_edit' => true,
                        'active' => true,
                        'priority' => 'normal',
                        'created_by_user_id' => Auth::id(),
                    ]);
                }
                
                $client = $existingClient;
                $message = 'Cliente existente vinculado: ';
                
            } else {
                // ✅ CORRECCIÓN 2: Manejar document_type_id
                $documentTypeId = $this->modal_document_type_id;
                
                if (!$documentTypeId) {
                    // Buscar tipo de documento válido para el país
                    $documentTypeId = \App\Models\DocumentType::where('country_id', $this->modal_country_id)
                                                            ->where('active', true)
                                                            ->first()?->id ?? 1; // Fallback seguro
                }

                // Cliente nuevo - crear
                $client = \App\Models\Client::create([
                    'legal_name' => $this->modal_legal_name,
                    'tax_id' => $this->modal_tax_id,
                    'country_id' => $this->modal_country_id,
                    'document_type_id' => $documentTypeId, // ✅ CAMPO REQUERIDO
                    'commercial_name' => $this->modal_commercial_name,
                    'address' => $this->modal_address_1,
                    'email' => $this->modal_email,
                    'status' => 'active',
                    'verified_at' => now(),
                    'created_by_company_id' => $company->id,
                ]);

                $company->addClient($client, [
                    'relation_type' => 'customer',
                    'can_edit' => true,
                    'active' => true,
                    'priority' => 'normal',
                    'created_by_user_id' => Auth::id(),
                ]);
                
                $message = 'Cliente creado: ';
            }

            DB::commit();

            // Asignar al campo correspondiente (sin cambios)
            switch ($this->clientSearchField) {
                case 'bl_shipper_id':
                    $this->bl_shipper_id = $client->id;
                    $this->bl_shipper_name = $client->legal_name . ' - ' . $client->tax_id;
                    break;
                case 'bl_consignee_id':
                    $this->bl_consignee_id = $client->id;
                    $this->bl_consignee_name = $client->legal_name . ' - ' . $client->tax_id;
                    break;
                case 'bl_notify_party_id':
                    $this->bl_notify_party_id = $client->id;
                    $this->bl_notify_party_name = $client->legal_name . ' - ' . $client->tax_id;
                    break;
            }

            $this->clients = Client::where('status', 'active')->orderBy('legal_name')->get();
            $this->closeClientModal();

            session()->flash('message', $message . $client->legal_name);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // ✅ LOGGING mejorado para debug
            Log::error('Error en createQuickClient ShipmentItemCreateForm', [
                'tax_id' => $this->modal_tax_id,
                'country_id' => $this->modal_country_id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            session()->flash('error', 'Error al crear el cliente: ' . $e->getMessage());
        }
    }

    // ====================================================
    // MÉTODO HELPER PARA ShipmentItemCreateForm (si no existe)
    // ====================================================
    private function getUserCompany()
    {
        $user = Auth::user();
        
        if ($user->userable_type === 'App\\Models\\Company') {
            return $user->userable;
        } elseif ($user->userable_type === 'App\\Models\\Operator') {
            return $user->userable->company;
        }
        
        return null;
    }

    // MÉTODOS ORIGINALES para BL (sin modificar)
    public function mount()
    {
         $this->resetAfipFields();
        if ($this->defaultBLData) {
            $this->bl_bill_number = $this->defaultBLData['bill_number'] ?? '';
            $this->bl_loading_port_id = $this->defaultBLData['loading_port_id'] ?? null;
            $this->bl_discharge_port_id = $this->defaultBLData['discharge_port_id'] ?? null;
            $this->bl_bill_date = $this->defaultBLData['bill_date'] ?? '';
            $this->bl_loading_date = $this->defaultBLData['loading_date'] ?? '';
            $this->bl_freight_terms = $this->defaultBLData['freight_terms'] ?? 'prepaid';
            $this->bl_payment_terms = $this->defaultBLData['payment_terms'] ?? 'cash';
            $this->bl_currency_code = $this->defaultBLData['currency_code'] ?? 'USD';
            $this->bl_primary_cargo_type_id = $this->defaultBLData['primary_cargo_type_id'] ?? null;
            $this->bl_primary_packaging_type_id = $this->defaultBLData['primary_packaging_type_id'] ?? null;
            // Inicializar campos AFIP con valores por defecto
            $this->tariff_position = '';
            $this->is_secure_logistics_operator = 'N';
            $this->is_monitored_transit = 'N';
            $this->is_renar = 'N';
            $this->foreign_forwarder_name = '';
            $this->foreign_forwarder_tax_id = '';
            $this->foreign_forwarder_country = '';
            $this->container_condition = '';
            $this->package_numbers = '';
            $this->packaging_type_code = '';
            $this->discharge_customs_code = '';
            $this->operational_discharge_code = '';
            $this->comments = '';
            $this->consignee_document_type = '';
            $this->consignee_tax_id = '';
        }

        if (!$this->needsToCreateBL) {
            $this->step = 2;
        }

        $this->containerTypes = \App\Models\ContainerType::where('active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
            
        Log::info('ShipmentItemCreateForm mounted', [
            'needs_bl' => $this->needsToCreateBL,
            'show_bl_section' => $this->showBLSection,
            'step' => $this->step,
            'shipment_id' => $this->shipment->id,
            'bill_of_lading_id' => $this->billOfLading?->id
        ]);
    }

    private function resetAfipFields()
    {
        // Reset campos AFIP
        $this->tariff_position = '';
        $this->is_secure_logistics_operator = 'N';
        $this->is_monitored_transit = 'N';
        $this->is_renar = 'N';
        $this->foreign_forwarder_name = '';
        $this->foreign_forwarder_tax_id = '';
        $this->foreign_forwarder_country = null; // ← CAMBIO CRÍTICO: null en lugar de ''
        $this->container_condition = '';
        $this->package_numbers = '';
        $this->packaging_type_code = '';
        $this->discharge_customs_code = '';
        $this->operational_discharge_code = '';
        $this->comments = '';
        $this->consignee_document_type = '';
        $this->consignee_tax_id = '';
        
        // Reset otros campos del item
        $this->item_reference = '';
        $this->item_description = '';
        $this->cargo_type_id = null;
        $this->packaging_type_id = null;
        $this->package_quantity = 1;
        $this->gross_weight_kg = '';
        $this->net_weight_kg = '';
        $this->volume_m3 = '';
        $this->declared_value = '';
        $this->country_of_origin = '';
        $this->hs_code = '';
        $this->commodity_code = '';
        $this->brand_model = '';
        $this->cargo_marks = '';
        $this->package_description = '';
        $this->lot_number = '';
        $this->is_dangerous_goods = false;
        $this->is_perishable = false;
        $this->requires_refrigeration = false;
        $this->special_instructions = '';
        $this->expiry_date = '';
        $this->inspection_type = '';
        
        // Reset contenedores
        $this->containers = [];
        $this->showContainerFields = false;
    }

    // MODIFICADO: Propiedad computada para detectar si es carga contenedorizada
    public function getIsContainerCargoProperty()
    {
        if (!$this->cargoTypes || !$this->cargo_type_id) {
            return false;
        }
        
        if ($this->cargo_type_id == 2) {
            return true;
        }
        
        $cargoType = $this->cargoTypes->find($this->cargo_type_id);
        return $cargoType && str_contains(strtolower($cargoType->name), 'contenedor');
    }

    // MODIFICADO: Actualizar cuando cambia el tipo de carga
    public function updatedCargoTypeId()
    {
        $this->showContainerFields = $this->isContainerCargo;
        
        if (!$this->showContainerFields) {
            $this->containers = [];
        } elseif (empty($this->containers)) {
            // Si se activa por primera vez, agregar un contenedor vacío
            $this->addContainer();
        }
    }

    // NUEVO: Métodos para manejo de múltiples contenedores
    public function addContainer()
    {
        $this->containers[] = [
            'id' => uniqid(), // ID temporal para el frontend
            'container_number' => '',
            'container_type_id' => null,
             'container_condition' => '',
            'seal_number' => '',
            'tare_weight' => 0,
            'package_quantity' => 0,
            'gross_weight_kg' => 0,
            'net_weight_kg' => 0,
            'volume_m3' => 0,
            'loading_sequence' => '',
            'notes' => ''
        ];
    }

    public function removeContainer($index)
    {
        if (count($this->containers) > 1) {
            unset($this->containers[$index]);
            $this->containers = array_values($this->containers); // Re-indexar array
        }
    }

    public function updateContainer($index, $field, $value)
    {
        if (isset($this->containers[$index])) {
            $this->containers[$index][$field] = $value;
        }
    }

    /**
     * NUEVO: Validar y limpiar números de precinto
     */
    private function processSealNumbers($sealString)
    {
        if (empty($sealString)) {
            return [];
        }
        
        // Separar por comas y limpiar
        $seals = array_map('trim', explode(',', $sealString));
        
        // Filtrar valores vacíos
        $seals = array_filter($seals, function($seal) {
            return !empty($seal);
        });
        
        // Validar formato de cada precinto (alfanumérico, 6-15 caracteres)
        foreach ($seals as $seal) {
            if (!preg_match('/^[A-Z0-9]{6,15}$/i', $seal)) {
                throw new \Exception("Formato de precinto inválido: {$seal}. Use solo letras y números (6-15 caracteres).");
            }
        }
        
        return array_values($seals); // Reindexar array
    }

    // NUEVO: Validar contenedores
    private function validateContainers()
    {
        if (!$this->showContainerFields || empty($this->containers)) {
            return true;
        }

        $errors = [];
        $totalPackageQuantity = 0;
        $totalGrossWeight = 0;
        $totalNetWeight = 0;
        $totalVolume = 0;

        foreach ($this->containers as $index => $container) {
            $containerErrors = [];
            
            // Validar campos obligatorios
            if (empty($container['container_number'])) {
                $containerErrors[] = "Número de contenedor es obligatorio";
            }
            
            if (empty($container['container_type_id'])) {
                $containerErrors[] = "Tipo de contenedor es obligatorio";
            }
            
            if ($container['package_quantity'] <= 0) {
                $containerErrors[] = "Cantidad de bultos debe ser mayor a 0";
            }
            
            if ($container['gross_weight_kg'] <= 0) {
                $containerErrors[] = "Peso bruto debe ser mayor a 0";
            }
            
            if ($container['net_weight_kg'] < 0) {
                $containerErrors[] = "Peso neto no puede ser negativo";
            }
            
            if ($container['gross_weight_kg'] < $container['net_weight_kg']) {
                $containerErrors[] = "Peso bruto no puede ser menor al peso neto";
            }

            // Acumular totales
            $totalPackageQuantity += $container['package_quantity'];
            $totalGrossWeight += $container['gross_weight_kg'];
            $totalNetWeight += $container['net_weight_kg'];
            $totalVolume += $container['volume_m3'];

            if (!empty($containerErrors)) {
                $errors["container_{$index}"] = $containerErrors;
            }
        }

        // Validar que los totales coincidan con el item
        if ($totalPackageQuantity != $this->package_quantity) {
            $errors['containers_total'] = "La suma de bultos en contenedores ({$totalPackageQuantity}) debe coincidir con el total del ítem ({$this->package_quantity})";
        }

        if (abs($totalGrossWeight - $this->gross_weight_kg) > 0.01) {
            $errors['containers_total'] = "La suma de peso bruto en contenedores ({$totalGrossWeight}) debe coincidir con el total del ítem ({$this->gross_weight_kg})";
        }

        if (!empty($errors)) {
            foreach ($errors as $field => $messages) {
                $errorMessage = is_array($messages) ? implode(', ', $messages) : $messages;
                $this->addError($field, $errorMessage);
            }
            return false;
        }

        return true;
    }

    // MÉTODOS ORIGINALES (sin modificar)
    public function createBillOfLading()
    {
        // Conservar lógica original completa
        try {
            $this->validate([
                'bl_shipper_id' => 'required|exists:clients,id,status,active',
                'bl_consignee_id' => 'required|exists:clients,id,status,active|different:bl_shipper_id',
                'bl_notify_party_id' => 'nullable|exists:clients,id,status,active',
                'bl_loading_port_id' => 'required|exists:ports,id,active,1',
                'bl_discharge_port_id' => 'required|exists:ports,id,active,1|different:bl_loading_port_id',
                'bl_primary_cargo_type_id' => 'required|exists:cargo_types,id,active,1',
                'bl_primary_packaging_type_id' => 'required|exists:packaging_types,id,active,1',
                'bl_bill_number' => 'required|string|max:100',
                'bl_bill_date' => 'required|date',
                'bl_loading_date' => 'required|date',
                'bl_freight_terms' => 'required|in:prepaid,collect',
                'bl_payment_terms' => 'required|in:cash,credit,advance',
                'bl_currency_code' => 'required|in:USD,ARS,EUR',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        try {
            DB::beginTransaction();

            $blData = [
                'bill_number' => $this->bl_bill_number,
                'shipper_id' => $this->bl_shipper_id,
                'consignee_id' => $this->bl_consignee_id,
                'notify_party_id' => $this->bl_notify_party_id,
                'loading_port_id' => $this->bl_loading_port_id,
                'discharge_port_id' => $this->bl_discharge_port_id,
                'primary_cargo_type_id' => $this->bl_primary_cargo_type_id,
                'primary_packaging_type_id' => $this->bl_primary_packaging_type_id,
                'bill_date' => $this->bl_bill_date,
                'loading_date' => $this->bl_loading_date,
                'freight_terms' => $this->bl_freight_terms,
                'payment_terms' => $this->bl_payment_terms,
                'currency_code' => $this->bl_currency_code,
            ];

            $controller = new ShipmentItemController();
            $this->billOfLading = $controller->createBillOfLadingWithData($this->shipment, $blData);
            
            $this->createSpecificContacts($this->billOfLading);

            DB::commit();

            $this->blCreated = true;
            $this->step = 2;
            $this->showBLSection = false;
            $this->needsToCreateBL = false;

            session()->flash('message', 'Conocimiento de embarque creado exitosamente.');

            Log::info('BillOfLading created via Livewire', [
                'bill_id' => $this->billOfLading->id,
                'bill_number' => $this->billOfLading->bill_number,
                'notify_party_id' => $this->billOfLading->notify_party_id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating BillOfLading in Livewire: ' . $e->getMessage(), [
                'bl_data' => $blData,
                'shipment_id' => $this->shipment->id,
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            session()->flash('error', 'Error al crear el conocimiento de embarque: ' . $e->getMessage());
        }
    }

    // MODIFICADO: Crear Shipment Item con múltiples contenedores
    public function createShipmentItem()
    {
        // Validar campos del item
        try {
            $this->validate([
                'item_reference' => 'required|string|max:100',
                'item_description' => 'required|string|max:2000',
                'cargo_type_id' => 'required|exists:cargo_types,id,active,1',
                'packaging_type_id' => 'required|exists:packaging_types,id,active,1',
                'package_quantity' => 'required|integer|min:1',
                'gross_weight_kg' => 'required|numeric|min:0.01',
                'net_weight_kg' => 'nullable|numeric|min:0',
                'volume_m3' => 'nullable|numeric|min:0',
                'declared_value' => 'nullable|numeric|min:0',
                'country_of_origin' => 'required|string|size:2',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        // Validar contenedores si es carga contenedorizada
        if ($this->showContainerFields && !$this->validateContainers()) {
            $this->dispatch('scroll-to-error');
            return;
        }

        // Validar contenedores si es carga contenedorizada
        if ($this->showContainerFields && !$this->validateContainers()) {
            return;
        }

        if (!$this->billOfLading) {
            session()->flash('error', 'No se ha configurado el conocimiento de embarque.');
            return;
        }

        try {
            DB::beginTransaction();

            $itemData = [
                'bill_of_lading_id' => $this->billOfLading->id,
                'line_number' => $this->nextLineNumber,
                'item_reference' => $this->item_reference,
                'item_description' => $this->item_description,
                'cargo_type_id' => $this->cargo_type_id,
                'packaging_type_id' => $this->packaging_type_id,
                'package_quantity' => $this->package_quantity,
                'gross_weight_kg' => $this->gross_weight_kg,
                'net_weight_kg' => $this->net_weight_kg ?: 0,
                'volume_m3' => $this->volume_m3 ?: 0,
                'declared_value' => $this->declared_value ?: 0,
                'country_of_origin' => $this->country_of_origin,
                'hs_code' => $this->hs_code,
                'commodity_code' => $this->commodity_code,
                'brand_model' => $this->brand_model,
                'cargo_marks' => $this->cargo_marks,
                'package_description' => $this->package_description,
                'lot_number' => $this->lot_number,
                'is_dangerous_goods' => $this->is_dangerous_goods,
                'is_perishable' => $this->is_perishable,
                'requires_refrigeration' => $this->requires_refrigeration,
                'special_instructions' => $this->special_instructions,
                'expiry_date' => $this->expiry_date,
                'inspection_type' => $this->inspection_type,
                // Campos AFIP
                'tariff_position' => $this->tariff_position,
                'is_secure_logistics_operator' => $this->is_secure_logistics_operator,
                'is_monitored_transit' => $this->is_monitored_transit,
                'is_renar' => $this->is_renar,
                'foreign_forwarder_name' => $this->foreign_forwarder_name,
                'foreign_forwarder_tax_id' => $this->foreign_forwarder_tax_id,
                'foreign_forwarder_country' => $this->foreign_forwarder_country,
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
                'container_condition' => $this->container_condition,
                'package_numbers' => $this->package_numbers,
                'packaging_type_code' => $this->packaging_type_code,
                'discharge_customs_code' => $this->discharge_customs_code,
                'operational_discharge_code' => $this->operational_discharge_code,
                'comments' => $this->comments,
                'consignee_document_type' => $this->consignee_document_type,
                'consignee_tax_id' => $this->consignee_tax_id,
            ];

            $shipmentItem = ShipmentItem::create($itemData);

            // NUEVO: Crear múltiples contenedores si es carga contenedorizada
            if ($this->showContainerFields && !empty($this->containers)) {
                $containersCreated = [];
                
                foreach ($this->containers as $containerData) {
                    // Crear el contenedor
                    $container = \App\Models\Container::create([
                        'container_number' => $containerData['container_number'],
                        'container_type_id' => $containerData['container_type_id'],
                        'container_condition' => $containerData['container_condition'] ?? 'P',
                        'tare_weight_kg' => $containerData['tare_weight'] ?: 2200,
                        'max_gross_weight_kg' => 30000,
                        'current_gross_weight_kg' => $containerData['gross_weight_kg'],
                        'cargo_weight_kg' => $containerData['net_weight_kg'],
                        'condition' => 'L', // Loaded
                        'operational_status' => 'loaded',
                        'shipper_seal' => $containerData['seal_number'],
                        'active' => true,
                        'blocked' => false,
                        'out_of_service' => false,
                        'requires_repair' => false,
                        'created_date' => now(),
                        'created_by_user_id' => Auth::id(),
                        'last_updated_date' => now(),
                        'last_updated_by_user_id' => Auth::id(),
                    ]);

                    // Asociar el item con el contenedor en la tabla pivote
                    $container->shipmentItems()->attach($shipmentItem->id, [
                        'package_quantity' => $containerData['package_quantity'],
                        'gross_weight_kg' => $containerData['gross_weight_kg'],
                        'net_weight_kg' => $containerData['net_weight_kg'],
                        'volume_m3' => $containerData['volume_m3'],
                        'quantity_percentage' => ($containerData['package_quantity'] / $this->package_quantity) * 100,
                        'weight_percentage' => ($containerData['gross_weight_kg'] / $this->gross_weight_kg) * 100,
                        'volume_percentage' => $this->volume_m3 > 0 ? ($containerData['volume_m3'] / $this->volume_m3) * 100 : 0,
                        'loading_sequence' => $containerData['loading_sequence'] ?: null,
                        'loaded_at' => now(),
                        'status' => 'loaded',
                        'created_date' => now(),
                        'created_by_user_id' => Auth::id(),
                    ]);

                    $containersCreated[] = $containerData['container_number'];

                    Log::info('Container created and linked to item', [
                        'container_id' => $container->id,
                        'container_number' => $container->container_number,
                        'item_id' => $shipmentItem->id,
                        'package_quantity' => $containerData['package_quantity'],
                        'gross_weight_kg' => $containerData['gross_weight_kg']
                    ]);
                }
            }

            DB::commit();

            // Mensaje dinámico según si se crearon contenedores
            if (!empty($containersCreated)) {
                $containersList = implode(', ', $containersCreated);
                $message = "Item y contenedor(es) {$containersList} creados exitosamente.";
            } else {
                $message = 'Item agregado exitosamente.';
            }

            session()->flash('message', $message);

            // ✅ NUEVO: Dispatch para scroll al tope
            $this->dispatch('item-created');

            if ($this->continueAdding) {
                $this->resetItemForm();
                $this->nextLineNumber++;
            } else {
                return redirect()->route('company.shipments.show', $this->shipment);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating ShipmentItem: ' . $e->getMessage(), [
                'item_data' => $itemData,
                'containers_count' => count($this->containers),
                'bill_of_lading_id' => $this->billOfLading->id,
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            session()->flash('error', 'Error al crear el item: ' . $e->getMessage());
        }
    }

    // MODIFICADO: Reset del formulario incluyendo contenedores
    private function resetItemForm()
    {
        $this->item_reference = '';
        $this->item_description = '';
        $this->cargo_type_id = null;
        $this->packaging_type_id = null;
        $this->package_quantity = 1;
        $this->gross_weight_kg = '';
        $this->net_weight_kg = '';
        $this->volume_m3 = '';
        $this->declared_value = '';
        $this->country_of_origin = '';
        $this->hs_code = '';
        $this->commodity_code = '';
        $this->brand_model = '';
        $this->cargo_marks = '';
        $this->package_description = '';
        $this->lot_number = '';
        $this->is_dangerous_goods = false;
        $this->is_perishable = false;
        $this->requires_refrigeration = false;
        $this->special_instructions = '';
        $this->expiry_date = '';
        $this->inspection_type = '';
        
        // Reset contenedores
        $this->containers = [];
        $this->showContainerFields = false;

        // Reset campos AFIP
        $this->tariff_position = '';
        $this->is_secure_logistics_operator = 'N';
        $this->is_monitored_transit = 'N';
        $this->is_renar = 'N';
        $this->foreign_forwarder_name = '';
        $this->foreign_forwarder_tax_id = '';
        $this->foreign_forwarder_country = '';
        $this->container_condition = '';
        $this->package_numbers = '';
        $this->packaging_type_code = '';
        $this->discharge_customs_code = '';
        $this->operational_discharge_code = '';
        $this->comments = '';
        $this->consignee_document_type = '';
        $this->consignee_tax_id = '';
    }

    // MÉTODOS ORIGINALES (sin modificar)
    public function toggleBLSection()
    {
        $this->showBLSection = !$this->showBLSection;
    }

    public function goToStep($step)
    {
        $this->step = $step;
    }

    private function createSpecificContacts($billOfLading)
    {
        // Lógica para crear contactos específicos para el BL si se definieron direcciones específicas
        if ($this->bl_shipper_use_specific) {
            // Lógica para crear contacto específico del shipper
        }
        
        if ($this->bl_consignee_use_specific) {
            // Lógica para crear contacto específico del consignee
        }
        
        if ($this->bl_notify_use_specific) {
            // Lógica para crear contacto específico del notify party
        }
    }

    public function render()
    {
        return view('livewire.shipment-item-create-form');
    }
}