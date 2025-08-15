<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.companies.show', $company) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Empresa') }} - {{ $company->legal_name }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.companies.update', $company) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Información General -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información General</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Razón Social -->
                            <div class="md:col-span-2">
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social *
                                </label>
                                <input type="text"
                                       name="legal_name"
                                       id="legal_name"
                                       value="{{ old('legal_name', $company->legal_name) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                       required>
                                @error('legal_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Nombre Comercial -->
                            <div class="md:col-span-2">
                                <label for="commercial_name" class="block text-sm font-medium text-gray-700">
                                    Nombre Comercial
                                </label>
                                <input type="text"
                                       name="commercial_name"
                                       id="commercial_name"
                                       value="{{ old('commercial_name', $company->commercial_name) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('commercial_name') border-red-300 @enderror">
                                @error('commercial_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CUIT -->
                            <div>
                                <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                    CUIT *
                                </label>
                                <input type="text"
                                       name="tax_id"
                                       id="tax_id"
                                       value="{{ old('tax_id', $company->tax_id) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('tax_id') border-red-300 @enderror"
                                       maxlength="11"
                                       pattern="[0-9]{11}"
                                       required>
                                @error('tax_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">11 dígitos sin guiones</p>
                            </div>

                            <!-- País -->
                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700">
                                    País *
                                </label>
                                <select name="country"
                                        id="country"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('country') border-red-300 @enderror"
                                        required>
                                    <option value="">Seleccionar país</option>
                                    <option value="AR" {{ old('country', $company->country) === 'AR' ? 'selected' : '' }}>Argentina</option>
                                    <option value="PY" {{ old('country', $company->country) === 'PY' ? 'selected' : '' }}>Paraguay</option>
                                </select>
                                @error('country')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="active" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select name="active"
                                        id="active"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" {{ old('active', $company->active) ? 'selected' : '' }}>Activa</option>
                                    <option value="0" {{ !old('active', $company->active) ? 'selected' : '' }}>Inactiva</option>
                                </select>
                                @error('active')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de Contacto</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email de Contacto
                                </label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       value="{{ old('email', $company->email) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-300 @enderror">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                    Teléfono
                                </label>
                                <input type="text"
                                       name="phone"
                                       id="phone"
                                       value="{{ old('phone', $company->phone) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('phone') border-red-300 @enderror">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Dirección -->
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-gray-700">
                                    Dirección
                                </label>
                                <input type="text"
                                       name="address"
                                       id="address"
                                       value="{{ old('address', $company->address) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('address') border-red-300 @enderror">
                                @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Ciudad -->
                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-700">
                                    Ciudad
                                </label>
                                <input type="text"
                                       name="city"
                                       id="city"
                                       value="{{ old('city', $company->city) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('city') border-red-300 @enderror">
                                @error('city')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Código Postal -->
                            <div>
                                <label for="postal_code" class="block text-sm font-medium text-gray-700">
                                    Código Postal
                                </label>
                                <input type="text"
                                       name="postal_code"
                                       id="postal_code"
                                       value="{{ old('postal_code', $company->postal_code) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('postal_code') border-red-300 @enderror">
                                @error('postal_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roles de empresa (NUEVA SECCIÓN) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Roles de Empresa</h3>
                        <p class="text-sm text-gray-600 mb-6">
                            Los roles determinan las funcionalidades disponibles y los webservices que puede usar la empresa.
                        </p>
                        
                        <div class="space-y-4">
                            @foreach($availableRoles as $role)
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                        name="company_roles[]" 
                                        id="role_{{ $role }}" 
                                        value="{{ $role }}"
                                        {{ in_array($role, old('company_roles', $company->getRoles())) ? 'checked' : '' }}
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

                        <!-- Vista previa de configuración -->
                        <div id="rolePreview" class="mt-6 hidden">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-800 mb-3">Configuración automática según roles:</h4>
                                <div id="rolePreviewContent" class="text-sm">
                                    <!-- Se llena dinámicamente con JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- Advertencia si no hay roles seleccionados -->
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

                        @error('company_roles')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <!-- Configuración de WebServices -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Configuración de WebServices</h3>
                            <a href="{{ route('admin.companies.webservices', $company) }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Configuración avanzada →
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Estado de WebServices -->
                            <div>
                                <label for="ws_active" class="block text-sm font-medium text-gray-700">
                                    Estado de WebServices
                                </label>
                                <select name="ws_active"
                                        id="ws_active"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" {{ old('ws_active', $company->ws_active) ? 'selected' : '' }}>Activo</option>
                                    <option value="0" {{ !old('ws_active', $company->ws_active) ? 'selected' : '' }}>Inactivo</option>
                                </select>
                                @error('ws_active')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Entorno de WebServices -->
                            <div>
                                <label for="ws_environment" class="block text-sm font-medium text-gray-700">
                                    Entorno
                                </label>
                                <select name="ws_environment"
                                        id="ws_environment"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Seleccionar entorno</option>
                                    <option value="testing" {{ old('ws_environment', $company->ws_environment) === 'testing' ? 'selected' : '' }}>Testing/Desarrollo</option>
                                    <option value="production" {{ old('ws_environment', $company->ws_environment) === 'production' ? 'selected' : '' }}>Producción</option>
                                </select>
                                @error('ws_environment')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Configuración JSON -->
                        <div class="mt-6">
                            <label for="ws_config" class="block text-sm font-medium text-gray-700">
                                Configuración Adicional (JSON)
                            </label>
                            <textarea name="ws_config"
                                      id="ws_config"
                                      rows="4"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('ws_config') border-red-300 @enderror"
                                      placeholder='{"timeout": 30, "retry_attempts": 3}'>{{ old('ws_config', is_array($company->ws_config) ? json_encode($company->ws_config, JSON_PRETTY_PRINT) : $company->ws_config) }}</textarea>
                            @error('ws_config')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Configuración en formato JSON válido</p>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <div class="text-sm text-blue-800">
                                <strong>Nota:</strong> Para configuraciones específicas de webservices (URLs, endpoints, etc.) utiliza la sección de configuración avanzada.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestión de Certificados -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Información de Certificados</h3>
                            <a href="{{ route('admin.companies.certificates', $company) }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Gestionar certificados →
                            </a>
                        </div>

                        @if($company->certificate_path)
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-green-800">Certificado Digital Configurado</h4>
                                        <div class="mt-2 text-sm text-green-700">
                                            @if($company->certificate_alias)
                                                <p><strong>Alias:</strong> {{ $company->certificate_alias }}</p>
                                            @endif
                                            @if($company->certificate_expires_at)
                                                <p>
                                                    <strong>Vencimiento:</strong> {{ $company->certificate_expires_at->format('d/m/Y') }}
                                                    @if($company->certificate_expires_at->isPast())
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                            Vencido
                                                        </span>
                                                    @elseif($company->certificate_expires_at->diffInDays() <= 30)
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                                                            Vence en {{ $company->certificate_expires_at->diffInDays() }} días
                                                        </span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos para actualizar información del certificado -->
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="certificate_alias" class="block text-sm font-medium text-gray-700">
                                        Alias del Certificado
                                    </label>
                                    <input type="text"
                                           name="certificate_alias"
                                           id="certificate_alias"
                                           value="{{ old('certificate_alias', $company->certificate_alias) }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>

                                <div>
                                    <label for="certificate_expires_at" class="block text-sm font-medium text-gray-700">
                                        Fecha de Vencimiento
                                    </label>
                                    <input type="date"
                                           name="certificate_expires_at"
                                           id="certificate_expires_at"
                                           value="{{ old('certificate_expires_at', $company->certificate_expires_at ? $company->certificate_expires_at->format('Y-m-d') : '') }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>
                        @else
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-yellow-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-medium text-yellow-800">No hay certificado configurado</h4>
                                        <p class="mt-1 text-sm text-yellow-700">
                                            Es necesario subir un certificado digital (.p12) para habilitar los webservices.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Fechas del Sistema -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Sistema</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Fecha de Registro -->
                            <div>
                                <label for="created_date" class="block text-sm font-medium text-gray-700">
                                    Fecha de Registro
                                </label>
                                <input type="date"
                                       name="created_date"
                                       id="created_date"
                                       value="{{ old('created_date', $company->created_date ? \Carbon\Carbon::parse($company->created_date)->format('Y-m-d') : '') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <!-- Información de solo lectura -->
                            <div class="space-y-2">
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Creado en el sistema:</span>
                                    <span class="text-sm text-gray-900">{{ $company->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Última modificación:</span>
                                    <span class="text-sm text-gray-900">{{ $company->updated_at->format('d/m/Y H:i') }}</span>
                                </div>
                                @if($company->last_access)
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Último acceso:</span>
                                        <span class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($company->last_access)->format('d/m/Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-3">
                                <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    Guardar Cambios
                                </button>
                                <a href="{{ route('admin.companies.show', $company) }}"
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                            </div>

                            <div class="text-right">
                                <button type="button"
                                        onclick="return confirm('¿Estás seguro de que quieres eliminar esta empresa? Esta acción eliminará también todos sus operadores y no se puede deshacer.') && document.getElementById('delete-form').submit()"
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Eliminar Empresa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Formulario de eliminación separado -->
            <form id="delete-form"
                  method="POST"
                  action="{{ route('admin.companies.destroy', $company) }}"
                  class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validación de CUIT
            const cuitField = document.getElementById('tax_id');
            cuitField.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, ''); // Solo números
                if (this.value.length > 11) {
                    this.value = this.value.substring(0, 11);
                }
            });

            // Validación de JSON en ws_config
            const wsConfigField = document.getElementById('ws_config');
            wsConfigField.addEventListener('blur', function() {
                if (this.value.trim()) {
                    try {
                        JSON.parse(this.value);
                        this.classList.remove('border-red-300');
                        this.classList.add('border-green-300');
                    } catch (e) {
                        this.classList.remove('border-green-300');
                        this.classList.add('border-red-300');
                        alert('El formato JSON no es válido');
                    }
                }
            });

            // Actualizar campos automáticamente según el país
            const countryField = document.getElementById('country');
            countryField.addEventListener('change', function() {
                const wsEnvironmentField = document.getElementById('ws_environment');

                // Sugerir configuración según el país
                if (this.value === 'AR') {
                    // Configuración para Argentina
                    console.log('País Argentina seleccionado');
                } else if (this.value === 'PY') {
                    // Configuración para Paraguay
                    console.log('País Paraguay seleccionado');
                }
            });

            // Advertencia al cambiar estado
            const activeField = document.getElementById('active');
            activeField.addEventListener('change', function() {
                if (this.value === '0') {
                    if (!confirm('¿Estás seguro de desactivar esta empresa? Esto afectará a todos sus operadores y usuarios asociados.')) {
                        this.value = '1';
                    }
                }
            });

            // Advertencia al activar WebServices sin certificado
            const wsActiveField = document.getElementById('ws_active');
            wsActiveField.addEventListener('change', function() {
                @if(!$company->certificate_path)
                if (this.value === '1') {
                    alert('Esta empresa no tiene certificado digital configurado. Los webservices no funcionarán correctamente sin un certificado válido.');
                }
                @endif
            });
        });
    </script>

    <script>
        // Config// Configuraciones de webservices según roles
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

        // FUNCIÓN: Actualizar información de roles
        function updateRoleInfo() {
            const selectedRoles = Array.from(document.querySelectorAll('input[name="company_roles[]"]:checked')).map(cb => cb.value);
            const previewDiv = document.getElementById('rolePreview');
            const previewContent = document.getElementById('rolePreviewContent');
            const warningDiv = document.getElementById('roleWarning');

            if (selectedRoles.length === 0) {
                if (warningDiv) warningDiv.classList.remove('hidden');
                if (previewDiv) previewDiv.classList.add('hidden');
                return;
            } else {
                if (warningDiv) warningDiv.classList.add('hidden');
            }

            if (selectedRoles.length > 0 && previewDiv && previewContent) {
                const availableWebservices = selectedRoles.flatMap(role => roleWebservices[role] || []);
                const availableFeatures = selectedRoles.flatMap(role => roleFeatures[role] || []);
                const uniqueWebservices = [...new Set(availableWebservices)];
                const uniqueFeatures = [...new Set(availableFeatures)];

                previewContent.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h5 class="font-medium text-blue-700 mb-2">Webservices habilitados:</h5>
                            <ul class="space-y-1">
                                ${uniqueWebservices.map(ws => `<li class="text-green-600">• ${ws}</li>`).join('')}
                            </ul>
                        </div>
                        <div>
                            <h5 class="font-medium text-blue-700 mb-2">Funcionalidades disponibles:</h5>
                            <ul class="space-y-1">
                                ${uniqueFeatures.map(feature => `<li class="text-blue-600">• ${feature.replace('_', ' ')}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
                previewDiv.classList.remove('hidden');
            } else if (previewDiv) {
                previewDiv.classList.add('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Validación de CUIT (mantener funcionalidad existente)
            const cuitField = document.getElementById('tax_id');
            if (cuitField) {
                cuitField.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, ''); // Solo números
                    if (this.value.length > 11) {
                        this.value = this.value.substring(0, 11);
                    }
                });
            }

            // Validación de JSON en ws_config (mantener funcionalidad existente)
            const wsConfigField = document.getElementById('ws_config');
            if (wsConfigField) {
                wsConfigField.addEventListener('blur', function() {
                    if (this.value.trim()) {
                        try {
                            JSON.parse(this.value);
                            this.classList.remove('border-red-300');
                            this.classList.add('border-green-300');
                        } catch (e) {
                            this.classList.remove('border-green-300');
                            this.classList.add('border-red-300');
                            alert('El formato JSON no es válido');
                        }
                    }
                });
            }

            // Actualizar campos automáticamente según el país (mantener funcionalidad existente)
            const countryField = document.getElementById('country');
            if (countryField) {
                countryField.addEventListener('change', function() {
                    const wsEnvironmentField = document.getElementById('ws_environment');

                    // Sugerir configuración según el país
                    if (this.value === 'AR') {
                        // Configuración para Argentina
                        console.log('País Argentina seleccionado');
                    } else if (this.value === 'PY') {
                        // Configuración para Paraguay
                        console.log('País Paraguay seleccionado');
                    }
                });
            }

            // Advertencia al cambiar estado (mantener funcionalidad existente)
            const activeField = document.getElementById('active');
            if (activeField) {
                activeField.addEventListener('change', function() {
                    if (this.value === '0') {
                        if (!confirm('¿Estás seguro de desactivar esta empresa? Esto afectará a todos sus operadores y usuarios asociados.')) {
                            this.value = '1';
                        }
                    }
                });
            }

            // Advertencia al activar WebServices sin certificado (mantener funcionalidad existente)
            const wsActiveField = document.getElementById('ws_active');
            if (wsActiveField) {
                wsActiveField.addEventListener('change', function() {
                    @if(!$company->certificate_path)
                    if (this.value === '1') {
                        alert('Esta empresa no tiene certificado digital configurado. Los webservices no funcionarán correctamente sin un certificado válido.');
                    }
                    @endif
                });
            }

            // NUEVA FUNCIONALIDAD: Configurar eventos para checkboxes de roles
            document.querySelectorAll('input[name="company_roles[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateRoleInfo);
            });

            // Inicializar la vista previa con los roles actuales
            updateRoleInfo();

            // NUEVA VALIDACIÓN: Validar roles en el formulario
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const selectedRoles = document.querySelectorAll('input[name="company_roles[]"]:checked');
                    
                    if (selectedRoles.length === 0) {
                        e.preventDefault();
                        alert('Debe seleccionar al menos un rol para la empresa');
                        const roleWarning = document.getElementById('roleWarning');
                        if (roleWarning) {
                            roleWarning.scrollIntoView({ behavior: 'smooth' });
                        }
                        return false;
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
