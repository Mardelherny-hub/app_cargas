<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gestión de Transbordos
 *
 * Funcionalidades implementadas:
 * - Gestión de transbordos (CRUD)
 * - Manejo de barcazas
 * - Seguimiento de posiciones
 * - División de cargas en barcazas
 * - Seguimiento de rutas
 *
 * Webservices soportados:
 * - TitTransContVacioReg (Título de Transporte de contenedores vacíos)
 */
class TransferController extends Controller
{
    use UserHelper;

    /**
     * Lista de transbordos.
     */
    public function index(Request $request)
    {
        // Verificar permisos de transbordos
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'Su empresa no tiene permisos para gestionar transbordos.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No tiene una empresa asignada.');
        }

        // Verificar que la empresa tenga rol "Transbordos"
        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        // Filtros de búsqueda
        $filters = $request->only(['search', 'status', 'barge_id', 'date_from', 'date_to']);

        // TODO: Implementar query de transbordos cuando se cree el modelo
        // Por ahora, datos de ejemplo para desarrollo
        $transfers = collect([
            (object)[
                'id' => 1,
                'transfer_number' => 'TR-2024-001',
                'barge_id' => 'BARCAZA-01',
                'origen' => 'Puerto Buenos Aires',
                'destino' => 'Puerto Asunción',
                'containers_count' => 15,
                'total_weight' => 350.5,
                'status' => 'in_transit',
                'current_position' => 'Km 1240 - Rio Paraná',
                'created_at' => now()->subDays(3),
                'last_position_update' => now()->subHours(2),
            ],
            (object)[
                'id' => 2,
                'transfer_number' => 'TR-2024-002',
                'barge_id' => 'BARCAZA-02',
                'origen' => 'Puerto Rosario',
                'destino' => 'Puerto Montevideo',
                'containers_count' => 8,
                'total_weight' => 180.0,
                'status' => 'loading',
                'current_position' => 'Puerto Rosario',
                'created_at' => now()->subDays(1),
                'last_position_update' => now()->subMinutes(30),
            ],
        ]);

        // Estadísticas para el dashboard
        $stats = [
            'total_transfers' => $transfers->count(),
            'active_transfers' => $transfers->whereIn('status', ['loading', 'in_transit'])->count(),
            'total_containers' => $transfers->sum('containers_count'),
            'total_barges' => $transfers->pluck('barge_id')->unique()->count(),
        ];

        return view('company.transfers.index', compact(
            'transfers',
            'stats',
            'filters'
        ));
    }

    /**
     * Formulario para crear nuevo transbordo.
     */
    public function create()
    {
        // Verificar permisos
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para crear transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        // Solo company-admin puede crear nuevos transbordos
        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden crear transbordos.');
        }

        // TODO: Obtener barcazas disponibles de la empresa
        $availableBarges = collect([
            (object)['id' => 'BARCAZA-01', 'name' => 'Barcaza Río I', 'capacity' => 20, 'status' => 'available'],
            (object)['id' => 'BARCAZA-02', 'name' => 'Barcaza Río II', 'capacity' => 15, 'status' => 'available'],
            (object)['id' => 'BARCAZA-03', 'name' => 'Barcaza Río III', 'capacity' => 25, 'status' => 'maintenance'],
        ]);

        // TODO: Obtener rutas disponibles
        $availableRoutes = collect([
            (object)['id' => 'R001', 'name' => 'Buenos Aires - Asunción', 'distance_km' => 1350],
            (object)['id' => 'R002', 'name' => 'Rosario - Montevideo', 'distance_km' => 890],
            (object)['id' => 'R003', 'name' => 'Santa Fe - Nueva Palmira', 'distance_km' => 756],
        ]);

        return view('company.transfers.create', compact('availableBarges', 'availableRoutes'));
    }

    /**
     * Guardar nuevo transbordo.
     */
    public function store(Request $request)
    {
        // Verificar permisos
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para crear transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden crear transbordos.');
        }

        $request->validate([
            'barge_id' => 'required|string|max:20',
            'route_id' => 'required|string|max:10',
            'origen' => 'required|string|max:100',
            'destino' => 'required|string|max:100',
            'containers' => 'required|array|min:1',
            'containers.*.id' => 'required|string',
            'containers.*.type' => 'required|in:full,empty',
            'containers.*.weight' => 'nullable|numeric|min:0',
            'estimated_departure' => 'required|date|after:now',
        ]);

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // TODO: Crear registro de transbordo en base de datos

            // Preparar datos para webservice TitTransContVacioReg (solo contenedores vacíos)
            $emptyContainers = array_filter($request->containers, function($container) {
                return $container['type'] === 'empty';
            });

            if (!empty($emptyContainers)) {
                $wsData = [
                    'IdTransaccion' => $this->generateTransactionId(),
                    'TitTransContVacioReg' => [
                        'idFiscal' => $company->tax_id,
                        'codViaTrans' => $this->getTransportCode($request->route_id),
                        'idTitTrans' => $this->generateTitleId(),
                        'idContenedores' => array_map(function($container) {
                            return $container['id'];
                        }, $emptyContainers),
                        'remitente' => $company->legal_name,
                        'consignatario' => $request->consignatario ?? 'TBD',
                        'destinatario' => $request->destinatario ?? 'TBD',
                        'origen' => $request->origen,
                        'destino' => $request->destino,
                        'idTrack' => $this->generateTrackingId(),
                    ]
                ];

                // TODO: Enviar a webservice DESA - TitTransContVacioReg
                Log::info('Registrando títulos de transporte contenedores vacíos en DESA', [
                    'company_id' => $company->id,
                    'barge_id' => $request->barge_id,
                    'empty_containers_count' => count($emptyContainers),
                    'ws_data' => $wsData
                ]);
            }

            DB::commit();

            return redirect()->route('company.transfers.index')
                ->with('success', 'Transbordo creado correctamente. Sincronización con DESA pendiente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear transbordo', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->withInput()
                ->with('error', 'Error al crear el transbordo: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalles de un transbordo.
     */
    public function show($id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para ver transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        $company = $this->getUserCompany();

        // TODO: Obtener transbordo de la base de datos
        // Por ahora, datos de ejemplo
        $transfer = (object)[
            'id' => $id,
            'transfer_number' => 'TR-2024-001',
            'barge_id' => 'BARCAZA-01',
            'barge_name' => 'Barcaza Río I',
            'route_id' => 'R001',
            'route_name' => 'Buenos Aires - Asunción',
            'origen' => 'Puerto Buenos Aires',
            'destino' => 'Puerto Asunción',
            'status' => 'in_transit',
            'current_position' => 'Km 1240 - Rio Paraná',
            'estimated_arrival' => now()->addDays(2),
            'created_at' => now()->subDays(3),
            'last_position_update' => now()->subHours(2),
            'containers' => [
                (object)['id' => 'CONT001', 'type' => 'full', 'weight' => 25.5, 'status' => 'loaded'],
                (object)['id' => 'CONT002', 'type' => 'empty', 'weight' => 0, 'status' => 'loaded'],
                (object)['id' => 'CONT003', 'type' => 'full', 'weight' => 30.0, 'status' => 'loaded'],
            ],
            'position_history' => [
                (object)['position' => 'Puerto Buenos Aires', 'timestamp' => now()->subDays(3), 'event' => 'departure'],
                (object)['position' => 'Km 850 - Rio Paraná', 'timestamp' => now()->subDays(2), 'event' => 'in_transit'],
                (object)['position' => 'Km 1240 - Rio Paraná', 'timestamp' => now()->subHours(2), 'event' => 'in_transit'],
            ]
        ];

        return view('company.transfers.show', compact('transfer'));
    }

    /**
     * Formulario para editar transbordo.
     */
    public function edit($id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para editar transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden editar transbordos.');
        }

        // TODO: Obtener transbordo de la base de datos
        $transfer = (object)[
            'id' => $id,
            'transfer_number' => 'TR-2024-001',
            'barge_id' => 'BARCAZA-01',
            'route_id' => 'R001',
            'status' => 'loading',
        ];

        // Solo se pueden editar transbordos en estado 'loading'
        if ($transfer->status !== 'loading') {
            return back()->with('error', 'Solo se pueden editar transbordos en estado de carga.');
        }

        $availableBarges = collect([
            (object)['id' => 'BARCAZA-01', 'name' => 'Barcaza Río I', 'capacity' => 20],
            (object)['id' => 'BARCAZA-02', 'name' => 'Barcaza Río II', 'capacity' => 15],
        ]);

        return view('company.transfers.edit', compact('transfer', 'availableBarges'));
    }

    /**
     * Actualizar transbordo.
     */
    public function update(Request $request, $id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para actualizar transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar transbordos.');
        }

        $request->validate([
            'barge_id' => 'required|string|max:20',
            'containers' => 'required|array|min:1',
            'estimated_departure' => 'required|date|after:now',
        ]);

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // TODO: Actualizar registro en base de datos

            // TODO: Si hay cambios en contenedores vacíos, rectificar en DESA
            Log::info('Actualizando transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id,
                'barge_id' => $request->barge_id
            ]);

            DB::commit();

            return redirect()->route('company.transfers.show', $id)
                ->with('success', 'Transbordo actualizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withInput()
                ->with('error', 'Error al actualizar el transbordo: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar transbordo.
     */
    public function destroy($id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para eliminar transbordos.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden eliminar transbordos.');
        }

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // TODO: Verificar que el transbordo se pueda eliminar (estado loading)
            // TODO: Cancelar registros en DESA si existen
            // TODO: Eliminar de base de datos

            Log::info('Eliminando transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id
            ]);

            DB::commit();

            return redirect()->route('company.transfers.index')
                ->with('success', 'Transbordo eliminado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al eliminar el transbordo: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar estado de un transbordo.
     */
    public function updateStatus(Request $request, $id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para actualizar estados.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar estados.');
        }

        $request->validate([
            'status' => 'required|in:loading,in_transit,arrived,completed,cancelled',
            'current_position' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500'
        ]);

        $company = $this->getUserCompany();

        try {
            // TODO: Actualizar estado en base de datos
            // TODO: Registrar actualización de posición si aplica
            Log::info('Actualizando estado de transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id,
                'new_status' => $request->status,
                'position' => $request->current_position
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de transbordo', [
                'company_id' => $company->id,
                'transfer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF del transbordo.
     */
    public function generatePdf($id)
    {
        if (!$this->canPerform('view_transfers')) {
            abort(403, 'No tiene permisos para generar reportes.');
        }

        if (!$this->hasCompanyRole('Transbordos')) {
            abort(403, 'Su empresa no tiene el rol de Transbordos.');
        }

        // TODO: Implementar generación de PDF
        return response()->json([
            'message' => 'Generación de PDF en desarrollo',
            'transfer_id' => $id
        ]);
    }

    // ========================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ========================================

    /**
     * Generar ID único de transacción para webservices.
     */
    private function generateTransactionId(): string
    {
        return 'TRANS_' . now()->format('YmdHis') . '_' . rand(1000, 9999);
    }

    /**
     * Generar ID único para título de transporte.
     */
    private function generateTitleId(): string
    {
        return 'TT' . now()->format('YmdHis') . rand(100, 999);
    }

    /**
     * Generar ID único para tracking.
     */
    private function generateTrackingId(): string
    {
        $year = now()->format('Y');
        $code = 'AR'; // Código país, TODO: obtener dinámicamente
        $sequence = str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $checkDigit = '9'; // TODO: calcular dígito verificador

        return $year . $code . $sequence . $checkDigit;
    }

    /**
     * Obtener código de vía de transporte según documentación DESA.
     */
    private function getTransportCode(string $routeId): string
    {
        // Mapeo de rutas a códigos EDIFACT según documentación
        $transportCodes = [
            'R001' => '8', // Hidrovia para caso específico según docs
            'R002' => '1', // Marítimo
            'R003' => '8', // Hidrovia
        ];

        return $transportCodes[$routeId] ?? '8'; // Default: Hidrovia
    }
}
