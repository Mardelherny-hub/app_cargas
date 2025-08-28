<DIV>

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
                        <input wire:model.live="bill_number" type="text" id="bill_number" required
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
                        <input wire:model.live="bill_date" type="date" id="bill_date" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('bill_date') border-red-300 @enderror">
                        @error('bill_date')
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
                        <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                            Cargador/Exportador <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="shipper_id" id="shipper_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('shipper_id') border-red-300 @enderror">
                            <option value="">Seleccionar cargador...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }} ({{ $client->tax_id }})
                                </option>
                            @endforeach
                        </select>
                        @error('shipper_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if($shipper_id)
                            <div class="mt-2 bg-blue-50 p-3 rounded-md">
                                <p class="text-sm font-medium text-blue-900">
                                    Cargador: {{ $this->getSelectedClientName($shipper_id) }}
                                </p>
                            </div>

                            <div class="mt-3 flex items-center">
                                <input wire:model.live="bl_shipper_use_specific" type="checkbox" id="bl_shipper_use_specific"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="bl_shipper_use_specific" class="ml-2 block text-sm text-gray-700">
                                    Usar dirección específica
                                </label>
                            </div>

                            @if($bl_shipper_use_specific)
                                <div class="mt-3 bg-gray-50 p-4 rounded-md space-y-3">
                                    <input wire:model.live="bl_shipper_address_1" type="text" placeholder="Dirección específica"
                                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <input wire:model.live="bl_shipper_city" type="text" placeholder="Ciudad específica"
                                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Consignatario/Importador --}}
                    <div>
                        <label for="consignee_id" class="block text-sm font-medium text-gray-700">
                            Consignatario/Importador <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="consignee_id" id="consignee_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('consignee_id') border-red-300 @enderror">
                            <option value="">Seleccionar consignatario...</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ $client->legal_name }} ({{ $client->tax_id }})
                                </option>
                            @endforeach
                        </select>
                        @error('consignee_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if($consignee_id)
                            <div class="mt-2 bg-green-50 p-3 rounded-md">
                                <p class="text-sm font-medium text-green-900">
                                    Consignatario: {{ $this->getSelectedClientName($consignee_id) }}
                                </p>
                            </div>

                            <div class="mt-3 flex items-center">
                                <input wire:model.live="bl_consignee_use_specific" type="checkbox" id="bl_consignee_use_specific" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="bl_consignee_use_specific" class="ml-2 block text-sm text-gray-700">
                                    Usar dirección específica
                                </label>
                            </div>

                            @if($bl_consignee_use_specific)
                                <div class="mt-3 bg-gray-50 p-4 rounded-md space-y-3">
                                    <input wire:model.live="bl_consignee_address_1" type="text" placeholder="Dirección específica"
                                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <input wire:model.live="bl_consignee_city" type="text" placeholder="Ciudad específica"
                                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Puertos y Rutas --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Puertos y Rutas</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Puerto de Carga --}}
                    <div>
                        <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                            Puerto de Carga <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="loading_port_id" id="loading_port_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('loading_port_id') border-red-300 @enderror">
                            <option value="">Seleccionar puerto...</option>
                            @foreach($loadingPorts as $port)
                                <option value="{{ $port->id }}">
                                    {{ $port->name }} ({{ $port->code }})
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
                            <option value="">Seleccionar puerto...</option>
                            @foreach($dischargePorts as $port)
                                <option value="{{ $port->id }}">
                                    {{ $port->name }} ({{ $port->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('discharge_port_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Mercancías --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Información de Mercancías</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {{-- Tipo de Carga --}}
                    <div>
                        <label for="primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                            Tipo de Carga <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="primary_cargo_type_id" id="primary_cargo_type_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_cargo_type_id') border-red-300 @enderror">
                            <option value="">Seleccionar tipo de carga</option>
                            @foreach($cargoTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('primary_cargo_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tipo de Empaque --}}
                    <div>
                        <label for="primary_packaging_type_id" class="block text-sm font-medium text-gray-700">
                            Tipo de Empaque <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="primary_packaging_type_id" id="primary_packaging_type_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('primary_packaging_type_id') border-red-300 @enderror">
                            <option value="">Seleccionar tipo de empaque</option>
                            @foreach($packagingTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('primary_packaging_type_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción de Carga --}}
                    <div class="sm:col-span-2">
                        <label for="cargo_description" class="block text-sm font-medium text-gray-700">
                            Descripción de la Carga <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model.live="cargo_description" id="cargo_description" rows="4" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('cargo_description') border-red-300 @enderror"
                                  placeholder="Describa detalladamente la mercancía..."></textarea>
                        @error('cargo_description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Total Paquetes --}}
                    <div>
                        <label for="total_packages" class="block text-sm font-medium text-gray-700">
                            Total Paquetes <span class="text-red-500">*</span>
                        </label>
                        <input wire:model.live="total_packages" type="number" id="total_packages" required min="1"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('total_packages') border-red-300 @enderror">
                        @error('total_packages')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Peso Bruto --}}
                    <div>
                        <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                            Peso Bruto (KG) <span class="text-red-500">*</span>
                        </label>
                        <input wire:model.live="gross_weight_kg" type="number" id="gross_weight_kg" required min="0.01" step="0.01"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('gross_weight_kg') border-red-300 @enderror">
                        @error('gross_weight_kg')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Peso Neto --}}
                    <div>
                        <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                            Peso Neto (KG)
                        </label>
                        <input wire:model.live="net_weight_kg" type="number" id="net_weight_kg" min="0" step="0.01"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('net_weight_kg') border-red-300 @enderror">
                        @error('net_weight_kg')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Volumen --}}
                    <div>
                        <label for="volume_m3" class="block text-sm font-medium text-gray-700">
                            Volumen (M³)
                        </label>
                        <input wire:model.live="volume_m3" type="number" id="volume_m3" min="0" step="0.001"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('volume_m3') border-red-300 @enderror">
                        @error('volume_m3')
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

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Mercancías Peligrosas --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="contains_dangerous_goods" id="contains_dangerous_goods" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="contains_dangerous_goods" class="font-medium text-gray-700">
                                Contiene Mercancías Peligrosas
                            </label>
                        </div>
                    </div>

                    {{-- Requiere Refrigeración --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="requires_refrigeration" id="requires_refrigeration" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="requires_refrigeration" class="font-medium text-gray-700">
                                Requiere Refrigeración
                            </label>
                        </div>
                    </div>

                    {{-- Es Prioritario --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="is_priority" id="is_priority" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_priority" class="font-medium text-gray-700">
                                Es Prioritario
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Consolidación --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Opciones de Consolidación</h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    {{-- Es Consolidado --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="is_consolidated" id="is_consolidated" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_consolidated" class="font-medium text-gray-700">
                                Es Consolidado
                            </label>
                        </div>
                    </div>

                    {{-- Es Master Bill --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="is_master_bill" id="is_master_bill" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_master_bill" class="font-medium text-gray-700">
                                Master Bill
                            </label>
                        </div>
                    </div>

                    {{-- Es House Bill --}}
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input wire:model.live="is_house_bill" id="is_house_bill" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_house_bill" class="font-medium text-gray-700">
                                House Bill
                            </label>
                        </div>
                    </div>
                </div>

                @if($is_house_bill)
                    <div class="mt-6">
                        <label for="master_bill_number" class="block text-sm font-medium text-gray-700">
                            Número de Master Bill <span class="text-red-500">*</span>
                        </label>
                        <input wire:model.live="master_bill_number" type="text" id="master_bill_number" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('master_bill_number') border-red-300 @enderror">
                        @error('master_bill_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
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
    
</DIV>