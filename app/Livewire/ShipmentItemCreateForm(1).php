<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Http\Controllers\Company\ShipmentItemController;
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
    // Cliente dueño de la mercadería
    public $searchClient = '';
    public $selectedClientId = '';
    public $selectedClientName = '';
    
    

    // Estado del componente
    public $step = 1; // 1 = Configurar BL (si es necesario), 2 = Agregar Item
    public $showBLSection = false;
    public $blCreated = false;

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
    public $gross_weight_kg = null;

    #[Validate('nullable|numeric|min:0')]
    public $net_weight_kg = null;

    #[Validate('nullable|numeric|min:0')]
    public $volume_m3 = null;

    #[Validate('nullable|numeric|min:0')]
    public $declared_value = null;

    #[Validate('required|string|size:2')]
    public $country_of_origin = 'AR';

    #[Validate('nullable|string|max:100')]
    public $hs_code = '';

    #[Validate('nullable|string|max:500')]
    public $cargo_marks = '';

    // Campos adicionales para contenedores
    #[Validate('nullable|string|max:15')]
    public $container_number = '';

    #[Validate('nullable|exists:container_types,id')]
    public $container_type_id = null;

    #[Validate('nullable|string|max:50')]
    public $seal_number = '';

    #[Validate('nullable|numeric|min:0')]
    public $tare_weight = 0;

    public $showContainerFields = false;
    public $containerTypes;

    public function mount()
    {
        // Determinar si necesitamos mostrar la sección de BL
        $this->showBLSection = $this->needsToCreateBL;
        
        if ($this->needsToCreateBL && $this->defaultBLData) {
            // Pre-poblar datos del BL con valores por defecto
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
        }

        // Si ya existe BL, pasar directamente al step 2
        if (!$this->needsToCreateBL) {
            $this->step = 2;
        }

        // contenedore
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

    public function getIsContainerCargoProperty()
    {
        if (!$this->cargoTypes || !$this->cargo_type_id) {
            return false;
        }
        
        // Buscar por ID específico (más confiable)
        if ($this->cargo_type_id == 2) {
            return true;
        }
        
        // Buscar por nombre como fallback
        $cargoType = $this->cargoTypes->find($this->cargo_type_id);
        return $cargoType && str_contains(strtolower($cargoType->name), 'contenedor');
    }

    public function updatedCargoTypeId()
    {
        $this->showContainerFields = $this->isContainerCargo;
        
        if (!$this->showContainerFields) {
            $this->container_number = '';
            $this->container_type_id = null;
            $this->seal_number = '';
            $this->tare_weight = 0;
        }
    }

    public function createBillOfLading()
    {
        // Validar solo los campos del BL
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

        try {
            DB::beginTransaction();

            // Preparar datos del BL
            $blData = [
                'bill_number' => $this->bl_bill_number,
                'shipper_id' => $this->bl_shipper_id,
                'consignee_id' => $this->bl_consignee_id,
                'notify_party_id' => $this->bl_notify_party_id, // ← AQUÍ está el fix!
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

            // Usar el método del controlador para crear el BL
            $controller = new ShipmentItemController();
            $this->billOfLading = $controller->createBillOfLadingWithData($this->shipment, $blData);
            // Crear contactos específicos si se definieron
            $this->createSpecificContacts($this->billOfLading);

            DB::commit();

            // Marcar como creado y avanzar al siguiente step
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

    public function createShipmentItem()
    {
    // Validar campos del item
    $this->validate([
        'selectedClientId' => 'required|exists:clients,id',
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

    if (!$this->billOfLading) {
        session()->flash('error', 'No se ha configurado el conocimiento de embarque.');
        return;
    }

    try {
        DB::beginTransaction();

        // Crear el shipment item
        $shipmentItem = ShipmentItem::create([
            'bill_of_lading_id' => $this->billOfLading->id,
            'line_number' => $this->nextLineNumber,
            'item_reference' => $this->item_reference,
            'item_description' => $this->item_description,
            'cargo_type_id' => $this->cargo_type_id,
            'packaging_type_id' => $this->packaging_type_id,
            'package_quantity' => $this->package_quantity,
            'gross_weight_kg' => $this->gross_weight_kg,
            'net_weight_kg' => $this->net_weight_kg,
            'volume_m3' => $this->volume_m3,
            'declared_value' => $this->declared_value,
            'currency_code' => $this->bl_currency_code,
            'country_of_origin' => $this->country_of_origin,
            'hs_code' => $this->hs_code,
            'cargo_marks' => $this->cargo_marks,
            'status' => 'draft',
            'created_date' => now(),
            'created_by_user_id' => Auth::id(),
            'last_updated_date' => now(),
            'last_updated_by_user_id' => Auth::id(),
        ]);

        // SI es carga contenedorizada, crear el contenedor automáticamente
        if ($this->showContainerFields && !empty($this->container_number)) {
            $container = \App\Models\Container::create([
                // Campos obligatorios
                'container_number' => $this->container_number,
                'container_type_id' => $this->container_type_id,
                'tare_weight_kg' => $this->tare_weight ?: 2200,
                'max_gross_weight_kg' => 30000, // Valor por defecto
                'current_gross_weight_kg' => $this->gross_weight_kg,
                'cargo_weight_kg' => $this->net_weight_kg,
                'condition' => 'L', // Loaded
                'operational_status' => 'loaded',
                
                // Precintos
                'shipper_seal' => $this->seal_number,
                
                // Estado
                'active' => true,
                'blocked' => false,
                'out_of_service' => false,
                'requires_repair' => false,
                
                // Auditoría
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);

            // Asociar el item con el contenedor en la tabla pivote
            $container->shipmentItems()->attach($shipmentItem->id, [
                'package_quantity' => $this->package_quantity,
                'gross_weight_kg' => $this->gross_weight_kg,
                'net_weight_kg' => $this->net_weight_kg,
                'volume_m3' => $this->volume_m3,
                'quantity_percentage' => 100.00,
                'weight_percentage' => 100.00,
                'volume_percentage' => 100.00,
                'loaded_at' => now(),
                'status' => 'loaded',
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
            ]);

            Log::info('Container created automatically with item', [
                'container_id' => $container->id,
                'item_id' => $shipmentItem->id,
                'container_number' => $this->container_number
            ]);
        }
        // Recalcular estadísticas del shipment
        $this->shipment->recalculateItemStats();

        DB::commit();

        Log::info('ShipmentItem created via Livewire', [
            'item_id' => $shipmentItem->id,
            'bill_of_lading_id' => $this->billOfLading->id,
            'line_number' => $this->nextLineNumber
        ]);

            // Determinar mensaje según si se creó contenedor o no
        $message = ($this->showContainerFields && !empty($this->container_number)) 
            ? "Item y contenedor {$this->container_number} creados exitosamente." 
            : 'Item agregado exitosamente.';

        // Redirigir para agregar otro item inmediatamente
        return redirect()->route('company.shipment-items.create', ['shipment' => $this->shipment->id])
            ->with('success', $message . ' Agregar otro item:');


    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error creating ShipmentItem in Livewire: ' . $e->getMessage(), [
            'shipment_id' => $this->shipment->id,
            'bill_of_lading_id' => $this->billOfLading?->id,
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]);

        session()->flash('error', 'Error al crear el item: ' . $e->getMessage());
    }
    }

    public function getFilteredClientsProperty()
    {
        if (strlen($this->searchClient) < 2) {
            return collect();
        }
        
        return \App\Models\Client::where('status', 'active')
            ->where(function($query) {
                $query->where('legal_name', 'like', '%' . $this->searchClient . '%')
                    ->orWhere('commercial_name', 'like', '%' . $this->searchClient . '%')
                    ->orWhere('tax_id', 'like', '%' . $this->searchClient . '%');
            })
            ->limit(20)
            ->get();
    }

    

    public function render()
    {
        return view('livewire.shipment-item-create-form');
    }

    private function getContainerTypeCode($containerTypeId)
    {
        if (!$containerTypeId) return '40HC';
        
        $containerType = $this->containerTypes->find($containerTypeId);
        return $containerType ? $containerType->code : '40HC';
    }

    private function getPackagingTypeName($packagingTypeId)
    {
        if (!$packagingTypeId) return 'CTNS';
        
        $packagingType = $this->packagingTypes->find($packagingTypeId);
        return $packagingType ? $packagingType->code : 'CTNS';
    }

    protected function createSpecificContacts(BillOfLading $billOfLading): void
    {
        // Crear contacto específico del shipper
        if ($this->bl_shipper_use_specific && $this->bl_shipper_id) {
            \App\Models\BillOfLadingContact::create([
                'bill_of_lading_id' => $billOfLading->id,
                'client_contact_data_id' => $this->getClientContactId($this->bl_shipper_id),
                'role' => 'shipper',
                'specific_address_line_1' => $this->bl_shipper_address_1,
                'specific_address_line_2' => $this->bl_shipper_address_2,
                'specific_city' => $this->bl_shipper_city,
                'specific_state_province' => $this->bl_shipper_state,
                'use_specific_data' => true,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        // Crear contacto específico del consignee
        if ($this->bl_consignee_use_specific && $this->bl_consignee_id) {
            \App\Models\BillOfLadingContact::create([
                'bill_of_lading_id' => $billOfLading->id,
                'client_contact_data_id' => $this->getClientContactId($this->bl_consignee_id),
                'role' => 'consignee',
                'specific_address_line_1' => $this->bl_consignee_address_1,
                'specific_address_line_2' => $this->bl_consignee_address_2,
                'specific_city' => $this->bl_consignee_city,
                'specific_state_province' => $this->bl_consignee_state,
                'use_specific_data' => true,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        // Crear contacto específico del notify party
        if ($this->bl_notify_use_specific && $this->bl_notify_party_id) {
            \App\Models\BillOfLadingContact::create([
                'bill_of_lading_id' => $billOfLading->id,
                'client_contact_data_id' => $this->getClientContactId($this->bl_notify_party_id),
                'role' => 'notify_party',
                'specific_address_line_1' => $this->bl_notify_address_1,
                'specific_address_line_2' => $this->bl_notify_address_2,
                'specific_city' => $this->bl_notify_city,
                'specific_state_province' => $this->bl_notify_state,
                'use_specific_data' => true,
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    protected function getClientContactId($clientId): int
    {
        // Obtener el primer contacto del cliente (o crear uno básico)
        $contact = \App\Models\ClientContactData::where('client_id', $clientId)->first();
        
        if (!$contact) {
            // Crear contacto básico si no existe
            $contact = \App\Models\ClientContactData::create([
                'client_id' => $clientId,
                'active' => true,
                'is_primary' => true,
                'created_by_user_id' => Auth::id(),
            ]);
        }
        
        return $contact->id;
    }
}