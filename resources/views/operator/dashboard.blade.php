<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Mi Panel de Trabajo') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">{{ $operator->full_name }} - {{ $company->business_name }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">{{ $companyStatus['name'] }}</span>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-{{ $companyStatus['ws_active'] ? 'green' : 'red' }}-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">{{ $companyStatus['country'] === 'AR' ? 'Argentina' : 'Paraguay' }}</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Alertas Personales -->
            @if(count($personalAlerts) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <svg class="inline w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Avisos Importantes
                            </h3>
                            <div class="space-y-3">
                                @foreach($personalAlerts as $alert)
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
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Resumen del Trabajo de Hoy -->
            <div class="mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            Resumen del día {{ $todaysSummary['date']->format('d/m/Y') }}
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ $todaysSummary['shipments_created'] }}</div>
                                <div class="text-sm text-gray-600">Cargas creadas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ $todaysSummary['shipments_updated'] }}</div>
                                <div class="text-sm text-gray-600">Cargas actualizadas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">{{ $todaysSummary['trips_created'] }}</div>
                                <div class="text-sm text-gray-600">Viajes creados</div>
                            </div>
                            @if($permissions['can_import'])
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-orange-600">{{ $todaysSummary['imports_performed'] }}</div>
                                    <div class="text-sm text-gray-600">Importaciones</div>
                                </div>
                            @else
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-teal-600">{{ $todaysSummary['reports_generated'] }}</div>
                                    <div class="text-sm text-gray-600">Reportes</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Mis Cargas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Mis Cargas</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['my_shipments'] }}</dd>
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

                <!-- Mis Viajes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Mis Viajes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['my_trips'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-blue-600">{{ $stats['active_trips'] }}</span> activos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permisos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Permisos</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ ($permissions['can_import'] ? 1 : 0) + ($permissions['can_export'] ? 1 : 0) + ($permissions['can_transfer'] ? 1 : 0) }}/3
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                @if($permissions['can_import'] && $permissions['can_export'] && $permissions['can_transfer'])
                                    <span class="font-medium text-green-600">Completos</span>
                                @else
                                    <span class="font-medium text-yellow-600">Limitados</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Última Actividad -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Última Actividad</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        @if($stats['last_activity'])
                                            {{ $stats['last_activity']->format('H:i') }}
                                        @else
                                            --:--
                                        @endif
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                @if($stats['last_activity'])
                                    {{ $stats['last_activity']->diffForHumans() }}
                                @else
                                    Sin actividad registrada
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mis Permisos y Estado de la Empresa -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Mis Permisos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Mis Permisos</h3>
                            <span class="text-sm text-gray-600">{{ $operator->type_name }}</span>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Importar datos:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $permissions['can_import'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $permissions['can_import'] ? 'Permitido' : 'No permitido' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Exportar datos:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $permissions['can_export'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $permissions['can_export'] ? 'Permitido' : 'No permitido' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Transferir cargas:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $permissions['can_transfer'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $permissions['can_transfer'] ? 'Permitido' : 'No permitido' }}
                                </span>
                            </div>
                            @if(count($permissions['special_permissions']) > 0)
                                <div class="pt-3 border-t border-gray-200">
                                    <span class="text-sm font-medium text-gray-700">Permisos especiales:</span>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($permissions['special_permissions'] as $permission)
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $permission }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Estado de la Empresa -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Estado de la Empresa</h3>
                            <span class="text-xs text-gray-600">{{ $companyStatus['country'] === 'AR' ? 'Argentina' : 'Paraguay' }}</span>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Certificado digital:</span>
                                @if($companyStatus['certificate_status'] === 'valid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Válido
                                    </span>
                                @elseif($companyStatus['certificate_status'] === 'warning')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Por vencer
                                    </span>
                                @elseif($companyStatus['certificate_status'] === 'expired')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Vencido
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Sin certificado
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">WebServices:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $companyStatus['ws_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $companyStatus['ws_active'] ? 'Activos' : 'Inactivos' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Ambiente:</span>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $companyStatus['ws_environment'] === 'production' ? 'Producción' : 'Testing' }}
                                </span>
                            </div>
                            @if($companyStatus['certificate_expires_at'])
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Vencimiento:</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ \Carbon\Carbon::parse($companyStatus['certificate_expires_at'])->format('d/m/Y') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($quickActions as $action)
                                @if($action['available'])
                                    <a href="{{ route($action['route']) }}"
                                       class="flex items-center p-4 bg-{{ $action['color'] }}-50 rounded-lg hover:bg-{{ $action['color'] }}-100 transition-colors">
                                        <svg class="w-6 h-6 text-{{ $action['color'] }}-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($action['icon'] === 'plus-circle')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            @elseif($action['icon'] === 'truck')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            @elseif($action['icon'] === 'list')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                            @elseif($action['icon'] === 'upload')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            @elseif($action['icon'] === 'download')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            @elseif($action['icon'] === 'exchange')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                            @endif
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-{{ $action['color'] }}-800">{{ $action['name'] }}</span>
                                            <p class="text-xs text-{{ $action['color'] }}-600">{{ $action['description'] }}</p>
                                        </div>
                                    </a>
                                @else
                                    <div class="flex items-center p-4 bg-gray-50 rounded-lg opacity-50">
                                        <svg class="w-6 h-6 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-gray-500">{{ $action['name'] }}</span>
                                            <p class="text-xs text-gray-400">Sin permisos</p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad Reciente y Ayuda -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Mi Actividad Reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Mi Actividad Reciente</h3>
                            <a href="{{ route('operator.shipments.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todo →
                            </a>
                        </div>
                        <div class="space-y-3">
                            @forelse($recentActivity as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-2 h-2 bg-{{ $activity['color'] }}-500 rounded-full"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $activity['message'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $activity['date']->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.713-3.714M14 40v-4c0-1.313.253-2.566.713-3.714m0 0A9.971 9.971 0 0124 24c4.21 0 7.813 2.602 9.288 6.286"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">No hay actividad reciente</p>
                                    <p class="text-xs text-gray-400">Comienza creando tu primera carga</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Ayuda y Recursos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Ayuda y Recursos</h3>
                            <a href="{{ route('operator.help.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver más →
                            </a>
                        </div>
                        <div class="space-y-3">
                            <a href="{{ route('operator.help.getting-started') }}"
                               class="block p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-blue-800">Guía de inicio</p>
                                        <p class="text-xs text-blue-600">Aprende lo básico</p>
                                    </div>
                                </div>
                            </a>

                            <a href="{{ route('operator.help.shipments') }}"
                               class="block p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-green-800">Gestión de cargas</p>
                                        <p class="text-xs text-green-600">Crear y gestionar cargas</p>
                                    </div>
                                </div>
                            </a>

                            <a href="{{ route('operator.help.tickets') }}"
                               class="block p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-orange-800">Soporte técnico</p>
                                        <p class="text-xs text-orange-600">Crear ticket de soporte</p>
                                    </div>
                                </div>
                            </a>

                            <a href="{{ route('operator.help.faq') }}"
                               class="block p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-purple-800">Preguntas frecuentes</p>
                                        <p class="text-xs text-purple-600">Respuestas rápidas</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
