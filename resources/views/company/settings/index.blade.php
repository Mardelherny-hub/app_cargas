<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            ⚙️ {{ __('Configuración de Empresa') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <!-- Encabezado de configuración -->
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Configuración General</h3>
                        <p class="text-gray-600">
                            Administra la información básica y configuraciones específicas de tu empresa.
                        </p>
                    </div>

                    @if(session('success'))
                        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            <span class="block sm:inline">{{ session('success') }}</span>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <!-- Formulario de configuración general -->
                    <form method="POST" action="{{ route('company.settings.update-general') }}" class="space-y-8">
                        @csrf
                        @method('PUT')

                        <!-- Información básica de la empresa -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Información Básica</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Razón Social -->
                                <div>
                                    <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                        Razón Social *
                                    </label>
                                    <input type="text"
                                           name="legal_name"
                                           id="legal_name"
                                           value="{{ old('legal_name', $currentSettings['general']['legal_name']) }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                           required>
                                    @error('legal_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Nombre Comercial -->
                                <div>
                                    <label for="commercial_name" class="block text-sm font-medium text-gray-700">
                                        Nombre Comercial
                                    </label>
                                    <input type="text"
                                           name="commercial_name"
                                           id="commercial_name"
                                           value="{{ old('commercial_name', $currentSettings['general']['commercial_name']) }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('commercial_name') border-red-300 @enderror">
                                    @error('commercial_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">
                                        Email de Contacto *
                                    </label>
                                    <input type="email"
                                           name="email"
                                           id="email"
                                           value="{{ old('email', $currentSettings['general']['email']) }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-300 @enderror"
                                           required>
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
                                           value="{{ old('phone', $currentSettings['general']['phone']) }}"
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
                                           value="{{ old('address', $currentSettings['general']['address']) }}"
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
                                           value="{{ old('city', $currentSettings['general']['city']) }}"
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
                                           value="{{ old('postal_code', $currentSettings['general']['postal_code']) }}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('postal_code') border-red-300 @enderror">
                                    @error('postal_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Configuración específica de webservices -->
                        <div class="bg-blue-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">🌐 Configuración de Webservices</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- ID María para MANE/Malvina -->
                                <div class="md:col-span-2">
                                    <label for="id_maria" class="block text-sm font-medium text-gray-700">
                                        🏷️ ID María (MANE/Malvina)
                                    </label>
                                    <input type="text"
                                           name="id_maria"
                                           id="id_maria"
                                           maxlength="10"
                                           value="{{ old('id_maria', $currentSettings['general']['id_maria'] ?? '') }}"
                                           placeholder="Ej: MAR001"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('id_maria') border-red-300 @enderror">
                                    <p class="mt-1 text-xs text-gray-500">
                                        ID único utilizado para identificar a su empresa en el sistema Malvina de Aduana Argentina. 
                                        <strong>Requerido para usar MANE.</strong> Máximo 10 caracteres alfanuméricos.
                                    </p>
                                    @error('id_maria')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Información de roles de empresa (solo lectura) -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        🎯 Roles de Empresa Activos
                                    </label>
                                    <div class="bg-white border border-gray-300 rounded-md p-3">
                                        @if(!empty($currentSettings['business_roles']))
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($currentSettings['business_roles'] as $role)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                        @if($role === 'Cargas') bg-blue-100 text-blue-800
                                                        @elseif($role === 'Desconsolidador') bg-green-100 text-green-800  
                                                        @elseif($role === 'Transbordos') bg-purple-100 text-purple-800
                                                        @else bg-gray-100 text-gray-800 @endif">
                                                        {{ $role }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-500 text-sm">No hay roles asignados</p>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Los roles determinan qué webservices puede usar su empresa. Para cambiar roles, contacte al administrador del sistema.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="{{ route('company.dashboard') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium transition duration-150 ease-in-out">
                                💾 Guardar Configuración
                            </button>
                        </div>
                    </form>

                    <!-- Información adicional -->
                    <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Información Importante
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li><strong>ID María:</strong> Es obligatorio para usar el webservice MANE. Sin este ID, no podrá enviar manifiestos al sistema Malvina.</li>
                                        <li><strong>Datos fiscales:</strong> El CUIT y país no se pueden modificar desde aquí. Contacte al administrador si necesita cambiarlos.</li>
                                        <li><strong>Webservices:</strong> Su empresa debe tener certificados digitales válidos para usar los webservices de aduana.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas de configuración -->
                    <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-green-800">Operadores</h4>
                            <p class="text-2xl font-bold text-green-900">{{ $configStats['operators_count'] }}</p>
                            <p class="text-xs text-green-600">{{ $configStats['active_operators'] }} activos</p>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800">Webservices</h4>
                            <p class="text-2xl font-bold text-blue-900">{{ $configStats['webservice_status'] === 'active' ? 'Activo' : 'Inactivo' }}</p>
                            <p class="text-xs text-blue-600">Estado general</p>
                        </div>
                        
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-purple-800">Certificados</h4>
                            <p class="text-2xl font-bold text-purple-900">{{ $configStats['certificate_status']['message'] ?? 'N/A' }}</p>
                            <p class="text-xs text-purple-600">Estado actual</p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-800">ID María</h4>
                            <p class="text-2xl font-bold text-gray-900">{{ !empty($currentSettings['general']['id_maria']) ? 'Configurado' : 'Pendiente' }}</p>
                            <p class="text-xs text-gray-600">Para MANE</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validación en tiempo real del ID María
            const idMariaField = document.getElementById('id_maria');
            
            if (idMariaField) {
                idMariaField.addEventListener('input', function() {
                    let value = this.value.toUpperCase();
                    
                    // Solo permitir letras mayúsculas y números
                    value = value.replace(/[^A-Z0-9]/g, '');
                    
                    // Limitar a 10 caracteres
                    if (value.length > 10) {
                        value = value.substring(0, 10);
                    }
                    
                    this.value = value;
                    
                    // Feedback visual
                    if (value.length > 0 && value.length <= 10) {
                        this.classList.remove('border-red-300');
                        this.classList.add('border-green-300');
                    } else if (value.length > 10) {
                        this.classList.remove('border-green-300');
                        this.classList.add('border-red-300');
                    } else {
                        this.classList.remove('border-red-300', 'border-green-300');
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>