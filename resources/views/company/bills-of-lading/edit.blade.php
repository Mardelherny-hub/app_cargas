<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Conocimiento de Embarque') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Conocimiento: {{ $billOfLading->bill_number }} - Envío: {{ $billOfLading->shipment->shipment_number }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    Ver Detalle
                </a>
                <a href="{{ route('company.bills-of-lading.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de error --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
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

            {{-- Formulario --}}
            <form method="POST" action="{{ route('company.bills-of-lading.update', $billOfLading) }}" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- SECCIÓN: Información Básica --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Información Básica
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Datos fundamentales del conocimiento de embarque.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Envío --}}
                            <div class="sm:col-span-2">
                                <label for="shipment_id" class="block text-sm font-medium text-gray-700">
                                    Envío <span class="text-red-500">*</span>
                                </label>
                                <select id="shipment_id" name="shipment_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipment_id') border-red-300 @enderror">
                                    <option value="">Seleccione envío</option>
                                    @foreach($formData['shipments'] as $shipment)
                                        <option value="{{ $shipment->id }}" 
                                                {{ old('shipment_id', $billOfLading->shipment_id) == $shipment->id ? 'selected' : '' }}>
                                            {{ $shipment->shipment_number }} - {{ $shipment->voyage->voyage_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('shipment_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
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

                            {{-- Estado --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select id="status" name="status"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-300 @enderror">
                                    @foreach($formData['statuses'] ?? [] as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('status', $billOfLading->status) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Partes Involucradas --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Partes Involucradas
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Cargador, consignatario y otros participantes en la operación.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Cargador/Exportador --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                                    Cargador/Exportador <span class="text-red-500">*</span>
                                </label>
                                <select id="shipper_id" name="shipper_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipper_id') border-red-300 @enderror">
                                    <option value="">Seleccione cargador</option>
                                    @foreach($formData['shippers'] as $shipper)
                                        <option value="{{ $shipper->id }}" 
                                                {{ old('shipper_id', $billOfLading->shipper_id) == $shipper->id ? 'selected' : '' }}>
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
                                    <option value="">Seleccione consignatario</option>
                                    @foreach($formData['consignees'] as $consignee)
                                        <option value="{{ $consignee->id }}" 
                                                {{ old('consignee_id', $billOfLading->consignee_id) == $consignee->id ? 'selected' : '' }}>
                                            {{ $consignee->legal_name }} - {{ $consignee->tax_id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('consignee_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Parte a Notificar --}}
                            <div>
                                <label for="notify_party_id" class="block text-sm font-medium text-gray-700">
                                    Parte a Notificar
                                </label>
                                <select id="notify_party_id" name="notify_party_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notify_party_id') border-red-300 @enderror">
                                    <option value="">Sin parte a notificar</option>
                                    @foreach($formData['notifyParties'] as $notifyParty)
                                        <option value="{{ $notifyParty->id }}" 
                                                {{ old('notify_party_id', $billOfLading->notify_party_id) == $notifyParty->id ? 'selected' : '' }}>
                                            {{ $notifyParty->legal_name }} - {{ $notifyParty->tax_id }}
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
                                    @foreach($formData['cargoOwners'] as $cargoOwner)
                                        <option value="{{ $cargoOwner->id }}" 
                                                {{ old('cargo_owner_id', $billOfLading->cargo_owner_id) == $cargoOwner->id ? 'selected' : '' }}>
                                            {{ $cargoOwner->legal_name }} - {{ $cargoOwner->tax_id }}
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

                {{-- SECCIÓN: Rutas y Puertos --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Rutas y Puertos
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Puertos de origen, destino y rutas de tránsito.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Puerto de Carga --}}
                            <div>
                                <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Carga <span class="text-red-500">*</span>
                                </label>
                                <select id="loading_port_id" name="loading_port_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                                    <option value="">Seleccione puerto de carga</option>
                                    @foreach($formData['loadingPorts'] as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('loading_port_id', $billOfLading->loading_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name ?? '' }}
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
                                    <option value="">Seleccione puerto de descarga</option>
                                    @foreach($formData['dischargePorts'] as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('discharge_port_id', $billOfLading->discharge_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name ?? '' }}
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
                                        <option value="{{ $port->id }}" 
                                                {{ old('transshipment_port_id', $billOfLading->transshipment_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('transshipment_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Destino Final --}}
                            <div>
                                <label for="final_destination_port_id" class="block text-sm font-medium text-gray-700">
                                    Destino Final
                                </label>
                                <select id="final_destination_port_id" name="final_destination_port_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('final_destination_port_id') border-red-300 @enderror">
                                    <option value="">Mismo que puerto de descarga</option>
                                    @foreach($formData['finalDestinationPorts'] as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('final_destination_port_id', $billOfLading->final_destination_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name ?? '' }}
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

                {{-- SECCIÓN: Información de Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Información de Carga
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Descripción, peso, cantidad y tipo de mercadería.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Tipo de Carga --}}
                            <div>
                                <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Carga <span class="text-red-500">*</span>
                                </label>
                                <select id="primary_cargo_type_id" name="primary_cargo_type_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                                    <option value="">Seleccione tipo de carga</option>
                                    @foreach($formData['cargoTypes'] as $cargoType)
                                        <option value="{{ $cargoType->id }}" 
                                                {{ old('primary_cargo_type_id', $billOfLading->primary_cargo_type_id) == $cargoType->id ? 'selected' : '' }}>
                                            {{ $cargoType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_cargo_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tipo de Embalaje --}}
                            <div>
                                <label for="primary_packaging_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Embalaje <span class="text-red-500">*</span>
                                </label>
                                <select id="primary_packaging_type_id" name="primary_packaging_type_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_packaging_type_id') border-red-300 @enderror">
                                    <option value="">Seleccione tipo de embalaje</option>
                                    @foreach($formData['packagingTypes'] as $packagingType)
                                        <option value="{{ $packagingType->id }}" 
                                                {{ old('primary_packaging_type_id', $billOfLading->primary_packaging_type_id) == $packagingType->id ? 'selected' : '' }}>
                                            {{ $packagingType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_packaging_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Total de Paquetes --}}
                            <div>
                                <label for="total_packages" class="block text-sm font-medium text-gray-700">
                                    Total de Paquetes
                                </label>
                                <input type="number" id="total_packages" name="total_packages" min="1"
                                       value="{{ old('total_packages', $billOfLading->total_packages) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_packages') border-red-300 @enderror">
                                @error('total_packages')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Bruto (KG) --}}
                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Bruto (KG)
                                </label>
                                <input type="number" id="gross_weight_kg" name="gross_weight_kg" step="0.01" min="0"
                                       value="{{ old('gross_weight_kg', $billOfLading->gross_weight_kg) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('gross_weight_kg') border-red-300 @enderror">
                                @error('gross_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Neto (KG) --}}
                            <div>
                                <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Neto (KG)
                                </label>
                                <input type="number" id="net_weight_kg" name="net_weight_kg" step="0.01" min="0"
                                       value="{{ old('net_weight_kg', $billOfLading->net_weight_kg) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('net_weight_kg') border-red-300 @enderror">
                                @error('net_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Volumen --}}
                            <div>
                                <label for="total_volume" class="block text-sm font-medium text-gray-700">
                                    Volumen (m³)
                                </label>
                                <input type="number" id="total_volume" name="total_volume" step="0.01" min="0"
                                       value="{{ old('total_volume', $billOfLading->total_volume) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_volume') border-red-300 @enderror">
                                @error('total_volume')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Descripción de la Carga --}}
                            <div class="sm:col-span-2 lg:col-span-3">
                                <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                                    Descripción de la Carga
                                </label>
                                <textarea id="cargo_description" name="cargo_description" rows="3"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror"
                                          placeholder="Descripción detallada de la mercadería...">{{ old('cargo_description', $billOfLading->cargo_description) }}</textarea>
                                @error('cargo_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Términos Comerciales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                                Términos Comerciales
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Condiciones comerciales y financieras del envío.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Términos de Flete --}}
                            <div>
                                <label for="freight_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Flete
                                </label>
                                <select id="freight_terms" name="freight_terms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_terms') border-red-300 @enderror">
                                    @foreach($formData['freightTerms'] ?? [] as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('freight_terms', $billOfLading->freight_terms) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('freight_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Incoterms --}}
                            <div>
                                <label for="incoterms" class="block text-sm font-medium text-gray-700">
                                    Incoterms
                                </label>
                                <select id="incoterms" name="incoterms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('incoterms') border-red-300 @enderror">
                                    <option value="">Seleccione Incoterm</option>
                                    @foreach($formData['incotermsList'] ?? [] as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('incoterms', $billOfLading->incoterms) == $key ? 'selected' : '' }}>
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
                                    Moneda
                                </label>
                                <select id="currency_code" name="currency_code"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('currency_code') border-red-300 @enderror">
                                    @foreach($formData['currencies'] ?? [] as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('currency_code', $billOfLading->currency_code) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('currency_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN: Observaciones --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Observaciones
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Instrucciones especiales y notas adicionales.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6">
                            {{-- Instrucciones Especiales --}}
                            <div>
                                <label for="special_instructions" class="block text-sm font-medium text-gray-700">
                                    Instrucciones Especiales
                                </label>
                                <textarea id="special_instructions" name="special_instructions" rows="3"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('special_instructions') border-red-300 @enderror"
                                          placeholder="Instrucciones especiales para el manejo de la carga...">{{ old('special_instructions', $billOfLading->special_instructions) }}</textarea>
                                @error('special_instructions')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Notas Internas --}}
                            <div>
                                <label for="internal_notes" class="block text-sm font-medium text-gray-700">
                                    Notas Internas
                                </label>
                                <textarea id="internal_notes" name="internal_notes" rows="3"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('internal_notes') border-red-300 @enderror"
                                          placeholder="Notas para uso interno de la empresa...">{{ old('internal_notes', $billOfLading->internal_notes) }}</textarea>
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
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Actualizar Conocimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>