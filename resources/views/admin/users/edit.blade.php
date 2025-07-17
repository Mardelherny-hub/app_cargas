<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.show', $user) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Usuario') }} - {{ $user->name }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Información Básica -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información Básica</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">
                                    Nombre Completo *
                                </label>
                                <input type="text"
                                       name="name"
                                       id="name"
                                       value="{{ old('name', $user->name) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('name') border-red-300 @enderror"
                                       required>
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email *
                                </label>
                                <input type="email"
                                       name="email"
                                       id="email"
                                       value="{{ old('email', $user->email) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-300 @enderror"
                                       required>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Zona Horaria -->
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700">
                                    Zona Horaria
                                </label>
                                <select name="timezone"
                                        id="timezone"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="UTC" {{ old('timezone', $user->timezone) === 'UTC' ? 'selected' : '' }}>UTC</option>
                                    <option value="America/Argentina/Buenos_Aires" {{ old('timezone', $user->timezone) === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>Buenos Aires (GMT-3)</option>
                                    <option value="America/Asuncion" {{ old('timezone', $user->timezone) === 'America/Asuncion' ? 'selected' : '' }}>Asunción (GMT-3)</option>
                                </select>
                                @error('timezone')
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
                                    <option value="1" {{ old('active', $user->active) ? 'selected' : '' }}>Activo</option>
                                    <option value="0" {{ !old('active', $user->active) ? 'selected' : '' }}>Inactivo</option>
                                </select>
                                @error('active')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestión de Roles -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Roles del Usuario</h3>

                        <div class="space-y-3">
                            @foreach($roles as $role)
                                <div class="flex items-center">
                                    <input type="checkbox"
                                           name="roles[]"
                                           value="{{ $role->name }}"
                                           id="role_{{ $role->id }}"
                                           {{ $user->hasRole($role->name) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="role_{{ $role->id }}" class="ml-3 flex-1">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $role->permissions->count() }} permisos incluidos
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        @error('roles')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <div class="text-sm text-blue-800">
                                <strong>Nota:</strong> Los cambios en roles afectarán los permisos del usuario inmediatamente.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Relación Polimórfica -->
                @if($user->userable)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            @if($user->userable_type === 'App\Models\Company')
                                Información de la Empresa Asociada
                            @elseif($user->userable_type === 'App\Models\Operator')
                                Información del Operador Asociado
                            @else
                                Información de la Entidad Asociada
                            @endif
                        </h3>

                        <div class="bg-gray-50 rounded-lg p-4">
                            @if($user->userable_type === 'App\Models\Company')
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                        <dd class="text-sm text-gray-900">{{ $user->userable->legal_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">CUIT</dt>
                                        <dd class="text-sm text-gray-900">{{ $user->userable->tax_id }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">País</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ $user->userable->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                        <dd class="text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->userable->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $user->userable->active ? 'Activa' : 'Inactiva' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                                <div class="mt-4">
                                    <a href="{{ route('admin.companies.show', $user->userable->id) }}"
                                       class="text-sm text-blue-600 hover:text-blue-500">
                                        Ver/Editar detalles de la empresa →
                                    </a>
                                </div>
                            @elseif($user->userable_type === 'App\Models\Operator')
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Nombre Completo</dt>
                                        <dd class="text-sm text-gray-900">{{ $user->userable->first_name }} {{ $user->userable->last_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Documento</dt>
                                        <dd class="text-sm text-gray-900">{{ $user->userable->document_number }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                        <dd class="text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->userable->type === 'internal' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800' }}">
                                                {{ $user->userable->type === 'internal' ? 'Interno' : 'Externo' }}
                                            </span>
                                        </dd>
                                    </div>
                                    @if($user->userable->company)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                                            <dd class="text-sm text-gray-900">
                                                <a href="{{ route('admin.companies.show', $user->userable->company->id) }}"
                                                   class="text-blue-600 hover:text-blue-500">
                                                    {{ $user->userable->company->legal_name }}
                                                </a>
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                                <div class="mt-4">
                                    <div class="text-sm text-gray-600">
                                        <strong>Nota:</strong> Para editar los detalles del operador, utiliza la sección de gestión de operadores.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <!-- Cambio de Contraseña -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Cambiar Contraseña</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nueva contraseña -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Nueva Contraseña
                                </label>
                                <input type="password"
                                       name="password"
                                       id="password"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 @enderror">
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Deja en blanco para mantener la contraseña actual</p>
                            </div>

                            <!-- Confirmar contraseña -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                    Confirmar Contraseña
                                </label>
                                <input type="password"
                                       name="password_confirmation"
                                       id="password_confirmation"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <div class="text-sm text-yellow-800">
                                <strong>Importante:</strong> Si cambias la contraseña, el usuario recibirá una notificación por email.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuraciones Adicionales -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuraciones Adicionales</h3>

                        <div class="space-y-4">
                            <!-- Forzar verificación de email -->
                            <div class="flex items-center">
                                <input type="checkbox"
                                       name="force_email_verification"
                                       id="force_email_verification"
                                       value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="force_email_verification" class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">
                                        Marcar email como verificado
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Útil si el usuario tiene problemas con la verificación automática
                                    </div>
                                </label>
                            </div>

                            <!-- Notificar al usuario -->
                            <div class="flex items-center">
                                <input type="checkbox"
                                       name="notify_user"
                                       id="notify_user"
                                       value="1"
                                       checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="notify_user" class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">
                                        Notificar cambios al usuario
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        El usuario recibirá un email con los cambios realizados
                                    </div>
                                </label>
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
                                <a href="{{ route('admin.users.show', $user) }}"
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                            </div>

                            <div class="text-right">
                                <button type="button"
                                        onclick="return confirm('¿Estás seguro de que quieres eliminar este usuario? Esta acción no se puede deshacer.') && document.getElementById('delete-form').submit()"
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Eliminar Usuario
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Formulario de eliminación separado -->
            <form id="delete-form"
                  method="POST"
                  action="{{ route('admin.users.destroy', $user) }}"
                  class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Validación del formulario en el lado del cliente
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('password_confirmation');

            function validatePasswords() {
                if (passwordField.value && confirmPasswordField.value) {
                    if (passwordField.value !== confirmPasswordField.value) {
                        confirmPasswordField.setCustomValidity('Las contraseñas no coinciden');
                    } else {
                        confirmPasswordField.setCustomValidity('');
                    }
                }
            }

            passwordField.addEventListener('input', validatePasswords);
            confirmPasswordField.addEventListener('input', validatePasswords);

            // Advertencia al cambiar roles
            const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
            roleCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked && this.value === 'super-admin') {
                        if (!confirm('¿Estás seguro de asignar el rol de Super Administrador? Este rol tiene acceso completo al sistema.')) {
                            this.checked = false;
                        }
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
