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

        Log::info('ShipmentItemCreateForm mounted', [
            'needs_bl' => $this->needsToCreateBL,
            'show_bl_section' => $this->showBLSection,
            'step' => $this->step,
            'shipment_id' => $this->shipment->id,
            'bill_of_lading_id' => $this->billOfLading?->id
        ]);
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

            // Recalcular estadísticas del shipment
            $this->shipment->recalculateItemStats();

            DB::commit();

            Log::info('ShipmentItem created via Livewire', [
                'item_id' => $shipmentItem->id,
                'bill_of_lading_id' => $this->billOfLading->id,
                'line_number' => $this->nextLineNumber
            ]);

            // Redirigir al shipment
            return redirect()->route('company.shipments.show', $this->shipment)
                ->with('success', 'Item agregado exitosamente al shipment.');

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

    public function render()
    {
        return view('livewire.shipment-item-create-form');
    }
}