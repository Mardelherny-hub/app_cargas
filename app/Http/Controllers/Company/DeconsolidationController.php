<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gestión de Desconsolidaciones
 *
 * Funcionalidades implementadas:
 * - Gestión de desconsolidaciones (CRUD)
 * - Títulos madre y títulos hijos
 * - Integración con webservices DESA
 * - Reportes de consolidación
 *
 * Webservices soportados:
 * - RegistrarTitulosDesconsolidador
 * - RectificarTitulosDesconsolidador
 * - EliminarTitulosDesconsolidador
 */
class DeconsolidationController extends Controller
{
    use UserHelper;

    /**
     * Lista de desconsolidaciones.
     */
    public function index(Request $request)
    {
        // Verificar permisos de desconsolidación
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'Su empresa no tiene permisos para gestionar desconsolidaciones.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No tiene una empresa asignada.');
        }

        // Verificar que la empresa tenga rol "Desconsolidador"
        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        // Filtros de búsqueda
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        // TODO: Implementar query de desconsolidaciones cuando se cree el modelo
        // Por ahora, datos de ejemplo para desarrollo
        $deconsolidations = collect([
            (object)[
                'id' => 1,
                'titulo_madre' => 'TM-2024-001',
                'viaje_id' => '202407100001AR',
                'contenedores_count' => 3,
                'titulos_hijos_count' => 12,
                'status' => 'registered',
                'created_at' => now()->subDays(2),
                'last_ws_sync' => now()->subHours(1),
            ],
            (object)[
                'id' => 2,
                'titulo_madre' => 'TM-2024-002',
                'viaje_id' => '202407090001AR',
                'contenedores_count' => 2,
                'titulos_hijos_count' => 8,
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'last_ws_sync' => null,
            ],
        ]);

        // Estadísticas para el dashboard
        $stats = [
            'total_deconsolidations' => $deconsolidations->count(),
            'pending_sync' => $deconsolidations->where('last_ws_sync', null)->count(),
            'total_containers' => $deconsolidations->sum('contenedores_count'),
            'total_titulo_hijos' => $deconsolidations->sum('titulos_hijos_count'),
        ];

        return view('company.deconsolidation.index', compact(
            'deconsolidations',
            'stats',
            'filters'
        ));
    }

    /**
     * Formulario para crear nueva desconsolidación.
     */
    public function create()
    {
        // Verificar permisos
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para crear desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        // Solo company-admin puede crear nuevas desconsolidaciones
        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden crear desconsolidaciones.');
        }

        // TODO: Obtener viajes disponibles de la empresa
        $availableTrips = collect([
            (object)['id' => '202407100001AR', 'origen' => 'Buenos Aires', 'destino' => 'Asunción'],
            (object)['id' => '202407110001AR', 'origen' => 'Rosario', 'destino' => 'Montevideo'],
        ]);

        return view('company.deconsolidation.create', compact('availableTrips'));
    }

    /**
     * Guardar nueva desconsolidación.
     */
    public function store(Request $request)
    {
        // Verificar permisos
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para crear desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden crear desconsolidaciones.');
        }

        $request->validate([
            'viaje_id' => 'required|string|max:16',
            'titulo_madre' => 'required|string|max:50',
            'contenedores' => 'required|array|min:1',
            'contenedores.*.id' => 'required|string',
            'contenedores.*.caracteristicas' => 'nullable|string',
            'titulos_hijos' => 'required|array|min:1',
            'titulos_hijos.*.numero' => 'required|string',
            'titulos_hijos.*.descripcion' => 'required|string',
        ]);

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // TODO: Crear registro de desconsolidación en base de datos

            // Preparar datos para webservice RegistrarTitulosDesconsolidador
            $wsData = [
                'IdTransaccion' => $this->generateTransactionId(),
                'InformacionTitulosDesconsolidadorDoc' => [
                    'IdentificadorViaje' => $request->viaje_id,
                    'TitulosDesconsolidador' => $this->formatTitulosForWebservice(
                        $request->titulo_madre,
                        $request->contenedores,
                        $request->titulos_hijos
                    )
                ]
            ];

            // TODO: Enviar a webservice DESA - RegistrarTitulosDesconsolidador
            Log::info('Registrando títulos desconsolidador en DESA', [
                'company_id' => $company->id,
                'viaje_id' => $request->viaje_id,
                'ws_data' => $wsData
            ]);

            DB::commit();

            return redirect()->route('company.deconsolidation.index')
                ->with('success', 'Desconsolidación creada correctamente. Sincronización con DESA pendiente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear desconsolidación', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->withInput()
                ->with('error', 'Error al crear la desconsolidación: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalles de una desconsolidación.
     */
    public function show($id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para ver desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        $company = $this->getUserCompany();

        // TODO: Obtener desconsolidación de la base de datos
        // Por ahora, datos de ejemplo
        $deconsolidation = (object)[
            'id' => $id,
            'titulo_madre' => 'TM-2024-001',
            'viaje_id' => '202407100001AR',
            'status' => 'registered',
            'created_at' => now()->subDays(2),
            'last_ws_sync' => now()->subHours(1),
            'ws_status' => 'success',
            'contenedores' => [
                (object)['id' => 'CONT001', 'caracteristicas' => '20 pies', 'estado' => 'registrado'],
                (object)['id' => 'CONT002', 'caracteristicas' => '40 pies', 'estado' => 'registrado'],
            ],
            'titulos_hijos' => [
                (object)['numero' => 'TH-001', 'descripcion' => 'Mercadería A', 'estado' => 'registrado'],
                (object)['numero' => 'TH-002', 'descripcion' => 'Mercadería B', 'estado' => 'registrado'],
            ]
        ];

        return view('company.deconsolidation.show', compact('deconsolidation'));
    }

    /**
     * Formulario para editar desconsolidación.
     */
    public function edit($id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para editar desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden editar desconsolidaciones.');
        }

        // TODO: Obtener desconsolidación de la base de datos
        $deconsolidation = (object)[
            'id' => $id,
            'titulo_madre' => 'TM-2024-001',
            'viaje_id' => '202407100001AR',
            'status' => 'pending',
        ];

        return view('company.deconsolidation.edit', compact('deconsolidation'));
    }

    /**
     * Actualizar desconsolidación.
     */
    public function update(Request $request, $id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para actualizar desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar desconsolidaciones.');
        }

        $request->validate([
            'titulo_madre' => 'required|string|max:50',
            'contenedores' => 'required|array|min:1',
            'titulos_hijos' => 'required|array|min:1',
        ]);

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // TODO: Actualizar registro en base de datos

            // Preparar datos para webservice RectificarTitulosDesconsolidador
            $wsData = [
                'IdTransaccion' => $this->generateTransactionId(),
                'InformacionTitulosDesconsolidadorDoc' => [
                    'IdentificadorViaje' => $request->viaje_id,
                    'TitulosDesconsolidador' => $this->formatTitulosForWebservice(
                        $request->titulo_madre,
                        $request->contenedores,
                        $request->titulos_hijos
                    )
                ]
            ];

            // TODO: Enviar a webservice DESA - RectificarTitulosDesconsolidador
            Log::info('Rectificando títulos desconsolidador en DESA', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'ws_data' => $wsData
            ]);

            DB::commit();

            return redirect()->route('company.deconsolidation.show', $id)
                ->with('success', 'Desconsolidación actualizada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar desconsolidación', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withInput()
                ->with('error', 'Error al actualizar la desconsolidación: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar desconsolidación.
     */
    public function destroy($id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para eliminar desconsolidaciones.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden eliminar desconsolidaciones.');
        }

        $company = $this->getUserCompany();

        try {
            DB::beginTransaction();

            // Preparar datos para webservice EliminarTitulosDesconsolidador
            $wsData = [
                'IdTransaccion' => $this->generateTransactionId(),
                'InformacionTitulosDesconsolidadorDoc' => [
                    'IdentificadorViaje' => 'TODO_GET_FROM_DB', // TODO: Obtener de BD
                    'PuertosConocimientos' => [] // TODO: Formatear según documentación
                ]
            ];

            // TODO: Enviar a webservice DESA - EliminarTitulosDesconsolidador
            Log::info('Eliminando títulos desconsolidador en DESA', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'ws_data' => $wsData
            ]);

            // TODO: Eliminar de base de datos

            DB::commit();

            return redirect()->route('company.deconsolidation.index')
                ->with('success', 'Desconsolidación eliminada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar desconsolidación', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al eliminar la desconsolidación: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar estado de una desconsolidación.
     */
    public function updateStatus(Request $request, $id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para actualizar estados.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        if ($this->isUser()) {
            abort(403, 'Solo los administradores de empresa pueden actualizar estados.');
        }

        $request->validate([
            'status' => 'required|in:pending,registered,rectified,cancelled'
        ]);

        $company = $this->getUserCompany();

        try {
            // TODO: Actualizar estado en base de datos
            Log::info('Actualizando estado de desconsolidación', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'new_status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de desconsolidación', [
                'company_id' => $company->id,
                'deconsolidation_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de la desconsolidación.
     */
    public function generatePdf($id)
    {
        if (!$this->canPerform('view_deconsolidation')) {
            abort(403, 'No tiene permisos para generar reportes.');
        }

        if (!$this->hasCompanyRole('Desconsolidador')) {
            abort(403, 'Su empresa no tiene el rol de Desconsolidador.');
        }

        // TODO: Implementar generación de PDF
        // Por ahora retornar mensaje de desarrollo
        return response()->json([
            'message' => 'Generación de PDF en desarrollo',
            'deconsolidation_id' => $id
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
        return 'DESCON_' . now()->format('YmdHis') . '_' . rand(1000, 9999);
    }

    /**
     * Formatear títulos para envío a webservice según documentación DESA.
     */
    private function formatTitulosForWebservice(string $tituloMadre, array $contenedores, array $titulosHijos): array
    {
        // Estructura según documentación DESA - RegistrarTitulosDesconsolidador
        return [
            'TituloMadre' => [
                'Numero' => $tituloMadre,
                'Contenedores' => array_map(function($contenedor) {
                    return [
                        'Id' => $contenedor['id'],
                        'Caracteristicas' => $contenedor['caracteristicas'] ?? '',
                        'Condicion' => 'LLENO', // Por defecto
                    ];
                }, $contenedores)
            ],
            'TitulosHijos' => array_map(function($titulo) {
                return [
                    'Numero' => $titulo['numero'],
                    'Descripcion' => $titulo['descripcion'],
                    'Estado' => 'PENDIENTE' // Por defecto
                ];
            }, $titulosHijos)
        ];
    }
}
