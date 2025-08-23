<x-app-layout>
@php
$user = Auth::user();
$company = null;
$companyRoles = [];
if ($user) {
    if ($user->userable_type === 'App\\Models\\Company') {
        $company = $user->userable;
        $companyRoles = $company->company_roles ?? [];
    } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
        $company = $user->userable->company;
        $companyRoles = $company->company_roles ?? [];
    }
}

// Agrupar conocimientos por viaje
$voyageGroups = $billsOfLading->groupBy(function($bill) {
    return $bill->shipment->voyage->id;
});
@endphp

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Conocimientos de Embarque') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                {{ $billsOfLading->total() }} conocimientos en {{ $voyageGroups->count() }} viajes
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            @if(in_array('Cargas', $companyRoles))
                <a href="{{ route('company.bills-of-lading.create') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    + Nuevo Conocimiento
                </a>
            @endif
            <button onclick="toggleCompactView()" 
                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <span id="viewToggleText">Vista Compacta</span>
            </button>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        {{-- ESTADÍSTICAS RÁPIDAS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-blue-500">
                <div class="text-2xl font-bold text-blue-600">{{ $stats['total'] ?? 0 }}</div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-yellow-500">
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['draft'] ?? 0 }}</div>
                <div class="text-sm text-gray-600">Borradores</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-green-500">
                <div class="text-2xl font-bold text-green-600">{{ $stats['verified'] ?? 0 }}</div>
                <div class="text-sm text-gray-600">Verificados</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-purple-500">
                <div class="text-2xl font-bold text-purple-600">{{ $stats['consolidated'] ?? 0 }}</div>
                <div class="text-sm text-gray-600">Consolidados</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-red-500">
                <div class="text-2xl font-bold text-red-600">{{ $stats['dangerous_goods'] ?? 0 }}</div>
                <div class="text-sm text-gray-600">Peligrosos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-gray-500">
                <div class="text-2xl font-bold text-gray-600">{{ $voyageGroups->count() }}</div>
                <div class="text-sm text-gray-600">Viajes</div>
            </div>
        </div>

        {{-- FILTROS RÁPIDOS --}}
        <div class="bg-white rounded-lg shadow-sm mb-6 p-4">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center space-x-2">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="🔍 Buscar por número, cargador..."
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos los estados</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Borrador</option>
                    <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verificado</option>
                    <option value="sent_to_customs" {{ request('status') === 'sent_to_customs' ? 'selected' : '' }}>Enviado</option>
                </select>

                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Filtrar
                </button>
                
                @if(request()->hasAny(['search', 'status', 'shipper_id', 'consignee_id']))
                    <a href="{{ route('company.bills-of-lading.index') }}" 
                       class="text-gray-600 hover:text-gray-900 text-sm">
                        Limpiar filtros
                    </a>
                @endif
            </form>
        </div>

        {{-- VISTA AGRUPADA POR VIAJES --}}
        <div class="space-y-6" id="voyageGroups">
            @forelse($voyageGroups as $voyageId => $voyageBills)
                @php
                    $voyage = $voyageBills->first()->shipment->voyage;
                    $totalBills = $voyageBills->count();
                    $totalWeight = $voyageBills->sum('gross_weight_kg');
                    $totalPackages = $voyageBills->sum('total_packages');
                    $statusCounts = $voyageBills->countBy('status');
                    $hasDangerous = $voyageBills->contains('contains_dangerous_goods', true);
                @endphp

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 voyage-group">
                    {{-- HEADER DEL VIAJE --}}
                    <div class="voyage-header bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-t-lg border-b cursor-pointer"
                         onclick="toggleVoyage('{{ $voyageId }}')">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">
                                            <a href="{{ route('company.voyages.show', $voyage) }}" 
                                               class="hover:text-blue-600">
                                                🚢 {{ $voyage->voyage_number }}
                                            </a>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            {{ $totalBills }} conocimiento{{ $totalBills !== 1 ? 's' : '' }} • 
                                            {{ number_format($totalWeight) }} kg •
                                            {{ number_format($totalPackages) }} bultos
                                            @if($hasDangerous)
                                                <span class="ml-2 text-red-600 font-medium">⚠️ Mercancía Peligrosa</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                {{-- Estados del viaje --}}
                                <div class="flex space-x-2">
                                    @if($statusCounts->get('draft', 0) > 0)
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                            {{ $statusCounts->get('draft') }} Draft
                                        </span>
                                    @endif
                                    @if($statusCounts->get('verified', 0) > 0)
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                            {{ $statusCounts->get('verified') }} Verificado
                                        </span>
                                    @endif
                                    @if($statusCounts->get('sent_to_customs', 0) > 0)
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                                            {{ $statusCounts->get('sent_to_customs') }} Enviado
                                        </span>
                                    @endif
                                </div>

                                {{-- Botón expandir/contraer --}}
                                <button class="voyage-toggle p-2 hover:bg-white hover:bg-opacity-50 rounded-full transition-colors duration-200">
                                    <svg class="w-5 h-5 text-gray-600 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- CONTENIDO DEL VIAJE (conocimientos) --}}
                    <div class="voyage-content" id="voyage-{{ $voyageId }}" style="display: block;">
                        <div class="divide-y divide-gray-100">
                            @foreach($voyageBills as $bill)
                                @php
                                    $statusColors = [
                                        'draft' => 'bg-yellow-50 border-yellow-200',
                                        'pending_review' => 'bg-blue-50 border-blue-200',
                                        'verified' => 'bg-green-50 border-green-200',
                                        'sent_to_customs' => 'bg-purple-50 border-purple-200',
                                        'accepted' => 'bg-green-50 border-green-200',
                                        'rejected' => 'bg-red-50 border-red-200',
                                        'completed' => 'bg-gray-50 border-gray-200',
                                        'cancelled' => 'bg-red-50 border-red-200',
                                    ];
                                    $statusLabels = [
                                        'draft' => 'Borrador',
                                        'pending_review' => 'Pendiente Revisión',
                                        'verified' => 'Verificado',
                                        'sent_to_customs' => 'Enviado a Aduana',
                                        'accepted' => 'Aceptado',
                                        'rejected' => 'Rechazado',
                                        'completed' => 'Completado',
                                        'cancelled' => 'Cancelado',
                                    ];
                                    $cardClass = $statusColors[$bill->status] ?? 'bg-gray-50 border-gray-200';
                                @endphp

                                <div class="bill-row p-6 hover:bg-gray-50 {{ $cardClass }} border-l-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4">
                                            {{-- Información del Conocimiento --}}
                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <h4 class="text-lg font-semibold text-gray-900">
                                                        <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                           class="hover:text-blue-600">
                                                            📄 {{ $bill->bill_number }}
                                                        </a>
                                                    </h4>
                                                    @if($bill->is_master_bill)
                                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">Master</span>
                                                    @endif
                                                    @if($bill->is_house_bill)
                                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">House</span>
                                                    @endif
                                                    @if($bill->contains_dangerous_goods)
                                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">⚠️</span>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    {{ $bill->primaryCargoType->name ?? 'Carga General' }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    Creado: {{ $bill->created_at->format('d/m/Y') }}
                                                </p>
                                            </div>

                                            {{-- Partes --}}
                                            <div>
                                                <div class="text-sm">
                                                    <div class="font-medium text-gray-900">📤 {{ $bill->shipper->legal_name ?? 'N/A' }}</div>
                                                    <div class="text-gray-600">📥 {{ $bill->consignee->legal_name ?? 'N/A' }}</div>
                                                </div>
                                            </div>

                                            {{-- Ruta --}}
                                            <div>
                                                <div class="text-sm">
                                                    <div class="text-gray-900">
                                                        🏠 {{ $bill->loadingPort->name ?? 'N/A' }}
                                                    </div>
                                                    <div class="text-gray-600 flex items-center">
                                                        <span class="mr-1">→</span> 
                                                        {{ $bill->dischargePort->name ?? 'N/A' }}
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Datos Técnicos --}}
                                            <div>
                                                <div class="text-sm">
                                                    <div class="text-gray-900 font-medium">
                                                        ⚖️ {{ number_format($bill->gross_weight_kg ?? 0) }} kg
                                                    </div>
                                                    <div class="text-gray-600">
                                                        📦 {{ number_format($bill->total_packages ?? 0) }} bultos
                                                    </div>
                                                    @if($bill->volume_m3)
                                                        <div class="text-gray-500 text-xs">
                                                            📐 {{ number_format($bill->volume_m3, 2) }} m³
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Estado y Acciones --}}
                                        <div class="flex items-center space-x-3 ml-4">
                                            {{-- Estado --}}
                                            <div class="text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$bill->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ $statusLabels[$bill->status] ?? $bill->status }}
                                                </span>
                                                @if($bill->verified_at)
                                                    <div class="text-xs text-green-600 mt-1">
                                                        ✓ {{ $bill->verified_at->format('d/m/Y') }}
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Acciones --}}
                                            <div class="flex flex-col space-y-1">
                                                <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                   class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                    Ver
                                                </a>
                                                @if(in_array($bill->status, ['draft', 'pending_review']) && in_array('Cargas', $companyRoles))
                                                    <a href="{{ route('company.bills-of-lading.edit', $bill) }}" 
                                                       class="text-green-600 hover:text-green-900 text-sm font-medium">
                                                        Editar
                                                    </a>
                                                @endif
                                                <a href="{{ route('company.bills-of-lading.pdf', $bill) }}" 
                                                   class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                    PDF
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Vista Compacta (oculta por defecto) --}}
                                    <div class="compact-view mt-4 pt-4 border-t border-gray-200 hidden">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex space-x-4">
                                                <span>{{ $bill->bill_number }}</span>
                                                <span>{{ $bill->shipper->legal_name ?? 'N/A' }}</span>
                                                <span>{{ number_format($bill->gross_weight_kg ?? 0) }} kg</span>
                                            </div>
                                            <div class="flex space-x-2">
                                                <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                   class="text-blue-600 hover:text-blue-900">Ver</a>
                                                @if(in_array($bill->status, ['draft', 'pending_review']) && in_array('Cargas', $companyRoles))
                                                    <a href="{{ route('company.bills-of-lading.edit', $bill) }}" 
                                                       class="text-green-600 hover:text-green-900">Editar</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay conocimientos registrados</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(request()->hasAny(['search', 'status']))
                            No se encontraron resultados con los filtros aplicados.
                        @else
                            Comience creando su primer conocimiento de embarque.
                        @endif
                    </p>
                    @if(in_array('Cargas', $companyRoles) && !request()->hasAny(['search', 'status']))
                        <div class="mt-6">
                            <a href="{{ route('company.bills-of-lading.create') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Crear primer conocimiento
                            </a>
                        </div>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- PAGINACIÓN --}}
        @if($billsOfLading->hasPages())
            <div class="mt-6">
                {{ $billsOfLading->links() }}
            </div>
        @endif
    </div>
