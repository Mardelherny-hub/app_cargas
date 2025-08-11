<?php

namespace App\Livewire\Company\Captains;

use App\Models\Captain;
use App\Models\Country;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * MÓDULO CAPITANES - Componente Livewire CaptainsTable
 * 
 * Componente para gestión completa de capitanes en el ámbito de empresa.
 * Maneja listado, filtros, búsqueda, ordenamiento y acciones CRUD.
 * 
 * CAMPOS BASADOS EN create_captains_table.php:
 * - Información personal: first_name, last_name, full_name, birth_date, gender, nationality
 * - Contacto: email, phone, mobile_phone, address  
 * - Licencia: license_number, license_class, license_issued_at, license_expires_at, license_country_id
 * - Profesional: years_of_experience, employment_status, daily_rate, rate_currency
 * - Relaciones: country_id, primary_company_id
 * - Estado: active, verified
 */
class CaptainsTable extends Component
{
    use WithPagination;
    use UserHelper;

    // Propiedades recibidas desde la vista blade
    public $countries;
    public $companies;
    public $filterOptions;

    // Filtros y búsqueda
    public $search = '';
    public $countryFilter = '';
    public $employmentStatusFilter = '';
    public $licenseClassFilter = '';
    public $activeFilter = '';
    public $experienceFilter = '';

    // Ordenamiento
    public $sortBy = 'full_name';
    public $sortDirection = 'asc';

    // Paginación
    public $perPage = 15;

    // Estados del componente
    public $selectedCaptains = [];
    public $selectAll = false;
    public $showingStats = true;

    // Modales y acciones
    public $showDeleteModal = false;
    public $captainToDelete = null;
    public $showBulkActions = false;

    // Listeners para eventos Livewire
    protected $listeners = [
        'loadStats' => 'emitStats',
        'captainUpdated' => 'resetPage',
        'captainDeleted' => 'resetPage'
    ];

    // Reglas de validación para filtros
    protected $rules = [
        'search' => 'nullable|string|max:100',
        'countryFilter' => 'nullable|exists:countries,id',
        'employmentStatusFilter' => 'nullable|in:employed,freelance,contract,retired',
        'licenseClassFilter' => 'nullable|in:master,chief_officer,officer,pilot',
        'activeFilter' => 'nullable|in:active,inactive',
        'perPage' => 'integer|min:5|max:100'
    ];

    /**
     * Inicializar componente
     */
    public function mount($countries, $companies, $filterOptions)
    {
        $this->countries = $countries;
        $this->companies = $companies;
        $this->filterOptions = $filterOptions;

        // Emitir estadísticas iniciales
        $this->emitStats();
    }

