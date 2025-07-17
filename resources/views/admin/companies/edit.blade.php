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
    @endpush
</x-app-layout>
