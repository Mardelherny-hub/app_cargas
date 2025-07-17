<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalles del Operador') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $operator->full_name }} - {{ $operator->type_display }}
                </p>
            </div>
            <div class="flex space-x-3">
                @if($permissions['canEdit'])
                    <a href="{{ route('company.operators.edit', $operator) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        {{ __('Editar') }}
                    </a>
                @endif
                <a href="{{ route('company.operators.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    {{ __('Volver al Listado') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado del Operador -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Estado Actual') }}</h3>
                        @if($permissions['canToggleStatus'])
                            <form method="POST" action="{{ route('company.operators.toggle-status', $operator) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        onclick="return confirm('¿Está seguro de cambiar el estado del operador?')"
                                        class="text-sm px-3 py-1 rounded-md {{ $operator->active ? 'bg-red-100 text-red-800 hover:bg-red-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}">
                                    {{ $operator->active ? __('Desactivar') : __('Activar') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Estado del Operador -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                @if($operator->active)
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <p class="text-sm font-medium text-gray-900">{{ __('Operador') }}</p>
                            <p class="text-xs text-gray-500">{{ $stats['account_status'] }}</p>
                        </div>

                        <!-- Estado del Usuario -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                @if($operator->user && $operator->user->active)
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <p class="text-sm font-medium text-gray-900">{{ __('Usuario') }}</p>
                            <p class="text-xs text-gray-500">{{ $stats['user_status'] }}</p>
                        </div>

                        <!-- Último Acceso -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">{{ __('Último Acceso') }}</p>
                            <p class="text-xs text-gray-500">
                                @if($stats['last_activity'])
                                    {{ $stats['last_activity']->diffForHumans() }}
                                @else
                                    {{ __('Nunca') }}
                                @endif
                            </p>
                        </div>

                        <!-- Días en el Sistema -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">{{ __('En el Sistema') }}</p>
                            <p class="text-xs text-gray-500">{{ $stats['days_since_creation'] }} {{ __('días') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Información Personal -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Información Personal') }}</h3>
                        </div>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Nombre Completo -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Nombre Completo') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->full_name }}</p>
                                </div>

                                <!-- Cargo/Posición -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Cargo/Posición') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->position ?? __('No especificado') }}</p>
                                </div>

                                <!-- Documento -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Número de Documento') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->document_number ?? __('No especificado') }}</p>
                                </div>

                                <!-- Teléfono -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Teléfono') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->phone ?? __('No especificado') }}</p>
                                </div>
                            </div>

                            <!-- Tipo de Operador -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Tipo de Operador') }}</label>
                                <div class="mt-1 flex items-center">
                                    @if($operator->type === 'internal')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ __('Interno - Sistema Central') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ __('Externo - Empleado de Empresa') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    @if($operator->type === 'internal')
                                        {{ __('Puede gestionar múltiples empresas y tiene acceso global al sistema.') }}
                                    @else
                                        {{ __('Puede trabajar únicamente con') }} {{ $operator->company_display }}.
                                    @endif
                                </p>
                            </div>

                            <!-- Empresa Asociada -->
                            @if($operator->company)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Empresa') }}</label>
                                    <div class="mt-1">
                                        <p class="text-sm text-gray-900">{{ $operator->company->legal_name }}</p>
                                        @if($operator->company->commercial_name && $operator->company->commercial_name !== $operator->company->legal_name)
                                            <p class="text-xs text-gray-500">{{ __('Nombre comercial:') }} {{ $operator->company->commercial_name }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500">{{ __('CUIT:') }} {{ $operator->company->tax_id }} ({{ $operator->company->country }})</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Panel Lateral -->
                <div class="space-y-6">
                    <!-- Permisos Operativos -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Permisos Operativos') }}</h3>
                                @if($permissions['canManagePermissions'])
                                    <button type="button"
                                            onclick="togglePermissionsEdit()"
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        {{ __('Gestionar') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <!-- Permiso de Importación -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 {{ $operator->can_import ? 'text-green-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="text-sm {{ $operator->can_import ? 'text-gray-900' : 'text-gray-500' }}">
                                        {{ __('Puede Importar') }}
                                    </span>
                                </div>
                                @if($operator->can_import)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ __('Activo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ __('Inactivo') }}
                                    </span>
                                @endif
                            </div>

                            <!-- Permiso de Exportación -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 {{ $operator->can_export ? 'text-green-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 13h6m-3-3v6" />
                                    </svg>
                                    <span class="text-sm {{ $operator->can_export ? 'text-gray-900' : 'text-gray-500' }}">
                                        {{ __('Puede Exportar') }}
                                    </span>
                                </div>
                                @if($operator->can_export)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ __('Activo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ __('Inactivo') }}
                                    </span>
                                @endif
                            </div>

                            <!-- Permiso de Transferencia -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 {{ $operator->can_transfer ? 'text-green-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    <span class="text-sm {{ $operator->can_transfer ? 'text-gray-900' : 'text-gray-500' }}">
                                        {{ __('Puede Transferir') }}
                                    </span>
                                </div>
                                @if($operator->can_transfer)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ __('Activo') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ __('Inactivo') }}
                                    </span>
                                @endif
                            </div>

                            @if(!$operator->can_import && !$operator->can_export && !$operator->can_transfer)
                                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-800">
                                                {{ __('Este operador no tiene permisos operativos activos.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Información de Acceso -->
                    @if($operator->user)
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Acceso al Sistema') }}</h3>
                            </div>

                            <div class="p-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $operator->user->email }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Roles') }}</label>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @forelse($operator->user->roles as $role)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="text-sm text-gray-500">{{ __('Sin roles asignados') }}</span>
                                        @endforelse
                                    </div>
                                </div>

                                @if($operator->user->email_verified_at)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">{{ __('Email Verificado') }}</label>
                                        <p class="mt-1 text-sm text-green-600">
                                            {{ $operator->user->email_verified_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                @else
                                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                        <p class="text-sm text-yellow-800">{{ __('Email no verificado') }}</p>
                                    </div>
                                @endif

                                @if($permissions['canResetPassword'])
                                    <div class="pt-4 border-t border-gray-200">
                                        <button type="button"
                                                onclick="resetPassword('{{ $operator->id }}')"
                                                class="text-sm text-red-600 hover:text-red-800">
                                            {{ __('Restablecer Contraseña') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Actividad Reciente -->
            @if($permissions['canViewActivity'] && !empty($recentActivity))
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Actividad Reciente') }}</h3>
                    </div>

                    <div class="p-6">
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @foreach($recentActivity as $index => $activity)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full bg-{{ $activity['color'] }}-500 flex items-center justify-center ring-8 ring-white">
                                                        @switch($activity['icon'])
                                                            @case('login')
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                                                </svg>
                                                                @break
                                                            @case('edit')
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                @break
                                                            @case('user-plus')
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                                </svg>
                                                                @break
                                                            @default
                                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                        @endswitch
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div>
                                                        <div class="text-sm">
                                                            <span class="font-medium text-gray-900">{{ $activity['message'] }}</span>
                                                        </div>
                                                        <p class="mt-0.5 text-sm text-gray-500">
                                                            {{ $activity['date']->format('d/m/Y H:i') }}
                                                            <span class="text-gray-400">•</span>
                                                            {{ $activity['date']->diffForHumans() }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Acciones Adicionales -->
            @if($permissions['canDelete'])
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Acciones Avanzadas') }}</h3>
                    </div>

                    <div class="p-6">
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">{{ __('Eliminar Operador') }}</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>{{ __('Esta acción eliminará permanentemente al operador y su usuario asociado.') }}</p>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button"
                                                onclick="confirmDelete('{{ $operator->id }}', '{{ $operator->full_name }}')"
                                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                            {{ __('Eliminar Operador') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function confirmDelete(operatorId, operatorName) {
            if (confirm(`¿Está seguro de que desea eliminar al operador "${operatorName}"?\n\nEsta acción eliminará:\n- Los datos del operador\n- Su usuario del sistema\n- Su acceso completo\n\nEsta acción NO se puede deshacer.`)) {
                // Crear formulario dinámico
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

        function resetPassword(operatorId) {
            if (confirm('¿Está seguro de que desea restablecer la contraseña de este operador?\n\nSe enviará un email con las nuevas credenciales.')) {
                // Implementar reset de password
                alert('Funcionalidad en desarrollo');
            }
        }

        function togglePermissionsEdit() {
            // Implementar edición de permisos
            alert('Funcionalidad en desarrollo - usar el botón Editar');
        }
    </script>
    @endpush
</x-app-layout>
