<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Panel de Empresa') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">{{ $company->business_name }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">{{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</span>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-{{ $webserviceStatus['active'] ? 'green' : 'red' }}-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">WebServices</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Alertas de la Empresa -->
            @if(count($companyAlerts) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <svg class="inline w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Alertas de la Empresa
                            </h3>
                            <div class="space-y-3">
                                @foreach($companyAlerts as $alert)
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
                                        @if($alert['action'])
                                            <div class="flex-shrink-0 ml-3">
                                                <a href="{{ $alert['action'] }}"
                                                   class="text-sm font-medium
                                                    @if($alert['type'] === 'danger') text-red-600 hover:text-red-500
                                                    @elseif($alert['type'] === 'warning') text-yellow-600 hover:text-yellow-500
                                                    @else text-blue-600 hover:text-blue-500
                                                    @endif">
                                                    Resolver →
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
                <!-- Operadores -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Operadores</dt>
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

                <!-- Cargas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Cargas Recientes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['recent_shipments'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-yellow-600">{{ $stats['pending_shipments'] }}</span> pendientes
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Viajes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Viajes Activos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['active_trips'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-blue-600">{{ $stats['completed_trips'] }}</span> completados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado del Certificado -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-{{ $certificateStatus['status'] === 'valid' ? 'green' : ($certificateStatus['status'] === 'warning' ? 'yellow' : 'red') }}-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Certificado</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        @if($certificateStatus['status'] === 'valid') Válido
                                        @elseif($certificateStatus['status'] === 'warning') Próximo a vencer
                                        @elseif($certificateStatus['status'] === 'expired') Vencido
                                        @else Sin certificado
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                {{ $certificateStatus['message'] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de la Empresa y WebServices -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Información de la Empresa -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Información de la Empresa</h3>
                            <a href="{{ route('company.settings') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Editar →
                            </a>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Razón Social:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $company->business_name }}</span>
                            </div>
                            @if($company->commercial_name)
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Nombre Comercial:</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $company->commercial_name }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">CUIT:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $company->tax_id }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">País:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Email:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $company->email }}</span>
                            </div>
                            @if($company->phone)
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Teléfono:</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $company->phone }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Estado de WebServices -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Estado de WebServices</h3>
                            <a href="{{ route('company.webservices.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Configurar →
                            </a>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Estado:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $webserviceStatus['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $webserviceStatus['active'] ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Ambiente:</span>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $webserviceStatus['environment'] === 'production' ? 'Producción' : 'Testing' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Pendientes:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $webserviceStatus['pending_sends'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Fallos:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $webserviceStatus['failed_sends'] }}</span>
                            </div>
                            @if($webserviceStatus['last_connection'])
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Última Conexión:</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $webserviceStatus['last_connection'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operadores Recientes y Actividad -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Operadores Recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Operadores Recientes</h3>
                            <a href="{{ route('company.operators.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todos →
                            </a>
                        </div>
                        <div class="space-y-3">
                            @forelse($recentOperators as $operator)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 mr-3">
                                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-medium text-white">
                                                    {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $operator->first_name }} {{ $operator->last_name }}</p>
                                            <p class="text-xs text-gray-600">{{ $operator->position ?? 'Operador' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $operator->active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay operadores registrados</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actividad Reciente</h3>
                        <div class="space-y-3">
                            @forelse($recentActivity as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-2 h-2 bg-{{ $activity['color'] }}-500 rounded-full"></div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ $activity['message'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $activity['date']->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay actividad reciente</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('company.shipments.create') }}"
                           class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="text-sm font-medium text-blue-800">Nueva Carga</span>
                        </a>

                        <a href="{{ route('company.trips.create') }}"
                           class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span class="text-sm font-medium text-green-800">Nuevo Viaje</span>
                        </a>

                        <a href="{{ route('company.operators.create') }}"
                           class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="text-sm font-medium text-purple-800">Nuevo Operador</span>
                        </a>

                        <a href="{{ route('company.reports.index') }}"
                           class="flex items-center p-4 bg-teal-50 rounded-lg hover:bg-teal-100 transition-colors">
                            <svg class="w-6 h-6 text-teal-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="text-sm font-medium text-teal-800">Ver Reportes</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
