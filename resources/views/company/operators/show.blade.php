<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Detalles del Operador') }} - {{ $operator->first_name }} {{ $operator->last_name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('company.operators.edit', $operator) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                </a>
                <a href="{{ route('company.operators.permissions', $operator) }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Permisos
                </a>
                <a href="{{ route('company.operators.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado y Acciones Rápidas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div class="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center">
                                    <span class="text-xl font-medium text-white">
                                        {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    {{ $operator->first_name }} {{ $operator->last_name }}
                                </h3>
                                <p class="text-lg text-gray-500">
                                    {{ $operator->position ?: 'Sin cargo definido' }}
                                </p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $operator->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        Creado hace {{ $stats['days_since_creation'] }} días
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-2">
                            <form method="POST" action="{{ route('company.operators.toggle-status', $operator) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="bg-{{ $operator->active ? 'red' : 'green' }}-600 hover:bg-{{ $operator->active ? 'red' : 'green' }}-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                                        onclick="return confirm('¿Está seguro de {{ $operator->active ? 'desactivar' : 'activar' }} este operador?')">
                                    {{ $operator->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Personal y de Contacto -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información Personal</h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre completo</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $operator->first_name }} {{ $operator->last_name }}</dd>
                            </div>
                            @if($operator->document_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $operator->document_number }}</dd>
                                </div>
                            @endif
                            @if($operator->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $operator->phone }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Cargo</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $operator->position ?: 'Sin cargo definido' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($operator->type) }} Operator</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de creación</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $operator->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Acceso</h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $operator->user?->email ?: 'Sin usuario asociado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Estado del usuario</dt>
                                <dd class="mt-1">
                                    @if($operator->user)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $operator->user->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $operator->user->active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Sin usuario
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Último acceso</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($stats['last_activity'])
                                        {{ $stats['last_activity']->format('d/m/Y H:i') }}
                                        <span class="text-gray-500">({{ $stats['last_activity']->diffForHumans() }})</span>
                                    @else
                                        <span class="text-gray-500">Nunca</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Rol del sistema</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($operator->user && $operator->user->roles->isNotEmpty())
                                        {{ $operator->user->roles->pluck('name')->join(', ') }}
                                    @else
                                        <span class="text-gray-500">Sin rol asignado</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Zona horaria</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $operator->user?->timezone ?: 'UTC' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Permisos del Operador -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Permisos del Operador</h3>
                        <a href="{{ route('company.operators.permissions', $operator) }}"
                           class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                            Gestionar Permisos →
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Permiso de Importación -->
                        <div class="border rounded-lg p-4 {{ $operator->can_import ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($operator->can_import)
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium {{ $operator->can_import ? 'text-green-900' : 'text-gray-900' }}">
                                        Importar Datos
                                    </h4>
                                    <p class="text-xs {{ $operator->can_import ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $operator->can_import ? 'Permitido' : 'No permitido' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Permiso de Exportación -->
                        <div class="border rounded-lg p-4 {{ $operator->can_export ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($operator->can_export)
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium {{ $operator->can_export ? 'text-green-900' : 'text-gray-900' }}">
                                        Exportar Datos
                                    </h4>
                                    <p class="text-xs {{ $operator->can_export ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $operator->can_export ? 'Permitido' : 'No permitido' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Permiso de Transferencia -->
                        <div class="border rounded-lg p-4 {{ $operator->can_transfer ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($operator->can_transfer)
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium {{ $operator->can_transfer ? 'text-green-900' : 'text-gray-900' }}">
                                        Transferir Cargas
                                    </h4>
                                    <p class="text-xs {{ $operator->can_transfer ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $operator->can_transfer ? 'Permitido' : 'No permitido' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($operator->special_permissions && count($operator->special_permissions) > 0)
                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Permisos Especiales</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($operator->special_permissions as $permission)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $permission }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Estadísticas y Actividad -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Estadísticas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas</h3>
                        <dl class="space-y-4">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Total de cargas</dt>
                                <dd class="text-sm text-gray-900">{{ $stats['total_shipments'] }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Cargas recientes</dt>
                                <dd class="text-sm text-gray-900">{{ $stats['recent_shipments'] }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Total de viajes</dt>
                                <dd class="text-sm text-gray-900">{{ $stats['total_trips'] }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Días en el sistema</dt>
                                <dd class="text-sm text-gray-900">{{ $stats['days_since_creation'] }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <p class="text-xs text-gray-500">
                                Las estadísticas de cargas y viajes estarán disponibles cuando se implementen esos módulos.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                        <div class="space-y-3">
                            @forelse($recentActivity as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-2 h-2 bg-{{ $activity['color'] }}-500 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $activity['message'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $activity['date']->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin actividad reciente</h3>
                                    <p class="mt-1 text-sm text-gray-500">Este operador aún no ha realizado acciones en el sistema</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
