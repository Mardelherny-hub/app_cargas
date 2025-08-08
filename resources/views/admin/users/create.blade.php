<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Usuario') }}
            </h2>
            <a href="{{ route('admin.users.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form id="userForm" method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
                        @csrf

                        <!-- Información básica del usuario -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Usuario</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Nombre completo *
                                        </label>
                                        <input type="text" 
                                               name="name" 
                                               id="name" 
                                               value="{{ old('name') }}"
                                               required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                            Email *
                                        </label>
                                        <input type="email" 
                                               name="email" 
                                               id="email" 
                                               value="{{ old('email') }}"
                                               required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('email')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                            Contraseña *
                                        </label>
                                        <input type="password" 
                                               name="password" 
                                               id="password" 
                                               required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('password')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                            Confirmar contraseña *
                                        </label>
                                        <input type="password" 
                                               name="password_confirmation" 
                                               id="password_confirmation" 
                                               required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                            Rol del usuario *
                                        </label>
                                        <select name="role" 
                                                id="role" 
                                                onchange="toggleRoleFields()"
                                                required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Selecciona un rol</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                                                    @if($role->name === 'user')
                                                        Operador
                                                    @else
                                                        {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('role')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                            Zona horaria
                                        </label>
                                        <select name="timezone" 
                                                id="timezone"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="America/Argentina/Buenos_Aires" {{ old('timezone') === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>
                                                Buenos Aires (Argentina)
                                            </option>
                                            <option value="America/Asuncion" {{ old('timezone') === 'America/Asuncion' ? 'selected' : '' }}>
                                                Asunción (Paraguay)
                                            </option>
                                            <option value="UTC" {{ old('timezone') === 'UTC' ? 'selected' : '' }}>
                                                UTC
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <div class="flex items-center">
                                        <input type="hidden" name="active" value="0">
                                        <input type="checkbox" 
                                               name="active" 
                                               id="active" 
                                               value="1"
                                               {{ old('active', true) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="active" class="ml-2 block text-sm text-gray-700">
                                            Usuario activo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Descripción del rol seleccionado -->
                        <div id="roleDescription" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <h4 class="text-blue-900 font-medium">Información del rol</h4>
                                    <p class="text-blue-800 text-sm mt-1" id="roleDescriptionText"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Company Admin -->
                        <div id="companyFields" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-blue-900 mb-4">Información de la Empresa</h3>
                                
                                <div>
                                    <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Empresa *
                                    </label>
                                    <select name="company_id" 
                                            id="company_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Selecciona una empresa</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->legal_name }} ({{ $company->tax_id }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500">El usuario será el administrador de esta empresa</p>
                                    @error('company_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Operadores -->
                        <div id="operatorFields" class="hidden space-y-6">
                            <div class="bg-green-50 rounded-lg p-4">
                                <h3 class="text-lg font-medium text-green-900 mb-4">Información del Operador</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Nombre *
                                        </label>
                                        <input type="text" 
                                               name="first_name" 
                                               id="first_name" 
                                               value="{{ old('first_name') }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('first_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Apellido *
                                        </label>
                                        <input type="text" 
                                               name="last_name" 
                                               id="last_name" 
                                               value="{{ old('last_name') }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('last_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label for="document_number" class="block text-sm font-medium text-gray-700 mb-2">
                                            Número de documento
                                        </label>
                                        <input type="text" 
                                               name="document_number" 
                                               id="document_number" 
                                               value="{{ old('document_number') }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('document_number')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                            Teléfono
                                        </label>
                                        <input type="text" 
                                               name="phone" 
                                               id="phone" 
                                               value="{{ old('phone') }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @error('phone')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cargo *
                                    </label>
                                    <input type="text" 
                                           name="position" 
                                           id="position" 
                                           value="{{ old('position') }}"
                                           placeholder="Ej: Operador de Cargas, Especialista en Importaciones..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    @error('position')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Input hidden para operator_type (siempre external) -->
                                <input type="hidden" name="operator_type" value="external">

                                <!-- Empresa del operador (siempre visible para operadores) -->
                                <div id="operatorCompanyField" class="mt-6">
                                    <label for="operator_company_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Empresa del operador *
                                    </label>
                                    <select name="operator_company_id" 
                                            id="operator_company_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Selecciona una empresa</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ old('operator_company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->legal_name }} ({{ $company->tax_id }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Todos los operadoress trabajan para una empresa específica.
                                    </p>
                                    @error('operator_company_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Permisos del operador -->
                                <div class="border-t pt-4 mt-6">
                                    <h4 class="text-md font-medium text-gray-700 mb-3">Permisos del Operador</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="flex items-center">
                                            <input type="hidden" name="can_import" value="0">
                                            <input type="checkbox" 
                                                   name="can_import" 
                                                   id="can_import" 
                                                   value="1"
                                                   {{ old('can_import') === '1' ? 'checked' : '' }}
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="can_import" class="ml-2 block text-sm text-gray-700">
                                                Puede importar
                                            </label>
                                        </div>

                                        <div class="flex items-center">
                                            <input type="hidden" name="can_export" value="0">
                                            <input type="checkbox" 
                                                   name="can_export" 
                                                   id="can_export" 
                                                   value="1"
                                                   {{ old('can_export') === '1' ? 'checked' : '' }}
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="can_export" class="ml-2 block text-sm text-gray-700">
                                                Puede exportar
                                            </label>
                                        </div>

                                        <div class="flex items-center">
                                            <input type="hidden" name="can_transfer" value="0">
                                            <input type="checkbox" 
                                                   name="can_transfer" 
                                                   id="can_transfer" 
                                                   value="1"
                                                   {{ old('can_transfer') === '1' ? 'checked' : '' }}
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="can_transfer" class="ml-2 block text-sm text-gray-700">
                                                Puede transferir entre empresas
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="{{ route('admin.users.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Crear Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
function toggleRoleFields() {
    const role = document.getElementById('role').value;
    const companyFields = document.getElementById('companyFields');
    const operatorFields = document.getElementById('operatorFields');
    const roleDescription = document.getElementById('roleDescription');
    const roleDescriptionText = document.getElementById('roleDescriptionText');

    // Ocultar todos los campos específicos
    companyFields.classList.add('hidden');
    operatorFields.classList.add('hidden');

    // Limpiar campos requeridos
    document.getElementById('company_id').removeAttribute('required');
    document.getElementById('first_name').removeAttribute('required');
    document.getElementById('last_name').removeAttribute('required');
    document.getElementById('operator_company_id').removeAttribute('required');

    if (role) {
        roleDescription.classList.remove('hidden');
        
        if (role === 'super-admin') {
            roleDescriptionText.textContent = 'Acceso completo al sistema. Puede crear empresas, gestionar usuarios y configurar el sistema.';
            
        } else if (role === 'company-admin') {
            roleDescriptionText.textContent = 'Administrador de una empresa específica. Puede gestionar operadores y datos de su empresa.';
            companyFields.classList.remove('hidden');
            document.getElementById('company_id').setAttribute('required', 'required');
            
        } else if (role === 'user') {
            roleDescriptionText.textContent = 'Operador que trabaja para una empresa específica. Acceso limitado según permisos asignados.';
            operatorFields.classList.remove('hidden');
            document.getElementById('first_name').setAttribute('required', 'required');
            document.getElementById('last_name').setAttribute('required', 'required');
            document.getElementById('operator_company_id').setAttribute('required', 'required');
        }
    } else {
        roleDescription.classList.add('hidden');
    }
}

// Validación de contraseñas
document.getElementById('password_confirmation').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmation = this.value;
    
    if (password !== confirmation) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});

// Restaurar estado si hay errores de validación
document.addEventListener('DOMContentLoaded', function() {
    const role = document.getElementById('role').value;
    if (role) {
        toggleRoleFields();
    }
});

// Validación del formulario antes del envío
document.getElementById('userForm').addEventListener('submit', function(e) {
    const role = document.getElementById('role').value;
    
    if (role === 'company-admin') {
        const companyId = document.getElementById('company_id').value;
        if (!companyId) {
            e.preventDefault();
            alert('Debe seleccionar una empresa para el Administrador de Empresa');
            return false;
        }
    } else if (role === 'user') {
        const operatorCompanyId = document.getElementById('operator_company_id').value;
        if (!operatorCompanyId) {
            e.preventDefault();
            alert('Debe seleccionar una empresa para el operador');
            return false;
        }
        
        // Validar que tenga al menos un permiso
        const canImport = document.getElementById('can_import').checked;
        const canExport = document.getElementById('can_export').checked;
        const canTransfer = document.getElementById('can_transfer').checked;
        
        if (!canImport && !canExport && !canTransfer) {
            e.preventDefault();
            alert('El operador debe tener al menos un permiso (Importar, Exportar o Transferir)');
            return false;
        }
    }
});
</script>
</x-app-layout>