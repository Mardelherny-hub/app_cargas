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
                            return;
                        }
                        if (!ctype_digit($cleanTaxId)) {
                            $fail('El RUC debe contener solo números.');
                        }
                    }
                },
                Rule::unique('clients')->where(function ($query) use ($clientId) {
                    $countryId = request('country_id');
                    if ($countryId) {
                        return $query->where('country_id', $countryId)
                                    ->where('id', '!=', $clientId);
                    }
                    return $query->where('id', '!=', $clientId);
                })
            ],

            'country_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:countries,id'
            ],

            'document_type_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!$value) return;
                    $countryId = request('country_id');
                    if (!$countryId) return;

                    $exists = DocumentType::where('id', $value)
                        ->where('country_id', $countryId)
                        ->exists();

                    if (!$exists) {
                        $fail('El tipo de documento no es válido para este país.');
                    }
                }
            ],

            'client_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['shipper', 'consignee', 'notify_party', 'owner'])
            ],

            'business_name' => [
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
                'exists:custom_offices,id'
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
            ],

            'verified_at' => [
                'nullable',
                'date'
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
            'client_type.required' => 'El tipo de cliente es obligatorio.',
            'client_type.in' => 'El tipo de cliente seleccionado no es válido.',
            'business_name.required' => 'La razón social es obligatoria.',
            'business_name.min' => 'La razón social debe tener al menos 3 caracteres.',
            'business_name.max' => 'La razón social no puede tener más de 255 caracteres.',
            'primary_port_id.exists' => 'El puerto seleccionado no es válido.',
            'customs_offices_id.exists' => 'La aduana seleccionada no es válida.',
            'notes.max' => 'Las observaciones no pueden tener más de 1000 caracteres.',
            'verified_at.date' => 'La fecha de verificación debe ser una fecha válida.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tax_id' => 'CUIT/RUC',
            'country_id' => 'país',
            'document_type_id' => 'tipo de documento',
            'client_type' => 'tipo de cliente',
            'business_name' => 'razón social',
            'primary_port_id' => 'puerto principal',
            'customs_offices_id' => 'aduana',
            'notes' => 'observaciones',
            'verified_at' => 'fecha de verificación'
        ];
    }
}
