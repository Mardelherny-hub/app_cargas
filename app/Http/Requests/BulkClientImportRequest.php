<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use App\Traits\UserHelper;

/**
 * FASE 3 - VALIDACIONES Y SERVICIOS
 *
 * Form Request for bulk client import validation
 *
 * Handles:
 * - File validation (CSV, Excel formats)
 * - Import configuration validation
 * - Authorization checks for bulk operations
 * - File size and structure validation
 * - Import options validation
 */
/**
 * @method bool has(string $key)
 * @method mixed input(string $key, mixed $default = null)
 * @method \Illuminate\Http\UploadedFile|null file(string $key)
 * @method void merge(array $input)
 */
class BulkClientImportRequest extends FormRequest
{
    use UserHelper;

    /**
     * Maximum file size in KB (10MB)
     */
    public const MAX_FILE_SIZE = 10240;

    /**
     * Maximum number of records to process
     */
    public const MAX_RECORDS = 5000;

    /**
     * Allowed file extensions
     */
    public const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    /**
     * Allowed MIME types
     */
    public const ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        // Super admin can always perform bulk imports
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin can perform bulk imports for their company
        if ($user->hasRole('company-admin')) {
            return $user->getUserCompany() !== null;
        }

        // Users with explicit bulk import permission
        if ($user->can('bulk_import_clients')) {
            return true;
        }

        // Users with general client creation permission (with company context)
        if ($user->can('create_clients') && $user->getUserCompany()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // File validation
            'import_file' => [
                'required',
                'file',
                'max:' . self::MAX_FILE_SIZE,
                function ($attribute, $value, $fail) {
                    $this->validateFileType($attribute, $value, $fail);
                },
                function ($attribute, $value, $fail) {
                    $this->validateFileStructure($attribute, $value, $fail);
                },
            ],

            // Import configuration
            'import_type' => [
                'required',
                'string',
                Rule::in(['clients', 'client_data', 'mixed_data'])
            ],

            // Processing options
            'skip_duplicates' => ['nullable', 'boolean'],
            'auto_extract_tax_ids' => ['nullable', 'boolean'],
            'apply_suggestions' => ['nullable', 'boolean'],
            'verify_tax_ids' => ['nullable', 'boolean'],
            'chunk_size' => ['nullable', 'integer', 'min:10', 'max:500'],

            // File structure options
            'has_headers' => ['nullable', 'boolean'],
            'start_row' => ['nullable', 'integer', 'min:1', 'max:100'],
            'delimiter' => ['nullable', 'string', 'max:1', Rule::in([',', ';', '\t', '|'])],
            'encoding' => ['nullable', 'string', Rule::in(['UTF-8', 'ISO-8859-1', 'Windows-1252'])],

            // Field mapping (optional)
            'field_mapping' => ['nullable', 'array'],
            'field_mapping.tax_id' => ['nullable', 'string', 'max:50'],
            'field_mapping.business_name' => ['nullable', 'string', 'max:50'],
            'field_mapping.country' => ['nullable', 'string', 'max:50'],
            'field_mapping.client_type' => ['nullable', 'string', 'max:50'],

            // Preview options
            'preview_only' => ['nullable', 'boolean'],
            'preview_rows' => ['nullable', 'integer', 'min:5', 'max:100'],

            // Company context
            'company_id' => [
                'sometimes',
                'integer',
                'exists:companies,id',
                function ($attribute, $value, $fail) {
                    $this->validateCompanyAccess($attribute, $value, $fail);
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'import_file.required' => 'Debe seleccionar un archivo para importar.',
            'import_file.file' => 'El archivo seleccionado no es válido.',
            'import_file.max' => 'El archivo no puede ser mayor a ' . self::MAX_FILE_SIZE . 'KB (' . round(self::MAX_FILE_SIZE/1024, 1) . 'MB).',
            'import_type.required' => 'Debe especificar el tipo de importación.',
            'import_type.in' => 'El tipo de importación seleccionado no es válido.',
            'chunk_size.min' => 'El tamaño de lote debe ser al menos 10 registros.',
            'chunk_size.max' => 'El tamaño de lote no puede ser mayor a 500 registros.',
            'start_row.min' => 'La fila de inicio debe ser al menos 1.',
            'start_row.max' => 'La fila de inicio no puede ser mayor a 100.',
            'delimiter.max' => 'El delimitador debe ser un solo carácter.',
            'delimiter.in' => 'El delimitador seleccionado no es válido.',
            'encoding.in' => 'La codificación seleccionada no es válida.',
            'preview_rows.min' => 'Debe previsualizar al menos 5 filas.',
            'preview_rows.max' => 'No puede previsualizar más de 100 filas.',
            'company_id.exists' => 'La empresa seleccionada no es válida.',
            'field_mapping.*.max' => 'Los nombres de campo no pueden tener más de 50 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'import_file' => 'archivo de importación',
            'import_type' => 'tipo de importación',
            'skip_duplicates' => 'omitir duplicados',
            'auto_extract_tax_ids' => 'extracción automática de CUIT/RUC',
            'apply_suggestions' => 'aplicar sugerencias',
            'verify_tax_ids' => 'verificar CUIT/RUC',
            'chunk_size' => 'tamaño de lote',
            'has_headers' => 'tiene encabezados',
            'start_row' => 'fila de inicio',
            'delimiter' => 'delimitador',
            'encoding' => 'codificación',
            'field_mapping' => 'mapeo de campos',
            'preview_only' => 'solo previsualización',
            'preview_rows' => 'filas de previsualización',
            'company_id' => 'empresa',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // Set default values for processing options
        if (!$this->has('skip_duplicates') || $this->input('skip_duplicates') === null) {
            $mergeData['skip_duplicates'] = true;
        }

        if (!$this->has('auto_extract_tax_ids') || $this->input('auto_extract_tax_ids') === null) {
            $mergeData['auto_extract_tax_ids'] = true;
        }

        if (!$this->has('apply_suggestions') || $this->input('apply_suggestions') === null) {
            $mergeData['apply_suggestions'] = false;
        }

        if (!$this->has('verify_tax_ids') || $this->input('verify_tax_ids') === null) {
            $mergeData['verify_tax_ids'] = true;
        }

        if (!$this->has('chunk_size') || $this->input('chunk_size') === null) {
            $mergeData['chunk_size'] = 100;
        }

        // Set default values for file structure
        if (!$this->has('has_headers') || $this->input('has_headers') === null) {
            $mergeData['has_headers'] = true;
        }

        if (!$this->has('start_row') || $this->input('start_row') === null) {
            $hasHeaders = $this->input('has_headers', true);
            $mergeData['start_row'] = $hasHeaders ? 2 : 1;
        }

        if (!$this->has('delimiter') || $this->input('delimiter') === null) {
            $mergeData['delimiter'] = ',';
        }

        if (!$this->has('encoding') || $this->input('encoding') === null) {
            $mergeData['encoding'] = 'UTF-8';
        }

        // Set default values for preview
        if (!$this->has('preview_rows') || $this->input('preview_rows') === null) {
            $mergeData['preview_rows'] = 10;
        }

        // Set company ID if not provided
        if (!$this->has('company_id') || $this->input('company_id') === null) {
            $user = Auth::user();
            if ($user) {
                $company = $user->getUserCompany();
                if ($company) {
                    $mergeData['company_id'] = $company->id;
                }
            }
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->performAdditionalValidation($validator);
        });
    }

    /**
     * Perform additional business rule validation.
     */
    protected function performAdditionalValidation($validator): void
    {
        if (!$validator->errors()->has('import_file')) {
            $this->validateFileContent($validator);
        }

        $this->validateProcessingConfiguration($validator);
    }

    /**
     * Validate file type and extension.
     */
    protected function validateFileType(string $attribute, $value, $fail): void
    {
        if (!($value instanceof UploadedFile)) {
            $fail('El archivo no es válido.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $fail('El archivo debe ser de tipo: ' . implode(', ', self::ALLOWED_EXTENSIONS));
            return;
        }

        $mimeType = $value->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $fail('El tipo de archivo no es válido.');
            return;
        }
    }

    /**
     * Basic file structure validation.
     */
    protected function validateFileStructure(string $attribute, $value, $fail): void
    {
        if (!($value instanceof UploadedFile)) {
            return;
        }

        if (!is_readable($value->getPathname())) {
            $fail('El archivo no se puede leer.');
            return;
        }

        if ($value->getSize() === 0) {
            $fail('El archivo está vacío.');
            return;
        }
    }

    /**
     * Validate file content and estimate record count.
     */
    protected function validateFileContent($validator): void
    {
        try {
            $file = $this->file('import_file');
            if (!$file) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'csv') {
                $this->validateCsvContent($file, $validator);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $this->validateExcelContent($file, $validator);
            }
        } catch (\Exception $e) {
            $validator->errors()->add('import_file', 'Error al validar el contenido del archivo: ' . $e->getMessage());
        }
    }

