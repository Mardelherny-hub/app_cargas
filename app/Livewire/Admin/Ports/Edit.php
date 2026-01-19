<?php

namespace App\Livewire\Admin\Ports;

use App\Models\Port;
use App\Models\Country;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Validate;

class Edit extends Component
{
    public Port $port;
    
    // Campos básicos de identificación
    #[Validate('nullable|string|max:10')]
    public ?string $code = '';

    #[Validate('required|string|max:150')]
    public ?string $name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $short_name = '';

    #[Validate('nullable|string|max:150')]
    public ?string $local_name = '';

    // Información de ubicación
    #[Validate('required|exists:countries,id')]
    public ?int $country_id = null;

    #[Validate('required|string|max:100')]
    public ?string $city = '';

    #[Validate('nullable|string|max:100')]
    public ?string $province_state = '';

    #[Validate('nullable|string|max:500')]
    public ?string $address = '';

    #[Validate('nullable|string|max:20')]
    public ?string $postal_code = '';

    // Coordenadas geográficas
    #[Validate('nullable|numeric|between:-90,90')]
    public ?float $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?float $longitude = null;

    #[Validate('nullable|numeric|min:0')]
    public ?float $water_depth = null;

    // Clasificación del puerto
    #[Validate('required|in:river,maritime,lake,canal,mixed')]
    public ?string $port_type = 'river';

    #[Validate('required|in:major,minor,terminal,anchorage,private')]
    public ?string $port_category = 'major';

    // Capacidades operativas
    public bool $handles_containers = true;
    public bool $handles_bulk_cargo = true;
    public bool $handles_general_cargo = true;
    public bool $handles_passengers = false;
    public bool $handles_dangerous_goods = false;
    public bool $has_customs_office = true;

    // Información de infraestructura
    #[Validate('nullable|integer|min:0')]
    public ?int $max_vessel_length = null;

    #[Validate('nullable|numeric|min:0')]
    public ?float $max_draft = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $berths_count = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $cranes_count = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $warehouse_area = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $open_storage_area = null;

    // Información de contacto
    #[Validate('nullable|string|max:20')]
    public ?string $phone = '';

    #[Validate('nullable|string|max:20')]
    public ?string $fax = '';

    #[Validate('nullable|email|max:100')]
    public ?string $email = '';

    #[Validate('nullable|url|max:255')]
    public ?string $website = '';

    #[Validate('nullable|string|max:10')]
    public ?string $vhf_channel = '';

    #[Validate('nullable|string|max:10')]
    public ?string $afip_code = '';

    #[Validate('nullable|string|max:10')]
    public ?string $dna_code = '';

    // Información económica
    #[Validate('nullable|string|max:3')]
    public ?string $currency_code = '';

    // Estado y configuración
    public bool $active = true;
    public bool $accepts_new_vessels = true;

    #[Validate('nullable|integer|min:0')]
    public int $display_order = 999;

    #[Validate('nullable|date')]
    public ?string $established_date = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $special_notes = '';

    // Datos adicionales para la vista
    public array $countries = [];
    public array $portTypes = [];
    public array $portCategories = [];

    // Estados del componente
    public bool $showAdvanced = false;
    public string $activeTab = 'basic';
    public bool $hasUnsavedChanges = false;

    // Campos originales para detectar cambios
    protected array $originalValues = [];

