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

                <form method="POST" action="{{ route('company.clients.update', $client) }}">
                    @csrf
                    @method('PUT')

                    <div class="px-6 py-6 space-y-6">
                        <!-- Información Básica -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Razón Social -->
                            <div>
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="legal_name" 
                                       id="legal_name" 
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                       value="{{ old('legal_name', $client->legal_name) }}"
                                       placeholder="Ingrese la razón social"
                                       maxlength="255">
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

                            <!-- Tipo de Documento -->
                            <div>
                                <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Documento <span class="text-red-500">*</span>
                                </label>
                                <select name="document_type_id" 
                                        id="document_type_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('document_type_id') border-red-300 @enderror">
                                    <option value="">Seleccione un tipo</option>
                                    @foreach($documentTypes as $documentType)
                                        <option value="{{ $documentType->id }}" {{ (old('document_type_id', $client->document_type_id) == $documentType->id) ? 'selected' : '' }}>
                                            {{ $documentType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Número de Documento -->
                            <div>
                                <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                    Número de Documento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="tax_id" 
                                       id="tax_id" 
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('tax_id') border-red-300 @enderror"
                                       value="{{ old('tax_id', $client->tax_id) }}"
                                       placeholder="Ingrese el número de documento"
                                       maxlength="50">
                                @error('tax_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <div id="validation-result"></div>
                            </div>

                            <!-- País -->
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700">
                                    País <span class="text-red-500">*</span>
                                </label>
                                <select name="country_id" 
                                        id="country_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('country_id') border-red-300 @enderror">
                                    <option value="">Seleccione un país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ (old('country_id', $client->country_id) == $country->id) ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <select name="status" id="status" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                    @php
                                        $statusValue = old('status', $client->status ?? 'active');
                                    @endphp
                                    <option value="active"   {{ $statusValue === 'active' ? 'selected' : '' }}>Activo</option>
                                    <option value="inactive" {{ $statusValue === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                    <option value="suspended"{{ $statusValue === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                </select>
                                @error('status') 
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p> 
                                @enderror
                            </div>


                            <!-- Aduana Habitual -->
                            <div>
                                <label for="custom_offices_id" class="block text-sm font-medium text-gray-700">Aduana Habitual</label>
                                <select name="custom_offices_id" 
                                        id="custom_offices_id" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('customs_office_id') border-red-300 @enderror">
                                    <option value="">Seleccione una aduana</option>
                                    @foreach($customOffices as $office)
                                        <option value="{{ $office->id }}" 
                                            {{ (old('custom_offices_id', $client->customs_office_id) == $office->id) ? 'selected' : '' }}>
                                            {{ $office->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('custom_offices_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Información de Contacto Principal -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Información de Contacto</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                                           value="{{ old('email', $client->email) }}"
                                           placeholder="correo@empresa.com">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Teléfono -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('phone') border-red-300 @enderror"
                                           value="{{ old('phone', $client->phone) }}"
                                           placeholder="+54 11 1234-5678">
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Dirección -->
                                <div class="md:col-span-2">
                                    <label for="address_line_1" class="block text-sm font-medium text-gray-700">Dirección</label>
                                    <input type="text" 
                                           name="address_line_1" 
                                           id="address_line_1" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('address_line_1') border-red-300 @enderror"
                                           value="{{ old('address_line_1', $client->address_line_1) }}"
                                           placeholder="Dirección completa"
                                           maxlength="255">
                                    @error('address_line_1')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Ciudad -->
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700">Ciudad</label>
                                    <input type="text" 
                                           name="city" 
                                           id="city" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('city') border-red-300 @enderror"
                                           value="{{ old('city', $client->city) }}"
                                           placeholder="Ciudad"
                                           maxlength="100">
                                    @error('city')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Observaciones -->
                                <div class="md:col-span-2">
                                    <label for="notes" class="block text-sm font-medium text-gray-700">Observaciones</label>
                                    <textarea name="notes" 
                                              id="notes" 
                                              rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('notes') border-red-300 @enderror"
                                              placeholder="Información adicional o notas sobre el cliente">{{ old('notes', $client->notes) }}</textarea>
                                    @error('notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Contactos Adicionales -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-medium text-gray-900">Contactos Adicionales</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Gestione los contactos adicionales para este cliente
                                    </p>
                                </div>
                                <button type="button" 
                                        id="addContactBtn"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Agregar Contacto
                                </button>
                            </div>
                            
                            <div id="contactsContainer" class="space-y-4">
                                <!-- Los contactos se cargan dinámicamente aquí -->
                            </div>

                            @if($errors->has('contacts'))
                                <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                                    <div class="text-sm text-red-600">
                                        {{ $errors->first('contacts') }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                        <a href="{{ route('company.clients.show', $client) }}" 
                           class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-6 py-2 rounded-md text-sm font-medium">
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
                
                <!-- Campo oculto para contact_type = 'general' -->
                <input type="hidden" name="contacts[__INDEX__][contact_type]" value="general">
                
                <!-- Nombre del Contacto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre del Contacto</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_name]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Nombre completo"
                           value="__CONTACT_PERSON_NAME__"
                           maxlength="255">
                </div>

                <!-- Posición/Cargo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Posición/Cargo</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_position]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Cargo o posición"
                           value="__CONTACT_PERSON_POSITION__"
                           maxlength="255">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" 
                           name="contacts[__INDEX__][email]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="contacto@empresa.com"
                           value="__EMAIL__">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                    <input type="text" 
                           name="contacts[__INDEX__][phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="+54 11 1234-5678"
                           value="__PHONE__">
                </div>

                <!-- Teléfono Móvil -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono Móvil</label>
                    <input type="text" 
                           name="contacts[__INDEX__][mobile_phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="+54 9 11 1234-5678"
                           value="__MOBILE_PHONE__">
                </div>

                <!-- Dirección -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                    <input type="text" 
                           name="contacts[__INDEX__][address_line_1]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Dirección completa"
                           value="__ADDRESS_LINE_1__"
                           maxlength="255">
                </div>

                <!-- Ciudad -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ciudad</label>
                    <input type="text" 
                           name="contacts[__INDEX__][city]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Ciudad"
                           value="__CITY__"
                           maxlength="100">
                </div>

                <!-- Contacto Principal -->
                <div>
                    <div class="flex items-center">
                        <input type="checkbox" 
                               name="contacts[__INDEX__][is_primary]" 
                               value="1"
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                               __IS_PRIMARY_CHECKED__>
                        <label class="ml-2 block text-sm font-medium text-gray-700">
                            Contacto Principal
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Solo puede haber un contacto principal por cliente</p>
                </div>

                <!-- Notas -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notas</label>
                    <textarea name="contacts[__INDEX__][notes]" 
                              rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Información adicional sobre este contacto">__NOTES__</textarea>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let contactIndex = 0;
            const contactsContainer = document.getElementById('contactsContainer');
            const contactTemplate = document.getElementById('contactTemplate');
            const addContactBtn = document.getElementById('addContactBtn');

            // Cargar contactos existentes
            const existingContacts = @json($client->contactData ?? []);

            // Agregar contactos existentes
            existingContacts.forEach(function(contact, index) {
                addContact(contact);
            });

            // Agregar contacto al hacer clic en el botón
            addContactBtn.addEventListener('click', function() {
                addContact();
            });

            // Event delegation para remover contactos
            contactsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-contact')) {
                    e.preventDefault();
                    removeContact(e.target.closest('.contact-item'));
                }
            });
            
            // Event delegation para control de contacto principal único
            contactsContainer.addEventListener('change', function(e) {
                if (e.target.name && e.target.name.includes('[is_primary]')) {
                    if (e.target.checked) {
                        // Desmarcar otros checkboxes de contacto principal
                        const otherCheckboxes = contactsContainer.querySelectorAll('input[name*="[is_primary]"]');
                        otherCheckboxes.forEach(checkbox => {
                            if (checkbox !== e.target) {
                                checkbox.checked = false;
                            }
                        });
                    }
                }
            });

            // Validación en tiempo real del número de documento
            document.getElementById('tax_id').addEventListener('blur', function() {
                const taxId = this.value.trim();
                const documentTypeId = document.getElementById('document_type_id').value;
                const originalTaxId = '{{ $client->tax_id }}';
                
                if (taxId && documentTypeId && taxId !== originalTaxId) {
                    validateTaxId(taxId, documentTypeId);
                }
            });

            function validateTaxId(taxId, documentTypeId) {
                const validationResult = document.getElementById('validation-result');
                
                showValidationMessage('loading', 'Validando documento...');
                
                fetch(`{{ route('company.clients.update', $client) }}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Validation-Only': 'true'
                    },
                    body: JSON.stringify({
                        tax_id: taxId,
                        document_type_id: documentTypeId,
                        _exclude_client_id: {{ $client->id }}
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        showValidationMessage('success', 'Documento válido');
                    } else {
                        showValidationMessage('error', data.message || 'El documento ya está registrado o no es válido');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showValidationMessage('error', 'Error al validar el documento');
                });
            }

            function showValidationMessage(type, message) {
                const validationResult = document.getElementById('validation-result');
                
                const icons = {
                    loading: '<svg class="w-5 h-5 text-blue-500 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
                    success: '<svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
                    error: '<svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'
                };

                const colors = {
                    loading: 'bg-blue-50 border-blue-200',
                    success: 'bg-green-50 border-green-200',
                    error: 'bg-red-50 border-red-200'
                };

                const textColors = {
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
                    
                    // Marcar como principal si corresponde
                    const isPrimaryChecked = existingData.is_primary ? 'checked' : '';
                    newContact.innerHTML = newContact.innerHTML.replace(/__IS_PRIMARY_CHECKED__/g, isPrimaryChecked);
                } else {
                    // Contacto nuevo - limpiar placeholders
                    newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_ID__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__EMAIL__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__PHONE__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__MOBILE_PHONE__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_NAME__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__ADDRESS_LINE_1__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__CITY__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__CONTACT_PERSON_POSITION__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__NOTES__/g, '');
                    newContact.innerHTML = newContact.innerHTML.replace(/__IS_PRIMARY_CHECKED__/g, '');
                }
                
                contactsContainer.appendChild(newContact);
                contactIndex++;
            }

            // Función para remover contacto
            function removeContact(contactItem) {
                if (confirm('¿Está seguro de eliminar este contacto?')) {
                    contactItem.remove();
                    updateContactNumbers();
                }
            }

            // Función para actualizar números de contacto
            function updateContactNumbers() {
                const contacts = contactsContainer.querySelectorAll('.contact-item');
                contacts.forEach((contact, index) => {
                    const numberSpan = contact.querySelector('.contact-number');
                    if (numberSpan) {
                        numberSpan.textContent = `Contacto ${index + 1}`;
                    }
                });
            }
        });
    </script>
</x-app-layout>