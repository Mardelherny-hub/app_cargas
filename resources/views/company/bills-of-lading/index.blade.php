@extends('layouts.company')

@section('title', 'Conocimientos de Embarque')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Conocimientos de Embarque</h1>
            <p class="text-muted mb-0">Gestión de conocimientos para manifiestos aduaneros</p>
        </div>
        
        @if($canManage)
        <div>
            <a href="{{ route('company.bills-of-lading.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                Nuevo Conocimiento
            </a>
        </div>
        @endif
    </div>

    {{-- Filtros --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>
                Filtros de Búsqueda
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('company.bills-of-lading.index') }}">
                <div class="row g-3">
                    {{-- Búsqueda por texto --}}
                    <div class="col-md-4">
                        <label for="search" class="form-label">Búsqueda General</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Número BL, cargador, consignatario...">
                    </div>

                    {{-- Estado --}}
                    <div class="col-md-2">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos los estados</option>
                            @foreach($filterData['statuses'] as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Envío --}}
                    <div class="col-md-3">
                        <label for="shipment_id" class="form-label">Envío</label>
                        <select class="form-select" id="shipment_id" name="shipment_id">
                            <option value="">Todos los envíos</option>
                            @foreach($filterData['shipments'] as $shipment)
                                <option value="{{ $shipment->id }}" {{ request('shipment_id') == $shipment->id ? 'selected' : '' }}>
                                    {{ $shipment->shipment_number }} ({{ $shipment->voyage->voyage_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Puerto de carga --}}
                    <div class="col-md-3">
                        <label for="loading_port_id" class="form-label">Puerto de Carga</label>
                        <select class="form-select" id="loading_port_id" name="loading_port_id">
                            <option value="">Todos los puertos</option>
                            @foreach($filterData['loadingPorts'] as $port)
                                <option value="{{ $port->id }}" {{ request('loading_port_id') == $port->id ? 'selected' : '' }}>
                                    {{ $port->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    {{-- Cargador --}}
                    <div class="col-md-3">
                        <label for="shipper_id" class="form-label">Cargador</label>
                        <select class="form-select" id="shipper_id" name="shipper_id">
                            <option value="">Todos los cargadores</option>
                            @foreach($filterData['shippers'] as $shipper)
                                <option value="{{ $shipper->id }}" {{ request('shipper_id') == $shipper->id ? 'selected' : '' }}>
                                    {{ $shipper->legal_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Consignatario --}}
                    <div class="col-md-3">
                        <label for="consignee_id" class="form-label">Consignatario</label>
                        <select class="form-select" id="consignee_id" name="consignee_id">
                            <option value="">Todos los consignatarios</option>
                            @foreach($filterData['consignees'] as $consignee)
                                <option value="{{ $consignee->id }}" {{ request('consignee_id') == $consignee->id ? 'selected' : '' }}>
                                    {{ $consignee->legal_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Fecha desde --}}
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Fecha Desde</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_from" 
                               name="date_from" 
                               value="{{ request('date_from') }}">
                    </div>

                    {{-- Fecha hasta --}}
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Fecha Hasta</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_to" 
                               name="date_to" 
                               value="{{ request('date_to') }}">
                    </div>

                    {{-- Filtros especiales --}}
                    <div class="col-md-2">
                        <label class="form-label">Filtros Especiales</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dangerous_goods" name="dangerous_goods" value="1" {{ request('dangerous_goods') ? 'checked' : '' }}>
                            <label class="form-check-label" for="dangerous_goods">
                                Mercadería Peligrosa
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="refrigerated" name="refrigerated" value="1" {{ request('refrigerated') ? 'checked' : '' }}>
                            <label class="form-check-label" for="refrigerated">
                                Refrigerado
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="{{ route('company.bills-of-lading.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de resultados --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                Resultados ({{ $billsOfLading->total() }} registros)
            </h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-download me-1"></i>Exportar Excel
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            @if($billsOfLading->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'bill_number', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">
                                        Nº Conocimiento
                                        @if(request('sort') === 'bill_number')
                                            <i class="fas fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'bill_date', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">
                                        Fecha
                                        @if(request('sort') === 'bill_date')
                                            <i class="fas fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th>Envío</th>
                                <th>Cargador</th>
                                <th>Consignatario</th>
                                <th>Ruta</th>
                                <th class="text-end">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'gross_weight_kg', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">
                                        Peso (kg)
                                        @if(request('sort') === 'gross_weight_kg')
                                            <i class="fas fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($billsOfLading as $bill)
                                <tr>
                                    <td>
                                        <strong>{{ $bill->bill_number }}</strong>
                                        @if($bill->contains_dangerous_goods)
                                            <span class="badge bg-warning ms-2" title="Mercadería Peligrosa">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </span>
                                        @endif
                                        @if($bill->requires_refrigeration)
                                            <span class="badge bg-info ms-1" title="Requiere Refrigeración">
                                                <i class="fas fa-snowflake"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $bill->bill_date->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="small">
                                            <strong>{{ $bill->shipment->shipment_number ?? 'N/A' }}</strong><br>
                                            <span class="text-muted">{{ $bill->shipment->voyage->voyage_number ?? 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>{{ Str::limit($bill->shipper->legal_name ?? 'N/A', 30) }}</strong><br>
                                            <span class="text-muted">{{ $bill->shipper->tax_id ?? '' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>{{ Str::limit($bill->consignee->legal_name ?? 'N/A', 30) }}</strong><br>
                                            <span class="text-muted">{{ $bill->consignee->tax_id ?? '' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>{{ $bill->loadingPort->name ?? 'N/A' }}</strong>
                                            <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                            <strong>{{ $bill->dischargePort->name ?? 'N/A' }}</strong>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <strong>{{ number_format($bill->gross_weight_kg, 0, ',', '.') }}</strong><br>
                                        <small class="text-muted">{{ $bill->total_packages }} bultos</small>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $statusColors = [
                                                'draft' => 'secondary',
                                                'pending_review' => 'warning',
                                                'verified' => 'info',
                                                'sent_to_customs' => 'primary',
                                                'accepted' => 'success',
                                                'rejected' => 'danger',
                                                'completed' => 'success',
                                                'cancelled' => 'dark'
                                            ];
                                            $statusColor = $statusColors[$bill->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $statusColor }}">
                                            {{ $bill->status_label }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($canManage && $bill->canBeEdited())
                                                <a href="{{ route('company.bills-of-lading.edit', $bill) }}" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endif

                                            @if($canManage && $bill->canBeDeleted())
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        title="Eliminar"
                                                        onclick="confirmDelete('{{ $bill->id }}', '{{ $bill->bill_number }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="d-flex justify-content-between align-items-center p-3">
                    <div class="text-muted">
                        Mostrando {{ $billsOfLading->firstItem() }} a {{ $billsOfLading->lastItem() }} 
                        de {{ $billsOfLading->total() }} resultados
                    </div>
                    {{ $billsOfLading->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No se encontraron conocimientos de embarque</h5>
                    <p class="text-muted">
                        @if(request()->hasAny(['search', 'status', 'shipment_id', 'shipper_id', 'consignee_id']))
                            Intenta modificar los filtros de búsqueda.
                        @else
                            Aún no hay conocimientos de embarque registrados.
                        @endif
                    </p>
                    @if($canManage)
                        <a href="{{ route('company.bills-of-lading.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Crear Primer Conocimiento
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal de confirmación para eliminar --}}
@if($canManage)
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar el conocimiento de embarque <strong id="deleteItemName"></strong>?</p>
                <p class="text-danger small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function confirmDelete(billId, billNumber) {
    document.getElementById('deleteItemName').textContent = billNumber;
    document.getElementById('deleteForm').action = `/company/bills-of-lading/${billId}`;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// Auto-submit form cuando cambian ciertos filtros
document.addEventListener('DOMContentLoaded', function() {
    const autoSubmitFields = ['status', 'shipment_id', 'loading_port_id', 'shipper_id', 'consignee_id'];
    
    autoSubmitFields.forEach(function(fieldName) {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });
});
</script>
@endpush