    /**
     * Validate CSV file content.
     */
    protected function validateCsvContent(UploadedFile $file, $validator): void
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            $validator->errors()->add('import_file', 'No se puede abrir el archivo CSV.');
            return;
        }

        $delimiter = $this->input('delimiter', ',');
        $rowCount = 0;
        $hasData = false;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowCount++;

            if (array_filter($row, function($cell) { return !empty(trim($cell ?? '')); })) {
                $hasData = true;
            }

            if ($rowCount > self::MAX_RECORDS + 100) {
                break;
            }
        }

        fclose($handle);

        if (!$hasData) {
            $validator->errors()->add('import_file', 'El archivo CSV no contiene datos válidos.');
            return;
        }

        $hasHeaders = filter_var($this->input('has_headers', true), FILTER_VALIDATE_BOOLEAN);
        $dataRows = $hasHeaders ? $rowCount - 1 : $rowCount;

        if ($dataRows > self::MAX_RECORDS) {
            $validator->errors()->add('import_file',
                "El archivo contiene demasiados registros ({$dataRows}). El máximo permitido es " . self::MAX_RECORDS . ".");
        }
    }

    /**
     * Validate Excel file content.
     */
    protected function validateExcelContent(UploadedFile $file, $validator): void
    {
        try {
            $fileSize = $file->getSize();
            $estimatedRows = $fileSize / 100; // Rough estimate

            if ($estimatedRows > self::MAX_RECORDS * 2) {
                $validator->errors()->add('import_file',
                    "El archivo Excel es muy grande. Considere dividirlo en archivos más pequeños.");
            }
        } catch (\Exception $e) {
            $validator->errors()->add('import_file', 'Error al validar el archivo Excel.');
        }
    }

    /**
     * Validate processing configuration.
     */
    protected function validateProcessingConfiguration($validator): void
    {
        $chunkSize = (int) $this->input('chunk_size', 100);
        $verifyTaxIds = filter_var($this->input('verify_tax_ids', true), FILTER_VALIDATE_BOOLEAN);

        if ($verifyTaxIds && $chunkSize > 200) {
            $validator->errors()->add('chunk_size',
                'Con verificación de CUIT/RUC activada, el tamaño de lote no debería ser mayor a 200.');
        }

        if ($this->has('field_mapping') && $this->input('field_mapping') !== null) {
            $mapping = $this->input('field_mapping', []);

            if (empty($mapping['tax_id']) && empty($mapping['business_name'])) {
                $validator->errors()->add('field_mapping',
                    'Debe mapear al menos el campo CUIT/RUC o Razón Social.');
            }
        }
    }

    /**
     * Validate user has access to the specified company.
     */
    protected function validateCompanyAccess(string $attribute, $value, $fail): void
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return;
        }

        if ($user->hasRole('company-admin')) {
            $userCompany = $user->getUserCompany();
            if (!$userCompany || $userCompany->id != $value) {
                $fail('No tiene permisos para importar clientes en esta empresa.');
            }
            return;
        }

        $userCompany = $user->getUserCompany();
        if (!$userCompany || $userCompany->id != $value) {
            $fail('No tiene permisos para importar clientes en esta empresa.');
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (!isset($validated['company_id'])) {
            $user = Auth::user();
            if ($user) {
                $company = $user->getUserCompany();
                if ($company) {
                    $validated['company_id'] = $company->id;
                }
            }
        }

        $validated['user_id'] = Auth::id();

        return $key ? data_get($validated, $key, $default) : $validated;
    }

    /**
     * Get import configuration for the job.
     */
    public function getImportConfiguration(): array
    {
        return [
            'skip_duplicates' => filter_var($this->input('skip_duplicates', true), FILTER_VALIDATE_BOOLEAN),
            'auto_extract_tax_ids' => filter_var($this->input('auto_extract_tax_ids', true), FILTER_VALIDATE_BOOLEAN),
            'apply_suggestions' => filter_var($this->input('apply_suggestions', false), FILTER_VALIDATE_BOOLEAN),
            'verify_tax_ids' => filter_var($this->input('verify_tax_ids', true), FILTER_VALIDATE_BOOLEAN),
            'chunk_size' => (int) $this->input('chunk_size', 100),
            'has_headers' => filter_var($this->input('has_headers', true), FILTER_VALIDATE_BOOLEAN),
            'start_row' => (int) $this->input('start_row', 2),
            'delimiter' => $this->input('delimiter', ','),
            'encoding' => $this->input('encoding', 'UTF-8'),
            'preview_only' => filter_var($this->input('preview_only', false), FILTER_VALIDATE_BOOLEAN),
            'preview_rows' => (int) $this->input('preview_rows', 10),
            'field_mapping' => $this->input('field_mapping', []),
        ];
    }

    /**
     * Get file processing information.
     */
    public function getFileInfo(): array
    {
        $file = $this->file('import_file');

        if (!$file) {
            return [];
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'path' => $file->getPathname(),
        ];
    }

    /**
     * Check if this is a preview request.
     */
    public function isPreviewOnly(): bool
    {
        return filter_var($this->input('preview_only', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the default field mapping for the import type.
     */
    public function getDefaultFieldMapping(): array
    {
        return [
            'tax_id' => 'cuit',
            'business_name' => 'razon_social',
            'country' => 'pais',
            'client_type' => 'tipo_cliente',
        ];
    }

    /**
     * Helper methods for better IDE support and code clarity
     */

    /**
     * Check if the request has a given input key and it's not empty.
     */
    protected function hasInput(string $key): bool
    {
        return $this->has($key) && $this->input($key) !== null && $this->input($key) !== '';
    }

    /**
     * Get input value with type casting.
     */
    protected function getInputAsBoolean(string $key, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get input value as integer.
     */
    protected function getInputAsInteger(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }

    /**
     * Get input value as string with trimming.
     */
    protected function getInputAsString(string $key, string $default = ''): string
    {
        return trim($this->input($key, $default) ?? '');
    }
}
