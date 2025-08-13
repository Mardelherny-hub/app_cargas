<div>
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
                <p class="mt-1 text-sm text-gray-600">Cargador, consignatario y otras partes</p>
            </div>

            {{-- Grid principal con los 4 buscadores --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Cargador/Exportador --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Cargador/Exportador <span class="text-red-500">*</span>
                        </label>
                        <button type="button" 
                                wire:click="openCreateModal('shipper')"
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
                                wire:click="openCreateModal('consignee')"
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

                {{-- Parte a Notificar --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Parte a Notificar
                        </label>
                        <button type="button" 
                                wire:click="openCreateModal('notify')"
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
                                wire:click="openCreateModal('cargo_owner')"
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
                        'placeholder' => 'Buscar propietario de carga...'
                    ], key('cargo-owner-search'))
                    @error('cargo_owner_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Direcciones Específicas --}}
            <div class="mt-6 space-y-4">
                {{-- Dirección específica del cargador --}}
                @if($shipper_id)
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700">
                            Dirección del Cargador para este Conocimiento
                        </label>
                        <label class="inline-flex items-center">
                            <input wire:model.live="shipper_use_specific" type="checkbox" 
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                        </label>
                    </div>
                    
                    @if($shipper_use_specific)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <input wire:model="shipper_address_1" type="text" placeholder="Dirección línea 1" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="shipper_address_2" type="text" placeholder="Dirección línea 2 (opcional)" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="shipper_city" type="text" placeholder="Ciudad" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="shipper_state" type="text" placeholder="Provincia/Estado" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="shipper_postal_code" type="text" placeholder="Código postal" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <select wire:model="shipper_country_id" 
                                    class="block w-full text-sm border-gray-300 rounded-md">
                                <option value="">Seleccionar país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Dirección específica del consignatario --}}
                @if($consignee_id)
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700">
                            Dirección del Consignatario para este Conocimiento
                        </label>
                        <label class="inline-flex items-center">
                            <input wire:model.live="consignee_use_specific" type="checkbox" 
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                        </label>
                    </div>
                    
                    @if($consignee_use_specific)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <input wire:model="consignee_address_1" type="text" placeholder="Dirección línea 1" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="consignee_address_2" type="text" placeholder="Dirección línea 2 (opcional)" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="consignee_city" type="text" placeholder="Ciudad" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="consignee_state" type="text" placeholder="Provincia/Estado" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="consignee_postal_code" type="text" placeholder="Código postal" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <select wire:model="consignee_country_id" 
                                    class="block w-full text-sm border-gray-300 rounded-md">
                                <option value="">Seleccionar país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Dirección específica de notificación --}}
                @if($notify_party_id)
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700">
                            Dirección de Notificación para este Conocimiento
                        </label>
                        <label class="inline-flex items-center">
                            <input wire:model.live="notify_use_specific" type="checkbox" 
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Usar dirección específica</span>
                        </label>
                    </div>
                    
                    @if($notify_use_specific)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <input wire:model="notify_address_1" type="text" placeholder="Dirección línea 1" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="notify_address_2" type="text" placeholder="Dirección línea 2 (opcional)" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="notify_city" type="text" placeholder="Ciudad" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="notify_state" type="text" placeholder="Provincia/Estado" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <input wire:model="notify_postal_code" type="text" placeholder="Código postal" 
                                   class="block w-full text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <select wire:model="notify_country_id" 
                                    class="block w-full text-sm border-gray-300 rounded-md">
                                <option value="">Seleccionar país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal para crear nuevo cliente --}}
    @if($showCreateModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelCreate"></div>

            {{-- Modal panel --}}
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form wire:submit="createClient">
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

                                    {{-- Datos adicionales --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Email</label>
                                            <input wire:model="new_email" type="email"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                            <input wire:model="new_phone" type="text"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Dirección</label>
                                        <input wire:model="new_address_1" type="text"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Ciudad</label>
                                        <input wire:model="new_city" type="text"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Crear Cliente
                        </button>
                        <button type="button" wire:click="cancelCreate"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Mensajes de feedback --}}
    @if (session()->has('message'))
        <div class="mt-4 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mt-4 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif
</div>