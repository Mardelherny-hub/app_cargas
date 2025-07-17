<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CONTROLADOR SIMPLIFICADO PARA TESTING
 * Una vez que funcione, reemplazar con el controlador completo
 */
class ClientController extends Controller
{
    /**
     * Listar clientes - VERSIÓN SIMPLIFICADA
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Super admin puede ver todos los clientes
        if ($user->hasRole('super-admin')) {
            $clients = Client::with(['country', 'companyRelations.company'])
                ->orderBy('updated_at', 'desc')
                ->paginate(25);
        } else {
            // Otros usuarios ven solo sus clientes accesibles
            $clients = collect(); // Vacío por ahora
        }

        // Datos auxiliares
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();
        
        // Estadísticas básicas
        $stats = [
            'total' => Client::count(),
            'verified' => Client::whereNotNull('verified_at')->count(),
            'pending' => Client::whereNull('verified_at')->count(),
            'inactive' => Client::where('status', 'inactive')->count(),
        ];

        return view('admin.clients.index', compact('clients', 'countries', 'companies', 'stats'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();

        return view('admin.clients.create', compact('countries', 'companies'));
    }

    /**
     * Crear nuevo cliente - VERSIÓN SIMPLIFICADA
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'tax_id' => 'required|string|max:11',
            'legal_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'client_type' => 'required|in:shipper,consignee,notify_party,owner',
            'status' => 'sometimes|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $client = Client::create($validatedData);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Mostrar cliente específico
     */
    public function show(Client $client)
    {
        $client->load(['country', 'documentType', 'companyRelations.company']);

        return view('admin.clients.show', compact('client'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Client $client)
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customsOffices = CustomOffice::where('active', true)->orderBy('name')->get();
        $companies = Company::where('active', true)->orderBy('legal_name')->get();

        return view('admin.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customsOffices', 'companies'));
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Client $client)
    {
        $validatedData = $request->validate([
            'tax_id' => 'required|string|max:11',
            'legal_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'client_type' => 'required|in:shipper,consignee,notify_party,owner',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:1000',
        ]);

        $client->update($validatedData);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * Eliminar cliente
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Cliente eliminado exitosamente');
    }

    /**
     * Métodos temporales para acciones específicas
     */
    public function verify(Client $client)
    {
        $client->update(['verified_at' => now()]);
        return back()->with('success', 'Cliente verificado exitosamente');
    }

    public function toggleStatus(Client $client)
    {
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);
        return back()->with('success', 'Estado del cliente actualizado');
    }

    public function transfer(Client $client)
    {
        return back()->with('info', 'Funcionalidad de transferencia en desarrollo');
    }

    public function bulkImport(Request $request)
    {
        return back()->with('info', 'Funcionalidad de importación masiva en desarrollo');
    }
}