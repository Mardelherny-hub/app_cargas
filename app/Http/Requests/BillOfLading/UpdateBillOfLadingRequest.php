<?php

namespace App\Http\Requests\BillOfLading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Traits\UserHelper;
use App\Models\BillOfLading;

/**
 * MÓDULO 4 - PARTE 1: GESTIÓN DE DATOS PARA MANIFIESTOS
 * 
 * Request para actualización de Conocimientos de Embarque
 * Validaciones específicas para empresa con rol "Cargas"
 * 
 * VALIDACIONES ESPECIALES PARA UPDATE:
 * - Permitir mismo bill_number si es el mismo registro
 * - Bloquear edición si ya fue enviado a webservices
 * - Validar cambios críticos que afecten manifiestos
 * 
 * ACTUALIZADO: Incluye todos los campos de la migración bills_of_lading
 */
class UpdateBillOfLadingRequest extends FormRequest
{
    use UserHelper;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verificar que el usuario puede editar conocimientos
        if (!$this->canPerform('view_cargas')) {
            return false;
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            return false;
        }

        // Verificar que el conocimiento puede ser editado
        $billOfLading = $this->route('bill_of_lading');
        if ($billOfLading && !$billOfLading->canBeEdited()) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $company = $this->getUserCompany();
        $billOfLading = $this->route('bill_of_lading');
        
