<?php

namespace App\Livewire\Admin\Ports;

use App\Models\Port;
use App\Models\Country;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Validate;

class Create extends Component
{
    // Campos básicos de identificación
    #[Validate('required|string|max:10|unique:ports,code')]
    public string $code = '';

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('nullable|string|max:50')]
    public string $short_name = '';

    #[Validate('nullable|string|max:150')]
    public string $local_name = '';

    // Información de ubicación
    #[Validate('required|exists:countries,id')]
    public ?int $country_id = null;

    #[Validate('required|string|max:100')]
    public string $city = '';

    #[Validate('nullable|string|max:100')]
    public string $province_state = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:20')]
    public string $postal_code = '';

    // Coordenadas geográficas
    #[Validate('nullable|numeric|between:-90,90')]
    public ?float $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?float $longitude = null;

    #[Validate('nullable|numeric|min:0')]
    public ?float $water_depth = null;

    // Clasificación del puerto
    #[Validate('required|in:river,maritime,lake,canal,mixed')]
    public string $port_type = 'river';

    #[Validate('required|in:major,minor,terminal,anchorage,private')]
    public string $port_category = 'major';

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
    public string $phone = '';

    #[Validate('nullable|string|max:20')]
    public string $fax = '';

    #[Validate('nullable|email|max:100')]
    public string $email = '';

    #[Validate('nullable|url|max:255')]
    public string $website = '';

    #[Validate('nullable|string|max:10')]
    public string $vhf_channel = '';

    // Información económica
    #[Validate('nullable|string|max:3')]
    public string $currency_code = '';

    // Estado y configuración
    public bool $active = true;
    public bool $accepts_new_vessels = true;

    #[Validate('nullable|integer|min:0')]
    public int $display_order = 999;

    #[Validate('nullable|date')]
    public ?string $established_date = null;

    #[Validate('nullable|string|max:1000')]
    public string $special_notes = '';

    // Datos adicionales para la vista
    public array $countries = [];
    public array $portTypes = [];
    public array $portCategories = [];

    // Estados del componente
    public bool $showAdvanced = false;
    public string $activeTab = 'basic';

    public function mount()
    {
        // Cargar países ordenados
        $this->countries = Country::orderBy('name')->get(['id', 'name'])->toArray();

        // Definir opciones
        $this->portTypes = [
            'river' => 'Fluvial',
            'maritime' => 'Marítimo',
            'lake' => 'Lacustre',
            'canal' => 'Canal',
            'mixed' => 'Mixto'
        ];

        $this->portCategories = [
            'major' => 'Principal',
            'minor' => 'Menor',
            'terminal' => 'Terminal',
            'anchorage' => 'Fondeadero',
            'private' => 'Privado'
        ];
    }

    public function updatedCode()
    {
        $this->code = strtoupper($this->code);
    }

    public function generateShortName()
    {
        if (!empty($this->name)) {
            // Generar nombre corto automáticamente
            $words = explode(' ', $this->name);
            if (count($words) > 1) {
                $this->short_name = $words[0];
            } else {
                $this->short_name = Str::limit($this->name, 20, '');
            }
        }
    }

    public function generateCode()
    {
        if (!empty($this->city)) {
            // Generar código basado en la ciudad
            $cityCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->city), 0, 5));
            $this->code = $cityCode;
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
        // Validar todos los campos
        $this->validate();

        try {
            // Preparar datos para crear
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

                // Económico
                'currency_code' => $this->currency_code ?: null,

                // Estado
                'active' => $this->active,
                'accepts_new_vessels' => $this->accepts_new_vessels,
                'display_order' => $this->display_order,
                'established_date' => $this->established_date ?: null,
                'special_notes' => $this->special_notes ?: null,
            ];

            // Crear el puerto
            $port = Port::create($portData);

            // Mensaje de éxito
            $this->dispatch('toast', type: 'success', message: 'Puerto creado exitosamente.');

            // Redirigir al índice
            return redirect()->route('admin.ports.index');

        } catch (QueryException $e) {
            // Manejar errores de base de datos
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $this->addError('code', 'El código del puerto ya existe.');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al crear el puerto: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error inesperado: ' . $e->getMessage());
        }
    }

    public function cancel()
    {
        return redirect()->route('admin.ports.index');
    }

    public function render()
    {
        return view('livewire.admin.ports.create');
    }
}