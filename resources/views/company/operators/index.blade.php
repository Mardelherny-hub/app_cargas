<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Operadores de') }} {{ $company->business_name }}
            </h2>
            @if($permissions['canCreate'])
                <a href="{{ route('company.operators.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    {{ __('Nuevo Operador') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        {{ __('Total Operadores') }}
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['total'] ?? 0 }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        {{ __('Activos') }}
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['active'] ?? 0 }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        {{ __('Pueden Importar') }}
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['can_import'] ?? 0 }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 13h6m-3-3v6" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        {{ __('Pueden Exportar') }}
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['can_export'] ?? 0 }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="bg-white shadow sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <form method="GET" action="{{ route('company.operators.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                            <!-- Búsqueda por nombre -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">
                                    {{ __('Buscar por nombre') }}
                                </label>
                                <input type="text"
                                       name="search"
                                       id="search"
                                       value="{{ request('search') }}"
                                       placeholder="Nombre o apellido..."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <!-- Filtro por estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    {{ __('Estado') }}
                                </label>
                                <select name="status"
                                        id="status"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">{{ __('Todos los estados') }}</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>
                                        {{ __('Activos') }}
                                    </option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>
                                        {{ __('Inactivos') }}
                                    </option>
                                </select>
                            </div>

                            <!-- Filtro por permisos -->
                            <div>
                                <label for="permission" class="block text-sm font-medium text-gray-700">
                                    {{ __('Permisos') }}
                                </label>
                                <select name="permission"
                                        id="permission"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">{{ __('Todos los permisos') }}</option>
                                    <option value="can_import" {{ request('permission') === 'can_import' ? 'selected' : '' }}>
                                        {{ __('Pueden Importar') }}
                                    </option>
                                    <option value="can_export" {{ request('permission') === 'can_export' ? 'selected' : '' }}>
                                        {{ __('Pueden Exportar') }}
                                    </option>
                                    <option value="can_transfer" {{ request('permission') === 'can_transfer' ? 'selected' : '' }}>
                                        {{ __('Pueden Transferir') }}
                                    </option>
                                </select>
                            </div>

                            <!-- Botones de acción -->
                            <div class="flex items-end space-x-2">
                                <button type="submit"
                                        class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    {{ __('Filtrar') }}
                                </button>
                                <a href="{{ route('company.operators.index') }}"
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    {{ __('Limpiar') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Operadores -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul role="list" class="divide-y divide-gray-200">
                    @forelse($operators as $operator)
                        <li class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Información del operador -->
                                    <div class="ml-4">
                                        <div class="flex items-center">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $operator->first_name }} {{ $operator->last_name }}
                                            </p>

                                            <!-- Estado -->
                                            @if($operator->active)
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    {{ __('Activo') }}
                                                </span>
                                            @else
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    {{ __('Inactivo') }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-1 flex items-center text-sm text-gray-500">
                                            <p>{{ $operator->position ?? __('Sin cargo definido') }}</p>
                                            @if($operator->document_number)
                                                <span class="mx-2">•</span>
                                                <p>{{ __('Doc:') }} {{ $operator->document_number }}</p>
                                            @endif
                                            @if($operator->phone)
                                                <span class="mx-2">•</span>
                                                <p>{{ $operator->phone }}</p>
                                            @endif
                                        </div>

                                        <!-- Permisos -->
                                        <div class="mt-2 flex items-center space-x-2">
                                            @if($operator->can_import)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                    {{ __('Importar') }}
                                                </span>
                                            @endif

                                            @if($operator->can_export)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 13h6m-3-3v6" />
                                                    </svg>
                                                    {{ __('Exportar') }}
                                                </span>
                                            @endif

                                            @if($operator->can_transfer)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                    </svg>
                                                    {{ __('Transferir') }}
                                                </span>
                                            @endif

                                            @if(!$operator->can_import && !$operator->can_export && !$operator->can_transfer)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ __('Sin permisos operativos') }}
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Usuario asociado -->
                                        @if($operator->user)
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ __('Usuario:') }} {{ $operator->user->email }}
                                                @if($operator->user->last_access)
                                                    • {{ __('Último acceso:') }} {{ $operator->user->last_access->diffForHumans() }}
                                                @endif
                                            </div>
                                        @else
                                            <div class="mt-1 text-xs text-red-600">
                                                {{ __('Sin usuario asociado') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Acciones -->
                                <div class="flex items-center space-x-2">
                                    <!-- Ver detalles - siempre disponible ya que estamos en index -->
                                    <a href="{{ route('company.operators.show', $operator) }}"
                                       class="text-gray-400 hover:text-gray-600"
                                       title="{{ __('Ver Detalles') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    @if($permissions['canEdit'])
                                        <a href="{{ route('company.operators.edit', $operator) }}"
                                           class="text-blue-400 hover:text-blue-600"
                                           title="{{ __('Editar') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                    @endif

                                    @if($permissions['canManageStatus'])
                                        <form method="POST" action="{{ route('company.operators.toggle-status', $operator) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    onclick="return confirm('¿Está seguro de cambiar el estado del operador?')"
                                                    class="text-{{ $operator->active ? 'red' : 'green' }}-400 hover:text-{{ $operator->active ? 'red' : 'green' }}-600"
                                                    title="{{ $operator->active ? __('Desactivar') : __('Activar') }}">
                                                @if($operator->active)
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>
                                    @endif

                                    @if($permissions['canDelete'])
                                        <button type="button"
                                                onclick="confirmDelete('{{ $operator->id }}', '{{ $operator->first_name }} {{ $operator->last_name }}')"
                                                class="text-red-400 hover:text-red-600"
                                                title="{{ __('Eliminar') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No hay operadores') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('Comience creando un nuevo operador para su empresa.') }}
                                </p>
                                @if($permissions['canCreate'])
                                    <div class="mt-6">
                                        <a href="{{ route('company.operators.create') }}"
                                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            {{ __('Crear Primer Operador') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforelse
                </ul>

                <!-- Paginación -->
                @if($operators->hasPages())
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $operators->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function confirmDelete(operatorId, operatorName) {
            if (confirm(`¿Está seguro de que desea eliminar al operador "${operatorName}"?\n\nEsta acción no se puede deshacer.`)) {
                // Crear un formulario dinámico para enviar la petición DELETE
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/company/operators/${operatorId}`;

                // Token CSRF
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);

                // Método DELETE
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                form.appendChild(methodField);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    @endpush
</x-app-layout>
