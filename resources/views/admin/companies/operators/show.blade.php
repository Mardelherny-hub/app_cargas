<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detalles del Operador
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $operator->first_name }} {{ $operator->last_name }} • 
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $operator->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.companies.operators.edit', [$company, $operator]) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Editar
                </a>
                <a href="{{ route('admin.companies.operators', $company) }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

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
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">{{ $operator->first_name }} {{ $operator->last_name }}</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Mensajes de Session -->
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Principal -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Información Personal -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Información Personal</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->first_name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Apellido</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->last_name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Documento</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->document_number ?: 'No especificado' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->phone ?: 'No especificado' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cargo/Posición</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->position ?: 'No especificado' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $operator->type === 'external' ? 'Externo' : 'Interno' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Usuario -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Información de Usuario</h3>
                        </div>
                        <div class="px-6 py-4">
                            @if($operator->user)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $operator->user->email }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Estado del Usuario</label>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->user->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $operator->user->active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Último Acceso</label>
                                        <p class="mt-1 text-sm text-gray-900">
                                            @if($stats['last_login'])
                                                {{ $stats['last_login']->format('d/m/Y H:i') }}
                                            @else
                                                <span class="text-gray-500">Nunca se ha conectado</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Roles</label>
                                        <div class="mt-1">
                                            @if($operator->user->roles->count() > 0)
                                                @foreach($operator->user->roles as $role)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mr-1">
                                                        {{ $role->name }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-gray-500">Sin roles asignados</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                Este operador no tiene un usuario asociado.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Permisos Operativos -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Permisos Operativos</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center">
                                    <div class="flex items-center justify-center h-12 w-12 rounded-md {{ $operator->can_import ? 'bg-green-100' : 'bg-gray-100' }} mx-auto">
                                        <svg class="h-6 w-6 {{ $operator->can_import ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                        </svg>
                                    </div>
                                    <h4 class="mt-2 text-sm font-medium text-gray-900">Importar</h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->can_import ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $operator->can_import ? 'Permitido' : 'Denegado' }}
                                    </span>
                                </div>
                                <div class="text-center">
                                    <div class="flex items-center justify-center h-12 w-12 rounded-md {{ $operator->can_export ? 'bg-green-100' : 'bg-gray-100' }} mx-auto">
                                        <svg class="h-6 w-6 {{ $operator->can_export ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                    </div>
                                    <h4 class="mt-2 text-sm font-medium text-gray-900">Exportar</h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->can_export ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $operator->can_export ? 'Permitido' : 'Denegado' }}
                                    </span>
                                </div>
                                <div class="text-center">
                                    <div class="flex items-center justify-center h-12 w-12 rounded-md {{ $operator->can_transfer ? 'bg-green-100' : 'bg-gray-100' }} mx-auto">
                                        <svg class="h-6 w-6 {{ $operator->can_transfer ? 'text-green-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                    <h4 class="mt-2 text-sm font-medium text-gray-900">Transferir</h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->can_transfer ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $operator->can_transfer ? 'Permitido' : 'Denegado' }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <p class="text-sm text-gray-500">
                                    Total de permisos activos: <span class="font-medium">{{ $stats['permissions_count'] }}</span> de 3
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Panel Lateral -->
                <div class="space-y-6">
                    
                    <!-- Estadísticas -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Estadísticas</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-blue-600">{{ $stats['created_days_ago'] }}</p>
                                    <p class="text-xs text-gray-500">Días registrado</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-green-600">{{ $stats['total_shipments'] }}</p>
                                    <p class="text-xs text-gray-500">Cargas procesadas</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-purple-600">{{ $stats['active_voyages'] }}</p>
                                    <p class="text-xs text-gray-500">Viajes activos</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-orange-600">{{ $stats['permissions_count'] }}</p>
                                    <p class="text-xs text-gray-500">Permisos activos</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empresa Asociada -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Empresa Asociada</h3>
                        </div>
                        <div class="px-6 py-4 text-center">
                            <h4 class="font-medium text-gray-900">{{ $company->legal_name }}</h4>
                            @if($company->commercial_name && $company->commercial_name !== $company->legal_name)
                                <p class="text-sm text-gray-500 mt-1">"{{ $company->commercial_name }}"</p>
                            @endif
                            <div class="mt-2 space-x-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $company->tax_id }}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $company->country }}
                                </span>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('admin.companies.show', $company) }}" 
                                   class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                                    Ver Empresa →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Acciones Rápidas</h3>
                        </div>
                        <div class="px-6 py-4 space-y-3">
                            <!-- Cambiar Estado -->
                            <form method="POST" action="{{ route('admin.companies.operators.toggle-status', [$company, $operator]) }}" class="w-full">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        onclick="return confirm('¿Está seguro de cambiar el estado del operador?')"
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white {{ $operator->active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700' }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                    {{ $operator->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>

                            <!-- Reset Password -->
                            @if($operator->user)
                                <form method="POST" action="{{ route('admin.companies.operators.reset-password', [$company, $operator]) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            onclick="return confirm('¿Está seguro de resetear la contraseña? Se generará una nueva contraseña temporal.')"
                                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Reset Contraseña
                                    </button>
                                </form>
                            @endif

                            <!-- Eliminar -->
                            <form method="POST" action="{{ route('admin.companies.operators.destroy', [$company, $operator]) }}" class="w-full">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        onclick="return confirm('¿Está seguro de eliminar este operador? Esta acción no se puede deshacer.')"
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Eliminar Operador
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Información de Auditoría -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información de Auditoría</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Creado</label>
                            <p class="mt-1 text-sm text-gray-900">
                                {{ $operator->created_at->format('d/m/Y H:i') }}
                                <span class="text-gray-500">({{ $operator->created_at->diffForHumans() }})</span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Última Actualización</label>
                            <p class="mt-1 text-sm text-gray-900">
                                {{ $operator->updated_at->format('d/m/Y H:i') }}
                                <span class="text-gray-500">({{ $operator->updated_at->diffForHumans() }})</span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Verificación Email</label>
                            <p class="mt-1 text-sm">
                                @if($operator->user && $operator->user->email_verified_at)
                                    <span class="text-green-600">✓ Verificado</span>
                                    <span class="text-gray-500 block text-xs">{{ $operator->user->email_verified_at->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="text-red-600">✗ No verificado</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>