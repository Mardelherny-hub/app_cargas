<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    /**
     * Lista/búsqueda (la vista montará el componente Livewire).
     */
    public function index()
    {
        return view('admin.countries.index');
    }

    /**
     * Formulario de creación (la vista montará el componente Livewire del form).
     */
    public function create()
    {
        return view('admin.countries.create');
    }

    /**
     * Crear nuevo país.
     */
    public function store(Request $request)
    {
        // Validación
        $request->validate([
            'iso_code' => 'required|string|max:3|unique:countries,iso_code',
            'alpha2_code' => 'required|string|max:2|unique:countries,alpha2_code',
            'name' => 'required|string|max:100',
            'official_name' => 'nullable|string|max:150',
            'nationality' => 'nullable|string|max:50',
            'customs_code' => 'nullable|string|max:10',
            'senasa_code' => 'nullable|string|max:10',
            'document_format' => 'nullable|string|max:50',
            'currency_code' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'primary_language' => 'nullable|string|max:5',
            'display_order' => 'required|integer|min:0',
            'allows_import' => 'boolean',
            'allows_export' => 'boolean',
            'allows_transit' => 'boolean',
            'requires_visa' => 'boolean',
            'active' => 'boolean',
            'is_primary' => 'boolean',
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Preparar datos
                $data = [
                    'iso_code' => strtoupper($request->iso_code),
                    'alpha2_code' => strtoupper($request->alpha2_code),
                    'numeric_code' => $request->numeric_code,
                    'name' => $request->name,
                    'official_name' => $request->official_name,
                    'nationality' => $request->nationality,
                    'customs_code' => $request->customs_code,
                    'senasa_code' => $request->senasa_code,
                    'document_format' => $request->document_format,
                    'currency_code' => $request->currency_code ? strtoupper($request->currency_code) : null,
                    'timezone' => $request->timezone,
                    'primary_language' => $request->primary_language,
                    'allows_import' => $request->has('allows_import'),
                    'allows_export' => $request->has('allows_export'),
                    'allows_transit' => $request->has('allows_transit'),
                    'requires_visa' => $request->has('requires_visa'),
                    'active' => $request->has('active'),
                    'display_order' => $request->display_order,
                    'is_primary' => $request->has('is_primary'),
                ];

                // Agregar campos de auditoría si existen en fillable
                if (in_array('created_by_user_id', (new Country())->getFillable())) {
                    $data['created_by_user_id'] = Auth::id();
                }

                Country::create($data);
            });

            return redirect()
                ->route('admin.countries.index')
                ->with('success', 'País creado correctamente.');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error al crear el país: ' . $e->getMessage());
        }
    }

    /**
     * Detalle (opcional).
     */
    public function show(Country $country)
    {
        return view('admin.countries.show', compact('country'));
    }

    /**
     * Formulario de edición (la vista montará el componente Livewire del form).
     */
    public function edit(Country $country)
    {
        return view('admin.countries.edit', compact('country'));
    }

    /**
     * Actualizar país.
     */
    public function update(Request $request, Country $country)
    {
        // Validación (excluyendo el país actual para unique)
        $request->validate([
            'iso_code' => [
                'required', 'string', 'max:3',
                Rule::unique('countries')->ignore($country->id)
            ],
            'alpha2_code' => [
                'required', 'string', 'max:2',
                Rule::unique('countries')->ignore($country->id)
            ],
            'name' => 'required|string|max:100',
            'official_name' => 'nullable|string|max:150',
            'nationality' => 'nullable|string|max:50',
            'customs_code' => 'nullable|string|max:10',
            'senasa_code' => 'nullable|string|max:10',
            'document_format' => 'nullable|string|max:50',
            'currency_code' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'primary_language' => 'nullable|string|max:5',
            'display_order' => 'required|integer|min:0',
            'allows_import' => 'boolean',
            'allows_export' => 'boolean',
            'allows_transit' => 'boolean',
            'requires_visa' => 'boolean',
            'active' => 'boolean',
            'is_primary' => 'boolean',
        ]);

        try {
            DB::transaction(function () use ($request, $country) {
                // Preparar datos
                $data = [
                    'iso_code' => strtoupper($request->iso_code),
                    'alpha2_code' => strtoupper($request->alpha2_code),
                    'numeric_code' => $request->numeric_code,
                    'name' => $request->name,
                    'official_name' => $request->official_name,
                    'nationality' => $request->nationality,
                    'customs_code' => $request->customs_code,
                    'senasa_code' => $request->senasa_code,
                    'document_format' => $request->document_format,
                    'currency_code' => $request->currency_code ? strtoupper($request->currency_code) : null,
                    'timezone' => $request->timezone,
                    'primary_language' => $request->primary_language,
                    'allows_import' => $request->has('allows_import'),
                    'allows_export' => $request->has('allows_export'),
                    'allows_transit' => $request->has('allows_transit'),
                    'requires_visa' => $request->has('requires_visa'),
                    'active' => $request->has('active'),
                    'display_order' => $request->display_order,
                    'is_primary' => $request->has('is_primary'),
                ];

                $country->update($data);
            });

            return redirect()
                ->route('admin.countries.index')
                ->with('success', 'País actualizado correctamente.');

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error al actualizar el país: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar país.
     */
    public function destroy(Country $country)
    {
        try {
            // Verificar si tiene dependencias
            $hasRelations = $country->ports()->exists() 
                || $country->CustomOffices()->exists() 
                || $country->companies()->exists()
                || $country->clients()->exists();

            if ($hasRelations) {
                return redirect()
                    ->route('admin.countries.index')
                    ->with('error', 'No se puede eliminar el país porque tiene puertos, aduanas, empresas o clientes asociados.');
            }

            DB::transaction(function () use ($country) {
                $country->delete();
            });

            return redirect()
                ->route('admin.countries.index')
                ->with('success', 'País eliminado correctamente.');

        } catch (\Exception $e) {
            return redirect()
                ->route('admin.countries.index')
                ->with('error', 'Error al eliminar el país: ' . $e->getMessage());
        }
    }
}