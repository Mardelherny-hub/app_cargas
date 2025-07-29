<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Conocimiento de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Complete todos los campos obligatorios marcados con *
                </p>
            </div>
            <div>
                <a href="{{ route('company.bills-of-lading.index') }}" 
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
            <form action="{{ route('company.bills-of-lading.store') }}" method="POST" class="space-y-6">
                @csrf
                
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
                            {{-- Envío --}}
                            <div class="sm:col-span-2">
                                <label for="shipment_id" class="block text-sm font-medium text-gray-700">
                                    Envío <span class="text-red-500">*</span>
                                </label>
                                <select id="shipment_id" name="shipment_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipment_id') border-red-300 @enderror">
                                    <option value="">Seleccione envío</option>
                                    @foreach($formData['shipments'] as $shipment)
                                        <option value="{{ $shipment->id }}" {{ old('shipment_id', $formData['preselectedShipment']) == $shipment->id ? 'selected' : '' }}>
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
                                       value="{{ old('bill_number') }}" placeholder="Ej: BL24001"
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
                                       value="{{ old('bill_date', $formData['defaultValues']['bill_date']) }}"
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
                                       value="{{ old('loading_date', $formData['defaultValues']['loading_date']) }}"
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
                                       value="{{ old('discharge_date') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_date') border-red-300 @enderror">
                                @error('discharge_date')
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
                            <p class="mt-1 text-sm text-gray-600">Condiciones de flete y pago</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- Términos de Flete --}}
                            <div>
                                <label for="freight_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Flete <span class="text-red-500">*</span>
                                </label>
                                <select id="freight_terms" name="freight_terms" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_terms') border-red-300 @enderror">
                                    <option value="">Seleccione términos</option>
                                    @foreach($formData['freightTerms'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('freight_terms', $formData['defaultValues']['freight_terms']) === $value ? 'selected' : '' }}>
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
                                    Términos de Pago
                                </label>
                                <select id="payment_terms" name="payment_terms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('payment_terms') border-red-300 @enderror">
                                    <option value="">Seleccione términos</option>
                                    @foreach($formData['paymentTerms'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_terms', $formData['defaultValues']['payment_terms']) === $value ? 'selected' : '' }}>
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
                                    Incoterms
                                </label>
                                <select id="incoterms" name="incoterms"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('incoterms') border-red-300 @enderror">
                                    <option value="">Seleccione Incoterm</option>
                                    @foreach($formData['incotermsList'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('incoterms') === $value ? 'selected' : '' }}>
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
                                    <option value="">Seleccione moneda</option>
                                    @foreach($formData['currencies'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('currency_code', $formData['defaultValues']['currency_code']) === $value ? 'selected' : '' }}>
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

                {{-- Clientes --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Partes Involucradas
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Cargador, consignatario y otras partes</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Cargador --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                                    Cargador/Exportador <span class="text-red-500">*</span>
                                </label>
                                <select id="shipper_id" name="shipper_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipper_id') border-red-300 @enderror">
                                    <option value="">Seleccione cargador</option>
                                    @foreach($formData['shippers'] as $shipper)
                                        <option value="{{ $shipper->id }}" {{ old('shipper_id', $formData['preselectedShipper']) == $shipper->id ? 'selected' : '' }}>
                                            {{ $shipper->legal_name }} ({{ $shipper->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('shipper_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Consignatario --}}
                            <div>
                                <label for="consignee_id" class="block text-sm font-medium text-gray-700">
                                    Consignatario/Importador <span class="text-red-500">*</span>
                                </label>
                                <select id="consignee_id" name="consignee_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('consignee_id') border-red-300 @enderror">
                                    <option value="">Seleccione consignatario</option>
                                    @foreach($formData['consignees'] as $consignee)
                                        <option value="{{ $consignee->id }}" {{ old('consignee_id', $formData['preselectedConsignee']) == $consignee->id ? 'selected' : '' }}>
                                            {{ $consignee->legal_name }} ({{ $consignee->tax_id }})
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
                                    <option value="">Sin especificar</option>
                                    @foreach($formData['notifyParties'] as $party)
                                        <option value="{{ $party->id }}" {{ old('notify_party_id') == $party->id ? 'selected' : '' }}>
                                            {{ $party->legal_name }} ({{ $party->tax_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('notify_party_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Propietario de Carga --}}
                            <div>
                                <label for="cargo_owner_id" class="block text-sm font-medium text-gray-700">
                                    Propietario de la Carga
                                </label>
                                <select id="cargo_owner_id" name="cargo_owner_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_owner_id') border-red-300 @enderror">
                                    <option value="">Sin especificar</option>
                                    @foreach($formData['cargoOwners'] as $owner)
                                        <option value="{{ $owner->id }}" {{ old('cargo_owner_id') == $owner->id ? 'selected' : '' }}>
                                            {{ $owner->legal_name }} ({{ $owner->tax_id }})
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

                {{-- Puertos y Rutas --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Puertos y Rutas
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Origen, destino y rutas de transporte</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Puerto de Carga --}}
                            <div>
                                <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Carga <span class="text-red-500">*</span>
                                </label>
                                <select id="loading_port_id" name="loading_port_id" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                                    <option value="">Seleccione puerto</option>
                                    @foreach($formData['loadingPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('loading_port_id') == $port->id ? 'selected' : '' }}>
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
                                    <option value="">Seleccione puerto</option>
                                    @foreach($formData['dischargePorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('discharge_port_id') == $port->id ? 'selected' : '' }}>
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
                                    @foreach($formData['transhipmentPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ old('transshipment_port_id') == $port->id ? 'selected' : '' }}>
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
                                        <option value="{{ $port->id }}" {{ old('final_destination_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('final_destination_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Aduanas --}}
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Aduanas</h4>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {{-- Aduana de Carga --}}
                                <div>
                                    <label for="loading_customs_id" class="block text-sm font-medium text-gray-700">
                                        Aduana de Carga
                                    </label>
                                    <select id="loading_customs_id" name="loading_customs_id"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_customs_id') border-red-300 @enderror">
                                        <option value="">Sin especificar</option>
                                        @foreach($formData['loadingCustoms'] as $customs)
                                            <option value="{{ $customs->id }}" {{ old('loading_customs_id') == $customs->id ? 'selected' : '' }}>
                                                {{ $customs->name }} - {{ $customs->country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('loading_customs_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Aduana de Descarga --}}
                                <div>
                                    <label for="discharge_customs_id" class="block text-sm font-medium text-gray-700">
                                        Aduana de Descarga
                                    </label>
                                    <select id="discharge_customs_id" name="discharge_customs_id"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_customs_id') border-red-300 @enderror">
                                        <option value="">Sin especificar</option>
                                        @foreach($formData['dischargeCustoms'] as $customs)
                                            <option value="{{ $customs->id }}" {{ old('discharge_customs_id') == $customs->id ? 'selected' : '' }}>
                                                {{ $customs->name }} - {{ $customs->country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('discharge_customs_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Descripción de Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Descripción de Carga
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Detalles de la mercadería y embalaje</p>
                        </div>

                        <div class="space-y-6">
                            {{-- Tipos de Carga --}}
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {{-- Tipo Principal de Carga --}}
                                <div>
                                    <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                                        Tipo Principal de Carga <span class="text-red-500">*</span>
                                    </label>
                                    <select id="primary_cargo_type_id" name="primary_cargo_type_id" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                                        <option value="">Seleccione tipo</option>
                                        @foreach($formData['cargoTypes'] as $type)
                                            <option value="{{ $type->id }}" {{ old('primary_cargo_type_id') == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
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
                                        <option value="">Seleccione tipo</option>
                                        @foreach($formData['packagingTypes'] as $type)
                                            <option value="{{ $type->id }}" {{ old('primary_packaging_type_id') == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('primary_packaging_type_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Descripción de la Carga --}}
                            <div>
                                <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                                    Descripción de la Mercadería <span class="text-red-500">*</span>
                                </label>
                                <textarea id="cargo_description" name="cargo_description" rows="3" required
                                          placeholder="Describa detalladamente la mercadería..."
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror">{{ old('cargo_description') }}</textarea>
                                @error('cargo_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Marcas y Código Commodity --}}
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {{-- Marcas de la Mercadería --}}
                                <div>
                                    <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                                        Marcas de la Mercadería
                                    </label>
                                    <textarea id="cargo_marks" name="cargo_marks" rows="2"
                                              placeholder="Marcas, números de serie, etc."
                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_marks') border-red-300 @enderror">{{ old('cargo_marks') }}</textarea>
                                    @error('cargo_marks')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Código Commodity --}}
                                <div>
                                    <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                                        Código Commodity/NCM
                                    </label>
                                    <input type="text" id="commodity_code" name="commodity_code"
                                           value="{{ old('commodity_code') }}" placeholder="Ej: 1234.56.78"
                                           maxlength="50"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_code') border-red-300 @enderror">
                                    @error('commodity_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pesos y Medidas --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                                Pesos y Medidas
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Cantidades, pesos y volúmenes</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- Total de Bultos --}}
                            <div>
                                <label for="total_packages" class="block text-sm font-medium text-gray-700">
                                    Total de Bultos <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="total_packages" name="total_packages" required
                                       value="{{ old('total_packages') }}" min="1"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_packages') border-red-300 @enderror">
                                @error('total_packages')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Peso Bruto --}}
                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Bruto (kg) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="gross_weight_kg" name="gross_weight_kg" required
                                       value="{{ old('gross_weight_kg') }}" step="0.001" min="0.001"
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
                                       value="{{ old('net_weight_kg') }}" step="0.001" min="0"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('net_weight_kg') border-red-300 @enderror">
                                @error('net_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Volumen --}}
                            <div>
                                <label for="volume_cbm" class="block text-sm font-medium text-gray-700">
                                    Volumen (m³)
                                </label>
                                <input type="number" id="volume_cbm" name="volume_cbm"
                                       value="{{ old('volume_cbm') }}" step="0.001" min="0"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_cbm') border-red-300 @enderror">
                                @error('volume_cbm')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Unidad de Medida --}}
                        <div class="mt-6">
                            <label for="measurement_unit" class="block text-sm font-medium text-gray-700">
                                Unidad de Medida
                            </label>
                            <select id="measurement_unit" name="measurement_unit"
                                    class="mt-1 block w-full sm:w-1/3 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('measurement_unit') border-red-300 @enderror">
                                @foreach($formData['measurementUnits'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('measurement_unit', $formData['defaultValues']['measurement_unit']) === $value ? 'selected' : '' }}>
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

                {{-- Características Especiales --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                Características Especiales
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">Condiciones especiales de la carga</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {{-- Requiere Inspección --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="requires_inspection" name="requires_inspection" type="checkbox" value="1"
                                           {{ old('requires_inspection') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="requires_inspection" class="font-medium text-gray-700">
                                        Requiere Inspección
                                    </label>
                                    <p class="text-gray-500">Inspección aduanera necesaria</p>
                                </div>
                            </div>

                            {{-- Contiene Mercadería Peligrosa --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="contains_dangerous_goods" name="contains_dangerous_goods" type="checkbox" value="1"
                                           {{ old('contains_dangerous_goods') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="contains_dangerous_goods" class="font-medium text-gray-700">
                                        Contiene Mercadería Peligrosa
                                    </label>
                                    <p class="text-gray-500">Requiere documentación especial</p>
                                </div>
                            </div>

                            {{-- Requiere Refrigeración --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="requires_refrigeration" name="requires_refrigeration" type="checkbox" value="1"
                                           {{ old('requires_refrigeration') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="requires_refrigeration" class="font-medium text-gray-700">
                                        Requiere Refrigeración
                                    </label>
                                    <p class="text-gray-500">Carga refrigerada</p>
                                </div>
                            </div>

                            {{-- Es Transbordo --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_transhipment" name="is_transhipment" type="checkbox" value="1"
                                           {{ old('is_transhipment') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_transhipment" class="font-medium text-gray-700">
                                        Es Transbordo
                                    </label>
                                    <p class="text-gray-500">Operación de transbordo</p>
                                </div>
                            </div>

                            {{-- Es Envío Parcial --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_partial_shipment" name="is_partial_shipment" type="checkbox" value="1"
                                           {{ old('is_partial_shipment') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_partial_shipment" class="font-medium text-gray-700">
                                        Es Envío Parcial
                                    </label>
                                    <p class="text-gray-500">Entrega en múltiples embarques</p>
                                </div>
                            </div>

                            {{-- Permite Entrega Parcial --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="allows_partial_delivery" name="allows_partial_delivery" type="checkbox" value="1"
                                           {{ old('allows_partial_delivery', '1') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="allows_partial_delivery" class="font-medium text-gray-700">
                                        Permite Entrega Parcial
                                    </label>
                                    <p class="text-gray-500">Se puede entregar por partes</p>
                                </div>
                            </div>
                        </div>

                        {{-- Campos de Mercadería Peligrosa --}}
                        <div id="dangerous_goods_fields" class="mt-6 hidden">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Información de Mercadería Peligrosa</h4>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                {{-- Número UN --}}
                                <div>
                                    <label for="un_number" class="block text-sm font-medium text-gray-700">
                                        Número UN
                                    </label>
                                    <input type="text" id="un_number" name="un_number"
                                           value="{{ old('un_number') }}" placeholder="UN1234"
                                           maxlength="10"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('un_number') border-red-300 @enderror">
                                    @error('un_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Clase IMDG --}}
                                <div>
                                    <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                        Clase IMDG
                                    </label>
                                    <input type="text" id="imdg_class" name="imdg_class"
                                           value="{{ old('imdg_class') }}" placeholder="3.1"
                                           maxlength="10"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('imdg_class') border-red-300 @enderror">
                                    @error('imdg_class')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Punto de Inflamación --}}
                                <div>
                                    <label for="flash_point" class="block text-sm font-medium text-gray-700">
                                        Punto de Inflamación (°C)
                                    </label>
                                    <input type="number" id="flash_point" name="flash_point"
                                           value="{{ old('flash_point') }}" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('flash_point') border-red-300 @enderror">
                                    @error('flash_point')
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
                                           {{ old('is_consolidated') ? 'checked' : '' }}
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
                                           {{ old('is_master_bill') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_master_bill" class="font-medium text-gray-700">
                                        Es Conocimiento Maestro
                                    </label>
                                    <p class="text-gray-500">Master Bill of Lading</p>
                                </div>
                            </div>

                            {{-- Es Conocimiento Hijo --}}
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="is_house_bill" name="is_house_bill" type="checkbox" value="1"
                                           {{ old('is_house_bill') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="is_house_bill" class="font-medium text-gray-700">
                                        Es Conocimiento Hijo
                                    </label>
                                    <p class="text-gray-500">House Bill of Lading</p>
                                </div>
                            </div>
                        </div>

                        {{-- Número de Conocimiento Maestro --}}
                        <div id="master_bill_field" class="mt-6 hidden">
                            <label for="master_bill_number" class="block text-sm font-medium text-gray-700">
                                Número de Conocimiento Maestro
                            </label>
                            <input type="text" id="master_bill_number" name="master_bill_number"
                                   value="{{ old('master_bill_number') }}" placeholder="Ingrese número del conocimiento maestro"
                                   maxlength="50"
                                   class="mt-1 block w-full sm:w-1/2 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                            @error('master_bill_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('company.bills-of-lading.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-md">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Crear Conocimiento
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
                } else {
                    dangerousGoodsFields.classList.add('hidden');
                }
            }
            
            dangerousGoodsCheckbox.addEventListener('change', toggleDangerousGoodsFields);
            toggleDangerousGoodsFields(); // Verificar estado inicial

            // Mostrar/ocultar campo de conocimiento maestro
            const houseBillCheckbox = document.getElementById('is_house_bill');
            const masterBillField = document.getElementById('master_bill_field');
            
            function toggleMasterBillField() {
                if (houseBillCheckbox.checked) {
                    masterBillField.classList.remove('hidden');
                } else {
                    masterBillField.classList.add('hidden');
                }
            }
            
            houseBillCheckbox.addEventListener('change', toggleMasterBillField);
            toggleMasterBillField(); // Verificar estado inicial

            // Validación: Evitar que sea maestro e hijo a la vez
            const masterBillCheckbox = document.getElementById('is_master_bill');
            
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