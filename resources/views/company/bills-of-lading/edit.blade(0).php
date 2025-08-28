<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Conocimiento de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Conocimiento: <span class="font-medium">{{ $billOfLading->bill_number }}</span> | 
                    Estado: <span class="font-medium">{{ ucfirst($billOfLading->status) }}</span>
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('company.bills-of-lading.update', $billOfLading) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                {{-- Información Básica --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Información Básica
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Datos principales del conocimiento de embarque</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Envío (Solo lectura en edición) --}}
                            <div class="sm:col-span-2">
                                <label for="shipment_id" class="block text-sm font-medium text-gray-700">
                                    Envío <span class="text-red-500">*</span>
                                </label>
                                <div class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                                    {{ $billOfLading->shipment->shipment_number }} - {{ $billOfLading->shipment->voyage->voyage_number }}
                                    <span class="text-xs text-gray-500">(No modificable)</span>
                                </div>
                                <input type="hidden" name="shipment_id" value="{{ $billOfLading->shipment_id }}">
                            </div>

                            {{-- Número de Conocimiento --}}
                            <div>
                                <label for="bill_number" class="block text-sm font-medium text-gray-700">
                                    Número de Conocimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="bill_number" name="bill_number" required
                                       value="{{ old('bill_number', $billOfLading->bill_number) }}" 
                                       placeholder="Ej: BL24001"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_number') border-red-300 @enderror">
                                @error('bill_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Fecha del Conocimiento --}}
                            <div>
                                <label for="bill_date" class="block text-sm font-medium text-gray-700">
                                    Fecha del Conocimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="bill_date" name="bill_date" required
                                       value="{{ old('bill_date', $billOfLading->bill_date?->format('Y-m-d')) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_date') border-red-300 @enderror">
                                @error('bill_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Fecha de Carga --}}
                            <div>
                                <label for="loading_date" class="block text-sm font-medium text-gray-700">
                                    Fecha de Carga
                                </label>
                                <input type="date" id="loading_date" name="loading_date" 
                                       value="{{ old('loading_date', $billOfLading->loading_date?->format('Y-m-d')) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_date') border-red-300 @enderror">
                                @error('loading_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Fecha de Descarga --}}
                            <div>
                                <label for="discharge_date" class="block text-sm font-medium text-gray-700">
                                    Fecha de Descarga
                                </label>
                                <input type="date" id="discharge_date" name="discharge_date" 
                                       value="{{ old('discharge_date', $billOfLading->discharge_date?->format('Y-m-d')) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_date') border-red-300 @enderror">
                                @error('discharge_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tipo de Conocimiento --}}
                            <div>
                                <label for="bill_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Conocimiento <span class="text-red-500">*</span>
                                </label>
                                <select id="bill_type" name="bill_type" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_type') border-red-300 @enderror">
                                    {{-- ✅ REMOVER LA OPCIÓN VACÍA --}}
                                    @php
                                        $currentBillType = old('bill_type', $billOfLading->bill_type ?: 'original');
                                        $billTypeOptions = [
                                            'original' => 'Original',
                                            'copy' => 'Copia',
                                            'duplicate' => 'Duplicado', 
                                            'amendment' => 'Enmienda'
                                        ];
                                    @endphp
                                    
                                    @foreach($billTypeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ $currentBillType == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('bill_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Partes Involucradas --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Partes Involucradas
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Información de clientes y partes del conocimiento</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Cargador/Exportador --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                                    Cargador/Exportador <span class="text-red-500">*</span>
                                </label>
                                <select id="shipper_id" name="shipper_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipper_id') border-red-300 @enderror">
                                    <option value="">Seleccionar cargador</option>
                                    @foreach($formData['shippers'] as $shipper)
                                        <option value="{{ $shipper->id }}" {{ old('shipper_id', $billOfLading->shipper_id) == $shipper->id ? 'selected' : '' }}>
                                            {{ $shipper->legal_name }} - {{ $shipper->tax_id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('shipper_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Consignatario/Importador --}}
                            <div>
                                <label for="consignee_id" class="block text-sm font-medium text-gray-700">
                                    Consignatario/Importador <span class="text-red-500">*</span>
                                </label>
                                <select id="consignee_id" name="consignee_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('consignee_id') border-red-300 @enderror">
                                    <option value="">Seleccionar consignatario</option>
                                    @foreach($formData['consignees'] as $consignee)
                                        <option value="{{ $consignee->id }}" {{ old('consignee_id', $billOfLading->consignee_id) == $consignee->id ? 'selected' : '' }}>
                                            {{ $consignee->legal_name }} - {{ $consignee->tax_id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('consignee_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Notificar a --}}
                            <div>
                                <label for="notify_party_id" class="block text-sm font-medium text-gray-700">
                                    Notificar a
                                </label>
                                <select id="notify_party_id" name="notify_party_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notify_party_id') border-red-300 @enderror">
                                    <option value="">Sin notificación</option>
                                    @foreach($formData['notifyParties'] as $party)
                                        <option value="{{ $party->id }}" {{ old('notify_party_id', $billOfLading->notify_party_id) == $party->id ? 'selected' : '' }}>
                                            {{ $party->legal_name }} - {{ $party->tax_id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('notify_party_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Propietario de la Carga --}}
                            <div>
                                <label for="cargo_owner_id" class="block text-sm font-medium text-gray-700">
                                    Propietario de la Carga
                                </label>
                                <select id="cargo_owner_id" name="cargo_owner_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_owner_id') border-red-300 @enderror">
                                    <option value="">Mismo que cargador</option>
                                    @foreach($formData['cargoOwners'] as $owner)
                                        <option value="{{ $owner->id }}" {{ old('cargo_owner_id', $billOfLading->cargo_owner_id) == $owner->id ? 'selected' : '' }}>
                                            {{ $owner->legal_name }} - {{ $owner->tax_id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cargo_owner_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Puertos y Ubicaciones --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Puertos y Ubicaciones
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Puntos de origen, destino y tránsito</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Puerto de Carga --}}
                            <div>
                                <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Carga <span class="text-red-500">*</span>
                                </label>
                                <select id="loading_port_id" name="loading_port_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                                    <option value="">Seleccionar puerto</option>
                                    @foreach($formData['loadingPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('loading_port_id', $billOfLading->loading_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('loading_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Puerto de Descarga --}}
                            <div>
                                <label for="discharge_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Descarga <span class="text-red-500">*</span>
                                </label>
                                <select id="discharge_port_id" name="discharge_port_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_port_id') border-red-300 @enderror">
                                    <option value="">Seleccionar puerto</option>
                                    @foreach($formData['dischargePorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('discharge_port_id', $billOfLading->discharge_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('discharge_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Puerto de Transbordo --}}
                            <div>
                                <label for="transshipment_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Transbordo
                                </label>
                                <select id="transshipment_port_id" name="transshipment_port_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('transshipment_port_id') border-red-300 @enderror">
                                    <option value="">Sin transbordo</option>
                                    @foreach($formData['transshipmentPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('transshipment_port_id', $billOfLading->transshipment_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('transshipment_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Puerto de Destino Final --}}
                            <div>
                                <label for="final_destination_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Destino Final
                                </label>
                                <select id="final_destination_port_id" name="final_destination_port_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('final_destination_port_id') border-red-300 @enderror">
                                    <option value="">Mismo que descarga</option>
                                    @foreach($formData['finalDestinationPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('final_destination_port_id', $billOfLading->final_destination_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('final_destination_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tipos de Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Tipos de Carga y Embalaje
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Clasificación de la mercancía</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Tipo Principal de Carga --}}
                            <div>
                                <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo Principal de Carga <span class="text-red-500">*</span>
                                </label>
                                <select id="primary_cargo_type_id" name="primary_cargo_type_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($formData['cargoTypes'] as $type)
                                        <option value="{{ $type->id }}" {{ old('primary_cargo_type_id', $billOfLading->primary_cargo_type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }} - {{ $type->description }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_cargo_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tipo Principal de Embalaje --}}
                            <div>
                                <label for="primary_packaging_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo Principal de Embalaje <span class="text-red-500">*</span>
                                </label>
                                <select id="primary_packaging_type_id" name="primary_packaging_type_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_packaging_type_id') border-red-300 @enderror">
                                    <option value="">Seleccionar embalaje</option>
                                    @foreach($formData['packagingTypes'] as $type)
                                        <option value="{{ $type->id }}" {{ old('primary_packaging_type_id', $billOfLading->primary_packaging_type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }} - {{ $type->description }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_packaging_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Medidas y Pesos --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Medidas y Pesos
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Información cuantitativa de la carga</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- Peso Bruto --}}
                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Bruto (kg) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="gross_weight_kg" name="gross_weight_kg" required
                                       value="{{ old('gross_weight_kg', $billOfLading->gross_weight_kg) }}" 
                                       step="0.01" min="0" placeholder="1000.00"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('gross_weight_kg') border-red-300 @enderror">
                                @error('gross_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Neto --}}
                            <div>
                                <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Neto (kg)
                                </label>
                                <input type="number" id="net_weight_kg" name="net_weight_kg"
                                       value="{{ old('net_weight_kg', $billOfLading->net_weight_kg) }}" 
                                       step="0.01" min="0" placeholder="950.00"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('net_weight_kg') border-red-300 @enderror">
                                @error('net_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Volumen --}}
                            <div>
                                <label for="volume_m3" class="block text-sm font-medium text-gray-700">
                                    Volumen (m³)
                                </label>
                                <input type="number" id="volume_m3" name="volume_m3"
                                       value="{{ old('volume_m3', $billOfLading->volume_m3) }}" 
                                       step="0.001" min="0" placeholder="25.500"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_m3') border-red-300 @enderror">
                                @error('volume_m3')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Cantidad de Bultos --}}
                            <div>
                                <label for="total_packages" class="block text-sm font-medium text-gray-700">
                                    Total Bultos <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="total_packages" name="total_packages" required
                                       value="{{ old('total_packages', $billOfLading->total_packages) }}" 
                                       min="1" placeholder="100"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_packages') border-red-300 @enderror">
                                @error('total_packages')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Unidad de Medida --}}
                            <div class="sm:col-span-2">
                                <label for="measurement_unit" class="block text-sm font-medium text-gray-700">
                                    Unidad de Medida <span class="text-red-500">*</span>
                                </label>
                                <select id="measurement_unit" name="measurement_unit" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('measurement_unit') border-red-300 @enderror">
                                    @foreach($formData['measurementUnits'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('measurement_unit', $billOfLading->measurement_unit ?? 'kg') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('measurement_unit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Términos Comerciales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                                Términos Comerciales
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Condiciones comerciales y de pago</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Términos de Flete --}}
                            <div>
                                <label for="freight_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Flete <span class="text-red-500">*</span>
                                </label>
                                <select id="freight_terms" name="freight_terms" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_terms') border-red-300 @enderror">
                                    @foreach($formData['freightTerms'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('freight_terms', $billOfLading->freight_terms) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('freight_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Términos de Pago --}}
                            <div>
                                <label for="payment_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Pago <span class="text-red-500">*</span>
                                </label>
                                <select id="payment_terms" name="payment_terms" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('payment_terms') border-red-300 @enderror">
                                    @foreach($formData['paymentTerms'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_terms', $billOfLading->payment_terms ?? 'cash') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Incoterms --}}
                            <div>
                                <label for="incoterms" class="block text-sm font-medium text-gray-700">
                                    Incoterms <span class="text-red-500">*</span>
                                </label>
                                <select id="incoterms" name="incoterms" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('incoterms') border-red-300 @enderror">
                                    @foreach($formData['incotermsList'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('incoterms', $billOfLading->incoterms) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('incoterms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Moneda --}}
                            <div>
                                <label for="currency_code" class="block text-sm font-medium text-gray-700">
                                    Moneda <span class="text-red-500">*</span>
                                </label>
                                <select id="currency_code" name="currency_code" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('currency_code') border-red-300 @enderror">
                                    @foreach($formData['currencies'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('currency_code', $billOfLading->currency_code ?? 'USD') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('currency_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Valor Declarado --}}
                            <div>
                                <label for="declared_value" class="block text-sm font-medium text-gray-700">
                                    Valor Declarado
                                </label>
                                <input type="number" id="declared_value" name="declared_value"
                                       value="{{ old('declared_value', $billOfLading->declared_value) }}" 
                                       step="0.01" min="0" placeholder="10000.00"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('declared_value') border-red-300 @enderror">
                                @error('declared_value')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Valor del Flete --}}
                            <div>
                                <label for="freight_amount" class="block text-sm font-medium text-gray-700">
                                    Valor del Flete
                                </label>
                                <input type="number" id="freight_amount" name="freight_amount"
                                       value="{{ old('freight_amount', $billOfLading->freight_amount) }}" 
                                       step="0.01" min="0" placeholder="1500.00"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_amount') border-red-300 @enderror">
                                @error('freight_amount')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Descripción de la Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Descripción de la Carga
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Detalles de las mercancías transportadas</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Descripción de las Mercancías --}}
                            <div class="sm:col-span-2">
                                <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                                    Descripción de las Mercancías <span class="text-red-500">*</span>
                                </label>
                                <textarea id="cargo_description" name="cargo_description" required rows="4"
                                          placeholder="Descripción detallada de las mercancías..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror">{{ old('cargo_description', $billOfLading->cargo_description) }}</textarea>
                                @error('cargo_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Marcas y Números --}}
                            <div>
                                <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                                    Marcas y Números
                                </label>
                                <textarea id="cargo_marks" name="cargo_marks" rows="3"
                                          placeholder="Marcas, números y señales de identificación..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_marks') border-red-300 @enderror">{{ old('cargo_marks', $billOfLading->cargo_marks) }}</textarea>
                                @error('cargo_marks')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Código de Commodity --}}
                            <div>
                                <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                                    Código NCM/HS
                                </label>
                                <input type="text" id="commodity_code" name="commodity_code"
                                       value="{{ old('commodity_code', $billOfLading->commodity_code) }}" 
                                       placeholder="1234.56.78.90"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_code') border-red-300 @enderror">
                                @error('commodity_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Características Especiales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                Características Especiales
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Condiciones especiales de la carga</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Mercancías Peligrosas --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="contains_dangerous_goods" name="contains_dangerous_goods" type="checkbox" value="1"
                                           {{ old('contains_dangerous_goods', $billOfLading->contains_dangerous_goods) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="contains_dangerous_goods" class="font-medium text-gray-700">
                                        Mercancías Peligrosas
                                    </label>
                                    <p class="text-gray-500">Carga clasificada como peligrosa</p>
                                </div>
                            </div>

                            {{-- Requiere Refrigeración --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="requires_refrigeration" name="requires_refrigeration" type="checkbox" value="1"
                                           {{ old('requires_refrigeration', $billOfLading->requires_refrigeration) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="requires_refrigeration" class="font-medium text-gray-700">
                                        Requiere Refrigeración
                                    </label>
                                    <p class="text-gray-500">Carga que requiere control de temperatura</p>
                                </div>
                            </div>

                            {{-- Requiere Inspección --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="requires_inspection" name="requires_inspection" type="checkbox" value="1"
                                           {{ old('requires_inspection', $billOfLading->requires_inspection) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="requires_inspection" class="font-medium text-gray-700">
                                        Requiere Inspección
                                    </label>
                                    <p class="text-gray-500">Debe ser inspeccionada antes del transporte</p>
                                </div>
                            </div>

                            {{-- Es Transbordo --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_transhipment" name="is_transhipment" type="checkbox" value="1"
                                           {{ old('is_transhipment', $billOfLading->is_transhipment) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_transhipment" class="font-medium text-gray-700">
                                        Es Transbordo
                                    </label>
                                    <p class="text-gray-500">Carga en transbordo</p>
                                </div>
                            </div>

                            {{-- Envío Parcial --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_partial_shipment" name="is_partial_shipment" type="checkbox" value="1"
                                           {{ old('is_partial_shipment', $billOfLading->is_partial_shipment) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_partial_shipment" class="font-medium text-gray-700">
                                        Envío Parcial
                                    </label>
                                    <p class="text-gray-500">Permite envíos parciales</p>
                                </div>
                            </div>

                            {{-- Permite Entrega Parcial --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="allows_partial_delivery" name="allows_partial_delivery" type="checkbox" value="1"
                                           {{ old('allows_partial_delivery', $billOfLading->allows_partial_delivery) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="allows_partial_delivery" class="font-medium text-gray-700">
                                        Permite Entrega Parcial
                                    </label>
                                    <p class="text-gray-500">Se puede entregar parcialmente</p>
                                </div>
                            </div>
                        </div>

                        {{-- Campos adicionales para mercancías peligrosas --}}
                        <div id="dangerous_goods_fields" class="mt-6 {{ old('contains_dangerous_goods', $billOfLading->contains_dangerous_goods) ? '' : 'hidden' }}">
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="un_number" class="block text-sm font-medium text-gray-700">
                                        Número UN <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="un_number" name="un_number"
                                           value="{{ old('un_number', $billOfLading->un_number) }}" 
                                           placeholder="UN1234"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('un_number') border-red-300 @enderror">
                                    @error('un_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                        Clase IMDG <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="imdg_class" name="imdg_class"
                                           value="{{ old('imdg_class', $billOfLading->imdg_class) }}" 
                                           placeholder="3"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('imdg_class') border-red-300 @enderror">
                                    @error('imdg_class')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Consolidación --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Opciones de Consolidación
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Configurar tipo de conocimiento</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Es Consolidado --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_consolidated" name="is_consolidated" type="checkbox" value="1"
                                           {{ old('is_consolidated', $billOfLading->is_consolidated) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_consolidated" class="font-medium text-gray-700">
                                        Es Consolidado
                                    </label>
                                    <p class="text-gray-500">Carga consolidada</p>
                                </div>
                            </div>

                            {{-- Es Conocimiento Maestro --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_master_bill" name="is_master_bill" type="checkbox" value="1"
                                           {{ old('is_master_bill', $billOfLading->is_master_bill) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_master_bill" class="font-medium text-gray-700">
                                        Conocimiento Maestro
                                    </label>
                                    <p class="text-gray-500">Master Bill of Lading</p>
                                </div>
                            </div>

                            {{-- Es Conocimiento Hijo --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_house_bill" name="is_house_bill" type="checkbox" value="1"
                                        {{ old('is_house_bill', $billOfLading->is_house_bill) ? 'checked' : '' }}
                                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_house_bill" class="font-medium text-gray-700">
                                        Conocimiento Hijo
                                    </label>
                                    <p class="text-gray-500">House Bill of Lading</p>
                                </div>
                            </div>

                            {{-- Campo de Conocimiento Maestro (se muestra cuando is_house_bill está marcado) --}}
                            <div id="master_bill_field" class="hidden sm:col-span-3">
                                <label for="master_bill_number" class="block text-sm font-medium text-gray-700">
                                    Conocimiento Maestro <span class="text-red-500">*</span>
                                </label>
                                <select id="master_bill_number" name="master_bill_number"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                                    <option value="">Seleccione conocimiento maestro</option>
                                    @foreach($formData['masterBills'] as $masterBill)
                                        <option value="{{ $masterBill['bill_number'] }}" 
                                                {{ old('master_bill_number', $billOfLading->master_bill_number) == $masterBill['bill_number'] ? 'selected' : '' }}>
                                            {{ $masterBill['display_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('master_bill_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Instrucciones Especiales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Instrucciones y Observaciones
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Información adicional y notas especiales</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Instrucciones Especiales --}}
                            <div>
                                <label for="special_instructions" class="block text-sm font-medium text-gray-700">
                                    Instrucciones Especiales
                                </label>
                                <textarea id="special_instructions" name="special_instructions" rows="3"
                                          placeholder="Instrucciones especiales para el manejo de la carga..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('special_instructions') border-red-300 @enderror">{{ old('special_instructions', $billOfLading->special_instructions) }}</textarea>
                                @error('special_instructions')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Instrucciones de Manejo --}}
                            <div>
                                <label for="handling_instructions" class="block text-sm font-medium text-gray-700">
                                    Instrucciones de Manejo
                                </label>
                                <textarea id="handling_instructions" name="handling_instructions" rows="3"
                                          placeholder="Instrucciones para el manejo y manipulación..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('handling_instructions') border-red-300 @enderror">{{ old('handling_instructions', $billOfLading->handling_instructions) }}</textarea>
                                @error('handling_instructions')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Observaciones Aduaneras --}}
                            <div>
                                <label for="customs_remarks" class="block text-sm font-medium text-gray-700">
                                    Observaciones Aduaneras
                                </label>
                                <textarea id="customs_remarks" name="customs_remarks" rows="3"
                                          placeholder="Observaciones para la autoridad aduanera..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('customs_remarks') border-red-300 @enderror">{{ old('customs_remarks', $billOfLading->customs_remarks) }}</textarea>
                                @error('customs_remarks')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Notas Internas --}}
                            <div>
                                <label for="internal_notes" class="block text-sm font-medium text-gray-700">
                                    Notas Internas
                                </label>
                                <textarea id="internal_notes" name="internal_notes" rows="3"
                                          placeholder="Notas internas de uso exclusivo de la empresa..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('internal_notes') border-red-300 @enderror">{{ old('internal_notes', $billOfLading->internal_notes) }}</textarea>
                                @error('internal_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Actualizar Conocimiento
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar campos de mercadería peligrosa
            const dangerousGoodsCheckbox = document.getElementById('contains_dangerous_goods');
            const dangerousGoodsFields = document.getElementById('dangerous_goods_fields');
            
            function toggleDangerousGoodsFields() {
                if (dangerousGoodsCheckbox.checked) {
                    dangerousGoodsFields.classList.remove('hidden');
                    // Hacer campos requeridos
                    document.getElementById('un_number').setAttribute('required', 'required');
                    document.getElementById('imdg_class').setAttribute('required', 'required');
                } else {
                    dangerousGoodsFields.classList.add('hidden');
                    // Quitar requerido
                    document.getElementById('un_number').removeAttribute('required');
                    document.getElementById('imdg_class').removeAttribute('required');
                    // Limpiar valores
                    document.getElementById('un_number').value = '';
                    document.getElementById('imdg_class').value = '';
                }
            }
            
            dangerousGoodsCheckbox.addEventListener('change', toggleDangerousGoodsFields);
            
            // Ejecutar al cargar para casos de old() data
            toggleDangerousGoodsFields();

            // Validación de peso neto vs bruto
            const grossWeightInput = document.getElementById('gross_weight_kg');
            const netWeightInput = document.getElementById('net_weight_kg');
            
            function validateWeights() {
                const grossWeight = parseFloat(grossWeightInput.value) || 0;
                const netWeight = parseFloat(netWeightInput.value) || 0;
                
                if (netWeight > grossWeight && grossWeight > 0) {
                    netWeightInput.setCustomValidity('El peso neto no puede ser mayor al peso bruto');
                } else {
                    netWeightInput.setCustomValidity('');
                }
            }
            
            grossWeightInput.addEventListener('input', validateWeights);
            netWeightInput.addEventListener('input', validateWeights);

            // Validación de fechas
            const loadingDateInput = document.getElementById('loading_date');
            const dischargeDateInput = document.getElementById('discharge_date');
            
            function validateDates() {
                const loadingDate = loadingDateInput.value;
                const dischargeDate = dischargeDateInput.value;
                
                if (loadingDate && dischargeDate && dischargeDate < loadingDate) {
                    dischargeDateInput.setCustomValidity('La fecha de descarga debe ser posterior a la fecha de carga');
                } else {
                    dischargeDateInput.setCustomValidity('');
                }
            }
            
            loadingDateInput.addEventListener('change', validateDates);
            dischargeDateInput.addEventListener('change', validateDates);

            // Evitar seleccionar el mismo puerto
            const loadingPortSelect = document.getElementById('loading_port_id');
            const dischargePortSelect = document.getElementById('discharge_port_id');
            
            function validatePorts() {
                const loadingPort = loadingPortSelect.value;
                const dischargePort = dischargePortSelect.value;
                
                if (loadingPort && dischargePort && loadingPort === dischargePort) {
                    dischargePortSelect.setCustomValidity('El puerto de descarga debe ser diferente al puerto de carga');
                } else {
                    dischargePortSelect.setCustomValidity('');
                }
            }
            
            loadingPortSelect.addEventListener('change', validatePorts);
            dischargePortSelect.addEventListener('change', validatePorts);
        });
    </script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const houseBillCheckbox = document.getElementById('is_house_bill');
    const masterBillField = document.getElementById('master_bill_field');
    const masterBillCheckbox = document.getElementById('is_master_bill');
    
    function toggleMasterBillField() {
        if (houseBillCheckbox.checked) {
            masterBillField.classList.remove('hidden');
        } else {
            masterBillField.classList.add('hidden');
            document.getElementById('master_bill_number').value = '';
        }
    }
    
    // Verificar estado inicial
    toggleMasterBillField();
    
    // Event listeners
    houseBillCheckbox.addEventListener('change', toggleMasterBillField);
    
    // Validación: Evitar que sea maestro e hijo a la vez
    masterBillCheckbox.addEventListener('change', function() {
        if (this.checked) {
            houseBillCheckbox.checked = false;
            toggleMasterBillField();
        }
    });
    
    houseBillCheckbox.addEventListener('change', function() {
        if (this.checked) {
            masterBillCheckbox.checked = false;
        }
    });
});
</script>
    @endpush
</x-app-layout>