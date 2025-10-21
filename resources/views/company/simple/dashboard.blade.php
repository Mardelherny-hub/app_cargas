{{-- 
  SISTEMA MODULAR WEBSERVICES - Dashboard Principal
  Ubicación: resources/views/company/simple/dashboard.blade.php
  
  Dashboard unificado con selector de webservices modulares.
  FASE 1: MIC/DTA Argentina activo
  FASE 2-5: Otros webservices preparados
  
  DATOS VERIFICADOS:
  - Variables del controlador: $voyages, $company, $webservice_types, $active_webservices
  - Campos Voyage: voyage_number, leadVessel->name, originPort->code, destinationPort->code
  - Relación webservice_stats agregada en controller
--}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Webservices Aduaneros
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Sistema modular para envío de manifiestos y documentación aduanera
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ $company->legal_name }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Mensajes Flash --}}
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Lista de Viajes con Estados de Webservices --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            <svg class="inline w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 16a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                            </svg>
                            Viajes Disponibles ({{ $voyages->total() }})
                        </h3>
                        
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('company.voyages.index') }}" 
                            class="text-sm text-indigo-600 hover:text-indigo-900">
                                Ver todos los voyages
                            </a>
                        </div>
                    </div>

                    @if($voyages->count() > 0)
                        <div class="space-y-6">
                            @foreach($voyages as $voyage)
                                @php
                                    // Obtener estados de webservices para este viaje
                                    $anticipadaStatus = $voyage->webservice_stats['anticipada'] ?? null;
                                    $micdtaStatus = $voyage->webservice_stats['micdta'] ?? null;
                                    $desconsolidadoStatus = $voyage->webservice_stats['desconsolidado'] ?? null;
                                    $transbordoStatus = $voyage->webservice_stats['transbordo'] ?? null;
                                    $manifiestoStatus = $voyage->webservice_stats['manifiesto'] ?? null;
                                    
                                    // Cada webservice es independiente - NO hay dependencias obligatorias
                                    $anticipadaEnviada = $anticipadaStatus && in_array($anticipadaStatus['status'], ['sent', 'approved']);
                                    $micdtaHabilitado = true; // MIC/DTA siempre habilitado (exportación)
                                    $desconsolidadoHabilitado = true; // Independiente
                                    $transbordoHabilitado = true; // Independiente
                                    // Definir colores de estados
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'sent' => 'bg-blue-100 text-blue-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'error' => 'bg-red-100 text-red-800',
                                    ];
                                    $statusTexts = [
                                        'pending' => 'Pendiente',
                                        'sent' => 'Enviado',
                                        'approved' => 'Aprobado',
                                        'error' => 'Error',
                                    ];
                                @endphp
                                
                                <div class="border-2 border-gray-200 rounded-lg p-5 hover:border-indigo-300 transition duration-150">
                                    {{-- Encabezado del Viaje --}}
                                    <div class="flex items-start justify-between mb-4 pb-3 border-b border-gray-200">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-3">
                                                <h4 class="text-lg font-bold text-gray-900 truncate">
                                                    {{ $voyage->voyage_number }}
                                                </h4>
                                                @if($voyage->leadVessel)
                                                    <span class="text-sm text-gray-600 truncate">
                                                        {{ $voyage->leadVessel->name }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <span class="font-medium">Ruta:</span>
                                                <span class="truncate inline-block max-w-xs">
                                                    {{ $voyage->originPort->code ?? 'N/D' }} 
                                                    <svg class="inline w-3 h-3 mx-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $voyage->destinationPort->code ?? 'N/D' }}
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div class="text-right text-xs text-gray-500 ml-4">
                                            <div>Cargas: {{ $voyage->shipments->count() }}</div>
                                            <div class="text-gray-400">Conocimientos: {{ $voyage->billsOfLading->count() }}</div>
                                        </div>
                                    </div>

                                    {{-- Sección de Webservices Argentina --}}
                                    <div class="mb-4">
                                        <div class="flex items-center mb-3">
                                            <span class="text-lg mr-2">🇦🇷</span>
                                            <h5 class="text-sm font-semibold text-gray-700">Argentina - AFIP</h5>
                                            <span class="ml-2 text-xs text-gray-500">(Secuencia obligatoria)</span>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                            {{-- ANTICIPADA - PASO 1 OBLIGATORIO --}}
                                            <div class="border rounded-lg p-3 bg-gradient-to-br from-indigo-50 to-white min-h-[120px] flex flex-col">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center mb-1">
                                                            <span class="text-xs font-bold text-red-600 mr-2">Importación</span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 block truncate">Anticipada</span>
                                                    </div>
                                                    @if($anticipadaStatus)
                                                        @php
                                                            $color = $statusColors[$anticipadaStatus['status']] ?? 'bg-gray-100 text-gray-800';
                                                            $text = $statusTexts[$anticipadaStatus['status']] ?? 'N/D';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }} whitespace-nowrap">
                                                            {{ $text }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 whitespace-nowrap">
                                                            No enviado
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    @if(!$anticipadaStatus || in_array($anticipadaStatus['status'] ?? '', ['pending', 'error']))
                                                        <a href="{{ route('company.simple.anticipada.show', $voyage) }}" 
                                                        class="block w-full text-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                                            Enviar Ahora
                                                        </a>
                                                    @else
                                                        <a href="{{ route('company.simple.anticipada.show', $voyage) }}" 
                                                        class="block w-full text-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded transition">
                                                            Ver Detalles
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- MIC/DTA - EXPORTACIÓN (Independiente) --}}
                                            <div class="border rounded-lg p-3 min-h-[120px] flex flex-col bg-gradient-to-br from-purple-50 to-white">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center mb-1">
                                                            @if(!$anticipadaEnviada)
                                                                <svg class="w-3 h-3 mr-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                            <span class="text-xs font-medium text-gray-600 mr-2">Exportación</span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 block truncate">MIC/DTA</span>
                                                    </div>
                                                    @if($micdtaStatus)
                                                        @php
                                                            $color = $statusColors[$micdtaStatus['status']] ?? 'bg-gray-100 text-gray-800';
                                                            $text = $statusTexts[$micdtaStatus['status']] ?? 'N/D';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }} whitespace-nowrap">
                                                            {{ $text }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 whitespace-nowrap">
                                                            No enviado
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    <div class="mt-auto">
                                                        @if(!$micdtaStatus || in_array($micdtaStatus['status'] ?? '', ['pending', 'error']))
                                                            <a href="{{ route('company.simple.micdta.show', $voyage) }}"
                                                            class="block w-full text-center px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition">
                                                                Enviar Ahora
                                                            </a>
                                                        @else
                                                            <a href="{{ route('company.simple.micdta.show', $voyage) }}"
                                                            class="block w-full text-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded transition">
                                                                Ver Detalles
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- DESCONSOLIDADO - PASO 3 --}}
                                            <div class="border rounded-lg p-3 min-h-[120px] flex flex-col {{ $anticipadaEnviada ? 'bg-white' : 'bg-gray-50 opacity-60' }}">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center mb-1">
                                                            @if(!$anticipadaEnviada)
                                                                <svg class="w-3 h-3 mr-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                            <span class="text-xs font-medium text-gray-600 mr-2"></span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 block truncate">Desconsolidado</span>
                                                    </div>

                                                    @if(!empty($desconsolidadoStatus))
                                                        @php
                                                            $color = $statusColors[$desconsolidadoStatus['status']] ?? 'bg-gray-100 text-gray-800';
                                                            $text  = $statusTexts[$desconsolidadoStatus['status']] ?? 'N/D';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }} whitespace-nowrap">
                                                            {{ $text }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 whitespace-nowrap">
                                                            No enviado
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="mt-auto">
                                                    @if($anticipadaEnviada)
                                                        @if(Route::has('company.simple.desconsolidado.show'))
                                                            <a href="{{ route('company.simple.desconsolidado.show', $voyage) }}"
                                                            class="block w-full text-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition">
                                                                Enviar Ahora
                                                            </a>
                                                        @else
                                                            <button disabled
                                                                    class="block w-full text-center px-3 py-1.5 bg-gray-200 text-gray-500 text-xs font-medium rounded cursor-not-allowed">
                                                                Ruta no disponible
                                                            </button>
                                                        @endif
                                                    @else
                                                        <button disabled
                                                                class="block w-full text-center px-3 py-1.5 bg-gray-200 text-gray-500 text-xs font-medium rounded cursor-not-allowed">
                                                            Requiere Paso 1
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>


                                            {{-- TRANSBORDO - PASO 4 --}}
                                            <div class="border rounded-lg p-3 min-h-[120px] flex flex-col {{ $anticipadaEnviada ? 'bg-white' : 'bg-gray-50 opacity-60' }}">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center mb-1">
                                                            @if(!$anticipadaEnviada)
                                                                <svg class="w-3 h-3 mr-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @endif
                                                            <span class="text-xs font-medium text-gray-600 mr-2">Paso 4</span>
                                                        </div>
                                                        <span class="text-sm font-semibold text-gray-900 block truncate">Transbordo</span>
                                                    </div>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 whitespace-nowrap">
                                                        Próximamente
                                                    </span>
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    <button disabled 
                                                            class="block w-full text-center px-3 py-1.5 bg-gray-200 text-gray-500 text-xs font-medium rounded cursor-not-allowed">
                                                        No Disponible
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Sección de Webservices Paraguay --}}
                                    {{-- MANIFIESTO PARAGUAY --}}
                                    @php
                                        // Habilitamos la tarjeta como activa.
                                        // Si la ruta aún no existe, el botón se deshabilita con un mensaje claro.
                                        $canOpenManifiesto = \Illuminate\Support\Facades\Route::has('company.simple.manifiesto.show');
                                    @endphp

                                    <div class="border rounded-lg p-3 bg-white min-h-[120px] flex flex-col">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm font-semibold text-gray-900 block truncate">Manifiesto</span>
                                                <span class="text-xs text-gray-500">DNA Paraguay</span>
                                            </div>

                                            @if(!empty($manifiestoStatus))
                                                @php
                                                    $color = $statusColors[$manifiestoStatus['status']] ?? 'bg-gray-100 text-gray-800';
                                                    $text  = $statusTexts[$manifiestoStatus['status']] ?? 'N/D';
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }} whitespace-nowrap">
                                                    {{ $text }}
                                                </span>
                                            @else
                                                {{-- Activo pero aún no enviado --}}
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 whitespace-nowrap">
                                                    No enviado
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-auto">
                                            @if($canOpenManifiesto)
                                                <a href="{{ route('company.simple.manifiesto.show', $voyage) }}"
                                                class="block w-full text-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                                    Enviar Ahora
                                                </a>
                                            @else
                                                <button disabled
                                                        class="block w-full text-center px-3 py-1.5 bg-gray-200 text-gray-500 text-xs font-medium rounded cursor-not-allowed">
                                                    Ruta no disponible
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            @endforeach
                        </div>

                        {{-- Paginación --}}
                        <div class="mt-6">
                            {{ $voyages->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin viajes disponibles</h3>
                            <p class="mt-1 text-sm text-gray-500">Comience creando un nuevo viaje.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>