<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Item') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Shipment: <span class="font-medium">{{ $shipmentItem->shipment->shipment_number }}</span> - 
                    Viaje: <span class="font-medium">{{ $shipmentItem->shipment->voyage->voyage_number }}</span> - 
                    Línea: <span class="font-medium">#{{ $shipmentItem->line_number }}</span>
                </p>
            </div>
            <a href="{{ route('company.shipment-items.show', $shipmentItem) }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Volver al Item
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Mensajes de Error --}}
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Hay errores en el formulario
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('company.shipment-items.update', $shipmentItem) }}" class="space-y-6" id="itemForm">
                @csrf
                @method('PUT')

                {{-- SECCIÓN: Información Básica del Item --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4-8-4m16 0v10l-8 4-8-4V7"/>
                                </svg>
                                Identificación del Item
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información básica para identificar el item de mercadería.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Número de Línea --}}
                            <div>
                                <label for="line_number" class="block text-sm font-medium text-gray-700">
                                    Número de Línea <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="line_number" 
                                       id="line_number" 
                                       value="{{ old('line_number', $shipmentItem->line_number) }}"
                                       min="1"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('line_number') border-red-300 @enderror">
                                @error('line_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Referencia del Item --}}
                            <div>
                                <label for="item_reference" class="block text-sm font-medium text-gray-700">
                                    Referencia del Item
                                </label>
                                <input type="text" 
                                       name="item_reference" 
                                       id="item_reference" 
                                       value="{{ old('item_reference', $shipmentItem->item_reference) }}"
                                       maxlength="100"
                                       placeholder="Ej: REF-001"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('item_reference') border-red-300 @enderror">
                                @error('item_reference')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-6">
                            {{-- Número de Lote --}}
                            <div>
                                <label for="lot_number" class="block text-sm font-medium text-gray-700">
                                    Número de Lote
                                </label>
                                <input type="text" 
                                       name="lot_number" 
                                       id="lot_number" 
                                       value="{{ old('lot_number', $shipmentItem->lot_number) }}"
                                       maxlength="50"
                                       placeholder="Ej: LOT-2025-001"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('lot_number') border-red-300 @enderror">
                                @error('lot_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Número de Serie --}}
                            <div>
                                <label for="serial_number" class="block text-sm font-medium text-gray-700">
                                    Número de Serie
                                </label>
                                <input type="text" 
                                       name="serial_number" 
                                       id="serial_number" 
                                       value="{{ old('serial_number', $shipmentItem->serial_number) }}"
                                       maxlength="100"
                                       placeholder="Ej: SN-ABC123"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('serial_number') border-red-300 @enderror">
                                @error('serial_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Clasificación de Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Clasificación de Carga
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información sobre el tipo de carga y embalaje.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Tipo de Carga --}}
                            <div>
                                <label for="cargo_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Carga <span class="text-red-500">*</span>
                                </label>
                                <select name="cargo_type_id" 
                                        id="cargo_type_id" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_type_id') border-red-300 @enderror">
                                    <option value="">Seleccione tipo de carga</option>
                                    @foreach($cargoTypes as $cargoType)
                                        <option value="{{ $cargoType->id }}" {{ old('cargo_type_id', $shipmentItem->cargo_type_id) == $cargoType->id ? 'selected' : '' }}>
                                            {{ $cargoType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cargo_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tipo de Embalaje --}}
                            <div>
                                <label for="packaging_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Embalaje <span class="text-red-500">*</span>
                                </label>
                                <select name="packaging_type_id" 
                                        id="packaging_type_id" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('packaging_type_id') border-red-300 @enderror">
                                    <option value="">Seleccione tipo de embalaje</option>
                                    @foreach($packagingTypes as $packagingType)
                                        <option value="{{ $packagingType->id }}" {{ old('packaging_type_id', $shipmentItem->packaging_type_id) == $packagingType->id ? 'selected' : '' }}>
                                            {{ $packagingType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('packaging_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Contenedores --}}
<div class="bg-white overflow-hidden shadow rounded-lg" id="containers-section" style="display: none;">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">
            Información de Contenedores
        </h3>
        
        <div id="containers-list">
    {{-- Contenedores existentes desde PHP --}}
    @if($containerData && count($containerData) > 0)
        @foreach($containerData as $index => $container)
            <div class="container-item mb-4 p-4 border border-gray-200 rounded-lg bg-gray-50" data-index="{{ $index }}">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-md font-medium text-gray-900">Contenedor {{ $index + 1 }}</h4>
                    @if(count($containerData) > 1)
                        <button type="button" onclick="removeContainer({{ $index }})" class="text-red-600 hover:text-red-800">
                            Eliminar
                        </button>
                    @endif
                </div>

                {{-- ID del contenedor (hidden para contenedores existentes) --}}
                <input type="hidden" name="containers[{{ $index }}][id]" value="{{ $container['id'] ?? '' }}">

                {{-- Información básica del contenedor --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- Número de Contenedor --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Número de Contenedor <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="containers[{{ $index }}][container_number]" 
                               value="{{ old('containers.'.$index.'.container_number', $container['container_number']) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="MSCU1234567"
                               required>
                    </div>

                    {{-- Tipo de Contenedor --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Tipo de Contenedor <span class="text-red-500">*</span>
                        </label>
                        <select name="containers[{{ $index }}][container_type_id]" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                required>
                            <option value="">Seleccionar tipo</option>
                            @foreach($containerTypes as $containerType)
                                <option value="{{ $containerType->id }}" 
                                    {{ old('containers.'.$index.'.container_type_id', $container['container_type_id']) == $containerType->id ? 'selected' : '' }}>
                                    {{ $containerType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Número de Precinto --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Número de Precinto
                        </label>
                        <input type="text" 
                               name="containers[{{ $index }}][seal_number]" 
                               value="{{ old('containers.'.$index.'.seal_number', $container['seal_number']) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="SL123456">
                    </div>

                    {{-- Peso de Tara --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Peso de Tara (kg)
                        </label>
                        <input type="number" 
                               name="containers[{{ $index }}][tare_weight]" 
                               value="{{ old('containers.'.$index.'.tare_weight', $container['tare_weight']) }}"
                               step="0.01"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="2300">
                    </div>
                </div>

                {{-- Distribución de la carga en este contenedor --}}
                <div class="pt-4 border-t border-gray-200">
                    <h5 class="text-sm font-medium text-gray-700 mb-3">Distribución de Carga en este Contenedor</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Cantidad de Bultos --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Cantidad de Bultos <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   name="containers[{{ $index }}][package_quantity]" 
                                   value="{{ old('containers.'.$index.'.package_quantity', $container['package_quantity']) }}"
                                   min="1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-package-qty"
                                   placeholder="20"
                                   required>
                        </div>

                        {{-- Peso Bruto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Peso Bruto (kg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   name="containers[{{ $index }}][gross_weight_kg]" 
                                   value="{{ old('containers.'.$index.'.gross_weight_kg', $container['gross_weight_kg']) }}"
                                   step="0.01"
                                   min="0.01"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-gross-weight"
                                   placeholder="1000.00"
                                   required>
                        </div>

                        {{-- Peso Neto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Peso Neto (kg)
                            </label>
                            <input type="number" 
                                   name="containers[{{ $index }}][net_weight_kg]" 
                                   value="{{ old('containers.'.$index.'.net_weight_kg', $container['net_weight_kg']) }}"
                                   step="0.01"
                                   min="0"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-net-weight"
                                   placeholder="990.00">
                        </div>

                        {{-- Volumen --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Volumen (m³)
                            </label>
                            <input type="number" 
                                   name="containers[{{ $index }}][volume_m3]" 
                                   value="{{ old('containers.'.$index.'.volume_m3', $container['volume_m3']) }}"
                                   step="0.001"
                                   min="0"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-volume"
                                   placeholder="1.250">
                        </div>
                    </div>

                    {{-- Campos adicionales --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        {{-- Secuencia de Carga --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Secuencia de Carga
                            </label>
                            <input type="text" 
                                   name="containers[{{ $index }}][loading_sequence]" 
                                   value="{{ old('containers.'.$index.'.loading_sequence', $container['loading_sequence']) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="A1, B2, etc.">
                        </div>

                       
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
        
        <button type="button" onclick="addContainer()" class="btn btn-primary">
            Agregar Contenedor
        </button>
    </div>
</div>

                {{-- SECCIÓN: Cantidades y Medidas --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Cantidades y Medidas
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información sobre cantidades, pesos y volúmenes.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- Cantidad de Bultos --}}
                            <div>
                                <label for="package_quantity" class="block text-sm font-medium text-gray-700">
                                    Cantidad de Bultos <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="package_quantity" 
                                       id="package_quantity" 
                                       value="{{ old('package_quantity', $shipmentItem->package_quantity) }}"
                                       min="1"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('package_quantity') border-red-300 @enderror">
                                @error('package_quantity')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Bruto (kg) --}}
                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Bruto (kg) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="gross_weight_kg" 
                                       id="gross_weight_kg" 
                                       value="{{ old('gross_weight_kg', $shipmentItem->gross_weight_kg) }}"
                                       min="0.01"
                                       step="0.01"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('gross_weight_kg') border-red-300 @enderror">
                                @error('gross_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Neto (kg) --}}
                            <div>
                                <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Neto (kg)
                                </label>
                                <input type="number" 
                                       name="net_weight_kg" 
                                       id="net_weight_kg" 
                                       value="{{ old('net_weight_kg', $shipmentItem->net_weight_kg) }}"
                                       min="0"
                                       step="0.01"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('net_weight_kg') border-red-300 @enderror">
                                @error('net_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Volumen (m³) --}}
                            <div>
                                <label for="volume_m3" class="block text-sm font-medium text-gray-700">
                                    Volumen (m³)
                                </label>
                                <input type="number" 
                                       name="volume_m3" 
                                       id="volume_m3" 
                                       value="{{ old('volume_m3', $shipmentItem->volume_m3) }}"
                                       min="0"
                                       step="0.001"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_m3') border-red-300 @enderror">
                                @error('volume_m3')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mt-6">
                            {{-- Valor Declarado --}}
                            <div>
                                <label for="declared_value" class="block text-sm font-medium text-gray-700">
                                    Valor Declarado
                                </label>
                                <input type="number" 
                                       name="declared_value" 
                                       id="declared_value" 
                                       value="{{ old('declared_value', $shipmentItem->declared_value) }}"
                                       min="0"
                                       step="0.01"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('declared_value') border-red-300 @enderror">
                                @error('declared_value')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Moneda --}}
                            <div>
                                <label for="currency_code" class="block text-sm font-medium text-gray-700">
                                    Moneda <span class="text-red-500">*</span>
                                </label>
                                <select name="currency_code" 
                                        id="currency_code" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('currency_code') border-red-300 @enderror">
                                    <option value="USD" {{ old('currency_code', $shipmentItem->currency_code) == 'USD' ? 'selected' : '' }}>USD - Dólar Estadounidense</option>
                                    <option value="ARS" {{ old('currency_code', $shipmentItem->currency_code) == 'ARS' ? 'selected' : '' }}>ARS - Peso Argentino</option>
                                    <option value="PYG" {{ old('currency_code', $shipmentItem->currency_code) == 'PYG' ? 'selected' : '' }}>PYG - Guaraní Paraguayo</option>
                                    <option value="EUR" {{ old('currency_code', $shipmentItem->currency_code) == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                </select>
                                @error('currency_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Unidad de Medida --}}
                            <div>
                                <label for="unit_of_measure" class="block text-sm font-medium text-gray-700">
                                    Unidad de Medida <span class="text-red-500">*</span>
                                </label>
                                <select name="unit_of_measure" 
                                        id="unit_of_measure" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('unit_of_measure') border-red-300 @enderror">
                                    <option value="PCS" {{ old('unit_of_measure', $shipmentItem->unit_of_measure) == 'PCS' ? 'selected' : '' }}>PCS - Piezas</option>
                                    <option value="KG" {{ old('unit_of_measure', $shipmentItem->unit_of_measure) == 'KG' ? 'selected' : '' }}>KG - Kilogramos</option>
                                    <option value="LT" {{ old('unit_of_measure', $shipmentItem->unit_of_measure) == 'LT' ? 'selected' : '' }}>LT - Litros</option>
                                    <option value="M3" {{ old('unit_of_measure', $shipmentItem->unit_of_measure) == 'M3' ? 'selected' : '' }}>M3 - Metros Cúbicos</option>
                                    <option value="BOX" {{ old('unit_of_measure', $shipmentItem->unit_of_measure) == 'BOX' ? 'selected' : '' }}>BOX - Cajas</option>
                                </select>
                                @error('unit_of_measure')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Descripción de la Mercadería --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Descripción de la Mercadería
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información descriptiva del item.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6">
                            {{-- Descripción del Item --}}
                            <div>
                                <label for="item_description" class="block text-sm font-medium text-gray-700">
                                    Descripción del Item <span class="text-red-500">*</span>
                                </label>
                                <textarea name="item_description" 
                                          id="item_description" 
                                          rows="3"
                                          required
                                          maxlength="1000"
                                          placeholder="Descripción detallada del item de mercadería..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('item_description') border-red-300 @enderror">{{ old('item_description', $shipmentItem->item_description) }}</textarea>
                                @error('item_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {{-- Marcas de la Mercadería --}}
                                <div>
                                    <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                                        Marcas de la Mercadería
                                    </label>
                                    <textarea name="cargo_marks" 
                                              id="cargo_marks" 
                                              rows="2"
                                              maxlength="500"
                                              placeholder="Marcas, números, símbolos..."
                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_marks') border-red-300 @enderror">{{ old('cargo_marks', $shipmentItem->cargo_marks) }}</textarea>
                                    @error('cargo_marks')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Código NCM/HS --}}
                                <div>
                                    <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                                        Código NCM/HS
                                    </label>
                                    <input type="text" 
                                           name="commodity_code" 
                                           id="commodity_code" 
                                           value="{{ old('commodity_code', $shipmentItem->commodity_code) }}"
                                           maxlength="20"
                                           placeholder="Ej: 12345678"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_code') border-red-300 @enderror">
                                    @error('commodity_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Descripción del Commodity --}}
                            <div>
                                <label for="commodity_description" class="block text-sm font-medium text-gray-700">
                                    Descripción del Commodity
                                </label>
                                <input type="text" 
                                       name="commodity_description" 
                                       id="commodity_description" 
                                       value="{{ old('commodity_description', $shipmentItem->commodity_description) }}"
                                       maxlength="255"
                                       placeholder="Descripción del tipo de mercadería según clasificación"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_description') border-red-300 @enderror">
                                @error('commodity_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Información Comercial --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M8 11v6a2 2 0 002 2h4a2 2 0 002-2v-6m-6 0h4"/>
                                </svg>
                                Información Comercial (Opcional)
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información adicional sobre marca, modelo y fabricante.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- Marca --}}
                            <div>
                                <label for="brand" class="block text-sm font-medium text-gray-700">
                                    Marca
                                </label>
                                <input type="text" 
                                       name="brand" 
                                       id="brand" 
                                       value="{{ old('brand', $shipmentItem->brand) }}"
                                       maxlength="100"
                                       placeholder="Ej: Samsung"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('brand') border-red-300 @enderror">
                                @error('brand')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Modelo --}}
                            <div>
                                <label for="model" class="block text-sm font-medium text-gray-700">
                                    Modelo
                                </label>
                                <input type="text" 
                                       name="model" 
                                       id="model" 
                                       value="{{ old('model', $shipmentItem->model) }}"
                                       maxlength="100"
                                       placeholder="Ej: Galaxy S24"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('model') border-red-300 @enderror">
                                @error('model')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Fabricante --}}
                            <div>
                                <label for="manufacturer" class="block text-sm font-medium text-gray-700">
                                    Fabricante
                                </label>
                                <input type="text" 
                                       name="manufacturer" 
                                       id="manufacturer" 
                                       value="{{ old('manufacturer', $shipmentItem->manufacturer) }}"
                                       maxlength="200"
                                       placeholder="Ej: Samsung Electronics"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('manufacturer') border-red-300 @enderror">
                                @error('manufacturer')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- País de Origen --}}
                            <div>
                                <label for="country_of_origin" class="block text-sm font-medium text-gray-700">
                                    País de Origen
                                </label>
                                <select name="country_of_origin" 
                                        id="country_of_origin" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('country_of_origin') border-red-300 @enderror">
                                    <option value="">Seleccione país</option>
                                    <option value="AR" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'AR' ? 'selected' : '' }}>Argentina</option>
                                    <option value="PY" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'PY' ? 'selected' : '' }}>Paraguay</option>
                                    <option value="BR" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'BR' ? 'selected' : '' }}>Brasil</option>
                                    <option value="UY" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'UY' ? 'selected' : '' }}>Uruguay</option>
                                    <option value="CL" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'CL' ? 'selected' : '' }}>Chile</option>
                                    <option value="CN" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'CN' ? 'selected' : '' }}>China</option>
                                    <option value="US" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'US' ? 'selected' : '' }}>Estados Unidos</option>
                                    <option value="DE" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'DE' ? 'selected' : '' }}>Alemania</option>
                                    <option value="JP" {{ old('country_of_origin', $shipmentItem->country_of_origin) == 'JP' ? 'selected' : '' }}>Japón</option>
                                </select>
                                @error('country_of_origin')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Características Especiales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                Características Especiales
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Marque si el item tiene características especiales que requieren manejo particular.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Mercancías Peligrosas --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center">
                                    <input type="hidden" name="is_dangerous_goods" value="0">
                                    <input type="checkbox" 
                                           name="is_dangerous_goods" 
                                           id="is_dangerous_goods" 
                                           value="1"
                                           {{ old('is_dangerous_goods', $shipmentItem->is_dangerous_goods) ? 'checked' : '' }}
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                                    <label for="is_dangerous_goods" class="ml-2 block text-sm font-medium text-gray-700">
                                        Mercancías Peligrosas
                                    </label>
                                </div>
                                <div id="dangerous_goods_fields" class="mt-4 space-y-3" style="display: {{ old('is_dangerous_goods', $shipmentItem->is_dangerous_goods) ? 'block' : 'none' }};">
                                    <div>
                                        <label for="un_number" class="block text-sm font-medium text-gray-700">
                                            Número UN
                                        </label>
                                        <input type="text" 
                                               name="un_number" 
                                               id="un_number" 
                                               value="{{ old('un_number', $shipmentItem->un_number) }}"
                                               maxlength="10"
                                               placeholder="Ej: UN1234"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 text-sm">
                                    </div>
                                    <div>
                                        <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                            Clase IMDG
                                        </label>
                                        <input type="text" 
                                               name="imdg_class" 
                                               id="imdg_class" 
                                               value="{{ old('imdg_class', $shipmentItem->imdg_class) }}"
                                               maxlength="10"
                                               placeholder="Ej: 3.1"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- Productos Perecederos --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center">
                                    <input type="hidden" name="is_perishable" value="0">
                                    <input type="checkbox" 
                                           name="is_perishable" 
                                           id="is_perishable" 
                                           value="1"
                                           {{ old('is_perishable', $shipmentItem->is_perishable) ? 'checked' : '' }}
                                           class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded">
                                    <label for="is_perishable" class="ml-2 block text-sm font-medium text-gray-700">
                                        Productos Perecederos
                                    </label>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    Productos que se deterioran con el tiempo
                                </p>
                            </div>

                            {{-- Mercadería Frágil --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center">
                                    <input type="hidden" name="is_fragile" value="0">
                                    <input type="checkbox" 
                                           name="is_fragile" 
                                           id="is_fragile" 
                                           value="1"
                                           {{ old('is_fragile', $shipmentItem->is_fragile) ? 'checked' : '' }}
                                           class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                                    <label for="is_fragile" class="ml-2 block text-sm font-medium text-gray-700">
                                        Mercadería Frágil
                                    </label>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    Requiere manejo cuidadoso
                                </p>
                            </div>
                        </div>

                        {{-- Refrigeración --}}
                        <div class="mt-6 border rounded-lg p-4">
                            <div class="flex items-center mb-4">
                                <input type="hidden" name="requires_refrigeration" value="0">
                                <input type="checkbox" 
                                       name="requires_refrigeration" 
                                       id="requires_refrigeration" 
                                       value="1"
                                       {{ old('requires_refrigeration', $shipmentItem->requires_refrigeration) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="requires_refrigeration" class="ml-2 block text-sm font-medium text-gray-700">
                                    Requiere Refrigeración
                                </label>
                            </div>
                            <div id="refrigeration_fields" class="grid grid-cols-1 gap-4 sm:grid-cols-2" style="display: {{ old('requires_refrigeration', $shipmentItem->requires_refrigeration) ? 'grid' : 'none' }};">
                                <div>
                                    <label for="temperature_min" class="block text-sm font-medium text-gray-700">
                                        Temperatura Mínima (°C)
                                    </label>
                                    <input type="number" 
                                           name="temperature_min" 
                                           id="temperature_min" 
                                           value="{{ old('temperature_min', $shipmentItem->temperature_min) }}"
                                           step="0.1"
                                           placeholder="Ej: -18"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div>
                                    <label for="temperature_max" class="block text-sm font-medium text-gray-700">
                                        Temperatura Máxima (°C)
                                    </label>
                                    <input type="number" 
                                           name="temperature_max" 
                                           id="temperature_max" 
                                           value="{{ old('temperature_max', $shipmentItem->temperature_max) }}"
                                           step="0.1"
                                           placeholder="Ej: -15"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Regulaciones --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Regulaciones y Permisos
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Información sobre permisos especiales e inspecciones.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Requiere Permiso --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <input type="hidden" name="requires_permit" value="0">
                                    <input type="checkbox" 
                                           name="requires_permit" 
                                           id="requires_permit" 
                                           value="1"
                                           {{ old('requires_permit', $shipmentItem->requires_permit) ? 'checked' : '' }}
                                           class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                    <label for="requires_permit" class="ml-2 block text-sm font-medium text-gray-700">
                                        Requiere Permiso Especial
                                    </label>
                                </div>
                                <div id="permit_fields" style="display: {{ old('requires_permit', $shipmentItem->requires_permit) ? 'block' : 'none' }};">
                                    <label for="permit_number" class="block text-sm font-medium text-gray-700">
                                        Número de Permiso
                                    </label>
                                    <input type="text" 
                                           name="permit_number" 
                                           id="permit_number" 
                                           value="{{ old('permit_number', $shipmentItem->permit_number) }}"
                                           maxlength="50"
                                           placeholder="Ej: PERM-2025-001"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500 text-sm">
                                </div>
                            </div>

                            {{-- Requiere Inspección --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <input type="hidden" name="requires_inspection" value="0">
                                    <input type="checkbox" 
                                           name="requires_inspection" 
                                           id="requires_inspection" 
                                           value="1"
                                           {{ old('requires_inspection', $shipmentItem->requires_inspection) ? 'checked' : '' }}
                                           class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                    <label for="requires_inspection" class="ml-2 block text-sm font-medium text-gray-700">
                                        Requiere Inspección
                                    </label>
                                </div>
                                <div id="inspection_fields" style="display: {{ old('requires_inspection', $shipmentItem->requires_inspection) ? 'block' : 'none' }};">
                                    <label for="inspection_type" class="block text-sm font-medium text-gray-700">
                                        Tipo de Inspección
                                    </label>
                                    <select name="inspection_type" 
                                            id="inspection_type" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-gray-500 focus:border-gray-500 text-sm">
                                        <option value="">Seleccione tipo</option>
                                        <option value="customs" {{ old('inspection_type', $shipmentItem->inspection_type) == 'customs' ? 'selected' : '' }}>Aduana</option>
                                        <option value="quality" {{ old('inspection_type', $shipmentItem->inspection_type) == 'quality' ? 'selected' : '' }}>Calidad</option>
                                        <option value="sanitary" {{ old('inspection_type', $shipmentItem->inspection_type) == 'sanitary' ? 'selected' : '' }}>Sanitaria</option>
                                        <option value="security" {{ old('inspection_type', $shipmentItem->inspection_type) == 'security' ? 'selected' : '' }}>Seguridad</option>
                                        <option value="environmental" {{ old('inspection_type', $shipmentItem->inspection_type) == 'environmental' ? 'selected' : '' }}>Ambiental</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('company.shipment-items.show', $shipmentItem) }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Actualizar Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- JavaScript para manejo de campos condicionales --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Campos de mercancías peligrosas
            const dangerousGoodsCheckbox = document.getElementById('is_dangerous_goods');
            const dangerousGoodsFields = document.getElementById('dangerous_goods_fields');

            function toggleDangerousGoodsFields() {
                if (dangerousGoodsCheckbox.checked) {
                    dangerousGoodsFields.style.display = 'block';
                } else {
                    dangerousGoodsFields.style.display = 'none';
                    document.getElementById('un_number').value = '';
                    document.getElementById('imdg_class').value = '';
                }
            }

            dangerousGoodsCheckbox.addEventListener('change', toggleDangerousGoodsFields);

            // Campos de refrigeración
            const refrigerationCheckbox = document.getElementById('requires_refrigeration');
            const refrigerationFields = document.getElementById('refrigeration_fields');

            function toggleRefrigerationFields() {
                if (refrigerationCheckbox.checked) {
                    refrigerationFields.style.display = 'grid';
                } else {
                    refrigerationFields.style.display = 'none';
                    document.getElementById('temperature_min').value = '';
                    document.getElementById('temperature_max').value = '';
                }
            }

            refrigerationCheckbox.addEventListener('change', toggleRefrigerationFields);

            // Campos de permisos
            const permitCheckbox = document.getElementById('requires_permit');
            const permitFields = document.getElementById('permit_fields');

            function togglePermitFields() {
                if (permitCheckbox.checked) {
                    permitFields.style.display = 'block';
                } else {
                    permitFields.style.display = 'none';
                    document.getElementById('permit_number').value = '';
                }
            }

            permitCheckbox.addEventListener('change', togglePermitFields);

            // Campos de inspección
            const inspectionCheckbox = document.getElementById('requires_inspection');
            const inspectionFields = document.getElementById('inspection_fields');

            function toggleInspectionFields() {
                if (inspectionCheckbox.checked) {
                    inspectionFields.style.display = 'block';
                } else {
                    inspectionFields.style.display = 'none';
                    document.getElementById('inspection_type').value = '';
                }
            }

            inspectionCheckbox.addEventListener('change', toggleInspectionFields);

            // Validación del formulario
            document.getElementById('itemForm').addEventListener('submit', function(e) {
                const packageQuantity = parseInt(document.getElementById('package_quantity').value);
                const grossWeight = parseFloat(document.getElementById('gross_weight_kg').value);
                const netWeight = parseFloat(document.getElementById('net_weight_kg').value);

                // Validar que el peso neto no sea mayor al peso bruto
                if (netWeight && grossWeight && netWeight > grossWeight) {
                    e.preventDefault();
                    alert('El peso neto no puede ser mayor al peso bruto.');
                    return false;
                }

                // Validar que la cantidad de bultos sea positiva
                if (packageQuantity < 1) {
                    e.preventDefault();
                    alert('La cantidad de bultos debe ser al menos 1.');
                    return false;
                }

                // Validar temperaturas si se requiere refrigeración
                const requiresRefrigeration = document.getElementById('requires_refrigeration').checked;
                if (requiresRefrigeration) {
                    const tempMin = parseFloat(document.getElementById('temperature_min').value);
                    const tempMax = parseFloat(document.getElementById('temperature_max').value);

                    if (tempMin && tempMax && tempMin > tempMax) {
                        e.preventDefault();
                        alert('La temperatura mínima no puede ser mayor a la temperatura máxima.');
                        return false;
                    }
                }
            });

            // Auto-calcular peso neto basado en peso bruto (85% por defecto) - Solo si no hay valor previo
            document.getElementById('gross_weight_kg').addEventListener('input', function() {
                const grossWeight = parseFloat(this.value);
                const netWeightField = document.getElementById('net_weight_kg');
                
                if (grossWeight && !netWeightField.value) {
                    netWeightField.value = (grossWeight * 0.85).toFixed(2);
                }
            });
        });
    </script>

   <script>
// Cargar datos existentes desde PHP
let containerIndex = {{ count($containerData ?? []) }};
let existingContainers = @json($containerData ?? []);

// Función para poblar contenedores existentes
function populateExistingContainers() {
    console.log('Poblando contenedores existentes:', existingContainers);
    
    existingContainers.forEach(function(containerData, index) {
        console.log(`Poblando contenedor ${index}:`, containerData);
        
        // Poblar campos básicos del contenedor existente
        const containerNumberField = document.querySelector(`input[name="containers[${index}][container_number]"]`);
        if (containerNumberField) {
            containerNumberField.value = containerData.container_number || '';
            console.log(`Set container_number[${index}]:`, containerData.container_number);
        }
        
        const containerTypeField = document.querySelector(`select[name="containers[${index}][container_type_id]"]`);
        if (containerTypeField) {
            containerTypeField.value = containerData.container_type_id || '';
            console.log(`Set container_type_id[${index}]:`, containerData.container_type_id);
        }
        
        const sealNumberField = document.querySelector(`input[name="containers[${index}][seal_number]"]`);
        if (sealNumberField) {
            sealNumberField.value = containerData.seal_number || '';
        }
        
        const tareWeightField = document.querySelector(`input[name="containers[${index}][tare_weight]"]`);
        if (tareWeightField) {
            tareWeightField.value = containerData.tare_weight || '';
        }
        
        // Poblar campos de distribución de carga
        const packageQtyField = document.querySelector(`input[name="containers[${index}][package_quantity]"]`);
        if (packageQtyField) {
            packageQtyField.value = containerData.package_quantity || '';
        }
        
        const grossWeightField = document.querySelector(`input[name="containers[${index}][gross_weight_kg]"]`);
        if (grossWeightField) {
            grossWeightField.value = containerData.gross_weight_kg || '';
        }
        
        const netWeightField = document.querySelector(`input[name="containers[${index}][net_weight_kg]"]`);
        if (netWeightField) {
            netWeightField.value = containerData.net_weight_kg || '';
        }
        
        const volumeField = document.querySelector(`input[name="containers[${index}][volume_m3]"]`);
        if (volumeField) {
            volumeField.value = containerData.volume_m3 || '';
        }
        
        const sequenceField = document.querySelector(`input[name="containers[${index}][loading_sequence]"]`);
        if (sequenceField) {
            sequenceField.value = containerData.loading_sequence || '';
        }
        
        
        // IMPORTANTE: Campo hidden para el ID (para actualizaciones)
        const idField = document.querySelector(`input[name="containers[${index}][id]"]`);
        if (idField) {
            idField.value = containerData.id || '';
            console.log(`Set container ID[${index}]:`, containerData.id);
        }
    });
}

// Función para agregar contenedor NUEVO
function addContainer() {
    const containersList = document.getElementById('containers-list');
    
    const containerHtml = `
        <div class="container-item mb-4 p-4 border border-gray-200 rounded-lg bg-gray-50" data-index="${containerIndex}">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-md font-medium text-gray-900">Contenedor ${containerIndex + 1}</h4>
                <button type="button" onclick="removeContainer(${containerIndex})" class="text-red-600 hover:text-red-800">
                    Eliminar
                </button>
            </div>

            <input type="hidden" name="containers[${containerIndex}][id]" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Número de Contenedor *
                    </label>
                    <input type="text" 
                           name="containers[${containerIndex}][container_number]" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="MSCU1234567"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Tipo de Contenedor *
                    </label>
                    <select name="containers[${containerIndex}][container_type_id]" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            required>
                        <option value="">Seleccionar tipo</option>
                        @foreach($containerTypes as $containerType)
                            <option value="{{ $containerType->id }}">{{ $containerType->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Peso de Tara (kg)
                    </label>
                    <input type="number" 
                           name="containers[${containerIndex}][tare_weight]" 
                           step="0.01"
                           value="2200"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="2200">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Número de Precinto
                    </label>
                    <input type="text" 
                           name="containers[${containerIndex}][seal_number]" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="SL123456">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Cantidad de Bultos *
                    </label>
                    <input type="number" 
                           name="containers[${containerIndex}][package_quantity]" 
                           min="1"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-package-qty"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Peso Bruto (kg) *
                    </label>
                    <input type="number" 
                           name="containers[${containerIndex}][gross_weight_kg]" 
                           step="0.01"
                           min="0.01"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-gross-weight"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Peso Neto (kg)
                    </label>
                    <input type="number" 
                           name="containers[${containerIndex}][net_weight_kg]" 
                           step="0.01"
                           min="0"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-net-weight">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Volumen (m³)
                    </label>
                    <input type="number" 
                           name="containers[${containerIndex}][volume_m3]" 
                           step="0.001"
                           min="0"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 container-volume">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Secuencia de Carga
                    </label>
                    <input type="text" 
                           name="containers[${containerIndex}][loading_sequence]" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="A1, B2, etc.">
                </div>

            </div>
        </div>
    `;

    containersList.insertAdjacentHTML('beforeend', containerHtml);
    containerIndex++;
    
    // Configurar event listeners para el nuevo contenedor
    setupContainerEventListeners();
}

// Función para eliminar contenedor
function removeContainer(index) {
    const containers = document.querySelectorAll('.container-item');
    if (containers.length > 1) {
        const containerToRemove = document.querySelector(`[data-index="${index}"]`);
        if (containerToRemove) {
            containerToRemove.remove();
            updateTotals();
            renumberContainers();
        }
    } else {
        alert('Debe mantener al menos un contenedor.');
    }
}

// Función para renumerar contenedores
function renumberContainers() {
    const containers = document.querySelectorAll('.container-item');
    containers.forEach((container, index) => {
        const title = container.querySelector('h4');
        if (title) {
            title.textContent = `Contenedor ${index + 1}`;
        }
    });
}

// Función para actualizar totales
function updateTotals() {
    let totalPackageQty = 0;
    let totalGrossWeight = 0;
    let totalNetWeight = 0;
    let totalVolume = 0;

    // Sumar todos los contenedores
    document.querySelectorAll('.container-package-qty').forEach(input => {
        totalPackageQty += parseInt(input.value) || 0;
    });

    document.querySelectorAll('.container-gross-weight').forEach(input => {
        totalGrossWeight += parseFloat(input.value) || 0;
    });

    document.querySelectorAll('.container-net-weight').forEach(input => {
        totalNetWeight += parseFloat(input.value) || 0;
    });

    document.querySelectorAll('.container-volume').forEach(input => {
        totalVolume += parseFloat(input.value) || 0;
    });

    // Obtener totales del ítem
    const itemPackageQty = parseInt(document.getElementById('package_quantity').value) || 0;
    const itemGrossWeight = parseFloat(document.getElementById('gross_weight_kg').value) || 0;

    // Actualizar display
    const packageTotalEl = document.getElementById('containers-package-total');
    const weightTotalEl = document.getElementById('containers-weight-total');
    const netWeightTotalEl = document.getElementById('containers-net-weight-total');
    const volumeTotalEl = document.getElementById('containers-volume-total');
    
    if (packageTotalEl) packageTotalEl.textContent = totalPackageQty;
    if (weightTotalEl) weightTotalEl.textContent = totalGrossWeight.toFixed(2);
    if (netWeightTotalEl) netWeightTotalEl.textContent = totalNetWeight.toFixed(2);
    if (volumeTotalEl) volumeTotalEl.textContent = totalVolume.toFixed(3);

    // Validación visual
    const packageIcon = document.getElementById('package-validation-icon');
    const weightIcon = document.getElementById('weight-validation-icon');

    if (packageIcon) {
        if (totalPackageQty === itemPackageQty) {
            packageIcon.textContent = '✓';
            packageIcon.className = 'text-green-600 ml-1';
        } else {
            packageIcon.textContent = '⚠️';
            packageIcon.className = 'text-red-600 ml-1';
        }
    }

    if (weightIcon) {
        if (Math.abs(totalGrossWeight - itemGrossWeight) <= 0.01) {
            weightIcon.textContent = '✓';
            weightIcon.className = 'text-green-600 ml-1';
        } else {
            weightIcon.textContent = '⚠️';
            weightIcon.className = 'text-red-600 ml-1';
        }
    }
}

// Configurar event listeners para contenedores
function setupContainerEventListeners() {
    document.querySelectorAll('.container-package-qty, .container-gross-weight, .container-net-weight, .container-volume').forEach(input => {
        input.removeEventListener('input', updateTotals); // Evitar duplicados
        input.addEventListener('input', updateTotals);
    });
}

// Detectar cambio en tipo de carga
function toggleContainersSection() {
    const cargoTypeSelect = document.getElementById('cargo_type_id');
    const containersSection = document.getElementById('containers-section');
    
    if (!cargoTypeSelect || !containersSection) return;
    
    const selectedText = cargoTypeSelect.options[cargoTypeSelect.selectedIndex]?.text.toLowerCase() || '';
    
    if (selectedText.includes('container') || selectedText.includes('contenedor')) {
        containersSection.style.display = 'block';
        
        // Si no hay contenedores Y no hay datos existentes, agregar uno automáticamente
        const containerItems = document.querySelectorAll('.container-item');
        if (containerItems.length === 0 && existingContainers.length === 0) {
            addContainer();
        }
    } else {
        containersSection.style.display = 'none';
    }
}

// Event listeners principales
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== INICIALIZANDO CONTENEDORES ===');
    console.log('Contenedores existentes:', existingContainers);
    console.log('Container index inicial:', containerIndex);
    
    // 1. Primero verificar el tipo de carga
    toggleContainersSection();
    
    // 2. Si hay contenedores existentes, poblarlos
    if (existingContainers && existingContainers.length > 0) {
        setTimeout(function() {
            populateExistingContainers();
            setupContainerEventListeners();
            updateTotals();
        }, 100);
    }
    
    // 3. Event listener para cambio de tipo de carga
    document.getElementById('cargo_type_id').addEventListener('change', toggleContainersSection);
    
    // 4. Event listeners para campos del ítem principal
    const packageQuantityField = document.getElementById('package_quantity');
    const grossWeightField = document.getElementById('gross_weight_kg');
    
    if (packageQuantityField) {
        packageQuantityField.addEventListener('input', function() {
            const itemTotalEl = document.getElementById('item-package-total');
            if (itemTotalEl) itemTotalEl.textContent = this.value;
            updateTotals();
        });
    }

    if (grossWeightField) {
        grossWeightField.addEventListener('input', function() {
            const itemTotalEl = document.getElementById('item-weight-total');
            if (itemTotalEl) itemTotalEl.textContent = parseFloat(this.value).toFixed(2);
            updateTotals();
        });
    }
});
</script>
</x-app-layout>