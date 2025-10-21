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
                // RegistrarTitEnvios - SOLO este método específico
                $lastTitEnvios = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarTitEnvios%')
                    ->where('soap_action', 'NOT LIKE', '%Anular%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->latest()
                    ->first();

                // RegistrarEnvios - SOLO este método específico
                $lastEnvios = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarEnvios%')
                    ->where('soap_action', 'NOT LIKE', '%RegistrarTitEnvios%')
                    ->where('soap_action', 'NOT LIKE', '%Anular%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->latest()
                    ->first();

                // RegistrarMicDta - SOLO este método específico
                $lastMicDta = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarMicDta%')
                    ->where('soap_action', 'NOT LIKE', '%RegistrarTitMicDta%')
                    ->where('soap_action', 'NOT LIKE', '%Anular%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->latest()
                    ->first();

                 // Obtener TRACKs activos (no expirados)
                $tracks = $voyage->webserviceTracks()
                    ->whereHas('webserviceTransaction', function($q) {
                        $q->where('webservice_type', 'micdta');
                    })
                    ->whereIn('webservice_tracks.status', ['generated', 'used_in_micdta', 'used_in_convoy', 'completed'])
                    ->latest()
                    ->get();
                
                // DEBUG: Ver datos reales
                $allTransactions = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->latest()
                    ->take(10)
                    ->get();

                // ===== GESTIÓN DE TÍTULOS (Botones 7, 8, 9) =====
                
                // Títulos vinculados a MIC/DTA (RegistrarTitMicDta)
                // Solo mostrar si NO hay un reset posterior
                $lastReset = $voyage->webserviceTransactions()
                    ->where('soap_action', 'like', '%RESET%')
                    ->orWhere('soap_action', 'like', '%AnularEnvios%')
                    ->latest()
                    ->first();
                
                $titulosVinculados = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarTitMicDta%')
                    ->where('soap_action', 'NOT LIKE', '%Desvincular%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        // Solo mostrar si es posterior al último reset
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
                
                // Contar títulos actualmente vinculados
                $countTitulosVinculados = 0;
                if ($titulosVinculados && isset($titulosVinculados->success_data['titulos_vinculados'])) {
                    $countTitulosVinculados = count($titulosVinculados->success_data['titulos_vinculados']);
                }
                
                // Última desvinculación (DesvincularTitMicDta)
                $lastDesvinculacion = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%DesvincularTitMicDta%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
                
                // Última anulación de título (AnularTitulo)
                $lastAnulacionTitulo = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%AnularTitulo%')
                    ->where('soap_action', 'NOT LIKE', '%AnularEnvios%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
            // ===== ZONA PRIMARIA (Botones 10, 11, 12) =====
                
                // Última salida de zona primaria (RegistrarSalidaZonaPrimaria)
                $lastSalidaZP = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarSalidaZonaPrimaria%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
                
                // Último arribo a zona primaria (RegistrarArriboZonaPrimaria)
                $lastArriboZP = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%RegistrarArriboZonaPrimaria%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
                
                // Última anulación de arribo (AnularArriboZonaPrimaria)
                $lastAnulacionArriboZP = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%AnularArriboZonaPrimaria%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->when($lastReset, function($q) use ($lastReset) {
                        return $q->where('created_at', '>', $lastReset->created_at);
                    })
                    ->latest()
                    ->first();
            // ===== CONSULTAS (Botones 13, 14, 15) =====
                
                // Última consulta de MIC/DTA asignados (ConsultarMicDtaAsig)
                $lastConsultaMicDta = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%ConsultarMicDtaAsig%')
                    ->whereIn('status', ['success', 'sent'])
                    ->latest()
                    ->first();
                
                // Última consulta de títulos registrados (ConsultarTitEnviosReg)
                $lastConsultaTitulos = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%ConsultarTitEnviosReg%')
                    ->whereIn('status', ['success', 'sent'])
                    ->latest()
                    ->first();
                
                // Última consulta de precumplido (ConsultarPrecumplido)
                $lastConsultaPrecumplido = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%ConsultarPrecumplido%')
                    ->whereIn('status', ['success', 'sent'])
                    ->latest()
                    ->first();
            // ===== ANULACIONES FINALES (Botones 16, 18) =====
                
                // Última solicitud de anulación MIC/DTA (SolicitarAnularMicDta)
                $lastSolicitudAnularMicDta = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%SolicitarAnularMicDta%')
                    ->whereIn('status', ['success', 'sent', 'pending'])
                    ->latest()
                    ->first();
                
                // Último test de conectividad (Dummy)
                $lastDummy = $voyage->webserviceTransactions()
                    ->where('webservice_type', 'micdta')
                    ->where('soap_action', 'like', '%Dummy%')
                    ->whereIn('status', ['success', 'sent'])
                    ->latest()
                    ->first();
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
                        // ===== GESTIÓN DE CONVOY (Botones 4, 5, 6) =====
                        // Solo aplica si es convoy voyage
                        
                        $lastConvoy = null;
                        $lastAsignarATA = null;
                        $lastRectifConvoy = null;
                        
                        if ($isConvoyVoyage) {
                            // Último convoy registrado (RegistrarConvoy)
                            $lastConvoy = $voyage->webserviceTransactions()
                                ->where('webservice_type', 'micdta')
                                ->where('soap_action', 'like', '%RegistrarConvoy%')
                                ->whereIn('status', ['success', 'sent', 'pending'])
                                ->when($lastReset ?? null, function($q) use ($lastReset) {
                                    return $q->where('created_at', '>', $lastReset->created_at);
                                })
                                ->latest()
                                ->first();
                            
                            // Última asignación de ATA/Remolcador (AsignarATARemol)
                            $lastAsignarATA = $voyage->webserviceTransactions()
                                ->where('webservice_type', 'micdta')
                                ->where('soap_action', 'like', '%AsignarATARemol%')
                                ->whereIn('status', ['success', 'sent', 'pending'])
                                ->when($lastReset ?? null, function($q) use ($lastReset) {
                                    return $q->where('created_at', '>', $lastReset->created_at);
                                })
                                ->latest()
                                ->first();
                            
                            // Última rectificación de convoy (RectifConvoyMicDta)
                            $lastRectifConvoy = $voyage->webserviceTransactions()
                                ->where('webservice_type', 'micdta')
                                ->where('soap_action', 'like', '%RectifConvoyMicDta%')
                                ->whereIn('status', ['success', 'sent', 'pending'])
                                ->when($lastReset ?? null, function($q) use ($lastReset) {
                                    return $q->where('created_at', '>', $lastReset->created_at);
                                })
                                ->latest()
                                ->first();
                        }
                    @endphp
                    <div class="border border-purple-200 rounded-lg p-4 bg-purple-50">
                        <h4 class="text-md font-semibold text-purple-900 mb-3">
                            🚢 Gestión de Convoy
                            @if (!$isConvoyVoyage)
                                <span class="ml-2 px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded">No Aplicable</span>
                            @endif
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            {{-- Botón 4: RegistrarConvoy --}}
                            <div class="flex flex-col">
                                @if ($isConvoyVoyage)
                                    <button onclick="executeAfipMethod('RegistrarConvoy')"
                                            class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                        <span class="text-2xl mb-2">⛴️</span>
                                        <span class="text-sm font-medium text-center">4. RegistrarConvoy</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Registra convoy</span>
                                    </button>
                                @else
                                    <button onclick="showConvoyNotApplicable('RegistrarConvoy')" disabled
                                            class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                        <span class="text-2xl mb-2">⛴️</span>
                                        <span class="text-sm font-medium text-center">4. RegistrarConvoy</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Registra convoy</span>
                                    </button>
                                @endif
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-purple-200 text-xs">
                                    @if($isConvoyVoyage && $lastConvoy)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Último registro:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastConvoy->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Convoy ID:</span>
                                            <span class="text-gray-700 font-mono text-xs truncate">
                                                {{ $lastConvoy->success_data['convoy_id'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastConvoy->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">
                                            {{ $isConvoyVoyage ? 'No registrado' : 'Solo convoy' }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 5: AsignarATARemol --}}
                            <div class="flex flex-col">
                                @if ($isConvoyVoyage)
                                    <button onclick="executeAfipMethod('AsignarATARemol')"
                                            class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                        <span class="text-2xl mb-2">🚤</span>
                                        <span class="text-sm font-medium text-center">5. AsignarATARemol</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Asigna remolcador</span>
                                    </button>
                                @else
                                    <button onclick="showConvoyNotApplicable('AsignarATARemol')" disabled
                                            class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                        <span class="text-2xl mb-2">🚤</span>
                                        <span class="text-sm font-medium text-center">5. AsignarATARemol</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Asigna remolcador</span>
                                    </button>
                                @endif
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-purple-200 text-xs">
                                    @if($isConvoyVoyage && $lastAsignarATA)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última asignación:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastAsignarATA->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Remolcador:</span>
                                            <span class="text-gray-700 font-mono text-xs truncate">
                                                {{ $lastAsignarATA->success_data['remolcador_id'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastAsignarATA->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">
                                            {{ $isConvoyVoyage ? 'No asignado' : 'Solo convoy' }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 6: RectifConvoyMicDta --}}
                            <div class="flex flex-col">
                                @if ($isConvoyVoyage)
                                    <button onclick="executeAfipMethod('RectifConvoyMicDta')"
                                            class="flex flex-col items-center justify-center p-4 bg-white border-2 border-purple-300 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-colors">
                                        <span class="text-2xl mb-2">✏️</span>
                                        <span class="text-sm font-medium text-center">6. RectifConvoyMicDta</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Rectifica convoy/MIC-DTA</span>
                                    </button>
                                @else
                                    <button onclick="showConvoyNotApplicable('RectifConvoyMicDta')" disabled
                                            class="flex flex-col items-center justify-center p-4 bg-gray-100 border-2 border-gray-300 rounded-lg cursor-not-allowed opacity-60">
                                        <span class="text-2xl mb-2">✏️</span>
                                        <span class="text-sm font-medium text-center">6. RectifConvoyMicDta</span>
                                        <span class="text-xs text-gray-600 text-center mt-1">Rectifica convoy/MIC-DTA</span>
                                    </button>
                                @endif
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-purple-200 text-xs">
                                    @if($isConvoyVoyage && $lastRectifConvoy)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última rectificación:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastRectifConvoy->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Campo:</span>
                                            <span class="text-gray-700 text-xs truncate">
                                                {{ $lastRectifConvoy->success_data['campo'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastRectifConvoy->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">
                                            {{ $isConvoyVoyage ? 'No rectificado' : 'Solo convoy' }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- GRUPO 3: GESTIÓN TÍTULOS (7-9) --}}
                    <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                        <h4 class="text-md font-semibold text-green-900 mb-3">
                            📑 Gestión de Títulos
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            {{-- Botón 7: RegistrarTitMicDta --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('RegistrarTitMicDta')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                    <span class="text-2xl mb-2">📝</span>
                                    <span class="text-sm font-medium text-center">7. RegistrarTitMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Vincula títulos a MIC/DTA</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-green-200 text-xs">
                                    @if($titulosVinculados)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última vinculación:</span>
                                            <span class="text-gray-900 font-medium">{{ $titulosVinculados->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Títulos vinculados:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ $countTitulosVinculados }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold
                                                @if($titulosVinculados->status === 'success') bg-green-100 text-green-800
                                                @elseif($titulosVinculados->status === 'sent') bg-blue-100 text-blue-800
                                                @else bg-gray-100 text-gray-800 @endif">
                                                {{ ucfirst($titulosVinculados->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No vinculado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 8: DesvincularTitMicDta --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('DesvincularTitMicDta')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                    <span class="text-2xl mb-2">🔗</span>
                                    <span class="text-sm font-medium text-center">8. DesvincularTitMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Desvincula títulos</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-green-200 text-xs">
                                    @if($lastDesvinculacion)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última desvinculación:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastDesvinculacion->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Títulos desvinculados:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">
                                                {{ isset($lastDesvinculacion->success_data['titulos_desvinculados']) ? count($lastDesvinculacion->success_data['titulos_desvinculados']) : '0' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastDesvinculacion->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">Sin desvinculaciones</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 9: AnularTitulo --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('AnularTitulo')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                    <span class="text-2xl mb-2">❌</span>
                                    <span class="text-sm font-medium text-center">9. AnularTitulo</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Anula título de transporte</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-green-200 text-xs">
                                    @if($lastAnulacionTitulo)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última anulación:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastAnulacionTitulo->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Título anulado:</span>
                                            <span class="text-gray-700 font-mono text-xs truncate">
                                                {{ $lastAnulacionTitulo->success_data['titulo_anulado'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                                Anulado
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">Sin anulaciones</p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- GRUPO 4: ZONA PRIMARIA (10-12) --}}
                    <div class="border border-orange-200 rounded-lg p-4 bg-orange-50">
                        <h4 class="text-md font-semibold text-orange-900 mb-3">
                            🏢 Zona Primaria
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            {{-- Botón 10: RegistrarSalidaZonaPrimaria --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('RegistrarSalidaZonaPrimaria')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                    <span class="text-2xl mb-2">🚪</span>
                                    <span class="text-sm font-medium text-center">10. RegistrarSalida</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Salida zona primaria</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-orange-200 text-xs">
                                    @if($lastSalidaZP)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última salida:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastSalidaZP->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Nro. Salida:</span>
                                            <span class="text-gray-700 font-mono text-xs">
                                                {{ $lastSalidaZP->success_data['nro_salida'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold
                                                @if($lastSalidaZP->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastSalidaZP->status === 'sent') bg-blue-100 text-blue-800
                                                @else bg-gray-100 text-gray-800 @endif">
                                                {{ ucfirst($lastSalidaZP->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No registrado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 11: RegistrarArriboZonaPrimaria --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('RegistrarArriboZonaPrimaria')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                    <span class="text-2xl mb-2">🛬</span>
                                    <span class="text-sm font-medium text-center">11. RegistrarArribo</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Arribo zona primaria</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-orange-200 text-xs">
                                    @if($lastArriboZP)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Último arribo:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastArriboZP->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Nro. Viaje:</span>
                                            <span class="text-gray-700 font-mono text-xs">
                                                {{ $lastArriboZP->success_data['nro_viaje'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastArriboZP->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No registrado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 12: AnularArriboZonaPrimaria --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('AnularArriboZonaPrimaria')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-orange-300 rounded-lg hover:bg-orange-100 hover:border-orange-400 transition-colors">
                                    <span class="text-2xl mb-2">🚫</span>
                                    <span class="text-sm font-medium text-center">12. AnularArribo</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Anula arribo zona primaria</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-orange-200 text-xs">
                                    @if($lastAnulacionArriboZP)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última anulación:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastAnulacionArriboZP->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Motivo:</span>
                                            <span class="text-gray-700 text-xs truncate">
                                                {{ $lastAnulacionArriboZP->success_data['motivo'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                                Anulado
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">Sin anulaciones</p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- GRUPO 5: CONSULTAS (13-15) --}}
                    <div class="border border-indigo-200 rounded-lg p-4 bg-indigo-50">
                        <h4 class="text-md font-semibold text-indigo-900 mb-3">
                            🔍 Consultas
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            {{-- Botón 13: ConsultarMicDtaAsig --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('ConsultarMicDtaAsig')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                    <span class="text-2xl mb-2">🔎</span>
                                    <span class="text-sm font-medium text-center">13. ConsultarMicDtaAsig</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">MIC/DTA asignados</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-indigo-200 text-xs">
                                    @if($lastConsultaMicDta)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última consulta:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastConsultaMicDta->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Resultados:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                {{ $lastConsultaMicDta->success_data['total'] ?? '0' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastConsultaMicDta->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No consultado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 14: ConsultarTitEnviosReg --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('ConsultarTitEnviosReg')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                    <span class="text-2xl mb-2">📊</span>
                                    <span class="text-sm font-medium text-center">14. ConsultarTitEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Títulos registrados</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-indigo-200 text-xs">
                                    @if($lastConsultaTitulos)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última consulta:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastConsultaTitulos->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Títulos:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                {{ $lastConsultaTitulos->success_data['total'] ?? '0' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                {{ ucfirst($lastConsultaTitulos->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No consultado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 15: ConsultarPrecumplido --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('ConsultarPrecumplido')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-indigo-300 rounded-lg hover:bg-indigo-100 hover:border-indigo-400 transition-colors">
                                    <span class="text-2xl mb-2">✅</span>
                                    <span class="text-sm font-medium text-center">15. ConsultarPrecumplido</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Estado precumplido</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-indigo-200 text-xs">
                                    @if($lastConsultaPrecumplido)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última consulta:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastConsultaPrecumplido->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold
                                                @if(isset($lastConsultaPrecumplido->success_data['precumplido']) && $lastConsultaPrecumplido->success_data['precumplido']) 
                                                    bg-green-100 text-green-800
                                                @else 
                                                    bg-yellow-100 text-yellow-800
                                                @endif">
                                                {{ isset($lastConsultaPrecumplido->success_data['precumplido']) && $lastConsultaPrecumplido->success_data['precumplido'] ? 'Precumplido' : 'Pendiente' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Consulta:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                                                Success
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No consultado</p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- GRUPO 6: ANULACIONES + TESTING (16-18) --}}
                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                        <h4 class="text-md font-semibold text-red-900 mb-3">
                            🗑️ Anulaciones y Testing
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            
                            {{-- Botón 16: SolicitarAnularMicDta --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('SolicitarAnularMicDta')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                    <span class="text-2xl mb-2">🗂️</span>
                                    <span class="text-sm font-medium text-center">16. SolicitarAnular</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Anula MIC/DTA</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-red-200 text-xs">
                                    @if($lastSolicitudAnularMicDta)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Última solicitud:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastSolicitudAnularMicDta->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">MIC/DTA:</span>
                                            <span class="text-gray-700 font-mono text-xs truncate">
                                                {{ $lastSolicitudAnularMicDta->success_data['micdta_id'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                                {{ ucfirst($lastSolicitudAnularMicDta->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No solicitado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 17: AnularEnvios (RESET TOTAL) --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('AnularEnvios')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                    <span class="text-2xl mb-2">📮</span>
                                    <span class="text-sm font-medium text-center">17. AnularEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">RESET TOTAL</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-red-200 text-xs">
                                    @if($lastReset)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Último reset:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastReset->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Transacciones:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                                {{ $lastReset->success_data['transacciones_anuladas'] ?? '0' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                                Reseteado
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No reseteado</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Botón 18: Dummy (Test) --}}
                            <div class="flex flex-col">
                                <button onclick="executeAfipMethod('Dummy')"
                                        class="flex flex-col items-center justify-center p-4 bg-white border-2 border-red-300 rounded-lg hover:bg-red-100 hover:border-red-400 transition-colors">
                                    <span class="text-2xl mb-2">🧪</span>
                                    <span class="text-sm font-medium text-center">18. Dummy</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Test conectividad</span>
                                </button>
                                
                                {{-- Info al pie --}}
                                <div class="mt-2 p-2 bg-white rounded border border-red-200 text-xs">
                                    @if($lastDummy)
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Último test:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastDummy->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-gray-600">Conectividad:</span>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold
                                                @if($lastDummy->status === 'success') bg-green-100 text-green-800
                                                @else bg-red-100 text-red-800 @endif">
                                                {{ $lastDummy->status === 'success' ? 'OK' : 'Error' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Latencia:</span>
                                            <span class="text-gray-700 text-xs">
                                                {{ $lastDummy->success_data['response_time'] ?? 'N/A' }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-gray-500 text-center">No testeado</p>
                                    @endif
                                </div>
                            </div>

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

    {{-- ========================================================================
    MODAL: DESVINCULAR TÍTULOS
    ======================================================================== --}}
    <div id="desvincular-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        🔗 Desvincular Títulos de MIC/DTA
                    </h3>
                    <button onclick="closeDesvincularModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="mt-4">
                    <div id="desvincular-loading" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Cargando títulos vinculados...</p>
                    </div>

                    <div id="desvincular-content" class="hidden">
                        <p class="text-sm text-gray-600 mb-4">
                            Seleccione los títulos que desea desvincular del MIC/DTA:
                        </p>

                        <div id="desvincular-error" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-700"></p>
                        </div>

                        <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md p-4">
                            <div id="titulos-list" class="space-y-2">
                                {{-- Se llenará dinámicamente con JavaScript --}}
                            </div>
                        </div>

                        <div class="mt-4 flex items-center">
                            <input type="checkbox" id="select-all-titulos" class="h-4 w-4 text-blue-600 rounded" onchange="toggleSelectAllTitulos()">
                            <label for="select-all-titulos" class="ml-2 text-sm text-gray-700">
                                Seleccionar todos
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                    <button onclick="closeDesvincularModal()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button onclick="confirmarDesvincular()" 
                            id="btn-confirmar-desvincular"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Desvincular Seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================
        MODAL: ANULAR TODO (RESET COMPLETO)
        ======================================================================== --}}
    <div id="anular-todo-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-red-900">
                        ⚠️ RESETEAR VIAJE A CERO
                    </h3>
                    <button onclick="closeAnularTodoModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="mt-4">
                    <div id="anular-loading" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-red-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Verificando operaciones registradas...</p>
                    </div>

                    <div id="anular-content" class="hidden">
                        {{-- Advertencia --}}
                        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <strong>ADVERTENCIA:</strong> Esta acción anulará TODAS las operaciones enviadas a AFIP:
                                    </p>
                                    <ul class="mt-2 text-sm text-red-600 list-disc list-inside space-y-1">
                                        <li>RegistrarTitEnvios</li>
                                        <li>RegistrarEnvios</li>
                                        <li>RegistrarMicDta</li>
                                        <li>Convoy, Títulos vinculados, Zona Primaria, etc.</li>
                                    </ul>
                                    <p class="mt-2 text-sm text-red-700 font-semibold">
                                        El viaje volverá a estado inicial (fojas cero).
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Información de operaciones --}}
                        <div class="bg-gray-50 border border-gray-200 rounded-md p-4 mb-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Operaciones registradas:</h4>
                            <div id="operaciones-info" class="text-sm text-gray-700 space-y-1">
                                {{-- Se llenará dinámicamente --}}
                            </div>
                        </div>

                        {{-- Campo motivo --}}
                        <div class="mb-4">
                            <label for="motivo-anulacion" class="block text-sm font-medium text-gray-700 mb-2">
                                Motivo de anulación <span class="text-red-500">*</span>
                            </label>
                            <textarea id="motivo-anulacion" 
                                    rows="3" 
                                    maxlength="200"
                                    placeholder="Indique el motivo por el cual desea resetear el viaje..."
                                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500">Máximo 200 caracteres</p>
                        </div>

                        {{-- Confirmación final --}}
                        <div class="flex items-start">
                            <input type="checkbox" id="confirm-reset" class="mt-1 h-4 w-4 text-red-600 rounded">
                            <label for="confirm-reset" class="ml-2 text-sm text-gray-700">
                                Confirmo que deseo <strong class="text-red-600">RESETEAR TODO</strong> y entiendo que esta acción no se puede deshacer
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                    <button onclick="closeAnularTodoModal()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button onclick="confirmarAnularTodo()" 
                            id="btn-confirmar-anular"
                            disabled
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        RESETEAR TODO
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================
        MODAL: ANULAR TÍTULO INDIVIDUAL
        ======================================================================== --}}
    <div id="anular-titulo-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        ❌ Anular Título Individual
                    </h3>
                    <button onclick="closeAnularTituloModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="mt-4">
                    <div id="anular-titulo-loading" class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Cargando títulos registrados...</p>
                    </div>

                    <div id="anular-titulo-content" class="hidden">
                        <div id="anular-titulo-error" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-700"></p>
                        </div>

                        <p class="text-sm text-gray-600 mb-4">
                            Seleccione el título que desea anular:
                        </p>

                        <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md p-4 mb-4">
                            <div id="titulos-anular-list" class="space-y-2">
                                {{-- Se llenará dinámicamente --}}
                            </div>
                        </div>

                        {{-- Campo motivo --}}
                        <div class="mb-4">
                            <label for="motivo-anular-titulo" class="block text-sm font-medium text-gray-700 mb-2">
                                Motivo de anulación <span class="text-red-500">*</span>
                            </label>
                            <textarea id="motivo-anular-titulo" 
                                    rows="3" 
                                    maxlength="200"
                                    placeholder="Indique el motivo por el cual desea anular este título..."
                                    class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500">Máximo 200 caracteres</p>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                    <button onclick="closeAnularTituloModal()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button onclick="confirmarAnularTitulo()" 
                            id="btn-confirmar-anular-titulo"
                            disabled
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Anular Título
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

        if (methodName === 'DesvincularTitMicDta') {
            showDesvincularModal();
            return;
        }

        if (methodName === 'AnularEnvios') {
            showAnularTodoModal();
            return;
        }

        if (methodName === 'AnularTitulo') {
            showAnularTituloModal();
            return;
        }

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

    // ========================================================================
    // MODAL DESVINCULAR TÍTULOS
    // ========================================================================

    let titulosVinculados = [];

    async function showDesvincularModal() {
        const modal = document.getElementById('desvincular-modal');
        const loading = document.getElementById('desvincular-loading');
        const content = document.getElementById('desvincular-content');
        const errorDiv = document.getElementById('desvincular-error');
        
        // Mostrar modal con loading
        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        errorDiv.classList.add('hidden');
        
        try {
            // Obtener títulos vinculados vía AJAX
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-vinculados`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (result.success && result.titulos && result.titulos.length > 0) {
                titulosVinculados = result.titulos;
                renderTitulosList(result.titulos);
                
                loading.classList.add('hidden');
                content.classList.remove('hidden');
            } else {
                throw new Error(result.error || 'No hay títulos vinculados');
            }
            
        } catch (error) {
            loading.classList.add('hidden');
            errorDiv.classList.remove('hidden');
            errorDiv.querySelector('p').textContent = error.message || 'Error al cargar títulos vinculados';
        }
    }

    function renderTitulosList(titulos) {
        const container = document.getElementById('titulos-list');
        container.innerHTML = '';
        
        titulos.forEach((titulo, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center p-2 hover:bg-gray-50 rounded';
            div.innerHTML = `
                <input type="checkbox" 
                    id="titulo-${index}" 
                    value="${titulo}" 
                    class="titulo-checkbox h-4 w-4 text-blue-600 rounded"
                    onchange="updateDesvincularButton()">
                <label for="titulo-${index}" class="ml-2 text-sm text-gray-900 font-mono cursor-pointer flex-1">
                    ${titulo}
                </label>
            `;
            container.appendChild(div);
        });
    }

    function toggleSelectAllTitulos() {
        const selectAll = document.getElementById('select-all-titulos');
        const checkboxes = document.querySelectorAll('.titulo-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        
        updateDesvincularButton();
    }

    function updateDesvincularButton() {
        const checkboxes = document.querySelectorAll('.titulo-checkbox:checked');
        const button = document.getElementById('btn-confirmar-desvincular');
        
        button.disabled = checkboxes.length === 0;
        button.textContent = checkboxes.length > 0 
            ? `Desvincular ${checkboxes.length} Título(s)` 
            : 'Desvincular Seleccionados';
    }

    async function confirmarDesvincular() {
        const checkboxes = document.querySelectorAll('.titulo-checkbox:checked');
        const titulosSeleccionados = Array.from(checkboxes).map(cb => cb.value);
        
        if (titulosSeleccionados.length === 0) {
            alert('Debe seleccionar al menos un título');
            return;
        }
        
        if (!confirm(`¿Confirma desvincular ${titulosSeleccionados.length} título(s)?`)) {
            return;
        }
        
        const button = document.getElementById('btn-confirmar-desvincular');
        const originalText = button.textContent;
        button.disabled = true;
        button.innerHTML = `<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>`;
        
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/desvincular-tit-micdta`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulos: titulosSeleccionados
                })
            });
            
            const result = await response.json();
            closeDesvincularModal();
            showResultModal('DesvincularTitMicDta', result, response.ok);
            
            // Recargar página si fue exitoso
            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }
            
        } catch (error) {
            showResultModal('DesvincularTitMicDta', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function closeDesvincularModal() {
        document.getElementById('desvincular-modal').classList.add('hidden');
        titulosVinculados = [];
    }

    // ========================================================================
    // MODAL ANULAR TODO (RESET)
    // ========================================================================

    async function showAnularTodoModal() {
        const modal = document.getElementById('anular-todo-modal');
        const loading = document.getElementById('anular-loading');
        const content = document.getElementById('anular-content');
        
        // Mostrar modal con loading
        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        
        try {
            // Obtener información de operaciones registradas
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-registrados`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                renderOperacionesInfo(result);
                loading.classList.add('hidden');
                content.classList.remove('hidden');
            } else {
                throw new Error(result.error || 'Error al verificar operaciones');
            }
            
        } catch (error) {
            loading.classList.add('hidden');
            alert('Error al cargar información: ' + error.message);
            closeAnularTodoModal();
        }
    }

    function renderOperacionesInfo(data) {
        const container = document.getElementById('operaciones-info');
        
        let html = `<p><strong>Títulos registrados:</strong> ${data.count || 0}</p>`;
        
        if (data.titulos && data.titulos.length > 0) {
            html += `<ul class="mt-2 list-disc list-inside text-xs text-gray-600">`;
            data.titulos.forEach(titulo => {
                html += `<li>${titulo}</li>`;
            });
            html += `</ul>`;
        }
        
        container.innerHTML = html;
        
        // Habilitar checkbox de confirmación
        const checkbox = document.getElementById('confirm-reset');
        checkbox.checked = false;
        checkbox.onchange = function() {
            const button = document.getElementById('btn-confirmar-anular');
            const motivo = document.getElementById('motivo-anulacion').value.trim();
            button.disabled = !(this.checked && motivo.length > 0);
        };
        
        // Habilitar validación de motivo
        document.getElementById('motivo-anulacion').oninput = function() {
            const checkbox = document.getElementById('confirm-reset');
            const button = document.getElementById('btn-confirmar-anular');
            button.disabled = !(checkbox.checked && this.value.trim().length > 0);
        };
    }

    async function confirmarAnularTodo() {
        const motivo = document.getElementById('motivo-anulacion').value.trim();
        
        if (!motivo) {
            alert('Debe indicar el motivo de anulación');
            return;
        }
        
        if (!document.getElementById('confirm-reset').checked) {
            alert('Debe confirmar la acción');
            return;
        }
        
        if (!confirm('⚠️ ÚLTIMA ADVERTENCIA\n\nEsta acción RESETEARÁ TODO el viaje a estado inicial.\n\n¿Está SEGURO de continuar?')) {
            return;
        }
        
        const button = document.getElementById('btn-confirmar-anular');
        const originalText = button.textContent;
        button.disabled = true;
        button.innerHTML = `<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>`;
        
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/anular-envios`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    anular_todos: true,
                    motivo_anulacion: motivo,
                    envios_ids: [],
                    tracks: []
                })
            });
            
            const result = await response.json();
            closeAnularTodoModal();
            showResultModal('AnularTodo', result, response.ok);
            
            // Recargar página si fue exitoso
            if (result.success) {
                setTimeout(() => location.reload(), 3000);
            }
            
        } catch (error) {
            showResultModal('AnularTodo', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function closeAnularTodoModal() {
        document.getElementById('anular-todo-modal').classList.add('hidden');
        document.getElementById('motivo-anulacion').value = '';
        document.getElementById('confirm-reset').checked = false;
    }

    // ========================================================================
    // MODAL ANULAR TÍTULO INDIVIDUAL
    // ========================================================================

    let tituloSeleccionado = null;

    async function showAnularTituloModal() {
        const modal = document.getElementById('anular-titulo-modal');
        const loading = document.getElementById('anular-titulo-loading');
        const content = document.getElementById('anular-titulo-content');
        const errorDiv = document.getElementById('anular-titulo-error');
        
        // Mostrar modal con loading
        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        errorDiv.classList.add('hidden');
        
        try {
            // Obtener títulos registrados vía AJAX
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-registrados`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (result.success && result.titulos && result.titulos.length > 0) {
                renderTitulosAnularList(result.titulos);
                loading.classList.add('hidden');
                content.classList.remove('hidden');
            } else {
                throw new Error(result.error || 'No hay títulos registrados para anular');
            }
            
        } catch (error) {
            loading.classList.add('hidden');
            errorDiv.classList.remove('hidden');
            errorDiv.querySelector('p').textContent = error.message || 'Error al cargar títulos registrados';
        }
    }

    function renderTitulosAnularList(titulos) {
        const container = document.getElementById('titulos-anular-list');
        container.innerHTML = '';
        
        titulos.forEach((titulo, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center p-3 hover:bg-gray-50 rounded border border-gray-200 cursor-pointer';
            div.onclick = function() { selectTituloAnular(titulo, div); };
            div.innerHTML = `
                <input type="radio" 
                    name="titulo-anular" 
                    id="titulo-anular-${index}" 
                    value="${titulo}" 
                    class="h-4 w-4 text-red-600">
                <label for="titulo-anular-${index}" class="ml-3 text-sm text-gray-900 font-mono flex-1 cursor-pointer">
                    ${titulo}
                </label>
            `;
            container.appendChild(div);
        });
    }

    function selectTituloAnular(titulo, element) {
        // Deseleccionar todos
        document.querySelectorAll('#titulos-anular-list > div').forEach(div => {
            div.classList.remove('bg-red-50', 'border-red-300');
            div.classList.add('border-gray-200');
        });
        
        // Seleccionar este
        element.classList.add('bg-red-50', 'border-red-300');
        element.classList.remove('border-gray-200');
        element.querySelector('input[type="radio"]').checked = true;
        
        tituloSeleccionado = titulo;
        updateAnularTituloButton();
    }

    function updateAnularTituloButton() {
        const button = document.getElementById('btn-confirmar-anular-titulo');
        const motivo = document.getElementById('motivo-anular-titulo').value.trim();
        
        button.disabled = !(tituloSeleccionado && motivo.length > 0);
    }

    // Listener para el campo motivo
    document.addEventListener('DOMContentLoaded', function() {
        const motivoField = document.getElementById('motivo-anular-titulo');
        if (motivoField) {
            motivoField.addEventListener('input', updateAnularTituloButton);
        }
    });

    async function confirmarAnularTitulo() {
        const motivo = document.getElementById('motivo-anular-titulo').value.trim();
        
        if (!tituloSeleccionado) {
            alert('Debe seleccionar un título');
            return;
        }
        
        if (!motivo) {
            alert('Debe indicar el motivo de anulación');
            return;
        }
        
        if (!confirm(`¿Confirma anular el título "${tituloSeleccionado}"?`)) {
            return;
        }
        
        const button = document.getElementById('btn-confirmar-anular-titulo');
        const originalText = button.textContent;
        button.disabled = true;
        button.innerHTML = `<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>`;
        
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/anular-titulo`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    titulo_id: tituloSeleccionado,
                    motivo_anulacion: motivo
                })
            });
            
            const result = await response.json();
            closeAnularTituloModal();
            showResultModal('AnularTitulo', result, response.ok);
            
            // Recargar página si fue exitoso
            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }
            
        } catch (error) {
            showResultModal('AnularTitulo', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function closeAnularTituloModal() {
        document.getElementById('anular-titulo-modal').classList.add('hidden');
        document.getElementById('motivo-anular-titulo').value = '';
        tituloSeleccionado = null;
    }

</script>