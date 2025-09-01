<div>
<div class="max-w-4xl mx-auto">
    <form wire:submit.prevent="save" class="space-y-6">
        {{-- Header --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">Crear Nuevo Puerto</h1>
                        <p class="mt-1 text-sm text-gray-600">Complete la información del puerto que desea agregar al sistema.</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button type="button" 
                                wire:click="cancel" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg wire:loading wire:target="save" class="inline w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span wire:loading.remove wire:target="save">Guardar Puerto</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Tabs Navigation --}}
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button type="button" 
                            wire:click="setTab('basic')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'basic' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Información Básica
                    </button>
                    <button type="button" 
                            wire:click="setTab('location')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'location' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Ubicación
                    </button>
                    <button type="button" 
                            wire:click="setTab('capabilities')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'capabilities' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Capacidades
                    </button>
                    <button type="button" 
                            wire:click="setTab('contact')"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'contact' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Contacto
                    </button>
                </nav>
            </div>
        </div>

        {{-- Tab Content --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            
            {{-- Tab: Información Básica --}}
            @if($activeTab === 'basic')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica del Puerto</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Código del Puerto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Código del Puerto <span class="text-red-500">*</span>
                            </label>
                            <div class="flex space-x-2">
                                <input type="text" 
                                       wire:model.blur="code"
                                       placeholder="ej. BUEBA, ARBUE"
                                       maxlength="10"
                                       class="flex-1 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                                       style="text-transform: uppercase">
                                <button type="button" 
                                        wire:click="generateCode"
                                        class="px-3 py-2 text-xs bg-gray-100 text-gray-600 rounded hover:bg-gray-200"
                                        title="Generar código basado en la ciudad">
                                    Auto
                                </button>
                            </div>
                            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">Código UN/LOCODE (máx. 10 caracteres)</p>
                        </div>

                        {{-- País --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                País <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="country_id" 
                                    class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country['id'] }}">{{ $country['name'] }}</option>
                                @endforeach
                            </select>
                            @error('country_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nombre del Puerto --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre del Puerto <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   wire:model.blur="name"
                                   placeholder="ej. Puerto de Buenos Aires"
                                   maxlength="150"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nombre Corto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Corto</label>
                            <div class="flex space-x-2">
                                <input type="text" 
                                       wire:model="short_name"
                                       placeholder="ej. Buenos Aires"
                                       maxlength="50"
                                       class="flex-1 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" 
                                        wire:click="generateShortName"
                                        class="px-3 py-2 text-xs bg-gray-100 text-gray-600 rounded hover:bg-gray-200"
                                        title="Generar nombre corto automáticamente">
                                    Auto
                                </button>
                            </div>
                            @error('short_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nombre Local --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Local</label>
                            <input type="text" 
                                   wire:model="local_name"
                                   placeholder="Nombre en idioma local"
                                   maxlength="150"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('local_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Tipo de Puerto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Puerto <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="port_type" 
                                    class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($portTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('port_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Categoría --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Categoría <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="port_category" 
                                    class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($portCategories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('port_category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tab: Ubicación --}}
            @if($activeTab === 'location')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ubicación Geográfica</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Ciudad --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Ciudad <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   wire:model="city"
                                   placeholder="ej. Buenos Aires"
                                   maxlength="100"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Provincia/Estado --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provincia/Estado</label>
                            <input type="text" 
                                   wire:model="province_state"
                                   placeholder="ej. Buenos Aires"
                                   maxlength="100"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('province_state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Dirección --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                            <textarea wire:model="address"
                                      rows="2"
                                      placeholder="Dirección completa del puerto"
                                      maxlength="500"
                                      class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Código Postal --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código Postal</label>
                            <input type="text" 
                                   wire:model="postal_code"
                                   placeholder="ej. C1001"
                                   maxlength="20"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Fecha de Establecimiento --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Establecimiento</label>
                            <input type="date" 
                                   wire:model="established_date"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('established_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Coordenadas --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Coordenadas Geográficas</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Latitud</label>
                                    <input type="number" 
                                           step="0.000001"
                                           wire:model="latitude"
                                           placeholder="-34.608521"
                                           class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('latitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Longitud</label>
                                    <input type="number" 
                                           step="0.000001"
                                           wire:model="longitude"
                                           placeholder="-58.371853"
                                           class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('longitude') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Profundidad (metros)</label>
                                    <input type="number" 
                                           step="0.1"
                                           wire:model="water_depth"
                                           placeholder="10.5"
                                           class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('water_depth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tab: Capacidades --}}
            @if($activeTab === 'capabilities')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Capacidades y Servicios</h3>
                    
                    {{-- Capacidades Operativas --}}
                    <div>
                        <h4 class="text-md font-medium text-gray-700 mb-3">Manejo de Cargas</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="handles_containers" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Contenedores</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="handles_bulk_cargo" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Carga a Granel</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="handles_general_cargo" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Carga General</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="handles_passengers" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Pasajeros</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="handles_dangerous_goods" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Mercancías Peligrosas</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="has_customs_office" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Oficina de Aduanas</span>
                            </label>
                        </div>
                    </div>

                    {{-- Infraestructura --}}
                    <div>
                        <h4 class="text-md font-medium text-gray-700 mb-3">Infraestructura</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitud Máxima Embarcación (m)</label>
                                <input type="number" 
                                       wire:model="max_vessel_length"
                                       placeholder="200"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('max_vessel_length') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Calado Máximo (m)</label>
                                <input type="number" 
                                       step="0.1"
                                       wire:model="max_draft"
                                       placeholder="12.5"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('max_draft') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Muelles</label>
                                <input type="number" 
                                       wire:model="berths_count"
                                       placeholder="8"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('berths_count') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Grúas</label>
                                <input type="number" 
                                       wire:model="cranes_count"
                                       placeholder="4"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('cranes_count') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Área de Almacén (m²)</label>
                                <input type="number" 
                                       wire:model="warehouse_area"
                                       placeholder="5000"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('warehouse_area') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Área de Almacenamiento Abierto (m²)</label>
                                <input type="number" 
                                       wire:model="open_storage_area"
                                       placeholder="10000"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('open_storage_area') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Estado y Configuración --}}
                    <div>
                        <h4 class="text-md font-medium text-gray-700 mb-3">Estado y Configuración</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Puerto Activo</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="accepts_new_vessels" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Acepta Nuevas Embarcaciones</span>
                            </label>
                        </div>
                    </div>

                    {{-- Orden de Visualización --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Orden de Visualización</label>
                            <input type="number" 
                                   wire:model="display_order"
                                   min="0"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('display_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">Menor número = mayor prioridad</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Moneda Local</label>
                            <input type="text" 
                                   wire:model="currency_code"
                                   placeholder="USD, ARS, PYG"
                                   maxlength="3"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 font-mono uppercase">
                            @error('currency_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tab: Contacto --}}
            @if($activeTab === 'contact')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Teléfono --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="tel" 
                                   wire:model="phone"
                                   placeholder="+54 11 4000-0000"
                                   maxlength="20"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Fax --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fax</label>
                            <input type="tel" 
                                   wire:model="fax"
                                   placeholder="+54 11 4000-0001"
                                   maxlength="20"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('fax') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" 
                                   wire:model="email"
                                   placeholder="contacto@puerto.com"
                                   maxlength="100"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Canal VHF --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Canal VHF</label>
                            <input type="text" 
                                   wire:model="vhf_channel"
                                   placeholder="16, 12"
                                   maxlength="10"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('vhf_channel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">Canal de radio para comunicaciones</p>
                        </div>

                        {{-- Sitio Web --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sitio Web</label>
                            <input type="url" 
                                   wire:model="website"
                                   placeholder="https://www.puerto.com"
                                   maxlength="255"
                                   class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Notas Especiales --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas Especiales</label>
                            <textarea wire:model="special_notes"
                                      rows="4"
                                      placeholder="Información adicional, restricciones especiales, horarios de operación, etc."
                                      maxlength="1000"
                                      class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('special_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">Máximo 1000 caracteres</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Footer con acciones --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    <span class="font-medium">Campos obligatorios:</span> Código, Nombre, País, Ciudad, Tipo y Categoría
                </div>
                <div class="flex items-center space-x-3">
                    <button type="button" 
                            wire:click="cancel" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm">
                        <svg wire:loading wire:target="save" class="inline w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="save" class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span wire:loading.remove wire:target="save">Guardar Puerto</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Toast notifications --}}
    <div x-data="{ show: false, type: '', message: '' }" 
         x-on:toast.window="
            show = true; 
            type = $event.detail.type; 
            message = $event.detail.message; 
            setTimeout(() => show = false, 3000)
         "
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-2"
         class="fixed top-4 right-4 z-50"
         style="display: none;">
        <div :class="{ 
            'bg-green-500': type === 'success', 
            'bg-red-500': type === 'error',
            'bg-yellow-500': type === 'warning'
        }" 
        class="text-white px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2 min-w-64">
            <!-- Success Icon -->
            <svg x-show="type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <!-- Error Icon -->
            <svg x-show="type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <!-- Warning Icon -->
            <svg x-show="type === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <span x-text="message"></span>
            <button @click="show = false" class="ml-2 hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>
</div>
</div>
