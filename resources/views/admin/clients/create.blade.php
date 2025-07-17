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

    <!-- Meta tag CSRF para peticiones AJAX -->
    @push('meta')
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endpush

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Errores en el formulario:</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.clients.store') }}" id="clientForm" class="space-y-6">
                @csrf

                <!-- Información Básica Requerida -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Información Básica (Requerida)</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                            
                            <!-- País (Requerido) -->
                            <div class="sm:col-span-1">
                                <label for="country_id" class="block text-sm font-medium text-gray-700">
                                    País <span class="text-red-500">*</span>
                                </label>
                                <select id="country_id" 
                                        name="country_id" 
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccione un país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" 
                                                data-iso="{{ $country->iso_code }}"
                                                {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }} ({{ $country->iso_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CUIT/RUC (Requerido) -->
                            <div class="sm:col-span-1">
                                <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                    <span id="tax_id_label">CUIT/RUC</span> <span class="text-red-500">*</span>
                                </label>
                                <div class="mt-1 relative">
                                    <input type="text" 
                                           id="tax_id" 
                                           name="tax_id" 
                                           value="{{ old('tax_id') }}"
                                           required
                                           maxlength="15"
                                           placeholder="Ingrese CUIT/RUC"
                                           class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <div id="tax_id_validation" class="hidden absolute right-2 top-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <p id="tax_id_help" class="mt-1 text-xs text-gray-500"></p>
                                @error('tax_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Razón Social (Requerida) -->
                            <div class="sm:col-span-2">
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="legal_name" 
                                       name="legal_name" 
                                       value="{{ old('legal_name') }}"
                                       required
                                       minlength="3"
                                       maxlength="255"
                                       placeholder="Nombre legal de la empresa"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('legal_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Cliente (Requerido) -->
                            <div class="sm:col-span-1">
                                <label for="client_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Cliente <span class="text-red-500">*</span>
                                </label>
                                <select id="client_type" 
                                        name="client_type" 
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccione el tipo</option>
                                    <option value="shipper" {{ old('client_type') === 'shipper' ? 'selected' : '' }}>
                                        Cargador/Exportador
                                    </option>
                                    <option value="consignee" {{ old('client_type') === 'consignee' ? 'selected' : '' }}>
                                        Consignatario/Importador
                                    </option>
                                    <option value="notify_party" {{ old('client_type') === 'notify_party' ? 'selected' : '' }}>
                                        Notificatario
                                    </option>
                                </select>
                                @error('client_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Documento -->
                            <div class="sm:col-span-1">
                                <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                                    Tipo de Documento
                                </label>
                                <select id="document_type_id" 
                                        name="document_type_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccione tipo</option>
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->id }}" 
                                                {{ old('document_type_id') == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Complementaria -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Información Complementaria</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                            
                            <!-- Puerto Principal -->
                            <div class="sm:col-span-1">
                                <label for="primary_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto Principal
                                </label>
                                <select id="primary_port_id" 
                                        name="primary_port_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccione puerto</option>
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

                            <!-- Aduana Habitual -->
                            <div class="sm:col-span-1">
                                <label for="customs_offices_id" class="block text-sm font-medium text-gray-700">
                                    Aduana Habitual
                                </label>
                                <select id="customs_offices_id" 
                                        name="customs_offices_id"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccione aduana</option>
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
                                <textarea id="notes" 
                                          name="notes" 
                                          rows="3"
                                          maxlength="1000"
                                          placeholder="Información adicional sobre el cliente"
                                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">Máximo 1000 caracteres</p>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-purple-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Información de Contacto (Opcional)</h3>
                            <span class="ml-2 text-sm text-gray-500">Recomendado para notificaciones</span>
                        </div>

                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                            
                            <!-- Email Principal -->
                            <div class="sm:col-span-1">
                                <label for="contact_email" class="block text-sm font-medium text-gray-700">
                                    Email Principal
                                </label>
                                <input type="email" 
                                       id="contact_email" 
                                       name="contact_email" 
                                       value="{{ old('contact_email') }}"
                                       maxlength="100"
                                       placeholder="correo@empresa.com"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('contact_email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Teléfono -->
                            <div class="sm:col-span-1">
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700">
                                    Teléfono
                                </label>
                                <input type="tel" 
                                       id="contact_phone" 
                                       name="contact_phone" 
                                       value="{{ old('contact_phone') }}"
                                       maxlength="50"
                                       placeholder="+54 11 1234-5678"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('contact_phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Dirección -->
                            <div class="sm:col-span-1">
                                <label for="contact_address" class="block text-sm font-medium text-gray-700">
                                    Dirección
                                </label>
                                <textarea id="contact_address" 
                                          name="contact_address" 
                                          rows="2"
                                          maxlength="500"
                                          placeholder="Dirección completa"
                                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('contact_address') }}</textarea>
                                @error('contact_address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Ciudad -->
                            <div class="sm:col-span-1">
                                <label for="contact_city" class="block text-sm font-medium text-gray-700">
                                    Ciudad
                                </label>
                                <input type="text" 
                                       id="contact_city" 
                                       name="contact_city" 
                                       value="{{ old('contact_city') }}"
                                       maxlength="100"
                                       placeholder="Ciudad"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @error('contact_city')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-3">
                                <a href="{{ route('admin.clients.index') }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Cancelar
                                </a>
                            </div>
                            
                            <div class="flex space-x-3">
                                <button type="submit" 
                                        id="submitBtn"
                                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span id="submitText">Crear Cliente</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- JavaScript para Validaciones -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('clientForm');
            const countrySelect = document.getElementById('country_id');
            const taxIdInput = document.getElementById('tax_id');
            const taxIdLabel = document.getElementById('tax_id_label');
            const taxIdHelp = document.getElementById('tax_id_help');
            const taxIdValidation = document.getElementById('tax_id_validation');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');

            // Configuración de validación por país
            const validationRules = {
                'AR': {
                    label: 'CUIT',
                    pattern: /^\d{11}$/,
                    format: 'XX-XXXXXXXX-X',
                    help: 'Formato: 11 dígitos sin guiones (20-12345678-9)',
                    placeholder: 'Ej: 20123456789'
                },
                'PY': {
                    label: 'RUC',
                    pattern: /^\d{8}-?\d$/,
                    format: 'XXXXXXXX-X',
                    help: 'Formato: 8 dígitos + dígito verificador (12345678-9)',
                    placeholder: 'Ej: 12345678-9'
                }
            };

            // Actualizar validación según país seleccionado
            function updateTaxIdValidation() {
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                const isoCode = selectedOption.getAttribute('data-iso');
                
                if (isoCode && validationRules[isoCode]) {
                    const rules = validationRules[isoCode];
                    taxIdLabel.textContent = rules.label;
                    taxIdInput.placeholder = rules.placeholder;
                    taxIdHelp.textContent = rules.help;
                    taxIdInput.dataset.pattern = rules.pattern.source;
                } else {
                    taxIdLabel.textContent = 'CUIT/RUC';
                    taxIdInput.placeholder = 'Ingrese CUIT/RUC';
                    taxIdHelp.textContent = 'Seleccione primero un país para ver el formato requerido';
                    taxIdInput.dataset.pattern = '';
                }
                
                validateTaxId();
            }

            // Validar formato de CUIT/RUC
            function validateTaxId() {
                const taxIdValue = taxIdInput.value.replace(/[^0-9]/g, '');
                const pattern = taxIdInput.dataset.pattern;
                
                if (!pattern || !taxIdValue) {
                    taxIdValidation.classList.add('hidden');
                    taxIdInput.classList.remove('border-green-500', 'border-red-500');
                    return;
                }

                const regex = new RegExp(pattern);
                const isValid = regex.test(taxIdValue);
                
                if (isValid) {
                    taxIdValidation.classList.remove('hidden', 'text-red-500');
                    taxIdValidation.classList.add('text-green-500');
                    taxIdInput.classList.remove('border-red-500');
                    taxIdInput.classList.add('border-green-500');
                    taxIdInput.setCustomValidity('');
                } else {
                    taxIdValidation.classList.remove('hidden', 'text-green-500');
                    taxIdValidation.classList.add('text-red-500');
                    taxIdInput.classList.remove('border-green-500');
                    taxIdInput.classList.add('border-red-500');
                    taxIdInput.setCustomValidity('Formato de CUIT/RUC inválido');
                }
            }

            // Formatear automáticamente el CUIT/RUC mientras se escribe
            function formatTaxId() {
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                const isoCode = selectedOption.getAttribute('data-iso');
                let value = taxIdInput.value.replace(/[^0-9]/g, '');
                
                // Formateo específico por país
                if (isoCode === 'AR' && value.length <= 11) {
                    // Argentina: XX-XXXXXXXX-X
                    if (value.length > 2 && value.length <= 10) {
                        value = value.slice(0, 2) + '-' + value.slice(2);
                    }
                    if (value.length > 11) {
                        value = value.slice(0, 2) + '-' + value.slice(2, 10) + '-' + value.slice(10, 11);
                    }
                } else if (isoCode === 'PY' && value.length <= 9) {
                    // Paraguay: XXXXXXXX-X
                    if (value.length > 8) {
                        value = value.slice(0, 8) + '-' + value.slice(8, 9);
                    }
                }
                
                taxIdInput.value = value;
                validateTaxId();
            }

            // Verificar si ya existe el CUIT/RUC usando la API (opcional)
            let checkTimeout;
            function checkDuplicateTaxId() {
                clearTimeout(checkTimeout);
                
                const taxIdValue = taxIdInput.value.replace(/[^0-9]/g, '');
                const countryId = countrySelect.value;
                
                if (taxIdValue.length < 8 || !countryId) return;
                
                checkTimeout = setTimeout(() => {
                    // Validación opcional - si falla, la validación principal se hace en el backend
                    fetch(`/api/v1/clients/search?q=${taxIdValue}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('API no disponible');
                            return response.json();
                        })
                        .then(data => {
                            // La API devuelve array de clientes, verificar si hay coincidencias
                            const existingClient = Array.isArray(data) ? data.find(client => 
                                client.tax_id.replace(/[^0-9]/g, '') === taxIdValue && 
                                client.country_id == countryId
                            ) : null;
                            
                            if (existingClient) {
                                taxIdInput.setCustomValidity('Ya existe un cliente con este CUIT/RUC en el país seleccionado');
                                taxIdHelp.textContent = `⚠️ Cliente existente: ${existingClient.legal_name}`;
                                taxIdHelp.classList.add('text-yellow-600');
                            } else {
                                if (taxIdInput.checkValidity()) {
                                    taxIdInput.setCustomValidity('');
                                }
                                taxIdHelp.classList.remove('text-yellow-600');
                                updateTaxIdValidation(); // Restaurar help text original
                            }
                        })
                        .catch(() => {
                            // Error en la consulta o sin permisos API
                            // Limpiar validaciones previas y continuar
                            if (taxIdInput.checkValidity()) {
                                taxIdInput.setCustomValidity('');
                            }
                            taxIdHelp.classList.remove('text-yellow-600');
                            updateTaxIdValidation();
                        });
                }, 800); // Delay más largo para evitar spam a la API
            }

            // Event Listeners
            countrySelect.addEventListener('change', updateTaxIdValidation);
            taxIdInput.addEventListener('input', formatTaxId);
            taxIdInput.addEventListener('input', checkDuplicateTaxId);

            // Validación del formulario antes del envío
            form.addEventListener('submit', function(e) {
                // Verificar campos requeridos
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.reportValidity();
                        if (isValid) {
                            field.focus();
                            isValid = false;
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    return;
                }
                
                // Validación específica del CUIT/RUC
                if (!taxIdInput.checkValidity()) {
                    e.preventDefault();
                    taxIdInput.focus();
                    taxIdInput.reportValidity();
                    return;
                }
                
                // Deshabilitar botón de envío para evitar doble click
                submitBtn.disabled = true;
                submitText.textContent = 'Creando...';
            });

            // Inicializar validaciones si hay valores pre-cargados
            if (countrySelect.value) {
                updateTaxIdValidation();
            }
        });
    </script>
</x-app-layout>