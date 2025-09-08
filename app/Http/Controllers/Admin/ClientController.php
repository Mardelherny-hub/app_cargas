<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\Company;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\BulkClientImportRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Exception;

/**
 * FASE 6 - ELIMINACIÓN DE ROLES DE CLIENTES
 *
 * Controlador Admin para gestión completa de clientes
 * CORRECCIÓN: Eliminados todos los roles de clientes - simplificado
 */
class ClientController extends Controller
{
    use AuthorizesRequests;
    /**
     * Listar clientes con filtros y búsqueda.
     */
    public function index(Request $request)
    {
        $query = Client::with([
            'country:id,name,alpha2_code',
            'documentType:id,name',
            'primaryPort:id,name',
            'customsOffice:id,name',
            'createdByCompany:id,legal_name',
            'primaryContact'
        ]);

        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('primaryContact', function($contact) use ($search) {
                      $contact->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por país
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filtro por verificación
        if ($request->filled('verified')) {
            if ($request->get('verified') === 'yes') {
                $query->whereNotNull('verified_at');
            } else {
                $query->whereNull('verified_at');
            }
        }

        // Ordenamiento
        $sortBy = $request->get('sort', 'updated_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $clients = $query->paginate(25);

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

        return view('admin.clients.index', compact('clients', 
                                                    'countries', 
                                                    'companies', 
                                                    'stats'));
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        // CORREGIDO: Solo puertos de Argentina y Paraguay para evitar timeout
        $argentina = \App\Models\Country::where('alpha2_code', 'AR')->first();
        $paraguay = \App\Models\Country::where('alpha2_code', 'PY')->first();
        $countryIds = collect([$argentina?->id, $paraguay?->id])->filter()->values();

        $ports = Port::where('active', true)
            ->whereIn('country_id', $countryIds)
            ->select('id', 'name', 'code', 'city', 'country_id')
            ->orderBy('name')
            ->get();        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        return view('admin.clients.create', compact('countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Crear nuevo cliente.
     */
    public function store(Request $request)
    {
        // Autorización explícita usando la política
        $this->authorize('create', Client::class);

        // VALIDACIÓN: Se mueve la validación aquí. El FormRequest anterior era incorrecto
        // y causaba un fallo de validación silencioso, lo que provocaba la recarga de la página.
        $validatedData = $request->validate([
            'legal_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'tax_id' => [
                'required', 'string', 'max:50',
                // La validación ahora es más precisa: el tax_id debe ser único POR PAÍS.
                Rule::unique('clients')->where(fn ($query) => $query->where('country_id', $request->country_id))
            ],
            'country_id' => 'required|integer|exists:countries,id',
            'document_type_id' => 'required|integer|exists:document_types,id',
            'contacts' => 'nullable|array',
            'contacts.*.contact_person_name' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.address_line_1' => 'nullable|string|max:255',
            'contacts.*.is_primary' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();
            
            Log::info('Client creation debug', [
                'all_data' => $request->all(),
                'validated_data' => $validatedData,
                'contacts_data' => $request->contacts ?? 'No contacts provided',
                'user_id' => Auth::id()
            ]);

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);

            // Para super-admin, usar ID especial 999 para identificar creación por sistema
            if (auth()->user()->hasRole('super-admin')) {
                $validatedData['created_by_company_id'] = 999; // ID especial para super-admin
            } else {
                // Para otros usuarios, usar su empresa
                $userCompany = auth()->user()->getUserCompany();
                $validatedData['created_by_company_id'] = $userCompany?->id;
            }

            // Establecer valores por defecto
            $validatedData['status'] = 'active';
            $validatedData['verified_at'] = now();

            $clientData = collect($validatedData)->except('contacts')->toArray();
            $client = Client::create($clientData);

            // Crear múltiples contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                $primaryContactId = $this->createMultipleContacts($client, $request->contacts);
                $client->primary_contact_data_id = $primaryContactId;
                $client->save();
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating client', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar cliente individual.
     */
    public function show(Client $client)
    {
        $client->load([
            'country:id,name,alpha2_code',
            'documentType:id,name',
            'primaryPort:id,name',
            'customsOffice:id,name',
            'createdByCompany:id,legal_name',
            'contactData'
        ]);

        return view('admin.clients.show', compact('client'));
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Client $client)
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        // CORREGIDO: Solo puertos de Argentina y Paraguay para evitar timeout
        $argentina = \App\Models\Country::where('alpha2_code', 'AR')->first();
        $paraguay = \App\Models\Country::where('alpha2_code', 'PY')->first();
        $countryIds = collect([$argentina?->id, $paraguay?->id])->filter()->values();

        $ports = Port::where('active', true)
            ->whereIn('country_id', $countryIds)
            ->select('id', 'name', 'code', 'city', 'country_id')
            ->orderBy('name')
            ->get();        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        $client->load(['contactData']);

        return view('admin.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Actualizar cliente.
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        

        try {
            dd($client);
            DB::beginTransaction();

            $validatedData = $request->validated();
            $clientData = collect($validatedData)->except('contacts')->toArray();

            // Limpiar CUIT/RUC
            if (isset($clientData['tax_id'])) {
                $clientData['tax_id'] = preg_replace('/[^0-9]/', '', $clientData['tax_id']);
            }

            // Actualizar cliente
            $client->update($clientData);

            // Actualizar contactos si se proporcionan y vincular el principal
            $primaryContactId = $client->primary_contact_data_id; // Mantener el actual por defecto
            if ($request->has('contacts') && is_array($request->contacts)) {
                $primaryContactId = $this->updateMultipleContacts($client, $request->contacts);
            }

            // Si el ID del contacto principal cambió, actualizarlo en el cliente
            if ($client->primary_contact_data_id != $primaryContactId) {
                $client->primary_contact_data_id = $primaryContactId;
                $client->save();
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating client', [
                'client_id' => $client->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al actualizar cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Eliminar cliente.
     */
    public function destroy(Client $client)
    {
        try {
            DB::beginTransaction();

            // Eliminar contactos relacionados
            $client->contactData()->delete();

            // Eliminar cliente
            $client->delete();

            DB::commit();

            return redirect()
                ->route('admin.clients.index')
                ->with('success', 'Cliente eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withErrors(['error' => 'Error al eliminar cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Verificar cliente.
     */
    public function verify(Client $client)
    {
        $client->update(['verified_at' => now()]);
        
        return back()->with('success', 'Cliente verificado exitosamente');
    }

    /**
     * Cambiar estado del cliente.
     */
    public function toggleStatus(Client $client)
    {
        $newStatus = $client->status === 'active' ? 'inactive' : 'active';
        $client->update(['status' => $newStatus]);
        
        return back()->with('success', 'Estado del cliente actualizado');
    }

    /**
     * Transferir cliente (placeholder).
     */
    public function transfer(Client $client)
    {
        return back()->with('info', 'Funcionalidad de transferencia en desarrollo');
    }

    /**
     * Importación masiva de clientes desde CSV
     * Basado en estructura real de PARANA.csv y Guaran.csv
     */
    public function bulkImport(BulkClientImportRequest $request)
    {
        try {
            $file = $request->file('import_file');
            $importType = $request->input('import_type', 'clients');
            
            // Leer contenido del archivo
            $csvContent = file_get_contents($file->getRealPath());
            
            // Detectar tipo de CSV
            $manifestType = $this->detectCsvType($csvContent);
            
            // Procesar datos según tipo
            $records = $this->processCsvData($csvContent, $manifestType);
            
            if (empty($records)) {
                return back()->with('error', 'No se encontraron registros válidos en el archivo CSV.');
            }
            
            // Procesar clientes
            $results = $this->processClientRecords($records);
            
            return back()->with('success', 
                "Importación completada: {$results['created']} creados, {$results['updated']} actualizados, {$results['errors']} errores."
            );
            
        } catch (Exception $e) {
            return back()->with('error', 'Error en importación: ' . $e->getMessage());
        }
    }

    /**
     * Detectar tipo de CSV basado en headers
     */
    private function detectCsvType(string $csvContent): string
    {
        $firstLine = strtok($csvContent, "\n");
        $headers = str_getcsv($firstLine);
        
        // Convertir headers a lowercase para comparación
        $headersLower = array_map('strtolower', $headers);
        
        // Detectar PARANA.csv
        if (in_array('shipper name', $headersLower) && 
            in_array('consignee name', $headersLower) && 
            in_array('barge name', $headersLower)) {
            return 'parana';
        }
        
        // Detectar GUARAN.csv (estructura similar pero puede variar)
        if (in_array('exportador', $headersLower) || 
            in_array('importador', $headersLower)) {
            return 'guaran';
        }
        
        return 'generic';
    }

    /**
     * Procesar datos del CSV según el tipo detectado
     */
    private function processCsvData(string $csvContent, string $manifestType): array
    {
        $lines = explode("\n", $csvContent);
        $headers = str_getcsv(array_shift($lines));
        $records = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            if (count($data) !== count($headers)) continue;
            
            $record = array_combine($headers, $data);
            $records[] = $this->extractClientInfo($record, $manifestType);
        }
        
        // Filtrar registros vacíos
        return array_filter($records);
    }

    /**
     * Extraer información de cliente del registro CSV
     */
    private function extractClientInfo(array $record, string $manifestType): ?array
    {
        $clients = [];
        
        switch ($manifestType) {
            case 'parana':
                $clients = $this->extractParanaClients($record);
                break;
            case 'guaran':
                $clients = $this->extractGuaranClients($record);
                break;
            default:
                $clients = $this->extractGenericClients($record);
        }
        
        return $clients;
    }

    /**
     * Extraer clientes de formato PARANA
     */
    private function extractParanaClients(array $record): array
    {
        $clients = [];
        
        // Shipper
        if (!empty($record['SHIPPER NAME'])) {
            $clients[] = [
                'legal_name' => trim($record['SHIPPER NAME']),
                'address' => trim($record['SHIPPER ADDRESS1'] ?? ''),
                'city' => trim($record['SHIPPER CITY'] ?? ''),
                'country_id' => $this->findCountryId($record['SHIPPER COUNTRY'] ?? ''),
                'document_type_id' => 1, // Valor por defecto
                'created_by_company_id' => Auth::user()->getUserCompany()?->id,
            ];
        }
        
        // Consignee
        if (!empty($record['CONSIGNEE NAME'])) {
            $clients[] = [
                'legal_name' => trim($record['CONSIGNEE NAME']),
                'address' => trim($record['CONSIGNEE ADDRESS1'] ?? ''),
                'city' => trim($record['CONSIGNEE CITY'] ?? ''),
                'country_id' => $this->findCountryId($record['CONSIGNEE COUNTRY'] ?? ''),
                'document_type_id' => 1, // Valor por defecto
                'created_by_company_id' => Auth::user()->getUserCompany()?->id,
            ];
        }
        
        // Notify Party
        if (!empty($record['NOTIFY PARTY NAME'])) {
            $clients[] = [
                'legal_name' => trim($record['NOTIFY PARTY NAME']),
                'address' => trim($record['NOTIFY PARTY ADDRESS1'] ?? ''),
                'city' => trim($record['NOTIFY PARTY CITY'] ?? ''),
                'country_id' => $this->findCountryId($record['NOTIFY PARTY COUNTRY'] ?? ''),
                'document_type_id' => 1, // Valor por defecto
                'created_by_company_id' => Auth::user()->getUserCompany()?->id,
            ];
        }
        
        return $clients;
    }

    /**
     * Extraer clientes de formato GUARAN
     */
    private function extractGuaranClients(array $record): array
    {
        $clients = [];
        
        // Implementar lógica específica para GUARAN
        // Basado en la estructura real del CSV
        
        return $clients;
    }

    /**
     * Extraer clientes de formato genérico
     */
    private function extractGenericClients(array $record): array
    {
        $clients = [];
        
        // Lógica genérica basada en columnas comunes
        $nameFields = ['name', 'company_name', 'legal_name', 'cliente', 'empresa'];
        
        foreach ($nameFields as $field) {
            if (isset($record[$field]) && !empty(trim($record[$field]))) {
                $clients[] = [
                    'legal_name' => trim($record[$field]),
                    'address' => trim($record['address'] ?? $record['direccion'] ?? ''),
                    'country_id' => $this->findCountryId($record['country'] ?? $record['pais'] ?? ''),
                    'document_type_id' => 1, // Valor por defecto
                    'created_by_company_id' => Auth::user()->getUserCompany()?->id,
                ];
                break;
            }
        }
        
        return $clients;
    }

    /**
     * Procesar registros de clientes y crear/actualizar
     */
    private function processClientRecords(array $clientsData): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0];
        
        foreach ($clientsData as $clientGroup) {
            if (!is_array($clientGroup)) continue;
            
            foreach ($clientGroup as $clientData) {
                if (empty($clientData['legal_name'])) continue;
                
                try {
                    // Buscar cliente existente por nombre legal
                    $existingClient = Client::where('legal_name', $clientData['legal_name'])->first();
                    
                    if ($existingClient) {
                        // Actualizar cliente existente
                        $existingClient->update($clientData);
                        $results['updated']++;
                    } else {
                        // Crear nuevo cliente
                        Client::create($clientData);
                        $results['created']++;
                    }
                    
                } catch (Exception $e) {
                    Log::error('Error processing client record', [
                        'data' => $clientData,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors']++;
                }
            }
        }
        
        return $results;
    }

    /**
     * Encontrar ID de país por nombre
     */
    private function findCountryId(?string $countryName): ?int
    {
        if (empty($countryName)) return null;
        
        $country = Country::where('name', 'like', "%{$countryName}%")
                         ->orWhere('alpha2_code', strtoupper($countryName))
                         ->first();
        
        return $country?->id;
    }

    /**
     * Búsqueda AJAX para autocompletado.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('status', 'active')
            ->where(function($q) use ($query) {
                $q->where('legal_name', 'like', "%{$query}%")
                  ->orWhere('commercial_name', 'like', "%{$query}%")
                  ->orWhere('tax_id', 'like', "%{$query}%");
            })
            ->with('country:id,name')
            ->limit(10)
            ->get(['id', 'legal_name', 'commercial_name', 'tax_id', 'country_id']);

        return response()->json($clients);
    }

    /**
     * API Helper: Obtener datos para formularios.
     */
    public function getFormData()
    {
        return response()->json([
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name', 'alpha2_code']),
            'document_types' => DocumentType::where('active', true)->orderBy('name')->get(['id', 'name']),
            'ports' => Port::where('active', true)->orderBy('name')->get(['id', 'name']),
            'custom_offices' => CustomOffice::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // =====================================================
    // MÉTODOS AUXILIARES PARA CONTACTOS
    // =====================================================

   private function createMultipleContacts(Client $client, array $contactsData): ?int
    {
        $primaryContactId = null;

        foreach ($contactsData as $contactData) {
            // Saltar si no hay datos mínimos
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['address_line_1'])) {
                continue;
            }

            $isPrimary = !empty($contactData['is_primary']);

            $contact = $client->contactData()->create([
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'address_line_1' => $contactData['address_line_1'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
                'created_by_user_id' => auth()->id(),
            ]);

            if ($isPrimary) {
                $primaryContactId = $contact->id;
            }
        }

        // Si no se marcó un primario, asignar el primero
        if (!$primaryContactId && $client->contactData()->count() > 0) {
            $firstContact = $client->contactData()->first();
            $firstContact->is_primary = true;
            $firstContact->save();
            $primaryContactId = $firstContact->id;
        }

        return $primaryContactId;
    }
    
    /**
     * Actualizar múltiples contactos de un cliente
     */
    private function updateMultipleContacts(Client $client, array $contactsData): ?int
    {
        $incomingContactIds = [];
        $primaryContactId = null;

        // 1. Actualizar contactos existentes y crear nuevos
        foreach ($contactsData as $contactData) {
            // Usar updateOrCreate: si el ID existe, actualiza; si no, crea.
            $contact = $client->contactData()->updateOrCreate(
                ['id' => $contactData['id'] ?? null], // Clave para buscar
                [                                     // Datos para actualizar o crear
                    'email' => $contactData['email'] ?? null,
                    'phone' => $contactData['phone'] ?? null,
                    'address_line_1' => $contactData['address_line_1'] ?? null,
                    'is_primary' => !empty($contactData['is_primary']),
                    'active' => true,
                    'updated_by_user_id' => auth()->id(),
                ]
            );

            $incomingContactIds[] = $contact->id;

            if (!empty($contactData['is_primary'])) {
                $primaryContactId = $contact->id;
            }
        }

        // 2. Eliminar contactos que ya no están en el formulario
        $client->contactData()->whereNotIn('id', $incomingContactIds)->delete();

        // 3. Asegurar que solo un contacto sea el principal
        if ($primaryContactId) {
            $client->contactData()->where('id', '!=', $primaryContactId)->update(['is_primary' => false]);
        } elseif ($client->contactData()->count() > 0) {
            // Si no hay primario (p.ej. se eliminó), asignar el primero
            $firstContact = $client->contactData()->first();
            $firstContact->is_primary = true;
            $firstContact->save();
            $primaryContactId = $firstContact->id;
        }

        return $primaryContactId;
    }
}