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

    // === DIRECCIONES ESPECÍFICAS (igual que en CREATE) ===
    // Shipper
    public $bl_shipper_use_specific = false;
    public $bl_shipper_address_1 = '';  
    public $bl_shipper_address_2 = '';
    public $bl_shipper_city = '';
    public $bl_shipper_state = '';

    // Consignee  
    public $bl_consignee_use_specific = false;
    public $bl_consignee_address_1 = '';
    public $bl_consignee_address_2 = '';
    public $bl_consignee_city = '';
    public $bl_consignee_state = '';

    // Notify
    public $bl_notify_use_specific = false;
    public $bl_notify_address_1 = '';
    public $bl_notify_address_2 = '';
    public $bl_notify_city = '';
    public $bl_notify_state = '';

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

    /**
     * Reglas de validación (copiadas del componente original)
     */
    protected $rules = [
        'shipment_id' => 'required|exists:shipments,id',
        'bill_number' => 'required|string|max:100',
        'bill_date' => 'required|date',
        'loading_date' => 'required|date',
        'discharge_date' => 'nullable|date|after_or_equal:loading_date',
        'freight_terms' => 'required|in:prepaid,collect,third_party',
        'payment_terms' => 'required|in:cash,credit,letter_of_credit,other',
        'currency_code' => 'required|in:USD,ARS,EUR,BRL',
        'incoterms' => 'nullable|string|max:10',
        'shipper_id' => 'required|exists:clients,id,status,active',
        'consignee_id' => 'required|exists:clients,id,status,active|different:shipper_id',
        'notify_party_id' => 'nullable|exists:clients,id,status,active',
        'cargo_owner_id' => 'nullable|exists:clients,id,status,active',
        'loading_port_id' => 'required|exists:ports,id,active,1',
        'discharge_port_id' => 'required|exists:ports,id,active,1|different:loading_port_id',
        'transshipment_port_id' => 'nullable|exists:ports,id,active,1',
        'final_destination_port_id' => 'nullable|exists:ports,id,active,1',
        'loading_customs_id' => 'nullable|exists:custom_offices,id,active,1',
        'discharge_customs_id' => 'nullable|exists:custom_offices,id,active,1',
        'primary_cargo_type_id' => 'required|exists:cargo_types,id,active,1',
        'primary_packaging_type_id' => 'required|exists:packaging_types,id,active,1',
        'cargo_description' => 'required|string|max:3000',
        'cargo_marks' => 'nullable|string|max:1000',
        'commodity_code' => 'nullable|string|max:100',
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

    public function mount($billOfLading)
    {
        $this->billOfLading = $billOfLading;
        
        // Cargar datos del formulario (igual que en create)
        $this->loadFormData();
        
        // Cargar datos del BL existente
        $this->loadBillOfLadingData();
    }

    /**
     * Cargar colecciones para selectores (copiado del create)
     */
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

        $this->loadingPorts = Port::where('active', true)->orderBy('name')->get();
        $this->dischargePorts = Port::where('active', true)->orderBy('name')->get();
        $this->transshipmentPorts = Port::where('active', true)->orderBy('name')->get();
        $this->finalDestinationPorts = Port::where('active', true)->orderBy('name')->get();
        $this->customsOffices = CustomOffice::where('active', true)->orderBy('name')->get();
        $this->cargoTypes = CargoType::where('active', true)->orderBy('name')->get();
        $this->packagingTypes = PackagingType::where('active', true)->orderBy('name')->get();
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
        $this->freight_terms = $bl->freight_terms ?? 'prepaid';
        $this->payment_terms = $bl->payment_terms ?? 'cash';
        $this->currency_code = $bl->currency_code ?? 'USD';
        $this->incoterms = $bl->incoterms ?? '';

        // Partes
        $this->shipper_id = $bl->shipper_id ?? '';
        $this->consignee_id = $bl->consignee_id ?? '';
        $this->notify_party_id = $bl->notify_party_id ?? '';
        $this->cargo_owner_id = $bl->cargo_owner_id ?? '';

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
                case 'notify_party':
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
        $this->{$prefix . '_specific_company_name'} = $contact->specific_company_name ?? '';
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
     * Actualizar contactos específicos
     */
    private function updateSpecificContacts()
    {
        // Eliminar contactos específicos existentes
        $this->billOfLading->specificContacts()->delete();

        // Crear nuevos contactos específicos si aplica
        $this->createSpecificContactIfNeeded('shipper', $this->shipper_id);
        $this->createSpecificContactIfNeeded('consignee', $this->consignee_id);
        if ($this->notify_party_id) {
            $this->createSpecificContactIfNeeded('notify_party', $this->notify_party_id, 'notify');
        }
    }

    /**
     * Crear contacto específico si es necesario
     */
    private function createSpecificContactIfNeeded($role, $clientId, $prefix = null)
    {
        $prefix = $prefix ?: $role;
        $useSpecific = $this->{$prefix . '_use_specific'};

        if (!$useSpecific) return;

        // Obtener datos del contacto principal del cliente
        $client = Client::find($clientId);
        $clientContact = $client->contactData->first();

        if (!$clientContact) return;

        \App\Models\BillOfLadingContact::create([
            'bill_of_lading_id' => $this->billOfLading->id,
            'client_contact_data_id' => $clientContact->id,
            'role' => $role,
            'use_specific_data' => true,
            'specific_company_name' => $this->{$prefix . '_specific_company_name'} ?: null,
            'specific_address_line_1' => $this->{$prefix . '_specific_address_1'} ?: null,
            'specific_address_line_2' => $this->{$prefix . '_specific_address_2'} ?: null,
            'specific_city' => $this->{$prefix . '_specific_city'} ?: null,
            'specific_state_province' => $this->{$prefix . '_specific_state'} ?: null,
            'specific_postal_code' => $this->{$prefix . '_specific_postal_code'} ?: null,
            'specific_country' => $this->{$prefix . '_specific_country'} ?: null,
            'specific_phone' => $this->{$prefix . '_specific_phone'} ?: null,
            'specific_email' => $this->{$prefix . '_specific_email'} ?: null,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Obtener nombre del cliente seleccionado
     */
    public function getSelectedClientName($clientId)
    {
        if (!$clientId) return '';
        
        $client = $this->clients->firstWhere('id', $clientId);
        return $client ? $client->legal_name : '';
    }

    /**
     * Actualizar BL - basado en el método submit() del create
     */
    public function submit()
    {
        $this->loading = true;

        try {
            $this->validate();

            DB::beginTransaction();

            $data = [
                'shipment_id' => $this->shipment_id,
                'bill_number' => $this->bill_number,
                'bill_date' => $this->bill_date,
                'loading_date' => $this->loading_date,
                'discharge_date' => $this->discharge_date ?: null,
                'freight_terms' => $this->freight_terms,
                'payment_terms' => $this->payment_terms,
                'currency_code' => $this->currency_code,
                'incoterms' => $this->incoterms ?: null,
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

    public function render()
    {
        return view('livewire.bill-of-lading-edit-form');
    }
}