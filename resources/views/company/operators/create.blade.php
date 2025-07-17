<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Operador') }} - {{ $company->legal_name }}
            </h2>
            <div class="text-sm text-gray-600">
                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                    {{ implode(', ', $company->company_roles ?? []) }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Información Contextual -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">{{ __('Crear Operador para') }} {{ $company->legal_name }}</h3>
                        <div class="mt-1 text-sm text-blue-700">
                            <p>{{ __('Los operadores pueden realizar operaciones según los roles de empresa:') }} <strong>{{ implode(', ', $company->company_roles ?? []) }}</strong></p>
                            <p class="mt-1">{{ __('Cada operador tendrá permisos específicos que puede configurar a continuación.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('company.operators.store') }}" class="space-y-6">
                @csrf

                <!-- Información Personal -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Información Personal') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Datos personales del operador.') }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">
                                    {{ __('Nombre') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="first_name"
                                       id="first_name"
                                       value="{{ old('first_name') }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('first_name') border-red-300 @enderror"
                                       placeholder="{{ __('Ingrese el nombre') }}">
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Apellido -->
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">
                                    {{ __('Apellido') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="last_name"
                                       id="last_name"
                                       value="{{ old('last_name') }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('last_name') border-red-300 @enderror"
                                       placeholder="{{ __('Ingrese el apellido') }}">
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Número de Documento -->
                            <div>
                                <label for="document_number" class="block text-sm font-medium text-gray-700">
                                    {{ __('Número de Documento') }}
                                </label>
                                <input type="text"
                                       name="document_number"
                                       id="document_number"
                                       value="{{ old('document_number') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('document_number') border-red-300 @enderror"
                                       placeholder="Ej: 12345678">
                                @error('document_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                    {{ __('Teléfono') }}
                                </label>
                                <input type="text"
                                       name="phone"
                                       id="phone"
                                       value="{{ old('phone') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-300 @enderror"
                                       placeholder="Ej: +54 11 1234-5678">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Cargo/Posición -->
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700">
                                    {{ __('Cargo/Posición') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="position"
                                       id="position"
                                       value="{{ old('position') }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('position') border-red-300 @enderror"
                                       placeholder="Ej: Operador de Cargas, Jefe de Operaciones">
                                @error('position')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- CORREGIDO: Campo tipo oculto, siempre external -->
                        <input type="hidden" name="type" value="external">

                        <!-- Información del tipo (solo informativo) -->
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800">{{ __('Operador de Empresa') }}</h4>
                                    <p class="mt-1 text-sm text-green-700">
                                        {{ __('Este operador será empleado específico de') }} <strong>{{ $company->legal_name }}</strong> {{ __('y solo podrá trabajar con esta empresa.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Acceso -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Acceso al Sistema') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Configure las credenciales de acceso del operador.') }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    {{ __('Email') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       value="{{ old('email') }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-300 @enderror"
                                       placeholder="usuario@empresa.com">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Contraseña -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    {{ __('Contraseña') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="password"
                                       name="password"
                                       id="password"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-300 @enderror"
                                       placeholder="{{ __('Mínimo 8 caracteres') }}">
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirmar Contraseña -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                    {{ __('Confirmar Contraseña') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="password"
                                       name="password_confirmation"
                                       id="password_confirmation"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos del Operador -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Permisos del Operador') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Configure qué operaciones puede realizar este operador.') }}</p>
                    </div>
                    <div class="px-6 py-4 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Importar -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox"
                                           name="can_import"
                                           id="can_import"
                                           value="1"
                                           {{ old('can_import') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_import" class="font-medium text-gray-700">{{ __('Puede Importar') }}</label>
                                    <p class="text-gray-500">{{ __('Crear y gestionar cargas de importación') }}</p>
                                </div>
                            </div>

                            <!-- Exportar -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox"
                                           name="can_export"
                                           id="can_export"
                                           value="1"
                                           {{ old('can_export') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_export" class="font-medium text-gray-700">{{ __('Puede Exportar') }}</label>
                                    <p class="text-gray-500">{{ __('Crear y gestionar cargas de exportación') }}</p>
                                </div>
                            </div>

                            <!-- Transferir -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox"
                                           name="can_transfer"
                                           id="can_transfer"
                                           value="1"
                                           {{ old('can_transfer') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_transfer" class="font-medium text-gray-700">{{ __('Puede Transferir') }}</label>
                                    <p class="text-gray-500">{{ __('Realizar transbordos entre empresas') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Estado Activo -->
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox"
                                       name="active"
                                       id="active"
                                       value="1"
                                       {{ old('active', true) ? 'checked' : '' }}
                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="active" class="font-medium text-gray-700">{{ __('Usuario Activo') }}</label>
                                <p class="text-gray-500">{{ __('El operador puede acceder al sistema') }}</p>
                            </div>
                        </div>

                        <!-- Validación de Permisos -->
                        <div id="permissions-warning" class="hidden bg-amber-50 border border-amber-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-amber-800">{{ __('Permisos Requeridos') }}</h3>
                                    <p class="mt-1 text-sm text-amber-700">
                                        {{ __('El operador debe tener al menos un permiso (importar, exportar o transferir).') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                        <a href="{{ route('company.operators.index') }}"
                           class="bg-white border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Cancelar') }}
                        </a>
                        <button type="submit"
                                class="bg-blue-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Crear Operador') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const permissionCheckboxes = document.querySelectorAll('input[name="can_import"], input[name="can_export"], input[name="can_transfer"]');
            const permissionsWarning = document.getElementById('permissions-warning');

            // Validar que al menos un permiso esté seleccionado
            function validatePermissions() {
                const hasPermission = Array.from(permissionCheckboxes).some(checkbox => checkbox.checked);

                if (hasPermission) {
                    permissionsWarning.classList.add('hidden');
                } else {
                    permissionsWarning.classList.remove('hidden');
                }

                return hasPermission;
            }

            // Escuchar cambios en los checkboxes de permisos
            permissionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', validatePermissions);
            });

            // Validar antes de enviar el formulario
            form.addEventListener('submit', function(e) {
                if (!validatePermissions()) {
                    e.preventDefault();
                    permissionsWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            // Validación inicial
            validatePermissions();
        });
    </script>
    @endpush
</x-app-layout>
