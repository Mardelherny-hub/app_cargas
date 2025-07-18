<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Client;
use App\Traits\UserHelper;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Form Request para validación de actualización de clientes
 * CORRECCIÓN CRÍTICA: client_type → client_roles (array múltiple)
 * BASADO EN: Datos reales del sistema
 */
class UpdateClientRequest extends FormRequest
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
        // Obtener ID del cliente - será definido cuando se creen las rutas
        $clientId = $this->route('client') ?? $this->route('id') ?? null;

        return [
            'tax_id' => [
                'sometimes',
                'required',
                'string',
                'max:11',
                function ($attribute, $value, $fail) {
                    $countryId = request('country_id');
                    if (!$countryId) return;

                    $country = Country::find($countryId);
                    if (!$country) {
                        $fail('El país seleccionado no es válido.');
                        return;
                    }

                    $cleanTaxId = preg_replace('/[^0-9]/', '', $value);

                    if ($country->iso_code === 'AR') {
                        // CUIT argentino: 11 dígitos
                        if (strlen($cleanTaxId) !== 11) {
                            $fail('El CUIT debe tener exactamente 11 dígitos.');
                            return;
                        }
                        if (!ctype_digit($cleanTaxId)) {
                            $fail('El CUIT debe contener solo números.');
                            return;
                        }
                        // Validar dígito verificador
                        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
                        $sum = 0;
                        for ($i = 0; $i < 10; $i++) {
                            $sum += intval($cleanTaxId[$i]) * $multipliers[$i];
                        }
                        $remainder = $sum % 11;
                        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;
                        if ($checkDigit != intval($cleanTaxId[10])) {
                            $fail('El CUIT ingresado no es válido (dígito verificador incorrecto).');
                        }
                    } elseif ($country->iso_code === 'PY') {
                        // RUC paraguayo: 8-9 dígitos
                        if (strlen($cleanTaxId) < 8 || strlen($cleanTaxId) > 9) {
                            $fail('El RUC debe tener entre 8 y 9 dígitos.');
                        }
                    }
                }
            ],

            // CORRECCIÓN: Cambio de client_type a client_roles (array)
            'client_roles' => [
                'sometimes',
                'required',
                'array',
                'min:1'
            ],
            'client_roles.*' => [
                'required',
                'string',
                Rule::in(['shipper', 'consignee', 'notify_party'])
            ],

            'legal_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:3'
            ],

            'primary_port_id' => [
                'nullable',
                'integer',
                'exists:ports,id'
            ],

            'customs_offices_id' => [
                'nullable',
                'integer',
                'exists:customs_offices,id'
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(['active', 'inactive', 'suspended'])
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que client_roles sea único (sin duplicados) si se proporciona
        if ($this->has('client_roles') && is_array($this->client_roles)) {
            $this->merge([
                'client_roles' => array_unique($this->client_roles)
            ]);
        }
    }
}