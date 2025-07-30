<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ImportController extends Controller
{
    use UserHelper;

    /**
     * Vista principal de importación de datos.
     */
    public function index()
    {
        // 1. Verificar permisos básicos de importación
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar datos.');
        }

        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        // 2. Verificar que el usuario tenga una empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso específico a esta empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar permisos específicos de importación según el usuario
        if ($this->isUser()) {
            if (!$this->canImport()) {
                abort(403, 'Su cuenta no tiene permisos de importación configurados.');
            }
        }

        // Obtener información de importación
        $importOptions = $this->getImportOptions($company);
        $recentImports = $this->getRecentImports($company);
        $importStats = $this->getImportStatistics($company);

        return view('company.import.index', compact(
            'company',
            'importOptions',
            'recentImports',
            'importStats'
        ));
    }

    /**
     * Formulario para importación desde Excel.
     */
    public function excel()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar desde Excel.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        $excelTemplates = $this->getExcelTemplates($company);

        return view('company.import.excel', compact('company', 'excelTemplates'));
    }

    /**
     * Procesar importación desde Excel.
     */
    public function processExcel(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar desde Excel.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        // 4. Validación
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB máximo
            'import_type' => 'required|in:shipments,containers,titles,voyages',
            'has_headers' => 'boolean',
            'start_row' => 'nullable|integer|min:1|max:100',
            'preview_only' => 'boolean',
        ], [
            'excel_file.required' => 'Debe seleccionar un archivo Excel.',
            'excel_file.mimes' => 'El archivo debe ser .xlsx, .xls o .csv.',
            'excel_file.max' => 'El archivo no puede ser mayor a 10MB.',
            'import_type.required' => 'Debe seleccionar el tipo de importación.',
            'import_type.in' => 'Tipo de importación no válido.',
        ]);

        try {
            // Guardar archivo temporalmente
            $filePath = $request->file('excel_file')->store('temp/imports');

            // Procesar según tipo de importación
            $result = $this->processExcelByType(
                $filePath,
                $request->import_type,
                $company,
                [
                    'has_headers' => $request->boolean('has_headers', true),
                    'start_row' => $request->start_row ?? 1,
                    'preview_only' => $request->boolean('preview_only', false),
                ]
            );

            // Limpiar archivo temporal
            Storage::delete($filePath);

            if ($request->boolean('preview_only')) {
                return response()->json([
                    'success' => true,
                    'preview' => $result,
                    'message' => 'Vista previa generada correctamente.',
                ]);
            }

            return redirect()->route('company.import.history')
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            // Limpiar archivo temporal en caso de error
            if (isset($filePath)) {
                Storage::delete($filePath);
            }

            return back()
                ->withInput()
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Formulario para importación desde XML.
     */
    public function xml()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar desde XML.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        $xmlFormats = $this->getXmlFormats($company);

        return view('company.import.xml', compact('company', 'xmlFormats'));
    }

    /**
     * Procesar importación desde XML.
     */
    public function processXml(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar desde XML.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        // 4. Validación
        $request->validate([
            'xml_file' => 'required|file|mimes:xml|max:5120', // 5MB máximo
            'xml_format' => 'required|in:anticipada,micdta,cuscar,edi',
            'validate_structure' => 'boolean',
        ], [
            'xml_file.required' => 'Debe seleccionar un archivo XML.',
            'xml_file.mimes' => 'El archivo debe ser .xml.',
            'xml_file.max' => 'El archivo no puede ser mayor a 5MB.',
            'xml_format.required' => 'Debe seleccionar el formato XML.',
        ]);

        try {
            // Guardar archivo temporalmente
            $filePath = $request->file('xml_file')->store('temp/imports');

            // Procesar XML según formato
            $result = $this->processXmlByFormat(
                $filePath,
                $request->xml_format,
                $company,
                [
                    'validate_structure' => $request->boolean('validate_structure', true),
                ]
            );

            // Limpiar archivo temporal
            Storage::delete($filePath);

            return redirect()->route('company.import.history')
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            // Limpiar archivo temporal en caso de error
            if (isset($filePath)) {
                Storage::delete($filePath);
            }

            return back()
                ->withInput()
                ->with('error', 'Error al procesar el archivo XML: ' . $e->getMessage());
        }
    }

    /**
     * Formulario para importación EDI.
     */
    public function edi()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar archivos EDI.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        $ediStandards = $this->getEdiStandards($company);

        return view('company.import.edi', compact('company', 'ediStandards'));
    }

    /**
     * Procesar importación EDI.
     */
    public function processEdi(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('import')) {
            abort(403, 'No tiene permisos para importar archivos EDI.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 3. Verificar permisos específicos
        if ($this->isUser() && !$this->canImport()) {
            abort(403, 'Su cuenta no tiene permisos de importación configurados.');
        }

        // 4. Validación
        $request->validate([
            'edi_file' => 'required|file|mimes:txt,edi|max:2048', // 2MB máximo
            'edi_standard' => 'required|in:edifact,x12,tradacoms',
            'delimiter' => 'nullable|string|max:1',
        ], [
            'edi_file.required' => 'Debe seleccionar un archivo EDI.',
            'edi_file.mimes' => 'El archivo debe ser .txt o .edi.',
            'edi_file.max' => 'El archivo no puede ser mayor a 2MB.',
            'edi_standard.required' => 'Debe seleccionar el estándar EDI.',
        ]);

        try {
            // Guardar archivo temporalmente
            $filePath = $request->file('edi_file')->store('temp/imports');

            // Procesar EDI según estándar
            $result = $this->processEdiByStandard(
                $filePath,
                $request->edi_standard,
                $company,
                [
                    'delimiter' => $request->delimiter ?? '+',
                ]
            );

            // Limpiar archivo temporal
            Storage::delete($filePath);

            return redirect()->route('company.import.history')
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            // Limpiar archivo temporal en caso de error
            if (isset($filePath)) {
                Storage::delete($filePath);
            }

            return back()
                ->withInput()
                ->with('error', 'Error al procesar el archivo EDI: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar historial de importaciones.
     */
    public function history(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_reports')) {
            abort(403, 'No tiene permisos para ver el historial de importaciones.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // TODO: Implementar cuando tengamos tabla de imports
        $imports = collect(); // Import::where('company_id', $company->id)

        return view('company.import.history', compact('company', 'imports'));
    }

    /**
     * Mostrar detalles de una importación específica.
     */
    public function showImport($importId)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('view_reports')) {
            abort(403, 'No tiene permisos para ver el historial de importaciones.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 2. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // TODO: Implementar cuando tengamos tabla de imports
        // $import = Import::where('company_id', $company->id)->findOrFail($importId);

        return view('company.import.show', compact('company', 'importId'));
    }

    // ========================================
    // MÉTODOS HELPER PRIVADOS
    // ========================================

    /**
     * Obtener opciones de importación disponibles.
     */
    private function getImportOptions(Company $company): array
    {
        $roles = $company->getRoles();

        $options = [
            'excel' => [
                'name' => 'Excel/CSV',
                'description' => 'Importar desde archivos Excel (.xlsx, .xls) o CSV',
                'formats' => ['xlsx', 'xls', 'csv'],
                'max_size' => '10MB',
                'available' => true,
            ],
            'xml' => [
                'name' => 'XML',
                'description' => 'Importar desde archivos XML estructurados',
                'formats' => ['xml'],
                'max_size' => '5MB',
                'available' => in_array('Cargas', $roles) || in_array('Desconsolidador', $roles),
            ],
            'edi' => [
                'name' => 'EDI',
                'description' => 'Importar desde archivos EDI (Electronic Data Interchange)',
                'formats' => ['txt', 'edi'],
                'max_size' => '2MB',
                'available' => in_array('Cargas', $roles),
            ],
            'cuscar' => [
                'name' => 'CUSCAR',
                'description' => 'Importar manifiestos en formato CUSCAR',
                'formats' => ['txt'],
                'max_size' => '5MB',
                'available' => in_array('Cargas', $roles),
            ],
        ];

        return $options;
    }

    /**
     * Obtener plantillas de Excel disponibles.
     */
    private function getExcelTemplates(Company $company): array
    {
        $roles = $company->getRoles();
        $templates = [];

        if (in_array('Cargas', $roles)) {
            $templates['shipments'] = [
                'name' => 'Cargas/Envíos',
                'description' => 'Plantilla para importar información de cargas',
                'required_columns' => [
                    'numero_conocimiento',
                    'fecha_embarque',
                    'puerto_origen',
                    'puerto_destino',
                    'consignee',
                    'peso_bruto',
                    'descripcion_mercancia',
                ],
                'optional_columns' => [
                    'shipper',
                    'notify_party',
                    'marca_bultos',
                    'numero_bultos',
                    'observaciones',
                ],
            ];

            $templates['containers'] = [
                'name' => 'Contenedores',
                'description' => 'Plantilla para importar información de contenedores',
                'required_columns' => [
                    'numero_contenedor',
                    'tipo_contenedor',
                    'peso_bruto',
                    'peso_neto',
                ],
                'optional_columns' => [
                    'sello',
                    'linea_naviera',
                    'estado',
                ],
            ];
        }

        if (in_array('Desconsolidador', $roles)) {
            $templates['titles'] = [
                'name' => 'Títulos',
                'description' => 'Plantilla para importar títulos madre e hijos',
                'required_columns' => [
                    'numero_titulo',
                    'tipo_titulo',
                    'titulo_madre',
                    'peso',
                    'descripcion',
                ],
                'optional_columns' => [
                    'observaciones',
                    'fecha_vencimiento',
                ],
            ];
        }

        if (in_array('Transbordos', $roles)) {
            $templates['voyages'] = [
                'name' => 'Viajes',
                'description' => 'Plantilla para importar información de viajes',
                'required_columns' => [
                    'numero_viaje',
                    'nave',
                    'fecha_salida',
                    'puerto_origen',
                    'puerto_destino',
                ],
                'optional_columns' => [
                    'fecha_llegada_estimada',
                    'capitan',
                    'observaciones',
                ],
            ];
        }

        return $templates;
    }

    /**
     * Obtener formatos XML disponibles.
     */
    private function getXmlFormats(Company $company): array
    {
        $roles = $company->getRoles();
        $formats = [];

        if (in_array('Cargas', $roles)) {
            $formats['anticipada'] = [
                'name' => 'Información Anticipada',
                'description' => 'XML de información anticipada marítima (AFIP)',
                'namespace' => 'ar.gov.afip.dia.serviciosWeb.wgesinformacionanticipada',
            ];

            $formats['micdta'] = [
                'name' => 'MIC/DTA',
                'description' => 'XML para registro MIC/DTA (AFIP)',
                'namespace' => 'ar.gov.afip.dia.serviciosWeb.wgesregsintia2',
            ];
        }

        if (in_array('Desconsolidador', $roles)) {
            $formats['cuscar'] = [
                'name' => 'CUSCAR',
                'description' => 'Manifiestos en formato CUSCAR UN/EDIFACT',
                'namespace' => 'un.edifact.cuscar',
            ];
        }

        return $formats;
    }

    /**
     * Obtener estándares EDI disponibles.
     */
    private function getEdiStandards(Company $company): array
    {
        return [
            'edifact' => [
                'name' => 'UN/EDIFACT',
                'description' => 'Estándar internacional UN/EDIFACT',
                'separator' => '+',
                'terminator' => "'",
            ],
            'x12' => [
                'name' => 'ANSI X12',
                'description' => 'Estándar americano ANSI X12',
                'separator' => '*',
                'terminator' => '~',
            ],
            'tradacoms' => [
                'name' => 'TRADACOMS',
                'description' => 'Estándar británico TRADACOMS',
                'separator' => '+',
                'terminator' => "'",
            ],
        ];
    }

    /**
     * Procesar Excel según tipo.
     */
    private function processExcelByType(string $filePath, string $type, Company $company, array $options): array
    {
        switch ($type) {
            case 'shipments':
                return $this->processShipmentsExcel($filePath, $company, $options);
            case 'containers':
                return $this->processContainersExcel($filePath, $company, $options);
            case 'titles':
                return $this->processTitlesExcel($filePath, $company, $options);
            case 'voyages':
                return $this->processTripsExcel($filePath, $company, $options);
            default:
                throw new \Exception('Tipo de importación no válido: ' . $type);
        }
    }

    /**
     * Procesar archivo de cargas/envíos desde Excel.
     */
    private function processShipmentsExcel(string $filePath, Company $company, array $options): array
    {
        // TODO: Implementar cuando esté el modelo Shipment
        $previewData = [
            ['Conocimiento' => 'BL001', 'Puerto Origen' => 'Buenos Aires', 'Puerto Destino' => 'Asunción'],
            ['Conocimiento' => 'BL002', 'Puerto Origen' => 'Montevideo', 'Puerto Destino' => 'Buenos Aires'],
        ];

        if ($options['preview_only']) {
            return [
                'type' => 'shipments',
                'rows_found' => count($previewData),
                'preview' => array_slice($previewData, 0, 5),
                'valid_rows' => count($previewData),
                'errors' => [],
            ];
        }

        // Aquí iría la lógica real de importación
        return [
            'imported' => count($previewData),
            'errors' => 0,
            'message' => count($previewData) . ' cargas importadas correctamente.',
        ];
    }

    /**
     * Procesar archivo de contenedores desde Excel.
     */
    private function processContainersExcel(string $filePath, Company $company, array $options): array
    {
        // TODO: Implementar cuando esté el modelo Container
        $previewData = [
            ['Contenedor' => 'UACU1234567', 'Tipo' => '20DC', 'Peso' => '18500'],
            ['Contenedor' => 'TCLU7654321', 'Tipo' => '40HC', 'Peso' => '26800'],
        ];

        if ($options['preview_only']) {
            return [
                'type' => 'containers',
                'rows_found' => count($previewData),
                'preview' => array_slice($previewData, 0, 5),
                'valid_rows' => count($previewData),
                'errors' => [],
            ];
        }

        return [
            'imported' => count($previewData),
            'errors' => 0,
            'message' => count($previewData) . ' contenedores importados correctamente.',
        ];
    }

    /**
     * Procesar archivo de títulos desde Excel.
     */
    private function processTitlesExcel(string $filePath, Company $company, array $options): array
    {
        // TODO: Implementar cuando esté el modelo Title
        $previewData = [
            ['Título' => 'T001', 'Tipo' => 'Madre', 'Peso' => '25000'],
            ['Título' => 'T001-01', 'Tipo' => 'Hijo', 'Peso' => '12500'],
        ];

        if ($options['preview_only']) {
            return [
                'type' => 'titles',
                'rows_found' => count($previewData),
                'preview' => array_slice($previewData, 0, 5),
                'valid_rows' => count($previewData),
                'errors' => [],
            ];
        }

        return [
            'imported' => count($previewData),
            'errors' => 0,
            'message' => count($previewData) . ' títulos importados correctamente.',
        ];
    }

    /**
     * Procesar archivo de viajes desde Excel.
     */
    private function processTripsExcel(string $filePath, Company $company, array $options): array
    {
        // TODO: Implementar cuando esté el modelo voyage
        $previewData = [
            ['Viaje' => 'V001', 'Nave' => 'MS Explorer', 'Origen' => 'Buenos Aires'],
            ['Viaje' => 'V002', 'Nave' => 'MS Navigator', 'Origen' => 'Montevideo'],
        ];

        if ($options['preview_only']) {
            return [
                'type' => 'voyages',
                'rows_found' => count($previewData),
                'preview' => array_slice($previewData, 0, 5),
                'valid_rows' => count($previewData),
                'errors' => [],
            ];
        }

        return [
            'imported' => count($previewData),
            'errors' => 0,
            'message' => count($previewData) . ' viajes importados correctamente.',
        ];
    }

    /**
     * Procesar XML según formato.
     */
    private function processXmlByFormat(string $filePath, string $format, Company $company, array $options): array
    {
        // TODO: Implementar procesamiento XML real
        return [
            'imported' => 1,
            'errors' => 0,
            'message' => "Archivo XML '{$format}' procesado correctamente.",
        ];
    }

    /**
     * Procesar EDI según estándar.
     */
    private function processEdiByStandard(string $filePath, string $standard, Company $company, array $options): array
    {
        // TODO: Implementar procesamiento EDI real
        return [
            'imported' => 1,
            'errors' => 0,
            'message' => "Archivo EDI '{$standard}' procesado correctamente.",
        ];
    }

    /**
     * Obtener importaciones recientes.
     */
    private function getRecentImports(Company $company): array
    {
        // TODO: Implementar cuando tengamos tabla de imports
        return [];
    }

    /**
     * Obtener estadísticas de importación.
     */
    private function getImportStatistics(Company $company): array
    {
        // TODO: Implementar cuando tengamos tabla de imports
        return [
            'total_imports' => 0,
            'imports_this_month' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
            'most_used_format' => 'Excel',
        ];
    }
}
