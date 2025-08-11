<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Client;

class SearchClient extends Component
{
    public $searchClient = '';
    public $selectedClientId = '';
    public $selectedClientName = '';
    public $fieldName = 'client_id';
    public $required = false;
    public $placeholder = 'Escriba nombre o CUIT del cliente...';

    public function mount($selectedClientId = null, $fieldName = 'client_id', $required = false, $placeholder = null)
    {
        $this->selectedClientId = $selectedClientId;
        $this->fieldName = $fieldName;
        $this->required = $required;
        if ($placeholder) {
            $this->placeholder = $placeholder;
        }
        
        if ($selectedClientId) {
            $client = Client::find($selectedClientId);
            if ($client) {
                $this->selectedClientName = $client->legal_name;
            }
        }
    }

    public function getFilteredClientsProperty()
    {
        if (strlen($this->searchClient) < 2) {
            return collect();
        }
        
        return Client::where('status', 'active')
            ->where(function($query) {
                $query->where('legal_name', 'like', '%' . $this->searchClient . '%')
                      ->orWhere('commercial_name', 'like', '%' . $this->searchClient . '%')
                      ->orWhere('tax_id', 'like', '%' . $this->searchClient . '%');
            })
            ->limit(20)
            ->get();
    }

    public function selectClient($clientId)
    {
        $client = Client::find($clientId);
        if ($client) {
            $this->selectedClientId = $client->id;
            $this->selectedClientName = $client->legal_name;
            $this->searchClient = '';
            
            // Emitir evento para que el componente padre sepa del cambio
            $this->dispatch('clientSelected', [
                'clientId' => $client->id,
                'clientName' => $client->legal_name,
                'fieldName' => $this->fieldName
            ]);
        }
    }

    public function clearSelectedClient()
    {
        $this->selectedClientId = '';
        $this->selectedClientName = '';
        $this->searchClient = '';
        
        $this->dispatch('clientCleared', ['fieldName' => $this->fieldName]);
    }

    public function render()
    {
        return view('livewire.search-client');
    }
}