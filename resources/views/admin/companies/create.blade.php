<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Empresa') }}
            </h2>
            <a href="{{ route('admin.companies.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Volver
            </a>
        </div> 
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">
                                        Hay errores en el formulario
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc space-y-1 pl-5">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.companies.store') }}" class="space-y-8" id="companyForm">
                        @csrf

                        <!-- Información básica de la empresa -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-6">Información Básica</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="legal_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Razón Social *
                                    </label>
                                    <input type="text" 
                                           name="legal_name" 
                                           id="legal_name" 
                                           value="{{ old('legal_name') }}"
                                           required
                                           placeholder="Ej: Rio de la Plata Transport S.A."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="commercial_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nombre Comercial
                                    </label>
                                    <input type="text" 
                                           name="commercial_name" 
                                           id="commercial_name" 
                                           value="{{ old('commercial_name') }}"
                                           placeholder="Ej: Rio Transport"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                                        País *
                                    </label>
                                    <select name="country" 
                                            id="country" 
                                            required
                                            onchange="updateCountryFields()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Seleccionar país</option>
                                        <option value="AR" {{ old('country') === 'AR' ? 'selected' : '' }}>Argentina</option>
                                        <option value="PY" {{ old('country') === 'PY' ? 'selected' : '' }}>Paraguay</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        <span id="tax_id_label">CUIT/RUC *</span>
                                    </label>
                                    <input type="text" 
                                        name="tax_id" 
                                        id="tax_id" 
                                        value="{{ old('tax_id') }}"
                                        required
                                        maxlength="11"
                                        placeholder="Ingrese CUIT/RUC"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p id="tax_id_help" class="mt-1 text-sm text-gray-500">Formato: 11 dígitos numéricos</p>
                                </div>
                            </div>
                        </div>

                        <!-- Información de contacto -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-6">Información de Contacto</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email *
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           value="{{ old('email') }}"
                                           required
                                           placeholder="contacto@empresa.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Teléfono
                                    </label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           value="{{ old('phone') }}"
                                           placeholder="+54 11 4567-8900"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div class="mt-6">
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Dirección
                                </label>
                                <textarea name="address" 
                                          id="address" 
                                          rows="2"
                                          placeholder="Dirección completa de la empresa"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address') }}</textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ciudad
                                    </label>
                                    <input type="text" 
                                           name="city" 
                                           id="city" 
                                           value="{{ old('city') }}"
                                           placeholder="Buenos Aires"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                                        Código Postal
                                    </label>
                                    <input type="text" 
                                           name="postal_code" 
                                           id="postal_code" 
                                           value="{{ old('postal_code') }}"
                                           placeholder="1001"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <!-- ID María para MANE/Malvina -->
                                <div>
                                    <label for="id_maria" class="block text-sm font-medium text-gray-700 mb-2">
                                        ID María (MANE/Malvina)
                                    </label>
                                    <input type="text" 
                                        name="id_maria" 
                                        id="id_maria" 
                                        value="{{ old('id_maria') }}"
                                        maxlength="10"
                                        placeholder="Ej: MAR001"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="mt-1 text-xs text-gray-500">ID utilizado para archivos del sistema Malvina de Aduana (máximo 10 caracteres)</p>
                                    @error('id_maria')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Roles de empresa -->
                        <div class="bg-blue-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-blue-900 mb-6">Roles de Empresa *</h3>
                            <p class="text-sm text-blue-700 mb-4">
                                Selecciona los roles que tendrá esta empresa en el sistema. Esto determinará las funcionalidades disponibles.
                            </p>
                            
                            <div class="space-y-4">
                                @foreach($availableRoles as $role)
                                    <div class="flex items-start">
                                        <input type="checkbox" 
                                               name="company_roles[]" 
                                               id="role_{{ $role }}" 
                                               value="{{ $role }}"
                                               {{ in_array($role, old('company_roles', [])) ? 'checked' : '' }}
                                               onchange="updateRoleInfo()"
                                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                        <div class="ml-3">
                                            <label for="role_{{ $role }}" class="text-sm font-medium text-gray-700">
                                                {{ $role }}
                                            </label>
                                            <p class="text-sm text-gray-500">
                                                @if($role === 'Cargas')
                                                    Gestión de cargas, contenedores y manifiestos. Acceso a webservices de información anticipada y MIC/DTA.
                                                @elseif($role === 'Desconsolidador')
                                                    Operaciones de desconsolidación. Gestión de títulos madre e hijos.
                                                @elseif($role === 'Transbordos')
                                                    Operaciones de transbordo entre barcazas. Tracking de posición y gestión de flotas.
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div id="roleWarning" class="hidden mt-4 p-3 bg-amber-50 border border-amber-200 rounded-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-amber-700">
                                            Debe seleccionar al menos un rol para la empresa.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración de webservices -->
                        <div class="bg-green-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-green-900 mb-6">Configuración de Webservices</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="ws_environment" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ambiente *
                                    </label>
                                    <select name="ws_environment" 
                                            id="ws_environment" 
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="testing" {{ old('ws_environment', 'testing') === 'testing' ? 'selected' : '' }}>Testing (Pruebas)</option>
                                        <option value="production" {{ old('ws_environment') === 'production' ? 'selected' : '' }}>Producción</option>
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500">Se recomienda iniciar en ambiente de pruebas</p>
                                </div>

                                <div class="flex items-center">
                                    <input type="hidden" name="ws_active" value="0">
                                    <input type="checkbox" 
                                           name="ws_active" 
                                           id="ws_active" 
                                           value="1"
                                           {{ old('ws_active', '1') === '1' ? 'checked' : '' }}
                                           class="h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="ws_active" class="ml-3 block text-sm text-gray-700">
                                        <span class="font-medium">Activar webservices</span>
                                        <span class="block text-gray-500">Permite comunicación con sistemas de aduana</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Vista previa de configuración automática -->
                            <div id="wsConfigPreview" class="mt-6 hidden">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Configuración automática según roles:</h4>
                                <div id="wsConfigContent" class="bg-white p-4 rounded border text-sm">
                                    <!-- Se llena dinámicamente con JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- Estado y configuración final -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-6">Estado y Configuración</h3>
                            
                            <div class="flex items-center">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" 
                                       name="active" 
                                       id="active" 
                                       value="1"
                                       {{ old('active', '1') === '1' ? 'checked' : '' }}
                                       class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="active" class="ml-3 block text-sm text-gray-700">
                                    <span class="font-medium">Empresa activa</span>
                                    <span class="block text-gray-500">La empresa podrá operar en el sistema</span>
                                </label>
                            </div>

                            <div class="mt-4 p-4 bg-blue-50 rounded-md">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">Próximos pasos después de crear la empresa:</h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Subir certificado digital (.p12) para webservices</li>
                                    <li>• Crear usuario administrador de la empresa</li>
                                    <li>• Configurar operadores y permisos</li>
                                    <li>• Probar conexión con webservices en ambiente de testing</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="{{ route('admin.companies.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Crear Empresa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    // FIX COMPLETO PARA: resources/views/admin/companies/create.blade.php
