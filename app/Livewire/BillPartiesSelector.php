<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\Client;
use App\Models\Country;
use Illuminate\Support\Facades\Auth;

class BillPartiesSelector extends Component
{
    // Props externas
    public $countries = [];
    
    // Datos de partes involucradas
    #[Validate('required|exists:clients,id,status,active')]
    public $shipper_id = null;

    #[Validate('required|exists:clients,id,status,active|different:shipper_id')]
    public $consignee_id = null;

    #[Validate('nullable|exists:clients,id,status,active')]
    public $notify_party_id = null;

    #[Validate('nullable|exists:clients,id,status,active')]
    public $cargo_owner_id = null;

    // Direcciones específicas - Cargador
    public $shipper_use_specific = false;
    public $shipper_address_1 = '';
    public $shipper_address_2 = '';
    public $shipper_city = '';
    public $shipper_state = '';
    public $shipper_postal_code = '';
    public $shipper_country_id = null;

    // Direcciones específicas - Consignatario
    public $consignee_use_specific = false;
    public $consignee_address_1 = '';
    public $consignee_address_2 = '';
    public $consignee_city = '';
    public $consignee_state = '';
    public $consignee_postal_code = '';
    public $consignee_country_id = null;

    // Direcciones específicas - Notificar
    public $notify_use_specific = false;
    public $notify_address_1 = '';
    public $notify_address_2 = '';
    public $notify_city = '';
    public $notify_state = '';
    public $notify_postal_code = '';
    public $notify_country_id = null;

    // Creación de nuevo cliente
    public $showCreateModal = false;
    public $clientType = ''; // 'shipper', 'consignee', 'notify', 'cargo_owner'
    
    // Datos del nuevo cliente
    public $new_legal_name = '';
    public $new_tax_id = '';
    public $new_country_id = null;
    public $new_email = '';
    public $new_phone = '';
    public $new_address_1 = '';
    public $new_city = '';

    public function mount($billOfLading = null)
    {
        // Cargar datos del bill of lading si existe (para edición)
        if ($billOfLading) {
            $this->shipper_id = $billOfLading->shipper_id;
            $this->consignee_id = $billOfLading->consignee_id;
            $this->notify_party_id = $billOfLading->notify_party_id;
            $this->cargo_owner_id = $billOfLading->cargo_owner_id;

            // Cargar direcciones específicas si existen
            $this->loadSpecificAddresses($billOfLading);
        }

        // Cargar países para el modal de crear cliente
        $this->loadCountries();
    }

    public function loadCountries()
    {
        $this->countries = Country::where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function loadSpecificAddresses($billOfLading)
    {
        // Implementar carga de direcciones específicas desde campos del BL
        // (si se almacenan en campos separados del modelo)
        
        // Ejemplo básico - ajustar según estructura real de la BD
        if ($billOfLading->shipper_specific_address) {
            $this->shipper_use_specific = true;
            $this->shipper_address_1 = $billOfLading->shipper_address_1;
            $this->shipper_address_2 = $billOfLading->shipper_address_2;
            $this->shipper_city = $billOfLading->shipper_city;
            $this->shipper_state = $billOfLading->shipper_state;
            $this->shipper_postal_code = $billOfLading->shipper_postal_code;
            $this->shipper_country_id = $billOfLading->shipper_country_id;
        }
        
        // Repetir para consignee y notify party...
    }

    public function openCreateModal($type)
    {
        $this->clientType = $type;
        $this->showCreateModal = true;
        $this->resetNewClientData();
    }

    public function resetNewClientData()
    {
        $this->new_legal_name = '';
        $this->new_tax_id = '';
        $this->new_country_id = null;
        $this->new_email = '';
        $this->new_phone = '';
        $this->new_address_1 = '';
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
            'new_address_1' => 'nullable|string|max:255',
            'new_city' => 'nullable|string|max:100',
        ]);

        $company = auth()->user()->userable_type === 'App\\Models\\Company' 
            ? auth()->user()->userable 
            : auth()->user()->userable->company;

