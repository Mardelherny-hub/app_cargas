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
                'max:9999',
            ],

            // === FECHAS OPERACIONALES ===
            'loading_date' => [
                'nullable',
                'date',
                'after_or_equal:bill_date',
                'before_or_equal:today',
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
            'free_time_expires_at' => [
                'nullable',
                'date',
                'after:today',
            ],

            // === TÉRMINOS COMERCIALES ===
            'freight_terms' => [
                'required',
                Rule::in(['prepaid', 'collect', 'prepaid_collect', 'prepaid_partial']),
            ],
            'payment_terms' => [
                'nullable',
                Rule::in(['cash', 'credit', 'prepaid', 'collect', 'cash_on_delivery']),
            ],
            'incoterms' => [
                'nullable',
                'string',
                'max:10',
                Rule::in(['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF']),
            ],
            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/', // Códigos de moneda ISO (USD, BRL, ARS, etc.)
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
                'max:999999.99',
            ],
            'net_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
                'lte:gross_weight_kg', // Peso neto ≤ peso bruto
            ],
            'volume_m3' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.999',
            ],
            'measurement_unit' => [
                'nullable',
                Rule::in(['kg', 'ton', 'm3', 'cbm', 'packages', 'units']),
            ],

            // === ESTADOS Y CONTROL ===
            'status' => [
                'sometimes',
                Rule::in(['draft', 'pending_review', 'verified', 'sent_to_customs', 'accepted', 'rejected', 'completed', 'cancelled']),
                function ($attribute, $value, $fail) use ($billOfLading) {
                    // Validar transiciones de estado permitidas
                    if ($billOfLading && !$this->isValidStatusTransition($billOfLading->status, $value)) {
                        $fail('La transición de estado no es válida.');
                    }
                },
            ],
            'priority_level' => [
                'nullable',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
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

            // === OBSERVACIONES ===
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

            // === CONTROL DE CALIDAD ===
            'has_discrepancies' => [
                'boolean',
            ],
            'discrepancy_details' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:has_discrepancies,true',
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

            // Validación personalizada: verificar coherencia de fechas
            if ($this->has(['loading_date', 'discharge_date'])) {
                $loadingDate = $this->input('loading_date');
                $dischargeDate = $this->input('discharge_date');
                
                if ($loadingDate && $dischargeDate && $dischargeDate < $loadingDate) {
                    $validator->errors()->add(
                        'discharge_date', 
                        'La fecha de descarga no puede ser anterior a la fecha de carga.'
                    );
                }
            }

            // Validación personalizada: coherencia de puertos para transbordo
            if ($this->input('is_transhipment') && !$this->input('transshipment_port_id')) {
                $validator->errors()->add(
                    'transshipment_port_id',
                    'Debe especificar el puerto de transbordo cuando se marca como transbordo.'
                );
            }

            // Validación personalizada: verificar que los clientes pueden realizar las operaciones
            $this->validateClientCapabilities($validator);

            // Validación personalizada: coherencia con ítems existentes
            $this->validateConsistencyWithItems($validator, $billOfLading);
        });
    }

    /**
     * Validar capacidades de los clientes seleccionados
     */
    private function validateClientCapabilities($validator): void
    {
        $shipperId = $this->input('shipper_id');
        $consigneeId = $this->input('consignee_id');

        if ($shipperId) {
            $shipper = \App\Models\Client::find($shipperId);
            if ($shipper && !in_array('shipper', $shipper->client_roles ?? [])) {
                $validator->errors()->add(
                    'shipper_id',
                    'El cliente seleccionado no tiene habilitado el rol de cargador/exportador.'
                );
            }
        }

        if ($consigneeId) {
            $consignee = \App\Models\Client::find($consigneeId);
            if ($consignee && !in_array('consignee', $consignee->client_roles ?? [])) {
                $validator->errors()->add(
                    'consignee_id',
                    'El cliente seleccionado no tiene habilitado el rol de consignatario/importador.'
                );
            }
        }
    }

    /**
     * Validar consistencia con ítems de mercadería existentes
     */
    private function validateConsistencyWithItems($validator, ?BillOfLading $billOfLading): void
    {
        if (!$billOfLading || $billOfLading->shipmentItems->isEmpty()) {
            return;
        }

        $itemsWeight = $billOfLading->calculateTotalItemsWeight();
        $newGrossWeight = $this->input('gross_weight_kg');

        // El peso bruto del conocimiento debe ser coherente con el peso de los ítems
        if ($newGrossWeight && $itemsWeight > 0 && abs($newGrossWeight - $itemsWeight) > ($itemsWeight * 0.1)) {
            $validator->errors()->add(
                'gross_weight_kg',
                "El peso bruto ({$newGrossWeight} kg) difiere significativamente del peso total de los ítems ({$itemsWeight} kg)."
            );
        }
    }

    /**
     * Validar transiciones de estado permitidas
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'draft' => ['pending_review', 'verified', 'cancelled'],
            'pending_review' => ['draft', 'verified', 'cancelled'],
            'verified' => ['sent_to_customs', 'cancelled'],
            'sent_to_customs' => ['accepted', 'rejected'],
            'accepted' => ['completed'],
            'rejected' => ['draft', 'pending_review'],
            'completed' => [], // Estado final
            'cancelled' => [], // Estado final
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalizar el número de conocimiento
        if ($this->has('bill_number')) {
            $this->merge([
                'bill_number' => strtoupper(trim($this->input('bill_number')))
            ]);
        }

        // Normalizar código de moneda
        if ($this->has('currency_code')) {
            $this->merge([
                'currency_code' => strtoupper(trim($this->input('currency_code')))
            ]);
        }

        // Asegurar que los checkboxes sean boolean
        $booleanFields = [
            'requires_inspection',
            'contains_dangerous_goods', 
            'requires_refrigeration',
            'is_transhipment',
            'is_partial_shipment',
            'allows_partial_delivery',
            'requires_documents_on_arrival',
            'has_discrepancies'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => (bool) $this->input($field)]);
            }
        }

        // Agregar usuario que actualiza
        $this->merge([
            'last_updated_by_user_id' => auth()->id()
        ]);
    }
}