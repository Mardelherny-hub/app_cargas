{{--
  DASHBOARD 18 MÉTODOS AFIP - MIC/DTA Argentina
  Panel de control para ejecutar métodos AFIP específicos desde la interfaz
  Ubicación: resources/views/company/simple/micdta/methods-dashboard.blade.php
--}}

<x-app-layout>
    <x-slot name="header">
   <x-slot name="header">
        @include('company.simple.partials.afip-header', [
            'voyage'  => $voyage,
            'company' => $company ?? null,
            'active'  => 'micdta',
        ])
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Información del Viaje--}}
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Viaje</p>
                        <p class="font-medium">{{ $voyage->voyage_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Embarcación</p>
                        <p class="font-medium">{{ $voyage->leadVessel->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Ruta</p>
                        <p class="font-medium">
                            {{ $voyage->originPort->code ?? 'N/A' }} → {{ $voyage->destinationPort->code ?? 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Estado MIC/DTA</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @if($micdta_status && $micdta_status->status === 'sent') bg-green-100 text-green-800
                            @elseif($micdta_status && $micdta_status->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($micdta_status && $micdta_status->status === 'error') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $micdta_status ? ucfirst($micdta_status->status) : 'No enviado' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Sección TRACKs Generados --}}
            @if(isset($tracks) && $tracks->count() > 0)
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="border-b border-gray-200 pb-3 mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            🏷️ TRACKs Generados ({{ $tracks->count() }})
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Identificadores AFIP para rastreo de envíos
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        TRACK Number
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Shipment
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Generado
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Método
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tracks as $track)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="text-sm font-mono text-gray-900">{{ $track->track_number }}</span>
                                                @if($track->afip_metadata && isset($track->afip_metadata['is_fake']) && $track->afip_metadata['is_fake'])
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                        ⚠️ TESTING
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ ucfirst($track->track_type) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ $track->reference_number ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($track->status === 'generated') bg-blue-100 text-blue-800
                                                @elseif($track->status === 'used_in_micdta') bg-green-100 text-green-800
                                                @elseif($track->status === 'completed') bg-gray-100 text-gray-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst(str_replace('_', ' ', $track->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $track->generated_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ $track->webservice_method }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($tracks->where('afip_metadata.is_fake', true)->count() > 0)
                        <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-orange-800">Ambiente de Testing</h4>
                                    <p class="text-sm text-orange-700 mt-1">
                                        Los TRACKs marcados como TESTING son ficticios generados automáticamente porque AFIP homologación no devuelve TRACKs reales según manual. 
                                        En producción se usarán TRACKs reales de AFIP.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @php
                // RegistrarTitEnvios - transacciones con shipment_id pero sin tracks generados
                $lastTitEnvios = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->whereNotNull('shipment_id')
                    ->where(function($q) {
                        $q->where('soap_action', 'like', '%TitEnvios%')
                          ->orWhereDoesntHave('webserviceTracks');
                    })
                    ->latest()
                    ->first();

                // RegistrarEnvios - transacciones con shipment_id que generaron tracks
                $lastEnvios = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->whereNotNull('shipment_id')
                    ->whereHas('webserviceTracks')
                    ->latest()
                    ->first();

                // RegistrarMicDta - transacciones sin shipment_id (nivel voyage)
                $lastMicDta = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->whereNull('shipment_id')
                    ->where('status', '!=', 'pending')
                    ->latest()
                    ->first();

                // Obtener TRACKs
                $tracks = $voyage->webserviceTracks()
                    ->whereHas('webserviceTransaction', function($q) {
                        $q->where('webservice_type', 'micdta');
                    })
                    ->latest()
                    ->get();
                
                // DEBUG: Ver datos reales
                $allTransactions = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->latest()
                    ->take(10)
                    ->get();
            @endphp
            {{-- Panel de Métodos AFIP --}}
            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Métodos AFIP Disponibles (18 Total)
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Ejecute métodos específicos según el estado del Viaje y requisitos AFIP
                    </p>
                </div>
                <div class="p-6 space-y-8">
                    {{-- GRUPO 1: MÉTODOS PRINCIPALES (1-3) --}}
                    <div class="border border-blue-200 rounded-lg p-4 bg-blue-50">
                        <h4 class="text-md font-semibold text-blue-900 mb-3">
                            🚢 Métodos Principales (Flujo Básico)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            <div class="flex flex-col bg-white border-2 border-blue-300 rounded-lg overflow-hidden">
                                <button onclick="executeAfipMethod('RegistrarTitEnvios')"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-blue-50 transition-colors">
                                    <span class="text-2xl mb-2">📋</span>
                                    <span class="text-sm font-medium text-center">1. RegistrarTitEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Registra títulos de transporte</span>
                                </button>
                                
                                @if($lastTitEnvios)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastTitEnvios->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastTitEnvios->status === 'sent') bg-green-100 text-green-800
                                                @elseif($lastTitEnvios->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastTitEnvios->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col bg-white border-2 border-blue-300 rounded-lg overflow-hidden">
                                <button onclick="executeAfipMethod('RegistrarEnvios')"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-blue-50 transition-colors">
                                    <span class="text-2xl mb-2">📦</span>
                                    <span class="text-sm font-medium text-center">2. RegistrarEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Genera TRACKs de envíos</span>
                                </button>
                                
                                @if($lastEnvios)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastEnvios->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastEnvios->status === 'sent') bg-green-100 text-green-800
                                                @elseif($lastEnvios->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastEnvios->status) }}
                                            </span>
                                        </div>
                                        @if($tracks->isNotEmpty())
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">TRACKs:</span>
                                                <span class="text-green-600 font-semibold">{{ $tracks->count() }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col bg-white border-2 border-blue-300 rounded-lg overflow-hidden">
                                <button onclick="executeAfipMethod('RegistrarMicDta')"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-blue-50 transition-colors">
                                    <span class="text-2xl mb-2">📄</span>
                                    <span class="text-sm font-medium text-center">3. RegistrarMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Registra MIC/DTA completo</span>
                                </button>
                                
                                @if($lastMicDta)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastMicDta->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastMicDta->status === 'sent') bg-green-100 text-green-800
                                                @elseif($lastMicDta->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastMicDta->status) }}
                                            </span>
                                        </div>
                                        @if($lastMicDta->external_reference)
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">MIC/DTA:</span>
                                                <span class="text-green-600 font-mono text-xs">{{ Str::limit($lastMicDta->external_reference, 12) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- GRUPO 2: GESTIÓN CONVOY (4-6) --}}
                    @php
                        $isConvoyVoyage = $voyage->shipments->count() > 1;
                        $convoyButtonClass = $isConvoyVoyage 
                            ? 'bg-white border-2 border-green-300 hover:bg-green-100 hover:border-green-400 transition-colors'
                            : 'bg-gray-100 border-2 border-gray-300 cursor-not-allowed opacity-60';
                        $convoyOnClick = $isConvoyVoyage ? 'onclick="executeAfipMethod(\'RegistrarConvoy\')"' : 'onclick="showConvoyNotApplicable()"';
                    @endphp
                    <div class="border border-purple-200 rounded-lg p-4 bg-purple-50">
                        <h4 class="text-md font-semibold text-purple-900 mb-3">
                            🚛 Gestión de Convoy
                            @if(!$isConvoyVoyage)
                                <span class="text-xs font-normal">(No aplicable para Viaje individual)</span>
                            @endif
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @if($isConvoyVoyage)
                            {{-- CONVOY APLICABLE --}}
                                <button onclick="executeAfipMethod('RegistrarConvoy')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                    <span class="text-2xl mb-2">🚢</span>
                                    <span class="text-sm font-medium text-center">4. RegistrarConvoy</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Agrupa MIC/DTAs en convoy</span>
                                </button>                                
                            @else
                                {{-- CONVOY NO APLICABLE --}}
                                <button onclick="showConvoyNotApplicable()"
                                        disabled
                                        class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                    <span class="text-2xl mb-2">🔗</span>
                                    <span class="text-sm font-medium text-center">4. RegistrarConvoy</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Solo para múltiples embarcaciones</span>
                                </button>
                            @endif

                            @if ($isConvoyVoyage)
                            {{-- aplicable --}}
                                <button onclick="executeAfipMethod('AsignarATARemol')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                    <span class="text-2xl mb-2">⚓</span>
                                    <span class="text-sm font-medium text-center">5. AsignarATARemol</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Asigna remolcador ATA</span>
                                </button>
                            @else
                            {{-- no aplicable   --}}
                                <button onclick="showConvoyNotApplicable('AsignarATARemol')"
                                        disabled
                                        class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                    <span class="text-2xl mb-2">⚓</span>
                                    <span class="text-sm font-medium text-center">5. AsignarATARemol</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Asigna remolcador ATA</span>
                                </button>
                            @endif    
                                
                            @if ($isConvoyVoyage)
                            {{-- aplicable --}}    
                                <button onclick="executeAfipMethod('RectifConvoyMicDta')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                    <span class="text-2xl mb-2">✏️</span>
                                    <span class="text-sm font-medium text-center">6. RectifConvoyMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Rectifica convoy/MIC-DTA</span>
                                </button>
                            @else
                            {{-- no aplicable   --}}
                                <button onclick="showConvoyNotApplicable('RectifConvoyMicDta')"
                                        disabled
                                        class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                    <span class="text-2xl mb-2">✏️</span>
                                    <span class="text-sm font-medium text-center">6. RectifConvoyMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Rectifica convoy/MIC-DTA</span>
                                </button>
                                
                            @endif
                                
                        </div>
                    </div>

                    {{-- GRUPO 3: GESTIÓN TÍTULOS (7-9) --}}
                    <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                        <h4 class="text-md font-semibold text-green-900 mb-3">
                            📑 Gestión de Títulos
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            <div class="flex flex-col bg-white border-2 border-purple-300 rounded-lg overflow-hidden">
                                <button onclick="executeAfipMethod('RegistrarTitMicDta')"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-purple-50 transition-colors">
                                    <span class="text-2xl mb-2">🔗</span>
                                    <span class="text-sm font-medium text-center">7. RegistrarTitMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Vincula títulos con MIC/DTA</span>
                                </button>
                                
                                @php
                                    // Buscar última ejecución de RegistrarTitMicDta
                                    $lastTitMicDta = $voyage->webserviceTransactions()
                                        ->where('webservice_type', 'micdta')
                                        ->where('soap_action', 'like', '%RegistrarTitMicDta%')
                                        ->latest()
                                        ->first();
                                @endphp
                                
                                @if($lastTitMicDta)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-purple-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastTitMicDta->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastTitMicDta->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastTitMicDta->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastTitMicDta->status) }}
                                            </span>
                                        </div>
                                        @if($lastTitMicDta->success_data && isset($lastTitMicDta->success_data['titulos_vinculados']))
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">Títulos vinculados:</span>
                                                <span class="text-gray-900 font-medium">
                                                    {{ is_array($lastTitMicDta->success_data['titulos_vinculados']) 
                                                        ? count($lastTitMicDta->success_data['titulos_vinculados']) 
                                                        : $lastTitMicDta->success_data['titulos_vinculados'] }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($lastTitMicDta->external_reference)
                                            <div class="mt-1 pt-1 border-t border-purple-100">
                                                <span class="text-gray-600">ID MIC/DTA:</span>
                                                <span class="text-purple-700 font-mono text-xs block truncate">
                                                    {{ Str::limit($lastTitMicDta->external_reference, 25) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col bg-white border-2 border-orange-300 rounded-lg overflow-hidden">
                                <button onclick="openDesvincularModal()"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-orange-50 transition-colors">
                                    <span class="text-2xl mb-2">🔓</span>
                                    <span class="text-sm font-medium text-center">8. DesvincularTitMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Desvincula títulos del MIC/DTA</span>
                                </button>
                                
                                @php
                                    // Buscar última ejecución de DesvincularTitMicDta
                                    $lastDesvinculacion = $voyage->webserviceTransactions()
                                        ->where('webservice_type', 'micdta')
                                        ->where('soap_action', 'like', '%DesvincularTitMicDta%')
                                        ->latest()
                                        ->first();
                                @endphp
                                
                                @if($lastDesvinculacion)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-orange-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastDesvinculacion->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastDesvinculacion->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastDesvinculacion->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastDesvinculacion->status) }}
                                            </span>
                                        </div>
                                        @if($lastDesvinculacion->success_data && isset($lastDesvinculacion->success_data['titulos_desvinculados']))
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">Títulos desvinculados:</span>
                                                <span class="text-gray-900 font-medium">
                                                    {{ is_array($lastDesvinculacion->success_data['titulos_desvinculados']) 
                                                        ? count($lastDesvinculacion->success_data['titulos_desvinculados']) 
                                                        : $lastDesvinculacion->success_data['titulos_desvinculados'] }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($lastDesvinculacion->external_reference)
                                            <div class="mt-1 pt-1 border-t border-orange-100">
                                                <span class="text-gray-600">ID MIC/DTA:</span>
                                                <span class="text-orange-700 font-mono text-xs block truncate">
                                                    {{ Str::limit($lastDesvinculacion->external_reference, 25) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <button onclick="ejecutarAnulacionDirecta()"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                <span class="text-2xl mb-2">❌</span>
                                <span class="text-sm font-medium text-center">9. AnularTitulo</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Anula título de transporte</span>
                            </button>
                        </div>
                    </div>

                    {{-- GRUPO 4: ZONA PRIMARIA (10-12) --}}
                    <div class="border border-orange-200 rounded-lg p-4 bg-orange-50">
                        <h4 class="text-md font-semibold text-orange-900 mb-3">
                            🏢 Zona Primaria
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            <button onclick="executeAfipMethod('RegistrarSalidaZonaPrimaria')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                <span class="text-2xl mb-2">🚪</span>
                                <span class="text-sm font-medium text-center">10. RegistrarSalida</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Salida zona primaria</span>
                            </button>

                            <button onclick="executeAfipMethod('RegistrarArriboZonaPrimaria')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                <span class="text-2xl mb-2">🛬</span>
                                <span class="text-sm font-medium text-center">11. RegistrarArribo</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Arribo zona primaria</span>
                            </button>

                            <button onclick="executeAfipMethod('AnularArriboZonaPrimaria')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                <span class="text-2xl mb-2">🚫</span>
                                <span class="text-sm font-medium text-center">12. AnularArribo</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Anula arribo zona primaria</span>
                            </button>
                        </div>
                    </div>

                    {{-- GRUPO 5: CONSULTAS (13-15) --}}
                    <div class="border border-indigo-200 rounded-lg p-4 bg-indigo-50">
                        <h4 class="text-md font-semibold text-indigo-900 mb-3">
                            🔍 Consultas
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            <button onclick="executeAfipMethod('ConsultarMicDtaAsig')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                <span class="text-2xl mb-2">🔎</span>
                                <span class="text-sm font-medium text-center">13. ConsultarMicDtaAsig</span>
                                <span class="text-xs text-gray-600 text-center mt-1">MIC/DTA asignados</span>
                            </button>

                            <button onclick="executeAfipMethod('ConsultarTitEnviosReg')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                <span class="text-2xl mb-2">📊</span>
                                <span class="text-sm font-medium text-center">14. ConsultarTitEnvios</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Títulos registrados</span>
                            </button>

                            <button onclick="executeAfipMethod('ConsultarPrecumplido')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                <span class="text-2xl mb-2">✅</span>
                                <span class="text-sm font-medium text-center">15. ConsultarPrecumplido</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Estado precumplido</span>
                            </button>
                        </div>
                    </div>

                    {{-- GRUPO 6: ANULACIONES + TESTING (16-18) --}}
                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                        <h4 class="text-md font-semibold text-red-900 mb-3">
                            🗑️ Anulaciones y Testing
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            <button onclick="executeAfipMethod('SolicitarAnularMicDta')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                <span class="text-2xl mb-2">🗂️</span>
                                <span class="text-sm font-medium text-center">16. SolicitarAnular</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Anula MIC/DTA</span>
                            </button>

                            <button onclick="executeAfipMethod('AnularEnvios')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                <span class="text-2xl mb-2">📮</span>
                                <span class="text-sm font-medium text-center">17. AnularEnvios</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Anula envíos por TRACKs</span>
                            </button>

                            <button onclick="executeAfipMethod('Dummy')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                <span class="text-2xl mb-2">🧪</span>
                                <span class="text-sm font-medium text-center">18. Dummy</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Test conectividad</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
             {{-- DEBUG TEMPORAL - ELIMINAR DESPUÉS --}}
                @if(auth()->user()->is_admin || true)
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-semibold text-yellow-900 mb-2">🔍 Últimas 10 transacciones MIC/DTA:</h4>
                        <div class="text-xs space-y-2 max-h-96 overflow-y-auto">
                            @forelse($allTransactions as $trans)
                                <div class="bg-white p-2 rounded border">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div><strong>ID:</strong> {{ $trans->id }}</div>
                                        <div><strong>Transaction ID:</strong> {{ Str::limit($trans->transaction_id, 20) }}</div>
                                        <div><strong>Shipment ID:</strong> {{ $trans->shipment_id ?? 'NULL' }}</div>
                                        <div><strong>Status:</strong> <span class="px-2 py-0.5 rounded text-xs
                                            @if($trans->status === 'sent') bg-green-100 text-green-800
                                            @elseif($trans->status === 'error') bg-red-100 text-red-800
                                            @else bg-yellow-100 text-yellow-800 @endif">
                                            {{ $trans->status }}
                                        </span></div>
                                        <div><strong>Fecha:</strong> {{ $trans->created_at->format('d/m H:i:s') }}</div>
                                        <div><strong>TRACKs:</strong> {{ $trans->webserviceTracks->count() }}</div>
                                        <div class="col-span-2"><strong>SOAP Action:</strong> <code class="text-xs bg-gray-100 px-1 rounded">{{ Str::limit($trans->soap_action ?? 'NULL', 60) }}</code></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-yellow-800">No hay transacciones MIC/DTA</p>
                            @endforelse
                        </div>
                        
                        {{-- <div class="mt-4 pt-4 border-t border-yellow-300">
                            <p class="text-yellow-900 font-medium mb-2">📊 Resultados de búsqueda:</p>
                            <div class="grid grid-cols-3 gap-4 text-xs">
                                <div class="bg-white p-3 rounded border">
                                    <p class="font-semibold text-gray-700 mb-1">RegistrarTitEnvios</p>
                                    @if($lastTitEnvios)
                                        <p class="text-green-700">✓ Encontrado</p>
                                        <p class="text-gray-600">ID: {{ $lastTitEnvios->id }}</p>
                                        <p class="text-gray-600">{{ $lastTitEnvios->created_at->format('d/m H:i') }}</p>
                                    @else
                                        <p class="text-red-700">✗ No encontrado</p>
                                    @endif
                                </div>
                                
                                <div class="bg-white p-3 rounded border">
                                    <p class="font-semibold text-gray-700 mb-1">RegistrarEnvios</p>
                                    @if($lastEnvios)
                                        <p class="text-green-700">✓ Encontrado</p>
                                        <p class="text-gray-600">ID: {{ $lastEnvios->id }}</p>
                                        <p class="text-gray-600">{{ $lastEnvios->created_at->format('d/m H:i') }}</p>
                                    @else
                                        <p class="text-red-700">✗ No encontrado</p>
                                    @endif
                                </div>
                                
                                <div class="bg-white p-3 rounded border">
                                    <p class="font-semibold text-gray-700 mb-1">RegistrarMicDta</p>
                                    @if($lastMicDta)
                                        <p class="text-green-700">✓ Encontrado</p>
                                        <p class="text-gray-600">ID: {{ $lastMicDta->id }}</p>
                                        <p class="text-gray-600">{{ $lastMicDta->created_at->format('d/m H:i') }}</p>
                                    @else
                                        <p class="text-red-700">✗ No encontrado</p>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="mt-3 bg-white p-3 rounded border">
                                <p class="font-semibold text-gray-700 mb-1">TRACKs Generados</p>
                                <p class="text-gray-600">Total: {{ $tracks->count() }}</p>
                            </div>
                        </div> --}}
                    </div>
                @endif
        </div>
    </div>

    {{-- Modal de Resultado --}}
    <div id="resultModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div id="resultIcon" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full"></div>
                <h3 id="resultTitle" class="text-lg font-medium text-gray-900 mt-4"></h3>
                <div id="resultMessage" class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500"></p>
                </div>
                <div class="items-center px-4 py-3">
                    <button onclick="closeResultModal()" 
                            class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-blue-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal para DesvincularTitMicDta --}}
    <div id="desvincularModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Desvincular Títulos del MIC/DTA
                    </h3>
                    <button onclick="closeDesvincularModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-3">
                        Seleccione los títulos que desea desvincular:
                    </p>
                    
                    <div id="titulosVinculadosList" class="space-y-2 max-h-60 overflow-y-auto border rounded-lg p-3">
                        <!-- Se llena dinámicamente con JavaScript -->
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDesvincularModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" onclick="confirmarDesvinculacion()"
                            class="px-4 py-2 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-md hover:bg-orange-700">
                        Desvincular Seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal para AnularTitulo - SIMPLIFICADO --}}
    <div id="anularTituloModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-red-900">
                        ⚠️ Anular Títulos - RESET TOTAL
                    </h3>
                    <button onclick="closeAnularTituloModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="bg-red-100 border-2 border-red-500 rounded-lg p-4 mb-4">
                    <p class="text-sm text-red-900 font-bold mb-3">
                        🚨 OPERACIÓN DESTRUCTIVA E IRREVERSIBLE
                    </p>
                    <p class="text-sm text-red-800 mb-2">
                        Esta acción anulará <strong>TODOS los títulos del viaje</strong> y:
                    </p>
                    <ul class="text-xs text-red-700 ml-4 list-disc space-y-1 mb-3">
                        <li>Cancelará TODOS los TRACKs generados</li>
                        <li>Invalidará el MIC/DTA registrado</li>
                        <li>Deberá reiniciar desde RegistrarTitEnvios (paso 1)</li>
                    </ul>
                    <p class="text-xs text-red-900 font-bold bg-red-200 p-2 rounded">
                        ⚠️ Solo usar en casos de error grave o cancelación total del viaje
                    </p>
                </div>

                <div class="mb-4 bg-gray-50 p-3 rounded">
                    <p class="text-sm font-medium text-gray-700 mb-2">
                        Títulos que serán anulados:
                    </p>
                    <div id="titulosListaAnular" class="text-sm text-gray-600">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Motivo de anulación: <span class="text-red-600">*</span>
                    </label>
                    <textarea id="motivoAnulacion" 
                            rows="3"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500"
                            placeholder="Explique el motivo de la anulación total..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Mínimo 20 caracteres</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAnularTituloModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" onclick="confirmarAnulacionTotal()"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:ring-2 focus:ring-red-500">
                        🚨 Anular TODOS los Títulos
                    </button>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>

