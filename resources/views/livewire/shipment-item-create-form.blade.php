<div>
    {{-- Mensajes flash --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-800">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Progress indicator --}}
    <div class="mb-6">
        <nav aria-label="Progress">
            <ol role="list" class="flex items-center">
                <li class="relative {{ $step >= 1 ? 'pr-8 sm:pr-20' : '' }}">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        @if($step > 1 || !$needsToCreateBL)
                            <div class="h-0.5 w-full bg-green-600"></div>
                        @else
                            <div class="h-0.5 w-full bg-gray-200"></div>
                        @endif
                    </div>
                    <div class="relative flex h-8 w-8 items-center justify-center rounded-full {{ ($step >= 1 && $showBLSection) || (!$needsToCreateBL) ? 'bg-green-600' : ($step == 1 ? 'bg-blue-600' : 'bg-gray-300') }}">
                        @if($step > 1 || !$needsToCreateBL)
                            <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <span class="text-sm font-medium text-white">1</span>
                        @endif
                    </div>
                    @if($step >= 1)
                        <span class="ml-4 min-w-0 flex flex-col">
                            <span class="text-sm font-medium">Bill of Lading</span>
                            <span class="text-sm text-gray-500">{{ $needsToCreateBL ? 'Configurar' : 'Configurado' }}</span>
                        </span>
                    @endif
                </li>

                <li class="relative">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step == 2 ? 'bg-blue-600' : 'bg-gray-300' }}">
                        <span class="text-sm font-medium text-white">2</span>
                    </div>
                    <span class="ml-4 min-w-0 flex flex-col">
                        <span class="text-sm font-medium">Agregar Items</span>
                        <span class="text-sm text-gray-500">Cargas al conocimiento</span>
                    </span>
                </li>
            </ol>
        </nav>
    </div>

    {{-- STEP 1: Configuración del Bill of Lading --}}
    @if($step == 1 && $needsToCreateBL)
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Configurar Bill of Lading</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Complete los datos básicos para crearlo automáticamente.
                    </p>
                </div>

                <form wire:submit="createBillOfLading" class="mt-6 space-y-6">
                    {{-- Partes Involucradas --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Partes Involucradas</h4>
                        
                        {{-- Grid con los 3 selectores MODIFICADOS --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- CARGADOR/EXPORTADOR - NUEVO BUSCADOR --}}
                            <div>
                                <label for="shipper_search" class="block text-sm font-medium text-gray-700">
                                    Cargador/Exportador <span class="text-red-500">*</span>
                                </label>
                                <div class="relative mt-1">
                                    <input 
                                        wire:model.live.debounce.300ms="shipperSearch" 
                                        type="text" 
                                        id="shipper_search"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 pr-10"
                                        placeholder="Buscar por nombre o CUIT..."
                                        autocomplete="off"
                                    >
                                    <button 
                                        type="button"
                                        wire:click="openClientModal('bl_shipper_id')"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-blue-600 hover:text-blue-800"
                                        title="Crear nuevo cliente"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                {{-- Dropdown de resultados --}}
                                @if(count($filteredShippers) > 0)
                                    <div class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        @foreach($filteredShippers as $client)
                                            <div 
                                                wire:click="selectShipper({{ $client['id'] }})"
                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                            >
                                                <div class="flex items-center">
                                                    <span class="font-normal block truncate">{{ $client['legal_name'] }}</span>
                                                    <span class="text-gray-500 ml-2 text-sm">{{ $client['tax_id'] }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Mostrar seleccionado --}}
                                @if($bl_shipper_id)
                                    @php 
                                        $selectedShipper = $clients->find($bl_shipper_id);
                                    @endphp
                                    @if($selectedShipper)
                                        <div class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $selectedShipper->legal_name }} - {{ $selectedShipper->tax_id }}
                                        </div>
                                    @endif
                                @endif
                                
                                @error('bl_shipper_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- CONSIGNATARIO/IMPORTADOR - NUEVO BUSCADOR --}}
                            <div>
                                <label for="consignee_search" class="block text-sm font-medium text-gray-700">
                                    Consignatario/Importador <span class="text-red-500">*</span>
                                </label>
                                <div class="relative mt-1">
                                    <input 
                                        wire:model.live.debounce.300ms="consigneeSearch" 
                                        type="text" 
                                        id="consignee_search"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 pr-10"
                                        placeholder="Buscar por nombre o CUIT..."
                                        autocomplete="off"
                                    >
                                    <button 
                                        type="button"
                                        wire:click="openClientModal('bl_consignee_id')"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-blue-600 hover:text-blue-800"
                                        title="Crear nuevo cliente"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                {{-- Dropdown de resultados --}}
                                @if(count($filteredConsignees) > 0)
                                    <div class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        @foreach($filteredConsignees as $client)
                                            <div 
                                                wire:click="selectConsignee({{ $client['id'] }})"
                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                            >
                                                <div class="flex items-center">
                                                    <span class="font-normal block truncate">{{ $client['legal_name'] }}</span>
                                                    <span class="text-gray-500 ml-2 text-sm">{{ $client['tax_id'] }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Mostrar seleccionado --}}
                                @if($bl_consignee_id)
                                    @php 
                                        $selectedConsignee = $clients->find($bl_consignee_id);
                                    @endphp
                                    @if($selectedConsignee)
                                        <div class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $selectedConsignee->legal_name }} - {{ $selectedConsignee->tax_id }}
                                        </div>
                                    @endif
                                @endif
                                
                                @error('bl_consignee_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- NOTIFICAR A - NUEVO BUSCADOR --}}
                            <div>
                                <label for="notify_search" class="block text-sm font-medium text-gray-700">
                                    Notificar a (Opcional)
                                </label>
                                <div class="relative mt-1">
                                    <input 
                                        wire:model.live.debounce.300ms="notifyPartySearch" 
                                        type="text" 
                                        id="notify_search"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 pr-10"
                                        placeholder="Buscar por nombre o CUIT..."
                                        autocomplete="off"
                                    >
                                    <button 
                                        type="button"
                                        wire:click="openClientModal('bl_notify_party_id')"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-blue-600 hover:text-blue-800"
                                        title="Crear nuevo cliente"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                {{-- Dropdown de resultados --}}
                                @if(count($filteredNotifyParties) > 0)
                                    <div class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        @foreach($filteredNotifyParties as $client)
                                            <div 
                                                wire:click="selectNotifyParty({{ $client['id'] }})"
                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                            >
                                                <div class="flex items-center">
                                                    <span class="font-normal block truncate">{{ $client['legal_name'] }}</span>
                                                    <span class="text-gray-500 ml-2 text-sm">{{ $client['tax_id'] }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Mostrar seleccionado --}}
                                @if($bl_notify_party_id)
                                    @php 
                                        $selectedNotify = $clients->find($bl_notify_party_id);
                                    @endphp
                                    @if($selectedNotify)
                                        <div class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $selectedNotify->legal_name }} - {{ $selectedNotify->tax_id }}
                                        </div>
                                    @endif
                                @endif
                                
                                @error('bl_notify_party_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- SECCIONES DE DIRECCIONES ESPECÍFICAS --}}
                        {{-- Dirección específica del cargador --}}
                        @if($bl_shipper_id)
                            <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Dirección del Cargador para este Conocimiento
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input wire:model.live="bl_shipper_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                                    </label>
                                </div>
                                
                                @if($bl_shipper_use_specific)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <input wire:model="bl_shipper_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_shipper_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_shipper_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_shipper_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Dirección específica del consignatario --}}
                        @if($bl_consignee_id)
                            <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Dirección del Consignatario para este Conocimiento
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input wire:model.live="bl_consignee_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                                    </label>
                                </div>
                                
                                @if($bl_consignee_use_specific)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <input wire:model="bl_consignee_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_consignee_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_consignee_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_consignee_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Dirección específica del notify party --}}
                        @if($bl_notify_party_id)
                            <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Dirección de Notificación para este Conocimiento
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input wire:model.live="bl_notify_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                                    </label>
                                </div>
                                
                                @if($bl_notify_use_specific)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <input wire:model="bl_notify_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_notify_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_notify_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <input wire:model="bl_notify_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Resto de campos del BL (SIN MODIFICAR) --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Datos del Transporte</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bl_loading_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Carga <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_loading_port_id" id="bl_loading_port_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar puerto</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}">{{ $port->name }} ({{ $port->country->name }})</option>
                                    @endforeach
                                </select>
                                @error('bl_loading_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_discharge_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Descarga <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_discharge_port_id" id="bl_discharge_port_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar puerto</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}">{{ $port->name }} ({{ $port->country->name }})</option>
                                    @endforeach
                                </select>
                                @error('bl_discharge_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Datos del Conocimiento</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="bl_bill_number" class="block text-sm font-medium text-gray-700">
                                    Número de Conocimiento <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="bl_bill_number" type="text" id="bl_bill_number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('bl_bill_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_bill_date" class="block text-sm font-medium text-gray-700">
                                    Fecha del Conocimiento <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="bl_bill_date" type="date" id="bl_bill_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('bl_bill_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_loading_date" class="block text-sm font-medium text-gray-700">
                                    Fecha de Carga <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="bl_loading_date" type="date" id="bl_loading_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('bl_loading_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Tipos de Carga</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bl_primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Carga Principal <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_primary_cargo_type_id" id="bl_primary_cargo_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($cargoTypes as $cargoType)
                                        <option value="{{ $cargoType->id }}">{{ $cargoType->name }}</option>
                                    @endforeach
                                </select>
                                @error('bl_primary_cargo_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_primary_packaging_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Embalaje Principal <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_primary_packaging_type_id" id="bl_primary_packaging_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($packagingTypes as $packagingType)
                                        <option value="{{ $packagingType->id }}">{{ $packagingType->name }}</option>
                                    @endforeach
                                </select>
                                @error('bl_primary_packaging_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Términos Comerciales</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="bl_freight_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Flete <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_freight_terms" id="bl_freight_terms" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="prepaid">Prepaid (Pagado)</option>
                                    <option value="collect">Collect (Por cobrar)</option>
                                </select>
                                @error('bl_freight_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_payment_terms" class="block text-sm font-medium text-gray-700">
                                    Términos de Pago <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_payment_terms" id="bl_payment_terms" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="cash">Cash (Efectivo)</option>
                                    <option value="credit">Credit (Crédito)</option>
                                    <option value="advance">Advance (Adelanto)</option>
                                </select>
                                @error('bl_payment_terms')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bl_currency_code" class="block text-sm font-medium text-gray-700">
                                    Moneda <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="bl_currency_code" id="bl_currency_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="USD">USD - Dólar Estadounidense</option>
                                    <option value="ARS">ARS - Peso Argentino</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                                @error('bl_currency_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" wire:click="goToStep(2)" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Omitir BL
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                            <span wire:loading.remove wire:target="createBillOfLading">Crear Bill of Lading</span>
                            <span wire:loading wire:target="createBillOfLading">Creando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- STEP 2: Agregar Items (SIN MODIFICAR) --}}
    @if($step == 2)
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-6">Agregar Carga</h3>

                <form wire:submit="createShipmentItem" class="space-y-6">
                    {{-- Identificación del Item --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Identificación del Item</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="item_reference" class="block text-sm font-medium text-gray-700">
                                    Referencia del Item <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="item_reference" type="text" id="item_reference" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="SOJA-2025-001">
                                @error('item_reference')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Número de Línea
                                </label>
                                <input type="text" value="{{ $nextLineNumber }}" disabled class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50 text-gray-500">
                                <p class="mt-1 text-xs text-gray-500">Asignado automáticamente</p>
                            </div>
                        </div>
                    </div>

                    {{-- Descripción --}}
                    <div>
                        <label for="item_description" class="block text-sm font-medium text-gray-700">
                            Descripción del Item <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model="item_description" id="item_description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Descripción detallada de la mercadería..."></textarea>
                        @error('item_description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Clasificación de la Carga --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Clasificación de la Carga</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="cargo_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Carga <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="cargo_type_id" id="cargo_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($cargoTypes as $cargoType)
                                        <option value="{{ $cargoType->id }}">{{ $cargoType->name }}</option>
                                    @endforeach
                                </select>
                                @error('cargo_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="packaging_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Embalaje <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="packaging_type_id" id="packaging_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($packagingTypes as $packagingType)
                                        <option value="{{ $packagingType->id }}">{{ $packagingType->name }}</option>
                                    @endforeach
                                </select>
                                @error('packaging_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Campos de Contenedor (si es necesario) --}}
                    {{-- SECCIÓN DE CONTENEDORES MODIFICADA --}}
@if($showContainerFields)
    <div>
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-medium text-gray-900">Información de Contenedores</h4>
            <button type="button" wire:click="addContainer" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Agregar Contenedor
            </button>
        </div>

        {{-- Lista de contenedores --}}
        @if(!empty($containers))
            <div class="space-y-4">
                @foreach($containers as $index => $container)
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50" wire:key="container-{{ $container['id'] }}">
                        <div class="flex justify-between items-center mb-3">
                            <h5 class="text-md font-medium text-gray-900">Contenedor {{ $index + 1 }}</h5>
                            @if(count($containers) > 1)
                                <button type="button" wire:click="removeContainer({{ $index }})" class="text-red-600 hover:text-red-800">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {{-- Número de Contenedor --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Número de Contenedor <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    wire:model="containers.{{ $index }}.container_number" 
                                    type="text" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="MSCU1234567"
                                >
                                @error("container_{$index}")
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Tipo de Contenedor --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Tipo de Contenedor <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    wire:model="containers.{{ $index }}.container_type_id" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($containerTypes as $containerType)
                                        <option value="{{ $containerType->id }}">{{ $containerType->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Número de Precinto --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Número de Precinto
                                </label>
                                <input 
                                    wire:model="containers.{{ $index }}.seal_number" 
                                    type="text" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="SL123456"
                                >
                            </div>

                            {{-- Peso de Tara --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Peso de Tara (kg)
                                </label>
                                <input 
                                    wire:model="containers.{{ $index }}.tare_weight" 
                                    type="number" 
                                    step="0.01" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="2300"
                                >
                            </div>
                        </div>

                        {{-- Distribución de la carga en este contenedor --}}
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h6 class="text-sm font-medium text-gray-700 mb-3">Distribución de Carga en este Contenedor</h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                {{-- Cantidad de Bultos --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Cantidad de Bultos <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.package_quantity" 
                                        type="number" 
                                        min="1" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="20"
                                    >
                                </div>

                                {{-- Peso Bruto --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Peso Bruto (kg) <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.gross_weight_kg" 
                                        type="number" 
                                        step="0.01" 
                                        min="0.01" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="1000.00"
                                    >
                                </div>

                                {{-- Peso Neto --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Peso Neto (kg)
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.net_weight_kg" 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="990.00"
                                    >
                                </div>

                                {{-- Volumen --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Volumen (m³)
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.volume_m3" 
                                        type="number" 
                                        step="0.001" 
                                        min="0" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="1.250"
                                    >
                                </div>
                            </div>

                            {{-- Campos adicionales --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                {{-- Secuencia de Carga --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Secuencia de Carga
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.loading_sequence" 
                                        type="text" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="A1, B2, etc."
                                    >
                                    <p class="mt-1 text-xs text-gray-500">Orden o posición en el contenedor</p>
                                </div>

                                {{-- Notas --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Notas
                                    </label>
                                    <input 
                                        wire:model="containers.{{ $index }}.notes" 
                                        type="text" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="Notas adicionales..."
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Validación de Totales --}}
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <h6 class="text-sm font-medium text-blue-900 mb-2">Validación de Totales</h6>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-blue-700">Bultos Contenedores:</span>
                        <span class="font-medium">
                            {{ collect($containers)->sum('package_quantity') }} / {{ $package_quantity }}
                        </span>
                        @if(collect($containers)->sum('package_quantity') != $package_quantity)
                            <span class="text-red-600 ml-1">⚠️</span>
                        @else
                            <span class="text-green-600 ml-1">✓</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-blue-700">Peso Bruto:</span>
                        <span class="font-medium">
                            {{ number_format(collect($containers)->sum('gross_weight_kg'), 2) }} / {{ number_format($gross_weight_kg ?: 0, 2) }} kg
                        </span>
                        @if(abs(collect($containers)->sum('gross_weight_kg') - ($gross_weight_kg ?: 0)) > 0.01)
                            <span class="text-red-600 ml-1">⚠️</span>
                        @else
                            <span class="text-green-600 ml-1">✓</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-blue-700">Peso Neto:</span>
                        <span class="font-medium">
                            {{ number_format(collect($containers)->sum('net_weight_kg'), 2) }} / {{ number_format($net_weight_kg ?: 0, 2) }} kg
                        </span>
                    </div>
                    <div>
                        <span class="text-blue-700">Volumen:</span>
                        <span class="font-medium">
                            {{ number_format(collect($containers)->sum('volume_m3'), 3) }} / {{ number_format($volume_m3 ?: 0, 3) }} m³
                        </span>
                    </div>
                </div>
                
                @error('containers_total')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

        @else
            {{-- Botón para agregar primer contenedor --}}
            <div class="text-center py-6 border-2 border-dashed border-gray-300 rounded-lg">
                <p class="text-gray-500 mb-3">No se han agregado contenedores</p>
                <button type="button" wire:click="addContainer" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Agregar Primer Contenedor
                </button>
            </div>
        @endif
    </div>
@endif

                    {{-- Cantidades y Medidas --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Cantidades y Medidas</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <div>
                                <label for="package_quantity" class="block text-sm font-medium text-gray-700">
                                    Cantidad de Bultos <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="package_quantity" type="number" min="1" id="package_quantity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="40">
                                @error('package_quantity')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Bruto (kg) <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="gross_weight_kg" type="number" step="0.01" min="0.01" id="gross_weight_kg" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="2000.00">
                                @error('gross_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                                    Peso Neto (kg)
                                </label>
                                <input wire:model="net_weight_kg" type="number" step="0.01" min="0" id="net_weight_kg" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="1980.00">
                                @error('net_weight_kg')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="volume_m3" class="block text-sm font-medium text-gray-700">
                                    Volumen (m³)
                                </label>
                                <input wire:model="volume_m3" type="number" step="0.001" min="0" id="volume_m3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="2.500">
                                @error('volume_m3')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="declared_value" class="block text-sm font-medium text-gray-700">
                                    Valor Declarado (USD)
                                </label>
                                <input wire:model="declared_value" type="number" step="0.01" min="0" id="declared_value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="1200.00">
                                @error('declared_value')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Origen y Clasificación --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Origen y Clasificación</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="country_of_origin" class="block text-sm font-medium text-gray-700">
                                    País de Origen <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="country_of_origin" id="country_of_origin" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccionar país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->alpha2_code }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('country_of_origin')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="hs_code" class="block text-sm font-medium text-gray-700">
                                    Código HS
                                </label>
                                <input wire:model="hs_code" type="text" id="hs_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="120100">
                                @error('hs_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="commodity_code" class="block text-sm font-medium text-gray-700">
                                    Código de Mercadería
                                </label>
                                <input wire:model="commodity_code" type="text" id="commodity_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="SOJA001">
                                @error('commodity_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="brand_model" class="block text-sm font-medium text-gray-700">
                                    Marca/Modelo
                                </label>
                                <input wire:model="brand_model" type="text" id="brand_model" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="SOJA ARGENTINA">
                                @error('brand_model')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Marcas y Embalaje --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Marcas y Embalaje</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                                    Marcas de la Mercadería
                                </label>
                                <textarea wire:model="cargo_marks" id="cargo_marks" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="SOJA ARG 2025 | HANDLE WITH CARE | KEEP DRY"></textarea>
                                @error('cargo_marks')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="package_description" class="block text-sm font-medium text-gray-700">
                                    Descripción del Embalaje
                                </label>
                                <textarea wire:model="package_description" id="package_description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Sacos de polipropileno de 50kg"></textarea>
                                @error('package_description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Información Adicional --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Información Adicional</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="lot_number" class="block text-sm font-medium text-gray-700">
                                    Número de Lote
                                </label>
                                <input wire:model="lot_number" type="text" id="lot_number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="LT240801">
                                @error('lot_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expiry_date" class="block text-sm font-medium text-gray-700">
                                    Fecha de Vencimiento
                                </label>
                                <input wire:model="expiry_date" type="date" id="expiry_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('expiry_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Características Especiales --}}
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Características Especiales</h4>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input wire:model="is_dangerous_goods" type="checkbox" id="is_dangerous_goods" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="is_dangerous_goods" class="ml-2 text-sm text-gray-700">Mercancía peligrosa</label>
                            </div>

                            <div class="flex items-center">
                                <input wire:model="is_perishable" type="checkbox" id="is_perishable" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="is_perishable" class="ml-2 text-sm text-gray-700">Mercancía perecedera</label>
                            </div>

                            <div class="flex items-center">
                                <input wire:model="requires_refrigeration" type="checkbox" id="requires_refrigeration" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="requires_refrigeration" class="ml-2 text-sm text-gray-700">Requiere refrigeración</label>
                            </div>
                        </div>
                    </div>

                    {{-- Instrucciones Especiales --}}
                    <div>
                        <label for="special_instructions" class="block text-sm font-medium text-gray-700">
                            Instrucciones Especiales
                        </label>
                        <textarea wire:model="special_instructions" id="special_instructions" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Instrucciones especiales para el manejo de la carga..."></textarea>
                        @error('special_instructions')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Botones de acción --}}
                    <div class="flex justify-between">
                        <a href="{{ route('company.shipments.show', $shipment) }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Cancelar
                        </a>
                        
                        <div class="flex space-x-3">
                            <button type="submit" 
                                    wire:click="$set('continueAdding', true)"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                <span wire:loading.remove wire:target="createShipmentItem">Agregar Item</span>
                                <span wire:loading wire:target="createShipmentItem">Agregando...</span>
                            </button>

                            <a href="{{ route('company.shipments.show', $shipment) }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Terminar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL DE CREACIÓN RÁPIDA DE CLIENTES --}}
    @if($showClientModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeClientModal"></div>

                {{-- Modal --}}
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="createQuickClient">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Crear Cliente Rápido
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Complete los campos obligatorios para crear el cliente.
                                    </p>
                                    
                                    <div class="mt-4 space-y-4">
                                        {{-- País --}}
                                        <div>
                                            <label for="modal_country_id" class="block text-sm font-medium text-gray-700">
                                                País <span class="text-red-500">*</span>
                                            </label>
                                            <select wire:model.live="modal_country_id" id="modal_country_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Seleccionar país</option>
                                                @foreach($countries as $country)
                                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('modal_country_id')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Tipo de Documento --}}
                                        @if($modal_country_id && count($availableDocumentTypes) > 0)
                                            <div>
                                                <label for="modal_document_type_id" class="block text-sm font-medium text-gray-700">
                                                    Tipo de Documento <span class="text-red-500">*</span>
                                                </label>
                                                <select wire:model="modal_document_type_id" id="modal_document_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Seleccionar tipo</option>
                                                    @foreach($availableDocumentTypes as $docType)
                                                        <option value="{{ $docType['id'] }}">{{ $docType['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                @error('modal_document_type_id')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif

                                        {{-- CUIT/RUC --}}
                                        <div>
                                            <label for="modal_tax_id" class="block text-sm font-medium text-gray-700">
                                                CUIT/RUC <span class="text-red-500">*</span>
                                            </label>
                                            <input wire:model="modal_tax_id" type="text" id="modal_tax_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="20-12345678-9">
                                            @error('modal_tax_id')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Razón Social --}}
                                        <div>
                                            <label for="modal_legal_name" class="block text-sm font-medium text-gray-700">
                                                Razón Social <span class="text-red-500">*</span>
                                            </label>
                                            <input wire:model="modal_legal_name" type="text" id="modal_legal_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="EMPRESA S.A.">
                                            @error('modal_legal_name')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Nombre Comercial (Opcional) --}}
                                        <div>
                                            <label for="modal_commercial_name" class="block text-sm font-medium text-gray-700">
                                                Nombre Comercial
                                            </label>
                                            <input wire:model="modal_commercial_name" type="text" id="modal_commercial_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Nombre comercial">
                                            @error('modal_commercial_name')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Dirección (Opcional) --}}
                                        <div>
                                            <label for="modal_address" class="block text-sm font-medium text-gray-700">
                                                Dirección
                                            </label>
                                            <input wire:model="modal_address" type="text" id="modal_address" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Dirección completa">
                                            @error('modal_address')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Teléfono y Email en una fila --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="modal_phone" class="block text-sm font-medium text-gray-700">
                                                    Teléfono
                                                </label>
                                                <input wire:model="modal_phone" type="text" id="modal_phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="+54 11 1234-5678">
                                                @error('modal_phone')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="modal_email" class="block text-sm font-medium text-gray-700">
                                                    Email
                                                </label>
                                                <input wire:model="modal_email" type="email" id="modal_email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="email@empresa.com">
                                                @error('modal_email')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                        {{-- Checkbox para usar dirección específica en el BL --}}
                                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                            <label class="inline-flex items-center">
                                                <input wire:model.live="modal_use_specific_address" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                                <span class="ml-2 text-sm text-gray-700 font-medium">Usar dirección específica para este Bill of Lading</span>
                                            </label>
                                            <p class="text-xs text-gray-600 mt-1">Si está marcado, se usará una dirección específica para este conocimiento en lugar de la dirección base del cliente.</p>
                                        </div>

                                        {{-- Campos adicionales si usa dirección específica --}}
                                        @if($modal_use_specific_address ?? false)
                                            <div class="space-y-3 p-3 bg-gray-50 border border-gray-200 rounded-md">
                                                <p class="text-sm font-medium text-gray-700">Dirección específica para este Bill of Lading:</p>
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div>
                                                        <input wire:model="modal_specific_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div>
                                                        <input wire:model="modal_specific_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div>
                                                        <input wire:model="modal_specific_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div>
                                                        <input wire:model="modal_specific_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Botones del modal --}}
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                <span wire:loading.remove wire:target="createQuickClient">Crear Cliente</span>
                                <span wire:loading wire:target="createQuickClient">Creando...</span>
                            </button>
                            <button type="button" wire:click="closeClientModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>