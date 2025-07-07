<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Panel de Operaciones Internas') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Monitoreo y gestión multi-empresa</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">Operador Interno</span>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-{{ $stats['system_health'] === 'good' ? 'green' : 'red' }}-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Sistema</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Alertas Críticas -->
            @if(count($criticalAlerts) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <svg class="inline w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Alertas Críticas del Sistema
                            </h3>
                            <div class="space-y-3">
                                @foreach($criticalAlerts as $alert)
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
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Total Empresas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
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

                <!-- Empresas con Certificados -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Con Certificados</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['companies_with_certificates'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-blue-600">{{ $stats['companies_with_active_ws'] }}</span> con WS activos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operadores Externos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Operadores Externos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_external_operators'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">{{ $stats['active_external_operators'] }}</span> activos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Actividad (7 días)</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['recent_activity'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-blue-600">Sistema {{ $stats['system_health'] === 'good' ? 'saludable' : 'con problemas' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empresas por País y WebServices -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Distribución por País -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Empresas por País</h3>
                            <a href="{{ route('internal.companies.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todas →
                            </a>
                        </div>
                        <div class="space-y-4">
                            @foreach($companiesByCountry as $country => $data)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 bg-{{ $country === 'AR' ? 'blue' : 'green' }}-500 rounded-full mr-3"></div>
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ $country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-lg font-semibold text-gray-900">{{ $data['total'] }}</span>
                                        <p class="text-xs text-gray-600">empresas</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Estado de WebServices -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Estado de WebServices</h3>
                            <a href="{{ route('internal.webservices.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver detalles →
                            </a>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Empresas con WS activos:</span>
                                <span class="text-lg font-semibold text-green-600">{{ $webserviceIssues['total_companies'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Con problemas:</span>
                                <span class="text-lg font-semibold text-red-600">{{ $webserviceIssues['with_issues'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Errores (24h):</span>
                                <span class="text-lg font-semibold text-yellow-600">{{ $webserviceIssues['last_24h_errors'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Envíos pendientes:</span>
                                <span class="text-lg font-semibold text-blue-600">{{ $webserviceIssues['pending_sends'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empresas que Requieren Atención -->
            @if(count($companiesNeedingAttention) > 0)
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <svg class="inline w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    Empresas que Requieren Atención
                                </h3>
                                <span class="text-sm text-gray-600">{{ count($companiesNeedingAttention) }} empresas</span>
                            </div>
                            <div class="space-y-3">
                                @foreach($companiesNeedingAttention as $item)
                                    <div class="flex items-center justify-between p-4 rounded-lg
                                        @if($item['priority'] === 'high') bg-red-50 border border-red-200
                                        @elseif($item['priority'] === 'medium') bg-yellow-50 border border-yellow-200
                                        @else bg-blue-50 border border-blue-200
                                        @endif">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 mr-4">
                                                <div class="w-3 h-3 bg-{{ $item['priority'] === 'high' ? 'red' : ($item['priority'] === 'medium' ? 'yellow' : 'blue') }}-500 rounded-full"></div>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ $item['company']->business_name }}</p>
                                                <p class="text-sm text-gray-600">{{ $item['issue'] }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-4">
                                            <div class="text-right">
                                                @if(isset($item['days_overdue']))
                                                    <p class="text-sm font-medium text-red-600">{{ $item['days_overdue'] }} días vencido</p>
                                                @elseif(isset($item['days_to_expiry']))
                                                    <p class="text-sm font-medium text-yellow-600">{{ $item['days_to_expiry'] }} días para vencer</p>
                                                @elseif(isset($item['days_since_creation']))
                                                    <p class="text-sm font-medium text-blue-600">{{ $item['days_since_creation'] }} días sin certificado</p>
                                                @elseif(isset($item['inactive_count']))
                                                    <p class="text-sm font-medium text-gray-600">{{ $item['inactive_count'] }} inactivos</p>
                                                @endif
                                            </div>
                                            <a href="{{ route('internal.companies.show', $item['company']) }}"
                                               class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                                Ver →
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Empresas Más Activas y Actividad Reciente -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Empresas Más Activas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Empresas Más Activas</h3>
                            <a href="{{ route('internal.monitoring.companies') }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todas →
                            </a>
                        </div>
                        <div class="space-y-3">
                            @forelse($activeCompanies as $company)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 mr-3">
                                            <div class="w-8 h-8 bg-{{ $company->country === 'AR' ? 'blue' : 'green' }}-500 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-medium text-white">
                                                    {{ $company->country }}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $company->business_name }}</p>
                                            <p class="text-xs text-gray-600">{{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-semibold text-gray-900">{{ $company->operators_count }}</span>
                                        <p class="text-xs text-gray-600">accesos activos</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay actividad reciente</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente del Sistema -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Actividad Reciente</h3>
                            <a href="{{ route('internal.monitoring.index') }}"
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
                                    @if(isset($activity['link']))
                                        <div class="flex-shrink-0">
                                            <a href="{{ $activity['link'] }}"
                                               class="text-xs text-blue-600 hover:text-blue-500">
                                                Ver →
                                            </a>
                                        </div>
                                    @endif
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
                        <a href="{{ route('internal.monitoring.companies') }}"
                           class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="text-sm font-medium text-blue-800">Monitorear Empresas</span>
                        </a>

                        <a href="{{ route('internal.webservices.index') }}"
                           class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                            </svg>
                            <span class="text-sm font-medium text-green-800">Gestionar WebServices</span>
                        </a>

                        <a href="{{ route('internal.transfers.index') }}"
                           class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            <span class="text-sm font-medium text-purple-800">Gestionar Transferencias</span>
                        </a>

                        <a href="{{ route('internal.reports.index') }}"
                           class="flex items-center p-4 bg-teal-50 rounded-lg hover:bg-teal-100 transition-colors">
                            <svg class="w-6 h-6 text-teal-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-teal-800">Reportes Globales</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Herramientas Avanzadas -->
            <div class="mt-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Herramientas Avanzadas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="{{ route('internal.support.index') }}"
                               class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                                <svg class="w-6 h-6 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <span class="text-sm font-medium text-orange-800">Soporte Técnico</span>
                            </a>

                            <a href="{{ route('internal.tools.index') }}"
                               class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-6 h-6 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-800">Herramientas de Sistema</span>
                            </a>

                            <a href="{{ route('internal.monitoring.system-health') }}"
                               class="flex items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                <span class="text-sm font-medium text-indigo-800">Salud del Sistema</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
