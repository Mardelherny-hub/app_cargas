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
 * Form Request para validación de creación de clientes
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
                Rule::unique('clients')->where(function ($query) {
                    $countryId = request('country_id');
                    if ($countryId) {
                        return $query->where('country_id', $countryId);
                    }
                    return $query;
                })
            ],

            'country_id' => [
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
                'required',
                'string',
                Rule::in(['shipper', 'consignee', 'notify_party', 'owner'])
            ],

            'legal_name' => [
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
                'nullable',
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
            'client_type.required' => 'El tipo de cliente es obligatorio.',
            'legal_name.required' => 'La razón social es obligatoria.',
            'legal_name.min' => 'La razón social debe tener al menos 3 caracteres.'
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

        return $validated;
    }
}
