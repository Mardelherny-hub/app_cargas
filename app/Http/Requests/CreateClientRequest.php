<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Country;
use App\Models\Client;
use App\Traits\UserHelper;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Form Request para validación de creación de clientes
 * CORRECCIÓN CRÍTICA: client_type → client_roles (array múltiple)
 * BASADO EN: Datos reales del sistema (legal_name, no legal_name)
 */
class CreateClientRequest extends FormRequest
{
    use UserHelper;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tax_id' => [
                'required',
                'string',
                'size:11',
                'unique:clients,tax_id,NULL,id,country_id,' . $this->country_id
            ],
            'country_id' => [
                'required',
                'exists:countries,id'
            ],
            'document_type_id' => [
                'required',
                'exists:document_types,id'
            ],
            
            // CORRECCIÓN: Cambio de client_type a client_roles (array)
            'client_roles' => [
                'required',
                'array',
                'min:1'
            ],
            'client_roles.*' => [
                'required',
                'string',
                Rule::in(['shipper', 'consignee', 'notify_party'])
            ],
            
            // CORRECCIÓN: Campo real es legal_name (no legal_name)
            'legal_name' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'primary_port_id' => [
                'nullable',
                'exists:ports,id'
            ],
            'customs_offices_id' => [
                'nullable',
                'exists:customs_offices,id'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000'
            ]
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
            'client_roles.required' => 'Debe seleccionar al menos un rol de cliente.',
            'client_roles.array' => 'Los roles de cliente deben ser un array.',
            'client_roles.min' => 'Debe seleccionar al menos un rol de cliente.',
            'client_roles.*.required' => 'Cada rol de cliente es obligatorio.',
            'client_roles.*.in' => 'El rol de cliente seleccionado no es válido.',
            'legal_name.required' => 'La razón social es obligatoria.',
            'legal_name.min' => 'La razón social debe tener al menos 3 caracteres.',
            'legal_name.max' => 'La razón social no puede tener más de 255 caracteres.',
            'primary_port_id.exists' => 'El puerto seleccionado no es válido.',
            'customs_offices_id.exists' => 'La aduana seleccionada no es válida.',
            'notes.max' => 'Las observaciones no pueden tener más de 1000 caracteres.'
        ];
    }

    /**
     * Get the validated data from the request, adding company_id automatically.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Agregar automáticamente la empresa del usuario
        $companyId = $this->getUserCompanyId();
        if ($companyId) {
            $validated['created_by_company_id'] = $companyId;
        }

        // Asegurar que client_roles sea único (sin duplicados)
        if (isset($validated['client_roles'])) {
            $validated['client_roles'] = array_unique($validated['client_roles']);
        }

        return $validated;
    }
}