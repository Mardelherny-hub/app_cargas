<div>
    
    {{-- Script para evitar el mensaje de navegación --}}
   <!-- REEMPLAZAR TODO EL SCRIPT POR: -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('BillOfLading form loaded');
});
</script>

    {{-- Formulario --}}
    <form wire:submit.prevent="submit" class="space-y-8">
        
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
                                    {{ $shipment->voyage->voyage_number ?? 'Sin viaje' }} - {{ $shipment->shipment_number }}
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

                    {{-- Fecha del Conocimiento --}}
                    <div>
                        <label for="bill_date" class="block text-sm font-medium text-gray-700">
                            Fecha del Conocimiento <span class="text-red-500">*</span>
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

                    {{-- Términos de Flete --}}
                    <div>
                        <label for="freight_terms" class="block text-sm font-medium text-gray-700">
                            Términos de Flete <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="freight_terms" id="freight_terms" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('freight_terms') border-red-300 @enderror">
                            <option value="prepaid">Prepagado</option>
                            <option value="collect">Por Cobrar</option>
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
                            <option value="advance">Adelanto</option>
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
                        <select wire:model="incoterms" id="incoterms"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('incoterms') border-red-300 @enderror">
                            <option value="">Seleccionar (opcional)</option>
                            <option value="EXW">EXW - Ex Works</option>
                            <option value="FCA">FCA - Free Carrier</option>
                            <option value="FAS">FAS - Free Alongside Ship</option>
                            <option value="FOB">FOB - Free On Board</option>
                            <option value="CFR">CFR - Cost and Freight</option>
                            <option value="CIF">CIF - Cost, Insurance and Freight</option>
                            <option value="CPT">CPT - Carriage Paid To</option>
                            <option value="CIP">CIP - Carriage and Insurance Paid</option>
                            <option value="DAP">DAP - Delivered at Place</option>
                            <option value="DPU">DPU - Delivered at Place Unloaded</option>
                            <option value="DDP">DDP - Delivered Duty Paid</option>
                        </select>
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
        <div class="mb-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Partes Involucradas
            </h3>
            <p class="mt-1 text-sm text-gray-600">Empresas y contactos relacionados con el embarque</p>
        </div>

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
                @livewire('search-client', [
                    'selectedClientId' => $shipper_id,
                    'fieldName' => 'shipper_id',
                    'required' => true,
                    'placeholder' => 'Buscar cargador por nombre o CUIT...'
                ], key('shipper-search'))
                @error('shipper_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
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
                @livewire('search-client', [
                    'selectedClientId' => $consignee_id,
                    'fieldName' => 'consignee_id',
                    'required' => true,
                    'placeholder' => 'Buscar consignatario por nombre o CUIT...'
                ], key('consignee-search'))
                @error('consignee_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
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
                @livewire('search-client', [
                    'selectedClientId' => $notify_party_id,
                    'fieldName' => 'notify_party_id',
                    'required' => false,
                    'placeholder' => 'Buscar parte a notificar...'
                ], key('notify-search'))
                @error('notify_party_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
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
                @livewire('search-client', [
                    'selectedClientId' => $cargo_owner_id,
                    'fieldName' => 'cargo_owner_id',
                    'required' => false,
                    'placeholder' => 'Buscar propietario...'
                ], key('cargo-owner-search'))
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
                        <select wire:model.live="loading_port_id" id="loading_port_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                            <option value="">Seleccione puerto</option>
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
                        <select wire:model.live="discharge_port_id" id="discharge_port_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('discharge_port_id') border-red-300 @enderror">
                            <option value="">Seleccione puerto</option>
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
                            <option value="">Seleccione puerto (opcional)</option>
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
                            <option value="">Seleccione puerto (opcional)</option>
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
                </div>

                {{-- Aduanas --}}
                <div class="mt-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">Oficinas Aduaneras</h4>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {{-- Aduana de Carga --}}
                        <div>
                            <label for="loading_customs_id" class="block text-sm font-medium text-gray-700">
                                Aduana de Carga
                            </label>
                            <select wire:model="loading_customs_id" id="loading_customs_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_customs_id') border-red-300 @enderror">
                                <option value="">Seleccione aduana (opcional)</option>
                                @foreach($customsOffices as $customs)
                                    <option value="{{ $customs->id }}">{{ $customs->name }} - {{ $customs->code }}</option>
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
                                <option value="">Seleccione aduana (opcional)</option>
                                @foreach($customsOffices as $customs)
                                    <option value="{{ $customs->id }}">{{ $customs->name }} - {{ $customs->code }}</option>
                                @endforeach
                            </select>
                            @error('discharge_customs_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Datos AFIP Origen/Destino --}}
                <div class="mt-6 border-t pt-6">
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

        {{-- Descripción de Carga --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Descripción de Carga
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">Detalles de la mercadería y embalaje</p>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Tipo Principal de Carga --}}
                    <div>
                        <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                            Tipo Principal de Carga <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="primary_cargo_type_id" id="primary_cargo_type_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                            <option value="">Seleccione tipo</option>
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
                            <option value="">Seleccione embalaje</option>
                            @foreach($packagingTypes as $packagingType)
                                <option value="{{ $packagingType->id }}">{{ $packagingType->name }}</option>
                            @endforeach
                        </select>
                        @error('primary_packaging_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Descripción de Mercaderías --}}
                <div class="mt-6">
                    <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                        Descripción de Mercaderías <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="cargo_description" id="cargo_description" rows="4" required
                              placeholder="Describa detalladamente la mercadería..."
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror"></textarea>
                    @error('cargo_description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-6">
                    {{-- Marcas de la Mercadería --}}
                    <div>
                        <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                            Marcas de la Mercadería
                        </label>
                        <textarea wire:model="cargo_marks" id="cargo_marks" rows="2"
                                  placeholder="Marcas, números de serie, etc."
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_marks') border-red-300 @enderror"></textarea>
                        @error('cargo_marks')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Código Commodity/NCM --}}
                    <div>
                        <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                            Código Commodity/NCM
                        </label>
                        <input wire:model="commodity_code" type="text" id="commodity_code"
                               placeholder="Ej: 1234.56.78"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('commodity_code') border-red-300 @enderror">
                        @error('commodity_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Pesos y Medidas --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l-3-9m3 9l3-9"/>
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
                        <input wire:model="total_packages" type="number" id="total_packages" min="1" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_packages') border-red-300 @enderror">
                        @error('total_packages')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Peso Bruto (kg) --}}
                    <div>
                        <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                            Peso Bruto (kg) <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="gross_weight_kg" type="number" id="gross_weight_kg" step="0.01" min="0.01" required
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
                        <input wire:model="net_weight_kg" type="number" id="net_weight_kg" step="0.01" min="0"
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
                        <input wire:model="volume_m3" type="number" id="volume_m3" step="0.001" min="0"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_m3') border-red-300 @enderror">
                        @error('volume_m3')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Unidad de Medida --}}
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-4 mt-6">
                    <div>
                        <label for="measurement_unit" class="block text-sm font-medium text-gray-700">
                            Unidad de Medida
                        </label>
                        <select wire:model="measurement_unit" id="measurement_unit"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('measurement_unit') border-red-300 @enderror">
                            <option value="KG">Kilogramos</option>
                            <option value="KG">Kilogramos</option>
                            <option value="TN">Toneladas</option>
                            <option value="LT">Litros</option>
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
                <div class="mb-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        Características Especiales
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">Condiciones especiales de la carga</p>
                </div>

                <div class="space-y-6">
                    {{-- Requiere Inspección --}}
                    <div class="flex items-center">
                        <input wire:model="requires_inspection" type="checkbox" id="requires_inspection" value="1"
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="requires_inspection" class="ml-3 block text-sm font-medium text-gray-700">
                            Requiere Inspección
                        </label>
                    </div>

                    {{-- Contiene Mercaderías Peligrosas --}}
                    <div class="flex items-center">
                        <input wire:model.live="contains_dangerous_goods" type="checkbox" id="contains_dangerous_goods" value="1"
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="contains_dangerous_goods" class="ml-3 block text-sm font-medium text-gray-700">
                            Contiene Mercaderías Peligrosas
                        </label>
                    </div>

                    @if($contains_dangerous_goods)
                        <div class="ml-7 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {{-- Número UN --}}
                            <div>
                                <label for="un_number" class="block text-sm font-medium text-gray-700">
                                    Número UN <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="un_number" type="text" id="un_number"
                                       placeholder="Ej: UN1234"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('un_number') border-red-300 @enderror">
                                @error('un_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Clase IMDG --}}
                            <div>
                                <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                    Clase IMDG <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="imdg_class" type="text" id="imdg_class"
                                       placeholder="Ej: 3"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('imdg_class') border-red-300 @enderror">
                                @error('imdg_class')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    {{-- Requiere Refrigeración --}}
                    <div class="flex items-center">
                        <input wire:model="requires_refrigeration" type="checkbox" id="requires_refrigeration" value="1"
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="requires_refrigeration" class="ml-3 block text-sm font-medium text-gray-700">
                            Requiere Refrigeración
                        </label>
                    </div>

                    {{-- Es Perecedero --}}
                    <div class="flex items-center">
                        <input wire:model="is_perishable" type="checkbox" id="is_perishable" value="1"
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="is_perishable" class="ml-3 block text-sm font-medium text-gray-700">
                            Es Perecedero
                        </label>
                    </div>

                    {{-- Es Envío Prioritario --}}
                    <div class="flex items-center">
                        <input wire:model="is_priority" type="checkbox" id="is_priority" value="1"
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="is_priority" class="ml-3 block text-sm font-medium text-gray-700">
                            Es Envío Prioritario
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Opciones de Consolidación --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Opciones de Consolidación
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">Configure tipo de conocimiento</p>
                </div>

                <div class="space-y-6">
                    {{-- Es Consolidado --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model="is_consolidated" type="checkbox" id="is_consolidated" value="1"
                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_consolidated" class="font-medium text-gray-700">
                                Es Consolidado
                            </label>
                            <p class="text-gray-500">Múltiples cargas en un conocimiento</p>
                        </div>
                    </div>

                    {{-- Es Conocimiento Maestro --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model="is_master_bill" type="checkbox" id="is_master_bill" value="1"
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
                            <input wire:model.live="is_house_bill" type="checkbox" id="is_house_bill" value="1"
                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_house_bill" class="font-medium text-gray-700">
                                Conocimiento Hijo
                            </label>
                            <p class="text-gray-500">House Bill of Lading</p>
                        </div>
                    </div>

                    @if($is_house_bill)
                    <div class="ml-7 space-y-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Conocimiento Maestro <span class="text-red-500">*</span>
                        </label>
                        
                        @if(count($availableMasterBills) > 0)
                            {{-- Selector de BL maestros disponibles --}}
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Seleccionar del shipment actual:</label>
                                <select wire:model.live="master_bill_number" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                                    <option value="">-- Seleccionar conocimiento maestro --</option>
                                    @foreach($availableMasterBills as $masterBill)
                                        <option value="{{ $masterBill->bill_number }}">
                                            {{ $masterBill->bill_number }} 
                                            @if($masterBill->cargo_description)
                                                - {{ Str::limit($masterBill->cargo_description, 50) }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            {{-- Opción para ingreso manual --}}
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">O ingresar manualmente:</label>
                                <input wire:model="master_bill_number" type="text" 
                                    placeholder="Número del conocimiento maestro"
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                            </div>
                        @else
                            {{-- No hay BL maestros, mostrar aviso e input manual --}}
                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-3">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.862-.833-2.632 0L3.18 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    <div class="text-sm text-yellow-800">
                                        <p class="font-medium">No hay conocimientos maestros en este shipment</p>
                                        <p>Debe crear primero un conocimiento maestro o ingresar el número manualmente.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <input wire:model="master_bill_number" type="text" 
                                placeholder="Número del conocimiento maestro"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                        @endif
                        
                        @error('master_bill_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                </div>
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div class="flex items-center justify-end space-x-4">
            <button type="button" 
                    onclick="window.location.href='{{ route('company.bills-of-lading.index') }}'"
                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Cancelar
            </button>
            
            <button type="submit" 
                    wire:loading.attr="disabled"
                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                <span wire:loading.remove>Crear Conocimiento</span>
                <span wire:loading class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Creando...
                </span>
            </button>
        </div>
    </form>

    {{-- Modal para crear cliente (CORREGIDO) --}}
    @if($showCreateClientModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" 
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Spacer element to center the modal --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            {{-- Modal panel --}}
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
                            
                                {{-- Razón Social --}}
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

                                {{-- Tax ID --}}
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

                                {{-- País --}}
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

                                {{-- Email y Teléfono --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                </div>

                                {{-- Dirección --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                                    <input wire:model="new_address" type="text"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('new_address') border-red-300 @enderror">
                                    @error('new_address')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Ciudad --}}
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
                
                {{-- Botones del modal --}}
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
</div>