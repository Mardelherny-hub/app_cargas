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
                    <div class="relative flex h-8 w-8 items-center justify-center rounded-full {{ ($step >= 1 && $showBLSection) || (!$needsToCreateBL) ? 'bg-green-600' : 'bg-gray-400' }} text-white">
                        @if($blCreated || !$needsToCreateBL)
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <span class="text-xs font-medium">1</span>
                        @endif
                    </div>
                    <span class="absolute top-10 left-1/2 transform -translate-x-1/2 text-xs font-medium text-gray-500">
                        Conocimiento
                    </span>
                </li>

                <li class="relative">
                    <div class="relative flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 2 ? 'bg-blue-600' : 'bg-gray-400' }} text-white">
                        <span class="text-xs font-medium">2</span>
                    </div>
                    <span class="absolute top-10 left-1/2 transform -translate-x-1/2 text-xs font-medium text-gray-500">
                        Carga
                    </span>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Información del contexto --}}
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Información del Envío</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p><strong>Viaje:</strong> {{ $shipment->voyage->voyage_number }}</p>
                    <p><strong>Envío:</strong> {{ $shipment->shipment_number }}</p>
                    @if($billOfLading)
                        <p><strong>Conocimiento:</strong> {{ $billOfLading->bill_number }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 1: Configuración del Bill of Lading --}}
    @if($showBLSection && $step === 1)
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-6 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Configuración del Conocimiento de Embarque</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        Este envío no tiene un conocimiento de embarque asociado. Complete los datos básicos para crearlo automáticamente.
                    </p>
                </div>
            </div>

            <form wire:submit="createBillOfLading" class="mt-6 space-y-6">
                {{-- Partes Involucradas --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Partes Involucradas</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="bl_shipper_id" class="block text-sm font-medium text-gray-700">
                                Cargador/Exportador <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_shipper_id" id="bl_shipper_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar cargador</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->legal_name }} - {{ $client->tax_id }}</option>
                                @endforeach
                            </select>
                            @error('bl_shipper_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        {{-- Dirección específica del cargador --}}
                        @if($bl_shipper_id)
                        <div class="mt-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Dirección del Cargador para este Conocimiento
                                </label>
                                <label class="inline-flex items-center">
                                    <input wire:model="bl_shipper_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                                </label>
                            </div>
                            
                            @if($bl_shipper_use_specific ?? false)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <input wire:model="bl_shipper_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_shipper_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_shipper_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_shipper_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <div>
                            <label for="bl_consignee_id" class="block text-sm font-medium text-gray-700">
                                Consignatario/Importador <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_consignee_id" id="bl_consignee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar consignatario</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->legal_name }} - {{ $client->tax_id }}</option>
                                @endforeach
                            </select>
                            @error('bl_consignee_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dirección específica del consignatario --}}
                        @if($bl_consignee_id)
                        <div class="mt-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Dirección del Consignatario para este Conocimiento
                                </label>
                                <label class="inline-flex items-center">
                                    <input wire:model="bl_consignee_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                                </label>
                            </div>
                            
                            @if($bl_consignee_use_specific ?? false)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <input wire:model="bl_consignee_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_consignee_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_consignee_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <input wire:model="bl_consignee_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <div>
                            <label for="bl_notify_party_id" class="block text-sm font-medium text-gray-700">
                                Notificar a
                            </label>
                            <select wire:model="bl_notify_party_id" id="bl_notify_party_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Sin notificación</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->legal_name }} - {{ $client->tax_id }}</option>
                                @endforeach
                            </select>
                            @error('bl_notify_party_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dirección específica de notificación --}}
                @if($bl_notify_party_id)
                <div class="mt-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Dirección de Notificación para este Conocimiento
                        </label>
                        <label class="inline-flex items-center">
                            <input wire:model="bl_notify_use_specific" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                        </label>
                    </div>
                    
                    @if($bl_notify_use_specific ?? false)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <input wire:model="bl_notify_address_1" type="text" placeholder="Dirección línea 1" class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="bl_notify_address_2" type="text" placeholder="Dirección línea 2 (opcional)" class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="bl_notify_city" type="text" placeholder="Ciudad" class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="bl_notify_state" type="text" placeholder="Provincia/Estado" class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    @endif
                </div>
                @endif
                    </div>
                </div>

                {{-- Puertos y Ubicaciones --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Puertos y Ubicaciones</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="bl_loading_port_id" class="block text-sm font-medium text-gray-700">
                                Puerto de Carga <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_loading_port_id" id="bl_loading_port_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar puerto</option>
                                @foreach($ports as $port)
                                    <option value="{{ $port->id }}">{{ $port->name }} - {{ $port->country->name ?? '' }}</option>
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
                                    <option value="{{ $port->id }}">{{ $port->name }} - {{ $port->country->name ?? '' }}</option>
                                @endforeach
                            </select>
                            @error('bl_discharge_port_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Información Básica --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

                {{-- Tipos de Carga --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Tipos de Carga y Embalaje</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="bl_primary_cargo_type_id" class="block text-sm font-medium text-gray-700">
                                Tipo Principal de Carga <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_primary_cargo_type_id" id="bl_primary_cargo_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar tipo</option>
                                @foreach($cargoTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('bl_primary_cargo_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="bl_primary_packaging_type_id" class="block text-sm font-medium text-gray-700">
                                Tipo Principal de Embalaje <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_primary_packaging_type_id" id="bl_primary_packaging_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar tipo</option>
                                @foreach($packagingTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('bl_primary_packaging_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Términos Comerciales --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Términos Comerciales</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="bl_freight_terms" class="block text-sm font-medium text-gray-700">
                                Términos de Flete <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="bl_freight_terms" id="bl_freight_terms" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="prepaid">Prepagado</option>
                                <option value="collect">Por Cobrar</option>
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
                                <option value="cash">Efectivo</option>
                                <option value="credit">Crédito</option>
                                <option value="advance">Adelantado</option>
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

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg wire:loading wire:target="createBillOfLading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="createBillOfLading">Crear Conocimiento de Embarque</span>
                        <span wire:loading wire:target="createBillOfLading">Creando...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- STEP 2: Crear Item de Carga --}}
    @if($step === 2)
        <div class="bg-white border border-gray-200 rounded-md p-6">
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

                {{-- Cliente Dueño de la Mercadería --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Cliente Dueño de la Mercadería</h4>
                    
                    @if($selectedClientId)
                        {{-- Cliente seleccionado --}}
                        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md">
                            <div>
                                <p class="text-sm font-medium text-green-800">Cliente seleccionado:</p>
                                <p class="text-sm text-green-700">{{ $selectedClientName }}</p>
                            </div>
                            <button type="button" wire:click="clearSelectedClient" class="text-green-600 hover:text-green-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @else
                        {{-- Búsqueda de cliente --}}
                        <div class="space-y-3">
                            <div class="flex space-x-2">
                                <div class="flex-1">
                                    <label for="searchClient" class="block text-sm font-medium text-gray-700">
                                        Buscar Cliente <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model.live="searchClient" 
                                        type="text" 
                                        id="searchClient"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                        placeholder="Escriba nombre o CUIT del cliente...">
                                </div>
                                <div class="flex items-end">
                                    <a href="{{ route('company.clients.create') }}" 
                                    target="_blank"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Crear
                                    </a>
                                </div>
                            </div>
                            
                            {{-- Resultados de búsqueda --}}
                            @if($searchClient && $this->filteredClients->count() > 0)
                                <div class="border border-gray-200 rounded-md max-h-40 overflow-y-auto">
                                    @foreach($this->filteredClients as $client)
                                        <div wire:click="selectClient({{ $client->id }})" 
                                            class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0">
                                            <p class="text-sm font-medium text-gray-900">{{ $client->legal_name }}</p>
                                            <p class="text-xs text-gray-500">{{ $client->tax_id }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($searchClient && strlen($searchClient) >= 2)
                                <p class="text-sm text-gray-500 italic">No se encontraron clientes</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Clasificación --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Clasificación</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="cargo_type_id" class="block text-sm font-medium text-gray-700">
                                Tipo de Carga <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="cargo_type_id" id="cargo_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar tipo</option>
                                @foreach($cargoTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
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
                                @foreach($packagingTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('packaging_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>                        
                    </div>
                </div>
                {{-- Sección Contenedor (Condicional) --}}
                @if($showContainerFields)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-lg font-medium text-blue-900">Datos del Contenedor</h4>
                            <p class="text-sm text-blue-700">Configure el contenedor físico para esta carga</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Número de Contenedor --}}
                        <div>
                            <label for="container_number" class="block text-sm font-medium text-gray-700">
                                Número de Contenedor <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="container_number" 
                                type="text" 
                                id="container_number" 
                                maxlength="15"
                                placeholder="MSKU1234567"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('container_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Tipo de Contenedor --}}
                        <div>
                            <label for="container_type_id" class="block text-sm font-medium text-gray-700">
                                Tipo de Contenedor <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="container_type_id" 
                                    id="container_type_id" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar tipo</option>
                                @foreach($containerTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->code }} - {{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('container_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Precinto --}}
                        <div>
                            <label for="seal_number" class="block text-sm font-medium text-gray-700">
                                Número de Precinto
                            </label>
                            <input wire:model="seal_number" 
                                type="text" 
                                id="seal_number" 
                                placeholder="SL123456"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        {{-- Tara --}}
                        <div>
                            <label for="tare_weight" class="block text-sm font-medium text-gray-700">
                                Tara del Contenedor (kg)
                            </label>
                            <input wire:model="tare_weight" 
                                type="number" 
                                id="tare_weight" 
                                step="0.01"
                                min="0"
                                placeholder="2200.00"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-blue-100 rounded-md">
                        <p class="text-sm text-blue-800">
                            ℹ️ <strong>Automático:</strong> Al guardar este item, se creará automáticamente el contenedor físico.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Cantidades y Medidas --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Cantidades y Medidas</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="package_quantity" class="block text-sm font-medium text-gray-700">
                                Cantidad de Bultos <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="package_quantity" type="number" id="package_quantity" min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('package_quantity')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="gross_weight_kg" class="block text-sm font-medium text-gray-700">
                                Peso Bruto (kg) <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="gross_weight_kg" type="number" step="0.01" id="gross_weight_kg" min="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('gross_weight_kg')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="net_weight_kg" class="block text-sm font-medium text-gray-700">
                                Peso Neto (kg)
                            </label>
                            <input wire:model="net_weight_kg" type="number" step="0.01" id="net_weight_kg" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('net_weight_kg')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="volume_m3" class="block text-sm font-medium text-gray-700">
                                Volumen (m³)
                            </label>
                            <input wire:model="volume_m3" type="number" step="0.001" id="volume_m3" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('volume_m3')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Información Adicional --}}
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Información Adicional</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="declared_value" class="block text-sm font-medium text-gray-700">
                                Valor Declarado
                            </label>
                            <input wire:model="declared_value" type="number" step="0.01" id="declared_value" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('declared_value')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="country_of_origin" class="block text-sm font-medium text-gray-700">
                                País de Origen <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="country_of_origin" id="country_of_origin" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->iso_code }}">{{ $country->name }} ({{ $country->iso_code }})</option>
                                @endforeach
                            </select>
                            @error('country_of_origin')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="hs_code" class="block text-sm font-medium text-gray-700">
                                Código HS/NCM
                            </label>
                            <input wire:model="hs_code" type="text" id="hs_code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="1234.56.78">
                            @error('hs_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Marcas y Observaciones --}}
                <div>
                    <label for="cargo_marks" class="block text-sm font-medium text-gray-700">
                        Marcas de la Mercadería
                    </label>
                    <textarea wire:model="cargo_marks" id="cargo_marks" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Marcas, números y señales de identificación..."></textarea>
                    @error('cargo_marks')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Botones de acción MEJORADOS --}}
            <<div class="flex justify-between">
    <a href="{{ route('company.shipments.show', $shipment) }}" 
       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Cancelar
    </a>
    
    <div class="flex space-x-3">
        {{-- Botón: Agregar Item (submit normal) --}}
        <button type="submit" 
                wire:click="$set('continueAdding', true)"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
            <span wire:loading.remove wire:target="createShipmentItem">Agregar Item</span>
            <span wire:loading wire:target="createShipmentItem">Agregando...</span>
        </button>

        {{-- Botón: Terminar (NO submit, link directo) --}}
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
    @endif
</div>