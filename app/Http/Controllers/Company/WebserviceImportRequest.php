<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use App\Traits\UserHelper;

class WebserviceImportRequest extends FormRequest
{
    use UserHelper;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verificar permisos de webservices o usuario con empresa
        return $this->canPerform('manage_webservices') || $this->hasRole('user');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'manifest_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240', // 10MB máximo
            ],
            'manifest_type' => [
                'required',
                'string',
                'in:parana,guaran,auto_detect',
            ],
            'webservice_type' => [
                'required',
                'string',
                'in:argentina_anticipated,argentina_micdta,paraguay_customs',
            ],
            'environment' => [
                'required',
                'string',
                'in:testing,production',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'manifest_file.required' => 'Debe seleccionar un archivo de manifiesto.',
            'manifest_file.file' => 'El archivo seleccionado no es válido.',
            'manifest_file.mimes' => 'El archivo debe ser formato CSV o TXT.',
            'manifest_file.max' => 'El archivo no puede ser mayor a 10MB.',
            'manifest_type.required' => 'Debe seleccionar el tipo de manifiesto.',
            'manifest_type.in' => 'Tipo de manifiesto no válido.',
            'webservice_type.required' => 'Debe seleccionar el webservice destino.',
            'webservice_type.in' => 'Webservice seleccionado no válido.',
            'environment.required' => 'Debe seleccionar el ambiente.',
            'environment.in' => 'Ambiente seleccionado no válido.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'manifest_file' => 'archivo de manifiesto',
            'manifest_type' => 'tipo de manifiesto',
            'webservice_type' => 'webservice',
            'environment' => 'ambiente',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $company = $this->getUserCompany();
            
            // Verificar que el usuario tenga empresa asociada
            if (!$company) {
                $validator->errors()->add('company', 'No se encontró empresa asociada al usuario.');
                return;
            }

            // Verificar que la empresa esté activa
            if (!$company->active) {
                $validator->errors()->add('company', 'La empresa no está activa.');
                return;
            }

            // Verificar que webservices estén activos para la empresa
            if (!$company->ws_active) {
                $validator->errors()->add('webservice', 'Los webservices están desactivados para su empresa.');
                return;
            }

            // Verificar certificado según ambiente seleccionado
            $environment = $this->input('environment');
            if ($environment === 'production' && !$company->certificate_file) {
                $validator->errors()->add('environment', 'Certificado digital requerido para ambiente de producción.');
            }
        });
    }
}