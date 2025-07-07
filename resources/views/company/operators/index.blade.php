<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Operadores') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">{{ $company->business_name }}</p>
            </div>
            <a href="{{ route('company.operators.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Nuevo Operador
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Módulo en Desarrollo -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">Módulo de Operadores en Desarrollo</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                            El módulo completo de gestión de operadores está siendo desarrollado.
                            Permitirá administrar el equipo de trabajo y sus permisos de forma granular.
                        </p>

                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-blue-800">Gestión de Personal</h4>
                                <ul class="mt-2 text-xs text-blue-600 space-y-1">
                                    <li>• Crear y editar operadores</li>
                                    <li>• Asignar roles y permisos</li>
                                    <li>• Control de acceso</li>
                                    <li>• Historial de actividad</li>
                                </ul>
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-green-800">Permisos Granulares</h4>
                                <ul class="mt-2 text-xs text-green-600 space-y-1">
                                    <li>• Importar datos</li>
                                    <li>• Exportar reportes</li>
                                    <li>• Transferir cargas</li>
                                    <li>• Permisos especiales</li>
                                </ul>
                            </div>

                            <div class="bg-purple-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-purple-800">Monitoreo</h4>
                                <ul class="mt-2 text-xs text-purple-600 space-y-1">
                                    <li>• Seguimiento de accesos</li>
                                    <li>• Estadísticas de uso</li>
                                    <li>• Reportes de actividad</li>
                                    <li>• Alertas automáticas</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Lista temporal de operadores actuales -->
                        @if($company->operators && $company->operators->count() > 0)
                            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                                <h4 class="text-sm font-medium text-gray-800 mb-4">Operadores Actuales</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($company->operators as $operator)
                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <div class="flex items-center space-x-3">
                                                <div class="h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-sm font-medium text-white">
                                                        {{ substr($operator->first_name, 0, 1) }}{{ substr($operator->last_name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        {{ $operator->first_name }} {{ $operator->last_name }}
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        {{ $operator->position ?? 'Operador' }}
                                                    </p>
                                                    <div class="flex items-center space-x-2 mt-1">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                            {{ $operator->active ? 'Activo' : 'Inactivo' }}
                                                        </span>
                                                        @if($operator->can_import)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                Import
                                                            </span>
                                                        @endif
                                                        @if($operator->can_export)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                                Export
                                                            </span>
                                                        @endif
                                                        @if($operator->can_transfer)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                                Transfer
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">
                                        Funcionalidad Actual
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>Los operadores existentes fueron creados durante la configuración inicial del sistema. El módulo completo de gestión estará disponible próximamente.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-center space-x-4">
                            <a href="{{ route('company.dashboard') }}"
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Volver al Dashboard
                            </a>
                            <a href="{{ route('company.shipments.index') }}"
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Ver Cargas
                            </a>
                            <a href="{{ route('company.reports.index') }}"
                               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Ver Reportes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
