<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Crear Nuevo Cliente') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Agregar cliente a la base compartida del sistema
                </p>
            </div>
            <div class="flex space-x-2">
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
                        Complete los datos básicos del cliente. Los campos marcados con * son obligatorios.
                    </p>
                </div>

                <form method="POST" action="{{ route('company.clients.store') }}" class="px-6 py-4">
                    @csrf

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
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
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
                                    <option value="{{ $type->id }}" {{ old('document_type_id') == $type->id ? 'selected' : '' }}>
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
                                   value="{{ old('tax_id') }}"
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
                                    <span class="text-sm text-gray-600">Ingrese CUIT/RUC y país para validar</span>
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
                                   value="{{ old('legal_name') }}"
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
                                   value="{{ old('commercial_name') }}"
                                   placeholder="Nombre comercial (opcional)"
                                   maxlength="255">
                            @error('commercial_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
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
                                    <option value="{{ $port->id }}" {{ old('primary_port_id') == $port->id ? 'selected' : '' }}>
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
                                    <option value="{{ $office->id }}" {{ old('custom_office_id') == $office->id ? 'selected' : '' }}>
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
                                      maxlength="1000">{{ old('notes') }}</textarea>
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
                                Agregue los contactos del cliente organizados por tipo (general, AFIP, manifiestos, etc.)
                            </p>
                        </div>

                        <div id="contactsContainer">
                            <!-- Los contactos se agregarán aquí dinámicamente -->
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
                            <a href="{{ route('company.clients.index') }}" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Crear Cliente
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
                           placeholder="email@dominio.com">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                    <input type="text" 
                           name="contacts[__INDEX__][phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Número de teléfono">
                </div>

                <!-- Teléfono Móvil -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Teléfono Móvil</label>
                    <input type="text" 
                           name="contacts[__INDEX__][mobile_phone]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Número de móvil">
                </div>

                <!-- Persona de Contacto -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Persona de Contacto</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_name]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Nombre completo">
                </div>

                <!-- Dirección -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Dirección</label>
                    <input type="text" 
                           name="contacts[__INDEX__][address_line_1]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Dirección completa">
                </div>

                <!-- Ciudad -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ciudad</label>
                    <input type="text" 
                           name="contacts[__INDEX__][city]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Ciudad">
                </div>

                <!-- Cargo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cargo</label>
                    <input type="text" 
                           name="contacts[__INDEX__][contact_person_position]" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Cargo o posición">
                </div>

                <!-- Notas -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notas</label>
                    <textarea name="contacts[__INDEX__][notes]" 
                              rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Observaciones adicionales"></textarea>
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

        // Función para validar CUIT/RUC
        function validateTaxId() {
            const taxId = taxIdInput.value.trim();
            const countryId = countrySelect.value;

            if (!taxId || !countryId) {
                showValidationResult('info', 'Ingrese CUIT/RUC y país para validar');
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
        function addContact() {
            const template = contactTemplate.content.cloneNode(true);
            const newContact = template.querySelector('.contact-item');
            
            // Reemplazar índices
            newContact.innerHTML = newContact.innerHTML.replace(/__INDEX__/g, contactIndex);
            newContact.innerHTML = newContact.innerHTML.replace(/__NUMBER__/g, contactIndex + 1);
            newContact.setAttribute('data-index', contactIndex);
            
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

        // Event listeners
        taxIdInput.addEventListener('blur', validateTaxId);
        countrySelect.addEventListener('change', validateTaxId);
        addContactBtn.addEventListener('click', addContact);

        // Agregar un contacto inicial
        addContact();
    });
</script>
@endpush