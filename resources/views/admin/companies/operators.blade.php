<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Operadores de') }} {{ $company->commercial_name ?: $company->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ __('Gestión completa de operadores de la empresa') }} - {{ __('Panel Super Admin') }}
                </p>
            </div>
            <div class="flex space-x-3">
                <!-- NUEVO: Botón crear operador -->
                <button onclick="showCreateOperatorModal()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="-ml-1 mr-2 h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ __('Crear Operador') }}
                </button>
                
                <a href="{{ route('admin.companies.show', $company) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Volver a Empresa') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Mensajes de éxito/error -->
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <svg class="flex-shrink-0 h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM9 9a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">{{ __('Total Operadores') }}</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_operators'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">{{ __('Activos') }}</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['active_operators'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">{{ __('Inactivos') }}</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['inactive_operators'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">{{ __('Externos') }}</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['external_operators'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Operadores CON HERRAMIENTAS ADMINISTRATIVAS -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Lista de Operadores') }}</h3>
                        <!-- NUEVO: Herramientas masivas -->
                        <div class="flex space-x-2">
                            <button onclick="toggleAllOperators()" 
                                    class="text-sm bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded">
                                {{ __('Seleccionar Todo') }}
                            </button>
                            <button onclick="bulkToggleStatus()" 
                                    class="text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-3 py-1 rounded">
                                {{ __('Cambiar Estado') }}
                            </button>
                        </div>
                    </div>
                </div>

                @if($operators->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <!-- NUEVO: Checkbox para selección -->
                                    <th class="px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Operador') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Email') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Tipo') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Permisos') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Estado') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Creado') }}
                                    </th>
                                    <!-- NUEVO: Columna de acciones -->
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Acciones') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($operators as $operator)
                                    <tr class="hover:bg-gray-50" data-operator-id="{{ $operator->id }}">
                                        <!-- NUEVO: Checkbox para selección individual -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" 
                                                   class="operator-checkbox rounded border-gray-300" 
                                                   value="{{ $operator->id }}">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-gray-700">
                                                            {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $operator->first_name }} {{ $operator->last_name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $operator->position ?: __('Sin cargo especificado') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $operator->user->email ?? __('Sin email') }}</div>
                                            <div class="text-sm text-gray-500">{{ $operator->phone ?: __('Sin teléfono') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                {{ $operator->type === 'external' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($operator->type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                @if($operator->can_import)
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        {{ __('Import') }}
                                                    </span>
                                                @endif
                                                @if($operator->can_export)
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        {{ __('Export') }}
                                                    </span>
                                                @endif
                                                @if($operator->can_transfer)
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                                        {{ __('Transfer') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $operator->active ? __('Activo') : __('Inactivo') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $operator->created_at->format('d/m/Y') }}
                                        </td>
                                        <!-- NUEVO: Columna de acciones completas -->
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-2">
                                                <!-- Ver -->
                                                <button onclick="viewOperator({{ $operator->id }})" 
                                                        class="text-blue-600 hover:text-blue-900" 
                                                        title="{{ __('Ver detalles') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>

                                                <!-- Editar -->
                                                <button onclick="editOperator({{ $operator->id }})" 
                                                        class="text-indigo-600 hover:text-indigo-900" 
                                                        title="{{ __('Editar') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>

                                                <!-- Cambiar estado -->
                                                <button onclick="toggleOperatorStatus({{ $operator->id }}, '{{ $operator->active ? 'deactivate' : 'activate' }}')" 
                                                        class="text-{{ $operator->active ? 'orange' : 'green' }}-600 hover:text-{{ $operator->active ? 'orange' : 'green' }}-900" 
                                                        title="{{ $operator->active ? __('Desactivar') : __('Activar') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($operator->active)
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636" />
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        @endif
                                                    </svg>
                                                </button>

                                                <!-- Reset contraseña -->
                                                <button onclick="resetOperatorPassword({{ $operator->id }})" 
                                                        class="text-purple-600 hover:text-purple-900" 
                                                        title="{{ __('Reset contraseña') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                    </svg>
                                                </button>

                                                <!-- Eliminar -->
                                                <button onclick="deleteOperator({{ $operator->id }}, '{{ $operator->first_name }} {{ $operator->last_name }}')" 
                                                        class="text-red-600 hover:text-red-900" 
                                                        title="{{ __('Eliminar') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    @if($operators->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $operators->links() }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM9 9a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('Sin operadores') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Esta empresa aún no tiene operadores registrados.') }}
                        </p>
                        <div class="mt-6">
                            <button onclick="showCreateOperatorModal()" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                {{ __('Crear Primer Operador') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    @push('scripts')
    <script>
        // SCRIPT 8: JavaScript real para CRUD de operadores desde admin
        
        // Variables globales
        const companyId = {{ $company->id }};
        const csrfToken = '{{ csrf_token() }}';

        /**
         * Redirigir a formulario de creación de operador
         */
        function showCreateOperatorModal() {
            window.location.href = `{{ route('admin.companies.operators.create', $company) }}`;
        }

        /**
         * Ver detalles del operador
         */
        function viewOperator(operatorId) {
            window.location.href = `/admin/companies/${companyId}/operators/${operatorId}`;
        }

        /**
         * Editar operador
         */
        function editOperator(operatorId) {
            window.location.href = `/admin/companies/${companyId}/operators/${operatorId}/edit`;
        }

        /**
         * Cambiar estado del operador (AJAX)
         */
        function toggleOperatorStatus(operatorId, action) {
            const actionText = action === 'activate' ? 'activar' : 'desactivar';
            
            if (confirm(`¿Está seguro de ${actionText} este operador?`)) {
                
                // Mostrar indicador de carga
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                button.disabled = true;

                fetch(`/admin/companies/${companyId}/operators/${operatorId}/toggle-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        action: action
                    })
                })
                .then(response => {
                    if (response.ok) {
                        // Recargar la página para mostrar cambios
                        window.location.reload();
                    } else {
                        throw new Error('Error en la respuesta del servidor');
                    }
                })
                .catch(error => {
                    alert('Error al cambiar el estado del operador. Intente nuevamente.');
                    console.error('Error:', error);
                    
                    // Restaurar botón
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                });
            }
        }

        /**
         * Reset contraseña del operador (AJAX)
         */
        function resetOperatorPassword(operatorId) {
            if (confirm('¿Está seguro de resetear la contraseña de este operador?\n\nSe generará una nueva contraseña temporal que se mostrará en pantalla.')) {
                
                // Mostrar indicador de carga
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                button.disabled = true;

                fetch(`/admin/companies/${companyId}/operators/${operatorId}/reset-password`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(response => {
                    if (response.ok) {
                        // Recargar la página para mostrar el mensaje con la nueva contraseña
                        window.location.reload();
                    } else {
                        throw new Error('Error en la respuesta del servidor');
                    }
                })
                .catch(error => {
                    alert('Error al resetear la contraseña. Intente nuevamente.');
                    console.error('Error:', error);
                    
                    // Restaurar botón
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                });
            }
        }

        /**
         * Eliminar operador
         */
        function deleteOperator(operatorId, operatorName) {
            if (confirm(`¿Está seguro de eliminar al operador "${operatorName}"?\n\nEsta acción no se puede deshacer.`)) {
                
                // Crear formulario dinámico para enviar DELETE
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/companies/${companyId}/operators/${operatorId}`;

                // Token CSRF
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);

                // Método DELETE
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);

                // Enviar formulario
                document.body.appendChild(form);
                form.submit();
            }
        }

        /**
         * Seleccionar/deseleccionar todos los operadores
         */
        function toggleAllOperators() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.operator-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActionsVisibility();
        }

        /**
         * Cambio masivo de estado
         */
        function bulkToggleStatus() {
            const selectedOperators = Array.from(document.querySelectorAll('.operator-checkbox:checked')).map(cb => cb.value);
            
            if (selectedOperators.length === 0) {
                alert('Debe seleccionar al menos un operador.');
                return;
            }

            // Mostrar opciones de acción
            const action = confirm('¿Qué acción desea realizar?\n\nAceptar = Activar operadores\nCancelar = Desactivar operadores');
            const actionType = action ? 'activate' : 'deactivate';
            const actionText = action ? 'activar' : 'desactivar';

            if (confirm(`¿Está seguro de ${actionText} ${selectedOperators.length} operador(es) seleccionado(s)?`)) {
                
                // Crear formulario para envío masivo
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/companies/${companyId}/operators/bulk-toggle-status`;

                // Token CSRF
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);

                // Acción
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = actionType;
                form.appendChild(actionInput);

                // IDs de operadores
                selectedOperators.forEach(operatorId => {
                    const operatorInput = document.createElement('input');
                    operatorInput.type = 'hidden';
                    operatorInput.name = 'operator_ids[]';
                    operatorInput.value = operatorId;
                    form.appendChild(operatorInput);
                });

                // Enviar formulario
                document.body.appendChild(form);
                form.submit();
            }
        }

        /**
         * Eliminación masiva de operadores
         */
        function bulkDeleteOperators() {
            const selectedOperators = Array.from(document.querySelectorAll('.operator-checkbox:checked')).map(cb => cb.value);
            
            if (selectedOperators.length === 0) {
                alert('Debe seleccionar al menos un operador.');
                return;
            }

            if (confirm(`¿Está seguro de eliminar ${selectedOperators.length} operador(es) seleccionado(s)?\n\nEsta acción no se puede deshacer.`)) {
                
                // Crear formulario para eliminación masiva
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/companies/${companyId}/operators/bulk-delete`;

                // Token CSRF
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);

                // IDs de operadores
                selectedOperators.forEach(operatorId => {
                    const operatorInput = document.createElement('input');
                    operatorInput.type = 'hidden';
                    operatorInput.name = 'operator_ids[]';
                    operatorInput.value = operatorId;
                    form.appendChild(operatorInput);
                });

                // Enviar formulario
                document.body.appendChild(form);
                form.submit();
            }
        }

        /**
         * Actualizar visibilidad de acciones masivas
         */
        function updateBulkActionsVisibility() {
            const selectedCount = document.querySelectorAll('.operator-checkbox:checked').length;
            const bulkActions = document.getElementById('bulkActions');
            
            if (bulkActions) {
                bulkActions.style.display = selectedCount > 0 ? 'block' : 'none';
            }
        }

        /**
         * Inicialización cuando carga la página
         */
        document.addEventListener('DOMContentLoaded', function() {
            
            // Configurar checkbox maestro
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', toggleAllOperators);
            }

            // Configurar checkboxes individuales
            const operatorCheckboxes = document.querySelectorAll('.operator-checkbox');
            operatorCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Actualizar estado del checkbox maestro
                    const totalCheckboxes = operatorCheckboxes.length;
                    const checkedCheckboxes = document.querySelectorAll('.operator-checkbox:checked').length;
                    
                    if (selectAllCheckbox) {
                        if (checkedCheckboxes === 0) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = false;
                        } else if (checkedCheckboxes === totalCheckboxes) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = true;
                        } else {
                            selectAllCheckbox.indeterminate = true;
                            selectAllCheckbox.checked = false;
                        }
                    }
                    
                    updateBulkActionsVisibility();
                });
            });

            // Configurar tooltips si está disponible
            if (typeof bootstrap !== 'undefined') {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });

        // Funciones auxiliares para debugging
        window.debugOperators = {
            getSelected: function() {
                return Array.from(document.querySelectorAll('.operator-checkbox:checked')).map(cb => cb.value);
            },
            selectAll: function() {
                document.getElementById('selectAll').checked = true;
                toggleAllOperators();
            },
            clearAll: function() {
                document.getElementById('selectAll').checked = false;
                toggleAllOperators();
            }
        };
    </script>

    <!-- NUEVO: Agregar botones de acciones masivas que aparecen cuando hay selección -->
    <div id="bulkActions" style="display: none;" class="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 border border-gray-200">
        <div class="flex space-x-2">
            <button onclick="bulkToggleStatus()" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                📝 Cambiar Estado
            </button>
            <button onclick="bulkDeleteOperators()" 
                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-sm">
                🗑️ Eliminar Seleccionados
            </button>
        </div>
    </div>
    @endpush
</x-app-layout>