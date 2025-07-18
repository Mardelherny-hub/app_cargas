<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Cliente') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->legal_name }} • {{ $client->tax_id }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.clients.show', $client) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Cliente
                </a>
                <a href="{{ route('company.clients.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Cliente</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Modifique los datos del cliente. Los campos marcados con * son obligatorios.
                    </p>
                </div>

                <form method="POST" action="{{ route('company.clients.update', $client) }}" class="px-6 py-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Datos Legales -->
                        <div class="md:col-span-2">
                            <div class="border-b border-gray-200 pb-4 mb-6">
                                <h4 class="text-md font-medium text-gray-900">Datos Legales</h4>
                            </div>
                        </div>

                        <!-- País -->
                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700">País *</label>
                            <select name="country_id" 
                                    id="country_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('country_id') border-red-300 @enderror"
                                    required>
                                <option value="">Seleccione un país</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" 
                                            {{ (old('country_id', $client->country_id) == $country->id) ? 'selected' : '' }}>
                                        {{ $country->name }} ({{ $country->iso_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Tipo de Documento -->
                        <div>
                            <label for="document_type_id" class="block text-sm font-medium text-gray-700">Tipo de Documento *</label>
                            <select name="document_type_id" 
                                    id="document_type_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('document_type_id') border-red-300 @enderror"
                                    required>
                                <option value="">Seleccione tipo de documento</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type->id }}" 
                                            {{ (old('document_type_id', $client->document_type_id) == $type->id) ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('document_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- CUIT/RUC -->
                        <div>
                            <label for="tax_id" class="block text-sm font-medium text-gray-700">CUIT/RUC *</label>
                            <input type="text" 
                                   name="tax_id" 
                                   id="tax_id" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('tax_id') border-red-300 @enderror"
                                   value="{{ old('tax_id', $client->tax_id) }}"
                                   placeholder="Ingrese CUIT/RUC"
                                   maxlength="20"
                                   required>
                            @error('tax_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Validación Visual -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Validación</label>
                            <div id="validation-result" class="mt-1 p-3 rounded-md border border-gray-200 bg-gray-50">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm text-gray-600">Modifique CUIT/RUC para validar</span>
                                </div>
                            </div>
                        </div>

                        <!-- Razón Social -->
                        <div>
                            <label for="legal_name" class="block text-sm font-medium text-gray-700">Razón Social *</label>
                            <input type="text" 
                                   name="legal_name" 
                                   id="legal_name" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                   value="{{ old('legal_name', $client->legal_name) }}"
                                   placeholder="Razón social completa"
                                   maxlength="255"
                                   required>
                            @error('legal_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Nombre Comercial -->
                        <div>
                            <label for="commercial_name" class="block text-sm font-medium text-gray-700">Nombre Comercial</label>
                            <input type="text" 
                                   name="commercial_name" 
                                   id="commercial_name" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('commercial_name') border-red-300 @enderror"
                                   value="{{ old('commercial_name', $client->commercial_name) }}"
                                   placeholder="Nombre comercial (opcional)"
                                   maxlength="255">
                            @error('commercial_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Reemplazar la sección de "Tipo de Cliente" en company/clients/edit.blade.php -->

<!-- Roles de Cliente - CORRECCIÓN: Selector múltiple con valores existentes -->
<div class="sm:col-span-1">
    <label for="client_roles" class="block text-sm font-medium text-gray-700">
        Roles de Cliente <span class="text-red-500">*</span>
    </label>
    <select id="client_roles" name="client_roles[]" multiple required
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            size="3">
        @php
            $oldRoles = old('client_roles', $client->client_roles ?? []);
            if (!is_array($oldRoles)) {
                $oldRoles = [$oldRoles];
            }
        @endphp
        @foreach(\App\Models\Client::CLIENT_ROLES as $key => $label)
            <option value="{{ $key }}" {{ in_array($key, $oldRoles) ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">
        Mantenga presionado Ctrl (Windows) o Cmd (Mac) para seleccionar múltiples roles.
    </p>
    @error('client_roles')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('client_roles.*')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<!-- Mostrar roles actuales del cliente -->
<div class="sm:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Roles Actuales
    </label>
    <div class="flex flex-wrap gap-2">
        @php
            $currentRoles = $client->client_roles ?? [];
            $roleColors = [
                'shipper' => 'bg-green-100 text-green-800',
                'consignee' => 'bg-blue-100 text-blue-800',
                'notify_party' => 'bg-yellow-100 text-yellow-800'
            ];
        @endphp
        
        @forelse($currentRoles as $role)
            @php
                $colorClass = $roleColors[$role] ?? 'bg-gray-100 text-gray-800';
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                {{ \App\Models\Client::CLIENT_ROLES[$role] ?? ucfirst($role) }}
            </span>
        @empty
            <span class="text-sm text-gray-500 italic">No hay roles asignados actualmente</span>
        @endforelse
    </div>
</div>


                        <!-- Configuración Operativa -->
                        <div class="md:col-span-2">
                            <div class="border-b border-gray-200 pb-4 mb-6 mt-6">
                                <h4 class="text-md font-medium text-gray-900">Configuración Operativa</h4>
                            </div>
                        </div>

                        <!-- Puerto Principal -->
                        <div>
                            <label for="primary_port_id" class="block text-sm font-medium text-gray-700">Puerto Principal</label>
                            <select name="primary_port_id" 
                                    id="primary_port_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('primary_port_id') border-red-300 @enderror">
                                <option value="">Seleccione puerto (opcional)</option>
                                @foreach($ports as $port)
                                    <option value="{{ $port->id }}" 
                                            {{ (old('primary_port_id', $client->primary_port_id) == $port->id) ? 'selected' : '' }}>
                                        {{ $port->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('primary_port_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Aduana -->
                        <div>
                            <label for="custom_office_id" class="block text-sm font-medium text-gray-700">Aduana</label>
                            <select name="custom_office_id" 
                                    id="custom_office_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('custom_office_id') border-red-300 @enderror">
                                <option value="">Seleccione aduana (opcional)</option>
                                @foreach($customOffices as $office)
                                    <option value="{{ $office->id }}" 
                                            {{ (old('custom_office_id', $client->custom_office_id) == $office->id) ? 'selected' : '' }}>
                                        {{ $office->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('custom_office_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Notas -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notas</label>
                            <textarea name="notes" 
                                      id="notes" 
                                      rows="4"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('notes') border-red-300 @enderror"
                                      placeholder="Observaciones adicionales sobre el cliente"
                                      maxlength="1000">{{ old('notes', $client->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Contactos Múltiples -->
                    <div class="mt-8">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <div class="flex items-center justify-between">
                                <h4 class="text-md font-medium text-gray-900">Contactos del Cliente</h4>
                                <button type="button" 
                                        id="addContactBtn" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Agregar Contacto
                                </button>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                Gestione los contactos del cliente organizados por tipo (general, AFIP, manifiestos, etc.)
                            </p>
                        </div>

                        <div id="contactsContainer">
                            <!-- Contactos existentes se cargan aquí -->
                        </div>

                        <!-- Información sobre tipos de contacto -->
                        <div class="mt-4 p-4 bg-blue-50 rounded-md">
                            <h5 class="text-sm font-medium text-blue-900 mb-2">Tipos de Contacto Disponibles:</h5>
                            <div class="text-sm text-blue-800 space-y-1">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <strong>General:</strong> Contacto administrativo general<br>
                                        <strong>AFIP:</strong> Para trámites y consultas AFIP<br>
                                        <strong>Manifiestos:</strong> Para envío de manifiestos<br>
                                        <strong>Avisos de Arribo:</strong> Para cartas de aviso
                                    </div>
                                    <div>
                                        <strong>Emergencias:</strong> Para situaciones urgentes<br>
                                        <strong>Facturación:</strong> Para temas comerciales<br>
                                        <strong>Operaciones:</strong> Para coordinación operativa
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('company.clients.show', $client) }}" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Actualizar Cliente
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template para nuevos contactos -->
    <template id="contactTemplate">
        <div class="contact-item border border-gray-200 rounded-lg p-4 mb-4" data-index="__INDEX__">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-medium text-gray-900">
                    <span class="contact-number">Contacto __NUMBER__</span>
                </h4>
                <button type="button" 
                        class="remove-contact text-red-600 hover:text-red-800 text-sm font-medium">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- ID oculto para contactos existentes -->
                <input type="hidden" name="contacts[__INDEX__][id]" value="__CONTACT_ID__">
                
                <!-- Tipo de Contacto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo de Contacto *</label>
                    <select name="contacts[__INDEX__][contact_type]" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required>
                        <option value="">Seleccione tipo</option>
                        <option value="general">General</option>
                        <option value="afip">AFIP</option>
                        <option value="manifests">Manifiestos</option>
                        <option value="arrival_notices">Avisos de Arribo</option>
                        <option value="emergency">Emergencias</option>
                        <option value="billing">Facturación</option>
                        <option value="operations">Operaciones</option>
                    </select>
                </div>

                <!-- Contacto Principal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contacto Principal</label>
                    <div class="mt-1 flex items-center">
                        <input type="checkbox" 
                               name="contacts[__INDEX__][is_primary]" 
                               value="1"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Marcar como contacto principal</span>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" 
                           name="contacts[__INDEX__][email]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="email@dominio.com"
                           value="__EMAIL__">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                    <input type="text" 
                           name="contacts[__INDEX__][phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Número de teléfono"
                           value="__PHONE__">
                </div>

                <!-- Teléfono Móvil -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono Móvil</label>
                    <input type="text" 
                           name="contacts[__INDEX__][mobile_phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Número de móvil"
                           value="__MOBILE_PHONE__">
                </div>

                <!-- Persona de Contacto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Persona de Contacto</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_name]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Nombre completo"
                           value="__CONTACT_PERSON_NAME__">
                </div>

                <!-- Dirección -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                    <input type="text" 
                           name="contacts[__INDEX__][address_line_1]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Dirección completa"
                           value="__ADDRESS_LINE_1__">
                </div>

                <!-- Ciudad -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ciudad</label>
                    <input type="text" 
                           name="contacts[__INDEX__][city]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Ciudad"
                           value="__CITY__">
                </div>

                <!-- Cargo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cargo</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_position]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Cargo o posición"
                           value="__CONTACT_PERSON_POSITION__">
                </div>

                <!-- Notas -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notas</label>
                    <textarea name="contacts[__INDEX__][notes]" 
                              rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Observaciones adicionales">__NOTES__</textarea>
                </div>
            </div>
        </div>
    </template>
</x-app-layout>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const taxIdInput = document.getElementById('tax_id');
        const countrySelect = document.getElementById('country_id');
        const validationResult = document.getElementById('validation-result');
        const addContactBtn = document.getElementById('addContactBtn');
        const contactsContainer = document.getElementById('contactsContainer');
        const contactTemplate = document.getElementById('contactTemplate');
        
        let contactIndex = 0;
        let originalTaxId = taxIdInput.value;
        let originalCountryId = countrySelect.value;

        // Cargar contactos existentes
        const existingContacts = @json($client->contactData ?? []);
        
        // Función para validar CUIT/RUC
        function validateTaxId() {
            const taxId = taxIdInput.value.trim();
            const countryId = countrySelect.value;

            if (!taxId || !countryId) {
                showValidationResult('info', 'Ingrese CUIT/RUC y país para validar');
                return;
            }

            // Solo validar si cambió
            if (taxId === originalTaxId && countryId === originalCountryId) {
                showValidationResult('info', 'CUIT/RUC actual del cliente');
                return;
            }

            showValidationResult('loading', 'Validando...');

            fetch('{{ route("company.clients.validate-tax-id") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    tax_id: taxId,
                    country_id: countryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    showValidationResult('success', data.message);
                } else {
                    showValidationResult('error', data.message);
                }
            })
            .catch(error => {
                showValidationResult('error', 'Error al validar');
            });
        }

        // Función para mostrar resultado de validación
        function showValidationResult(type, message) {
            const icons = {
                info: '<svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                loading: '<svg class="w-5 h-5 text-blue-500 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
                success: '<svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
                error: '<svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'
            };

            const colors = {
                info: 'bg-gray-50 border-gray-200',
                loading: 'bg-blue-50 border-blue-200',
                success: 'bg-green-50 border-green-200',
                error: 'bg-red-50 border-red-200'
            };

            const textColors = {
                info: 'text-gray-600',
                loading: 'text-blue-600',
                success: 'text-green-600',
                error: 'text-red-600'
            };

            validationResult.className = `mt-1 p-3 rounded-md border ${colors[type]}`;
            validationResult.innerHTML = `
                <div class="flex items-center">
                    ${icons[type]}
                    <span class="text-sm ${textColors[type]}">${message}</span>
                </div>
            `;
        }

        // Función para agregar contacto
        function addContact(existingData = null) {
            const template = contactTemplate.content.cloneNode(true);
            const newContact = template.querySelector('.contact-item');
            
            // Reemplazar índices
            newContact.innerHTML = newContact.innerHTML.replace(/__INDEX__/g, contactIndex);
            newContact.innerHTML = newContact.innerHTML.replace(/__NUMBER__/g, contactIndex + 1);
            newContact.setAttribute('data-index', contactIndex);
            
            // Si es contacto existente, llenar datos
            if (existingData) {
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_ID__/g, existingData.id || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__EMAIL__/g, existingData.email || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__PHONE__/g, existingData.phone || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__MOBILE_PHONE__/g, existingData.mobile_phone || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_NAME__/g, existingData.contact_person_name || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__ADDRESS_LINE_1__/g, existingData.address_line_1 || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CITY__/g, existingData.city || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_POSITION__/g, existingData.contact_person_position || '');
                newContact.innerHTML = newContact.innerHTML.replace(/__NOTES__/g, existingData.notes || '');
                
                // Seleccionar tipo de contacto
                const typeSelect = newContact.querySelector('select[name*="[contact_type]"]');
                if (typeSelect && existingData.contact_type) {
                    typeSelect.value = existingData.contact_type;
                }
                
                // Marcar como principal si corresponde
                const primaryCheckbox = newContact.querySelector('input[name*="[is_primary]"]');
                if (primaryCheckbox && existingData.is_primary) {
                    primaryCheckbox.checked = true;
                }
            } else {
                // Limpiar placeholders para contacto nuevo
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_ID__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__EMAIL__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__PHONE__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__MOBILE_PHONE__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_NAME__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__ADDRESS_LINE_1__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CITY__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_POSITION__/g, '');
                newContact.innerHTML = newContact.innerHTML.replace(/__NOTES__/g, '');
            }
            
            contactsContainer.appendChild(newContact);
            
            // Agregar event listener para eliminar
            newContact.querySelector('.remove-contact').addEventListener('click', function() {
                newContact.remove();
                updateContactNumbers();
            });
            
            contactIndex++;
        }

        // Función para actualizar números de contacto
        function updateContactNumbers() {
            const contacts = contactsContainer.querySelectorAll('.contact-item');
            contacts.forEach((contact, index) => {
                contact.querySelector('.contact-number').textContent = `Contacto ${index + 1}`;
            });
        }

        // Cargar contactos existentes al inicio
        existingContacts.forEach(contact => {
            addContact(contact);
        });

        // Event listeners
        taxIdInput.addEventListener('blur', validateTaxId);
        countrySelect.addEventListener('change', validateTaxId);
        addContactBtn.addEventListener('click', () => addContact());
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rolesSelect = document.getElementById('client_roles');
    const selectedRolesContainer = document.createElement('div');
    selectedRolesContainer.className = 'mt-2 flex flex-wrap gap-2';
    selectedRolesContainer.id = 'selected-roles-display';
    
    // Insertar después del texto de ayuda
    const helpText = rolesSelect.nextElementSibling;
    helpText.parentNode.insertBefore(selectedRolesContainer, helpText.nextSibling);
    
    // Colores para los badges
    const roleColors = {
        'shipper': 'bg-green-100 text-green-800',
        'consignee': 'bg-blue-100 text-blue-800', 
        'notify_party': 'bg-yellow-100 text-yellow-800'
    };
    
    function updateSelectedRolesDisplay() {
        const selectedOptions = Array.from(rolesSelect.selectedOptions);
        selectedRolesContainer.innerHTML = '';
        
        if (selectedOptions.length === 0) {
            selectedRolesContainer.innerHTML = '<span class="text-sm text-gray-500 italic">Ningún rol seleccionado</span>';
            return;
        }
        
        selectedOptions.forEach(option => {
            const badge = document.createElement('span');
            const colorClass = roleColors[option.value] || 'bg-gray-100 text-gray-800';
            badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`;
            badge.textContent = option.text;
            
            // Agregar icono si es un rol existente
            const currentRoles = @json($client->client_roles ?? []);
            if (currentRoles.includes(option.value)) {
                badge.innerHTML = `
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    ${option.text}
                `;
            }
            
            selectedRolesContainer.appendChild(badge);
        });
    }
    
    // Actualizar display cuando cambie la selección
    rolesSelect.addEventListener('change', updateSelectedRolesDisplay);
    
    // Inicializar display
    updateSelectedRolesDisplay();
    
    // Detectar cambios para mostrar indicador de "modificado"
    let originalRoles = @json($client->client_roles ?? []);
    rolesSelect.addEventListener('change', function() {
        const currentlySelected = Array.from(this.selectedOptions).map(option => option.value);
        const hasChanges = JSON.stringify(originalRoles.sort()) !== JSON.stringify(currentlySelected.sort());
        
        if (hasChanges) {
            this.style.borderColor = '#f59e0b'; // border-yellow-500
            this.style.borderWidth = '2px';
        } else {
            this.style.borderColor = '#d1d5db'; // border-gray-300  
            this.style.borderWidth = '1px';
        }
    });
});
</script>
@endpush