    public function mount($portId = null)
{
    if ($portId) {
        // Cargar el puerto desde la base de datos
        $this->port = Port::with('country')->findOrFail($portId);
        
        // Asignar valores a las propiedades del componente
        $this->code = $this->port->code;
        $this->name = $this->port->name;
        $this->short_name = $this->port->short_name;
        $this->local_name = $this->port->local_name;
        $this->country_id = $this->port->country_id;
        $this->city = $this->port->city;
        $this->province_state = $this->port->province_state;
        $this->address = $this->port->address;
        $this->postal_code = $this->port->postal_code;
        $this->latitude = $this->port->latitude;
        $this->longitude = $this->port->longitude;
        $this->water_depth = $this->port->water_depth;
        $this->port_type = $this->port->port_type;
        $this->port_category = $this->port->port_category;
        $this->handles_containers = $this->port->handles_containers;
        $this->handles_bulk_cargo = $this->port->handles_bulk_cargo;
        $this->handles_general_cargo = $this->port->handles_general_cargo;
        $this->handles_passengers = $this->port->handles_passengers;
        $this->handles_dangerous_goods = $this->port->handles_dangerous_goods;
        $this->has_customs_office = $this->port->has_customs_office;
        $this->max_vessel_length = $this->port->max_vessel_length;
        $this->max_draft = $this->port->max_draft;
        $this->berths_count = $this->port->berths_count;
        $this->storage_area = $this->port->storage_area;
        $this->has_crane = $this->port->has_crane;
        $this->has_warehouse = $this->port->has_warehouse;
        $this->webservice_code = $this->port->webservice_code;
        $this->afip_code = $this->port->afip_code;
        $this->dna_code = $this->port->dna_code;
        $this->supports_anticipada = $this->port->supports_anticipada;
        $this->supports_micdta = $this->port->supports_micdta;
        $this->supports_manifest = $this->port->supports_manifest;
        $this->phone = $this->port->phone;
        $this->email = $this->port->email;
        $this->website = $this->port->website;
        $this->vhf_channel = $this->port->vhf_channel;
        $this->port_authority = $this->port->port_authority;
        $this->timezone = $this->port->timezone;
        $this->active = $this->port->active;
        $this->accepts_new_vessels = $this->port->accepts_new_vessels;
        $this->operates_24h = $this->port->operates_24h;
        $this->display_order = $this->port->display_order;
        $this->special_notes = $this->port->special_notes;
        
        // También cargar países para el select
        $this->loadCountries();
    }
}

private function loadCountries()
{
    $this->countries = Country::where('active', true)
                             ->orderBy('name')
                             ->get()
                             ->toArray();
}
    protected function loadPortData()
    {
        // Identificación básica
        $this->code = $this->port->code ?? '';
        $this->name = $this->port->name ?? '';
        $this->short_name = $this->port->short_name ?? '';
        $this->local_name = $this->port->local_name ?? '';

        // Ubicación
        $this->country_id = $this->port->country_id;
        $this->city = $this->port->city ?? '';
        $this->province_state = $this->port->province_state ?? '';
        $this->address = $this->port->address ?? '';
        $this->postal_code = $this->port->postal_code ?? '';

        // Coordenadas - mantener null si no hay valor
        $this->latitude = $this->port->latitude;
        $this->longitude = $this->port->longitude;
        $this->water_depth = $this->port->water_depth;

        // Clasificación - con fallbacks seguros
        $this->port_type = $this->port->port_type ?? 'river';
        $this->port_category = $this->port->port_category ?? 'major';

        // Capacidades - casting seguro a boolean
        $this->handles_containers = (bool) ($this->port->handles_containers ?? true);
        $this->handles_bulk_cargo = (bool) ($this->port->handles_bulk_cargo ?? true);
        $this->handles_general_cargo = (bool) ($this->port->handles_general_cargo ?? true);
        $this->handles_passengers = (bool) ($this->port->handles_passengers ?? false);
        $this->handles_dangerous_goods = (bool) ($this->port->handles_dangerous_goods ?? false);
        $this->has_customs_office = (bool) ($this->port->has_customs_office ?? true);

        // Infraestructura - mantener null si no hay valor
        $this->max_vessel_length = $this->port->max_vessel_length;
        $this->max_draft = $this->port->max_draft;
        $this->berths_count = $this->port->berths_count;
        $this->cranes_count = $this->port->cranes_count;
        $this->warehouse_area = $this->port->warehouse_area;
        $this->open_storage_area = $this->port->open_storage_area;

        // Contacto
        $this->phone = $this->port->phone ?? '';
        $this->fax = $this->port->fax ?? '';
        $this->email = $this->port->email ?? '';
        $this->website = $this->port->website ?? '';
        $this->vhf_channel = $this->port->vhf_channel ?? '';

        // Códigos de aduana por país
        $this->afip_code = $this->port->afip_code ?? '';
        $this->dna_code = $this->port->dna_code ?? '';

        // Económico
        $this->currency_code = $this->port->currency_code ?? '';

        // Estado
        $this->active = (bool) ($this->port->active ?? true);
        $this->accepts_new_vessels = (bool) ($this->port->accepts_new_vessels ?? true);
        $this->display_order = (int) ($this->port->display_order ?? 999);
        
        // Fecha - manejo seguro
        $this->established_date = $this->port->established_date 
            ? $this->port->established_date->format('Y-m-d') 
            : '';
            
        $this->special_notes = $this->port->special_notes ?? '';
    }

