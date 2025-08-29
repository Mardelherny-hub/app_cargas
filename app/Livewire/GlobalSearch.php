<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Debounce;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\Client;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use Illuminate\Support\Facades\Auth;

/**
 * GlobalSearch Livewire Component
 * 
 * Componente de búsqueda global que permite encontrar información
 * relacionada con importaciones recientes en toda la aplicación.
 * 
 * CAMPOS VERIFICADOS basados en migraciones reales:
 * - BillOfLading: bill_number, master_bill_number, internal_reference
 * - Container: container_number, full_container_number  
 * - Client: legal_name, commercial_name, tax_id
 * - ShipmentItem: item_description, cargo_marks, commodity_code
 * - Voyage: voyage_number, internal_reference
 */
class GlobalSearch extends Component
{
    public $query = '';
    public $showResults = false;
    public $selectedIndex = -1;
    
    // Límites de resultados por categoría
    private const RESULTS_LIMIT = 5;
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Actualizar búsqueda con debounce
     */
    #[Debounce(300)]
    public function updatedQuery()
    {
        $this->showResults = strlen(trim($this->query)) >= self::MIN_QUERY_LENGTH;
        $this->selectedIndex = -1;
    }

    /**
     * Ocultar resultados
     */
    public function hideResults()
    {
        $this->showResults = false;
        $this->selectedIndex = -1;
    }

    /**
     * Limpiar búsqueda
     */
    public function clearSearch()
    {
        $this->query = '';
        $this->showResults = false;
        $this->selectedIndex = -1;
    }

    /**
     * Navegar a un resultado específico
     */
    public function goToResult($type, $id)
    {
        $routes = [
            'bill' => 'company.bills-of-lading.show',
            'container' => 'company.containers.show',  
            'client' => 'company.clients.show',
            'item' => 'company.shipment-items.show',
            'voyage' => 'company.voyages.show'
        ];

        if (isset($routes[$type])) {
            $this->clearSearch();
            return redirect()->route($routes[$type], $id);
        }
    }

    /**
     * Computed property: Obtener resultados de búsqueda
     */
    public function getResultsProperty()
    {
        if (!$this->showResults || strlen(trim($this->query)) < self::MIN_QUERY_LENGTH) {
            return [
                'bills' => collect(),
                'containers' => collect(),
                'clients' => collect(),
                'items' => collect(),
                'voyages' => collect(),
                'total' => 0
            ];
        }

        $user = Auth::user();
        if (!$user || !$user->company) {
            return $this->getEmptyResults();
        }

        $companyId = $user->company->id;
        $searchTerm = '%' . trim($this->query) . '%';

        // Búsqueda en Bills of Lading
        $bills = BillOfLading::whereHas('shipment.voyage', function($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->where(function($query) use ($searchTerm) {
                $query->where('bill_number', 'like', $searchTerm)
                      ->orWhere('master_bill_number', 'like', $searchTerm)
                      ->orWhere('internal_reference', 'like', $searchTerm);
            })
            ->with(['shipper:id,legal_name', 'consignee:id,legal_name', 'shipment.voyage:id,voyage_number'])
            ->limit(self::RESULTS_LIMIT)
            ->get();

        // Búsqueda en Contenedores
        $containers = Container::whereHas('shipmentItems.billOfLading.shipment.voyage', function($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->where(function($query) use ($searchTerm) {
                $query->where('container_number', 'like', $searchTerm)
                      ->orWhere('full_container_number', 'like', $searchTerm);
            })
            ->with(['shipmentItems.billOfLading:id,bill_number'])
            ->limit(self::RESULTS_LIMIT)
            ->get();

        // Búsqueda en Clientes
        $clients = Client::where('status', 'active')
            ->where(function($query) use ($searchTerm) {
                $query->where('legal_name', 'like', $searchTerm)
                      ->orWhere('commercial_name', 'like', $searchTerm)
                      ->orWhere('tax_id', 'like', $searchTerm);
            })
            ->with(['country:id,name'])
            ->limit(self::RESULTS_LIMIT)
            ->get();

        // Búsqueda en Items de Carga
        $items = ShipmentItem::whereHas('billOfLading.shipment.voyage', function($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->where(function($query) use ($searchTerm) {
                $query->where('item_description', 'like', $searchTerm)
                      ->orWhere('cargo_marks', 'like', $searchTerm)
                      ->orWhere('commodity_code', 'like', $searchTerm);
            })
            ->with(['billOfLading:id,bill_number'])
            ->limit(self::RESULTS_LIMIT)
            ->get();

        // Búsqueda en Voyages
        $voyages = Voyage::where('company_id', $companyId)
            ->where(function($query) use ($searchTerm) {
                $query->where('voyage_number', 'like', $searchTerm)
                      ->orWhere('internal_reference', 'like', $searchTerm);
            })
            ->with(['originPort:id,name', 'destinationPort:id,name'])
            ->limit(self::RESULTS_LIMIT)
            ->get();

        $total = $bills->count() + $containers->count() + $clients->count() + 
                 $items->count() + $voyages->count();

        return [
            'bills' => $bills,
            'containers' => $containers,
            'clients' => $clients,
            'items' => $items,
            'voyages' => $voyages,
            'total' => $total
        ];
    }

    /**
     * Resultados vacíos
     */
    private function getEmptyResults()
    {
        return [
            'bills' => collect(),
            'containers' => collect(),
            'clients' => collect(),
            'items' => collect(),
            'voyages' => collect(),
            'total' => 0
        ];
    }

    /**
     * Destacar coincidencias en texto
     */
    public function highlightMatch($text, $query)
    {
        if (!$text || !$query) {
            return $text;
        }

        return preg_replace(
            '/(' . preg_quote($query, '/') . ')/i', 
            '<mark class="bg-yellow-200 text-yellow-800">$1</mark>', 
            $text
        );
    }

    /**
     * Render del componente
     */
    public function render()
    {
        return view('livewire.global-search');
    }
}