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
@endphp

<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conocimiento de Embarque') }} - {{ $billOfLading->bill_number }}
        </h2>
        <div class="flex space-x-2">
                <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Editar
                </a>
            <a href="{{ route('company.bills-of-lading.pdf', $billOfLading) }}" 
               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                PDF
            </a>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        {{-- NAVEGACIÓN CONTEXTUAL --}}
        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                @if($billOfLading->shipment && $billOfLading->shipment->voyage)
                    <a href="{{ route('company.voyages.show', $billOfLading->shipment->voyage) }}" 
                       class="hover:text-gray-700">
                        {{ $billOfLading->shipment->voyage->voyage_number }}
                    </a>
                    <span>→</span>
                @endif
                @if($billOfLading->shipment)
                    <a href="{{ route('company.shipments.show', $billOfLading->shipment) }}" 
                       class="hover:text-gray-700">
                        {{ $billOfLading->shipment->shipment_number }}
                    </a>
                    <span>→</span>
                @endif
                <span class="text-gray-900 font-medium">{{ $billOfLading->bill_number }}</span>
            </nav>

            {{-- ESTADO Y CARACTERÍSTICAS --}}
            <div class="flex items-center space-x-3 mb-4">
                @php
                    $statusColors = [
                        'draft' => 'bg-yellow-100 text-yellow-800',
                        'pending_review' => 'bg-blue-100 text-blue-800',
                        'verified' => 'bg-green-100 text-green-800',
                        'sent_to_customs' => 'bg-purple-100 text-purple-800',
                        'accepted' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'completed' => 'bg-gray-100 text-gray-800',
                        'cancelled' => 'bg-red-100 text-red-800',
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
                @endphp
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$billOfLading->status] ?? 'bg-gray-100 text-gray-800' }}">
                    {{ $statusLabels[$billOfLading->status] ?? $billOfLading->status }}
                </span>

                @if($billOfLading->is_master_bill)
                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">Master</span>
                @endif
                @if($billOfLading->is_house_bill)
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">House</span>
                @endif
                @if($billOfLading->contains_dangerous_goods)
                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">⚠️ Peligroso</span>
                @endif
            </div>

        </div>

        {{-- GRID PRINCIPAL --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- COLUMNA PRINCIPAL (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- INFORMACIÓN BÁSICA --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Información Básica
                        </h3>
                        @if(isset($permissions['can_edit']) && $permissions['can_edit'])
                            <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}" 
                               class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                Editar →
                            </a>
                        @endif
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Número BL</dt>
                                <dd class="text-sm font-semibold text-gray-900">{{ $billOfLading->bill_number }}</dd>
                            </div>
                            @if($billOfLading->master_bill_number)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Master BL</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->master_bill_number }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->house_bill_number)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">House BL</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->house_bill_number }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->permiso_embarque)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Permiso de Embarque</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->permiso_embarque }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha BL</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->bill_date ? $billOfLading->bill_date->format('d/m/Y') : 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Referencia Interna</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->internal_reference ?? 'N/A' }}</dd>
                            </div>
                            @if($billOfLading->shipment && $billOfLading->shipment->voyage)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Viaje</dt>
                                <dd class="text-sm text-gray-900">
                                    <a href="{{ route('company.voyages.show', $billOfLading->shipment->voyage) }}" 
                                       class="text-blue-600 hover:text-blue-900">
                                        {{ $billOfLading->shipment->voyage->voyage_number }}
                                    </a>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- PARTES INVOLUCRADAS --}}
                {{-- ============================================================ --}}


                {{-- Partes Involucradas (CON DIRECCIONES ESPECÍFICAS) --}}
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Partes Involucradas</h3>
                        
                        {{-- Indicador de direcciones específicas --}}
                        @if($billOfLading->hasSpecificAddresses())
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h12a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V8z" clip-rule="evenodd"/>
                                </svg>
                                Direcciones Específicas
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Cargador/Exportador --}}
                        <div class="border-l-4 border-green-500 pl-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Cargador/Exportador</h4>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ $billOfLading->getShipperDisplayName() }}</p>
                                    <p class="text-sm text-gray-600">Tax ID: {{ $billOfLading->shipper->tax_id ?? '-' }}</p>
                                    
                                    {{-- Dirección específica o genérica --}}
                                    <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                                        <p class="text-gray-700 font-medium">Dirección:</p>
                                        <p class="text-gray-600">{{ $billOfLading->getShipperDisplayAddress() }}</p>
                                        
                                        {{-- Indicador si es específica --}}
                                        @if($billOfLading->shipperContact && $billOfLading->shipperContact->use_specific_data)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-xs font-medium text-blue-700 bg-blue-100 rounded">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Dirección específica
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if($billOfLading->shipper->country)
                                        <p class="text-sm text-gray-500 mt-1">País: {{ $billOfLading->shipper->country->name }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('company.clients.show', $billOfLading->shipper) }}" 
                                class="text-blue-600 hover:text-blue-900 text-xs">
                                    Ver →
                                </a>
                            </div>
                        </div>

                        {{-- Consignatario/Importador --}}
                        @if($billOfLading->consignee)
                        <div class="border-l-4 border-blue-500 pl-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Consignatario/Importador</h4>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ $billOfLading->getConsigneeDisplayName() }}</p>
                                    <p class="text-sm text-gray-600">Tax ID: {{ $billOfLading->consignee->tax_id ?? '-' }}</p>
                                    
                                    {{-- Dirección específica o genérica --}}
                                    <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                                        <p class="text-gray-700 font-medium">Dirección:</p>
                                        <p class="text-gray-600">{{ $billOfLading->getConsigneeDisplayAddress() }}</p>
                                        
                                        {{-- Indicador si es específica --}}
                                        @if($billOfLading->consigneeContact && $billOfLading->consigneeContact->use_specific_data)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-xs font-medium text-blue-700 bg-blue-100 rounded">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Dirección específica
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if($billOfLading->consignee->country)
                                        <p class="text-sm text-gray-500 mt-1">País: {{ $billOfLading->consignee->country->name }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('company.clients.show', $billOfLading->consignee) }}" 
                                class="text-blue-600 hover:text-blue-900 text-xs">
                                    Ver →
                                </a>
                            </div>
                        </div>
                        @endif

                        {{-- Parte a Notificar --}}
                        @if($billOfLading->notifyParty)
                        <div class="border-l-4 border-yellow-500 pl-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Parte a Notificar</h4>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ $billOfLading->getNotifyPartyDisplayName() }}</p>
                                    <p class="text-sm text-gray-600">Tax ID: {{ $billOfLading->notifyParty->tax_id ?? '-' }}</p>
                                    
                                    {{-- Dirección específica o genérica --}}
                                    <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                                        <p class="text-gray-700 font-medium">Dirección:</p>
                                        <p class="text-gray-600">{{ $billOfLading->getNotifyPartyDisplayAddress() }}</p>
                                        
                                        {{-- Indicador si es específica --}}
                                        @php
                                            $notifySpecificContact = $billOfLading->specificContacts()->where('role', 'notify_party')->first();
                                        @endphp
                                        @if($notifySpecificContact && $notifySpecificContact->use_specific_data)
                                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-xs font-medium text-blue-700 bg-blue-100 rounded">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Dirección específica
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('company.clients.show', $billOfLading->notifyParty) }}" 
                                class="text-blue-600 hover:text-blue-900 text-xs">
                                    Ver →
                                </a>
                            </div>
                        </div>
                        @endif

                        {{-- Propietario de Carga --}}
                        @if($billOfLading->cargoOwner)
                        <div class="border-l-4 border-purple-500 pl-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide">Propietario de Carga</h4>
                                    <p class="text-lg font-semibold text-gray-900 mt-1">{{ $billOfLading->cargoOwner->legal_name }}</p>
                                    <p class="text-sm text-gray-600">Tax ID: {{ $billOfLading->cargoOwner->tax_id ?? '-' }}</p>
                                    
                                    {{-- Dirección genérica (cargo owner no tiene específicas por ahora) --}}
                                    @if($billOfLading->cargoOwner->contactData->first())
                                        @php
                                            $cargoContact = $billOfLading->cargoOwner->contactData->first();
                                            $cargoAddress = collect([
                                                $cargoContact->address_line_1,
                                                $cargoContact->address_line_2,
                                                $cargoContact->city,
                                                $cargoContact->state_province,
                                                $cargoContact->postal_code
                                            ])->filter()->implode(', ');
                                        @endphp
                                        <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                                            <p class="text-gray-700 font-medium">Dirección:</p>
                                            <p class="text-gray-600">{{ $cargoAddress ?: 'Dirección no disponible' }}</p>
                                        </div>
                                    @endif
                                    
                                    @if($billOfLading->cargoOwner->country)
                                        <p class="text-sm text-gray-500 mt-1">País: {{ $billOfLading->cargoOwner->country->name }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('company.clients.show', $billOfLading->cargoOwner) }}" 
                                class="text-blue-600 hover:text-blue-900 text-xs">
                                    Ver →
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- RUTAS Y PUERTOS --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Rutas y Puertos
                        </h3>
                        @if(isset($permissions['can_edit']) && $permissions['can_edit'])
                            <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}" 
                               class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                Editar →
                            </a>
                        @endif
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Puerto de Carga --}}
                            @if($billOfLading->loadingPort)
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Puerto de Carga</h4>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <p class="font-semibold text-gray-900">{{ $billOfLading->loadingPort->name }}</p>
                                    <p class="text-sm text-gray-600">Código: {{ $billOfLading->loadingPort->code }}</p>
                                    @if($billOfLading->loadingPort->country)
                                        <p class="text-sm text-gray-600">{{ $billOfLading->loadingPort->country->name }}</p>
                                    @endif
                                    @if($billOfLading->loading_date)
                                        <p class="text-sm text-gray-600 mt-1">
                                            <strong>Fecha:</strong> {{ $billOfLading->loading_date->format('d/m/Y') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @endif

                            {{-- Puerto de Descarga --}}
                            @if($billOfLading->dischargePort)
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Puerto de Descarga</h4>
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <p class="font-semibold text-gray-900">{{ $billOfLading->dischargePort->name }}</p>
                                    <p class="text-sm text-gray-600">Código: {{ $billOfLading->dischargePort->code }}</p>
                                    @if($billOfLading->dischargePort->country)
                                        <p class="text-sm text-gray-600">{{ $billOfLading->dischargePort->country->name }}</p>
                                    @endif
                                    @if($billOfLading->discharge_date)
                                        <p class="text-sm text-gray-600 mt-1">
                                            <strong>Fecha:</strong> {{ $billOfLading->discharge_date->format('d/m/Y') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @endif

                            {{-- Puerto de Transbordo --}}
                            @if($billOfLading->transshipmentPort)
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Puerto de Transbordo</h4>
                                <div class="bg-yellow-50 p-3 rounded-lg">
                                    <p class="font-semibold text-gray-900">{{ $billOfLading->transshipmentPort->name }}</p>
                                    <p class="text-sm text-gray-600">Código: {{ $billOfLading->transshipmentPort->code }}</p>
                                    @if($billOfLading->transshipmentPort->country)
                                        <p class="text-sm text-gray-600">{{ $billOfLading->transshipmentPort->country->name }}</p>
                                    @endif
                                </div>
                            </div>
                            @endif

                            {{-- Destino Final --}}
                            @if($billOfLading->finalDestinationPort)
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Destino Final</h4>
                                <div class="bg-purple-50 p-3 rounded-lg">
                                    <p class="font-semibold text-gray-900">{{ $billOfLading->finalDestinationPort->name }}</p>
                                    <p class="text-sm text-gray-600">Código: {{ $billOfLading->finalDestinationPort->code }}</p>
                                    @if($billOfLading->finalDestinationPort->country)
                                        <p class="text-sm text-gray-600">{{ $billOfLading->finalDestinationPort->country->name }}</p>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                        {{-- Códigos AFIP --}}
                        @if($billOfLading->origin_customs_code || $billOfLading->discharge_customs_code)
                        <div class="md:col-span-2 mt-6">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Códigos AFIP Webservices</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Origen AFIP --}}
                                @if($billOfLading->origin_customs_code && $billOfLading->origin_operative_code)
                                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                    <p class="text-xs font-semibold text-blue-800 uppercase mb-1">📦 Origen</p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Aduana:</span> {{ $billOfLading->origin_customs_code }}
                                    </p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Lugar Operativo:</span> {{ $billOfLading->origin_operative_code }}
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        <code>&lt;codAdu&gt;{{ $billOfLading->origin_customs_code }}&lt;/codAdu&gt; &lt;codLugOper&gt;{{ $billOfLading->origin_operative_code }}&lt;/codLugOper&gt;</code>
                                    </p>
                                </div>
                                @endif

                                {{-- Destino AFIP --}}
                                @if($billOfLading->discharge_customs_code && $billOfLading->operational_discharge_code)
                                <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                                    <p class="text-xs font-semibold text-green-800 uppercase mb-1">📍 Destino</p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Aduana:</span> {{ $billOfLading->discharge_customs_code }}
                                    </p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Lugar Operativo:</span> {{ $billOfLading->operational_discharge_code }}
                                    </p>
                                    <p class="text-xs text-green-600 mt-1">
                                        <code>&lt;codAdu&gt;{{ $billOfLading->discharge_customs_code }}&lt;/codAdu&gt; &lt;codLugOper&gt;{{ $billOfLading->operational_discharge_code }}&lt;/codLugOper&gt;</code>
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                    
                </div>

                {{-- ITEMS DE MERCADERÍA --}}
                @php
                    $itemsCount = $billOfLading->shipmentItems->count();
                    $showFullTable = $itemsCount <= 15;
                    $itemsToShow = $showFullTable ? $billOfLading->shipmentItems : $billOfLading->shipmentItems->take(8);
                @endphp

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Items de Mercadería ({{ $itemsCount }})
                            </h3>
                            <div class="flex space-x-2">
                                <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" 
                                   class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm font-medium">
                                    + Agregar Item
                                </a>
                                @if($itemsCount > 15)
                                    <a href="{{ route('company.bills-of-lading.show', ['bill_of_lading' => $billOfLading->id, 'show_all_items' => '1']) }}#items" 
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm font-medium">
                                        Ver Todos ({{ $itemsCount }})
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Resumen Ejecutivo --}}
                        @if($itemsCount > 0)
                        <div class="grid grid-cols-4 gap-4 mt-4">
                            <div class="bg-blue-50 p-3 rounded">
                                <div class="text-xl font-bold text-blue-600">{{ number_format($itemsCount) }}</div>
                                <div class="text-xs text-gray-600">Total Items</div>
                            </div>
                            <div class="bg-green-50 p-3 rounded">
                                <div class="text-xl font-bold text-green-600">{{ number_format($billOfLading->shipmentItems->sum('gross_weight_kg')) }}</div>
                                <div class="text-xs text-gray-600">Peso (kg)</div>
                            </div>
                            <div class="bg-purple-50 p-3 rounded">
                                <div class="text-xl font-bold text-purple-600">{{ number_format($billOfLading->shipmentItems->sum('package_quantity')) }}</div>
                                <div class="text-xs text-gray-600">Bultos</div>
                            </div>
                            <div class="bg-yellow-50 p-3 rounded">
                                <div class="text-xl font-bold text-yellow-600">${{ number_format($billOfLading->shipmentItems->sum('declared_value'), 2) }}</div>
                                <div class="text-xs text-gray-600">Valor Total</div>
                            </div>
                        </div>
                        @endif

                        {{-- Buscador Simple --}}
                        @if($itemsCount > 15)
                        <div class="mt-4">
                            <div class="flex space-x-3">
                                <div class="flex-1">
                                    <input type="text" 
                                           id="itemSearch" 
                                           placeholder="Buscar items por descripción, referencia o código..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button onclick="clearSearch()" 
                                        class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">
                                    Limpiar
                                </button>
                            </div>
                            <div id="searchResults" class="text-sm text-gray-600 mt-1"></div>
                        </div>
                        @endif
                    </div>

                    {{-- Tabla de Items --}}
                    <div class="overflow-x-auto">
                        @if($itemsCount > 0)
                            <table class="w-full divide-y divide-gray-200" id="itemsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Línea</th>
                                        {{-- <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th> --}}
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peso (kg)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($itemsToShow as $item)
                                        <tr class="hover:bg-gray-50 item-row" data-search="{{ strtolower($item->item_description . ' ' . $item->item_reference . ' ' . ($item->cargoType->name ?? '')) }}">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item->line_number }}</td>
                                            {{-- <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900">{{ $item->item_description }}</div>
                                                @if($item->item_reference)
                                                    <div class="text-sm text-gray-500">Ref: {{ $item->item_reference }}</div>
                                                @endif
                                                @if($item->hs_code)
                                                    <div class="text-xs text-gray-400">HS: {{ $item->hs_code }}</div>
                                                @endif
                                            </td> --}}
                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ $item->cargoType->name ?? 'N/A' }}
                                                @if($item->packagingType)
                                                    <div class="text-xs text-gray-500">{{ $item->packagingType->name }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->package_quantity) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->gross_weight_kg, 2) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900">${{ number_format($item->declared_value, 2) }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('company.shipment-items.show', $item) }}" 
                                                       class="text-blue-600 hover:text-blue-900 text-xs">Ver</a>
                                                    <a href="{{ route('company.shipment-items.edit', $item) }}" 
                                                       class="text-green-600 hover:text-green-900 text-xs">Editar</a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            
                            {{-- Indicador de items adicionales --}}
                            @if(!$showFullTable && $itemsCount > 8)
                                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-center">
                                    <span class="text-sm text-gray-600">
                                        Mostrando 8 de {{ $itemsCount }} items
                                    </span>
                                    <a href="{{ route('company.bills-of-lading.show', ['bill_of_lading' => $billOfLading->id, 'show_all_items' => '1']) }}#items" 
                                    class="ml-2 text-blue-600 hover:text-blue-900 text-sm font-medium">
                                        Ver todos →
                                    </a>
                                </div>
                            @endif
                        @else
                            <div class="px-6 py-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay items registrados</h3>
                                <p class="mt-1 text-sm text-gray-500">Comience agregando el primer item de mercadería.</p>
                                <div class="mt-6">
                                    <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Agregar primer item
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- COLUMNA LATERAL (1/3) --}}
            <div class="space-y-6">
                
                {{-- DATOS TÉCNICOS --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Datos Técnicos
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Paquetes</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ number_format($billOfLading->total_packages ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Peso Bruto</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ number_format($billOfLading->gross_weight_kg ?? 0, 2) }} kg</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Peso Neto</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ number_format($billOfLading->net_weight_kg ?? 0, 2) }} kg</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Volumen</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ number_format($billOfLading->volume_m3 ?? 0, 3) }} m³</dd>
                            </div>
                            @if($billOfLading->primaryCargoType)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Carga Principal</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->primaryCargoType->name }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->commodity_code)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Código NCM</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->commodity_code }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->cargo_marks)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Marcas y Números</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->cargo_marks }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->primaryPackagingType)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Embalaje Principal</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->primaryPackagingType->name }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>


