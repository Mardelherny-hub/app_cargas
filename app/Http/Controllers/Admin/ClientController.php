<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\Company;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Controlador Admin para gestión completa de clientes
 * CORRECCIÓN CRÍTICA: client_type → client_roles (múltiples roles)
 */
class ClientController extends Controller
{
    /**
     * Listar clientes con filtros y búsqueda.
     */
    public function index(Request $request)
    {
        $query = Client::with([
            'country:id,name,alpha2_code',
            'documentType:id,name',
            'primaryPort:id,name',
            'customOffice:id,name',
            'createdByCompany:id,legal_name',
            'primaryContact'
        ]);

        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhereHas('primaryContact', function($contact) use ($search) {
                      $contact->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // CORRECCIÓN: Filtro por roles de cliente (JSON)
        if ($request->filled('client_role')) {
            $role = $request->get('client_role');
            $validRoles = ['shipper', 'consignee', 'notify_party'];
            if (in_array($role, $validRoles)) {
                $query->whereJsonContains('client_roles', $role);
            }
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
            // CORRECCIÓN: Estadísticas por roles (JSON)
            'by_role' => [
                'shipper' => Client::whereJsonContains('client_roles', 'shipper')->count(),
                'consignee' => Client::whereJsonContains('client_roles', 'consignee')->count(),
                'notify_party' => Client::whereJsonContains('client_roles', 'notify_party')->count(),
            ],
        ];

        $availableRoles = Client::getClientRoleOptions();

        return view('admin.clients.index', compact('clients', 
                                                    'countries', 
                                                    'companies', 
                                                    'stats',
                                                    'availableRoles'));
    }

    /**
     * Mostrar formulario de creación.
     */
    public function create()
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        return view('admin.clients.create', compact('countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Crear nuevo cliente.
     * CORRECCIÓN: Usa CreateClientRequest con client_roles
     */
    public function store(CreateClientRequest $request)
    {
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC
            $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);

            // Crear cliente
            $client = Client::create($validatedData);

            // Crear múltiples contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->createMultipleContacts($client, $request->contacts);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating client', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear cliente: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar cliente específico.
     */
    public function show(Client $client)
    {
        $client->load([
            'country', 
            'documentType', 
            'primaryPort',
            'customOffice',
            'createdByCompany',
            'contactData' => function($query) {
                $query->where('active', true)->orderBy('is_primary', 'desc');
            }
        ]);

        // Agrupar contactos por tipo
        $contactsByType = $client->contactData->groupBy('contact_type');

        // Tipos de contacto para mostrar etiquetas
        $contactTypes = [
            'afip' => 'AFIP/Webservices',
            'arrival_notice' => 'Cartas de Arribo',
            'manifest' => 'Manifiestos',
            'operations' => 'Operaciones',
            'billing' => 'Facturación',
            'emergencies' => 'Emergencias',
            'general' => 'General',
        ];

        return view('admin.clients.show', compact('client', 'contactsByType', 'contactTypes'));
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Client $client)
    {
        $countries = Country::where('active', true)->orderBy('name')->get();
        $documentTypes = DocumentType::where('active', true)->orderBy('name')->get();
        $ports = Port::where('active', true)->orderBy('name')->get();
        $customOffices = CustomOffice::where('active', true)->orderBy('name')->get();

        // Cargar contactos para edición
        $client->load(['contactData' => function($query) {
            $query->where('active', true)->orderBy('is_primary', 'desc')->orderBy('contact_type');
        }]);

        return view('admin.clients.edit', compact('client', 'countries', 'documentTypes', 'ports', 'customOffices'));
    }

    /**
     * Actualizar cliente.
     * CORRECCIÓN: Usa UpdateClientRequest con client_roles
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        try {
            DB::beginTransaction();

            // Los datos ya vienen validados del FormRequest
            $validatedData = $request->validated();

            // Limpiar CUIT/RUC si se proporciona
            if (isset($validatedData['tax_id'])) {
                $validatedData['tax_id'] = preg_replace('/[^0-9]/', '', $validatedData['tax_id']);
            }
            
            // Actualizar cliente
            $client->update($validatedData);

            // Actualizar múltiples contactos
            if ($request->has('contacts') && is_array($request->contacts)) {
                $this->updateMultipleContacts($client, $request->contacts);
            }

            DB::commit();

            return redirect()
                ->route('admin.clients.show', $client)
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating client', [
                'client_id' => $client->id,
                'data' => $request->validated(),
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
            // Solo cambiar estado, no eliminar físicamente
            $client->update(['status' => 'inactive']);

            return redirect()
                ->route('admin.clients.index')
                ->with('success', 'Cliente desactivado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error deleting client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Error al desactivar cliente');
        }
    }

    /**
     * Verificar cliente CUIT/RUC.
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
     * Detectar tipo de CSV basado en contenido real
     */
    private function detectCsvType(string $content): string
    {
        // PARANA: Inicia directamente con headers
        if (str_contains($content, 'LOCATION NAME,ADDRESS LINE1') && 
            str_contains($content, 'MAERSK')) {
            return 'parana';
        }
        
        // Guaran: Tiene metadata inicial "EDI To Custom"
        if (str_contains($content, 'EDI To Custom') && 
            str_contains($content, 'User Name : Admin')) {
            return 'guaran';
        }
        
        return 'parana'; // Por defecto
    }

    /**
     * Procesar datos CSV según tipo detectado
     */
    private function processCsvData(string $content, string $manifestType): array
    {
        $lines = str_getcsv($content, "\n");
        $data = [];
        $headers = null;
        $startProcessing = false;
        
        foreach ($lines as $lineNumber => $line) {
            $row = str_getcsv($line);
            
            if ($manifestType === 'guaran' && !$startProcessing) {
                // Guaran: Buscar línea de headers después de metadata
                if (!empty($row[0]) && str_contains($row[0], 'LOCATION NAME')) {
                    $headers = array_map('trim', $row);
                    $startProcessing = true;
                }
                continue;
            }
            
            if ($manifestType === 'parana' && $headers === null) {
                // PARANA: Primera línea no vacía son los headers
                if (!empty($row[0])) {
                    $headers = array_map('trim', $row);
                    continue;
                }
            }
            
            // Procesar datos
            if ($headers && !empty($row[0]) && count($row) >= count($headers)) {
                $record = array_combine($headers, $row);
                if ($record && !empty($record['BL NUMBER'])) {
                    $data[] = $record;
                }
            }
        }
        
        return $data;
    }

    /**
     * Procesar registros y crear/actualizar clientes
     * CORREGIDO: Maneja "SAME AS CONSIGNEE" encontrado en datos reales
     */
    private function processClientRecords(array $records): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0];
        $processedClients = []; // Evitar duplicados en mismo import
        
        foreach ($records as $record) {
            try {
                // Extraer datos de cada tipo de cliente
                $clientData = $this->extractClientData($record);
                
                // Manejar caso especial "SAME AS CONSIGNEE"
                $consigneeClient = null;
                
                foreach ($clientData as $clientInfo) {
                    $key = $clientInfo['legal_name'] . '_' . ($clientInfo['tax_id'] ?? 'no_cuit');
                    
                    // Evitar procesar mismo cliente múltiples veces
                    if (isset($processedClients[$key])) {
                        $client = $processedClients[$key];
                        // Agregar rol si no lo tiene
                        if (!$client->hasRole($clientInfo['role'])) {
                            $roles = $client->client_roles;
                            $roles[] = $clientInfo['role'];
                            $client->update(['client_roles' => array_unique($roles)]);
                            $results['updated']++;
                        }
                        
                        // Guardar referencia si es consignee para "SAME AS CONSIGNEE"
                        if ($clientInfo['role'] === 'consignee') {
                            $consigneeClient = $client;
                        }
                        continue;
                    }
                    
                    // Buscar cliente existente por CUIT o nombre
                    $existingClient = $this->findExistingClient($clientInfo);
                    
                    if ($existingClient) {
                        // Actualizar roles del cliente existente
                        if (!$existingClient->hasRole($clientInfo['role'])) {
                            $roles = $existingClient->client_roles;
                            $roles[] = $clientInfo['role'];
                            $existingClient->update(['client_roles' => array_unique($roles)]);
                            $results['updated']++;
                        }
                        $processedClients[$key] = $existingClient;
                        
                        // Guardar referencia si es consignee
                        if ($clientInfo['role'] === 'consignee') {
                            $consigneeClient = $existingClient;
                        }
                    } else {
                        // Crear nuevo cliente
                        $client = $this->createNewClient($clientInfo);
                        if ($client) {
                            $results['created']++;
                            $processedClients[$key] = $client;
                            
                            // Guardar referencia si es consignee
                            if ($clientInfo['role'] === 'consignee') {
                                $consigneeClient = $client;
                            }
                        } else {
                            $results['errors']++;
                        }
                    }
                }
                
                // Manejar "SAME AS CONSIGNEE" - agregar rol notify_party al consignee
                if ($consigneeClient && !empty($record['NOTIFY PARTY NAME'])) {
                    $notifyName = trim($record['NOTIFY PARTY NAME']);
                    if (strtoupper($notifyName) === 'SAME AS CONSIGNEE') {
                        if (!$consigneeClient->hasRole('notify_party')) {
                            $roles = $consigneeClient->client_roles;
                            $roles[] = 'notify_party';
                            $consigneeClient->update(['client_roles' => array_unique($roles)]);
                            $results['updated']++;
                        }
                    }
                }
                
            } catch (Exception $e) {
                $results['errors']++;
                \Log::error("Error procesando registro CSV: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Extraer datos de clientes desde registro CSV
     * Basado en estructura real encontrada en PARANA/Guaran
     */
    private function extractClientData(array $record): array
    {
        $clients = [];
        
        // 1. SHIPPER (Cargador/Exportador)
        if (!empty($record['SHIPPER NAME'])) {
            $shipperData = [
                'legal_name' => trim($record['SHIPPER NAME']),
                'role' => 'shipper',
                'tax_id' => $this->extractTaxId($record['SHIPPER ADDRESS1'] ?? ''),
                'country_id' => 1, // Argentina por defecto para PARANA
                'document_type_id' => 1, // CUIT por defecto
                'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
            ];
            
            if (!empty($shipperData['legal_name'])) {
                $clients[] = $shipperData;
            }
        }
        
        // 2. CONSIGNEE (Consignatario/Importador)  
        if (!empty($record['CONSIGNEE NAME'])) {
            $consigneeData = [
                'legal_name' => trim($record['CONSIGNEE NAME']),
                'role' => 'consignee',
                'tax_id' => $this->extractTaxId($record['CONSIGNEE ADDRESS1'] ?? ''),
                'country_id' => $this->detectCountry($record['CONSIGNEE COUNTRY'] ?? 'PARAGUAY'),
                'document_type_id' => 1, // CUIT/RUC
                'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
            ];
            
            if (!empty($consigneeData['legal_name'])) {
                $clients[] = $consigneeData;
            }
        }
        
        // 3. NOTIFY PARTY (Notificatario)
        if (!empty($record['NOTIFY PARTY NAME'])) {
            $notifyName = trim($record['NOTIFY PARTY NAME']);
            
            // Saltar si es "SAME AS CONSIGNEE" - ya procesamos el consignee
            if (strtoupper($notifyName) === 'SAME AS CONSIGNEE') {
                // Agregar rol notify_party al consignee si existe
                if (!empty($clients) && $clients[count($clients)-1]['role'] === 'consignee') {
                    // No crear registro separado, será manejado en processClientRecords
                }
            } else {
                $notifyData = [
                    'legal_name' => $notifyName,
                    'role' => 'notify_party',
                    'tax_id' => $this->extractTaxId($record['NOTIFY PARTY ADDRESS1'] ?? ''),
                    'country_id' => $this->detectCountry($record['NOTIFY PARTY COUNTRY'] ?? 'PARAGUAY'),
                    'document_type_id' => 1,
                    'created_by_company_id' => auth()->user()->getUserCompany()?->id ?? 1,
                ];
                
                if (!empty($notifyData['legal_name'])) {
                    $clients[] = $notifyData;
                }
            }
        }
        
        return $clients;
    }

    /**
     * Extraer CUIT/RUC de texto de dirección
     * Patrón encontrado: "CUIT: 30688415531"
     */
    private function extractTaxId(string $addressText): ?string
    {
        if (empty($addressText)) {
            return null;
        }
        
        // Patrón CUIT: seguido de números
        if (preg_match('/CUIT:\s*(\d{11})/', $addressText, $matches)) {
            return $matches[1];
        }
        
        // Patrón RUC: seguido de números  
        if (preg_match('/RUC:\s*(\d+)/', $addressText, $matches)) {
            return $matches[1];
        }
        
        // Buscar solo números de 11 dígitos (CUIT) o 8+ dígitos (RUC)
        if (preg_match('/\b(\d{11})\b/', $addressText, $matches)) {
            return $matches[1]; // CUIT argentino
        }
        
        if (preg_match('/\b(\d{8,10})\b/', $addressText, $matches)) {
            return $matches[1]; // RUC paraguayo u otros
        }
        
        return null;
    }

    /**
     * Detectar país basado en campo COUNTRY
     */
    private function detectCountry(string $countryText): int
    {
        $countryUpper = strtoupper(trim($countryText));
        
        switch ($countryUpper) {
            case 'ARGENTINA':
            case 'ARG':
                return 1; // ID de Argentina en BD
                
            case 'PARAGUAY':
            case 'PAR':
            case 'PY':
                return 2; // ID de Paraguay en BD
                
            default:
                return 1; // Por defecto Argentina
        }
    }

    /**
     * Buscar cliente existente por CUIT/RUC o nombre
     * Usa métodos existentes del modelo Client
     */
    private function findExistingClient(array $clientInfo): ?Client
    {
        // 1. Buscar por CUIT/RUC si está disponible (más confiable)
        if (!empty($clientInfo['tax_id'])) {
            $client = Client::findByTaxId(
                $clientInfo['tax_id'], 
                $clientInfo['country_id']
            );
            
            if ($client) {
                return $client;
            }
        }
        
        // 2. Buscar por nombre legal como fallback
        if (!empty($clientInfo['legal_name'])) {
            $client = Client::where('legal_name', $clientInfo['legal_name'])
                ->where('country_id', $clientInfo['country_id'])
                ->first();
                
            if ($client) {
                return $client;
            }
        }
        
        // 3. Búsqueda aproximada por nombre (para casos con variaciones menores)
        if (!empty($clientInfo['legal_name']) && strlen($clientInfo['legal_name']) > 10) {
            $cleanName = $this->cleanCompanyName($clientInfo['legal_name']);
            
            $client = Client::where('country_id', $clientInfo['country_id'])
                ->where(function($query) use ($cleanName) {
                    $query->where('legal_name', 'like', "%{$cleanName}%")
                        ->orWhere('legal_name', 'like', "%{$cleanName}%");
                })
                ->first();
                
            if ($client) {
                return $client;
            }
        }
        
        return null;
    }

    /**
     * Limpiar nombre de empresa para búsqueda aproximada
     * Remueve sufijos comunes y normaliza
     */
    private function cleanCompanyName(string $companyName): string
    {
        $name = trim($companyName);
        
        // Remover sufijos comunes
        $suffixes = [
            ' S.A.', ' S.R.L.', ' LTDA.', ' S.A.C.I.', 
            ' S.A', ' SRL', ' LTDA', ' SACI',
            ' SA', ' LIMITADA'
        ];
        
        foreach ($suffixes as $suffix) {
            if (str_ends_with(strtoupper($name), strtoupper($suffix))) {
                $name = substr($name, 0, -strlen($suffix));
                break;
            }
        }
        
        // Normalizar espacios
        $name = preg_replace('/\s+/', ' ', trim($name));
        
        // Remover caracteres especiales para búsqueda
        $name = preg_replace('/[^\w\s]/', '', $name);
        
        return trim($name);
    }

    /**
     * Crear nuevo cliente con rol asignado
     * Usa estructura existente del modelo Client
     */
    private function createNewClient(array $clientInfo): ?Client
    {
        try {
            // Preparar datos usando campos fillable del modelo
            $clientData = [
                'tax_id' => $this->cleanTaxId($clientInfo['tax_id'] ?? null),
                'country_id' => $clientInfo['country_id'],
                'document_type_id' => $clientInfo['document_type_id'],
                'client_roles' => [$clientInfo['role']], // Array JSON como requiere el modelo
                'legal_name' => $clientInfo['legal_name'],
                'status' => 'active', // Por defecto activo
                'created_by_company_id' => $clientInfo['created_by_company_id'],
                'verified_at' => null, // Sin verificar inicialmente
                'notes' => 'Creado desde importación CSV'
            ];
            
            // Validar datos mínimos requeridos
            if (empty($clientData['legal_name'])) {
                \Log::warning("Cliente sin nombre legal: " . json_encode($clientInfo));
                return null;
            }
            
            // Crear cliente usando el modelo existente
            $client = Client::create($clientData);
            
            \Log::info("Cliente creado desde CSV: {$client->legal_name} con rol {$clientInfo['role']}");
            
            return $client;
            
        } catch (Exception $e) {
            \Log::error("Error creando cliente: " . $e->getMessage() . " - Datos: " . json_encode($clientInfo));
            return null;
        }
    }

    /**
     * Limpiar CUIT/RUC para almacenamiento
     * Basado en el evento saving() del modelo Client
     */
    private function cleanTaxId(?string $taxId): ?string
    {
        if (empty($taxId)) {
            return null;
        }
        
        // Limpiar caracteres no numéricos (como hace el modelo)
        $cleaned = preg_replace('/[^0-9]/', '', $taxId);
        
        // Validar longitud mínima
        if (strlen($cleaned) < 7) {
            return null;
        }
        
        return $cleaned;
    }

    /**
     * Búsqueda AJAX para autocompletado.
     * CORRECCIÓN: Busca en client_roles (JSON)
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
                  ->orWhere('tax_id', 'like', "%{$query}%");
            })
            ->with('country:id,name')
            ->limit(10)
            ->get(['id', 'legal_name', 'tax_id', 'client_roles', 'country_id']);

        return response()->json($clients);
    }

    /**
     * API Helper: Obtener datos para formularios.
     * CORRECCIÓN: Devuelve client_roles disponibles
     */
    public function getFormData()
    {
        return response()->json([
            'client_roles' => Client::getClientRoleOptions(),
            'statuses' => Client::getStatusOptions(),
            'countries' => Country::where('active', true)->get(['id', 'name']),
            'document_types' => DocumentType::where('active', true)->get(['id', 'name']),
            'ports' => Port::where('active', true)->get(['id', 'name']),
            'customs_offices' => CustomOffice::where('active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Crear múltiples contactos para un cliente.
     */
    private function createMultipleContacts(Client $client, array $contacts): void
    {
        $hasPrimary = false;
        
        foreach ($contacts as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }
            
            // Solo un contacto puede ser primario
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? $index === 0);
            if ($isPrimary) {
                $hasPrimary = true;
            }
            
            $client->contactData()->create([
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile_phone' => $contactData['mobile_phone'] ?? null,
                'address_line_1' => $contactData['address_line_1'] ?? null,
                'address_line_2' => $contactData['address_line_2'] ?? null,
                'city' => $contactData['city'] ?? null,
                'state_province' => $contactData['state_province'] ?? null,
                'contact_person_name' => $contactData['contact_person_name'] ?? null,
                'contact_person_position' => $contactData['contact_person_position'] ?? null,
                'notes' => $contactData['notes'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
                'created_by_user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Actualizar múltiples contactos para un cliente.
     */
    private function updateMultipleContacts(Client $client, array $contacts): void
    {
        $hasPrimary = false;
        
        foreach ($contacts as $index => $contactData) {
            // Validar que tenga al menos email o teléfono
            if (empty($contactData['email']) && empty($contactData['phone']) && empty($contactData['mobile_phone'])) {
                continue;
            }
            
            // Solo un contacto puede ser primario
            $isPrimary = !$hasPrimary && ($contactData['is_primary'] ?? $index === 0);
            if ($isPrimary) {
                $hasPrimary = true;
            }
            
            $contactAttributes = [
                'contact_type' => $contactData['contact_type'] ?? 'general',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile_phone' => $contactData['mobile_phone'] ?? null,
                'address_line_1' => $contactData['address_line_1'] ?? null,
                'address_line_2' => $contactData['address_line_2'] ?? null,
                'city' => $contactData['city'] ?? null,
                'state_province' => $contactData['state_province'] ?? null,
                'contact_person_name' => $contactData['contact_person_name'] ?? null,
                'contact_person_position' => $contactData['contact_person_position'] ?? null,
                'notes' => $contactData['notes'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
                'updated_by_user_id' => auth()->id(),
            ];
            
            if (!empty($contactData['id'])) {
                // Actualizar contacto existente
                $client->contactData()->where('id', $contactData['id'])->update($contactAttributes);
            } else {
                // Crear nuevo contacto
                $contactAttributes['created_by_user_id'] = auth()->id();
                $client->contactData()->create($contactAttributes);
            }
        }
    }
}