<script>
    const voyageId = {{ $voyage->id }};

    /**
     * Ejecutar método AFIP específico
     */
    async function executeAfipMethod(methodName) {
        if (confirm(`¿Ejecutar método ${methodName}?\n\nEsta acción enviará datos a AFIP Argentina.`)) {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            button.disabled = true;
            button.innerHTML = `<svg class="animate-spin w-6 h-6 mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>`;

            try {
                // CORREGIR: Mapeo directo methodName → ruta
                const routeMap = {
                    'RegistrarTitEnvios': 'registrar-tit-envios',
                    'RegistrarEnvios': 'registrar-envios',
                    'RegistrarMicDta': 'registrar-micdta',
                    'RegistrarConvoy': 'registrar-convoy',
                    'AsignarATARemol': 'asignar-ata-remol',
                    'RectifConvoyMicDta': 'rectif-convoy-micdta',
                    'RegistrarTitMicDta': 'registrar-tit-micdta',
                    'DesvincularTitMicDta': 'desvincular-tit-micdta',
                    'AnularTitulo': 'anular-titulo',
                    'RegistrarSalidaZonaPrimaria': 'registrar-salida-zona-primaria',
                    'RegistrarArriboZonaPrimaria': 'registrar-arribo-zona-primaria',
                    'AnularArriboZonaPrimaria': 'anular-arribo-zona-primaria',
                    'ConsultarMicDtaAsig': 'consultar-micdta-asig',
                    'ConsultarTitEnviosReg': 'consultar-tit-envios-reg',
                    'ConsultarPrecumplido': 'consultar-precumplido',
                    'SolicitarAnularMicDta': 'solicitar-anular-micdta',
                    'AnularEnvios': 'anular-envios',
                    'Dummy': 'dummy'
                };
                
                const route = routeMap[methodName];
                if (!route) {
                    throw new Error(`Método ${methodName} no encontrado`);
                }
                
                const url = `/company/simple/webservices/micdta/${voyageId}/${route}`;
                
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        force_send: false,
                        notes: `Ejecutado desde panel métodos AFIP - ${new Date().toLocaleString()}`
                    })
                });

                const result = await response.json();
                showResultModal(methodName, result, response.ok);

            } catch (error) {
                showResultModal(methodName, { error: 'Error de comunicación: ' + error.message }, false);
            } finally {
                // Restaurar botón
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    }

    // Variables globales para desvinculación
    let titulosVinculados = [];

    /**
     * Abrir modal de desvinculación
     */
    async function openDesvincularModal() {
        // Obtener títulos vinculados del servidor
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-vinculados`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success && result.titulos && result.titulos.length > 0) {
                titulosVinculados = result.titulos;
                renderTitulosList(result.titulos);
                document.getElementById('desvincularModal').classList.remove('hidden');
            } else {
                alert('No hay títulos vinculados para desvincular.');
            }
        } catch (error) {
            console.error('Error obteniendo títulos vinculados:', error);
            alert('Error al cargar títulos vinculados');
        }
    }

    /**
     * Renderizar lista de títulos con checkboxes
     */
    function renderTitulosList(titulos) {
        const container = document.getElementById('titulosVinculadosList');
        container.innerHTML = '';

        titulos.forEach((titulo, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center space-x-2 p-2 hover:bg-gray-50 rounded';
            div.innerHTML = `
                <input type="checkbox" 
                    id="titulo_${index}" 
                    value="${titulo}"
                    class="titulo-checkbox w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <label for="titulo_${index}" class="flex-1 text-sm text-gray-700 cursor-pointer">
                    ${titulo}
                </label>
            `;
            container.appendChild(div);
        });
    }

    /**
     * Cerrar modal de desvinculación
     */
    function closeDesvincularModal() {
        document.getElementById('desvincularModal').classList.add('hidden');
    }

    /**
     * Confirmar y ejecutar desvinculación
     */
    async function confirmarDesvinculacion() {
        const checkboxes = document.querySelectorAll('.titulo-checkbox:checked');
        const titulosSeleccionados = Array.from(checkboxes).map(cb => cb.value);

        if (titulosSeleccionados.length === 0) {
            alert('Debe seleccionar al menos un título para desvincular');
            return;
        }

        if (!confirm(`¿Confirma desvincular ${titulosSeleccionados.length} título(s)?`)) {
            return;
        }

        closeDesvincularModal();

        // Ejecutar desvinculación vía AJAX
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/desvincular-tit-micdta`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulos: titulosSeleccionados,
                    force_send: false,
                    notes: `Desvinculación selectiva desde interfaz - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            showResultModal('DesvincularTitMicDta', result, response.ok);

        } catch (error) {
            showResultModal('DesvincularTitMicDta', { 
                error: 'Error de comunicación: ' + error.message 
            }, false);
        }
    }

    // ========================================
    // MODAL ANULAR TÍTULO (RESET TOTAL)
    // ========================================

    /**
     * Abrir modal de anulación total
     */
    async function openAnularTituloModal() {
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-registrados`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success && result.titulos && result.titulos.length > 0) {
                // Mostrar lista de títulos que serán anulados
                const lista = document.getElementById('titulosListaAnular');
                lista.innerHTML = '<ul class="list-disc ml-4 space-y-1">' + 
                    result.titulos.map(t => `<li class="text-red-700 font-medium">${t}</li>`).join('') +
                    '</ul>';
                
                document.getElementById('anularTituloModal').classList.remove('hidden');
            } else {
                alert('No hay títulos registrados para anular.');
            }
        } catch (error) {
            console.error('Error obteniendo títulos:', error);
            alert('Error al cargar títulos');
        }
    }

    /**
     * Cerrar modal de anulación
     */
    function closeAnularTituloModal() {
        document.getElementById('anularTituloModal').classList.add('hidden');
        const textarea = document.getElementById('motivoAnulacion');
        if (textarea) textarea.value = '';
    }

    /**
     * Confirmar anulación TOTAL de todos los títulos
     */
    async function confirmarAnulacionTotal() {
        const motivo = document.getElementById('motivoAnulacion').value.trim();

        if (!motivo || motivo.length < 20) {
            alert('⚠️ Debe proporcionar un motivo detallado (mínimo 20 caracteres)');
            document.getElementById('motivoAnulacion').focus();
            return;
        }

        // Triple confirmación para operación destructiva
        if (!confirm('⚠️ PRIMERA CONFIRMACIÓN\n\n¿Está seguro de anular TODOS los títulos?\n\nEsta acción es IRREVERSIBLE.')) {
            return;
        }

        if (!confirm('⚠️ SEGUNDA CONFIRMACIÓN\n\nAl continuar:\n- Se cancelarán TODOS los TRACKs\n- Se invalidará el MIC/DTA\n- Deberá reiniciar desde cero\n\n¿Confirma la anulación TOTAL?')) {
            return;
        }

        if (!confirm('⚠️ CONFIRMACIÓN FINAL\n\nÚltima advertencia: Esta operación NO se puede deshacer.\n\n¿Proceder con la anulación?')) {
            return;
        }

        closeAnularTituloModal();

        // Mostrar loading si existe
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.classList.remove('hidden');

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/anular-titulo`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulo_id: 'ALL',
                    motivo_anulacion: motivo,
                    anular_todos: true,
                    force_send: false,
                    notes: `Anulación TOTAL desde interfaz - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            
            if (spinner) spinner.classList.add('hidden');
            
            showResultModal('AnularTitulo', result, response.ok);

        } catch (error) {
            if (spinner) spinner.classList.add('hidden');
            
            showResultModal('AnularTitulo', { 
                error: 'Error de comunicación: ' + error.message 
            }, false);
        }
    }

    /**
     * Mostrar modal con resultado
     */
    function showResultModal(methodName, result, isSuccess) {
        const modal = document.getElementById('resultModal');
        const icon = document.getElementById('resultIcon');
        const title = document.getElementById('resultTitle');
        const message = document.getElementById('resultMessage');
        
        if (isSuccess && result.success) {
            icon.innerHTML = '✅';
            icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100';
            title.textContent = `${methodName} - Exitoso`;
            message.innerHTML = `
                <p class="text-sm text-gray-700">
                    <strong>Método:</strong> ${methodName}<br>
                    ${result.data?.transaction_id ? `<strong>Transaction ID:</strong> ${result.data.transaction_id}<br>` : ''}
                    ${result.data?.external_reference ? `<strong>Referencia:</strong> ${result.data.external_reference}<br>` : ''}
                    <strong>Mensaje:</strong> ${result.message || 'Operación completada exitosamente'}
                </p>
            `;

             // ✅ NUEVO: Si el método requiere recarga, agregar botón especial
            if (result.reload_required || methodName === 'AnularTitulo') {
                message.innerHTML += `
                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800 font-semibold">
                            ⚠️ Esta operación modificó datos críticos. Se recargará la página automáticamente.
                        </p>
                    </div>
                `;
                
                // Recargar automáticamente después de 3 segundos
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        } else {
            icon.innerHTML = '❌';
            icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100';
            title.textContent = `${methodName} - Error`;
            
            // ✅ CONSTRUIR MENSAJE DE ERROR CON DETALLES
            let errorHtml = `
                <p class="text-sm text-gray-700">
                    <strong>Error:</strong> ${result.error || result.details || 'Error desconocido'}<br>
                    ${result.error_code ? `<strong>Código:</strong> ${result.error_code}<br>` : ''}
                </p>
            `;
            
            // ✅ AGREGAR: Lista de errores de validación
            if (result.validation_errors && result.validation_errors.length > 0) {
                errorHtml += `
                    <div class="mt-4 p-3 bg-red-50 rounded-md">
                        <p class="text-sm font-semibold text-red-800 mb-2">Errores encontrados:</p>
                        <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                            ${result.validation_errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                        <p class="text-xs text-red-600 mt-2 italic">Por favor corrija estos datos antes de continuar.</p>
                    </div>
                `;
            }
            
            // ✅ AGREGAR: Lista de advertencias (warnings)
            if (result.warnings && result.warnings.length > 0) {
                errorHtml += `
                    <div class="mt-3 p-3 bg-yellow-50 rounded-md">
                        <p class="text-sm font-semibold text-yellow-800 mb-2">Advertencias:</p>
                        <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                            ${result.warnings.map(warning => `<li>${warning}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            message.innerHTML = errorHtml;
        }
        
        modal.classList.remove('hidden');
    }

    /**
     * Cerrar modal de resultado
     */
    function closeResultModal() {
        document.getElementById('resultModal').classList.add('hidden');
    }

    function showConvoyNotApplicable() {
        showResultModal('Convoy', {
            error: 'Los métodos de convoy solo aplican para Viajes con múltiples embarcaciones (remolcador + barcazas). Su Viajeactual tiene una sola embarcación.'
        }, false);
    }

    {{-- Modal para AnularTitulo - SIMPLIFICADO --}}
<div id="anularTituloModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-red-900">
                    ⚠️ Anular Títulos - RESET TOTAL
                </h3>
                <button onclick="closeAnularTituloModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="bg-red-100 border-2 border-red-500 rounded-lg p-4 mb-4">
                <p class="text-sm text-red-900 font-bold mb-3">
                    🚨 OPERACIÓN DESTRUCTIVA E IRREVERSIBLE
                </p>
                <p class="text-sm text-red-800 mb-2">
                    Esta acción anulará <strong>TODOS los títulos del viaje</strong> y:
                </p>
                <ul class="text-xs text-red-700 ml-4 list-disc space-y-1 mb-3">
                    <li>Cancelará TODOS los TRACKs generados</li>
                    <li>Invalidará el MIC/DTA registrado</li>
                    <li>Deberá reiniciar desde RegistrarTitEnvios (paso 1)</li>
                </ul>
                <p class="text-xs text-red-900 font-bold bg-red-200 p-2 rounded">
                    ⚠️ Solo usar en casos de error grave o cancelación total del viaje
                </p>
            </div>

            <div class="mb-4 bg-gray-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-700 mb-2">
                    Títulos que serán anulados:
                </p>
                <div id="titulosListaAnular" class="text-sm text-gray-600">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Motivo de anulación: <span class="text-red-600">*</span>
                </label>
                <textarea id="motivoAnulacion" 
                          rows="3"
                          class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500"
                          placeholder="Explique el motivo de la anulación total (mínimo 20 caracteres)..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeAnularTituloModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" onclick="confirmarAnulacionTotal()"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700">
                    🚨 Anular TODOS los Títulos
                </button>
            </div>
        </div>
    </div>
</div>

    /**
     * Cerrar modal de anulación
     */
    function closeAnularTituloModal() {
        document.getElementById('anularTituloModal').classList.add('hidden');
        document.getElementById('tituloAnularSelect').value = '';
        document.getElementById('motivoAnulacion').value = '';
    }

    /**
     * Confirmar anulación de título
     */
    async function confirmarAnulacion() {
        const tituloId = document.getElementById('tituloAnularSelect').value;
        const motivo = document.getElementById('motivoAnulacion').value.trim();

        if (!tituloId) {
            alert('Debe seleccionar un título');
            return;
        }

        if (!motivo || motivo.length < 10) {
            alert('Debe proporcionar un motivo detallado (mínimo 10 caracteres)');
            return;
        }

        if (!confirm(`⚠️ CONFIRMACIÓN FINAL\n\n¿Está ABSOLUTAMENTE SEGURO de anular el título "${tituloId}"?\n\nEsta operación es IRREVERSIBLE y cancelará todos los TRACKs asociados.\n\nMotivo: ${motivo}`)) {
            return;
        }

        closeAnularTituloModal();

        // Ejecutar anulación
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/anular-titulo`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulo_id: tituloId,
                    motivo_anulacion: motivo,
                    force_send: false,
                    notes: `Anulación desde interfaz - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            showResultModal('AnularTitulo', result, response.ok);

        } catch (error) {
            showResultModal('AnularTitulo', { 
                error: 'Error de comunicación: ' + error.message 
            }, false);
        }
    }

    function ejecutarAnulacionDirecta() {
    const motivo = prompt('⚠️ ANULACIÓN TOTAL\n\nIngrese el motivo (mínimo 20 caracteres):');
    
    if (!motivo || motivo.length < 20) {
        alert('Motivo muy corto o cancelado');
        return;
    }
    
    if (!confirm('¿Confirma anular TODOS los títulos?\n\nESTO ES IRREVERSIBLE')) {
        return;
    }
    
    fetch(`/company/simple/webservices/micdta/${voyageId}/anular-titulo`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            titulo_id: 'ALL',
            motivo_anulacion: motivo,
            anular_todos: true
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ Títulos anulados. Recargando...');
            window.location.reload();
        } else {
            alert('❌ Error: ' + (result.error_message || result.error));
        }
    })
    .catch(e => alert('❌ Error: ' + e.message));
}

    /**
     * Cerrar modal
     */
    function closeAnularTituloModal() {
        document.getElementById('anularTituloModal').classList.add('hidden');
        document.getElementById('motivoAnulacion').value = '';
    }

    /**
     * Confirmar anulación TOTAL
     */
    async function confirmarAnulacionTotal() {
        const motivo = document.getElementById('motivoAnulacion').value.trim();

        if (!motivo || motivo.length < 20) {
            alert('⚠️ Debe proporcionar un motivo detallado (mínimo 20 caracteres)');
            document.getElementById('motivoAnulacion').focus();
            return;
        }

        if (!confirm('⚠️ PRIMERA CONFIRMACIÓN\n\n¿Está seguro de anular TODOS los títulos?\n\nEsta acción es IRREVERSIBLE.')) {
            return;
        }

        if (!confirm('⚠️ SEGUNDA CONFIRMACIÓN\n\nAl continuar:\n- Se cancelarán TODOS los TRACKs\n- Se invalidará el MIC/DTA\n- Deberá reiniciar desde cero\n\n¿Confirma la anulación TOTAL?')) {
            return;
        }

        if (!confirm('⚠️ CONFIRMACIÓN FINAL\n\nÚltima advertencia: Esta operación NO se puede deshacer.\n\n¿Proceder con la anulación?')) {
            return;
        }

        closeAnularTituloModal();

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/anular-titulo`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulo_id: 'ALL',
                    motivo_anulacion: motivo,
                    anular_todos: true,
                    force_send: false,
                    notes: `Anulación TOTAL desde interfaz - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            showResultModal('AnularTitulo', result, response.ok);

        } catch (error) {
            showResultModal('AnularTitulo', { 
                error: 'Error de comunicación: ' + error.message 
            }, false);
        }
    }

</script>