</div>

{{-- JavaScript para interactividad --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Estado de vista compacta
    let isCompactView = false;

    // Toggle vista compacta
    window.toggleCompactView = function() {
        isCompactView = !isCompactView;
        const toggleText = document.getElementById('viewToggleText');
        const billRows = document.querySelectorAll('.bill-row');
        const compactViews = document.querySelectorAll('.compact-view');

        if (isCompactView) {
            toggleText.textContent = 'Vista Detallada';
            billRows.forEach(row => {
                row.classList.add('compact');
                const mainContent = row.querySelector('.grid');
                if (mainContent) mainContent.style.display = 'none';
            });
            compactViews.forEach(view => view.classList.remove('hidden'));
        } else {
            toggleText.textContent = 'Vista Compacta';
            billRows.forEach(row => {
                row.classList.remove('compact');
                const mainContent = row.querySelector('.grid');
                if (mainContent) mainContent.style.display = 'grid';
            });
            compactViews.forEach(view => view.classList.add('hidden'));
        }
    };

    // Toggle viajes
    window.toggleVoyage = function(voyageId) {
        const content = document.getElementById('voyage-' + voyageId);
        const toggle = content.parentElement.querySelector('.voyage-toggle svg');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            toggle.style.transform = 'rotate(0deg)';
        } else {
            content.style.display = 'none';
            toggle.style.transform = 'rotate(-90deg)';
        }
    };

    // Auto-expandir primer viaje si solo hay uno
    const voyageGroups = document.querySelectorAll('.voyage-group');
    if (voyageGroups.length === 1) {
        const firstContent = voyageGroups[0].querySelector('.voyage-content');
        if (firstContent) firstContent.style.display = 'block';
    }
});
</script>

<style>
.bill-row.compact {
    padding: 12px 24px !important;
}

.voyage-header:hover {
    background: linear-gradient(to right, rgb(239 246 255), rgb(224 231 255));
}

.voyage-toggle svg {
    transition: transform 0.2s ease-in-out;
}

.voyage-content {
    transition: all 0.3s ease-in-out;
}

@media (max-width: 768px) {
    .bill-row .grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .bill-row .flex.items-center.space-x-3 {
        margin-left: 0;
        margin-top: 16px;
        justify-content: space-between;
    }
}
</style>

</x-app-layout>