{{-- DATOS AFIP ORIGEN/DESTINO --}}
@if($billOfLading->origin_location || $billOfLading->origin_country_code || $billOfLading->origin_loading_date || $billOfLading->destination_country_code || $billOfLading->discharge_customs_code || $billOfLading->operational_discharge_code)
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Datos AFIP
        </h3>
    </div>
    <div class="px-6 py-4">
        <dl class="space-y-2">
            @if($billOfLading->origin_location)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">Lugar Origen</dt>
                <dd class="text-sm text-gray-900">{{ $billOfLading->origin_location }}</dd>
            </div>
            @endif
            
            @if($billOfLading->origin_country_code)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">País Origen</dt>
                <dd><span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-medium">{{ $billOfLading->origin_country_code }}</span></dd>
            </div>
            @endif
            
            @if($billOfLading->origin_loading_date)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">Fecha Carga Origen</dt>
                <dd class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($billOfLading->origin_loading_date)->format('d/m/Y H:i') }}</dd>
            </div>
            @endif
            
            @if($billOfLading->destination_country_code)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">País Destino</dt>
                <dd><span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-medium">{{ $billOfLading->destination_country_code }}</span></dd>
            </div>
            @endif
            
            @if($billOfLading->discharge_customs_code)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">Aduana Descarga</dt>
                <dd><span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded text-xs font-medium">{{ $billOfLading->discharge_customs_code }}</span></dd>
            </div>
            @endif
            
            @if($billOfLading->operational_discharge_code)
            <div class="flex justify-between items-center">
                <dt class="text-sm font-medium text-gray-500">Lugar Operativo</dt>
                <dd><span class="px-2 py-0.5 bg-orange-100 text-orange-800 rounded text-xs font-medium">{{ $billOfLading->operational_discharge_code }}</span></dd>
            </div>
            @endif
        </dl>
    </div>
