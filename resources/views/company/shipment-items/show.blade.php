<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalle del Item') }} #{{ $shipmentItem->line_number }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Shipment: <a href="{{ route('company.shipments.show', $shipmentItem->shipment) }}" class="font-medium text-blue-600 hover:text-blue-500">{{ $shipmentItem->shipment->shipment_number }}</a> - 
                    Viaje: <span class="font-medium">{{ $shipmentItem->shipment->voyage->voyage_number }}</span>
                    @if($shipmentItem->item_reference)
                        - Ref: <span class="font-medium">{{ $shipmentItem->item_reference }}</span>
                    @endif
                </p>
            </div>
            <div class="flex space-x-3">
                @if($shipmentItem->shipment->status === 'planning' || $shipmentItem->shipment->status === 'loading')
                    <a href="{{ route('company.shipment-items.edit', $shipmentItem) }}" 
                       class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                        Editar
                    </a>
                @endif
                <a href="{{ route('company.shipments.show', $shipmentItem->shipment) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Volver al Shipment
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Estado y Alertas --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($shipmentItem->status)
                                        @case('draft')
                                            bg-gray-100 text-gray-800
                                            @break
                                        @case('validated')
                                            bg-blue-100 text-blue-800
                                            @break
                                        @case('submitted')
                                            bg-yellow-100 text-yellow-800
                                            @break
                                        @case('accepted')
                                            bg-green-100 text-green-800
                                            @break
                                        @case('rejected')
                                            bg-red-100 text-red-800
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800
                                    @endswitch
                                ">
                                    Estado: {{ ucfirst($shipmentItem->status) }}
                                </span>
                            </div>
                            @if($shipmentItem->has_discrepancies)
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Con Discrepancias
                                    </span>
                                </div>
                            @endif
                            @if($shipmentItem->requires_review)
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                        Requiere Revisión
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div class="text-sm text-gray-500">
                            Creado: {{ $shipmentItem->created_date->format('d/m/Y H:i') }}
                        </div>
                    </div>
                    @if($shipmentItem->discrepancy_notes)
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-700">
                                <strong>Notas de Discrepancia:</strong> {{ $shipmentItem->discrepancy_notes }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Columna Principal --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Información Básica --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4-8-4m16 0v10l-8 4-8-4V7"/>
                                </svg>
                                Información Básica
                            </h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Número de Línea</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">#{{ $shipmentItem->line_number }}</dd>
                                </div>
                                @if($shipmentItem->item_reference)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Referencia del Item</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->item_reference }}</dd>
                                    </div>
                                @endif
                                @if($shipmentItem->client)
                                    <div class="sm:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">Cliente Dueño de la Mercadería</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                                {{ $shipmentItem->client->legal_name }} ({{ $shipmentItem->client->tax_id }})
                                            </div>
                                        </dd>
                                    </div>
                                @endif
                                @if($shipmentItem->lot_number)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Número de Lote</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->lot_number }}</dd>
                                    </div>
                                @endif
                                @if($shipmentItem->serial_number)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Número de Serie</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->serial_number }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Descripción de la Mercadería --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Descripción de la Mercadería
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Descripción del Item</dt>
                                    <dd class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">
                                        {{ $shipmentItem->item_description }}
                                    </dd>
                                </div>
                                @if($shipmentItem->cargo_marks)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Marcas de la Mercadería</dt>
                                        <dd class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">
                                            {{ $shipmentItem->cargo_marks }}
                                        </dd>
                                    </div>
                                @endif
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                    @if($shipmentItem->commodity_code)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Código NCM/HS</dt>
                                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $shipmentItem->commodity_code }}</dd>
                                        </div>
                                    @endif
                                    @if($shipmentItem->commodity_description)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Descripción del Commodity</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->commodity_description }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>

                    {{-- Información Comercial --}}
                    @if($shipmentItem->brand || $shipmentItem->model || $shipmentItem->manufacturer || $shipmentItem->country_of_origin)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M8 11v6a2 2 0 002 2h4a2 2 0 002-2v-6m-6 0h4"/>
                                    </svg>
                                    Información Comercial
                                </h3>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                    @if($shipmentItem->brand)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Marca</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->brand }}</dd>
                                        </div>
                                    @endif
                                    @if($shipmentItem->model)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Modelo</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->model }}</dd>
                                        </div>
                                    @endif
                                    @if($shipmentItem->manufacturer)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Fabricante</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->manufacturer }}</dd>
                                        </div>
                                    @endif
                                    @if($shipmentItem->country_of_origin)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">País de Origen</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                @switch($shipmentItem->country_of_origin)
                                                    @case('AR') 🇦🇷 Argentina @break
                                                    @case('PY') 🇵🇾 Paraguay @break
                                                    @case('BR') 🇧🇷 Brasil @break
                                                    @case('UY') 🇺🇾 Uruguay @break
                                                    @case('CL') 🇨🇱 Chile @break
                                                    @case('CN') 🇨🇳 China @break
                                                    @case('US') 🇺🇸 Estados Unidos @break
                                                    @case('DE') 🇩🇪 Alemania @break
                                                    @case('JP') 🇯🇵 Japón @break
                                                    @default {{ $shipmentItem->country_of_origin }}
                                                @endswitch
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    @endif

                </div>

                {{-- Columna Lateral --}}
                <div class="space-y-6">

                    {{-- Clasificación --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Clasificación
                            </h3>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Carga</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $shipmentItem->cargoType->name }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Embalaje</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $shipmentItem->packagingType->name }}
                                        </span>
                                    </dd>
                                </div>
                                @if($shipmentItem->package_type_description)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Descripción del Embalaje</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $shipmentItem->package_type_description }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Cantidades y Medidas --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Cantidades y Medidas
                            </h3>
                            <dl class="space-y-4">
                                <div class="bg-purple-50 p-3 rounded-md">
                                    <dt class="text-sm font-medium text-purple-700">Cantidad de Bultos</dt>
                                    <dd class="mt-1 text-lg font-bold text-purple-900">{{ number_format($shipmentItem->package_quantity) }} {{ $shipmentItem->unit_of_measure }}</dd>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Peso Bruto</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ number_format($shipmentItem->gross_weight_kg, 2) }} kg</dd>
                                    </div>
                                    @if($shipmentItem->net_weight_kg)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Peso Neto</dt>
                                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ number_format($shipmentItem->net_weight_kg, 2) }} kg</dd>
                                        </div>
                                    @endif
                                </div>
                                @if($shipmentItem->volume_m3)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Volumen</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ number_format($shipmentItem->volume_m3, 3) }} m³</dd>
                                    </div>
                                @endif
                                @if($shipmentItem->declared_value)
                                    <div class="bg-gray-50 p-3 rounded-md">
                                        <dt class="text-sm font-medium text-gray-700">Valor Declarado</dt>
                                        <dd class="mt-1 text-lg font-bold text-gray-900">
                                            {{ $shipmentItem->currency_code }} {{ number_format($shipmentItem->declared_value, 2) }}
                                        </dd>
                                    </div>
                                @endif
                                @if($shipmentItem->units_per_package)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Unidades por Bulto</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ number_format($shipmentItem->units_per_package) }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Características Especiales --}}
                    @if($shipmentItem->is_dangerous_goods || $shipmentItem->is_perishable || $shipmentItem->is_fragile || $shipmentItem->requires_refrigeration)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    Características Especiales
                                </h3>
                                <div class="space-y-3">
                                    @if($shipmentItem->is_dangerous_goods)
                                        <div class="flex items-start p-3 bg-red-50 border border-red-200 rounded-md">
                                            <svg class="w-5 h-5 mr-2 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-red-800">Mercancías Peligrosas</p>
                                                @if($shipmentItem->un_number)
                                                    <p class="text-xs text-red-700">UN: {{ $shipmentItem->un_number }}</p>
                                                @endif
                                                @if($shipmentItem->imdg_class)
                                                    <p class="text-xs text-red-700">Clase IMDG: {{ $shipmentItem->imdg_class }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if($shipmentItem->is_perishable)
                                        <div class="flex items-center p-2 bg-yellow-50 border border-yellow-200 rounded-md">
                                            <svg class="w-4 h-4 mr-2 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm font-medium text-yellow-800">Productos Perecederos</span>
                                        </div>
                                    @endif
                                    @if($shipmentItem->is_fragile)
                                        <div class="flex items-center p-2 bg-orange-50 border border-orange-200 rounded-md">
                                            <svg class="w-4 h-4 mr-2 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm font-medium text-orange-800">Mercadería Frágil</span>
                                        </div>
                                    @endif
                                    @if($shipmentItem->requires_refrigeration)
                                        <div class="flex items-start p-3 bg-blue-50 border border-blue-200 rounded-md">
                                            <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm5.707 8.707a1 1 0 11-1.414-1.414L10 7.586l1.707 1.707a1 1 0 01-1.414 1.414L10 10.414l-.293.293z" clip-rule="evenodd"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-blue-800">Requiere Refrigeración</p>
                                                @if($shipmentItem->temperature_min || $shipmentItem->temperature_max)
                                                    <p class="text-xs text-blue-700">
                                                        Temperatura: 
                                                        @if($shipmentItem->temperature_min && $shipmentItem->temperature_max)
                                                            {{ $shipmentItem->temperature_min }}°C a {{ $shipmentItem->temperature_max }}°C
                                                        @elseif($shipmentItem->temperature_min)
                                                            Mín: {{ $shipmentItem->temperature_min }}°C
                                                        @elseif($shipmentItem->temperature_max)
                                                            Máx: {{ $shipmentItem->temperature_max }}°C
                                                        @endif
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Regulaciones y Permisos --}}
                    @if($shipmentItem->requires_permit || $shipmentItem->requires_inspection)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Regulaciones y Permisos
                                </h3>
                                <div class="space-y-3">
                                    @if($shipmentItem->requires_permit)
                                        <div class="flex items-start p-3 bg-gray-50 border border-gray-200 rounded-md">
                                            <svg class="w-5 h-5 mr-2 text-gray-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 2C5.589 2 2 5.589 2 10s3.589 8 8 8 8-3.589 8-8-3.589-8-8-8zm3.707 5.293a1 1 0 00-1.414-1.414L9 9.172 7.707 7.879a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">Requiere Permiso Especial</p>
                                                @if($shipmentItem->permit_number)
                                                    <p class="text-xs text-gray-600">Número: {{ $shipmentItem->permit_number }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if($shipmentItem->requires_inspection)
                                        <div class="flex items-start p-3 bg-gray-50 border border-gray-200 rounded-md">
                                            <svg class="w-5 h-5 mr-2 text-gray-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">Requiere Inspección</p>
                                                @if($shipmentItem->inspection_type)
                                                    <p class="text-xs text-gray-600">
                                                        Tipo: 
                                                        @switch($shipmentItem->inspection_type)
                                                            @case('customs') Aduana @break
                                                            @case('quality') Calidad @break
                                                            @case('sanitary') Sanitaria @break
                                                            @case('security') Seguridad @break
                                                            @case('environmental') Ambiental @break
                                                            @default {{ ucfirst($shipmentItem->inspection_type) }}
                                                        @endswitch
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Auditoría y Metadatos --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Información de Auditoría
                    </h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Creado</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $shipmentItem->created_date->format('d/m/Y H:i') }}
                                @if($shipmentItem->createdByUser)
                                    <br><span class="text-xs text-gray-500">por {{ $shipmentItem->createdByUser->name }}</span>
                                @endif
                            </dd>
                        </div>
                        @if($shipmentItem->last_updated_date && $shipmentItem->last_updated_date != $shipmentItem->created_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Última Actualización</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $shipmentItem->last_updated_date->format('d/m/Y H:i') }}
                                    @if($shipmentItem->lastUpdatedByUser)
                                        <br><span class="text-xs text-gray-500">por {{ $shipmentItem->lastUpdatedByUser->name }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado del Shipment</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @switch($shipmentItem->shipment->status)
                                        @case('planning')
                                            bg-gray-100 text-gray-800
                                            @break
                                        @case('loading')
                                            bg-blue-100 text-blue-800
                                            @break
                                        @case('loaded')
                                            bg-yellow-100 text-yellow-800
                                            @break
                                        @case('in_transit')
                                            bg-purple-100 text-purple-800
                                            @break
                                        @case('arrived')
                                            bg-indigo-100 text-indigo-800
                                            @break
                                        @case('discharging')
                                            bg-orange-100 text-orange-800
                                            @break
                                        @case('completed')
                                            bg-green-100 text-green-800
                                            @break
                                        @case('delayed')
                                            bg-red-100 text-red-800
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800
                                    @endswitch
                                ">
                                    {{ ucfirst($shipmentItem->shipment->status) }}
                                </span>
                            </dd>
                        </div>
                        @if($shipmentItem->webservice_item_id)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">ID Webservice</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $shipmentItem->webservice_item_id }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Acciones Adicionales --}}
            @if($shipmentItem->shipment->status === 'planning' || $shipmentItem->shipment->status === 'loading')
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Acciones</h3>
                        <div class="flex space-x-3">
                            <a href="{{ route('company.shipment-items.edit', $shipmentItem) }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Editar Item
                            </a>
                            <form method="POST" action="{{ route('company.shipment-items.duplicate', $shipmentItem) }}" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    Duplicar Item
                                </button>
                            </form>
                            @if(auth()->user()->hasRole('company-admin'))
                                <form method="POST" action="{{ route('company.shipment-items.destroy', $shipmentItem) }}" 
                                      class="inline" 
                                      onsubmit="return confirm('¿Está seguro de eliminar este item? Esta acción no se puede deshacer.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar Item
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>