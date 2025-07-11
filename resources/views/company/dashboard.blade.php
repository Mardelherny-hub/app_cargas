<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }} - {{ $isCompanyAdmin ? 'Administrador' : 'Usuario' }} de Empresa
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Alertas importantes -->
            @if(count($companyAlerts) > 0)
                <div class="space-y-3">
                    @foreach($companyAlerts as $alert)
                        <div class="bg-{{ $alert['type'] === 'error' ? 'red' : ($alert['type'] === 'warning' ? 'yellow' : 'blue') }}-50 border border-{{ $alert['type'] === 'error' ? 'red' : ($alert['type'] === 'warning' ? 'yellow' : 'blue') }}-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    @if($alert['type'] === 'error')
                                        <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    @elseif($alert['type'] === 'warning')
                                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm text-{{ $alert['type'] === 'error' ? 'red' : ($alert['type'] === 'warning' ? 'yellow' : 'blue') }}-800">
                                        {{ $alert['message'] }}
                                    </p>
                                    @if($alert['action'])
                                        <div class="mt-2">
                                            <a href="{{ $alert['action'] }}" class="text-sm text-{{ $alert['type'] === 'error' ? 'red' : ($alert['type'] === 'warning' ? 'yellow' : 'blue') }}-600 hover:text-{{ $alert['type'] === 'error' ? 'red' : ($alert['type'] === 'warning' ? 'yellow' : 'blue') }}-500 underline">
                                                Resolver ahora →
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Header con información de la empresa -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold">{{ $company->commercial_name ?? $company->business_name }}</h1>
                            <p class="text-blue-100 mt-1">CUIT: {{ $company->tax_id }} | {{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}</p>
                            <p class="text-blue-100 text-sm">{{ $company->city }}, {{ $company->address }}</p>

                            <!-- Roles de empresa -->
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($companyRolesInfo['roles'] as $role)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $role }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center space-x-4">
                                <!-- Estado de certificado -->
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 {{ $certificateStatus['status'] === 'valid' ? 'text-green-300' : ($certificateStatus['status'] === 'expiring' ? 'text-yellow-300' : 'text-red-300') }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm">
                                        @if($certificateStatus['status'] === 'valid')
                                            Certificado Válido
                                        @elseif($certificateStatus['status'] === 'expiring')
                                            Certificado por Vencer
                                        @elseif($certificateStatus['status'] === 'expired')
                                            Certificado Vencido
                                        @else
                                            Sin Certificado
                                        @endif
                                    </span>
                                </div>

                                <!-- Estado de webservices -->
                                <div class="flex items-center">
                                    <div class="w-3 h-3 mr-2 rounded-full {{ $webserviceStatus['active'] ? 'bg-green-400' : 'bg-gray-400' }}"></div>
                                    <span class="text-sm">
                                        WebServices {{ $webserviceStatus['active'] ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Operadores -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
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

                <!-- Importación -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Con Importación</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['operators_with_import'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                operadores habilitados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exportación -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Con Exportación</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['operators_with_export'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                operadores habilitados
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transbordos -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Con Transbordos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['operators_with_transfer'] }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm text-gray-600">
                                operadores habilitados
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido específico según rol -->
            @if($isCompanyAdmin)
                <!-- Panel de Administrador -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Estadísticas de operadores -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Gestión de Operadores</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Total de operadores</span>
                                    <span class="font-medium">{{ $adminDashboard['operatorStats']['total'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Activos</span>
                                    <span class="font-medium text-green-600">{{ $adminDashboard['operatorStats']['active'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Con permisos de importación</span>
                                    <span class="font-medium">{{ $adminDashboard['operatorStats']['with_import_permission'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Con permisos de exportación</span>
                                    <span class="font-medium">{{ $adminDashboard['operatorStats']['with_export_permission'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Con permisos de transbordos</span>
                                    <span class="font-medium">{{ $adminDashboard['operatorStats']['with_transfer_permission'] }}</span>
                                </div>
                            </div>

                            @if($permissions['canManageOperators'])
                                <div class="mt-4 pt-4 border-t">
                                    <a href="{{ route('company.operators.index') }}" class="text-sm text-blue-600 hover:text-blue-500">
                                        Gestionar operadores →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Tareas pendientes -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tareas Pendientes</h3>
                            @if(count($adminDashboard['pendingTasks']) > 0)
                                <div class="space-y-3">
                                    @foreach($adminDashboard['pendingTasks'] as $task)
                                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="flex-shrink-0">
                                                <div class="w-2 h-2 bg-{{ $task['priority'] === 'high' ? 'red' : ($task['priority'] === 'medium' ? 'yellow' : 'blue') }}-500 rounded-full mt-2"></div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900">{{ $task['title'] }}</p>
                                                <p class="text-xs text-gray-600 mt-1">{{ $task['description'] }}</p>
                                                @if($task['action'])
                                                    <div class="mt-2">
                                                        <a href="{{ $task['action'] }}" class="text-xs text-blue-600 hover:text-blue-500">
                                                            Resolver →
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">¡Todo al día!</h3>
                                    <p class="mt-1 text-sm text-gray-500">No hay tareas pendientes por resolver</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Acciones de administrador -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones de Administrador</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @if($permissions['canManageOperators'])
                                <a href="{{ route('company.operators.index') }}" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                    <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-purple-800">Operadores</span>
                                </a>
                            @endif

                            @if($permissions['canManageCertificates'])
                                <a href="{{ route('company.certificates.index') }}" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                    <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    <span class="text-sm font-medium text-blue-800">Certificados</span>
                                </a>
                            @endif

                            @if($permissions['canViewReports'])
                                <a href="{{ route('company.reports.index') }}" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                    <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-sm font-medium text-green-800">Reportes</span>
                                </a>
                            @endif

                            @if($permissions['canManageSettings'])
                                <a href="{{ route('company.settings.index') }}" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-800">Configuración</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($isUser)
                <!-- Panel de Usuario -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Mis estadísticas -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mis Estadísticas</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Mis cargas</span>
                                    <span class="font-medium">{{ $userDashboard['personalStats']['my_shipments'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Pendientes</span>
                                    <span class="font-medium text-yellow-600">{{ $userDashboard['personalStats']['pending_shipments'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Completadas</span>
                                    <span class="font-medium text-green-600">{{ $userDashboard['personalStats']['completed_shipments'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Mis viajes</span>
                                    <span class="font-medium">{{ $userDashboard['personalStats']['my_trips'] }}</span>
                                </div>
                            </div>

                            <!-- Resumen de permisos -->
                            <div class="mt-6 pt-4 border-t">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Mis Permisos</h4>
                                <div class="flex flex-wrap gap-2">
                                    @if($userDashboard['personalStats']['permissions_summary']['can_import'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Importación
                                        </span>
                                    @endif
                                    @if($userDashboard['personalStats']['permissions_summary']['can_export'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Exportación
                                        </span>
                                    @endif
                                    @if($userDashboard['personalStats']['permissions_summary']['can_transfer'])
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Transbordos
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones disponibles -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Disponibles</h3>
                            @if(count($userDashboard['availableActions']) > 0)
                                <div class="grid grid-cols-1 gap-3">
                                    @foreach($userDashboard['availableActions'] as $action)
                                        <a href="{{ $action['route'] }}" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                            <div class="flex-shrink-0">
                                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($action['icon'] === 'download')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                                    @elseif($action['icon'] === 'upload')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    @elseif($action['icon'] === 'truck')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                    @elseif($action['icon'] === 'box')
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                    @endif
                                                </svg>
                                                <span class="text-sm font-medium text-gray-900">{{ $action['title'] }}</span>
                                            </div>
                                            <div class="ml-4 flex-shrink-0">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin acciones disponibles</h3>
                                    <p class="mt-1 text-sm text-gray-500">Contacte al administrador para configurar permisos</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Alertas personales -->
                @if(count($userDashboard['personalAlerts']) > 0)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Alertas Personales</h3>
                            <div class="space-y-3">
                                @foreach($userDashboard['personalAlerts'] as $alert)
                                    <div class="flex items-start p-3 bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-50 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-800">{{ $alert['message'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Trabajo reciente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actividad Reciente</h3>
                        <div class="text-center py-6">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin actividad reciente</h3>
                            <p class="mt-1 text-sm text-gray-500">Comience a trabajar para ver su actividad aquí</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Acciones rápidas comunes -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <!-- Viajes -->
                        @if($permissions['canAccessTrips'])
                            <a href="{{ route('company.trips.index') }}" class="flex flex-col items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                <svg class="w-8 h-8 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-medium text-indigo-800">Viajes</span>
                            </a>
                        @endif

                        <!-- Cargas -->
                        @if($permissions['canAccessShipments'])
                            <a href="{{ route('company.shipments.index') }}" class="flex flex-col items-center p-4 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
                                <svg class="w-8 h-8 text-emerald-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <span class="text-sm font-medium text-emerald-800">Cargas</span>
                            </a>
                        @endif

                        <!-- Importación -->
                        @if($permissions['canAccessImport'])
                            <a href="{{ route('company.import.index') }}" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                                <span class="text-sm font-medium text-blue-800">Importación</span>
                            </a>
                        @endif

                        <!-- Exportación -->
                        @if($permissions['canAccessExport'])
                            <a href="{{ route('company.export.index') }}" class="flex flex-col items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm font-medium text-orange-800">Exportación</span>
                            </a>
                        @endif

                        <!-- Documentos -->
                        <a href="{{-- route('company.documents.index') --}}#" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-purple-800">Documentos</span>
                        </a>

                        <!-- Perfil -->
                        <a href="{{-- route('profile.edit') --}}#" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-8 h-8 text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-800">Mi Perfil</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Información del sistema -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado del Sistema</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Estado de webservices -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                <div class="w-4 h-4 rounded-full {{ $webserviceStatus['active'] ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">WebServices</p>
                            <p class="text-xs text-gray-500">
                                {{ $webserviceStatus['active'] ? 'Operativo' : 'Inactivo' }}
                            </p>
                        </div>

                        <!-- Estado de certificado -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                <div class="w-4 h-4 rounded-full {{ $certificateStatus['status'] === 'valid' ? 'bg-green-500' : ($certificateStatus['status'] === 'expiring' ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Certificado</p>
                            <p class="text-xs text-gray-500">
                                @if($certificateStatus['status'] === 'valid')
                                    Válido
                                @elseif($certificateStatus['status'] === 'expiring')
                                    Por vencer
                                @elseif($certificateStatus['status'] === 'expired')
                                    Expirado
                                @else
                                    No configurado
                                @endif
                            </p>
                        </div>

                        <!-- Estado general -->
                        <div class="text-center">
                            <div class="flex justify-center mb-2">
                                <div class="w-4 h-4 rounded-full {{ $company->active ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            </div>
                            <p class="text-sm font-medium text-gray-900">Empresa</p>
                            <p class="text-xs text-gray-500">
                                {{ $company->active ? 'Activa' : 'Inactiva' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
