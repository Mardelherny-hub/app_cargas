<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Reportes MIC/DTA - AFIP') }}
            </h2>
            <a href="{{ route('company.reports.index') }}" 
               class="text-sm text-blue-600 hover:text-blue-800">
                ← Volver a Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Descripción --}}
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-purple-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-purple-800 mb-1">Formato oficial AFIP</h3>
                        <p class="text-sm text-purple-700">
                            Lista de viajes con envíos MIC/DTA registrados ante AFIP. 
                            Genera el PDF oficial con todos los datos declarados.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Listado de Viajes --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-purple-500 to-purple-600">
                    <h3 class="text-lg font-semibold text-white">
                        Viajes con MIC/DTA Registrado
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    @if($voyages->count() > 0)
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Viaje
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Embarcación
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ruta
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Salida
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-center">
                                        Envíos
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-center">
                                        Conocimientos
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($voyages as $voyage)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $voyage->voyage_number }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                ID: {{ $voyage->id }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $voyage->leadVessel->name ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                {{ $voyage->originPort->name ?? 'N/A' }}
                                                <svg class="w-4 h-4 inline text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                </svg>
                                                {{ $voyage->destinationPort->name ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $voyage->departure_date ? $voyage->departure_date->format('d/m/Y') : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $voyage->shipments->count() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $voyage->billsOfLading->count() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <form method="POST" action="{{ route('company.reports.export', 'micdta') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="format" value="pdf">
                                                    <input type="hidden" name="filters[voyage_id]" value="{{ $voyage->id }}">
                                                    <input type="hidden" name="filters[template]" value="standard">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-md transition-all duration-200">
                                                        Reporte actual
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('company.reports.export', 'micdta') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="format" value="pdf">
                                                    <input type="hidden" name="filters[voyage_id]" value="{{ $voyage->id }}">
                                                    <input type="hidden" name="filters[template]" value="client">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white text-sm font-medium rounded-md transition-all duration-200">
                                                        Formato MIC/DTA
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-12 px-6">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay viajes con MIC/DTA registrado y enlazado</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                El reporte actual requiere una transacción MIC/DTA exitosa enlazada al viaje.
                                Para generar el formato según muestra desde los conocimientos cargados, utilice la sección inferior.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('company.webservices.index') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md">
                                    Ir a Webservices
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Formato adicional basado en la muestra del cliente --}}
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="px-6 py-4 bg-gray-800">
                    <h3 class="text-lg font-semibold text-white">
                        Formato MIC/DTA según muestra - Viajes con conocimientos
                    </h3>
                    <p class="text-sm text-gray-200 mt-1">
                        Salida alternativa. El reporte MIC/DTA existente se conserva sin cambios.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    @if($printableVoyages->count() > 0)
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viaje</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Embarcación</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruta</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Conocimientos</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($printableVoyages as $voyage)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $voyage->voyage_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $voyage->leadVessel->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $voyage->originPort->name ?? 'N/A' }} → {{ $voyage->destinationPort->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            {{ $voyage->billsOfLading->count() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <form method="POST" action="{{ route('company.reports.export', 'micdta') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="format" value="pdf">
                                                <input type="hidden" name="filters[voyage_id]" value="{{ $voyage->id }}">
                                                <input type="hidden" name="filters[template]" value="client">
                                                <button type="submit"
                                                        class="inline-flex items-center px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md">
                                                    Formato MIC/DTA
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-6 text-sm text-gray-500">
                            No hay otros viajes con conocimientos disponibles para esta salida.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>