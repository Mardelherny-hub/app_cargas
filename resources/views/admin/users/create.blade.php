<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Usuario') }}
            </h2>
            <a href="{{ route('admin.users.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Volver
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

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

                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6" id="userForm">
                        @csrf

                        <!-- Información básica del usuario -->
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
                            </div>
                        </div>

                        <!-- Contraseña -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Contraseña *
                                </label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="mt-1 text-sm text-gray-500">Mínimo 8 caracteres</p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Confirmar contraseña *
                                </label>
                                <input type="password" 
                                       name="password_confirmation" 
                                       id="password_confirmation" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Rol del usuario -->
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                Rol del usuario *
                            </label>
                            <select name="role" 
                                    id="role" 
                                    required
                                    onchange="toggleRoleFields()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecciona un rol</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 text-sm text-gray-600">
                                <div id="roleDescription" class="hidden">
                                    <strong>Descripciones de roles:</strong>
                                    <ul class="mt-1 ml-4 list-disc space-y-1">
                                        <li><strong>Super Admin:</strong> Acceso total al sistema</li>
                                        <li><strong>Company Admin:</strong> Administrador de una empresa específica</li>
                                        <li><strong>Internal Operator:</strong> Operador interno del sistema</li>
                                        <li><strong>External Operator:</strong> Operador de una empresa específica</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración adicional -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zona horaria
                                </label>
                                <select name="timezone" 
                                        id="timezone"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="America/Argentina/Buenos_Aires" {{ old('timezone') === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>Argentina (Buenos Aires)</option>
                                    <option value="America/Asuncion" {{ old('timezone') === 'America/Asuncion' ? 'selected' : '' }}>Paraguay (Asunción)</option>
                                    <option value="UTC" {{ old('timezone') === 'UTC' ? 'selected' : '' }}>UTC</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" 
                                       name="active" 
                                       id="active" 
                                       value="1"
                                       {{ old('active', '1') === '1' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="active" class="ml-2 block text-sm text-gray-700">
                                    Usuario activo
                                </label>
                            </div>
                        </div>

                        <!-- Campos específicos para Company Admin -->
                        <div id="companyFields" class="hidden space-y-6">
                            <div class="bg-blue-50 rounded-lg p-4">
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
                                            <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->legal_name }} ({{ $company->tax_id }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500">El usuario será el administrador de esta empresa</p>
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
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="document_number" class="block text-sm font-medium text-gray-700 mb-2">
                                            Documento (DNI/Cédula) *
                                        </label>
                                        <input type="text" 
                                               name="document_number" 
                                               id="document_number" 
                                               value="{{ old('document_number') }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                                    </div>
                                </div>

                                <div>
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cargo
                                    </label>
                                    <input type="text" 
                                           name="position" 
                                           id="position" 
                                           value="{{ old('position') }}"
                                           placeholder="Ej: Despachante, Operador de cargas, etc."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Empresa para operador externo -->
                                <div id="operatorCompanyField" class="hidden">
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
                                </div>

                                <!-- Permisos del operador -->
                                <div class="border-t pt-4">
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
                                                Puede importar datos
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
                                                Puede exportar datos
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
            const operatorCompanyField = document.getElementById('operatorCompanyField');
            const roleDescription = document.getElementById('roleDescription');

            // Ocultar todos los campos específicos
            companyFields.classList.add('hidden');
            operatorFields.classList.add('hidden');
            operatorCompanyField.classList.add('hidden');

            // Limpiar campos requeridos
            document.getElementById('company_id').removeAttribute('required');
            document.getElementById('first_name').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('document_number').removeAttribute('required');
            document.getElementById('operator_company_id').removeAttribute('required');

            // Mostrar descripción de roles
            roleDescription.classList.remove('hidden');

            // Mostrar campos específicos según el rol
            if (role === 'company-admin') {
                companyFields.classList.remove('hidden');
                document.getElementById('company_id').setAttribute('required', 'required');
            } else if (role === 'internal-operator' || role === 'external-operator') {
                operatorFields.classList.remove('hidden');
                document.getElementById('first_name').setAttribute('required', 'required');
                document.getElementById('last_name').setAttribute('required', 'required');
                document.getElementById('document_number').setAttribute('required', 'required');

                if (role === 'external-operator') {
                    operatorCompanyField.classList.remove('hidden');
                    document.getElementById('operator_company_id').setAttribute('required', 'required');
                }
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
                    alert('Debe seleccionar una empresa para el rol Company Admin');
                    return false;
                }
            } else if (role === 'external-operator') {
                const operatorCompanyId = document.getElementById('operator_company_id').value;
                if (!operatorCompanyId) {
                    e.preventDefault();
                    alert('Debe seleccionar una empresa para el operador externo');
                    return false;
                }
            }
        });
    </script>
</x-app-layout>