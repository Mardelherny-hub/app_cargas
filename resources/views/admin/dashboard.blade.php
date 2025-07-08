<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Panel de Administración') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Sistema multi-empresa con roles de negocio</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">Super Administrador</span>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Sistema Operativo</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Alertas del Sistema -->
            @if(count($systemAlerts) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <svg class="inline w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Alertas del Sistema
                            </h3>
                            <div class="space-y-3">
                                @foreach($systemAlerts as $alert)
                                    <div class="flex items-center p-3 rounded-lg
                                        @if($alert['type'] === 'danger') bg-red-50 border border-red-200
                                        @elseif($alert['type'] === 'warning') bg-yellow-50 border border-yellow-200
                                        @else bg-blue-50 border border-blue-200
                                        @endif">
                                        <div class="flex-shrink-0 mr-3">
                                            @if($alert['type'] === 'danger')
                                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($alert['type'] === 'warning')
                                                <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium
                                                @if($alert['type'] === 'danger') text-red-800
                                                @elseif($alert['type'] === 'warning') text-yellow-800
                                                @else text-blue-800
                                                @endif">
                                                {{ $alert['title'] }}
                                            </p>
                                            <p class="text-sm
                                                @if($alert['type'] === 'danger') text-red-700
                                                @elseif($alert['type'] === 'warning') text-yellow-700
                                                @else text-blue-700
                                                @endif">
                                                {{ $alert['message'] }}
                                            </p>
                                        </div>
                                        @if(isset($alert['action']))
                                            <div class="flex-shrink-0 ml-4">
                                                <a href="{{ $alert['action'] }}"
                                                   class="text-sm font-medium
                                                    @if($alert['type'] === 'danger') text-red-600 hover:text-red-500
                                                    @elseif($alert['type'] === 'warning') text-yellow-600 hover:text-yellow-500
                                                    @else text-blue-600 hover:text-blue-500
                                                    @endif">
                                                    Ver detalles →
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Total Usuarios -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Usuarios</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_users'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">{{ $stats['active_users'] }}</span> activos
                                <span class="mx-2">•</span>
                                <span class="font-medium text-blue-600">{{ $stats['properly_configured_users'] }}</span> configurados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Empresas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Empresas</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_companies'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">{{ $stats['active_companies'] }}</span> activas
                                <span class="mx-2">•</span>
                                <span class="font-medium text-blue-600">{{ $stats['companies_with_roles'] }}</span> con roles
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Webservices Activos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Webservices</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $webserviceStats['anticipada'] + $webserviceStats['micdta'] + $webserviceStats['desconsolidados'] + $webserviceStats['transbordos'] }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-indigo-600">{{ $webserviceStats['multiple_ws'] }}</span> multi-WS
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accesos Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Accesos (7 días)</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['recent_logins'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">Sistema activo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NUEVO: Distribución por Roles de Negocio (Roberto's key requirement) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Empresas por Roles de Negocio -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Empresas por Roles de Negocio
                        </h3>
                        <div class="space-y-3">
                            @foreach(['Cargas', 'Desconsolidador', 'Transbordos', 'Multiples', 'Sin_roles'] as $role)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3
                                            @if($role === 'Cargas') bg-blue-500
                                            @elseif($role === 'Desconsolidador') bg-green-500
                                            @elseif($role === 'Transbordos') bg-purple-500
                                            @elseif($role === 'Multiples') bg-yellow-500
                                            @else bg-gray-400
                                            @endif"></div>
                                        <span class="text-sm font-medium text-gray-700">
                                            @if($role === 'Sin_roles') Sin Roles
                                            @elseif($role === 'Multiples') Múltiples Roles
                                            @else {{ $role }}
                                            @endif
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $companiesByBusinessRole[$role] ?? 0 }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de Webservices -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                            </svg>
                            Webservices por Tipo
                        </h3>
                        <div class="space-y-3">
                            @foreach(['anticipada', 'micdta', 'desconsolidados', 'transbordos'] as $ws)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3
                                            @if($ws === 'anticipada') bg-blue-500
                                            @elseif($ws === 'micdta') bg-green-500
                                            @elseif($ws === 'desconsolidados') bg-yellow-500
                                            @else bg-purple-500
                                            @endif"></div>
                                        <span class="text-sm font-medium text-gray-700 capitalize">
                                            {{ $ws }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $webserviceStats[$ws] ?? 0 }}
                                    </span>
                                </div>
                            @endforeach
                            <div class="border-t pt-2 mt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Multi-WS</span>
                                    <span class="text-sm font-semibold text-indigo-600">
                                        {{ $webserviceStats['multiple_ws'] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usuarios por Rol (simplificado según Roberto) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            Usuarios por Rol
                        </h3>
                        <div class="space-y-3">
                            @foreach($usersByRole as $role)
                                @php
                                    $roleDisplay = match($role->role) {
                                        'super-admin' => 'Super Admin',
                                        'company-admin' => 'Admin Empresa',
                                        'user' => 'Usuario',
                                        default => ucfirst(str_replace('-', ' ', $role->role))
                                    };
                                    $roleColor = match($role->role) {
                                        'super-admin' => 'bg-red-500',
                                        'company-admin' => 'bg-yellow-500',
                                        'user' => 'bg-blue-500',
                                        default => 'bg-gray-500'
                                    };
                                @endphp
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 {{ $roleColor }} rounded-full mr-3"></div>
                                        <span class="text-sm font-medium text-gray-700">{{ $roleDisplay }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $role->count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empresas por País + Enlaces Rápidos + Problemas -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Empresas por País -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Empresas por País</h3>
                        <div class="space-y-3">
                            @foreach($companiesByCountry as $country)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ $country['country'] === 'AR' ? 'Argentina' : 'Paraguay' }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-semibold text-gray-900">{{ $country['count'] }}</span>
                                        <div class="text-xs text-gray-500">
                                            {{ $country['active_count'] }} activas
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Enlaces Rápidos (movido aquí para mayor accesibilidad) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Acciones Rápidas
                        </h3>
                        <div class="space-y-3">
                            <a href="{{ route('admin.companies.create') }}"
                               class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition-colors">
                                <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-800">Crear Empresa</span>
                            </a>

                            <a href="{{ route('admin.users.create') }}"
                               class="flex items-center p-3 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg transition-colors">
                                <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                                <span class="text-sm font-medium text-green-800">Crear Usuario</span>
                            </a>

                            <a href="{{ route('admin.reports.index') }}"
                               class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg transition-colors">
                                <svg class="w-5 h-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm font-medium text-purple-800">Ver Reportes</span>
                            </a>

                            <a href="{{ route('admin.system.settings') }}"
                               class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-colors">
                                <svg class="w-5 h-5 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-800">Configuración</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- NUEVO: Empresas que necesitan configuración -->
                @if(count($companiesNeedingSetup) > 0)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 text-red-600">
                                <svg class="inline w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Empresas con Problemas
                            </h3>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                @foreach($companiesNeedingSetup as $item)
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                        <div class="font-medium text-sm text-red-800">
                                            {{ $item['company']->business_name }}
                                        </div>
                                        <div class="text-xs text-red-600 mt-1">
                                            @foreach($item['errors'] as $error)
                                                <div>• {{ $error }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Placeholder cuando no hay problemas -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 text-green-600">
                                <svg class="inline w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Sistema Saludable
                            </h3>
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-gray-600">Todas las empresas están correctamente configuradas</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Empresas y Usuarios Recientes -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Empresas Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Empresas Recientes</h3>
                        <div class="space-y-3">
                            @forelse($recentCompanies as $company)
                                <div class="flex items-center justify-between border-b pb-2">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $company->business_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                            • Roles: {{ $company->roles_display }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $company->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">No hay empresas recientes</p>
                            @endforelse
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('admin.companies.index') }}" class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todas las empresas →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Usuarios Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Usuarios Recientes</h3>
                        <div class="space-y-3">
                            @forelse($recentUsers as $user)
                                <div class="flex items-center justify-between border-b pb-2">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $user->email }}
                                            @if($user->roles->isNotEmpty())
                                                • {{ $user->roles->first()->name }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $user->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">No hay usuarios recientes</p>
                            @endforelse
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todos los usuarios →
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificados próximos a vencer -->
            @if($expiringCertificates->count() > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 text-yellow-600">
                                <svg class="inline w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Certificados Próximos a Vencer
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($expiringCertificates as $company)
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="font-medium text-sm text-yellow-800">
                                            {{ $company->business_name }}
                                        </div>
                                        <div class="text-xs text-yellow-600 mt-1">
                                            Vence: {{ $company->certificate_expires_at->format('d/m/Y') }}
                                            ({{ $company->certificate_expires_at->diffForHumans() }})
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            Roles: {{ $company->roles_display }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Enlaces Rápidos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('admin.companies.create') }}"
                   class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span class="text-sm font-medium text-blue-800">Crear Empresa</span>
                    </div>
                </a>

                <a href="{{ route('admin.users.create') }}"
                   class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        <span class="text-sm font-medium text-green-800">Crear Usuario</span>
                    </div>
                </a>

                <a href="{{ route('admin.reports.index') }}"
                   class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="text-sm font-medium text-purple-800">Ver Reportes</span>
                    </div>
                </a>

                <a href="{{ route('admin.system.settings') }}"
                   class="bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg p-4 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84 1.724 1.724 0 00-1.066 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-1.066-1.066c-.427-.426-1.12-.426-1.546 0l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35a1.724 1.724 0 001.066 1.066c.427.426.427 1.12 0 1.546l-.84.84a1.724 1.724 0 01-1.066.84c-1.756.426-1.756 2.924 0 3.35"/>
                        </svg>
                        <span class="text-sm font-medium text-gray-800">Configuración</span>
                    </div>
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
