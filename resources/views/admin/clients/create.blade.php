<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Nuevo Cliente') }}
            </h2>
            <a href="{{ route('admin.clients.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Volver al Listado
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.clients.store') }}" id="client-form">
                        @csrf

                        <!-- Información Principal -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Principal</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- País -->
                                <div>
                                    <x-label for="country_id" value="{{ __('País') }}" />
                                    <select id="country_id" name="country_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                                            required onchange="updateCountryDependentFields()">
                                        <option value="">Seleccione un país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" 
                                                    data-code="{{ $country->alpha2_code }}"
                                                    {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="country_id" class="mt-2" />
                                </div>

                                <!-- CUIT/RUC -->
                                <div>
                                    <x-label for="tax_id" value="{{ __('CUIT/RUC') }}" />
                                    <x-input id="tax_id" 
                                           class="block mt-1 w-full" 
                                           type="text" 
                                           name="tax_id" 
                                           value="{{ old('tax_id') }}" 
                                           required 
                                           placeholder="Ej: 20-12345678-9"
                                           onkeyup="validateTaxId()" />
                                    <x-input-error for="tax_id" class="mt-2" />
                                    <div id="tax-id-validation" class="mt-2 text-sm"></div>
                                </div>

                                <!-- Razón Social -->
                                <div class="md:col-span-2">
                                    <x-label for="business_name" value="{{ __('Razón Social') }}" />
                                    <x-input id="business_name" 
                                           class="block mt-1 w-full" 
                                           type="text" 
                                           name="business_name" 
                                           value="{{ old('business_name') }}" 
                                           required 
                                           placeholder="Ingrese la razón social completa" />
                                    <x-input-error for="business_name" class="mt-2" />
                                </div>

                                <!-- Tipo de Cliente -->
                                <div>
                                    <x-label for="client_type" value="{{ __('Tipo de Cliente') }}" />
                                    <select id="client_type" name="client_type" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                                            required>
                                        <option value="">Seleccione un tipo</option>
                                        <option value="shipper" {{ old('client_type') === 'shipper' ? 'selected' : '' }}>Embarcador</option>
                                        <option value="consignee" {{ old('client_type') === 'consignee' ? 'selected' : '' }}>Consignatario</option>
                                        <option value="notify_party" {{ old('client_type') === 'notify_party' ? 'selected' : '' }}>Notificado</option>
                                        <option value="owner" {{ old('client_type') === 'owner' ? 'selected' : '' }}>Propietario</option>
                                    </select>
                                    <x-input-error for="client_type" class="mt-2" />
                                </div>

                                <!-- Tipo de Documento -->
                                <div>
                                    <x-label for="document_type_id" value="{{ __('Tipo de Documento') }}" />
                                    <select id="document_type_id" name="document_type_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Seleccione un tipo (opcional)</option>
                                        <!-- Se carga dinámicamente según el país -->
                                    </select>
                                    <x-input-error for="document_type_id" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Información Adicional -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Adicional</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Puerto Principal -->
                                <div>
                                    <x-label for="primary_port_id" value="{{ __('Puerto Principal') }}" />
                                    <select id="primary_port_id" name="primary_port_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Seleccione un puerto (opcional)</option>
                                        <!-- Se carga dinámicamente según el país -->
                                    </select>
                                    <x-input-error for="primary_port_id" class="mt-2" />
                                </div>

                                <!-- Aduana -->
                                <div>
                                    <x-label for="customs_offices_id" value="{{ __('Aduana') }}" />
                                    <select id="customs_offices_id" name="customs_offices_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Seleccione una aduana (opcional)</option>
                                        <!-- Se carga dinámicamente según el país -->
                                    </select>
                                    <x-input-error for="customs_offices_id" class="mt-2" />
                                </div>

                                <!-- Estado -->
                                <div>
                                    <x-label for="status" value="{{ __('Estado') }}" />
                                    <select id="status" name="status" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Activo</option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                    </select>
                                    <x-input-error for="status" class="mt-2" />
                                </div>

                                <!-- Empresa (Solo para super-admin) -->
                                @if(auth()->user()->hasRole('super-admin'))
                                    <div>
                                        <x-label for="company_id" value="{{ __('Empresa') }}" />
                                        <select id="company_id" name="company_id" 
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="">Seleccione una empresa</option>
                                            @foreach($companies as $company)
                                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                                    {{ $company->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error for="company_id" class="mt-2" />
                                    </div>
                                @endif

                                <!-- Observaciones -->
                                <div class="md:col-span-2">
                                    <x-label for="notes" value="{{ __('Observaciones') }}" />
                                    <textarea id="notes" 
                                            name="notes" 
                                            rows="3" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                                            placeholder="Información adicional sobre el cliente (opcional)">{{ old('notes') }}</textarea>
                                    <x-input-error for="notes" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('admin.clients.index') }}" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Crear Cliente
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para validación y carga dinámica -->
    <script>
        // Validación de CUIT/RUC
        function validateTaxId() {
            const taxIdInput = document.getElementById('tax_id');
            const countrySelect = document.getElementById('country_id');
            const validationDiv = document.getElementById('tax-id-validation');
            
            const taxId = taxIdInput.value.replace(/[^0-9]/g, '');
            const selectedCountry = countrySelect.options[countrySelect.selectedIndex];
            
            if (!selectedCountry || !selectedCountry.dataset.code) {
                validationDiv.innerHTML = '<span class="text-yellow-600">⚠️ Seleccione un país primero</span>';
                return;
            }
            
            const countryCode = selectedCountry.dataset.code;
            
            if (taxId.length === 0) {
                validationDiv.innerHTML = '';
                return;
            }
            
            if (countryCode === 'AR') {
                // Validación CUIT Argentina
                if (taxId.length !== 11) {
                    validationDiv.innerHTML = '<span class="text-red-600">❌ CUIT debe tener 11 dígitos</span>';
                    return;
                }
                
                // Validar dígito verificador
                const multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
                let sum = 0;
                for (let i = 0; i < 10; i++) {
                    sum += parseInt(taxId[i]) * multipliers[i];
                }
                const remainder = sum % 11;
                const checkDigit = remainder < 2 ? remainder : 11 - remainder;
                
                if (checkDigit == parseInt(taxId[10])) {
                    validationDiv.innerHTML = '<span class="text-green-600">✅ CUIT válido</span>';
                } else {
                    validationDiv.innerHTML = '<span class="text-red-600">❌ CUIT inválido (dígito verificador)</span>';
                }
            } else if (countryCode === 'PY') {
                // Validación RUC Paraguay
                if (taxId.length < 8 || taxId.length > 9) {
                    validationDiv.innerHTML = '<span class="text-red-600">❌ RUC debe tener entre 8 y 9 dígitos</span>';
                    return;
                }
                validationDiv.innerHTML = '<span class="text-green-600">✅ RUC válido</span>';
            } else {
                validationDiv.innerHTML = '<span class="text-blue-600">ℹ️ Formato no validado para este país</span>';
            }
        }

        // Cargar campos dependientes del país
        function updateCountryDependentFields() {
            const countryId = document.getElementById('country_id').value;
            
            if (!countryId) {
                clearDependentFields();
                return;
            }
            
            // Cargar tipos de documento
            loadDocumentTypes(countryId);
            
            // Cargar puertos
            loadPorts(countryId);
            
            // Cargar aduanas
            loadCustomsOffices(countryId);
            
            // Validar CUIT/RUC después de cambiar país
            validateTaxId();
        }

        function clearDependentFields() {
            document.getElementById('document_type_id').innerHTML = '<option value="">Seleccione un tipo (opcional)</option>';
            document.getElementById('primary_port_id').innerHTML = '<option value="">Seleccione un puerto (opcional)</option>';
            document.getElementById('customs_offices_id').innerHTML = '<option value="">Seleccione una aduana (opcional)</option>';
            document.getElementById('tax-id-validation').innerHTML = '';
        }

        function loadDocumentTypes(countryId) {
            fetch(`/api/form-data/document-types/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('document_type_id');
                    select.innerHTML = '<option value="">Seleccione un tipo (opcional)</option>';
                    data.forEach(item => {
                        select.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                    });
                })
                .catch(() => {
                    // Fallback en caso de error
                    document.getElementById('document_type_id').innerHTML = '<option value="">Error al cargar tipos</option>';
                });
        }

        function loadPorts(countryId) {
            fetch(`/api/form-data/ports/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('primary_port_id');
                    select.innerHTML = '<option value="">Seleccione un puerto (opcional)</option>';
                    data.forEach(item => {
                        select.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                    });
                })
                .catch(() => {
                    document.getElementById('primary_port_id').innerHTML = '<option value="">Error al cargar puertos</option>';
                });
        }

        function loadCustomsOffices(countryId) {
            fetch(`/api/form-data/customs-offices/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('customs_offices_id');
                    select.innerHTML = '<option value="">Seleccione una aduana (opcional)</option>';
                    data.forEach(item => {
                        select.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                    });
                })
                .catch(() => {
                    document.getElementById('customs_offices_id').innerHTML = '<option value="">Error al cargar aduanas</option>';
                });
        }

        // Inicializar en caso de que haya valores old() después de error de validación
        document.addEventListener('DOMContentLoaded', function() {
            const countryId = document.getElementById('country_id').value;
            if (countryId) {
                updateCountryDependentFields();
            }
        });
    </script>
</x-app-layout>