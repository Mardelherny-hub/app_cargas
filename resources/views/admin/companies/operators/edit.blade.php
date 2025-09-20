<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Operador
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $operator->first_name }} {{ $operator->last_name }} • {{ $company->legal_name }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.companies.operators.show', [$company, $operator]) }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Ver Detalles
                </a>
                <a href="{{ route('admin.companies.operators', $company) }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Breadcrumb -->
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-700 hover:text-gray-900">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('admin.companies.index') }}" class="ml-1 text-gray-700 hover:text-gray-900 md:ml-2">
                                Empresas
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('admin.companies.show', $company) }}" class="ml-1 text-gray-700 hover:text-gray-900 md:ml-2">
                                {{ $company->legal_name }}
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('admin.companies.operators', $company) }}" class="ml-1 text-gray-700 hover:text-gray-900 md:ml-2">
                                Operadores
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('admin.companies.operators.show', [$company, $operator]) }}" class="ml-1 text-gray-700 hover:text-gray-900 md:ml-2">
                                {{ $operator->first_name }} {{ $operator->last_name }}
                            </a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">Editar</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Mensajes de Validación -->
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Se encontraron los siguientes errores:
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

            <!-- Formulario Principal -->
            <form method="POST" action="{{ route('admin.companies.operators.update', [$company, $operator]) }}" id="operatorForm" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Información Personal -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Información Personal</h3>
                        <p class="mt-1 text-sm text-gray-600">Actualice los datos básicos del operador.</p>
                    </div>
                    <div class="px-6 py-4 space-y-6">
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
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
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
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
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
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('document_number')
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
                                       value="{{ old('phone', $operator->phone) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Cargo/Posición -->
                            <div class="md:col-span-2">
                                <label for="position" class="block text-sm font-medium text-gray-700">
                                    Cargo/Posición
                                </label>
                                <input type="text" 
                                       name="position" 
                                       id="position" 
                                       value="{{ old('position', $operator->position) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('position')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo (Solo mostrar, no editable) -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Tipo de Operador
                                </label>
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-blue-100 text-blue-800">
                                        Externo
                                    </span>
                                    <p class="mt-1 text-xs text-gray-500">
                                        El tipo de operador no puede ser modificado. Todos los operadores de empresas son externos.
                                    </p>
                                </div>
                                <!-- Campo oculto para mantener el tipo -->
                                <input type="hidden" name="type" value="external">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Usuario -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Información de Usuario</h3>
                        <p class="mt-1 text-sm text-gray-600">Configure el acceso al sistema del operador.</p>
                    </div>
                    <div class="px-6 py-4 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       value="{{ old('email', $operator->user->email ?? '') }}"
                                       required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Estado del Operador
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               name="active" 
                                               id="active"
                                               value="1"
                                               {{ old('active', $operator->active) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-900">Operador activo</span>
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Si se desactiva, el operador no podrá acceder al sistema.
                                    </p>
                                </div>
                                @error('active')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
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
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">
                                    Dejar en blanco para mantener la contraseña actual.
                                </p>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirmar Contraseña -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                    Confirmar Contraseña
                                </label>
                                <input type="password" 
                                       name="password_confirmation" 
                                       id="password_confirmation" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-xs text-gray-500">
                                    Solo requerido si cambia la contraseña.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos Operativos -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Permisos Operativos</h3>
                        <p class="mt-1 text-sm text-gray-600">Configure los permisos del operador. <span class="text-red-500">Al menos un permiso es requerido.</span></p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Permiso Importar -->
                            <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-lg {{ old('can_import', $operator->can_import) ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center">
                                        <svg class="h-6 w-6 {{ old('can_import', $operator->can_import) ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               name="can_import" 
                                               id="can_import"
                                               value="1"
                                               {{ old('can_import', $operator->can_import) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 permission-checkbox">
                                        <span class="ml-2">
                                            <p class="text-sm font-medium text-gray-900">Puede Importar</p>
                                            <p class="text-sm text-gray-500">Gestionar cargas de importación</p>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Permiso Exportar -->
                            <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-lg {{ old('can_export', $operator->can_export) ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center">
                                        <svg class="h-6 w-6 {{ old('can_export', $operator->can_export) ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               name="can_export" 
                                               id="can_export"
                                               value="1"
                                               {{ old('can_export', $operator->can_export) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 permission-checkbox">
                                        <span class="ml-2">
                                            <p class="text-sm font-medium text-gray-900">Puede Exportar</p>
                                            <p class="text-sm text-gray-500">Gestionar cargas de exportación</p>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Permiso Transferir -->
                            <div class="relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm flex items-center space-x-3 hover:border-gray-400 focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-lg {{ old('can_transfer', $operator->can_transfer) ? 'bg-green-100' : 'bg-gray-100' }} flex items-center justify-center">
                                        <svg class="h-6 w-6 {{ old('can_transfer', $operator->can_transfer) ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" 
                                               name="can_transfer" 
                                               id="can_transfer"
                                               value="1"
                                               {{ old('can_transfer', $operator->can_transfer) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 permission-checkbox">
                                        <span class="ml-2">
                                            <p class="text-sm font-medium text-gray-900">Puede Transferir</p>
                                            <p class="text-sm text-gray-500">Gestionar transbordos y transferencias</p>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Mensaje de error para permisos -->
                        <div id="permissions-error" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-700">
                                <svg class="inline h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                El operador debe tener al menos un permiso operativo.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.companies.operators.show', [$company, $operator]) }}"
                       class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Actualizar Operador
                    </button>
                </div>
            </form>

        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('operatorForm');
                const passwordField = document.getElementById('password');
                const passwordConfirmField = document.getElementById('password_confirmation');
                const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
                const permissionsError = document.getElementById('permissions-error');

                // Validación de contraseñas
                function validatePasswords() {
                    const password = passwordField.value;
                    const passwordConfirm = passwordConfirmField.value;

                    if (password && password !== passwordConfirm) {
                        passwordConfirmField.setCustomValidity('Las contraseñas no coinciden');
                    } else {
                        passwordConfirmField.setCustomValidity('');
                    }
                }

                // Validación de permisos
                function validatePermissions() {
                    const hasPermission = Array.from(permissionCheckboxes).some(checkbox => checkbox.checked);
                    
                    if (!hasPermission) {
                        permissionsError.classList.remove('hidden');
                        permissionCheckboxes.forEach(checkbox => {
                            checkbox.setCustomValidity('Debe seleccionar al menos un permiso operativo');
                        });
                        return false;
                    } else {
                        permissionsError.classList.add('hidden');
                        permissionCheckboxes.forEach(checkbox => {
                            checkbox.setCustomValidity('');
                        });
                        return true;
                    }
                }

                // Actualizar iconos de permisos
                function updatePermissionIcons() {
                    permissionCheckboxes.forEach(checkbox => {
                        const container = checkbox.closest('.relative');
                        const icon = container.querySelector('.h-10.w-10');
                        const svg = icon.querySelector('svg');
                        
                        if (checkbox.checked) {
                            icon.className = icon.className.replace('bg-gray-100', 'bg-green-100');
                            svg.className = svg.className.replace('text-gray-400', 'text-green-600');
                        } else {
                            icon.className = icon.className.replace('bg-green-100', 'bg-gray-100');
                            svg.className = svg.className.replace('text-green-600', 'text-gray-400');
                        }
                    });
                }

                // Event listeners
                passwordField.addEventListener('input', validatePasswords);
                passwordConfirmField.addEventListener('input', validatePasswords);

                permissionCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        validatePermissions();
                        updatePermissionIcons();
                    });
                });

                // Validación del formulario
                form.addEventListener('submit', function(e) {
                    validatePasswords();
                    
                    if (!validatePermissions()) {
                        e.preventDefault();
                        permissionCheckboxes[0].focus();
                        return false;
                    }

                    // Verificar si hay algún error de validación
                    const invalidFields = form.querySelectorAll(':invalid');
                    if (invalidFields.length > 0) {
                        e.preventDefault();
                        invalidFields[0].focus();
                        invalidFields[0].reportValidity();
                    }
                });

                // Ejecutar validaciones iniciales
                validatePermissions();
                updatePermissionIcons();
            });
        </script>
    @endpush
</x-app-layout>