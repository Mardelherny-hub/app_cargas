@extends('layouts.company')

@section('title', 'Conocimiento ' . $billOfLading->bill_number)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                Conocimiento {{ $billOfLading->bill_number }}
                @if($billOfLading->contains_dangerous_goods)
                    <span class="badge bg-warning ms-2" title="Mercadería Peligrosa">
                        <i class="fas fa-exclamation-triangle"></i> Peligrosa
                    </span>
                @endif
                @if($billOfLading->requires_refrigeration)
                    <span class="badge bg-info ms-2" title="Requiere Refrigeración">
                        <i class="fas fa-snowflake"></i> Refrigerado
                    </span>
                @endif
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('company.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('company.bills-of-lading.index') }}">Conocimientos</a></li>
                    <li class="breadcrumb-item active">{{ $billOfLading->bill_number }}</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group" role="group">
            <a href="{{ route('company.bills-of-lading.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
            @if($canEdit)
                <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i>Editar
                </a>
            @endif
            <button type="button" class="btn btn-info">
                <i class="fas fa-print me-2"></i>Imprimir
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- Estado y Información Principal --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información Principal</h5>
                    @php
                        $statusColors = [
                            'draft' => 'secondary', 'pending_review' => 'warning', 'verified' => 'info',
                            'sent_to_customs' => 'primary', 'accepted' => 'success', 'rejected' => 'danger',
                            'completed' => 'success', 'cancelled' => 'dark'
                        ];
                        $statusColor = $statusColors[$billOfLading->status] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $statusColor }} fs-6">{{ $billOfLading->status_label }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <strong>Número:</strong><br>
                            <span class="fs-5">{{ $billOfLading->bill_number }}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Fecha:</strong><br>
                            {{ $billOfLading->bill_date->format('d/m/Y') }}
                        </div>
                        <div class="col-md-3">
                            <strong>Envío:</strong><br>
                            {{ $billOfLading->shipment->shipment_number ?? 'N/A' }}<br>
                            <small class="text-muted">{{ $billOfLading->shipment->voyage->voyage_number ?? 'N/A' }}</small>
                        </div>
                        <div class="col-md-3">
                            <strong>Términos Flete:</strong><br>
                            {{ ucfirst(str_replace('_', ' ', $billOfLading->freight_terms)) }}
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
                    <div class="row g-4">
                        {{-- Cargador --}}
                        <div class="col-md-6">
                            <div class="border-start border-primary border-3 ps-3">
                                <h6 class="text-primary mb-2">Cargador/Exportador</h6>
                                @if($billOfLading->shipper)
                                    <strong>{{ $billOfLading->shipper->legal_name }}</strong><br>
                                    @if($billOfLading->shipper->commercial_name && $billOfLading->shipper->commercial_name !== $billOfLading->shipper->legal_name)
                                        <em>{{ $billOfLading->shipper->commercial_name }}</em><br>
                                    @endif
                                    <strong>{{ $billOfLading->shipper->tax_id }}</strong><br>
                                    {{ $billOfLading->shipper->address }}<br>
                                    {{ $billOfLading->shipper->city }}
                                @else
                                    <span class="text-muted">No especificado</span>
                                @endif
                            </div>
                        </div>

                        {{-- Consignatario --}}
                        <div class="col-md-6">
                            <div class="border-start border-success border-3 ps-3">
                                <h6 class="text-success mb-2">Consignatario/Importador</h6>
                                @if($billOfLading->consignee)
                                    <strong>{{ $billOfLading->consignee->legal_name }}</strong><br>
                                    @if($billOfLading->consignee->commercial_name && $billOfLading->consignee->commercial_name !== $billOfLading->consignee->legal_name)
                                        <em>{{ $billOfLading->consignee->commercial_name }}</em><br>
                                    @endif
                                    <strong>{{ $billOfLading->consignee->tax_id }}</strong><br>
                                    {{ $billOfLading->consignee->address }}<br>
                                    {{ $billOfLading->consignee->city }}
                                @else
                                    <span class="text-muted">No especificado</span>
                                @endif
                            </div>
                        </div>

                        {{-- Parte a Notificar --}}
                        @if($billOfLading->notifyParty)
                        <div class="col-md-6">
                            <div class="border-start border-info border-3 ps-3">
                                <h6 class="text-info mb-2">Parte a Notificar</h6>
                                <strong>{{ $billOfLading->notifyParty->legal_name }}</strong><br>
                                <strong>{{ $billOfLading->notifyParty->tax_id }}</strong>
                            </div>
                        </div>
                        @endif

                        {{-- Propietario de Carga --}}
                        @if($billOfLading->cargoOwner)
                        <div class="col-md-6">
                            <div class="border-start border-warning border-3 ps-3">
                                <h6 class="text-warning mb-2">Propietario de la Carga</h6>
                                <strong>{{ $billOfLading->cargoOwner->legal_name }}</strong><br>
                                <strong>{{ $billOfLading->cargoOwner->tax_id }}</strong>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Ruta y Puertos --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-route me-2"></i>Ruta y Puertos</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="text-center">
                                <div class="border rounded p-3 bg-light">
                                    <i class="fas fa-anchor fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-1">Puerto de Carga</h6>
                                    <strong>{{ $billOfLading->loadingPort->name ?? 'N/A' }}</strong><br>
                                    <small class="text-muted">{{ $billOfLading->loadingPort->code ?? '' }}</small>
                                </div>
                                @if($billOfLading->loading_date)
                                    <small class="text-muted mt-2 d-block">
                                        Carga: {{ $billOfLading->loading_date->format('d/m/Y') }}
                                    </small>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <i class="fas fa-arrow-right fa-2x text-muted"></i>
                        </div>
                        <div class="col-md-5">
                            <div class="text-center">
                                <div class="border rounded p-3 bg-light">
                                    <i class="fas fa-anchor fa-2x text-success mb-2"></i>
                                    <h6 class="mb-1">Puerto de Descarga</h6>
                                    <strong>{{ $billOfLading->dischargePort->name ?? 'N/A' }}</strong><br>
                                    <small class="text-muted">{{ $billOfLading->dischargePort->code ?? '' }}</small>
                                </div>
                                @if($billOfLading->discharge_date)
                                    <small class="text-muted mt-2 d-block">
                                        Descarga: {{ $billOfLading->discharge_date->format('d/m/Y') }}
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Puertos adicionales --}}
                    @if($billOfLading->transshipmentPort || $billOfLading->finalDestinationPort)
                        <hr class="my-4">
                        <div class="row">
                            @if($billOfLading->transshipmentPort)
                                <div class="col-md-6">
                                    <h6 class="text-info">Puerto de Transbordo</h6>
                                    <strong>{{ $billOfLading->transshipmentPort->name }}</strong>
                                    ({{ $billOfLading->transshipmentPort->code }})
                                </div>
                            @endif
                            @if($billOfLading->finalDestinationPort)
                                <div class="col-md-6">
                                    <h6 class="text-success">Destino Final</h6>
                                    <strong>{{ $billOfLading->finalDestinationPort->name }}</strong>
                                    ({{ $billOfLading->finalDestinationPort->code }})
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Ítems de Mercadería --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Ítems de Mercadería</h5>
                    @if($canAddItems)
                        <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-1"></i>Agregar Ítem
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    @if($billOfLading->shipmentItems->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Línea</th>
                                        <th>Descripción</th>
                                        <th>Tipo Carga</th>
                                        <th class="text-end">Bultos</th>
                                        <th class="text-end">Peso (kg)</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($billOfLading->shipmentItems as $item)
                                        <tr>
                                            <td>{{ $item->line_number }}</td>
                                            <td>{{ Str::limit($item->cargo_description, 50) }}</td>
                                            <td>{{ $item->cargoType->name ?? 'N/A' }}</td>
                                            <td class="text-end">{{ number_format($item->package_quantity) }}</td>
                                            <td class="text-end">{{ number_format($item->gross_weight_kg, 2) }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('company.shipment-items.show', $item) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay ítems de mercadería registrados</p>
                            @if($canAddItems)
                                <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Agregar Primer Ítem
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Medidas y Pesos --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-weight me-2"></i>Medidas y Pesos</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-primary mb-1">{{ number_format($billOfLading->total_packages) }}</h3>
                                <small class="text-muted">Total Bultos</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-success mb-1">{{ number_format($billOfLading->gross_weight_kg, 0) }}</h3>
                                <small class="text-muted">Peso Bruto (kg)</small>
                            </div>
                        </div>
                        @if($billOfLading->net_weight_kg)
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-info mb-1">{{ number_format($billOfLading->net_weight_kg, 0) }}</h3>
                                <small class="text-muted">Peso Neto (kg)</small>
                            </div>
                        </div>
                        @endif
                        @if($billOfLading->volume_m3)
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-warning mb-1">{{ number_format($billOfLading->volume_m3, 2) }}</h3>
                                <small class="text-muted">Volumen (m³)</small>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Características --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Características</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @if($billOfLading->requires_inspection)
                            <span class="badge bg-warning">Requiere Inspección</span>
                        @endif
                        @if($billOfLading->contains_dangerous_goods)
                            <span class="badge bg-danger">Mercadería Peligrosa</span>
                        @endif
                        @if($billOfLading->requires_refrigeration)
                            <span class="badge bg-info">Refrigerado</span>
                        @endif
                        @if($billOfLading->is_transhipment)
                            <span class="badge bg-primary">Transbordo</span>
                        @endif
                        @if($billOfLading->is_partial_shipment)
                            <span class="badge bg-secondary">Envío Parcial</span>
                        @endif
                        @if($billOfLading->allows_partial_delivery)
                            <span class="badge bg-success">Permite Entrega Parcial</span>
                        @endif
                    </div>
                    
                    @if($billOfLading->primaryCargoType)
                        <hr>
                        <div>
                            <strong>Tipo de Carga:</strong><br>
                            {{ $billOfLading->primaryCargoType->name }}
                        </div>
                    @endif
                    
                    @if($billOfLading->primaryPackagingType)
                        <div class="mt-2">
                            <strong>Tipo de Embalaje:</strong><br>
                            {{ $billOfLading->primaryPackagingType->name }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Webservices --}}
            @if($billOfLading->webservice_status)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cloud me-2"></i>Estado Webservices</h5>
                </div>
                <div class="card-body">
                    @php
                        $wsColors = ['sent' => 'primary', 'accepted' => 'success', 'rejected' => 'danger'];
                        $wsColor = $wsColors[$billOfLading->webservice_status] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $wsColor }} mb-2">
                        {{ ucfirst($billOfLading->webservice_status) }}
                    </span>
                    
                    @if($billOfLading->webservice_reference)
                        <div class="small">
                            <strong>Referencia:</strong> {{ $billOfLading->webservice_reference }}
                        </div>
                    @endif
                    
                    @if($billOfLading->webservice_sent_at)
                        <div class="small text-muted">
                            Enviado: {{ $billOfLading->webservice_sent_at->format('d/m/Y H:i') }}
                        </div>
                    @endif
                    
                    @if($billOfLading->webservice_error_message)
                        <div class="small text-danger mt-2">
                            <strong>Error:</strong> {{ $billOfLading->webservice_error_message }}
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Observaciones --}}
            @if($billOfLading->special_instructions || $billOfLading->handling_instructions || $billOfLading->customs_remarks)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Observaciones</h5>
                </div>
                <div class="card-body">
                    @if($billOfLading->special_instructions)
                        <div class="mb-3">
                            <strong>Instrucciones Especiales:</strong><br>
                            <p class="mb-0">{{ $billOfLading->special_instructions }}</p>
                        </div>
                    @endif
                    
                    @if($billOfLading->handling_instructions)
                        <div class="mb-3">
                            <strong>Instrucciones de Manejo:</strong><br>
                            <p class="mb-0">{{ $billOfLading->handling_instructions }}</p>
                        </div>
                    @endif
                    
                    @if($billOfLading->customs_remarks)
                        <div class="mb-3">
                            <strong>Observaciones Aduaneras:</strong><br>
                            <p class="mb-0">{{ $billOfLading->customs_remarks }}</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Auditoría --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Auditoría</h5>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <strong>Creado:</strong><br>
                            {{ $billOfLading->created_at->format('d/m/Y H:i') }}<br>
                            <span class="text-muted">por {{ $billOfLading->createdByUser->name ?? 'Sistema' }}</span>
                        </div>
                        
                        @if($billOfLading->updated_at != $billOfLading->created_at)
                        <div class="mb-2">
                            <strong>Última actualización:</strong><br>
                            {{ $billOfLading->updated_at->format('d/m/Y H:i') }}<br>
                            <span class="text-muted">por {{ $billOfLading->lastUpdatedByUser->name ?? 'Sistema' }}</span>
                        </div>
                        @endif
                        
                        @if($billOfLading->verified_at)
                        <div class="mb-2">
                            <strong>Verificado:</strong><br>
                            {{ $billOfLading->verified_at->format('d/m/Y H:i') }}<br>
                            <span class="text-muted">por {{ $billOfLading->verifiedByUser->name ?? 'Sistema' }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection