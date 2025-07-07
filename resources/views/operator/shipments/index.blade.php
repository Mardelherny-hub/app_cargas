<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Mis Cargas') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Gestión personal de cargas</p>
            </div>
            <a href="{{ route('operator.shipments.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Nueva Carga
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">Mis Cargas - En Desarrollo</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                            Tu espacio personal para gestionar cargas. Podrás crear, editar y hacer seguimiento de tus cargas asignadas de forma sencilla e intuitiva.
                        </p>

                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-xl mx-auto">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-blue-800">Gestión Simplificada</h4>
                                <ul class="mt-2 text-xs text-blue-600 space-y-1">
                                    <li>• Crear cargas rápidamente</li>
                                    <li>• Editar información básica</li>
                                    <li>• Cambiar estados</li>
                                    <li>• Ver historial personal</li>
                                </ul>
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-green-800">Herramientas Útiles</h4>
                                <ul class="mt-2 text-xs text-green-600 space-y-1">
                                    <li>• Búsqueda rápida</li>
                                    <li>• Filtros personalizados</li>
                                    <li>• Duplicar cargas</li>
                                    <li>• Exportar a PDF</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">
                                        Interfaz Diseñada para Ti
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>La vista de operador está optimizada para el trabajo diario. Solo verás las cargas que te corresponden y las funciones que necesitas.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-center space-x-4">
                            <a href="{{ route('operator.dashboard') }}"
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Volver al Dashboard
                            </a>
                            <a href="{{ route('operator.trips.index') }}"
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Ver Mis Viajes
                            </a>
                            <a href="{{ route('operator.help.index') }}"
                               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Ver Ayuda
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