    /**
     * Actualizar consulta cuando cambian los filtros
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCountryFilter()
    {
        $this->resetPage();
    }

    public function updatedEmploymentStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedLicenseClassFilter()
    {
        $this->resetPage();
    }

    public function updatedActiveFilter()
    {
        $this->resetPage();
    }

    /**
     * Limpiar todos los filtros
     */
    public function clearFilters()
    {
        $this->search = '';
        $this->countryFilter = '';
        $this->employmentStatusFilter = '';
        $this->licenseClassFilter = '';
        $this->activeFilter = '';
        $this->experienceFilter = '';
        $this->selectedCaptains = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /**
     * Cambiar ordenamiento
     */
    public function sortBy($field)
    {
        $allowedFields = [
            'full_name', 'license_number', 'license_class', 
            'years_of_experience', 'employment_status', 'created_date'
        ];

        if (in_array($field, $allowedFields)) {
            if ($this->sortBy === $field) {
                $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                $this->sortBy = $field;
                $this->sortDirection = 'asc';
            }
            $this->resetPage();
        }
    }

    /**
     * Obtener consulta de capitanes con filtros aplicados
     */
    public function getCaptainsProperty()
    {
        $company = $this->getUserCompany();
        if (!$company) {
            return collect();
        }

        $query = Captain::query()
            ->with([
                'country:id,name,alpha2_code',
                'licenseCountry:id,name,alpha2_code', 
                'primaryCompany:id,legal_name'
            ])
            ->where(function($q) use ($company) {
                // Capitanes relacionados con la empresa
                $q->where('primary_company_id', $company->id)
                  ->orWhereHas('voyages', function($subQ) use ($company) {
                      $subQ->where('company_id', $company->id);
                  });
            });

        // Aplicar filtros
        if ($this->search) {
            $query->where(function($q) {
                $q->where('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('license_number', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->countryFilter) {
            $query->where('country_id', $this->countryFilter);
        }

        if ($this->employmentStatusFilter) {
            $query->where('employment_status', $this->employmentStatusFilter);
        }

        if ($this->licenseClassFilter) {
            $query->where('license_class', $this->licenseClassFilter);
        }

        if ($this->activeFilter === 'active') {
            $query->where('active', true);
        } elseif ($this->activeFilter === 'inactive') {
            $query->where('active', false);
        }

        // Aplicar ordenamiento
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    /**
     * Seleccionar/deseleccionar todos los capitanes
     */
    public function updatedSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedCaptains = $this->captains->pluck('id')->toArray();
        } else {
            $this->selectedCaptains = [];
        }
    }

    /**
     * Actualizar selección individual
     */
    public function updatedSelectedCaptains()
    {
        $this->selectAll = count($this->selectedCaptains) === $this->captains->count();
    }

    /**
     * Confirmar eliminación de capitán
     */
    public function confirmDelete($captainId)
    {
        // Verificar permisos
        if (!$this->isCompanyAdmin()) {
            $this->dispatch('show-alert', [
                'type' => 'error',
                'message' => 'No tiene permisos para eliminar capitanes.'
            ]);
            return;
        }

        $captain = Captain::find($captainId);
        if (!$captain) {
            $this->dispatch('show-alert', [
                'type' => 'error', 
                'message' => 'Capitán no encontrado.'
            ]);
            return;
        }

        $this->captainToDelete = $captain;
        $this->showDeleteModal = true;
    }

    /**
     * Eliminar capitán
     */
    public function deleteCaptain()
    {
        if (!$this->captainToDelete || !$this->isCompanyAdmin()) {
            $this->showDeleteModal = false;
            return;
        }

        try {
            // Verificar si el capitán tiene viajes activos
            $activeVoyages = $this->captainToDelete->voyages()
                ->whereIn('status', ['planning', 'loading', 'loaded', 'in_transit'])
                ->count();

            if ($activeVoyages > 0) {
                $this->dispatch('show-alert', [
                    'type' => 'error',
                    'message' => 'No se puede eliminar el capitán porque tiene viajes activos.'
                ]);
                $this->showDeleteModal = false;
                return;
            }

            // Soft delete para mantener referencia histórica
            $this->captainToDelete->update([
                'active' => false,
                'last_updated_by_user_id' => Auth::id(),
                'last_updated_date' => now()
            ]);

            $this->dispatch('show-alert', [
                'type' => 'success',
                'message' => 'Capitán desactivado correctamente.'
            ]);

            $this->emit('captainDeleted');
            $this->emitStats();

        } catch (\Exception $e) {
            $this->dispatch('show-alert', [
                'type' => 'error',
                'message' => 'Error al eliminar el capitán: ' . $e->getMessage()
            ]);
        }

        $this->showDeleteModal = false;
        $this->captainToDelete = null;
    }

    /**
     * Cancelar eliminación
     */
    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->captainToDelete = null;
    }

    /**
     * Activar/desactivar capitán
     */
    public function toggleStatus($captainId)
    {
        if (!$this->isCompanyAdmin()) {
            $this->dispatch('show-alert', [
                'type' => 'error',
                'message' => 'No tiene permisos para cambiar el estado de capitanes.'
            ]);
            return;
        }

        $captain = Captain::find($captainId);
        if (!$captain) {
            return;
        }

        $captain->update([
            'active' => !$captain->active,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now()
        ]);

        $status = $captain->active ? 'activado' : 'desactivado';
        $this->dispatch('show-alert', [
            'type' => 'success',
            'message' => "Capitán {$status} correctamente."
        ]);

        $this->emitStats();
    }

    /**
     * Calcular y emitir estadísticas
     */
    public function emitStats()
    {
        $company = $this->getUserCompany();
        if (!$company) {
            return;
        }

        // Estadísticas básicas
        $allCaptains = Captain::where(function($q) use ($company) {
            $q->where('primary_company_id', $company->id)
              ->orWhereHas('voyages', function($subQ) use ($company) {
                  $subQ->where('company_id', $company->id);
              });
        });

        $stats = [
            'total' => $allCaptains->count(),
            'active' => $allCaptains->where('active', true)->count(),
            'masters' => $allCaptains->where('license_class', 'master')->count(),
            'avgExperience' => round($allCaptains->avg('years_of_experience') ?? 0, 1),
            'monthlyVoyages' => $company->voyages()
                ->whereMonth('departure_date', now()->month)
                ->whereYear('departure_date', now()->year)
                ->count(),
            'activeVoyageCaptains' => $allCaptains->whereHas('voyages', function($q) {
                $q->whereIn('status', ['loading', 'loaded', 'in_transit']);
            })->count(),
            'avgRating' => round($allCaptains->avg('performance_rating') ?? 0, 1),
            'safetyIncidents' => $allCaptains->sum('safety_incidents') ?? 0
        ];

        $this->dispatch('statsUpdated', $stats);
    }

    /**
     * Exportar capitanes seleccionados
     */
    public function exportSelected()
    {
        if (empty($this->selectedCaptains)) {
            $this->dispatch('show-alert', [
                'type' => 'warning',
                'message' => 'Seleccione al menos un capitán para exportar.'
            ]);
            return;
        }

        // Redirigir a la ruta de exportación con IDs seleccionados
        return redirect()->route('company.captains.export', [
            'ids' => implode(',', $this->selectedCaptains)
        ]);
    }

    /**
     * Renderizar componente
     */
    public function render()
    {
        return view('livewire.company.captains.captains-table', [
            'captains' => $this->captains
        ]);
    }
}