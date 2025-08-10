<?php

namespace App\Livewire\Company;

use App\Models\Captain;
use App\Models\Country;
use App\Traits\UserHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Captains extends Component
{
    use WithPagination, UserHelper;

    // Propiedades del componente
    public $search = '';
    public $showModal = false;
    public $modalMode = 'create'; // create, edit, show
    public $selectedCaptain = null;

    // Campos del formulario (basados en migración real)
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $license_number = '';
    public $license_class = 'officer';
    public $country_id = '';
    public $employment_status = 'employed';
    public $years_of_experience = 0;
    public $active = true;

    // Datos para selects
    public $countries = [];
    public $licenseClasses = [
        'master' => 'Capitán',
        'chief_officer' => 'Primer Oficial', 
        'officer' => 'Oficial',
        'pilot' => 'Piloto'
    ];
    public $employmentStatuses = [
        'employed' => 'Empleado',
        'freelance' => 'Freelance',
        'contract' => 'Contratista',
        'retired' => 'Retirado'
    ];

    protected $rules = [
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:20',
        'license_number' => 'required|string|max:50',
        'license_class' => 'required|in:master,chief_officer,officer,pilot',
        'country_id' => 'required|exists:countries,id',
        'employment_status' => 'required|in:employed,freelance,contract,retired',
        'years_of_experience' => 'nullable|integer|min:0|max:50',
        'active' => 'boolean'
    ];

    public function mount($countries)
    {
        $this->countries = $countries;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getCaptainsProperty()
    {
        $company = $this->getUserCompany();
        
        return Captain::where(function($query) use ($company) {
            $query->where('primary_company_id', $company->id)
                  ->orWhereHas('voyages', function($q) use ($company) {
                      $q->where('company_id', $company->id);
                  });
        })
        ->when($this->search, function($query) {
            $query->where(function($q) {
                $q->where('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('license_number', 'like', '%' . $this->search . '%');
            });
        })
        ->with(['country', 'primaryCompany'])
        ->orderBy('full_name')
        ->paginate(15);
    }

    public function create()
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function edit($captainId)
    {
        $captain = Captain::findOrFail($captainId);
        $this->selectedCaptain = $captain;
        $this->fillForm($captain);
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function show($captainId)
    {
        $this->selectedCaptain = Captain::with(['country', 'primaryCompany'])->findOrFail($captainId);
        $this->modalMode = 'show';
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $company = $this->getUserCompany();
        
        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'email' => $this->email,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'license_class' => $this->license_class,
            'country_id' => $this->country_id,
            'employment_status' => $this->employment_status,
            'years_of_experience' => $this->years_of_experience,
            'active' => $this->active,
            'primary_company_id' => $company->id,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now()
        ];

        if ($this->modalMode === 'create') {
            $data['created_by_user_id'] = Auth::id();
            $data['created_date'] = now();
            Captain::create($data);
            session()->flash('message', 'Capitán creado exitosamente.');
        } else {
            $this->selectedCaptain->update($data);
            session()->flash('message', 'Capitán actualizado exitosamente.');
        }

        $this->closeModal();
    }

    public function delete($captainId)
    {
        $captain = Captain::findOrFail($captainId);
        
        // Verificar viajes activos
        $activeVoyages = $captain->voyages()
            ->whereIn('status', ['planning', 'loading', 'loaded', 'in_transit'])
            ->count();

        if ($activeVoyages > 0) {
            session()->flash('error', 'No se puede eliminar el capitán porque tiene viajes activos.');
            return;
        }

        // Soft delete
        $captain->update([
            'active' => false,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now()
        ]);

        session()->flash('message', 'Capitán desactivado exitosamente.');
    }

    public function toggleStatus($captainId)
    {
        $captain = Captain::findOrFail($captainId);
        $captain->update([
            'active' => !$captain->active,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_date' => now()
        ]);

        $status = $captain->active ? 'activado' : 'desactivado';
        session()->flash('message', "Capitán {$status} exitosamente.");
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->first_name = '';
        $this->last_name = '';
        $this->email = '';
        $this->phone = '';
        $this->license_number = '';
        $this->license_class = 'officer';
        $this->country_id = '';
        $this->employment_status = 'employed';
        $this->years_of_experience = 0;
        $this->active = true;
        $this->selectedCaptain = null;
        $this->resetErrorBag();
    }

    private function fillForm($captain)
    {
        $this->first_name = $captain->first_name;
        $this->last_name = $captain->last_name;
        $this->email = $captain->email;
        $this->phone = $captain->phone;
        $this->license_number = $captain->license_number;
        $this->license_class = $captain->license_class;
        $this->country_id = $captain->country_id;
        $this->employment_status = $captain->employment_status;
        $this->years_of_experience = $captain->years_of_experience ?? 0;
        $this->active = $captain->active;
    }

    public function render()
    {
        return view('livewire.company.captains', [
            'captains' => $this->captains
        ]);
    }
}