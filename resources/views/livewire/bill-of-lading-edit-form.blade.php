<div>
    {{-- Header con información del BL --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-blue-900">
                    Editando: {{ $billOfLading->bill_number }}
                </h3>
                <p class="text-sm text-blue-700 mt-1">
                    Shipment: {{ $billOfLading->shipment->shipment_number }} | 
                    Viaje: {{ $billOfLading->shipment->voyage->voyage_number }}
                </p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                    {{ $billOfLading->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                    {{ $billOfLading->status === 'verified' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $billOfLading->status === 'pending_review' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                    {{ ucfirst($billOfLading->status) }}
                </span>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('BillOfLading edit form loaded');
    });
    </script>

    {{-- Formulario --}}
    <form wire:submit.prevent="submit" class="space-y-8" x-data="{
        shipper_use_specific: @entangle('shipper_use_specific'),
        consignee_use_specific: @entangle('consignee_use_specific'), 
        notify_use_specific: @entangle('notify_use_specific')
    }">
        
        {{-- Información Básica --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Información Básica</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Envío --}}
                    <div class="sm:col-span-2">
                        <label for="shipment_id" class="block text-sm font-medium text-gray-700">
                            Envío <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="shipment_id" id="shipment_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipment_id') border-red-300 @enderror">
                            <option value="">Seleccionar envío</option>
                            @foreach($availableShipments as $shipment)
                                <option value="{{ $shipment->id }}">
                                    {{ $shipment->voyage->voyage_number ?? 'Sin viaje' }} - 
                                    {{ $shipment->shipment_number }}
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
                        <input wire:model="bill_number" type="text" id="bill_number" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_number') border-red-300 @enderror">
                        @error('bill_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Permiso de Embarque (TRP) --}}
                    <div>
                        <label for="permiso_embarque" class="block text-sm font-medium text-gray-700">
                            Permiso de Embarque (TRP) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                            id="permiso_embarque" 
                            wire:model.defer="permiso_embarque"
                            placeholder="Ej: 0-25001-TRB30265757Z"
                            maxlength="100"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('permiso_embarque') border-red-300 @enderror">
                        @error('permiso_embarque')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Número de permiso obligatorio para declaraciones AFIP</p>
                    </div>

                     {{-- Identificador Destinación Aduanera (idDecla) --}}
                    <div>
                        <label for="id_decla" class="block text-sm font-medium text-gray-700">
                            ID Destinación AFIP <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                            id="id_decla" 
                            wire:model.defer="id_decla"
                            placeholder="Ej: 25001TRB3025222E"
                            maxlength="16"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 uppercase @error('id_decla') border-red-300 @enderror">
                        @error('id_decla')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Número de destinación aduanera pre-cumplida en Malvina (16 caracteres)</p>
                    </div>

                    {{-- Fecha de Emisión --}}
                    <div>
                        <label for="bill_date" class="block text-sm font-medium text-gray-700">
                            Fecha de Emisión <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="bill_date" type="date" id="bill_date" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_date') border-red-300 @enderror">
                        @error('bill_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Fecha de Carga --}}
                    <div>
                        <label for="loading_date" class="block text-sm font-medium text-gray-700">
                            Fecha de Carga <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="loading_date" type="date" id="loading_date" required
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
                        <input wire:model="discharge_date" type="date" id="discharge_date"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_date') border-red-300 @enderror">
                        @error('discharge_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Términos de Flete --}}
                    <div>
                        <label for="freight_terms" class="block text-sm font-medium text-gray-700">
                            Términos de Flete <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="freight_terms" id="freight_terms" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_terms') border-red-300 @enderror">
                            <option value="prepaid">Prepago</option>
                            <option value="collect">Por Cobrar</option>
                            <option value="third_party">Tercero</option>
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
                        <select wire:model="payment_terms" id="payment_terms" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('payment_terms') border-red-300 @enderror">
                            <option value="cash">Efectivo</option>
                            <option value="credit">Crédito</option>
                            <option value="letter_of_credit">Carta de Crédito</option>
                            <option value="other">Otro</option>
                        </select>
                        @error('payment_terms')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Moneda --}}
                    <div>
                        <label for="currency_code" class="block text-sm font-medium text-gray-700">
                            Moneda <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="currency_code" id="currency_code" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('currency_code') border-red-300 @enderror">
                            <option value="USD">USD - Dólar Estadounidense</option>
                            <option value="ARS">ARS - Peso Argentino</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="BRL">BRL - Real Brasileño</option>
                        </select>
                        @error('currency_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Incoterms --}}
                    <div>
                        <label for="incoterms" class="block text-sm font-medium text-gray-700">
                            Incoterms
                        </label>
                        <input wire:model="incoterms" type="text" id="incoterms"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('incoterms') border-red-300 @enderror">
                        @error('incoterms')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Partes Involucradas --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Partes Involucradas</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Cargador/Exportador --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Cargador/Exportador <span class="text-red-500">*</span>
                            </label>
                            <button type="button" 
                                    wire:click="openCreateClientModal('shipper')"
                                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Crear nuevo
                            </button>
                        </div>
                        <select wire:model.live="shipper_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipper_id') border-red-300 @enderror">
                            <option value="">Seleccionar cargador...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('shipper_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        
                        {{-- Dirección específica para Shipper --}}
                        <div class="mt-4">
                            <label class="flex items-center">
                                <input x-model="shipper_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-600">Usar dirección específica para este conocimiento</span>
                            </label>
                            
                            <div x-show="shipper_use_specific" class="mt-3 space-y-3 p-3 bg-gray-50 rounded-md">
                                <div class="mt-3 space-y-3 p-3 bg-gray-50 rounded-md">
                                    <div>
                                        <input wire:model="shipper_specific_address_1" type="text" placeholder="Dirección línea 1"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <input wire:model="shipper_specific_address_2" type="text" placeholder="Dirección línea 2"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="shipper_specific_city" type="text" placeholder="Ciudad"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="shipper_specific_state" type="text" placeholder="Estado/Provincia"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="shipper_specific_postal_code" type="text" placeholder="Código postal"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="shipper_specific_country" type="text" placeholder="País"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="shipper_specific_phone" type="text" placeholder="Teléfono"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="shipper_specific_email" type="email" placeholder="Email"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Consignatario/Importador --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Consignatario/Importador <span class="text-red-500">*</span>
                            </label>
                            <button type="button" 
                                    wire:click="openCreateClientModal('consignee')"
                                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Crear nuevo
                            </button>
                        </div>
                        <select wire:model.live="consignee_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('consignee_id') border-red-300 @enderror">
                            <option value="">Seleccionar consignatario...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('consignee_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Dirección específica para Consignee --}}
                        <div class="mt-4">
                            <label class="flex items-center">
                                <input wire:model.live="consignee_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-600">Usar dirección específica para este conocimiento</span>
                            </label>
                            
                            @if($consignee_use_specific)
                                <div class="mt-3 space-y-3 p-3 bg-gray-50 rounded-md">
                                    <div>
                                        <input wire:model="consignee_specific_address_1" type="text" placeholder="Dirección línea 1"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <input wire:model="consignee_specific_address_2" type="text" placeholder="Dirección línea 2"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="consignee_specific_city" type="text" placeholder="Ciudad"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="consignee_specific_state" type="text" placeholder="Estado/Provincia"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="consignee_specific_postal_code" type="text" placeholder="Código postal"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="consignee_specific_country" type="text" placeholder="País"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="consignee_specific_phone" type="text" placeholder="Teléfono"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="consignee_specific_email" type="email" placeholder="Email"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Notificar a --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Notificar a
                            </label>
                            <button type="button" 
                                    wire:click="openCreateClientModal('notify')"
                                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Crear nuevo
                            </button>
                        </div>
                        <select wire:model.live="notify_party_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notify_party_id') border-red-300 @enderror">
                            <option value="">Seleccionar parte a notificar...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('notify_party_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Dirección específica para Notify --}}
                        <div class="mt-4">
                            <label class="flex items-center">
                                <input wire:model.live="notify_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-600">Usar dirección específica para este conocimiento</span>
                            </label>
                            
                            @if($notify_use_specific)
                                <div class="mt-3 space-y-3 p-3 bg-gray-50 rounded-md">
                                    <div>
                                        <input wire:model="notify_specific_address_1" type="text" placeholder="Dirección línea 1"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <input wire:model="notify_specific_address_2" type="text" placeholder="Dirección línea 2"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="notify_specific_city" type="text" placeholder="Ciudad"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="notify_specific_state" type="text" placeholder="Estado/Provincia"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="notify_specific_postal_code" type="text" placeholder="Código postal"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="notify_specific_country" type="text" placeholder="País"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input wire:model="notify_specific_phone" type="text" placeholder="Teléfono"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <input wire:model="notify_specific_email" type="email" placeholder="Email"
                                               class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Propietario de la Carga --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Propietario de la Carga
                            </label>
                            <button type="button" 
                                    wire:click="openCreateClientModal('cargo_owner')"
                                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Crear nuevo
                            </button>
                        </div>
                        <select wire:model.live="cargo_owner_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_owner_id') border-red-300 @enderror">
                            <option value="">Seleccionar propietario...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }}
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
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Puertos y Rutas</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Puerto de Carga --}}
                    <div>
                        <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                            Puerto de Carga <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="loading_port_id" id="loading_port_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                            <option value="">Seleccionar puerto de carga</option>
                            @foreach($loadingPorts as $port)                            
                                <option value="{{ $port->id }}">
                                    @if($port->country_id == 11)
                                        🇦🇷 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @else
                                        🇵🇾 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @endif
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
                        <select wire:model="discharge_port_id" id="discharge_port_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_port_id') border-red-300 @enderror">
                            <option value="">Seleccionar puerto de descarga</option>
                            @foreach($dischargePorts as $port)
                                <option value="{{ $port->id }}">
                                    @if($port->country_id == 11)
                                        🇦🇷 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @else
                                        🇵🇾 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @endif
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
                        <select wire:model="transshipment_port_id" id="transshipment_port_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('transshipment_port_id') border-red-300 @enderror">
                            <option value="">Sin transbordo</option>
                            @foreach($transshipmentPorts as $port)
                                <option value="{{ $port->id }}">
                                    @if($port->country_id == 11)
                                        🇦🇷 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @else
                                        🇵🇾 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @endif
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
                        <select wire:model="final_destination_port_id" id="final_destination_port_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('final_destination_port_id') border-red-300 @enderror">
                            <option value="">Mismo que descarga</option>
                            @foreach($finalDestinationPorts as $port)
                                <option value="{{ $port->id }}">
                                    @if($port->country_id == 11)
                                        🇦🇷 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @else
                                        🇵🇾 {{ $port->code }} - {{ $port->name }} - {{ $port->city }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('final_destination_port_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Aduana de Carga --}}
                    <div>
                        <label for="loading_customs_id" class="block text-sm font-medium text-gray-700">
                            Aduana de Carga
                        </label>
                        <select wire:model="loading_customs_id" id="loading_customs_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_customs_id') border-red-300 @enderror">
                            <option value="">Seleccionar aduana</option>
                            @foreach($customsOffices as $customs)
                                <option value="{{ $customs->id }}">{{ $customs->name }}</option>
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
                        <select wire:model="discharge_customs_id" id="discharge_customs_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_customs_id') border-red-300 @enderror">
                            <option value="">Seleccionar aduana</option>
                            @foreach($customsOffices as $customs)
                                <option value="{{ $customs->id }}">{{ $customs->name }}</option>
                            @endforeach
                        </select>
                        @error('discharge_customs_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Datos AFIP Origen/Destino --}}
                    <div class="mt-6 border-t pt-6 col-span-2">
                        <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Datos AFIP Origen/Destino
                        </h4>
                        <p class="text-xs text-gray-500 mb-4">Campos opcionales requeridos por AFIP para webservice RegistrarTitulosCbc</p>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Lugar de Origen --}}
                            <div>
                                <label for="origin_location" class="block text-sm font-medium text-gray-700">
                                    Lugar de Origen
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <input wire:model="origin_location" type="text" id="origin_location" 
                                    maxlength="50"
                                    placeholder="Ej: Depósito Central Buenos Aires"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('origin_location') border-red-300 @enderror">
                                @error('origin_location')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: LugarOrigen (máx. 50 caracteres)</p>
                            </div>

                            {{-- País Lugar de Origen --}}
                            <div>
                                <label for="origin_country_code" class="block text-sm font-medium text-gray-700">
                                    País Lugar de Origen
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <select wire:model="origin_country_code" id="origin_country_code"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('origin_country_code') border-red-300 @enderror">
                                    <option value="">Seleccionar país (opcional)</option>
                                    <option value="ARG">🇦🇷 Argentina</option>
                                    <option value="PRY">🇵🇾 Paraguay</option>
                                    <option value="BRA">🇧🇷 Brasil</option>
                                    <option value="URY">🇺🇾 Uruguay</option>
                                    <option value="CHL">🇨🇱 Chile</option>
                                    <option value="BOL">🇧🇴 Bolivia</option>
                                </select>
                                @error('origin_country_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: CodigoPaisLugarOrigen (código 3 letras)</p>
                            </div>

                            {{-- Fecha Carga en Lugar de Origen --}}
                            <div>
                                <label for="origin_loading_date" class="block text-sm font-medium text-gray-700">
                                    Fecha Carga en Lugar de Origen
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <input wire:model="origin_loading_date" type="datetime-local" id="origin_loading_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('origin_loading_date') border-red-300 @enderror">
                                @error('origin_loading_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: FechaCargaLugarOrigen</p>
                            </div>

                            {{-- País de Destino --}}
                            <div>
                                <label for="destination_country_code" class="block text-sm font-medium text-gray-700">
                                    País de Destino
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <select wire:model="destination_country_code" id="destination_country_code"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('destination_country_code') border-red-300 @enderror">
                                    <option value="">Seleccionar país (opcional)</option>
                                    <option value="ARG">🇦🇷 Argentina</option>
                                    <option value="PRY">🇵🇾 Paraguay</option>
                                    <option value="BRA">🇧🇷 Brasil</option>
                                    <option value="URY">🇺🇾 Uruguay</option>
                                    <option value="CHL">🇨🇱 Chile</option>
                                    <option value="BOL">🇧🇴 Bolivia</option>
                                </select>
                                @error('destination_country_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: CodigoPaisDestino (código 3 letras)</p>
                            </div>

                            {{-- Código Aduana de Descarga --}}
                            <div>
                                <label for="discharge_customs_code" class="block text-sm font-medium text-gray-700">
                                    Código Aduana de Descarga
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <input wire:model="discharge_customs_code" type="text" id="discharge_customs_code" 
                                    maxlength="3"
                                    placeholder="Ej: 001"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_customs_code') border-red-300 @enderror">
                                @error('discharge_customs_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: CodigoAduanaDescarga (BUR_DESC, 3 caracteres)</p>
                            </div>

                            {{-- Código Lugar Operativo de Descarga --}}
                            <div>
                                <label for="operational_discharge_code" class="block text-sm font-medium text-gray-700">
                                    Código Lugar Operativo Descarga
                                    <span class="text-xs text-gray-500 ml-1">(Opcional)</span>
                                </label>
                                <input wire:model="operational_discharge_code" type="text" id="operational_discharge_code" 
                                    maxlength="5"
                                    placeholder="Ej: PYTVT"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('operational_discharge_code') border-red-300 @enderror">
                                @error('operational_discharge_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">AFIP: CodigoLugarOperativoDescarga (LOT_ADUA, máx. 5 caracteres)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Información de Mercancías --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Información de Mercancías</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Tipo Principal de Carga --}}
                    <div>
                        <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                            Tipo Principal de Carga <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="primary_cargo_type_id" id="primary_cargo_type_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                            <option value="">Seleccionar tipo de carga</option>
                            @foreach($cargoTypes as $cargoType)
                                <option value="{{ $cargoType->id }}">{{ $cargoType->name }}</option>
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
                        <select wire:model="primary_packaging_type_id" id="primary_packaging_type_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_packaging_type_id') border-red-300 @enderror">
                            <option value="">Seleccionar tipo de embalaje</option>
                            @foreach($packagingTypes as $packagingType)
                                <option value="{{ $packagingType->id }}">{{ $packagingType->name }}</option>
                            @endforeach
                        </select>
                        @error('primary_packaging_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción de la Carga --}}
                    <div class="sm:col-span-2">
                        <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                            Descripción de la Carga <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model="cargo_description" id="cargo_description" rows="4" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror"
                                  placeholder="Descripción detallada de las mercancías..."></textarea>
                        @error('cargo_description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Marcas de la Carga --}}
                    <div>
                        <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                            Marcas de la Carga
                        </label>
                        <textarea wire:model="cargo_marks" id="cargo_marks" rows="3"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_marks') border-red-300 @enderror"
                                  placeholder="Marcas y números de los bultos..."></textarea>
                        @error('cargo_marks')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Código de Mercancía --}}
                    <div>
                        <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                            Código de Mercancía
                        </label>
                        <input wire:model="commodity_code" type="text" id="commodity_code"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_code') border-red-300 @enderror">
                        @error('commodity_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Cantidades y Pesos --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Cantidades y Pesos</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Total de Bultos --}}
                    <div>
                        <label for="total_packages" class="block text-sm font-medium text-gray-700">
                            Total de Bultos <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="total_packages" type="number" id="total_packages" min="1" required
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
                        <input wire:model="gross_weight_kg" type="number" step="0.01" id="gross_weight_kg" min="0" required
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
                        <input wire:model="net_weight_kg" type="number" step="0.01" id="net_weight_kg" min="0"
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
                        <input wire:model="volume_m3" type="number" step="0.01" id="volume_m3" min="0"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_m3') border-red-300 @enderror">
                        @error('volume_m3')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Unidad de Medida --}}
                    <div>
                        <label for="measurement_unit" class="block text-sm font-medium text-gray-700">
                            Unidad de Medida
                        </label>
                        <select wire:model="measurement_unit" id="measurement_unit"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('measurement_unit') border-red-300 @enderror">
                            <option value="KG">Kilogramos</option>
                            <option value="LB">Libras</option>
                            <option value="MT">Toneladas Métricas</option>
                        </select>
                        @error('measurement_unit')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Características Especiales --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Características Especiales</h3>

                <div class="space-y-6">
                    {{-- Mercancías Peligrosas --}}
                    <div>
                        <label class="flex items-center">
                            <input wire:model.live="contains_dangerous_goods" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm font-medium text-gray-700">Contiene mercancías peligrosas</span>
                        </label>
                        
                        @if($contains_dangerous_goods)
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <div>
                                    <label for="un_number" class="block text-sm font-medium text-gray-700">
                                        Número UN <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="un_number" type="text" id="un_number"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('un_number') border-red-300 @enderror">
                                    @error('un_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                        Clase IMDG <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="imdg_class" type="text" id="imdg_class"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('imdg_class') border-red-300 @enderror">
                                    @error('imdg_class')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Otras Características --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="flex items-center">
                            <input wire:model="requires_refrigeration" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Requiere refrigeración</span>
                        </label>

                        <label class="flex items-center">
                            <input wire:model="is_perishable" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Producto perecedero</span>
                        </label>

                        <label class="flex items-center">
                            <input wire:model="is_priority" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Prioridad alta</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Opciones de Consolidación --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Opciones de Consolidación</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <label class="flex items-center">
                        <input wire:model="is_consolidated" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Carga consolidada</span>
                    </label>

                    <label class="flex items-center">
                        <input wire:model.live="is_master_bill" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Conocimiento maestro</span>
                    </label>

                    <label class="flex items-center">
                        <input wire:model.live="is_house_bill" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Conocimiento hijo</span>
                    </label>
                </div>

                @if($is_house_bill)
                    <div class="mt-4">
                        <label for="master_bill_number" class="block text-sm font-medium text-gray-700">
                            Número del Conocimiento Maestro <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="master_bill_number" type="text" id="master_bill_number" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                        @error('master_bill_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        </div>

        {{-- Estado de Documentación --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Estado de Documentación</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <label class="flex items-center">
                        <input wire:model="original_released" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Original liberado</span>
                    </label>

                    <label class="flex items-center">
                        <input wire:model="documentation_complete" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Documentación completa</span>
                    </label>

                    <label class="flex items-center">
                        <input wire:model="requires_inspection" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Requiere inspección</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div class="flex items-center justify-end space-x-4">
            <button type="button" 
                    onclick="window.location.href='{{ route('company.bills-of-lading.show', $billOfLading) }}'"
                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Cancelar
            </button>
            
            <button type="submit" 
                    wire:loading.attr="disabled"
                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                <span wire:loading.remove>Actualizar Conocimiento</span>
                <span wire:loading class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Actualizando...
                </span>
            </button>
        </div>
    </form>

    {{-- Modal para crear cliente --}}
    @if($showCreateClientModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" 
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Crear Nuevo Cliente
                                @if($clientType == 'shipper') - Cargador
                                @elseif($clientType == 'consignee') - Consignatario  
                                @elseif($clientType == 'notify') - Parte a Notificar
                                @elseif($clientType == 'cargo_owner') - Propietario de Carga
                                @endif
                            </h3>
                            
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Razón Social <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="new_legal_name" type="text" required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_legal_name') border-red-300 @enderror">
                                    @error('new_legal_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        CUIT/RUC/Tax ID <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="new_tax_id" type="text" required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_tax_id') border-red-300 @enderror">
                                    @error('new_tax_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        País <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="new_country_id" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_country_id') border-red-300 @enderror">
                                        <option value="">Seleccionar país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_country_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input wire:model="new_email" type="email"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_email') border-red-300 @enderror">
                                    @error('new_email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input wire:model="new_phone" type="text"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_phone') border-red-300 @enderror">
                                    @error('new_phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                                    <input wire:model="new_address" type="text"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_address') border-red-300 @enderror">
                                    @error('new_address')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Ciudad</label>
                                    <input wire:model="new_city" type="text"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_city') border-red-300 @enderror">
                                    @error('new_city')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" wire:click="createClient"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Crear Cliente
                    </button>
                    <button type="button" wire:click="cancelCreateClient"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Mensajes Flash --}}
    @if (session()->has('message'))
        <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-md mt-4">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-md mt-4">
            {{ session('error') }}
        </div>
    @endif
</div>