</div>
@endif

                {{-- FECHAS IMPORTANTES --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4v8a2 2 0 002 2h4a2 2 0 002-2v-8m-6 0a2 2 0 012-2h4a2 2 0 012 2m-6 0h8"/>
                            </svg>
                            Fechas Importantes
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-3">
                            @if($billOfLading->bill_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha BL</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->bill_date->format('d/m/Y') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->loading_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha Carga</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->loading_date->format('d/m/Y') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->discharge_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha Descarga</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->discharge_date->format('d/m/Y') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->arrival_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha Arribo</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->arrival_date->format('d/m/Y') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->delivery_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha Entrega</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->delivery_date->format('d/m/Y') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- TÉRMINOS COMERCIALES --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Términos Comerciales
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-3">
                            @if($billOfLading->freight_terms)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Términos de Flete</dt>
                                <dd class="text-sm text-gray-900 capitalize">{{ $billOfLading->freight_terms }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->payment_terms)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Términos de Pago</dt>
                                <dd class="text-sm text-gray-900 capitalize">{{ $billOfLading->payment_terms }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->incoterms)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Incoterms</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->incoterms }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->currency_code)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Moneda</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->currency_code }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- CARACTERÍSTICAS ESPECIALES --}}
                @if($billOfLading->contains_dangerous_goods || $billOfLading->requires_refrigeration || $billOfLading->is_perishable || $billOfLading->requires_inspection)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.081 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            Características Especiales
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <div class="space-y-2">
                            @if($billOfLading->contains_dangerous_goods)
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-900">Mercancía Peligrosa</span>
                                    @if($billOfLading->un_number || $billOfLading->imdg_class)
                                        <div class="ml-auto text-xs text-gray-500">
                                            @if($billOfLading->un_number)UN: {{ $billOfLading->un_number }}@endif
                                            @if($billOfLading->imdg_class) | IMDG: {{ $billOfLading->imdg_class }}@endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if($billOfLading->requires_refrigeration)
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-900">Requiere Refrigeración</span>
                                </div>
                            @endif
                            @if($billOfLading->is_perishable)
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-900">Perecedero</span>
                                </div>
                            @endif
                            @if($billOfLading->requires_inspection)
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-900">Requiere Inspección</span>
                                </div>
                            @endif
                            @if($billOfLading->is_consolidated)
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    <span class="text-sm text-gray-900">Consolidado</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- OTROS CONOCIMIENTOS DEL MISMO ENVÍO --}}
@if($shipment && $shipment->billsOfLading && $shipment->billsOfLading->count() > 1)
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Otros Conocimientos ({{ $shipment->billsOfLading->count() - 1 }})
        </h3>
    </div>
    <div class="px-6 py-4">
        @php
            $otherBills = $shipment->billsOfLading->where('id', '!=', $billOfLading->id);
            $showCount = min(3, $otherBills->count());
            $hasMore = $otherBills->count() > 3;
        @endphp
        
        <div class="grid gap-2">
            @foreach($otherBills->take(3) as $otherBL)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex-1">
                        <a href="{{ route('company.bills-of-lading.show', $otherBL) }}" 
                           class="font-medium text-blue-600 hover:text-blue-900">
                            {{ $otherBL->bill_number }}
                        </a>
                        <div class="text-xs text-gray-500 mt-1">
                            Items: {{ $otherBL->shipmentItems->count() }} | 
                            Peso: {{ number_format($otherBL->gross_weight_kg ?? 0) }} kg
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full {{ $statusColors[$otherBL->status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ $statusLabels[$otherBL->status] ?? $otherBL->status }}
                    </span>
                </div>
            @endforeach
        </div>
        
        @if($hasMore)
            <div class="mt-3 text-center">
                <a href="{{ route('company.bills-of-lading.index', ['shipment_id' => $shipment->id]) }}" 
                   class="text-sm text-blue-600 hover:text-blue-900 font-medium">
                    Ver todos ({{ $otherBills->count() }}) →
                </a>
            </div>
        @endif
    </div>
</div>
@endif

                {{-- AUDITORÍA --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Auditoría
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="space-y-2 text-sm">
                            @if($billOfLading->createdByUser)
                            <div>
                                <dt class="text-gray-500">Creado por:</dt>
                                <dd class="text-gray-900">{{ $billOfLading->createdByUser->name }}</dd>
                                <dd class="text-xs text-gray-500">{{ $billOfLading->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->lastUpdatedByUser)
                            <div>
                                <dt class="text-gray-500">Última actualización:</dt>
                                <dd class="text-gray-900">{{ $billOfLading->lastUpdatedByUser->name }}</dd>
                                <dd class="text-xs text-gray-500">{{ $billOfLading->updated_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif
                            @if($billOfLading->verifiedByUser)
                            <div>
                                <dt class="text-gray-500">Verificado por:</dt>
                                <dd class="text-gray-900">{{ $billOfLading->verifiedByUser->name }}</dd>
                                <dd class="text-xs text-gray-500">{{ $billOfLading->verified_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- JavaScript para búsqueda de items --}}
@if($itemsCount > 15)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('itemSearch');
    const searchResults = document.getElementById('searchResults');
    const itemRows = document.querySelectorAll('.item-row');
    
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;
        
        itemRows.forEach(row => {
            const searchData = row.getAttribute('data-search');
            const isVisible = searchTerm === '' || searchData.includes(searchTerm);
            
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });
        
        // Actualizar contador
        if (searchTerm === '') {
            searchResults.textContent = '';
        } else {
            searchResults.textContent = `Mostrando ${visibleCount} de {{ $itemsToShow->count() }} items`;
        }
    }
    
    searchInput.addEventListener('input', performSearch);
    
    window.clearSearch = function() {
        searchInput.value = '';
        performSearch();
    };
});
</script>
@endif

</x-app-layout>