<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar Cliente') }} - {{ $client->business_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.show', $client) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Ver Cliente
                </a>
                <a href="{{ route('admin.clients.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Información de estado del cliente -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-500">Estado:</span>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 
                                       ($client->status === 'inactive' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($client->status) }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-500">Verificación:</span>
                                @if($client->verified_at)
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        ✅ Verificado
                                    </span>
                                @else
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        ⏳ Pendiente
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            <div>Creado: {{ $client->created_at->format('d/m/Y H:i') }}</div>
                            <div>Actualizado: {{ $client->updated_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de edición -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.clients.update', $client) }}" id="client-form">
                        @csrf
                        @method('PUT')

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
                                                    {{ (old('country_id', $client->country_id) == $country->id) ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="country_id" class="mt-2" />
                                </div>

                                <!-- CUIT/RUC -->
                                <div>
                                    <x-label for="tax_id" value="{{ __('CUIT/RUC') }}" />
                                    <div class="relative">
                                        <x-input id="tax_id" 
                                               class="block mt-1 w-full pr-10" 
                                               type="text" 
                                               name="tax_id" 
                                               value="{{ old('tax_id', $client->tax_id) }}" 
                                               required 
                                               placeholder="Ej: 20-12345678-9"
                                               onkeyup="validateTaxId()" />
                                        @if($client->verified_at)
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <x-input-error for="tax_id" class="mt-2" />
                                    <div id="tax-id-validation" class="mt-2 text-sm"></div>
                                    @if($client->verified_at)
                                        <div class="mt-1 text-sm text-green-600">
                                            Verificado el {{ $client->verified_at->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Razón Social -->
                                <div class="md:col-span-2">
                                    <x-label for="business_name" value="{{ __('Razón Social') }}" />
                                    <x-input id="business_name" 
                                           class="block mt-1 w-full" 
                                           type="text" 
                                           name="business_name" 
                                           value="{{ old('business_name', $client->business_name) }}" 
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
                                        <option value="shipper" {{ old('client_type', $client->client_type) === 'shipper' ? 'selected' : '' }}>Embarcador</option>
                                        <option value="consignee" {{ old('client_type', $client->client_type) === 'consignee' ? 'selected' : '' }}>Consignatario</option>
                                        <option value="notify_party" {{ old('client_type', $client->client_type) === 'notify_party' ? 'selected' : '' }}>Notificado</option>
                                        <option value="owner" {{ old('client_type', $client->client_type) === 'owner' ? 'selected' : '' }}>Propietario</option>
                                    </select>
                                    <x-input-error for="client_type" class="mt-2" />
                                </div>

                                <!-- Tipo de Documento -->
                                <div>
                                    <x-label for="document_type_id" value="{{ __('Tipo de Documento') }}" />
                                    <select id="document_type_id" name="document_type_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Seleccione un tipo (opcional)</option>
                                        @foreach($documentTypes as $documentType)
                                            <option value="{{ $documentType->id }}" 
                                                    {{ old('document_type_id', $client->document_type_id) == $documentType->id ? 'selected' : '' }}>
                                                {{ $documentType->name }}
                                            </option>
                                        @endforeach
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
                                        @foreach($ports as $port)
                                            <option value="{{ $port->id }}" 
                                                    {{ old('primary_port_id', $client->primary_port_id) == $port->id ? 'selected' : '' }}>
                                                {{ $port->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="primary_port_id" class="mt-2" />
                                </div>

                                <!-- Aduana -->
                                <div>
                                    <x-label for="customs_offices_id" value="{{ __('Aduana') }}" />
                                    <select id="customs_offices_id" name="customs_offices_id" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">Seleccione una aduana (opcional)</option>
                                        @foreach($customsOffices as $customsOffice)
                                            <option value="{{ $customsOffice->id }}" 
                                                    {{ old('customs_offices_id', $client->customs_offices_id) == $customsOffice->id ? 'selected' : '' }}>
                                                {{ $customsOffice->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error for="customs_offices_id" class="mt-2" />
                                </div>

                                <!-- Estado -->
                                <div>
                                    <x-label for="status" value="{{ __('Estado') }}" />
                                    <select id="status" name="status" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="active" {{ old('status', $client->status) === 'active' ? 'selected' : '' }}>Activo</option>
                                        <option value="inactive" {{ old('status', $client->status) === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                        <option value="suspended" {{ old('status', $client->status) === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                    </select>
                                    <x-input-error for="status" class="mt-2" />
                                </div>

                                <!-- Empresas relacionadas -->
                                <div>
                                    <x-label value="{{ __('Empresas Relacionadas') }}" />
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse($client->companyRelations as $relation)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                {{ $relation->company->business_name }}
                                                <span class="ml-1 text-xs">({{ ucfirst($relation->relation_type) }})</span>
                                            </span>
                                        @empty
                                            <span class="text-sm text-gray-500">Sin empresas relacionadas</span>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Observaciones -->
                                <div class="md:col-span-2">
                                    <x-label for="notes" value="{{ __('Observaciones') }}" />
                                    <textarea id="notes" 
                                            name="notes" 
                                            rows="3" 
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                                            placeholder="Información adicional sobre el cliente (opcional)">{{ old('notes', $client->notes) }}</textarea>
                                    <x-input-error for="notes" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Acciones adicionales para super-admin -->
                        @if(auth()->user()->hasRole('super-admin'))
                            <div class="mb-8">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones Administrativas</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    
                                    <!-- Verificar manualmente -->
                                    @if(!$client->verified_at)
                                        <div class="flex items-center">
                                            <input type="checkbox" id="mark_verified" name="mark_verified" 
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <label for="mark_verified" class="ml-2 text-sm text-gray-600">
                                                Marcar como verificado
                                            </label>
                                        </div>
                                    @endif

                                    <!-- Reverificar -->
                                    <div class="flex items-center">
                                        <input type="checkbox" id="reverify" name="reverify" 
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <label for="reverify" class="ml-2 text-sm text-gray-600">
                                            Reverificar CUIT/RUC
                                        </label>
                                    </div>

                                </div>
                            </div>
                        @endif

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-between mt-6">
                            <div class="flex space-x-3">
                                <a href="{{ route('admin.clients.show', $client) }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                                <a href="{{ route('admin.clients.index') }}" 
                                   class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Volver al Listado
                                </a>
                            </div>
                            <div class="flex space-x-3">
                                @if(auth()->user()->hasRole('super-admin'))
                                    <button type="button" 
                                            onclick="toggleClientStatus()"
                                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }}
                                    </button>
                                @endif
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Actualizar Cliente
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para validación y funcionalidad -->
    <script>
        // Validación de CUIT/RUC (similar a create)
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

        function loadDocumentTypes(countryId) {
            fetch(`/api/form-data/document-types/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('document_type_id');
                    const currentValue = select.value;
                    select.innerHTML = '<option value="">Seleccione un tipo (opcional)</option>';
                    data.forEach(item => {
                        const selected = item.id == currentValue ? 'selected' : '';
                        select.innerHTML += `<option value="${item.id}" ${selected}>${item.name}</option>`;
                    });
                })
                .catch(() => {
                    // Manejo de errores silencioso
                });
        }

        function loadPorts(countryId) {
            fetch(`/api/form-data/ports/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('primary_port_id');
                    const currentValue = select.value;
                    select.innerHTML = '<option value="">Seleccione un puerto (opcional)</option>';
                    data.forEach(item => {
                        const selected = item.id == currentValue ? 'selected' : '';
                        select.innerHTML += `<option value="${item.id}" ${selected}>${item.name}</option>`;
                    });
                })
                .catch(() => {
                    // Manejo de errores silencioso
                });
        }

        function loadCustomsOffices(countryId) {
            fetch(`/api/form-data/customs-offices/${countryId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('customs_offices_id');
                    const currentValue = select.value;
                    select.innerHTML = '<option value="">Seleccione una aduana (opcional)</option>';
                    data.forEach(item => {
                        const selected = item.id == currentValue ? 'selected' : '';
                        select.innerHTML += `<option value="${item.id}" ${selected}>${item.name}</option>`;
                    });
                })
                .catch(() => {
                    // Manejo de errores silencioso
                });
        }

        // Función para cambiar estado del cliente
        function toggleClientStatus() {
            const clientId = {{ $client->id }};
            const currentStatus = '{{ $client->status }}';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (confirm(`¿Está seguro de que desea ${newStatus === 'active' ? 'activar' : 'desactivar'} este cliente?`)) {
                fetch(`/admin/clients/${clientId}/toggle-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al cambiar el estado del cliente');
                    }
                })
                .catch(() => {
                    alert('Error al cambiar el estado del cliente');
                });
            }
        }

        // Inicializar validación al cargar
        document.addEventListener('DOMContentLoaded', function() {
            validateTaxId();
        });
    </script>
</x-app-layout>