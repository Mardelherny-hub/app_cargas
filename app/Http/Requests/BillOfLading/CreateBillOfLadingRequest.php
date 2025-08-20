<?php

namespace App\Http\Requests\BillOfLading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Traits\UserHelper;

/**
 * MÓDULO 4 - PARTE 1: GESTIÓN DE DATOS PARA MANIFIESTOS
 * 
 * Request para creación de Conocimientos de Embarque
 * Validaciones específicas para empresa con rol "Cargas"
 * 
 * VALIDACIONES CRÍTICAS:
 * - Verificar rol de empresa "Cargas"
 * - CUIT/RUC válidos para clientes
 * - Números de conocimiento únicos
 * - Fechas lógicas (carga ≤ descarga)
 * - Pesos coherentes (neto ≤ bruto)
 * 
 * CORREGIDO: Validaciones de puertos usan 'active,1' no 'status,active'
 */
class CreateBillOfLadingRequest extends FormRequest
{
    use UserHelper;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verificar que el usuario puede crear conocimientos
        if (!$this->canPerform('view_cargas')) {
            return false;
        }

        // Verificar que la empresa tenga rol "Cargas"
        if (!$this->hasCompanyRole('Cargas')) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // DEBUG: Ver qué datos llegan
        \Log::info('=== BILL OF LADING CREATE REQUEST DEBUG ===', [
            'all_request_data' => $this->all(),
            'shipper_id' => $this->input('shipper_id'),
            'consignee_id' => $this->input('consignee_id'),
            'measurement_unit' => $this->input('measurement_unit'),
            'loading_date' => $this->input('loading_date'),
            'old_values' => old(),
            'has_old' => !empty(old())
        ]);
        $company = $this->getUserCompany();
        
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
                'nullable',
                'integer',
                'exists:clients,id,status,active',
            ],
            'consignee_id' => [
                'nullable',
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
                'exists:ports,id,active,1',
            ],
            'discharge_port_id' => [
                'required',
                'integer',
                'exists:ports,id,active,1',
                'different:loading_port_id',
            ],
            'transshipment_port_id' => [
                'nullable',
                'integer',
                'exists:ports,id,active,1',
            ],
            'final_destination_port_id' => [
                'nullable',
                'integer',
                'exists:ports,id,active,1',
            ],
            'loading_customs_id' => [
                'nullable',
                'integer',
                'exists:customs_offices,id,active,1',
            ],
            'discharge_customs_id' => [
                'nullable',
                'integer',
                'exists:customs_offices,id,active,1',
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
            'secondary_cargo_type_id' => [
                'nullable',
                'integer',
                'exists:cargo_types,id,active,1',
                'different:primary_cargo_type_id',
            ],
            'secondary_packaging_type_id' => [
                'nullable',
                'integer',
                'exists:packaging_types,id,active,1',
            ],

            // === DATOS BÁSICOS DEL CONOCIMIENTO ===
            'bill_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('bills_of_lading', 'bill_number')
                    ->whereNull('deleted_at'),
            ],
            'bl_type' => [
                'required',
                'string',
                'in:original,copy,duplicate,express,telex,sea_waybill',
            ],
            'bill_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'freight_terms' => [
                'required',
                'string',
                'in:prepaid,collect,prepaid_collect',
            ],

            // === FECHAS OPERATIVAS ===
            'loading_date' => [
                'nullable',
                'date',
            ],
            'discharge_date' => [
                'nullable',
                'date',
                'after_or_equal:loading_date',
            ],
            'delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:loading_date',
            ],
            'place_of_receipt' => [
                'nullable',
                'string',
                'max:255',
            ],
            'place_of_delivery' => [
                'nullable',
                'string',
                'max:255',
            ],

            // === INFORMACIÓN COMERCIAL ===
            'incoterm' => [
                'required',
                'string',
                'in:EXW,FCA,FAS,FOB,CFR,CIF,CPT,CIP,DAP,DPU,DDP',
            ],
            'incoterm_location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                'in:USD,EUR,ARS,PYG,BRL,UYU',
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'min:0.0001',
                'max:999999.9999',
            ],
            'freight_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'other_charges' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'total_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],

            // === MEDIDAS Y PESOS ===
            'measurement_unit' => [
                'nullable',
                'string',
                 'in:KG,TON,LB,CBM,CFT,LTR,PCS,PKG',
            ],
            'gross_weight' => [
                'required',
                'numeric',
                'min:0.001',
                'max:999999999.999',
            ],
            'net_weight' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.999',
                'lte:gross_weight',
            ],
            'volume' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
            ],

            // === CONTENEDORES Y EMBALAJE ===
            'number_of_packages' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
            ],
            'container_numbers' => [
                'nullable',
                'json',
            ],
            'seal_numbers' => [
                'nullable',
                'json',
            ],
            'marks_and_numbers' => [
                'nullable',
                'string',
                'max:2000',
            ],

            // === DESCRIPCIONES ===
            'goods_description' => [
                'required',
                'string',
                'max:3000',
            ],
            'additional_remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'special_instructions' => [
                'nullable',
                'string',
                'max:2000',
            ],

            // === TÉRMINOS DE PAGO ===
            'payment_terms' => [
                'required',
                'string',
                'in:cash,credit,advance,documents_against_payment,documents_against_acceptance,letter_of_credit',
            ],
            'payment_due_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'credit_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],

            // === REGULACIONES Y CONTROL ===
            'dangerous_goods' => [
                'boolean',
            ],
            'dangerous_goods_class' => [
                'nullable',
                'string',
                'max:10',
                'required_if:dangerous_goods,true',
            ],
            'temperature_controlled' => [
                'boolean',
            ],
            'temperature_range' => [
                'nullable',
                'string',
                'max:50',
                'required_if:temperature_controlled,true',
            ],
            'fumigation_required' => [
                'boolean',
            ],
            'fumigation_details' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:fumigation_required,true',
            ],

            // === SEGUROS ===
            'insurance_required' => [
                'boolean',
            ],
            'insurance_value' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
                'required_if:insurance_required,true',
            ],
            'insurance_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'insurance_company' => [
                'nullable',
                'string',
                'max:255',
            ],
            'policy_number' => [
                'nullable',
                'string',
                'max:100',
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
            'master_bill_number' => [
                'nullable',
                'string',
                'max:50',
                'required_if:is_house_bill,true',
            ],
            'requires_surrender' => [
                'boolean',
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

            // Datos básicos
            'bl_number.required' => 'El número de conocimiento es obligatorio.',
            'bl_number.unique' => 'Ya existe un conocimiento con este número.',
            'bl_type.required' => 'Debe seleccionar el tipo de conocimiento.',
            'issued_date.required' => 'La fecha de emisión es obligatoria.',
            'freight_terms.required' => 'Debe seleccionar los términos de flete.',

            // Fechas
            'loading_date.required' => 'La fecha de carga es obligatoria.',
            'discharge_date.after_or_equal' => 'La fecha de descarga debe ser posterior a la de carga.',
            'delivery_date.after_or_equal' => 'La fecha de entrega debe ser posterior a la de carga.',

            // Comerciales
            'incoterm.required' => 'Debe seleccionar un Incoterm.',
            'currency.required' => 'Debe seleccionar una moneda.',
            'measurement_unit.required' => 'Debe seleccionar una unidad de medida.',

            // Pesos
            'gross_weight.required' => 'El peso bruto es obligatorio.',
            'gross_weight.min' => 'El peso bruto debe ser mayor a 0.',
            'net_weight.lte' => 'El peso neto no puede ser mayor al peso bruto.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'number_of_packages.required' => 'El número de bultos es obligatorio.',

            // Descripciones
            'goods_description.required' => 'La descripción de las mercancías es obligatoria.',
            'goods_description.max' => 'La descripción no puede exceder 3000 caracteres.',

            // Términos de pago
            'payment_terms.required' => 'Debe seleccionar los términos de pago.',

            // Mercancías peligrosas
            'dangerous_goods_class.required_if' => 'Debe especificar la clase cuando las mercancías son peligrosas.',
            'temperature_range.required_if' => 'Debe especificar el rango de temperatura cuando se requiere control.',
            'fumigation_details.required_if' => 'Debe especificar los detalles cuando se requiere fumigación.',

            // Seguros
            'insurance_value.required_if' => 'Debe especificar el valor del seguro cuando se requiere.',

            // Documentos
            'original_release_date.required_if' => 'Debe especificar la fecha cuando el original fue liberado.',
            'customs_bond_number.required_if' => 'Debe especificar el número de garantía cuando se requiere.',
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
            'notify_party_id' => 'notificar a',
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
            'freight_terms' => 'términos de flete',
            'loading_date' => 'fecha de carga',
            'discharge_date' => 'fecha de descarga',
            'delivery_date' => 'fecha de entrega',
            'incoterm' => 'Incoterm',
            'currency' => 'moneda',
            'measurement_unit' => 'unidad de medida',
            'gross_weight' => 'peso bruto',
            'net_weight' => 'peso neto',
            'volume' => 'volumen',
            'quantity' => 'cantidad',
            'number_of_packages' => 'número de bultos',
            'goods_description' => 'descripción de las mercancías',
            'payment_terms' => 'términos de pago',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación: Si es conocimiento hijo, debe tener un maestro
            if ($this->input('is_house_bill') && !$this->input('master_bill_number')) {
                $validator->errors()->add('master_bill_number', 'Los conocimientos hijo deben tener un número de conocimiento maestro.');
            }

            // Validación: Si es conocimiento maestro, no puede ser hijo
            if ($this->input('is_master_bill') && $this->input('is_house_bill')) {
                $validator->errors()->add('is_house_bill', 'Un conocimiento no puede ser maestro e hijo a la vez.');
            }
        });
    }
}