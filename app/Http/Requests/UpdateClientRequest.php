<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Client;
use App\Models\Country;
use App\Traits\UserHelper;
use Illuminate\Validation\Rule;

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
        return $this->user()->can('update', $this->route('client'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client?->id;
        $countryId = $this->input('country_id');
        $country = is_scalar($countryId) ? Country::find($countryId) : null;
        $requiresLocalDocument = in_array(strtoupper((string) $country?->alpha2_code), ['AR', 'PY'], true);

        // Los registros extranjeros históricos pueden tener un tipo de documento
        // distinto de su país. Permitir otras ediciones si la identificación no cambia.
        $foreignIdentityUnchanged = $client instanceof Client
            && !$requiresLocalDocument
            && (string) $client->country_id === (string) $countryId
            && (string) ($client->document_type_id ?? '') === (string) ($this->input('document_type_id') ?? '')
            && (string) ($client->tax_id ?? '') === (string) ($this->input('tax_id') ?? '');

        $documentTypeRule = Rule::exists('document_types', 'id');
        if (!$foreignIdentityUnchanged) {
            $documentTypeRule->where('country_id', $countryId)->where('active', true);
        }

        return [
            'tax_id' => [
                Rule::requiredIf($requiresLocalDocument || ($this->filled('document_type_id') && !$foreignIdentityUnchanged)),
                'nullable',
                'string',
                'max:20',
                ...($requiresLocalDocument ? ['regex:/^[0-9]+$/'] : []),
                Rule::unique('clients', 'tax_id')->where('country_id', $countryId)->ignore($clientId),
            ],
            'country_id' => 'required|exists:countries,id',
            'document_type_id' => [
                Rule::requiredIf($requiresLocalDocument),
                'nullable',
                $documentTypeRule,
            ],

            // Datos básicos del cliente
            'legal_name' => 'required|string|min:3|max:255',
            'commercial_name' => 'nullable|string|max:255',
            
            // Referencias operativas opcionales
            'primary_port_id' => 'nullable|exists:ports,id',
            'customs_offices_id' => 'nullable|exists:customs_offices,id',

            // Estado del cliente
            'status' => 'required|in:active,inactive,suspended',

            // Observaciones
            'notes' => 'nullable|string|max:1000',

            // Contactos
            'contacts' => 'nullable|array|min:1',
            'contacts.*.id' => [
                'nullable',
                'integer',
                Rule::exists('client_contact_data', 'id')->where(function ($query) use ($clientId) {
                    return $query->where('client_id', $clientId);
                }),
            ],
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.address_line_1' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tax_id.required' => 'La identificación fiscal es obligatoria para el país o documento seleccionado.',
            'tax_id.unique' => 'Ya existe un cliente con esta identificación fiscal en este país.',
            'tax_id.regex' => 'El CUIT/RUC debe contener sólo números y separadores habituales.',
            'country_id.required' => 'El país es obligatorio.',
            'country_id.exists' => 'El país seleccionado no es válido.',
            'document_type_id.required' => 'El tipo de documento es obligatorio para Argentina y Paraguay.',
            'document_type_id.exists' => 'El tipo de documento debe corresponder al país seleccionado y estar activo.',
            'legal_name.required' => 'La razón social es obligatoria.',
            'legal_name.min' => 'La razón social debe tener al menos 3 caracteres.',
            'legal_name.max' => 'La razón social no puede tener más de 255 caracteres.',
            'commercial_name.max' => 'El nombre comercial no puede tener más de 255 caracteres.',
            'primary_port_id.exists' => 'El puerto seleccionado no es válido.',
            'customs_offices_id.exists' => 'La aduana seleccionada no es válida.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'notes.max' => 'Las observaciones no pueden tener más de 1000 caracteres.',
            'contacts.*.id.exists' => 'El contacto que intenta modificar no es válido o no pertenece a este cliente.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('document_type_id') === '') {
            $this->merge(['document_type_id' => null]);
        }

        $taxId = $this->input('tax_id');
        if (!is_string($taxId)) {
            return;
        }

        $taxId = trim($taxId);
        $countryId = $this->input('country_id');
        $country = is_scalar($countryId) ? Country::find($countryId) : null;

        // Sólo quitar separadores de CUIT/RUC; rechazar letras sin convertirlas.
        if ($taxId !== ''
            && in_array(strtoupper((string) $country?->alpha2_code), ['AR', 'PY'], true)
            && preg_match('/^[0-9.\\s-]+$/', $taxId)) {
            $taxId = preg_replace('/[^0-9]/', '', $taxId);
        }

        $this->merge(['tax_id' => $taxId === '' ? null : $taxId]);
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