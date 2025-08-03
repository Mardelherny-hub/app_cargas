<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Traits\UserHelper;

/**
 * SIMPLIFICADO - Request para actualizar clientes sin roles
 * 
 * SIMPLIFICACIÓN APLICADA:
 * - ❌ REMOVIDO: Validaciones de client_roles
 * - ✅ AGREGADO: Validaciones para commercial_name, address, email
 * - ✅ MANTIENE: Validaciones básicas y lógica de empresa
 */
class UpdateClientRequest extends FormRequest
{
    use UserHelper;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clients.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clientId = $this->route('client')->id ?? null;

        return [
            // Identificación del cliente (el CUIT/RUC y país no se pueden cambiar)
            'tax_id' => [
                'required',
                'string',
                'max:11',
                'unique:clients,tax_id,' . $clientId . ',id,country_id,' . $this->country_id
            ],
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => 'required|exists:document_types,id',

            // Datos básicos del cliente
            'legal_name' => 'required|string|min:3|max:255',
            'commercial_name' => 'nullable|string|max:255',
            
            // Datos de contacto básicos
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|string|max:500',

            // Referencias operativas opcionales
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:customs_offices,id',

            // Estado del cliente
            'status' => 'required|in:active,inactive,suspended',

            // Observaciones
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tax_id.required' => 'El CUIT/RUC es obligatorio.',
            'tax_id.unique' => 'Ya existe un cliente con este CUIT/RUC en este país.',
            'country_id.required' => 'El país es obligatorio.',
            'country_id.exists' => 'El país seleccionado no es válido.',
            'document_type_id.required' => 'El tipo de documento es obligatorio.',
            'document_type_id.exists' => 'El tipo de documento seleccionado no es válido.',
            'legal_name.required' => 'La razón social es obligatoria.',
            'legal_name.min' => 'La razón social debe tener al menos 3 caracteres.',
            'legal_name.max' => 'La razón social no puede tener más de 255 caracteres.',
            'commercial_name.max' => 'El nombre comercial no puede tener más de 255 caracteres.',
            'address.max' => 'La dirección no puede tener más de 500 caracteres.',
            'email.max' => 'El campo email no puede tener más de 500 caracteres.',
            'primary_port_id.exists' => 'El puerto seleccionado no es válido.',
            'customs_offices_id.exists' => 'La aduana seleccionada no es válida.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'notes.max' => 'Las observaciones no pueden tener más de 1000 caracteres.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar CUIT/RUC removiendo caracteres no numéricos
        if ($this->has('tax_id')) {
            $this->merge([
                'tax_id' => preg_replace('/[^0-9]/', '', $this->tax_id)
            ]);
        }

        // Limpiar email - convertir múltiples emails separados por coma/punto y coma a formato estándar
        if ($this->has('email') && !empty($this->email)) {
            $emails = preg_split('/[;,]\s*/', $this->email);
            $emails = array_filter(array_map('trim', $emails));
            $this->merge([
                'email' => implode(';', $emails)
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // No agregar created_by_company_id en actualizaciones
        // Solo mantener los datos que se pueden actualizar

        return $validated;
    }
}