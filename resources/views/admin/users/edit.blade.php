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

            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Hay errores en el formulario
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc space-y-1 pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6" id="editUserForm">
                @csrf
                @method('PUT')

                <!-- Información básica del usuario -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre completo *
                                </label>
                                <input type="text" 
                                       name="name" 
                                       id="name" 
                                       value="{{ old('name', $user->name) }}"
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
                                       value="{{ old('email', $user->email) }}"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zona horaria
                                </label>
                                <select name="timezone" 
                                        id="timezone"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="America/Argentina/Buenos_Aires" {{ old('timezone', $user->timezone) === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>Argentina (Buenos Aires)</option>
                                    <option value="America/Asuncion" {{ old('timezone', $user->timezone) === 'America/Asuncion' ? 'selected' : '' }}>Paraguay (Asunción)</option>
                                    <option value="UTC" {{ old('timezone', $user->timezone) === 'UTC' ? 'selected' : '' }}>UTC</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" 
                                       name="active" 
                                       id="active" 
                                       value="1"
                                       {{ old('active', $user->active) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="active" class="ml-2 block text-sm text-gray-700">
                                    Usuario activo
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Usuario -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Tipo de Usuario</h3>
                        
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Usuario *
                            </label>
                            <select name="role" 
                                    id="role" 
                                    required
                                    onchange="toggleRoleFields()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecciona un tipo de usuario</option>
                                <option value="super-admin" {{ old('role', $user->roles->first()?->name) === 'super-admin' ? 'selected' : '' }}>
                                    Super Administrador
                                </option>
                                <option value="company-admin" {{ old('role', $user->roles->first()?->name) === 'company-admin' ? 'selected' : '' }}>
                                    Administrador de Empresa
                                </option>
                                <option value="user" {{ old('role', $user->roles->first()?->name) === 'user' ? 'selected' : '' }}>
                                    Operador
                                </option>
                            </select>
                            <div class="mt-2 text-sm text-gray-600">
                                <div id="roleDescription" class="hidden">
                                    <strong>Tipos de usuario:</strong>
                                    <ul class="mt-1 ml-4 list-disc space-y-1">
                                        <li><strong>Super Administrador:</strong> Acceso total al sistema, crea empresas</li>
                                        <li><strong>Administrador de Empresa:</strong> Gestiona usuarios de su empresa</li>
                                        <li><strong>Operador:</strong> Realiza operaciones según permisos asignados</li>
                                    </ul>
                                </div>
                            </div>
                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Campos específicos para Company Admin -->
                <div id="companyFields" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-blue-900 mb-4">Información de Empresa</h3>
                        
                        <div>
                            <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Empresa *
                            </label>
                            <select name="company_id" 
                                    id="company_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecciona una empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" 
                                        {{ old('company_id', $user->userable_type === 'App\Models\Company' ? $user->userable_id : '') == $company->id ? 'selected' : '' }}>
                                        {{ $company->legal_name }} ({{ $company->tax_id }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">El usuario será el administrador de esta empresa</p>
                        </div>
                    </div>
                </div>

                <!-- Campos específicos para Operadores -->
                <div id="operatorFields" class="hidden bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-green-900 mb-4">Información del Operador</h3>
                        
                        @php
                            $operator = $user->userable_type === 'App\Models\Operator' ? $user->userable : null;
                        @endphp

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre *
                                </label>
                                <input type="text" 
                                       name="first_name" 
                                       id="first_name" 
                                       value="{{ old('first_name', $operator?->first_name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Apellido *
                                </label>
                                <input type="text" 
                                       name="last_name" 
                                       id="last_name" 
                                       value="{{ old('last_name', $operator?->last_name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="document_number" class="block text-sm font-medium text-gray-700 mb-2">
                                    Documento (DNI/Cédula)
                                </label>
                                <input type="text" 
                                       name="document_number" 
                                       id="document_number" 
                                       value="{{ old('document_number', $operator?->document_number) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Teléfono
                                </label>
                                <input type="text" 
                                       name="phone" 
                                       id="phone" 
                                       value="{{ old('phone', $operator?->phone) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="mt-6">
                            <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                                Cargo *
                            </label>
                            <input type="text" 
                                   name="position" 
                                   id="position" 
                                   value="{{ old('position', $operator?->position) }}"
                                   placeholder="Ej: Despachante, Operador de cargas, etc."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Tipo de Operador -->
                        <div class="mt-6">
                            <label for="operator_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Operador *
                            </label>
                            <select name="operator_type" 
                                    id="operator_type"
                                    onchange="toggleOperatorCompany()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione el tipo</option>
                                <option value="external" {{ old('operator_type', $operator?->type) === 'external' ? 'selected' : '' }}>
                                    Externo (Empleado de empresa)
                                </option>
                                <option value="internal" {{ old('operator_type', $operator?->type) === 'internal' ? 'selected' : '' }}>
                                    Interno (Empleado del sistema)
                                </option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Los operadores externos trabajan para una empresa específica. Los internos tienen acceso global.
                            </p>
                        </div>

                        <!-- Empresa para operador externo -->
                        <div id="operatorCompanyField" class="hidden mt-6">
                            <label for="operator_company_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Empresa del operador *
                            </label>
                            <select name="operator_company_id" 
                                    id="operator_company_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecciona una empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" 
                                        {{ old('operator_company_id', $operator?->company_id) == $company->id ? 'selected' : '' }}>
                                        {{ $company->legal_name }} ({{ $company->tax_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Permisos del operador -->
                        <div class="mt-6 border-t pt-4">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Permisos del Operador</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center">
                                    <input type="hidden" name="can_import" value="0">
                                    <input type="checkbox" 
                                           name="can_import" 
                                           id="can_import" 
                                           value="1"
                                           {{ old('can_import', $operator?->can_import) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="can_import" class="ml-2 block text-sm text-gray-700">
                                        Puede importar datos
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="hidden" name="can_export" value="0">
                                    <input type="checkbox" 
                                           name="can_export" 
                                           id="can_export" 
                                           value="1"
                                           {{ old('can_export', $operator?->can_export) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="can_export" class="ml-2 block text-sm text-gray-700">
                                        Puede exportar datos
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="hidden" name="can_transfer" value="0">
                                    <input type="checkbox" 
                                           name="can_transfer" 
                                           id="can_transfer" 
                                           value="1"
                                           {{ old('can_transfer', $operator?->can_transfer) ? 'checked' : '' }}
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
                <div class="flex items-center justify-end space-x-4 pt-6">
                    <a href="{{ route('admin.users.show', $user) }}"
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md text-sm font-medium">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                        Guardar Cambios
                    </button>
                </div>
            </form>

            <!-- FORMULARIO SEPARADO: Cambio de Contraseña -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cambiar Contraseña</h3>
                    
                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nueva Contraseña *
                                </label>
                                <input type="password" 
                                       name="password" 
                                       id="new_password" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Confirmar Contraseña *
                                </label>
                                <input type="password" 
                                       name="password_confirmation" 
                                       id="new_password_confirmation" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Misma lógica JavaScript que la vista create
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const companyFields = document.getElementById('companyFields');
            const operatorFields = document.getElementById('operatorFields');
            const roleDescription = document.getElementById('roleDescription');

            // Ocultar todos los campos específicos
            companyFields.classList.add('hidden');
            operatorFields.classList.add('hidden');

            // Limpiar campos requeridos
            document.getElementById('company_id').removeAttribute('required');
            document.getElementById('first_name').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('operator_type').removeAttribute('required');

            if (role) {
                roleDescription.classList.remove('hidden');
                
                if (role === 'super-admin') {
                    // Super admin no necesita campos adicionales
                    
                } else if (role === 'company-admin') {
                    companyFields.classList.remove('hidden');
                    document.getElementById('company_id').setAttribute('required', 'required');
                    
                } else if (role === 'user') {
                    operatorFields.classList.remove('hidden');
                    document.getElementById('first_name').setAttribute('required', 'required');
                    document.getElementById('last_name').setAttribute('required', 'required');
                    document.getElementById('operator_type').setAttribute('required', 'required');
                    
                    // Restaurar estado del tipo de operador
                    toggleOperatorCompany();
                }
            } else {
                roleDescription.classList.add('hidden');
            }
        }

        function toggleOperatorCompany() {
            const operatorType = document.getElementById('operator_type').value;
            const operatorCompanyField = document.getElementById('operatorCompanyField');
            
            if (operatorType === 'external') {
                operatorCompanyField.classList.remove('hidden');
                document.getElementById('operator_company_id').setAttribute('required', 'required');
            } else {
                operatorCompanyField.classList.add('hidden');
                document.getElementById('operator_company_id').removeAttribute('required');
            }
        }

        // Validación de contraseñas
        document.getElementById('password_confirmation').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmation = this.value;
            
            if (password && confirmation && password !== confirmation) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Restaurar estado al cargar
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
        });

        // Validación del formulario antes del envío (igual que create)
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            
            if (role === 'company-admin') {
                const companyId = document.getElementById('company_id').value;
                if (!companyId) {
                    e.preventDefault();
                    alert('Debe seleccionar una empresa para el Administrador de Empresa');
                    return false;
                }
            } else if (role === 'user') {
                const operatorType = document.getElementById('operator_type').value;
                if (!operatorType) {
                    e.preventDefault();
                    alert('Debe seleccionar el tipo de operador');
                    return false;
                }
                
                if (operatorType === 'external') {
                    const operatorCompanyId = document.getElementById('operator_company_id').value;
                    if (!operatorCompanyId) {
                        e.preventDefault();
                        alert('Debe seleccionar una empresa para el operador externo');
                        return false;
                    }
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