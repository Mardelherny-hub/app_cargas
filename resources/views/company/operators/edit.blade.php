<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar Operador') }} - {{ $operator->first_name }} {{ $operator->last_name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('company.operators.show', $operator) }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Ver Detalles
                </a>
                <a href="{{ route('company.operators.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver a Operadores
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('company.operators.update', $operator) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Información Personal -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Información Personal</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Actualice los datos básicos del operador.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">
                                    Nombre <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="first_name"
                                       id="first_name"
                                       value="{{ old('first_name', $operator->first_name) }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('first_name') border-red-300 @enderror">
                                @error('first_name')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Apellido -->
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">
                                    Apellido <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="last_name"
                                       id="last_name"
                                       value="{{ old('last_name', $operator->last_name) }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('last_name') border-red-300 @enderror">
                                @error('last_name')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Documento -->
                            <div>
                                <label for="document_number" class="block text-sm font-medium text-gray-700">
                                    Número de Documento
                                </label>
                                <input type="text"
                                       name="document_number"
                                       id="document_number"
                                       value="{{ old('document_number', $operator->document_number) }}"
                                       placeholder="DNI, Cédula, etc."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('document_number') border-red-300 @enderror">
                                @error('document_number')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
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
                                       value="{{ old('phone', $operator->phone) }}"
                                       placeholder="Ej: +54 11 1234-5678"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('phone') border-red-300 @enderror">
                                @error('phone')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Cargo -->
                            <div class="md:col-span-2">
                                <label for="position" class="block text-sm font-medium text-gray-700">
                                    Cargo/Posición
                                </label>
                                <input type="text"
                                       name="position"
                                       id="position"
                                       value="{{ old('position', $operator->position) }}"
                                       placeholder="Ej: Operador de Cargas, Supervisor, etc."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('position') border-red-300 @enderror">
                                @error('position')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Acceso -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Información de Acceso</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Actualice las credenciales para el acceso al sistema.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       value="{{ old('email', $operator->user?->email) }}"
                                       required
                                       placeholder="operador@empresa.com"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-300 @enderror">
                                @error('email')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="active" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select name="active"
                                        id="active"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('active') border-red-300 @enderror">
                                    <option value="1" {{ old('active', $operator->active) == '1' ? 'selected' : '' }}>Activo</option>
                                    <option value="0" {{ old('active', $operator->active) == '0' ? 'selected' : '' }}>Inactivo</option>
                                </select>
                                @error('active')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Contraseña -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Nueva Contraseña
                                </label>
                                <input type="password"
                                       name="password"
                                       id="password"
                                       minlength="8"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 @enderror">
                                <p class="mt-1 text-xs text-gray-500">Deje en blanco para mantener la contraseña actual. Mínimo 8 caracteres.</p>
                                @error('password')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirmar Contraseña -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                    Confirmar Nueva Contraseña
                                </label>
                                <input type="password"
                                       name="password_confirmation"
                                       id="password_confirmation"
                                       minlength="8"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Solo requerido si está cambiando la contraseña.</p>
                            </div>
                        </div>

                        <!-- Información del usuario actual -->
                        @if($operator->user)
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-blue-800">
                                                Información del usuario actual
                                            </h3>
                                            <div class="mt-2 text-sm text-blue-700 space-y-1">
                                                <p><strong>Último acceso:</strong> {{ $operator->user->last_access ? $operator->user->last_access->format('d/m/Y H:i') : 'Nunca' }}</p>
                                                <p><strong>Registrado:</strong> {{ $operator->user->created_at->format('d/m/Y H:i') }}</p>
                                                <p><strong>Zona horaria:</strong> {{ $operator->user->timezone ?? 'UTC' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Permisos del Operador -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Permisos del Operador</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Configure qué acciones puede realizar este operador en el sistema.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <!-- Permiso de Importación -->
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="can_import"
                                           name="can_import"
                                           type="checkbox"
                                           value="1"
                                           {{ old('can_import', $operator->can_import) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_import" class="font-medium text-gray-700">
                                        Puede Importar Datos
                                    </label>
                                    <p class="text-gray-500">
                                        Permite importar cargas desde archivos Excel, XML, EDI, etc.
                                    </p>
                                </div>
                            </div>

                            <!-- Permiso de Exportación -->
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="can_export"
                                           name="can_export"
                                           type="checkbox"
                                           value="1"
                                           {{ old('can_export', $operator->can_export) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_export" class="font-medium text-gray-700">
                                        Puede Exportar Datos
                                    </label>
                                    <p class="text-gray-500">
                                        Permite exportar datos a diferentes formatos y enviar a webservices.
                                    </p>
                                </div>
                            </div>

                            <!-- Permiso de Transferencia -->
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="can_transfer"
                                           name="can_transfer"
                                           type="checkbox"
                                           value="1"
                                           {{ old('can_transfer', $operator->can_transfer) ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="can_transfer" class="font-medium text-gray-700">
                                        Puede Transferir Cargas
                                    </label>
                                    <p class="text-gray-500">
                                        Permite transferir cargas entre diferentes empresas o operadores.
                                    </p>
                                </div>
                            </div>

                            <!-- Nota sobre permisos especiales -->
                            @if($operator->special_permissions && count($operator->special_permissions) > 0)
                                <div class="bg-purple-50 border border-purple-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-purple-800">
                                                Permisos Especiales Configurados
                                            </h3>
                                            <div class="mt-2 text-sm text-purple-700">
                                                <p>Este operador tiene permisos especiales configurados. Para modificarlos, use la sección dedicada de <a href="{{ route('company.operators.permissions', $operator) }}" class="underline font-medium">Gestión de Permisos</a>.</p>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach($operator->special_permissions as $permission)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                            {{ $permission }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                        <div class="flex space-x-3">
                            <a href="{{ route('company.operators.show', $operator) }}"
                               class="bg-white border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancelar
                            </a>
                            <a href="{{ route('company.operators.permissions', $operator) }}"
                               class="bg-purple-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                Gestionar Permisos
                            </a>
                        </div>
                        <button type="submit"
                                class="bg-blue-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Actualizar Operador
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Validación de contraseñas
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('password_confirmation');

            function validatePasswords() {
                if (passwordField.value === '' && confirmPasswordField.value === '') {
                    // Si ambos están vacíos, no hay problema
                    confirmPasswordField.setCustomValidity('');
                    return;
                }

                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }

            // Validar cuando se cambie cualquier campo de contraseña
            passwordField.addEventListener('input', validatePasswords);
            confirmPasswordField.addEventListener('input', validatePasswords);

            // Validar antes de enviar el formulario
            document.querySelector('form').addEventListener('submit', function(e) {
                validatePasswords();
                if (!confirmPasswordField.checkValidity()) {
                    e.preventDefault();
                    confirmPasswordField.reportValidity();
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