// Reemplazar todo el bloque JavaScript existente con este código corregido

<script>
    // CONFIGURACIÓN CORREGIDA - Validaciones específicas por país
    const countryConfigs = {
        'AR': {
            validation_type: 'prefix_based',
            valid_prefixes: ['20', '23', '24', '27', '30', '33', '34'], // ← FIX: Lista completa
            required_length: 11,
            tax_id_label: 'CUIT Argentina *',
            tax_id_help: 'Formato: 11 dígitos. Prefijos válidos: 20, 23, 24, 27, 30 (empresas), 33, 34',
            phone_example: '+54 11 4567-8900',
            city_example: 'Buenos Aires',
            prefix_descriptions: {
                '20': 'Personas físicas masculinas',
                '23': 'Personas físicas monotributo masculinas',
                '24': 'Personas físicas monotributo femeninas',
                '27': 'Personas físicas femeninas',
                '30': 'Personas jurídicas (empresas)',
                '33': 'Personas jurídicas especiales',
                '34': 'Personas jurídicas extranjeras'
            }
        },
        'PY': {
            validation_type: 'length_based',
            min_length: 6,
            max_length: 9,
            tax_id_label: 'RUC Paraguay *',
            tax_id_help: 'Formato: 6-9 dígitos sin guion (ej: 12345678). Sin prefijos obligatorios',
            phone_example: '+595 21 123-456',
            city_example: 'Asunción'
        }
    };

    // Configuraciones de webservices según roles (sin cambios)
    const roleWebservices = {
        'Cargas': ['anticipada', 'micdta'],
        'Desconsolidador': ['desconsolidados'],
        'Transbordos': ['transbordos']
    };

    const roleFeatures = {
        'Cargas': ['contenedores', 'manifiestos'],
        'Desconsolidador': ['titulos_madre', 'titulos_hijos'],
        'Transbordos': ['barcazas', 'tracking_posicion']
    };

    // FUNCIÓN CORREGIDA: Actualizar campos según el país seleccionado
    function updateCountryFields() {
        const country = document.getElementById('country').value;
        const taxIdField = document.getElementById('tax_id');
        const taxIdLabel = document.getElementById('tax_id_label');
        const taxIdHelp = document.getElementById('tax_id_help');
        const phoneField = document.getElementById('phone');
        const cityField = document.getElementById('city');

        if (country && countryConfigs[country]) {
            const config = countryConfigs[country];
            
            // Actualizar etiquetas y placeholders
            taxIdLabel.textContent = config.tax_id_label;
            taxIdHelp.textContent = config.tax_id_help;
            phoneField.placeholder = config.phone_example;
            cityField.placeholder = config.city_example;

            // ELIMINADO: Auto-completar prefijo automático
            // NO forzamos ningún prefijo específico

            // NUEVA VALIDACIÓN: Verificar prefijos válidos
            taxIdField.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, ''); // Solo números
                
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }

                this.value = value;
                
                // Validación visual mejorada
                validateTaxIdFormat(value, config);
            });
        }
    }

    // NUEVA FUNCIÓN: Validar formato de CUIT/RUC
    function validateTaxIdFormat(value, config) {
        const taxIdField = document.getElementById('tax_id');
        const validationMessage = document.getElementById('tax_id_validation_message');
        
        // Crear elemento de mensaje si no existe
        if (!validationMessage) {
            const messageDiv = document.createElement('div');
            messageDiv.id = 'tax_id_validation_message';
            messageDiv.className = 'mt-1 text-sm';
            taxIdField.parentNode.appendChild(messageDiv);
        }

        if (value.length === 0) {
            // Campo vacío - estado neutral
            taxIdField.classList.remove('border-red-300', 'border-green-300');
            document.getElementById('tax_id_validation_message').innerHTML = '';
            return;
        }

        if (value.length >= 2) {
            const prefix = value.substring(0, 2);
            
            if (!config.valid_prefixes.includes(prefix)) {
                // Prefijo inválido
                taxIdField.classList.remove('border-green-300');
                taxIdField.classList.add('border-red-300');
                document.getElementById('tax_id_validation_message').innerHTML = 
                    `<span class="text-red-600">⚠️ Prefijo inválido. Use: ${config.valid_prefixes.join(', ')}</span>`;
                return false;
            }
        }

        if (value.length === 11) {
            const prefix = value.substring(0, 2);
            
            if (config.valid_prefixes.includes(prefix)) {
                // CUIT completo y válido
                taxIdField.classList.remove('border-red-300');
                taxIdField.classList.add('border-green-300');
                document.getElementById('tax_id_validation_message').innerHTML = 
                    '<span class="text-green-600">✓ Formato correcto</span>';
                return true;
            }
        } else if (value.length > 0 && value.length < 11) {
            // En progreso
            taxIdField.classList.remove('border-red-300', 'border-green-300');
            document.getElementById('tax_id_validation_message').innerHTML = 
                `<span class="text-gray-500">Faltan ${11 - value.length} dígitos</span>`;
        }

        return false;
    }

    // FUNCIÓN MEJORADA: Actualizar información de roles
    function updateRoleInfo() {
        const selectedRoles = Array.from(document.querySelectorAll('input[name="company_roles[]"]:checked')).map(cb => cb.value);
        const previewDiv = document.getElementById('rolePreview');
        const warningDiv = document.getElementById('roleWarning');

        if (selectedRoles.length === 0) {
            warningDiv.classList.remove('hidden');
            previewDiv.classList.add('hidden');
            return;
        } else {
            warningDiv.classList.add('hidden');
        }

        if (selectedRoles.length > 0) {
            const availableWebservices = selectedRoles.flatMap(role => roleWebservices[role] || []);
            const availableFeatures = selectedRoles.flatMap(role => roleFeatures[role] || []);
            const uniqueWebservices = [...new Set(availableWebservices)];
            const uniqueFeatures = [...new Set(availableFeatures)];

            previewDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h5 class="font-medium text-gray-700 mb-2">Webservices habilitados:</h5>
                        <ul class="space-y-1">
                            ${uniqueWebservices.map(ws => `<li class="text-green-600">• ${ws}</li>`).join('')}
                        </ul>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-700 mb-2">Funcionalidades disponibles:</h5>
                        <ul class="space-y-1">
                            ${uniqueFeatures.map(feature => `<li class="text-blue-600">• ${feature.replace('_', ' ')}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;
            previewDiv.classList.remove('hidden');
        } else {
            previewDiv.classList.add('hidden');
        }
    }

    // VALIDACIÓN MEJORADA del formulario antes del envío
    document.getElementById('companyForm').addEventListener('submit', function(e) {
        const selectedRoles = document.querySelectorAll('input[name="company_roles[]"]:checked');
        
        if (selectedRoles.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un rol para la empresa');
            document.getElementById('roleWarning').scrollIntoView({ behavior: 'smooth' });
            return false;
        }

        const taxId = document.getElementById('tax_id').value;
        const country = document.getElementById('country').value;
        
        if (country && taxId.length === 11) {
            const config = countryConfigs[country];
            const prefix = taxId.substring(0, 2);
            
            if (config && !config.valid_prefixes.includes(prefix)) {
                e.preventDefault();
                alert(`El CUIT/RUC debe empezar con uno de estos prefijos: ${config.valid_prefixes.join(', ')}`);
                document.getElementById('tax_id').focus();
                return false;
            }
        }
    });

    // Inicializar cuando la página carga
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        document.getElementById('country').addEventListener('change', updateCountryFields);
        
        // Configurar eventos para checkboxes de roles
        document.querySelectorAll('input[name="company_roles[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', updateRoleInfo);
        });

        // Restaurar estado si hay errores de validación
        const country = document.getElementById('country').value;
        if (country) {
            updateCountryFields();
        }
        
        updateRoleInfo();
    });
</script>
</x-app-layout>