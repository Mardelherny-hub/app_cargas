<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Operador') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $operator->full_name }} - {{ $company->legal_name }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('company.operators.show', $operator) }}"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    {{ __('Ver Detalles') }}
                </a>
                <a href="{{ route('company.operators.index') }}"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    {{ __('Volver al Listado') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Mostrar errores de validación -->
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">{{ __('Hay errores en el formulario:') }}</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul role="list" class="list-disc pl-5 space-y-1">
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
            <form method="POST" action="{{ route('company.operators.update', $operator) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Información Personal -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Información Personal') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Actualice los datos básicos del operador.') }}</p>
                    </div>

                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">
                                    {{ __('Nombre') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="first_name" id="first_name"
                                    value="{{ old('first_name', $operator->first_name) }}" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('first_name') border-red-300 @enderror"
                                    placeholder="Ingrese el nombre">
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Apellido -->
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">
                                    {{ __('Apellido') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="last_name" id="last_name"
                                    value="{{ old('last_name', $operator->last_name) }}" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('last_name') border-red-300 @enderror"
                                    placeholder="Ingrese el apellido">
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Documento -->
                            <div>
                                <label for="document_number" class="block text-sm font-medium text-gray-700">
                                    {{ __('Número de Documento') }}
                                </label>
                                <input type="text" name="document_number" id="document_number"
                                    value="{{ old('document_number', $operator->document_number) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('document_number') border-red-300 @enderror"
                                    placeholder="DNI, Cédula, etc.">
                                @error('document_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                    {{ __('Teléfono') }}
                                </label>
                                <input type="tel" name="phone" id="phone"
                                    value="{{ old('phone', $operator->phone) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-300 @enderror"
                                    placeholder="+54 11 1234-5678">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Cargo/Posición y Tipo -->
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Cargo/Posición -->
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700">
                                    {{ __('Cargo/Posición') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="position" id="position"
                                    value="{{ old('position', $operator->position) }}" required
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
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-blue-800">{{ __('Operador de Empresa') }}</h4>
                                    <p class="mt-1 text-sm text-blue-700">
                                        {{ __('Este operador es empleado específico de') }}
                                        <strong>{{ $company->legal_name }}</strong>
                                        {{ __('y solo puede trabajar con esta empresa.') }}
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
                        <p class="mt-1 text-sm text-gray-600">{{ __('Actualice las credenciales de acceso.') }}</p>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                {{ __('Correo Electrónico') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" id="email"
                                value="{{ old('email', $operator->user?->email) }}" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-300 @enderror"
                                placeholder="operador@empresa.com">
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('Este email será usado para iniciar sesión en el sistema.') }}
                            </p>
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nueva Contraseña -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    {{ __('Nueva Contraseña') }}
                                </label>
                                <input type="password" name="password" id="password" minlength="8"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-300 @enderror"
                                    placeholder="Dejar vacío para mantener actual">
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Dejar vacío si no desea cambiar la contraseña.') }}
                                </p>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirmar Nueva Contraseña -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                    {{ __('Confirmar Nueva Contraseña') }}
                                </label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    minlength="8"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Repetir nueva contraseña">
                            </div>
                        </div>

                        <!-- Estado del Usuario -->
                        @if ($operator->user)
                            <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">
                                    {{ __('Estado Actual del Usuario') }}</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p>• <strong>{{ __('Email verificado:') }}</strong>
                                        @if ($operator->user->email_verified_at)
                                            <span class="text-green-600">{{ __('Sí') }}</span>
                                            ({{ $operator->user->email_verified_at->format('d/m/Y H:i') }})
                                        @else
                                            <span class="text-red-600">{{ __('No') }}</span>
                                        @endif
                                    </p>
                                    <p>• <strong>{{ __('Último acceso:') }}</strong>
                                        @if ($operator->user->last_access)
                                            {{ $operator->user->last_access->format('d/m/Y H:i') }}
                                            ({{ $operator->user->last_access->diffForHumans() }})
                                        @else
                                            {{ __('Nunca') }}
                                        @endif
                                    </p>
                                    <p>• <strong>{{ __('Roles:') }}</strong>
                                        @if ($operator->user->roles->count() > 0)
                                            {{ $operator->user->roles->pluck('name')->implode(', ') }}
                                        @else
                                            {{ __('Sin roles asignados') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Permisos Operativos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Permisos Operativos') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Configure qué operaciones puede realizar este operador.') }}</p>
                    </div>

                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800">{{ __('Importante') }}</h3>
                                        <div class="mt-1 text-sm text-yellow-700">
                                            <p>{{ __('El operador debe tener al menos un permiso operativo para poder trabajar en el sistema.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permisos disponibles -->
                            @foreach ($formData['permissions'] as $permission => $label)
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="{{ $permission }}" id="{{ $permission }}"
                                            value="1"
                                            {{ old($permission, $operator->$permission) ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3">
                                        <label for="{{ $permission }}" class="text-sm font-medium text-gray-700">
                                            {{ $label }}
                                        </label>
                                        <p class="text-xs text-gray-500">
                                            @switch($permission)
                                                @case('can_import')
                                                    {{ __('Puede crear y gestionar cargas de importación.') }}
                                                @break

                                                @case('can_export')
                                                    {{ __('Puede crear y gestionar cargas de exportación.') }}
                                                @break

                                                @case('can_transfer')
                                                    {{ __('Puede gestionar transbordos y transferencias entre puertos.') }}
                                                @break
                                            @endswitch
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Estado del Operador -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Estado') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Configure el estado del operador.') }}</p>
                    </div>

                    <div class="p-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox" name="active" id="active" value="1"
                                    {{ old('active', $operator->active) ? 'checked' : '' }}
                                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                            </div>
                            <div class="ml-3">
                                <label for="active" class="text-sm font-medium text-gray-700">
                                    {{ __('Operador Activo') }}
                                </label>
                                <p class="text-xs text-gray-500">
                                    {{ __('Solo los operadores activos pueden acceder al sistema.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Información del estado actual -->
                        <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
                            <h5 class="text-sm font-medium text-gray-900 mb-1">{{ __('Estado actual:') }}</h5>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p>• <strong>{{ __('Operador:') }}</strong>
                                    <span class="{{ $operator->active ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $operator->active ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </p>
                                <p>• <strong>{{ __('Usuario del sistema:') }}</strong>
                                    <span class="{{ $operator->user?->active ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $operator->user?->active ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </p>
                                <p>• <strong>{{ __('Creado:') }}</strong>
                                    {{ $operator->created_at->format('d/m/Y H:i') }}
                                    ({{ $operator->created_at->diffForHumans() }})</p>
                                <p>• <strong>{{ __('Última modificación:') }}</strong>
                                    {{ $operator->updated_at->format('d/m/Y H:i') }}
                                    ({{ $operator->updated_at->diffForHumans() }})</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('Información Importante') }}</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p>• {{ __('Los cambios se aplicarán inmediatamente al guardar.') }}</p>
                        <p>• {{ __('Si cambia la contraseña, se recomienda informar al operador.') }}</p>
                        <p>• {{ __('Si desactiva el operador, perderá acceso inmediato al sistema.') }}</p>
                        <p>• {{ __('Los cambios de tipo (interno/externo) pueden afectar los permisos globales.') }}
                        </p>
                    </div>

                    @if ($formData['company_roles'] && count($formData['company_roles']) > 0)
                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <h5 class="text-sm font-medium text-blue-800 mb-1">
                                {{ __('Roles disponibles en su empresa:') }}</h5>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($formData['company_roles'] as $role)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $role }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-blue-600">
                                {{ __('Los operadores externos podrán trabajar con estas funcionalidades según sus permisos individuales.') }}
                            </p>
                        </div>
                    @endif
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                        <a href="{{ route('company.operators.show', $operator) }}"
                            class="bg-white border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Cancelar') }}
                        </a>
                        <button type="submit"
                            class="bg-blue-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ __('Guardar Cambios') }}
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
                const passwordField = document.getElementById('password');
                const confirmPasswordField = document.getElementById('password_confirmation');
                const permissionCheckboxes = document.querySelectorAll(
                    'input[name$="_import"], input[name$="_export"], input[name$="_transfer"]');
                const typeSelect = document.getElementById('type');

                // Alerta para operadores internos
                function showInternalOperatorWarning() {
                    if (typeSelect.value === 'internal') {
                        // Crear alerta si no existe
                        let existingAlert = document.getElementById('internal-operator-alert');
                        if (!existingAlert) {
                            const alertHtml = `
                            <div id="internal-operator-alert" class="mb-6 bg-amber-50 border border-amber-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-amber-800">{{ __('Atención: Operador Interno') }}</h3>
                                        <div class="mt-1 text-sm text-amber-700">
                                            <p>{{ __('Está cambiando a operador INTERNO que tendrá acceso global al sistema y podrá gestionar múltiples empresas, no solo') }} {{ $company->legal_name }}.</p>
                                            <p class="mt-1"><strong>{{ __('¿Está seguro del cambio?') }}</strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;

                            // Insertar antes del formulario
                            const formContainer = document.querySelector('form').parentElement;
                            const formElement = document.querySelector('form');
                            formElement.insertAdjacentHTML('beforebegin', alertHtml);
                        }
                    } else {
                        // Remover alerta si existe
                        const existingAlert = document.getElementById('internal-operator-alert');
                        if (existingAlert) {
                            existingAlert.remove();
                        }
                    }
                }

                // Escuchar cambios en el tipo
                typeSelect.addEventListener('change', showInternalOperatorWarning);

                // Validación de contraseñas
                function validatePasswords() {
                    if (passwordField.value || confirmPasswordField.value) {
                        if (passwordField.value !== confirmPasswordField.value) {
                            confirmPasswordField.setCustomValidity('Las contraseñas no coinciden');
                            confirmPasswordField.classList.add('border-red-300');
                        } else {
                            confirmPasswordField.setCustomValidity('');
                            confirmPasswordField.classList.remove('border-red-300');
                        }
                    } else {
                        confirmPasswordField.setCustomValidity('');
                        confirmPasswordField.classList.remove('border-red-300');
                    }
                }

                passwordField.addEventListener('input', validatePasswords);
                confirmPasswordField.addEventListener('input', validatePasswords);

                // Validación de permisos (al menos uno debe estar seleccionado)
                function validatePermissions() {
                    const hasPermission = Array.from(permissionCheckboxes).some(checkbox => checkbox.checked);

                    permissionCheckboxes.forEach(checkbox => {
                        if (!hasPermission) {
                            checkbox.setCustomValidity('Debe seleccionar al menos un permiso operativo');
                        } else {
                            checkbox.setCustomValidity('');
                        }
                    });
                }

                permissionCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', validatePermissions);
                });

                // Validación del formulario
                form.addEventListener('submit', function(e) {
                    validatePasswords();
                    validatePermissions();

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
                showInternalOperatorWarning(); // Verificar estado inicial
            });
        </script>
    @endpush
</x-app-layout>
