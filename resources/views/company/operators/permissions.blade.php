<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Gestión de Permisos') }} - {{ $operator->first_name }} {{ $operator->last_name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('company.operators.edit', $operator) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Editar Operador
                </a>
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
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Información del Operador -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center">
                                <span class="text-lg font-medium text-white">
                                    {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                {{ $operator->first_name }} {{ $operator->last_name }}
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ $operator->position ?: 'Sin cargo definido' }} • {{ $operator->user?->email }}
                            </p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $operator->active ? 'Activo' : 'Inactivo' }}
                                </span>
                                <span class="text-xs text-gray-400">•</span>
                                <span class="text-xs text-gray-500">
                                    Tipo: {{ ucfirst($operator->type) }} Operator
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('company.operators.update-permissions', $operator) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Permisos Básicos del Operador -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Permisos Básicos</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Configure los permisos fundamentales para las operaciones del sistema.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Permiso de Importación -->
                            <div class="border rounded-lg p-6 {{ $operatorPermissions['can_import'] ? 'border-green-200 bg-green-50' : 'border-gray-200' }}">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="can_import"
                                               name="can_import"
                                               type="checkbox"
                                               value="1"
                                               {{ old('can_import', $operatorPermissions['can_import']) ? 'checked' : '' }}
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3">
                                        <label for="can_import" class="text-sm font-medium text-gray-900">
                                            Importar Datos
                                        </label>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Permite importar cargas desde:
                                        </p>
                                        <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                            <li>• Archivos Excel (.xlsx, .xls)</li>
                                            <li>• Archivos XML</li>
                                            <li>• Archivos EDI</li>
                                            <li>• Archivos CUSCAR</li>
                                            <li>• Archivos de texto (.txt, .csv)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Permiso de Exportación -->
                            <div class="border rounded-lg p-6 {{ $operatorPermissions['can_export'] ? 'border-green-200 bg-green-50' : 'border-gray-200' }}">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="can_export"
                                               name="can_export"
                                               type="checkbox"
                                               value="1"
                                               {{ old('can_export', $operatorPermissions['can_export']) ? 'checked' : '' }}
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3">
                                        <label for="can_export" class="text-sm font-medium text-gray-900">
                                            Exportar Datos
                                        </label>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Permite exportar y enviar:
                                        </p>
                                        <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                            <li>• Datos a webservices</li>
                                            <li>• Archivos Excel</li>
                                            <li>• Reportes en PDF</li>
                                            <li>• Manifiestos</li>
                                            <li>• Conocimientos de embarque</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Permiso de Transferencia -->
                            <div class="border rounded-lg p-6 {{ $operatorPermissions['can_transfer'] ? 'border-green-200 bg-green-50' : 'border-gray-200' }}">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="can_transfer"
                                               name="can_transfer"
                                               type="checkbox"
                                               value="1"
                                               {{ old('can_transfer', $operatorPermissions['can_transfer']) ? 'checked' : '' }}
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3">
                                        <label for="can_transfer" class="text-sm font-medium text-gray-900">
                                            Transferir Cargas
                                        </label>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Permite transferir:
                                        </p>
                                        <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                            <li>• Cargas entre operadores</li>
                                            <li>• Cargas entre empresas</li>
                                            <li>• Viajes completos</li>
                                            <li>• Reasignar responsabilidades</li>
                                            <li>• Cambiar propietarios</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de Permisos Básicos -->
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
                                            Información sobre Permisos Básicos
                                        </h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p>
                                                Sin estos permisos, el operador solo podrá crear, ver y editar cargas básicas.
                                                Los permisos se aplican inmediatamente después de guardar.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos Especiales -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Permisos Especiales</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Configure permisos adicionales específicos para casos especiales.
                            </p>
                        </div>

                        <!-- Lista de Permisos Especiales Disponibles -->
                        <div class="space-y-4">
                            @php
                                $availableSpecialPermissions = [
                                    'bulk-operations' => [
                                        'name' => 'Operaciones Masivas',
                                        'description' => 'Permite realizar operaciones en lote sobre múltiples cargas o viajes simultáneamente.'
                                    ],
                                    'force-close-trips' => [
                                        'name' => 'Forzar Cierre de Viajes',
                                        'description' => 'Puede cerrar viajes forzosamente aunque tengan cargas pendientes.'
                                    ],
                                    'emergency-rectification' => [
                                        'name' => 'Rectificaciones de Emergencia',
                                        'description' => 'Permite realizar rectificaciones fuera del horario normal o en situaciones de emergencia.'
                                    ],
                                    'cross-company-view' => [
                                        'name' => 'Vista Entre Empresas',
                                        'description' => 'Puede ver cargas y viajes de otras empresas para coordinación.'
                                    ],
                                    'admin-reports' => [
                                        'name' => 'Reportes Administrativos',
                                        'description' => 'Acceso a reportes especiales y estadísticas avanzadas de la empresa.'
                                    ],
                                    'certificate-management' => [
                                        'name' => 'Gestión de Certificados',
                                        'description' => 'Puede cargar y gestionar certificados digitales de la empresa.'
                                    ]
                                ];
                                $currentSpecialPermissions = $specialPermissions ?? [];
                            @endphp

                            @foreach($availableSpecialPermissions as $key => $permission)
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="special_{{ $key }}"
                                               name="special_permissions[]"
                                               type="checkbox"
                                               value="{{ $key }}"
                                               {{ in_array($key, $currentSpecialPermissions) ? 'checked' : '' }}
                                               class="focus:ring-purple-500 h-4 w-4 text-purple-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="special_{{ $key }}" class="font-medium text-gray-700">
                                            {{ $permission['name'] }}
                                        </label>
                                        <p class="text-gray-500">
                                            {{ $permission['description'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if(count($currentSpecialPermissions) > 0)
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Permisos Especiales Actuales</h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($currentSpecialPermissions as $permission)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                            {{ $availableSpecialPermissions[$permission]['name'] ?? $permission }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Advertencias y Limitaciones -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Advertencias y Limitaciones</h3>

                        <div class="space-y-4">
                            <!-- Advertencia sobre permisos -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800">
                                            Importante sobre los Permisos
                                        </h3>
                                        <div class="mt-2 text-sm text-yellow-700">
                                            <ul class="list-disc list-inside space-y-1">
                                                <li>Los cambios de permisos se aplican inmediatamente tras guardar.</li>
                                                <li>El operador debe cerrar sesión y volver a iniciarla para ver todos los cambios.</li>
                                                <li>Los permisos especiales deben ser utilizados con precaución.</li>
                                                <li>Algunos permisos pueden requerir configuración adicional del sistema.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Limitaciones -->
                            <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Limitaciones del Sistema</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p>• El operador siempre podrá ver y gestionar sus propias cargas, independientemente de los permisos.</p>
                                    <p>• Los permisos de webservices dependen de la configuración de certificados de la empresa.</p>
                                    <p>• Algunos permisos especiales solo funcionan en horarios de oficina configurados.</p>
                                    <p>• El acceso a reportes administrativos puede estar limitado por el tipo de licencia.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                        <div class="flex space-x-3">
                            <a href="{{ route('company.operators.show', $operator) }}"
                               class="bg-white border border-gray-300 rounded-md py-2 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                Cancelar
                            </a>
                            <button type="button"
                                    onclick="resetToDefaults()"
                                    class="bg-gray-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                Restablecer Predeterminados
                            </button>
                        </div>
                        <button type="submit"
                                class="bg-purple-600 border border-transparent rounded-md py-2 px-4 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            Guardar Permisos
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function resetToDefaults() {
            if (confirm('¿Está seguro de restablecer todos los permisos a los valores predeterminados?\n\nEsto desactivará todos los permisos especiales y mantendrá solo los permisos básicos según el tipo de operador.')) {
                // Desmarcar todos los checkboxes especiales
                const specialCheckboxes = document.querySelectorAll('input[name="special_permissions[]"]');
                specialCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });

                // Establecer permisos básicos predeterminados para operador externo
                document.getElementById('can_import').checked = false;
                document.getElementById('can_export').checked = false;
                document.getElementById('can_transfer').checked = false;

                alert('Permisos restablecidos a valores predeterminados. Recuerde guardar los cambios.');
            }
        }

        // Mostrar advertencia si se seleccionan permisos especiales sensibles
        document.addEventListener('DOMContentLoaded', function() {
            const sensitivePermissions = [
                'force-close-trips',
                'emergency-rectification',
                'cross-company-view'
            ];

            sensitivePermissions.forEach(permission => {
                const checkbox = document.getElementById('special_' + permission);
                if (checkbox) {
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            alert('ATENCIÓN: Ha seleccionado un permiso especial sensible. Asegúrese de que el operador realmente necesita este nivel de acceso.');
                        }
                    });
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