    protected function saveOriginalValues()
    {
        $this->originalValues = [
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'local_name' => $this->local_name,
            'country_id' => $this->country_id,
            'city' => $this->city,
            'province_state' => $this->province_state,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'water_depth' => $this->water_depth,
            'port_type' => $this->port_type,
            'port_category' => $this->port_category,
            'handles_containers' => $this->handles_containers,
            'handles_bulk_cargo' => $this->handles_bulk_cargo,
            'handles_general_cargo' => $this->handles_general_cargo,
            'handles_passengers' => $this->handles_passengers,
            'handles_dangerous_goods' => $this->handles_dangerous_goods,
            'has_customs_office' => $this->has_customs_office,
            'max_vessel_length' => $this->max_vessel_length,
            'max_draft' => $this->max_draft,
            'berths_count' => $this->berths_count,
            'cranes_count' => $this->cranes_count,
            'warehouse_area' => $this->warehouse_area,
            'open_storage_area' => $this->open_storage_area,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'website' => $this->website,
            'vhf_channel' => $this->vhf_channel,
            'afip_code' => $this->afip_code,
            'dna_code' => $this->dna_code,
            'currency_code' => $this->currency_code,
            'active' => $this->active,
            'accepts_new_vessels' => $this->accepts_new_vessels,
            'display_order' => $this->display_order,
            'established_date' => $this->established_date,
            'special_notes' => $this->special_notes,
        ];
    }

    public function updated($propertyName)
    {
        // Detectar cambios comparando con valores originales
        $this->checkForChanges();
    }

    public function updatedCode()
    {
        $this->code = strtoupper($this->code);
        $this->checkForChanges();
    }

    public function generateShortName()
    {
        if (!empty($this->name)) {
            $words = explode(' ', $this->name);
            if (count($words) > 1) {
                $this->short_name = $words[0];
            } else {
                $this->short_name = Str::limit($this->name, 20, '');
            }
            $this->checkForChanges();
        }
    }

