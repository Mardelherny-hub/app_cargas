<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Centro de Reportes
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Genere y descargue reportes de la actividad de su empresa
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- ESTADÍSTICAS RÁPIDAS --}}
            @php
                $companyId = auth()->user()->userable->company_id ?? auth()->user()->userable_id;
                $stats = [
                    'voyages' => \App\Models\Voyage::where('company_id', $companyId)->count(),
                    'bills' => \App\Models\BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->count(),
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Viajes</p>
                            <p class="text-3xl font-bold mt-1">{{ $stats['voyages'] }}</p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Conocimientos</p>
                            <p class="text-3xl font-bold mt-1">{{ $stats['bills'] }}</p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Reportes Disponibles</p>
                            <p class="text-3xl font-bold mt-1">2</p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Formatos</p>
                            <p class="text-3xl font-bold mt-1">PDF + Excel</p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- REPORTES DISPONIBLES --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📄 Reportes de Cargas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    {{-- MANIFIESTO DE CARGA --}}
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-200">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4">
                            <div class="flex items-center justify-between text-white">
                                <h4 class="text-lg font-bold">Manifiesto de Carga</h4>
                                <div class="bg-white bg-opacity-20 rounded-full p-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">
                                Documento completo con todos los conocimientos de un viaje específico.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1 mb-4">
                                <li>✓ Formato landscape (apaisado)</li>
                                <li>✓ Datos del viaje completos</li>
                                <li>✓ Todos los BLs con totales</li>
                                <li>✓ PDF y Excel disponibles</li>
                            </ul>
                            <a href="{{ route('company.reports.manifests') }}" 
                               class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-4 rounded-lg font-medium transition-colors">
                                Generar Manifiesto →
                            </a>
                        </div>
                    </div>

                    {{-- LISTADO DE CONOCIMIENTOS --}}
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-200">
                        <div class="bg-gradient-to-r from-green-500 to-green-600 p-4">
                            <div class="flex items-center justify-between text-white">
                                <h4 class="text-lg font-bold">Conocimientos</h4>
                                <div class="bg-white bg-opacity-20 rounded-full p-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">
                                Listado detallado filtrable por fechas, clientes, puertos y estado.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1 mb-4">
                                <li>✓ Formato portrait (vertical)</li>
                                <li>✓ Filtros avanzados</li>
                                <li>✓ Totales y estadísticas</li>
                                <li>✓ PDF y Excel disponibles</li>
                            </ul>
                            <a href="{{ route('company.reports.bills-of-lading') }}" 
                               class="block w-full bg-green-600 hover:bg-green-700 text-white text-center py-2 px-4 rounded-lg font-medium transition-colors">
                                Generar Listado →
                            </a>
                        </div>
                    </div>

                    {{-- CARTAS DE AVISO - ACTUALIZADO: AHORA FUNCIONAL --}}
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-200">
                        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-4">
                            <div class="flex items-center justify-between text-white">
                                <h4 class="text-lg font-bold">Cartas de Aviso</h4>
                                <div class="bg-white bg-opacity-20 rounded-full p-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">
                                Notificaciones de llegada por consignatario con membrete profesional.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1 mb-4">
                                <li>✅ Una carta por consignatario</li>
                                <li>✅ Membrete personalizado</li>
                                <li>✅ Detalle de mercadería</li>
                                <li>✅ Exportar PDF individual o múltiple</li>
                            </ul>
                            <a href="{{ route('company.reports.arrival-notices') }}"
                            class="block w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white text-center py-2 px-4 rounded-lg font-medium transition-all duration-300 shadow-sm hover:shadow-md">
                                Generar Cartas
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- REPORTES ADUANEROS --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🛂 Reportes Aduaneros</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- MIC/DTA --}}
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-200">
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-4">
                            <div class="flex items-center justify-between text-white">
                                <h4 class="text-lg font-bold">Reportes MIC/DTA</h4>
                                <div class="bg-white bg-opacity-20 rounded-full p-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">
                                Formato oficial AFIP para manifiestos internacionales y declaraciones de tránsito.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1 mb-4">
                                <li>✅ Formato AFIP oficial</li>
                                <li>✅ Códigos aduaneros incluidos</li>
                                <li>✅ Datos de transbordo si aplican</li>
                                <li>✅ Exportar PDF landscape</li>
                            </ul>
                            <a href="{{ route('company.reports.micdta') }}"
                            class="block w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white text-center py-2 px-4 rounded-lg font-medium transition-all duration-300 shadow-sm hover:shadow-md">
                                Generar MIC/DTA
                            </a>
                        </div>
                    </div>

                    {{-- LISTADO MANIFIESTO ADUANA --}}
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden border border-gray-200">
                        <div class="bg-gradient-to-r from-red-500 to-red-600 p-4">
                            <div class="flex items-center justify-between text-white">
                                <h4 class="text-lg font-bold">Manifiesto Aduanero</h4>
                                <div class="bg-white bg-opacity-20 rounded-full p-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 text-sm mb-4">
                                Reporte oficial para presentación física ante autoridades aduaneras con códigos y datos completos.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1 mb-4">
                                <li>✅ Formato oficial para aduana</li>
                                <li>✅ Códigos aduaneros incluidos</li>
                                <li>✅ Datos de transbordo si aplican</li>
                                <li>✅ Espacios para firmas oficiales</li>
                                <li>✅ Exportar PDF landscape</li>
                            </ul>
                            <a href="{{ route('company.reports.customs-manifest') }}"
                            class="block w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white text-center py-2 px-4 rounded-lg font-medium transition-all duration-300 shadow-sm hover:shadow-md">
                                Generar Manifiesto
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- INFORMACIÓN ADICIONAL --}}
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            💡 Consejos para generar reportes
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Los reportes en <strong>PDF</strong> son ideales para impresión y envío oficial</li>
                                <li>Los reportes en <strong>Excel</strong> permiten análisis y manipulación de datos</li>
                                <li>Use filtros para generar reportes específicos según sus necesidades</li>
                                <li>Todos los reportes incluyen auditoría (fecha, hora y usuario)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>