        try {
            \DB::beginTransaction();

            // ✅ CORRECCIÓN 1: Verificar cliente existente
            $existingClient = Client::where('tax_id', $this->new_tax_id)
                                ->where('country_id', $this->new_country_id)
                                ->first();

            if ($existingClient) {
                // Cliente existe - verificar relación con empresa
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
                $message = 'Cliente existente vinculado exitosamente.';
                
            } else {
                // ✅ CORRECCIÓN 2: Obtener document_type_id válido
                $documentTypeId = \App\Models\DocumentType::where('country_id', $this->new_country_id)
                                                        ->where('active', true)
                                                        ->first()?->id ?? 1; // Fallback seguro

                // Cliente nuevo - crear
                $client = Client::create([
                    'legal_name' => $this->new_legal_name,
                    'tax_id' => $this->new_tax_id,
                    'country_id' => $this->new_country_id,
                    'document_type_id' => $documentTypeId, // ✅ CAMPO REQUERIDO
                    'address' => $this->new_address_1,
                    'email' => $this->new_email,
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
                
                $message = 'Cliente creado exitosamente.';
            }

            // Datos de contacto adicionales (sin cambios)
            if ($this->new_phone || $this->new_city) {
                $client->contactData()->updateOrCreate(
                    ['client_id' => $client->id],
                    [
                        'phone' => $this->new_phone,
                        'city' => $this->new_city,
                        'address_line_1' => $this->new_address_1,
                    ]
                );
            }

            \DB::commit();

            // Asignar cliente al campo correspondiente (sin cambios)
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

            $this->dispatch('refreshClients');
            $this->showCreateModal = false;
            $this->dispatch('clientCreated', [
                'client_id' => $client->id,
                'type' => $this->clientType
            ]);

            session()->flash('message', $message);

        } catch (\Exception $e) {
            \DB::rollBack();
            
            // ✅ LOGGING mejorado para debug
            Log::error('Error en createClient BillPartiesSelector', [
                'tax_id' => $this->new_tax_id,
                'country_id' => $this->new_country_id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            session()->flash('error', 'Error al crear el cliente: ' . $e->getMessage());
        }
    }

    public function cancelCreate()
    {
        $this->showCreateModal = false;
        $this->resetNewClientData();
    }

    // Listeners para los eventos de SearchClient
    protected $listeners = [
        'clientSelected' => 'handleClientSelected',
        'clientCleared' => 'handleClientCleared'
    ];

    public function handleClientSelected($data)
    {
        $fieldName = $data['fieldName'];
        $clientId = $data['clientId'];
        
        switch ($fieldName) {
            case 'shipper_id':
                $this->shipper_id = $clientId;
                $this->dispatch('updateFormField', 'shipper_id', $clientId);
                break;
            case 'consignee_id':
                $this->consignee_id = $clientId;
                $this->dispatch('updateFormField', 'consignee_id', $clientId);
                break;
            case 'notify_party_id':
                $this->notify_party_id = $clientId;
                $this->dispatch('updateFormField', 'notify_party_id', $clientId);
                break;
            case 'cargo_owner_id':
                $this->cargo_owner_id = $clientId;
                $this->dispatch('updateFormField', 'cargo_owner_id', $clientId);
                break;
        }
    }

    public function handleClientCleared($data)
    {
        $fieldName = $data['fieldName'];
        
        switch ($fieldName) {
            case 'shipper_id':
                $this->shipper_id = null;
                $this->dispatch('updateFormField', 'shipper_id', null);
                break;
            case 'consignee_id':
                $this->consignee_id = null;
                $this->dispatch('updateFormField', 'consignee_id', null);
                break;
            case 'notify_party_id':
                $this->notify_party_id = null;
                $this->dispatch('updateFormField', 'notify_party_id', null);
                break;
            case 'cargo_owner_id':
                $this->cargo_owner_id = null;
                $this->dispatch('updateFormField', 'cargo_owner_id', null);
                break;
        }
    }

    // Método para obtener todos los datos del componente
    public function getPartiesData()
    {
        return [
            'shipper_id' => $this->shipper_id,
            'consignee_id' => $this->consignee_id,
            'notify_party_id' => $this->notify_party_id,
            'cargo_owner_id' => $this->cargo_owner_id,
            
            // Direcciones específicas
            'shipper_use_specific' => $this->shipper_use_specific,
            'shipper_address_1' => $this->shipper_address_1,
            'shipper_address_2' => $this->shipper_address_2,
            'shipper_city' => $this->shipper_city,
            'shipper_state' => $this->shipper_state,
            'shipper_postal_code' => $this->shipper_postal_code,
            'shipper_country_id' => $this->shipper_country_id,
            
            'consignee_use_specific' => $this->consignee_use_specific,
            'consignee_address_1' => $this->consignee_address_1,
            'consignee_address_2' => $this->consignee_address_2,
            'consignee_city' => $this->consignee_city,
            'consignee_state' => $this->consignee_state,
            'consignee_postal_code' => $this->consignee_postal_code,
            'consignee_country_id' => $this->consignee_country_id,
            
            'notify_use_specific' => $this->notify_use_specific,
            'notify_address_1' => $this->notify_address_1,
            'notify_address_2' => $this->notify_address_2,
            'notify_city' => $this->notify_city,
            'notify_state' => $this->notify_state,
            'notify_postal_code' => $this->notify_postal_code,
            'notify_country_id' => $this->notify_country_id,
        ];
    }

    public function render()
    {
        return view('livewire.bill-parties-selector');
    }
}