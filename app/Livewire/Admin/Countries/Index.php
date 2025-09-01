<?php

namespace App\Livewire\Admin\Countries;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /** Buscador simple pero inteligente */
    public string $search = '';
    
    /** Opciones de paginación/orden conservadas por URL */
    #[Url] public int $perPage = 25;
    #[Url] public string $sortField = 'name';
    #[Url] public string $sortDirection = 'asc';

    /** ID para confirmar eliminación */
    public ?int $deletingId = null;

    protected string $paginationTheme = 'tailwind';

    // Reset de página al cambiar filtros
    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedPerPage(): void  { $this->resetPage(); }

    public function sortBy(string $field): void
    {
        $sortable = ['name','official_name','iso_code','alpha_code','numeric_code'];
        if (!in_array($field, $sortable, true)) return;

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    /**
     * Query con búsqueda inteligente SIMPLE
     */
    protected function baseQuery(): Builder
    {
        $query = Country::query();
        
        if (strlen(trim($this->search)) === 0) {
            return $query;
        }
        
        $searchTerm = trim($this->search);
        
        return $query->where(function (Builder $q) use ($searchTerm) {
            
            // Si es exactamente 2 o 3 letras mayúsculas, buscar códigos primero
            if (preg_match('/^[A-Z]{2,3}$/', $searchTerm)) {
                $q->where('iso_code', '=', $searchTerm)
                  ->orWhere('alpha_code', '=', $searchTerm);
            }
            
            // Si es solo números, buscar código numérico
            if (preg_match('/^\d+$/', $searchTerm)) {
                $q->orWhere('numeric_code', '=', $searchTerm);
            }
            
            // Búsqueda normal en texto (siempre)
            $likePattern = '%' . $searchTerm . '%';
            $q->orWhere('name', 'like', $likePattern)
              ->orWhere('official_name', 'like', $likePattern)
              ->orWhere('iso_code', 'like', $likePattern)
              ->orWhere('alpha_code', 'like', $likePattern)
              ->orWhere('numeric_code', 'like', $likePattern);
        });
    }

    public function getCountriesProperty()
    {
        $sortable = ['name','official_name','iso_code','alpha_code','numeric_code'];
        $field = in_array($this->sortField, $sortable, true) ? $this->sortField : 'name';

        return $this->baseQuery()
            ->orderBy($field, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->dispatch('open-delete-confirmation');
    }

    public function delete(): void
    {
        if (!$this->deletingId) return;

        try {
            $model = Country::find($this->deletingId);
            if ($model) {
                $model->delete();
                $this->dispatch('toast', type: 'success', message: 'País eliminado correctamente.');
            }
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'No se puede eliminar: el país está referenciado por otros registros.');
        } finally {
            $this->deletingId = null;
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.admin.countries.index', [
            'countries' => $this->countries,
        ]);
    }
}