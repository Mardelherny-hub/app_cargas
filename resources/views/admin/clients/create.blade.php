<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Nuevo Cliente
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Registrar un nuevo cliente en la base de datos compartida
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.index') }}" 
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Hay errores en el formulario
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.clients.store') }}" id="clientForm">
                @csrf
                
                <!-- Información Básica del Cliente -->
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Información Legal</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                            <!-- CUIT/RUC -->
                            <div class="sm:col-span-1">
                                <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                    CUIT/RUC <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="tax_id" name="tax_id" required maxlength="15"
                                       value="{{ old('tax_id') }}"
                                       placeholder="Ej: 20123456789"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('tax_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Solo números, sin guiones ni espacios</p>
                            </div>

                            <!-- País -->
                            <div class="sm:col-span-1">
                                <label for="country_id" class="block text-sm font-medium text-gray-700">
                                    País <span class="text-red-500">*</span>
                                </label>
                                <select id="country_id" name="country_id" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccionar país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" 
                                                {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Razón Social -->
                            <div class="sm:col-span-2">
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="legal_name" name="legal_name" required maxlength="255"
                                       value="{{ old('legal_name') }}"
                                       placeholder="Ej: EMPRESA TRANSPORTADORA S.A."
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('legal_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Documento -->
                            <div class="sm:col-span-1">
                                <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Documento
                                </label>
                                <select id="document_type_id" name="document_type_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Sin especificar</option>
                                    @foreach($documentTypes as $docType)
                                        <option value="{{ $docType->id }}" 
                                                {{ old('document_type_id') == $docType->id ? 'selected' : '' }}>
                                            {{ $docType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Puerto Principal -->
                            <div class="sm:col-span-1">
                                <label for="primary_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto Principal
                                </label>
                                <select id="primary_port_id" name="primary_port_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Sin especificar</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('primary_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('primary_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Aduana -->
                            <div class="sm:col-span-1">
                                <label for="customs_offices_id" class="block text-sm font-medium text-gray-700">
                                    Aduana Habitual
                                </label>
                                <select id="customs_offices_id" name="customs_offices_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Sin especificar</option>
                                    @foreach($customOffices as $office)
                                        <option value="{{ $office->id }}" 
                                                {{ old('customs_offices_id') == $office->id ? 'selected' : '' }}>
                                            {{ $office->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('customs_offices_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Observaciones -->
                            <div class="sm:col-span-2">
                                <label for="notes" class="block text-sm font-medium text-gray-700">
                                    Observaciones
                                </label>
                                <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                          placeholder="Observaciones adicionales sobre el cliente..."
                                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contactos Múltiples -->
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"/>
                                </svg>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Contactos</h3>
                                <span class="ml-2 text-sm text-gray-500">(Opcional - puede agregarse después)</span>
                            </div>
                            <button type="button" id="addContactBtn" 
                                    class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Agregar Contacto
                            </button>
                        </div>

                        <div id="contactsContainer">
                            <!-- Los contactos se agregarán dinámicamente aquí -->
                        </div>

                        <div id="noContactsMessage" class="text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm">No hay contactos agregados</p>
                            <p class="text-xs text-gray-400 mt-1">Puedes agregar contactos ahora o después de crear el cliente</p>
                        </div>

                        <!-- Información sobre emails múltiples 
                        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3 text-sm text-blue-700">
                                <p class="font-medium">Sobre los emails de contacto:</p>
                                <p class="mt-1">En el campo email puede ingresar múltiples direcciones separadas por punto y coma (;) para enviar cartas y notificaciones a varias direcciones a la vez.</p>
                                <p class="mt-1 text-xs text-blue-600">Ejemplo: carta1@empresa.com; carta2@empresa.com; operaciones@empresa.com</p>
                            </div>
                        </div>-->
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.clients.index') }}" 
                       class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-6 py-2 rounded-md text-sm font-medium">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 text-white hover:bg-blue-700 px-6 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Crear Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template para nuevos contactos -->
    <template id="contactTemplate">
        <div class="contact-item border border-gray-200 rounded-lg p-4 mb-4" data-index="__INDEX__">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-medium text-gray-900">
                    <span class="contact-number">__NUMBER__</span>. Nuevo Contacto
                </h4>
                <button type="button" class="remove-contact text-red-600 hover:text-red-800 text-sm font-medium">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                </button>
            </div>

            <div class="grid grid-cols-1 gap-y-4 gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Campo oculto para contact_type -->
                <input type="hidden" name="contacts[__INDEX__][contact_type]" value="general">
                
                <!-- Es Principal -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Contacto Principal
                    </label>
                    <div class="mt-1">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="contacts[__INDEX__][is_primary]" value="1"
                                   class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out">
                            <span class="ml-2 text-sm text-gray-700">Es el contacto principal</span>
                        </label>
                    </div>
                </div>

                <!-- Nombre de la Persona -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Nombre de Contacto
                    </label>
                    <input type="text" name="contacts[__INDEX__][contact_person_name]" maxlength="150"
                           placeholder="Ej: Juan Pérez"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Posición/Cargo -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Cargo/Posición
                    </label>
                    <input type="text" name="contacts[__INDEX__][contact_person_position]" maxlength="100"
                           placeholder="Ej: Gerente de Operaciones"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Email -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Email
                    </label>
                    <input type="email" name="contacts[__INDEX__][email]" maxlength="255"
                           placeholder="contacto@empresa.com"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Teléfono Fijo -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Teléfono Fijo
                    </label>
                    <input type="tel" name="contacts[__INDEX__][phone]" maxlength="20"
                           placeholder="Ej: +54 11 4555-1234"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Teléfono Móvil -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Teléfono Móvil
                    </label>
                    <input type="tel" name="contacts[__INDEX__][mobile_phone]" maxlength="20"
                           placeholder="Ej: +54 9 11 1234-5678"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Dirección Línea 1 -->
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Dirección Principal
                    </label>
                    <input type="text" name="contacts[__INDEX__][address_line_1]" maxlength="255"
                           placeholder="Ej: Av. Corrientes 1234"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Dirección Línea 2 -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Dirección Complementaria
                    </label>
                    <input type="text" name="contacts[__INDEX__][address_line_2]" maxlength="255"
                           placeholder="Ej: Piso 5, Oficina 12"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Ciudad -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Ciudad
                    </label>
                    <input type="text" name="contacts[__INDEX__][city]" maxlength="100"
                           placeholder="Ej: Buenos Aires"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Provincia/Estado -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">
                        Provincia/Estado
                    </label>
                    <input type="text" name="contacts[__INDEX__][state_province]" maxlength="100"
                           placeholder="Ej: CABA"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>

                <!-- Observaciones -->
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700">
                        Observaciones
                    </label>
                    <textarea name="contacts[__INDEX__][notes]" rows="2" maxlength="500"
                              placeholder="Observaciones adicionales sobre este contacto..."
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                </div>
            </div>
        </div>
    </template>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let contactIndex = 0;
        
        // Botón para agregar contacto
        document.getElementById('addContactBtn').addEventListener('click', function() {
            addNewContact();
            hideNoContactsMessage();
        });
        
        // Event delegation para botones de eliminar
        document.getElementById('contactsContainer').addEventListener('click', function(e) {
            if (e.target.closest('.remove-contact')) {
                e.preventDefault();
                removeContact(e.target.closest('.contact-item'));
            }
        });
        
        // Event delegation para control de contacto principal único
        document.getElementById('contactsContainer').addEventListener('change', function(e) {
            if (e.target.name && e.target.name.includes('[is_primary]')) {
                if (e.target.checked) {
                    // Desmarcar otros checkboxes de contacto principal
                    const otherCheckboxes = document.querySelectorAll('input[name*="[is_primary]"]');
                    otherCheckboxes.forEach(checkbox => {
                        if (checkbox !== e.target) {
                            checkbox.checked = false;
                        }
                    });
                }
            }
        });
        
        function addNewContact() {
            const template = document.getElementById('contactTemplate');
            const clone = template.content.cloneNode(true);
            
            // Reemplazar placeholders
            const html = clone.querySelector('.contact-item').outerHTML
                .replace(/__INDEX__/g, contactIndex)
                .replace(/__NUMBER__/g, contactIndex + 1);
            
            // Agregar al contenedor
            document.getElementById('contactsContainer').insertAdjacentHTML('beforeend', html);
            
            contactIndex++;
            updateContactNumbers();
        }
        
        function removeContact(contactItem) {
            if (confirm('¿Está seguro de eliminar este contacto?')) {
                contactItem.remove();
                updateContactNumbers();
                
                // Mostrar mensaje si no hay contactos
                if (document.querySelectorAll('.contact-item').length === 0) {
                    showNoContactsMessage();
                }
            }
        }
        
        function updateContactNumbers() {
            const contactItems = document.querySelectorAll('.contact-item');
            contactItems.forEach((item, index) => {
                const numberSpan = item.querySelector('.contact-number');
                if (numberSpan) {
                    numberSpan.textContent = index + 1;
                }
                
                // Actualizar atributo data-index
                item.setAttribute('data-index', index);
                
                // Actualizar nombres de campos
                const inputs = item.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    }
                });
            });
        }
        
        function hideNoContactsMessage() {
            const message = document.getElementById('noContactsMessage');
            if (message) {
                message.style.display = 'none';
            }
        }
        
        function showNoContactsMessage() {
            const message = document.getElementById('noContactsMessage');
            if (message) {
                message.style.display = 'block';
            }
        }
        
        // Validación del formulario
        document.getElementById('clientForm').addEventListener('submit', function(e) {
            const contactItems = document.querySelectorAll('.contact-item');
            let hasValidContact = true;
            
                            // Si hay contactos, al menos uno debe tener email o teléfono
            if (contactItems.length > 0) {
                hasValidContact = false;
                contactItems.forEach(item => {
                    const email = item.querySelector('input[name*="[email]"]').value;
                    const phone = item.querySelector('input[name*="[phone]"]').value;
                    const mobile = item.querySelector('input[name*="[mobile_phone]"]').value;
                    
                    if (email || phone || mobile) {
                        hasValidContact = true;
                    }
                });
                
                if (!hasValidContact) {
                    e.preventDefault();
                    alert('Si agrega contactos, debe incluir al menos un email o teléfono en algún contacto.');
                    return false;
                }
            }
        });
    });
    </script>
</x-app-layout>