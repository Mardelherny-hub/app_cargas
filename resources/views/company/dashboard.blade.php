<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard - {{ $company->legal_name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <!-- Header con información de la empresa -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <div class="md:flex md:items-center md:justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                            <div class="mt-2 flex items-center text-sm text-gray-500">
                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-6a1 1 0 00-1-1H9a1 1 0 00-1 1v6a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 8a1 1 0 011-1h4a1 1 0 011 1v4H7v-4z" clip-rule="evenodd"/>
                                </svg>
                                {{ $company->legal_name }}
                            </div>
                            <div class="mt-2 flex items-center text-sm text-gray-500">
                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                @if($isCompanyAdmin)
                                    Administrador
                                @else
                                    Operador
                                @endif
                            </div>
                            @if(!empty($companyRoles))
                            <div class="mt-2 flex items-center text-sm text-gray-500">
                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ implode(', ', $companyRoles) }}
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 flex md:mt-0 md:ml-4">
                        @if($isCompanyAdmin)
                        <a href="{{ route('company.operators.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Nuevo Operador
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <!-- Viajes Activos -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Viajes Activos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['active_voyages'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="{{ route('company.voyages.index') }}" class="font-medium text-blue-700 hover:text-blue-900">
                                Ver todos los viajes
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Cargas Pendientes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Cargas Pendientes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['pending_shipments'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="{{ route('company.shipments.index') }}" class="font-medium text-green-700 hover:text-green-900">
                                Gestionar cargas
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Manifiestos del Mes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Manifiestos este Mes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['monthly_manifests'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="{{ route('company.manifests.index') }}" class="font-medium text-purple-700 hover:text-purple-900">
                                Ver manifiestos
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Webservices Enviados -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">WS Enviados (7d)</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['weekly_webservices'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="{{ route('company.webservices.history') }}" class="font-medium text-orange-700 hover:text-orange-900">
                                Ver historial
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Estados del Sistema
                                </dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    @php
                                        // Obtener conteo rápido de elementos con estados pendientes/en proceso
                                        $pendingCount = 0;
                                        if($company) {
                                            $pendingVoyages = \App\Models\Voyage::where('company_id', $company->id)
                                                ->whereIn('status', ['planning', 'confirmed'])->count();
                                            $pendingShipments = \App\Models\Shipment::whereHas('voyage', function($q) use ($company) {
                                                    $q->where('company_id', $company->id);
                                                })
                                                ->whereIn('status', ['planning', 'loading'])->count();
                                            $pendingCount = $pendingVoyages + $pendingShipments;
                                        }
                                    @endphp
                                    {{ $pendingCount }} Pendientes
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="{{ route('company.dashboard-estados.index') }}" 
                        class="font-medium text-indigo-700 hover:text-indigo-900 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Gestionar Estados
                        </a>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        Vista consolidada • Cambios masivos • Filtros
                    </div>
                </div>
            </div>

            <!-- Panel principal según rol -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @if($isCompanyAdmin)
                    <!-- Panel de Administrador de Empresa -->
                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    <svg class="inline w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                    </svg>
                                    Gestión de Operadores
                                </h3>
                                
                                @if(isset($adminDashboard['operatorStats']))
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="bg-blue-50 rounded-lg p-4">
                                        <div class="text-sm font-medium text-blue-900">Total Operadores</div>
                                        <div class="text-2xl font-bold text-blue-600">{{ $adminDashboard['operatorStats']['total'] }}</div>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-4">
                                        <div class="text-sm font-medium text-green-900">Activos</div>
                                        <div class="text-2xl font-bold text-green-600">{{ $adminDashboard['operatorStats']['active'] }}</div>
                                    </div>
                                </div>
                                @endif

                                <div class="mt-4">
                                    <a href="{{ route('company.operators.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                        Ver todos los operadores →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                @else
                    <!-- Panel para Operadores -->
                    <div class="lg:col-span-2">
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    <svg class="inline w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a2 2 0 002 2h4a2 2 0 002-2V3a2 2 0 012 2v6.5A1.5 1.5 0 0016.5 13H15v-1a2 2 0 00-2-2H7a2 2 0 00-2 2v1H3.5A1.5 1.5 0 012 11.5V5z" clip-rule="evenodd"/>
                                    </svg>
                                    Mi Trabajo Reciente
                                </h3>
                                
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin actividad reciente</h3>
                                    <p class="mt-1 text-sm text-gray-500">Comience creando un viaje o carga.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Panel lateral -->
                <div class="space-y-6">
                    @if($isCompanyAdmin)
                        <!-- Estado del Sistema para Admin -->
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Estado Webservices
                                </h3>
                                @if(isset($webserviceStatus))
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Estado General</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $webserviceStatus['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $webserviceStatus['active'] ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <!-- Panel para Operadores -->
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Mis Estadísticas
                                </h3>
                                @if(isset($userDashboard['personalStats']))
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Viajes creados</span>
                                        <span class="font-medium text-blue-600">{{ $userDashboard['personalStats']['voyages_created'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Cargas procesadas</span>
                                        <span class="font-medium text-green-600">{{ $userDashboard['personalStats']['shipments_processed'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Manifiestos enviados</span>
                                        <span class="font-medium text-purple-600">{{ $userDashboard['personalStats']['manifests_sent'] ?? 0 }}</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>