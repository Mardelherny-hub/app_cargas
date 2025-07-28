@extends('layouts.company')

@section('title', 'Nuevo Conocimiento de Embarque')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Nuevo Conocimiento de Embarque</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('company.bills-of-lading.index') }}">Conocimientos</a></li>
                    <li class="breadcrumb-item active">Nuevo</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('company.bills-of-lading.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('company.bills-of-lading.store') }}" novalidate>
        @csrf
        
        <div class="row">
            <div class="col-lg-8">
                {{-- Datos Principales --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Datos Principales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Envío --}}
                            <div class="col-md-6">
                                <label for="shipment_id" class="form-label required">Envío</label>
                                <select class="form-select @error('shipment_id') is-invalid @enderror" 
                                        id="shipment_id" 
                                        name="shipment_id" 
                                        required>
                                    <option value="">Seleccione un envío</option>
                                    @foreach($formData['shipments'] as $shipment)
                                        <option value="{{ $shipment->id }}" 
                                                {{ old('shipment_id', $formData['preselectedShipment']) == $shipment->id ? 'selected' : '' }}>
                                            {{ $shipment->shipment_number }} - {{ $shipment->voyage->voyage_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('shipment_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Número de Conocimiento --}}
                            <div class="col-md-6">
                                <label for="bill_number" class="form-label required">Número de Conocimiento</label>
                                <input type="text" 
                                       class="form-control @error('bill_number') is-invalid @enderror" 
                                       id="bill_number" 
                                       name="bill_number" 
                                       value="{{ old('bill_number') }}"
                                       placeholder="Ej: BL24001" 
                                       required>
                                @error('bill_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Fecha del Conocimiento --}}
                            <div class="col-md-4">
                                <label for="bill_date" class="form-label required">Fecha del Conocimiento</label>
                                <input type="date" 
                                       class="form-control @error('bill_date') is-invalid @enderror" 
                                       id="bill_date" 
                                       name="bill_date" 
                                       value="{{ old('bill_date', date('Y-m-d')) }}" 
                                       required>
                                @error('bill_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Términos de Flete --}}
                            <div class="col-md-4">
                                <label for="freight_terms" class="form-label required">Términos de Flete</label>
                                <select class="form-select @error('freight_terms') is-invalid @enderror" 
                                        id="freight_terms" 
                                        name="freight_terms" 
                                        required>
                                    <option value="">Seleccione términos</option>
                                    <option value="prepaid" {{ old('freight_terms') === 'prepaid' ? 'selected' : '' }}>Prepaid</option>
                                    <option value="collect" {{ old('freight_terms') === 'collect' ? 'selected' : '' }}>Collect</option>
                                    <option value="prepaid_collect" {{ old('freight_terms') === 'prepaid_collect' ? 'selected' : '' }}>Prepaid/Collect</option>
                                    <option value="prepaid_partial" {{ old('freight_terms') === 'prepaid_partial' ? 'selected' : '' }}>Prepaid Partial</option>
                                </select>
                                @error('freight_terms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Incoterms --}}
                            <div class="col-md-4">
                                <label for="incoterms" class="form-label">Incoterms</label>
                                <select class="form-select @error('incoterms') is-invalid @enderror" 
                                        id="incoterms" 
                                        name="incoterms">
                                    <option value="">Sin especificar</option>
                                    @foreach(['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF'] as $incoterm)
                                        <option value="{{ $incoterm }}" {{ old('incoterms') === $incoterm ? 'selected' : '' }}>
                                            {{ $incoterm }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('incoterms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Clientes --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Clientes</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Cargador --}}
                            <div class="col-md-6">
                                <label for="shipper_id" class="form-label required">Cargador/Exportador</label>
                                <select class="form-select @error('shipper_id') is-invalid @enderror" 
                                        id="shipper_id" 
                                        name="shipper_id" 
                                        required>
                                    <option value="">Seleccione cargador</option>
                                    @foreach($formData['shippers'] as $shipper)
                                        <option value="{{ $shipper->id }}" {{ old('shipper_id') == $shipper->id ? 'selected' : '' }}>
                                            {{ $shipper->legal_name }} ({{ $shipper->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('shipper_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Consignatario --}}
                            <div class="col-md-6">
                                <label for="consignee_id" class="form-label required">Consignatario/Importador</label>
                                <select class="form-select @error('consignee_id') is-invalid @enderror" 
                                        id="consignee_id" 
                                        name="consignee_id" 
                                        required>
                                    <option value="">Seleccione consignatario</option>
                                    @foreach($formData['consignees'] as $consignee)
                                        <option value="{{ $consignee->id }}" {{ old('consignee_id') == $consignee->id ? 'selected' : '' }}>
                                            {{ $consignee->legal_name }} ({{ $consignee->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('consignee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Parte a Notificar --}}
                            <div class="col-md-6">
                                <label for="notify_party_id" class="form-label">Parte a Notificar</label>
                                <select class="form-select @error('notify_party_id') is-invalid @enderror" 
                                        id="notify_party_id" 
                                        name="notify_party_id">
                                    <option value="">Sin especificar</option>
                                    @foreach($formData['notifyParties'] as $party)
                                        <option value="{{ $party->id }}" {{ old('notify_party_id') == $party->id ? 'selected' : '' }}>
                                            {{ $party->legal_name }} ({{ $party->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('notify_party_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Propietario de Carga --}}
                            <div class="col-md-6">
                                <label for="cargo_owner_id" class="form-label">Propietario de la Carga</label>
                                <select class="form-select @error('cargo_owner_id') is-invalid @enderror" 
                                        id="cargo_owner_id" 
                                        name="cargo_owner_id">
                                    <option value="">Sin especificar</option>
                                    @foreach($formData['cargoOwners'] as $owner)
                                        <option value="{{ $owner->id }}" {{ old('cargo_owner_id') == $owner->id ? 'selected' : '' }}>
                                            {{ $owner->legal_name }} ({{ $owner->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('cargo_owner_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Puertos y Rutas --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ship me-2"></i>Puertos y Rutas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Puerto de Carga --}}
                            <div class="col-md-6">
                                <label for="loading_port_id" class="form-label required">Puerto de Carga</label>
                                <select class="form-select @error('loading_port_id') is-invalid @enderror" 
                                        id="loading_port_id" 
                                        name="loading_port_id" 
                                        required>
                                    <option value="">Seleccione puerto</option>
                                    @foreach($formData['loadingPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('loading_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('loading_port_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Puerto de Descarga --}}
                            <div class="col-md-6">
                                <label for="discharge_port_id" class="form-label required">Puerto de Descarga</label>
                                <select class="form-select @error('discharge_port_id') is-invalid @enderror" 
                                        id="discharge_port_id" 
                                        name="discharge_port_id" 
                                        required>
                                    <option value="">Seleccione puerto</option>
                                    @foreach($formData['dischargePorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('discharge_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('discharge_port_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Tipos de Carga --}}
                            <div class="col-md-6">
                                <label for="primary_cargo_type_id" class="form-label required">Tipo Principal de Carga</label>
                                <select class="form-select @error('primary_cargo_type_id') is-invalid @enderror" 
                                        id="primary_cargo_type_id" 
                                        name="primary_cargo_type_id" 
                                        required>
                                    <option value="">Seleccione tipo</option>
                                    @foreach($formData['cargoTypes'] as $type)
                                        <option value="{{ $type->id }}" {{ old('primary_cargo_type_id') == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_cargo_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Tipo de Embalaje --}}
                            <div class="col-md-6">
                                <label for="primary_packaging_type_id" class="form-label required">Tipo Principal de Embalaje</label>
                                <select class="form-select @error('primary_packaging_type_id') is-invalid @enderror" 
                                        id="primary_packaging_type_id" 
                                        name="primary_packaging_type_id" 
                                        required>
                                    <option value="">Seleccione tipo</option>
                                    @foreach($formData['packagingTypes'] as $type)
                                        <option value="{{ $type->id }}" {{ old('primary_packaging_type_id') == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_packaging_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Medidas y Pesos --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-weight me-2"></i>Medidas y Pesos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Total de Bultos --}}
                            <div class="col-md-3">
                                <label for="total_packages" class="form-label required">Total de Bultos</label>
                                <input type="number" 
                                       class="form-control @error('total_packages') is-invalid @enderror" 
                                       id="total_packages" 
                                       name="total_packages" 
                                       value="{{ old('total_packages') }}"
                                       min="1" 
                                       required>
                                @error('total_packages')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Peso Bruto --}}
                            <div class="col-md-3">
                                <label for="gross_weight_kg" class="form-label required">Peso Bruto (kg)</label>
                                <input type="number" 
                                       class="form-control @error('gross_weight_kg') is-invalid @enderror" 
                                       id="gross_weight_kg" 
                                       name="gross_weight_kg" 
                                       value="{{ old('gross_weight_kg') }}"
                                       step="0.01" 
                                       min="0.01" 
                                       required>
                                @error('gross_weight_kg')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Peso Neto --}}
                            <div class="col-md-3">
                                <label for="net_weight_kg" class="form-label">Peso Neto (kg)</label>
                                <input type="number" 
                                       class="form-control @error('net_weight_kg') is-invalid @enderror" 
                                       id="net_weight_kg" 
                                       name="net_weight_kg" 
                                       value="{{ old('net_weight_kg') }}"
                                       step="0.01" 
                                       min="0">
                                @error('net_weight_kg')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Volumen --}}
                            <div class="col-md-3">
                                <label for="volume_m3" class="form-label">Volumen (m³)</label>
                                <input type="number" 
                                       class="form-control @error('volume_m3') is-invalid @enderror" 
                                       id="volume_m3" 
                                       name="volume_m3" 
                                       value="{{ old('volume_m3') }}"
                                       step="0.001" 
                                       min="0">
                                @error('volume_m3')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- Características --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Características</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="requires_inspection" name="requires_inspection" value="1" {{ old('requires_inspection') ? 'checked' : '' }}>
                            <label class="form-check-label" for="requires_inspection">
                                Requiere Inspección
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="contains_dangerous_goods" name="contains_dangerous_goods" value="1" {{ old('contains_dangerous_goods') ? 'checked' : '' }}>
                            <label class="form-check-label" for="contains_dangerous_goods">
                                Contiene Mercadería Peligrosa
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="requires_refrigeration" name="requires_refrigeration" value="1" {{ old('requires_refrigeration') ? 'checked' : '' }}>
                            <label class="form-check-label" for="requires_refrigeration">
                                Requiere Refrigeración
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="is_transhipment" name="is_transhipment" value="1" {{ old('is_transhipment') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_transhipment">
                                Es Transbordo
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="is_partial_shipment" name="is_partial_shipment" value="1" {{ old('is_partial_shipment') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_partial_shipment">
                                Envío Parcial
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Observaciones --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Observaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="special_instructions" class="form-label">Instrucciones Especiales</label>
                            <textarea class="form-control @error('special_instructions') is-invalid @enderror" 
                                      id="special_instructions" 
                                      name="special_instructions" 
                                      rows="3" 
                                      placeholder="Instrucciones especiales para el manejo...">{{ old('special_instructions') }}</textarea>
                            @error('special_instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="internal_notes" class="form-label">Notas Internas</label>
                            <textarea class="form-control @error('internal_notes') is-invalid @enderror" 
                                      id="internal_notes" 
                                      name="internal_notes" 
                                      rows="3" 
                                      placeholder="Notas internas (no aparecen en documentos oficiales)...">{{ old('internal_notes') }}</textarea>
                            @error('internal_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Conocimiento
                            </button>
                            <button type="submit" name="action" value="save_and_add_items" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Guardar y Agregar Ítems
                            </button>
                            <a href="{{ route('company.bills-of-lading.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
.required::after {
    content: ' *';
    color: red;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calcular peso neto (85% del bruto por defecto)
    const grossWeightInput = document.getElementById('gross_weight_kg');
    const netWeightInput = document.getElementById('net_weight_kg');
    
    grossWeightInput.addEventListener('blur', function() {
        if (this.value && !netWeightInput.value) {
            netWeightInput.value = (parseFloat(this.value) * 0.85).toFixed(2);
        }
    });

    // Validar que consignatario sea diferente a cargador
    const shipperSelect = document.getElementById('shipper_id');
    const consigneeSelect = document.getElementById('consignee_id');
    
    function validateClients() {
        if (shipperSelect.value && consigneeSelect.value && shipperSelect.value === consigneeSelect.value) {
            consigneeSelect.setCustomValidity('El consignatario debe ser diferente al cargador');
        } else {
            consigneeSelect.setCustomValidity('');
        }
    }
    
    shipperSelect.addEventListener('change', validateClients);
    consigneeSelect.addEventListener('change', validateClients);
});
</script>
@endpush