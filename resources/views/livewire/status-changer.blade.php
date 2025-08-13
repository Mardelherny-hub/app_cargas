<div class="status-changer-component" x-data="{ dropdownOpen: false }">
<div class="status-changer-component" x-data="{ dropdownOpen: false }">
<div class="status-changer-component" x-data="{ dropdownOpen: false }">
    {{-- Versión compacta y minimalista con marco de color --}}
    <div class="flex items-center space-x-1 px-2 py-1 rounded border-l-2 
        {{ $currentStatus === 'draft' ? 'border-l-gray-400 bg-gray-50' : '' }}
        {{ $currentStatus === 'pending_review' ? 'border-l-yellow-400 bg-yellow-50' : '' }}
        {{ $currentStatus === 'verified' ? 'border-l-blue-400 bg-blue-50' : '' }}
        {{ $currentStatus === 'sent_to_customs' ? 'border-l-purple-400 bg-purple-50' : '' }}
        {{ $currentStatus === 'accepted' ? 'border-l-green-400 bg-green-50' : '' }}
        {{ $currentStatus === 'rejected' ? 'border-l-red-400 bg-red-50' : '' }}
        {{ $currentStatus === 'completed' ? 'border-l-green-600 bg-green-50' : '' }}
        {{ $currentStatus === 'cancelled' ? 'border-l-red-600 bg-red-50' : '' }}">
        
        {{-- Badge de estado actual --}}
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $this->statusColor }}">
            {{ $this->statusLabel }}
        </span>

        {{-- Botón de cambio minimalista (solo si hay opciones disponibles) --}}
        @if(count($availableStatuses) > 0 && $showAsDropdown)
            <div class="relative inline-block text-left">
                <button type="button" 
                        class="inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 hover:bg-white border border-gray-300 hover:border-gray-400 rounded transition-all duration-150"
                        @click="dropdownOpen = !dropdownOpen"
                        title="Cambiar estado ({{ count($availableStatuses) }} opciones disponibles)">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </button>

                <div x-show="dropdownOpen" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     @click.away="dropdownOpen = false"
                     class="origin-top-right absolute right-0 mt-1 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <div class="py-1">
                        @foreach($availableStatuses as $status => $label)
                            <button wire:click="initiateStatusChange('{{ $status }}')"
                                    @click="dropdownOpen = false"
                                    class="group flex items-center w-full px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-800 transition-colors duration-150">
                                <span class="inline-flex items-center w-2 h-2 mr-3 rounded-full 
                                    {{ $status === 'draft' ? 'bg-gray-400' : '' }}
                                    {{ $status === 'pending_review' ? 'bg-yellow-400' : '' }}
                                    {{ $status === 'verified' ? 'bg-blue-400' : '' }}
                                    {{ $status === 'sent_to_customs' ? 'bg-purple-400' : '' }}
                                    {{ $status === 'accepted' ? 'bg-green-400' : '' }}
                                    {{ $status === 'rejected' ? 'bg-red-400' : '' }}
                                    {{ $status === 'completed' ? 'bg-green-600' : '' }}
                                    {{ $status === 'cancelled' ? 'bg-red-600' : '' }}"></span>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- DEBUG: Mostrar cuando no hay opciones disponibles (remover después) --}}
    @if(count($availableStatuses) === 0 && app()->environment('local'))
        <div class="text-xs text-red-600 mt-1">
            Debug: Sin estados disponibles. Estado actual: {{ $currentStatus }}
        </div>
    @endif

    {{-- Modal de confirmación --}}
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:key="modal-{{ $model->id }}">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Confirmar cambio de estado
                        </h3>
                        <button wire:click="cancelModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Contenido --}}
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            ¿Está seguro que desea cambiar el estado de 
                            <span class="font-medium">{{ $this->statusLabel }}</span> 
                            a 
                            <span class="font-medium">{{ $availableStatuses[$selectedStatus] ?? $selectedStatus }}</span>?
                        </p>

                        {{-- Campo para razón (si se requiere) --}}
                        @if($showReason || in_array($selectedStatus, ['rejected', 'cancelled']))
                            <div>
                                <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">
                                    Motivo {{ in_array($selectedStatus, ['rejected', 'cancelled']) ? '(requerido)' : '(opcional)' }}
                                </label>
                                <textarea wire:model="reason" 
                                         id="reason"
                                         rows="3" 
                                         class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                         placeholder="Escriba el motivo del cambio..."></textarea>
                                @error('reason') 
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p> 
                                @enderror
                            </div>
                        @endif

                        {{-- Errores generales --}}
                        @error('general') 
                            <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            </div>
                        @enderror

                        @error('selectedStatus') 
                            <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button wire:click="cancelModal"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button wire:click="changeStatus"
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed">
                            <span wire:loading.remove wire:target="changeStatus">Confirmar</span>
                            <span wire:loading wire:target="changeStatus">Cambiando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Mensaje de éxito --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition 
             x-init="setTimeout(() => show = false, 3000)"
             class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">
            {{ session('message') }}
        </div>
    @endif
</div>

        {{-- Botones como alternativa al dropdown --}}
        @if(count($availableStatuses) > 0 && !$showAsDropdown)
            @foreach($availableStatuses as $status => $label)
                <button wire:click="initiateStatusChange('{{ $status }}')"
                        class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ $label }}
                </button>
            @endforeach
        @endif
    </div>

    {{-- DEBUG: Mostrar cuando no hay opciones disponibles (remover después) --}}
    @if(count($availableStatuses) === 0 && app()->environment('local'))
        <div class="text-xs text-red-600 mt-1">
            Debug: Sin estados disponibles. Estado actual: {{ $currentStatus }}
        </div>
    @endif

    {{-- Modal de confirmación --}}
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:key="modal-{{ $model->id }}">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Confirmar cambio de estado
                        </h3>
                        <button wire:click="cancelModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Contenido --}}
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            ¿Está seguro que desea cambiar el estado de 
                            <span class="font-medium">{{ $this->statusLabel }}</span> 
                            a 
                            <span class="font-medium">{{ $availableStatuses[$selectedStatus] ?? $selectedStatus }}</span>?
                        </p>

                        {{-- Campo para razón (si se requiere) --}}
                        @if($showReason || in_array($selectedStatus, ['rejected', 'cancelled']))
                            <div>
                                <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">
                                    Motivo {{ in_array($selectedStatus, ['rejected', 'cancelled']) ? '(requerido)' : '(opcional)' }}
                                </label>
                                <textarea wire:model="reason" 
                                         id="reason"
                                         rows="3" 
                                         class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                         placeholder="Escriba el motivo del cambio..."></textarea>
                                @error('reason') 
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p> 
                                @enderror
                            </div>
                        @endif

                        {{-- Errores generales --}}
                        @error('general') 
                            <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            </div>
                        @enderror

                        @error('selectedStatus') 
                            <div class="bg-red-50 border border-red-200 rounded-md p-3">
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button wire:click="cancelModal"
                                class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button wire:click="changeStatus"
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed">
                            <span wire:loading.remove wire:target="changeStatus">Confirmar</span>
                            <span wire:loading wire:target="changeStatus">Cambiando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Mensaje de éxito --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition 
             x-init="setTimeout(() => show = false, 3000)"
             class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">
            {{ session('message') }}
        </div>
    @endif
</div>