<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Panel de Administración') }}
            </h2>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600">Super Administrador</span>
                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
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
                                                {{ $alert['message'] }}
                                            </p>
                                        </div>
                                        @if(isset($alert['action']))
                                            <div class="flex-shrink-0 ml-3">
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Operadores -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Operadores</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_operators'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">{{ $stats['active_operators'] }}</span> activos
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
                                <span class="font-medium text-blue-600">Sistema activo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribución de Usuarios y Empresas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Usuarios por Rol -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Usuarios por Rol</h3>
                        <div class="space-y-3">
                            @foreach($usersByRole as $role)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ ucfirst(str_replace('-', ' ', $role->role)) }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $role->count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

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
                                            {{ $country->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $country->count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificados Próximos a Vencer -->
            @if(count($expiringCertificates) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Certificados Próximos a Vencer</h3>
                            <div class="space-y-3">
                                @foreach($expiringCertificates as $company)
                                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $company->business_name }}</p>
                                            <p class="text-xs text-gray-600">{{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-yellow-800">
                                                {{ \Carbon\Carbon::parse($company->certificate_expires_at)->diffForHumans() }}
                                            </p>
                                            <p class="text-xs text-gray-600">
                                                {{ \Carbon\Carbon::parse($company->certificate_expires_at)->format('d/m/Y') }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Actividad Reciente -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Empresas Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Empresas Recientes</h3>
                        <div class="space-y-3">
                            @forelse($recentCompanies as $company)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $company->business_name }}</p>
                                        <p class="text-xs text-gray-600">{{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-600">
                                            {{ $company->created_at->format('d/m/Y') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay empresas recientes</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Usuarios Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Usuarios Recientes</h3>
                        <div class="space-y-3">
                            @forelse($recentUsers as $user)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-600">{{ $user->roles->first()?->name ?? 'Sin rol' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-600">
                                            {{ $user->created_at->format('d/m/Y') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay usuarios recientes</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="mt-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="{{ route('admin.users.create') }}"
                               class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-800">Crear Usuario</span>
                            </a>

                            <a href="{{ route('admin.companies.create') }}"
                               class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span class="text-sm font-medium text-green-800">Crear Empresa</span>
                            </a>

                            <a href="{{ route('admin.reports.index') }}"
                               class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span class="text-sm font-medium text-purple-800">Ver Reportes</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Administración</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-button-link href="{{ route('admin.settings') }}">
                                ⚙️ Configuración general
                            </x-button-link>
                            <x-button-link href="{{ route('admin.tools') }}">
                                🧰 Herramientas
                            </x-button-link>
                            <x-button-link href="{{ route('admin.maintenance') }}">
                                🛠 Mantenimiento
                            </x-button-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