    public function generateCode()
    {
        if (!empty($this->city)) {
            $cityCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->city), 0, 5));
            $this->code = $cityCode;
            $this->checkForChanges();
        }
    }

    protected function checkForChanges()
    {
        $this->hasUnsavedChanges = false;
        
        // Lista de propiedades a comparar
        $propertiesToCheck = [
            'code', 'name', 'short_name', 'local_name', 'country_id', 'city', 
            'province_state', 'address', 'postal_code', 'latitude', 'longitude', 
            'water_depth', 'port_type', 'port_category', 'handles_containers',
            'handles_bulk_cargo', 'handles_general_cargo', 'handles_passengers',
            'handles_dangerous_goods', 'has_customs_office', 'max_vessel_length',
            'max_draft', 'berths_count', 'cranes_count', 'warehouse_area',
            'open_storage_area', 'phone', 'fax', 'email', 'website', 'vhf_channel',
            'afip_code', 'dna_code',
            'currency_code', 'active', 'accepts_new_vessels', 'display_order',
            'established_date', 'special_notes'
        ];

        foreach ($propertiesToCheck as $property) {
            if (isset($this->originalValues[$property]) && 
                property_exists($this, $property) && 
                $this->$property != $this->originalValues[$property]) {
                $this->hasUnsavedChanges = true;
                break;
            }
        }
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleAdvanced()
    {
        $this->showAdvanced = !$this->showAdvanced;
    }

    public function save()
    {
        // Validar código único (excluyendo el registro actual)
         $this->validate([
            'code' => 'required|string|max:10|unique:ports,code,' . $this->port->id,
            'name' => 'required|string|max:150',
            'country_id' => 'required|exists:countries,id',
            'city' => 'required|string|max:100',
            'port_type' => 'required|in:river,maritime,lake,canal,mixed',
            'port_category' => 'required|in:major,minor,terminal,anchorage,private',
        ]);

        // Validar el resto de campos
        $this->validate();

        try {
            // Preparar datos para actualizar
            $portData = [
                // Identificación básica
                'code' => strtoupper($this->code),
                'name' => $this->name,
                'short_name' => $this->short_name ?: null,
                'local_name' => $this->local_name ?: null,

                // Ubicación
                'country_id' => $this->country_id,
                'city' => $this->city,
                'province_state' => $this->province_state ?: null,
                'address' => $this->address ?: null,
                'postal_code' => $this->postal_code ?: null,

                // Coordenadas
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'water_depth' => $this->water_depth,

                // Clasificación
                'port_type' => $this->port_type,
                'port_category' => $this->port_category,

                // Capacidades
                'handles_containers' => $this->handles_containers,
                'handles_bulk_cargo' => $this->handles_bulk_cargo,
                'handles_general_cargo' => $this->handles_general_cargo,
                'handles_passengers' => $this->handles_passengers,
                'handles_dangerous_goods' => $this->handles_dangerous_goods,
                'has_customs_office' => $this->has_customs_office,

                // Infraestructura
                'max_vessel_length' => $this->max_vessel_length,
                'max_draft' => $this->max_draft,
                'berths_count' => $this->berths_count,
                'cranes_count' => $this->cranes_count,
                'warehouse_area' => $this->warehouse_area,
                'open_storage_area' => $this->open_storage_area,

                // Contacto
                'phone' => $this->phone ?: null,
                'fax' => $this->fax ?: null,
                'email' => $this->email ?: null,
                'website' => $this->website ?: null,
                'vhf_channel' => $this->vhf_channel ?: null,

                // Códigos de aduana
                'afip_code' => $this->afip_code ?: null,
                'dna_code' => $this->dna_code ?: null,

                // Económico
                'currency_code' => $this->currency_code ?: null,

                // Estado
                'active' => $this->active,
                'accepts_new_vessels' => $this->accepts_new_vessels,
                'display_order' => $this->display_order,
                'established_date' => $this->established_date ?: null,
                'special_notes' => $this->special_notes ?: null,
            ];

            // Actualizar el puerto
            $this->port->update($portData);

            // Actualizar valores originales
            $this->saveOriginalValues();
            $this->hasUnsavedChanges = false;

            // Mensaje de éxito
            $this->dispatch('toast', type: 'success', message: 'Puerto actualizado exitosamente.');

            // Redirigir al índice
            return redirect()->route('admin.ports.index');

        } catch (QueryException $e) {
            // Manejar errores de base de datos
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $this->addError('code', 'El código del puerto ya existe.');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al actualizar el puerto: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error inesperado: ' . $e->getMessage());
        }
    }

    public function cancel()
    {
        if ($this->hasUnsavedChanges) {
            $this->dispatch('confirm-discard-changes');
            return;
        }
        
        return redirect()->route('admin.ports.index');
    }

    public function confirmDiscard()
    {
        return redirect()->route('admin.ports.index');
    }

    public function resetChanges()
    {
        $this->loadPortData();
        $this->hasUnsavedChanges = false;
        $this->dispatch('toast', type: 'success', message: 'Cambios deshechos correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.ports.edit');
    }
}