        return [
            // === RELACIONES PRINCIPALES ===
            'shipment_id' => [
                'required',
                'integer',
                'exists:shipments,id',
                function ($attribute, $value, $fail) use ($company) {
                    // Verificar que el shipment pertenece a la empresa del usuario
                    $shipment = \App\Models\Shipment::find($value);
                    if ($shipment && $shipment->voyage->company_id !== $company->id) {
                        $fail('El envío seleccionado no pertenece a su empresa.');
                    }
                },
            ],
            'shipper_id' => [
                'required',
                'integer',
                'exists:clients,id,status,active',
            ],
            'consignee_id' => [
                'required',
                'integer',
                'exists:clients,id,status,active',
                'different:shipper_id',
            ],
            'notify_party_id' => [
                'nullable',
                'integer',
                'exists:clients,id,status,active',
            ],
            'cargo_owner_id' => [
                'nullable',
                'integer',
                'exists:clients,id,status,active',
            ],

            // === PUERTOS Y ADUANAS ===
            'loading_port_id' => [
                'required',
                'integer',
                'exists:ports,id,status,active',
            ],
            'discharge_port_id' => [
                'required',
                'integer',
                'exists:ports,id,status,active',
                'different:loading_port_id',
            ],
            'transshipment_port_id' => [
                'nullable',
                'integer',
                'exists:ports,id,status,active',
            ],
            'final_destination_port_id' => [
                'nullable',
                'integer',
                'exists:ports,id,status,active',
            ],
            'loading_customs_id' => [
                'nullable',
                'integer',
                'exists:customs_offices,id,status,active',
            ],
            'discharge_customs_id' => [
                'nullable',
                'integer',
                'exists:customs_offices,id,status,active',
            ],

            // === TIPOS DE CARGA ===
            'primary_cargo_type_id' => [
                'required',
                'integer',
                'exists:cargo_types,id,active,1',
            ],
            'primary_packaging_type_id' => [
                'required',
                'integer',
                'exists:packaging_types,id,active,1',
            ],

            // === IDENTIFICACIÓN DEL CONOCIMIENTO ===
            'bill_number' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-\/]+$/', // Solo letras mayúsculas, números, guiones y barras
                Rule::unique('bills_of_lading', 'bill_number')
                    ->ignore($billOfLading?->id)
                    ->whereNull('deleted_at'),
            ],
            'master_bill_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'house_bill_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'internal_reference' => [
                'nullable',
                'string',
                'max:100',
            ],
            'bill_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after:2020-01-01', // Fecha mínima razonable
            ],
            'manifest_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'manifest_line_number' => [
                'nullable',
                'integer',
                'min:1',
                'max:99999',
            ],

            // === FECHAS OPERACIONALES ===
            'loading_date' => [
                'nullable',
                'date',
                'after_or_equal:bill_date',
            ],
            'discharge_date' => [
                'nullable',
                'date',
                'after_or_equal:loading_date',
            ],
            'arrival_date' => [
                'nullable',
                'date',
                'after_or_equal:loading_date',
            ],
            'delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:arrival_date',
            ],
            'cargo_ready_date' => [
                'nullable',
                'date',
                'after_or_equal:bill_date',
            ],
            'free_time_expires_at' => [
                'nullable',
                'date',
                'after:arrival_date',
            ],

            // === TÉRMINOS COMERCIALES ===
            'freight_terms' => [
                'required',
                Rule::in(['prepaid', 'collect', 'prepaid_collect', 'third_party']),
            ],
            'payment_terms' => [
                'nullable',
                Rule::in(['cash', 'credit', 'advance', 'cod', 'letter_of_credit']),
            ],
            'incoterms' => [
                'nullable',
                Rule::in(['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF']),
            ],
            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/', // Exactamente 3 letras mayúsculas
            ],

            // === MEDIDAS Y PESOS ===
            'total_packages' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
            ],
            'gross_weight_kg' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.999',
            ],
            'net_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:gross_weight_kg', // El peso neto no puede ser mayor al bruto
            ],
            'volume_m3' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.999',
            ],
            'measurement_unit' => [
                'nullable',
                Rule::in(['KG', 'TON', 'M3', 'LTR', 'PCS']),
            ],
            'container_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:999',
            ],

            // === DESCRIPCIÓN DE CARGA ===
            'cargo_description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'cargo_marks' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'commodity_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            // === TIPO Y CARACTERÍSTICAS DEL CONOCIMIENTO ===
            'bill_type' => [
                'nullable',
                Rule::in(['original', 'copy', 'duplicate', 'amendment']),
            ],
            'status' => [
                'nullable',
                Rule::in(['draft', 'confirmed', 'loaded', 'in_transit', 'discharged', 'delivered', 'returned', 'cancelled']),
            ],
            'priority_level' => [
                'nullable',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],

            // === CARACTERÍSTICAS ESPECIALES DE CARGA ===
            'requires_inspection' => [
                'boolean',
            ],
            'contains_dangerous_goods' => [
                'boolean',
            ],
            'requires_refrigeration' => [
                'boolean',
            ],
            'is_transhipment' => [
                'boolean',
            ],
            'is_partial_shipment' => [
                'boolean',
            ],
            'allows_partial_delivery' => [
                'boolean',
            ],
            'requires_documents_on_arrival' => [
                'boolean',
            ],

            // === CONSOLIDACIÓN ===
            'is_consolidated' => [
                'boolean',
            ],
            'is_master_bill' => [
                'boolean',
            ],
            'is_house_bill' => [
                'boolean',
            ],
            'requires_surrender' => [
                'boolean',
            ],

            // === MERCANCÍAS PELIGROSAS ===
            'un_number' => [
                'nullable',
                'string',
                'max:10',
                'required_if:contains_dangerous_goods,true',
            ],
            'imdg_class' => [
                'nullable',
                'string',
                'max:10',
                'required_if:contains_dangerous_goods,true',
            ],

            // === INFORMACIÓN FINANCIERA ===
            'freight_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'insurance_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'declared_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'additional_charges' => [
                'nullable',
                'json',
            ],

            // === INSTRUCCIONES Y OBSERVACIONES ===
            'special_instructions' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'handling_instructions' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'customs_remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'loading_remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'discharge_remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'delivery_remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // === CONTROL DE CALIDAD Y CONDICIÓN ===
            'cargo_condition_loading' => [
                'nullable',
                Rule::in(['good', 'fair', 'poor', 'damaged']),
            ],
            'cargo_condition_discharge' => [
                'nullable',
                Rule::in(['good', 'fair', 'poor', 'damaged']),
            ],
            'condition_remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // === VERIFICACIÓN Y DISCREPANCIAS ===
            'has_discrepancies' => [
                'boolean',
            ],
            'discrepancy_details' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:has_discrepancies,true',
            ],

            // === ENTREGA Y RECOGIDA ===
            'delivery_address' => [
                'nullable',
                'string',
                'max:500',
            ],
            'pickup_address' => [
                'nullable',
                'string',
                'max:500',
            ],
            'delivery_contact_name' => [
                'nullable',
                'string',
                'max:200',
            ],
            'delivery_contact_phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'delivery_instructions' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // === DOCUMENTOS ===
            'required_documents' => [
                'nullable',
                'json',
            ],
            'attached_documents' => [
                'nullable',
                'json',
            ],
            'original_released' => [
                'boolean',
            ],
            'original_release_date' => [
                'nullable',
                'date',
                'required_if:original_released,true',
            ],
            'documentation_complete' => [
                'boolean',
            ],
            'ready_for_delivery' => [
                'boolean',
            ],

            // === CONTROL ADUANERO ===
            'customs_cleared' => [
                'boolean',
            ],
            'customs_bond_required' => [
                'boolean',
            ],
            'customs_bond_number' => [
                'nullable',
                'string',
                'max:50',
                'required_if:customs_bond_required,true',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Relaciones principales
            'shipment_id.required' => 'Debe seleccionar un envío.',
            'shipment_id.exists' => 'El envío seleccionado no existe o está inactivo.',
            'shipper_id.required' => 'Debe seleccionar un cargador/exportador.',
            'shipper_id.exists' => 'El cargador seleccionado no existe o está inactivo.',
            'consignee_id.required' => 'Debe seleccionar un consignatario/importador.',
            'consignee_id.different' => 'El consignatario debe ser diferente al cargador.',

            // Puertos
            'loading_port_id.required' => 'Debe seleccionar el puerto de carga.',
            'discharge_port_id.required' => 'Debe seleccionar el puerto de descarga.',
            'discharge_port_id.different' => 'El puerto de descarga debe ser diferente al de carga.',

            // Número de conocimiento
            'bill_number.required' => 'El número de conocimiento es obligatorio.',
            'bill_number.unique' => 'Ya existe otro conocimiento con este número.',
            'bill_number.regex' => 'El número de conocimiento solo puede contener letras mayúsculas, números, guiones y barras.',

            // Fechas
            'bill_date.required' => 'La fecha del conocimiento es obligatoria.',
            'bill_date.before_or_equal' => 'La fecha del conocimiento no puede ser futura.',
            'loading_date.after_or_equal' => 'La fecha de carga no puede ser anterior a la fecha del conocimiento.',
            'discharge_date.after_or_equal' => 'La fecha de descarga no puede ser anterior a la de carga.',

            // Estado
            'status.in' => 'El estado seleccionado no es válido.',

            // Pesos y medidas
            'total_packages.required' => 'La cantidad total de bultos es obligatoria.',
            'total_packages.min' => 'Debe haber al menos 1 bulto.',
            'gross_weight_kg.required' => 'El peso bruto es obligatorio.',
            'gross_weight_kg.min' => 'El peso bruto debe ser mayor a 0.',
            'net_weight_kg.lte' => 'El peso neto no puede ser mayor al peso bruto.',

            // Mercancías peligrosas
            'un_number.required_if' => 'Debe especificar el número UN para mercancía peligrosa.',
            'imdg_class.required_if' => 'Debe especificar la clase IMDG para mercancía peligrosa.',

            // Documentos
            'original_release_date.required_if' => 'Debe especificar la fecha cuando marca el original como entregado.',
            'customs_bond_number.required_if' => 'Debe especificar el número de garantía aduanera.',

            // Discrepancias
            'discrepancy_details.required_if' => 'Debe especificar los detalles cuando marca que hay discrepancias.',

            // Términos comerciales
            'freight_terms.required' => 'Debe especificar los términos de flete.',
            'incoterms.in' => 'El Incoterm seleccionado no es válido.',
            'currency_code.regex' => 'El código de moneda debe ser de 3 letras mayúsculas (ej: USD, BRL, ARS).',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'shipment_id' => 'envío',
            'shipper_id' => 'cargador/exportador',
            'consignee_id' => 'consignatario/importador',
            'notify_party_id' => 'parte a notificar',
            'cargo_owner_id' => 'propietario de la carga',
            'loading_port_id' => 'puerto de carga',
            'discharge_port_id' => 'puerto de descarga',
            'transshipment_port_id' => 'puerto de transbordo',
            'final_destination_port_id' => 'puerto de destino final',
            'loading_customs_id' => 'aduana de carga',
            'discharge_customs_id' => 'aduana de descarga',
            'primary_cargo_type_id' => 'tipo de carga principal',
            'primary_packaging_type_id' => 'tipo de embalaje principal',
            'bill_number' => 'número de conocimiento',
            'bill_date' => 'fecha del conocimiento',
            'loading_date' => 'fecha de carga',
            'discharge_date' => 'fecha de descarga',
            'status' => 'estado',
            'total_packages' => 'total de bultos',
            'gross_weight_kg' => 'peso bruto (kg)',
            'net_weight_kg' => 'peso neto (kg)',
            'volume_m3' => 'volumen (m³)',
            'freight_terms' => 'términos de flete',
            'has_discrepancies' => 'tiene discrepancias',
            'discrepancy_details' => 'detalles de discrepancias',
            'special_instructions' => 'instrucciones especiales',
            'un_number' => 'número UN',
            'imdg_class' => 'clase IMDG',
            'original_release_date' => 'fecha entrega del original',
            'customs_bond_number' => 'número de garantía aduanera',
            'cargo_condition_loading' => 'condición carga en origen',
            'cargo_condition_discharge' => 'condición carga en destino',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $billOfLading = $this->route('bill_of_lading');

            // Validación: no permitir cambios críticos si ya fue enviado a webservices
            if ($billOfLading && $billOfLading->webservice_sent_at) {
                $criticalFields = [
                    'bill_number', 'shipper_id', 'consignee_id', 
                    'gross_weight_kg', 'total_packages'
                ];

                foreach ($criticalFields as $field) {
                    if ($this->has($field) && $this->input($field) != $billOfLading->$field) {
                        $validator->errors()->add(
                            $field,
                            'No se puede modificar este campo después de enviar a webservices.'
                        );
                    }
                }
            }

            // Validación: Si es conocimiento hijo, debe tener un maestro
            if ($this->input('is_house_bill') && !$this->input('master_bill_number')) {
                $validator->errors()->add('master_bill_number', 'Los conocimientos hijo deben tener un número de conocimiento maestro.');
            }

            // Validación: Si es conocimiento maestro, no puede ser hijo
            if ($this->input('is_master_bill') && $this->input('is_house_bill')) {
                $validator->errors()->add('is_house_bill', 'Un conocimiento no puede ser maestro e hijo a la vez.');
            }

            // Validación: Si contiene mercancía peligrosa, algunos campos son obligatorios
            if ($this->input('contains_dangerous_goods')) {
                if (!$this->input('un_number')) {
                    $validator->errors()->add('un_number', 'El número UN es obligatorio para mercancía peligrosa.');
                }
                if (!$this->input('imdg_class')) {
                    $validator->errors()->add('imdg_class', 'La clase IMDG es obligatoria para mercancía peligrosa.');
                }
            }

            // Validación: Evitar cambio de estado a "cancelled" si tiene documentos originales entregados
            if ($billOfLading && $this->input('status') === 'cancelled' && $billOfLading->original_released) {
                $validator->errors()->add('status', 'No se puede cancelar un conocimiento con originales ya entregados.');
            }
        });
    }
}