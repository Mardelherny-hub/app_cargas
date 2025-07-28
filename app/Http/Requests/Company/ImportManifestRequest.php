<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class ImportManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Por ahora simple
    }

    public function rules(): array
    {
        return [
            'manifest_file' => 'required|file|mimes:csv,txt|max:10240',
            'manifest_type' => 'required|string',
            'webservice_type' => 'required|string', // Sin restricción por ahora
            'environment' => 'required|string',
        ];
    }

    // Agregar para debug
    public function messages(): array
    {
        return [
            'manifest_file.required' => 'ERROR: No se detectó archivo. Verifique que el formulario tenga enctype multipart.',
            'webservice_type.required' => 'DEBUG: Valor webservice recibido: ' . $this->input('webservice_type'),
        ];
    }
}