<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard - Administrador de Empresa') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header con información de la empresa -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold">{{ $company->commercial_name ?? $company->business_name }}</h1>
                            <p class="text-blue-100 mt-1">CUIT: {{ $company->tax_id }} | {{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</p>
                            <p class="text-blue-100 text-sm">{{ $company->city }}, {{ $company->address }}</p>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center space-x-4">
                                <!-- Estado de certificado -->
                                @if($company->certificate_path)
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2 {{ $certificateStatus['is_valid'] ? 'text-green-300' : 'text-red-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm">
                                            {{ $certificateStatus['is_valid'] ? 'Certificado Válido' : 'Certificado Vencido' }}
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-center text-yellow-300">
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm">Sin Certificado</span>
                                    </div>
                                @endif

                                <!-- Estado de webservices -->
                                <div class="flex items-center">
                                    <div class="w-3 h-3 mr-2 rounded-full {{ $company->ws_active ? 'bg-green-400' : 'bg-gray-400' }}"></div>
                                    <span class="text-sm">
                                        WebServices {{ $company->ws_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Cargas -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Cargas</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_shipments'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-yellow-600">{{ $stats['pending_shipments'] ?? 0 }}</span> pendientes
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Viajes -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Viajes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_trips'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-blue-600">{{ $stats['active_trips'] ?? 0 }}</span> en curso
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operadores -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Operadores</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_operators'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">{{ $stats['active_operators'] ?? 0 }}</span> activos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facturación -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Facturación Mes</dt>
                                    <dd class="text-lg font-medium text-gray-900">${{ number_format($stats['monthly_revenue'] ?? 0, 0, ',', '.') }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">+{{ $stats['revenue_growth'] ?? 0 }}%</span> vs mes anterior
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad Reciente y Acciones Rápidas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Actividad Reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Actividad Reciente</h3>
                            <span class="text-xs text-gray-500">Últimas 24 horas</span>
                        </div>
                        <div class="space-y-4">
                            @forelse($recentActivity ?? [] as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900">{{ $activity['description'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $activity['time'] }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin actividad reciente</h3>
                                    <p class="mt-1 text-sm text-gray-500">No hay eventos registrados en las últimas 24 horas</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="{{ route('company.shipments.create') }}"
                               class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-800">Nueva Carga</span>
                            </a>

                            <a href="{{ route('company.trips.create') }}"
                               class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span class="text-sm font-medium text-green-800">Nuevo Viaje</span>
                            </a>

                            <a href="{{ route('company.operators.create') }}"
                               class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                                <span class="text-sm font-medium text-purple-800">Nuevo Operador</span>
                            </a>

                            <a href="{{ route('company.reports.index') }}"
                               class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                                <svg class="w-8 h-8 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm font-medium text-yellow-800">Reportes</span>
                            </a>
                        </div>

                        <!-- Enlaces adicionales -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex justify-between space-x-4">
                                <a href="{{ route('company.certificates.index') }}"
                                   class="text-sm text-blue-600 hover:text-blue-500">
                                    Gestionar Certificados →
                                </a>
                                <a href="{{ route('company.webservices.config') }}"
                                   class="text-sm text-blue-600 hover:text-blue-500">
                                    Configurar WebServices →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumen del Día -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Resumen del día {{ now()->format('d/m/Y') }}
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $todaysSummary['shipments_created'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">Cargas creadas</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $todaysSummary['trips_started'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">Viajes iniciados</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ $todaysSummary['shipments_delivered'] ?? 0 }}</div>
                            <div class="text-sm text-gray-600">Cargas entregadas</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">${{ number_format($todaysSummary['revenue_today'] ?? 0, 0, ',', '.') }}</div>
                            <div class="text-sm text-gray-600">Facturación hoy</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
