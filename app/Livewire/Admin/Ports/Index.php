<?php

namespace App\Livewire\Admin\Ports;

use App\Models\Port;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    #[Url]
    public ?int $countryId = null;

    #[Url]
    public ?string $portType = null;

    #[Url]
    public ?string $searchType = 'smart'; // smart, exact, code

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    /** ID seleccionado para confirmar eliminación */
    public ?int $deletingId = null;

    // Cache para evitar consultas repetidas
    protected array $searchCache = [];

    // Reset paginación cuando cambian los filtros
    public function updatedSearch(): void     
    { 
        $this->resetPage(); 
        $this->clearSearchCache(); 
    }
    
    public function updatedCountryId(): void  
    { 
        $this->resetPage(); 
    }
    
    public function updatedPortType(): void   
    { 
        $this->resetPage(); 
    }
    
    public function updatedSearchType(): void 
    { 
        $this->resetPage(); 
        $this->clearSearchCache(); 
    }
    
    public function updatedPerPage(): void    
    { 
        $this->resetPage(); 
    }

    private function clearSearchCache(): void
    {
        $this->searchCache = [];
    }

    public function sortBy(string $field): void
    {
        $sortable = ['name', 'short_name', 'city', 'code', 'port_type', 'country_name'];
        if (! in_array($field, $sortable, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    /**
     * Búsqueda inteligente mejorada
     * Detecta automáticamente el tipo de búsqueda basado en el input
     */
    protected function baseQuery(): Builder
    {
        $query = Port::query()->with('country:id,name');
        
        // Si no hay búsqueda, aplicar solo filtros
        if (empty(trim($this->search))) {
            return $this->applyFilters($query);
        }

        $searchTerm = trim($this->search);
        
        // Aplicar búsqueda según tipo
        switch ($this->searchType) {
            case 'exact':
                $query = $this->applyExactSearch($query, $searchTerm);
                break;
            case 'code':
                $query = $this->applyCodeSearch($query, $searchTerm);
                break;
            case 'smart':
            default:
                $query = $this->applySmartSearch($query, $searchTerm);
                break;
        }

        return $this->applyFilters($query);
    }

    /**
     * Búsqueda inteligente - detecta automáticamente el patrón
     */
    private function applySmartSearch(Builder $query, string $term): Builder
    {
        // Detectar si es un código (solo letras/números, 3-8 caracteres)
        if (preg_match('/^[A-Za-z0-9]{2,8}$/', $term)) {
            return $this->applyCodeSearch($query, $term);
        }

        // Detectar si es coordenadas (contiene números con punto/coma)
        if (preg_match('/^-?\d+\.?\d*[,\s]+-?\d+\.?\d*$/', $term)) {
            return $this->applyCoordinateSearch($query, $term);
        }

        // Detectar si es búsqueda por país (empieza con mayúscula, más de 4 caracteres)
        if (preg_match('/^[A-Z][a-z]{3,}/', $term)) {
            return $this->applyCountryPrioritySearch($query, $term);
        }

        // Búsqueda general inteligente
        return $this->applyGeneralSearch($query, $term);
    }

    /**
     * Búsqueda por código optimizada
     */
    private function applyCodeSearch(Builder $query, string $term): Builder
    {
        $termUpper = strtoupper($term);
        
        return $query->where(function (Builder $q) use ($term, $termUpper) {
            // Prioridad 1: Código exacto
            $q->where('code', '=', $termUpper)
              // Prioridad 2: Código que empiece con el término  
              ->orWhere('code', 'like', $termUpper . '%')
              // Prioridad 3: Short name exacto
              ->orWhere('short_name', '=', $termUpper)
              // Prioridad 4: Short name que empiece
              ->orWhere('short_name', 'like', $termUpper . '%')
              // Prioridad 5: Código que contenga
              ->orWhere('code', 'like', '%' . $termUpper . '%');
        });
    }

    /**
     * Búsqueda por coordenadas
     */
    private function applyCoordinateSearch(Builder $query, string $term): Builder
    {
        // Extraer coordenadas del término
        preg_match('/(-?\d+\.?\d*)[,\s]+(-?\d+\.?\d*)/', $term, $matches);
        
        if (count($matches) >= 3) {
            $lat = floatval($matches[1]);
            $lng = floatval($matches[2]);
            
            // Búsqueda aproximada (radio de ~1 grado)
            return $query->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereRaw('ABS(CAST(latitude AS DECIMAL(10,6)) - ?) < 1', [$lat])
                        ->whereRaw('ABS(CAST(longitude AS DECIMAL(10,6)) - ?) < 1', [$lng]);
        }
        
        return $query->whereRaw('1 = 0'); // Sin resultados si no se pueden parsear
    }

    /**
     * Búsqueda con prioridad en países
     */
    private function applyCountryPrioritySearch(Builder $query, string $term): Builder
    {
        $escapedTerm = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        
        return $query->leftJoin('countries', 'ports.country_id', '=', 'countries.id')
                    ->where(function (Builder $q) use ($escapedTerm) {
                        // Prioridad 1: Nombre del país
                        $q->where('countries.name', 'like', $escapedTerm)
                          // Prioridad 2: Nombre del puerto
                          ->orWhere('ports.name', 'like', $escapedTerm)
                          // Prioridad 3: Ciudad
                          ->orWhere('ports.city', 'like', $escapedTerm)
                          // Prioridad 4: Provincia/Estado
                          ->orWhere('ports.province_state', 'like', $escapedTerm);
                    })->select('ports.*');
    }

    /**
     * Búsqueda general optimizada
     */
    private function applyGeneralSearch(Builder $query, string $term): Builder
    {
        $escapedTerm = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        
        return $query->where(function (Builder $q) use ($escapedTerm, $term) {
            // Búsqueda en campos principales que sí existen
            $q->where('name', 'like', $escapedTerm)
              ->orWhere('short_name', 'like', $escapedTerm)
              ->orWhere('local_name', 'like', $escapedTerm)
              ->orWhere('city', 'like', $escapedTerm)
              ->orWhere('province_state', 'like', $escapedTerm)
              ->orWhere('code', 'like', $escapedTerm)
              // Búsqueda en campos adicionales que existen
              ->orWhere('address', 'like', $escapedTerm)
              ->orWhere('postal_code', 'like', $escapedTerm);
        });
    }

    /**
     * Búsqueda exacta
     */
    private function applyExactSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', '=', $term)
              ->orWhere('short_name', '=', $term)
              ->orWhere('code', '=', strtoupper($term))
              ->orWhere('city', '=', $term);
        });
    }

    /**
     * Aplicar filtros adicionales
     */
    private function applyFilters(Builder $query): Builder
    {
        return $query->when($this->countryId, 
                fn (Builder $q) => $q->where('country_id', $this->countryId))
                    ->when($this->portType, 
                fn (Builder $q) => $q->where('port_type', $this->portType));
    }

    /**
     * Propiedad computada para los puertos con ordenamiento inteligente
     */
    public function getPortsProperty()
    {
        $sortable = ['name', 'short_name', 'city', 'code', 'port_type'];
        $field = in_array($this->sortField, $sortable, true) ? $this->sortField : 'name';

        $query = $this->baseQuery();

        // Ordenamiento inteligente basado en la búsqueda
        if (!empty(trim($this->search)) && $this->searchType === 'smart') {
            $query = $this->applyIntelligentOrdering($query, trim($this->search));
        } else {
            $query->orderBy($field, $this->sortDirection);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Ordenamiento inteligente basado en relevancia
     */
    private function applyIntelligentOrdering(Builder $query, string $term): Builder
    {
        $termUpper = strtoupper($term);
        $escapedTerm = str_replace(['%', '_'], ['\%', '\_'], $term);
        
        return $query->selectRaw('ports.*, 
            CASE 
                WHEN UPPER(code) = ? THEN 1
                WHEN UPPER(short_name) = ? THEN 2  
                WHEN UPPER(name) = ? THEN 3
                WHEN UPPER(code) LIKE ? THEN 4
                WHEN UPPER(name) LIKE ? THEN 5
                WHEN UPPER(city) LIKE ? THEN 6
                ELSE 7
            END as relevance_score', 
            [
                $termUpper, $termUpper, strtoupper($escapedTerm),
                $termUpper . '%', strtoupper($escapedTerm) . '%', 
                strtoupper($escapedTerm) . '%'
            ])
            ->orderBy('relevance_score')
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->dispatch('open-delete-confirmation');
    }

    #[On('delete-confirmed')]
    public function delete(): void
    {
        if (!$this->deletingId) return;

        try {
            $port = Port::find($this->deletingId);
            if ($port) {
                $port->delete();
                $this->dispatch('toast', type: 'success', message: 'Puerto eliminado correctamente.');
            }
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el puerto está referenciado.');
        } finally {
            $this->deletingId = null;
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.admin.ports.index', [
            'ports'     => $this->ports,
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'portTypes' => Port::query()
                ->select('port_type')
                ->whereNotNull('port_type')
                ->distinct()
                ->orderBy('port_type')
                ->pluck('port_type'),
        ]);
    }
}