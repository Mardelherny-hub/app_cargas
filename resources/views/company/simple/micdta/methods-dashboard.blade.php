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

            {{-- Embarcaciones del Viaje --}}
            @if($voyage->shipments->count() > 0)
                <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">🚢 Embarcaciones del Viaje ({{ $voyage->shipments->count() }})</h3>
                    <div class="flex flex-wrap gap-3">
                        @foreach($voyage->shipments as $shipment)
                            @php
                                $sv = $shipment->vessel;
                                $tipEmb = $sv && $sv->vesselType ? strtoupper($sv->vesselType->code) : 'N/A';
                                $esBarcazaShip = in_array($tipEmb, ['BARGE_STD_001', 'BAR', 'BARCAZA']);
                                
                                $lastMicDtaShip = $voyage->webserviceTransactions()
                                    ->where('shipment_id', $shipment->id)
                                    ->where('soap_action', 'like', '%RegistrarMicDta%')
                                    ->latest()
                                    ->first();
                                $micDtaStatus = $lastMicDtaShip ? $lastMicDtaShip->status : null;
                            @endphp
                            <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm
                                {{ $micDtaStatus === 'success' ? 'border-green-300 bg-green-50' : ($micDtaStatus === 'error' ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50') }}">
                                <span class="font-medium">{{ $sv->name ?? 'N/A' }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $esBarcazaShip ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                    {{ $esBarcazaShip ? 'BAR' : 'AUT' }}
                                </span>
                                @if($micDtaStatus === 'success')
                                    <span class="text-green-600">✅</span>
                                @elseif($micDtaStatus === 'error')
                                    <span class="text-red-600" title="{{ $lastMicDtaShip->error_message }}">❌</span>
                                @else
                                    <span class="text-gray-400">⏳</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                
                // DEBUG: Ver datos reales (incluye todos los tipos MIC/DTA relacionados)
                $allTransactions = $voyage->webserviceTransactions()
                    ->whereIn('webservice_type', ['micdta', 'anular_micdta', 'convoy', 'ata_remolcador', 'salida_zona_primaria'])
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
                            
                            {{-- ============================================ --}}
                            {{-- TARJETA 1: RegistrarTitEnvios (MEJORADA) --}}
                            {{-- ============================================ --}}
                            @php
                                // Contar Shipments del voyage
                                $shipmentsCount = $voyage->shipments->count();
                                
                                // Contar BLs totales
                                $allBillsOfLading = $voyage->shipments->flatMap->billsOfLading;
                                $blsTotales = $allBillsOfLading->count();
                                
                                // Verificar datos obligatorios para RegistrarTitEnvios
                                // - Cada BL necesita: shipper, consignee, permiso_embarque
                                $blsConShipper = $allBillsOfLading->filter(fn($bl) => $bl->shipper_id !== null)->count();
                                $blsConConsignee = $allBillsOfLading->filter(fn($bl) => $bl->consignee_id !== null)->count();
                                $blsConPermiso = $allBillsOfLading->filter(fn($bl) => !empty($bl->permiso_embarque))->count();
                                
                                // Verificar embarcación
                                $tieneEmbarcacion = $voyage->leadVessel !== null;
                                
                                // Verificar puertos
                                $tienePuertos = $voyage->originPort !== null && $voyage->destinationPort !== null;
                                
                                // Requisitos completos
                                $requisitosCompletos = $shipmentsCount > 0 
                                    && $blsTotales > 0 
                                    && $blsConShipper === $blsTotales 
                                    && $blsConConsignee === $blsTotales
                                    && $tieneEmbarcacion
                                    && $tienePuertos;
                                
                                // TRACKs específicos generados por RegistrarTitEnvios
                                $tracksTitEnvios = $voyage->webserviceTracks()
                                    ->where('webservice_tracks.webservice_method', 'RegistrarTitEnvios')
                                    ->whereIn('webservice_tracks.status', ['generated', 'used_in_micdta', 'used_in_convoy', 'completed'])
                                    ->get();
                                
                                // Obtener idTitTrans si existe
                                $idTitTransRegistrado = null;
                                if ($lastTitEnvios && $lastTitEnvios->success_data) {
                                    $successData = is_array($lastTitEnvios->success_data) 
                                        ? $lastTitEnvios->success_data 
                                        : json_decode($lastTitEnvios->success_data, true);
                                    $idTitTransRegistrado = $successData['id_tit_trans'] ?? $successData['idTitTrans'] ?? null;
                                }
                            @endphp

                            <div class="flex flex-col bg-white border-2 {{ $requisitosCompletos ? 'border-blue-300' : 'border-yellow-300' }} rounded-lg overflow-hidden">
                                {{-- Botón principal --}}
                                <button onclick="executeAfipMethod('RegistrarTitEnvios')"
                                        class="flex flex-col items-center justify-center p-4 hover:bg-blue-50 transition-colors">
                                    <span class="text-2xl mb-2">📋</span>
                                    <span class="text-sm font-medium text-center">1. RegistrarTitEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Registra títulos de transporte</span>
                                </button>
                                
                                {{-- Panel de información --}}
                                <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs space-y-1">
                                    
                                    {{-- Datos del Voyage --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Shipments:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $shipmentsCount > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $shipmentsCount }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Bills of Lading:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $blsTotales > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $blsTotales }}
                                        </span>
                                    </div>
                                    
                                    {{-- Requisitos de datos --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Embarcación:</span>
                                        @if($tieneEmbarcacion)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                ✓ {{ Str::limit($voyage->leadVessel->name ?? 'Sí', 12) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Falta
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Puertos:</span>
                                        @if($tienePuertos)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Configurados
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Faltan
                                            </span>
                                        @endif
                                    </div>
                                    
                                    {{-- Datos de BLs --}}
                                    @if($blsTotales > 0)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">BLs c/Shipper:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $blsConShipper === $blsTotales ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $blsConShipper }}/{{ $blsTotales }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">BLs c/Consignee:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $blsConConsignee === $blsTotales ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $blsConConsignee }}/{{ $blsTotales }}
                                            </span>
                                        </div>
                                    @endif
                                    
                                    {{-- Separador si hay historial --}}
                                    @if($lastTitEnvios)
                                        <hr class="border-gray-200 my-1">
                                    @endif
                                    
                                    {{-- Historial de último envío --}}
                                    @if($lastTitEnvios)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastTitEnvios->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastTitEnvios->status === 'sent' || $lastTitEnvios->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastTitEnvios->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastTitEnvios->status) }}
                                            </span>
                                        </div>
                                        
                                        {{-- ID del Título registrado --}}
                                        @if($idTitTransRegistrado)
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">idTitTrans:</span>
                                                <span class="text-gray-900 font-mono text-xs truncate max-w-[120px]" title="{{ $idTitTransRegistrado }}">
                                                    {{ Str::limit($idTitTransRegistrado, 15) }}
                                                </span>
                                            </div>
                                        @endif
                                        
                                        {{-- TRACKs generados --}}
                                        @if($tracksTitEnvios->isNotEmpty())
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">TRACKs:</span>
                                                <span class="text-green-600 font-semibold">{{ $tracksTitEnvios->count() }} generados</span>
                                            </div>
                                        @endif
                                        
                                        {{-- Mostrar error/warning si hubo --}}
                                        @if($lastTitEnvios->status === 'error' && $lastTitEnvios->error_message)
                                            <div class="mt-1 p-1.5 bg-red-50 rounded text-xs text-red-700 truncate" title="{{ $lastTitEnvios->error_message }}">
                                                ⚠️ {{ Str::limit($lastTitEnvios->error_message, 50) }}
                                            </div>
                                        @elseif($lastTitEnvios->status === 'sent' && $lastTitEnvios->error_message)
                                            <div class="mt-1 p-1.5 bg-orange-50 border-l-2 border-orange-400 rounded text-xs text-orange-700">
                                                ⚠️ {{ Str::limit($lastTitEnvios->error_message, 50) }}
                                            </div>
                                        @endif
                                    @endif
                                    
                                    {{-- Mensaje de ayuda si faltan requisitos --}}
                                    @if(!$requisitosCompletos && !$lastTitEnvios)
                                        <div class="mt-1 p-1.5 bg-yellow-50 rounded text-xs text-yellow-700">
                                            💡 Verifique: 
                                            @if($shipmentsCount === 0) Shipments @endif
                                            @if($blsTotales === 0) BLs @endif
                                            @if(!$tieneEmbarcacion) Embarcación @endif
                                            @if(!$tienePuertos) Puertos @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- ============================================ --}}
                            {{-- TARJETA 2: RegistrarEnvios (MEJORADA) --}}
                            {{-- ============================================ --}}
                            @php
                                // Verificar si hay título registrado (requisito previo)
                                $hasTituloRegistrado = $lastTitEnvios && in_array($lastTitEnvios->status, ['success', 'sent']);
                                
                                // Obtener idTitTrans del título registrado (de la respuesta AFIP)
                                $idTitTrans = null;
                                if ($hasTituloRegistrado && $lastTitEnvios->success_data) {
                                    $successData = is_array($lastTitEnvios->success_data) 
                                        ? $lastTitEnvios->success_data 
                                        : json_decode($lastTitEnvios->success_data, true);
                                    $idTitTrans = $successData['id_tit_trans'] ?? $successData['idTitTrans'] ?? null;
                                }
                                
                                // Contar BLs con id_decla (campo obligatorio para RegistrarEnvios)
                                $allBillsOfLading = $voyage->shipments->flatMap->billsOfLading;
                                $blsConIdDecla = $allBillsOfLading->filter(fn($bl) => !empty($bl->id_decla))->count();
                                $blsTotales = $allBillsOfLading->count();
                                $blsListos = $blsConIdDecla === $blsTotales && $blsTotales > 0;
                                
                                // TRACKs específicos generados por RegistrarEnvios
                                $tracksEnvios = $voyage->webserviceTracks()
                                    ->where('webservice_tracks.webservice_method', 'RegistrarEnvios')
                                    ->whereIn('webservice_tracks.status', ['generated', 'used_in_micdta', 'used_in_convoy', 'completed'])
                                    ->get();
                                
                                // Determinar si el botón debe estar habilitado
                                $puedeEjecutarEnvios = $hasTituloRegistrado && $blsListos;
                            @endphp

                            <div class="flex flex-col bg-white border-2 {{ $puedeEjecutarEnvios ? 'border-blue-300' : 'border-gray-300' }} rounded-lg overflow-hidden">
                                {{-- Botón principal --}}
                                <button onclick="{{ $puedeEjecutarEnvios ? "executeAfipMethod('RegistrarEnvios')" : "showRequisitosPrevios('RegistrarEnvios')" }}"
                                        class="flex flex-col items-center justify-center p-4 {{ $puedeEjecutarEnvios ? 'hover:bg-blue-50' : 'bg-gray-50 cursor-not-allowed' }} transition-colors">
                                    <span class="text-2xl mb-2">📦</span>
                                    <span class="text-sm font-medium text-center {{ !$puedeEjecutarEnvios ? 'text-gray-500' : '' }}">2. RegistrarEnvios</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Agrega envíos a título existente</span>
                                </button>
                                
                                {{-- Panel de información --}}
                                <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs space-y-1">
                                    
                                    {{-- Requisito: Título Registrado --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Título registrado:</span>
                                        @if($hasTituloRegistrado)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Sí
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Pendiente
                                            </span>
                                        @endif
                                    </div>
                                    
                                    {{-- ID del Título (si existe) --}}
                                    @if($idTitTrans)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">idTitTrans:</span>
                                            <span class="text-gray-900 font-mono text-xs truncate max-w-[120px]" title="{{ $idTitTrans }}">
                                                {{ Str::limit($idTitTrans, 15) }}
                                            </span>
                                        </div>
                                    @endif
                                    
                                    {{-- BLs con id_decla --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">BLs con id_decla:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $blsListos ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ $blsConIdDecla }}/{{ $blsTotales }}
                                        </span>
                                    </div>
                                    
                                    {{-- Separador si hay historial --}}
                                    @if($lastEnvios)
                                        <hr class="border-gray-200 my-1">
                                    @endif
                                    
                                    {{-- Historial de último envío --}}
                                    @if($lastEnvios)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastEnvios->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastEnvios->status === 'sent' || $lastEnvios->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastEnvios->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastEnvios->status) }}
                                            </span>
                                        </div>
                                        
                                        {{-- Mostrar error si hubo --}}
                                        @if($lastEnvios->status === 'error' && $lastEnvios->error_message)
                                            <div class="mt-1 p-1.5 bg-red-50 rounded text-xs text-red-700 truncate" title="{{ $lastEnvios->error_message }}">
                                                ⚠️ {{ Str::limit($lastEnvios->error_message, 50) }}
                                            </div>
                                        @endif
                                    @endif
                                    
                                    {{-- TRACKs generados por este método --}}
                                    @if($tracksEnvios->isNotEmpty())
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">TRACKs (Envíos):</span>
                                            <span class="text-green-600 font-semibold">{{ $tracksEnvios->count() }} generados</span>
                                        </div>
                                    @endif
                                    
                                    {{-- Mensaje de ayuda si no puede ejecutar --}}
                                    @if(!$puedeEjecutarEnvios)
                                        <div class="mt-1 p-1.5 bg-blue-50 rounded text-xs text-blue-700">
                                            💡 
                                            @if(!$hasTituloRegistrado)
                                                Primero ejecute RegistrarTitEnvios
                                            @elseif(!$blsListos)
                                                Faltan {{ $blsTotales - $blsConIdDecla }} BL(s) con id_decla
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- ============================================ --}}
                            {{-- TARJETA 3: RegistrarMicDta (MEJORADA) --}}
                            {{-- ============================================ --}}
                            @php
                                // Verificar requisitos previos para RegistrarMicDta
                                // Necesita: TRACKs generados de RegistrarTitEnvios o RegistrarEnvios
                                $tracksTotales = $voyage->webserviceTracks()
                                    ->whereIn('webservice_tracks.webservice_method', ['RegistrarTitEnvios', 'RegistrarEnvios'])
                                    ->whereIn('webservice_tracks.status', ['generated', 'used_in_micdta', 'used_in_convoy', 'completed'])
                                    ->count();
                                
                                $tieneTracks = $tracksTotales > 0;
                                
                                // Verificar embarcación con datos completos
                                $vessel = $voyage->leadVessel;
                                $tieneEmbarcacion = $vessel !== null;
                                $embarcacionCompleta = $tieneEmbarcacion && !empty($vessel->registration_number);
                                
                                // Verificar capitán asignado
                                $captain = $voyage->captain;
                                $tieneCapitan = $captain !== null;
                                $capitanCompleto = $tieneCapitan && !empty($captain->document_number);
                                
                                // Verificar puertos para ruta informática
                                $tienePuertos = $voyage->originPort !== null && $voyage->destinationPort !== null;
                                
                                // Verificar si es convoy o autopropulsado
                                $isConvoyVoyage = $voyage->is_convoy ?? false;
                                
                                // Último registro de MicDta
                                $lastMicDta = $voyage->webserviceTransactions()
                                    ->where('webservice_type', 'micdta')
                                    ->where('soap_action', 'like', '%RegistrarMicDta%')
                                    ->where('soap_action', 'NOT LIKE', '%RegistrarTitMicDta%')
                                    ->latest()
                                    ->first();
                                
                                // Obtener idMicDta si existe
                                $idMicDtaRegistrado = null;
                                $nroViajeRegistrado = null;
                                if ($lastMicDta && $lastMicDta->success_data) {
                                    $successData = is_array($lastMicDta->success_data) 
                                        ? $lastMicDta->success_data 
                                        : json_decode($lastMicDta->success_data, true);
                                    $idMicDtaRegistrado = $successData['mic_dta_id'] ?? $successData['id_micdta'] ?? $successData['idMicDta'] ?? null;
                                    $nroViajeRegistrado = $successData['nro_viaje'] ?? $successData['nroViaje'] ?? null;
                                }
                                
                                // Requisitos completos
                                // Para convoy/barcazas, no se requiere capitán (va en el remolcador)
                                $esBarcaza = $vessel && $vessel->vesselType && in_array(strtoupper($vessel->vesselType->code), ['BARGE_STD_001', 'BAR', 'BARCAZA']);
                                $puedeEjecutarMicDta = $tieneTracks && $embarcacionCompleta && ($capitanCompleto || $isConvoyVoyage || $esBarcaza) && $tienePuertos;
                            @endphp

                            <div class="flex flex-col bg-white border-2 {{ $puedeEjecutarMicDta ? 'border-blue-300' : 'border-yellow-300' }} rounded-lg overflow-hidden">
                                {{-- Botón principal --}}
                                <button onclick="{{ $puedeEjecutarMicDta ? "executeAfipMethod('RegistrarMicDta')" : "showRequisitosPrevios('RegistrarMicDta')" }}"
                                        class="flex flex-col items-center justify-center p-4 {{ $puedeEjecutarMicDta ? 'hover:bg-blue-50' : 'bg-gray-50' }} transition-colors">
                                    <span class="text-2xl mb-2">📄</span>
                                    <span class="text-sm font-medium text-center {{ !$puedeEjecutarMicDta ? 'text-gray-500' : '' }}">3. RegistrarMicDta</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Genera MIC/DTA oficial</span>
                                </button>
                                
                                {{-- Panel de información --}}
                                <div class="px-3 py-2 bg-gray-50 border-t border-blue-200 text-xs space-y-1">
                                    
                                    {{-- TRACKs disponibles --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">TRACKs disponibles:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $tieneTracks ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $tracksTotales }}
                                        </span>
                                    </div>
                                    
                                    {{-- Embarcación --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Embarcación:</span>
                                        @if($embarcacionCompleta)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" title="{{ $vessel->name }}">
                                                ✓ {{ Str::limit($vessel->name, 12) }}
                                            </span>
                                        @elseif($tieneEmbarcacion)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                ⚠️ Sin matrícula
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Falta
                                            </span>
                                        @endif
                                    </div>
                                    
                                    {{-- Capitán --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Capitán:</span>
                                        @if($capitanCompleto)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" title="{{ $captain->name }}">
                                                ✓ {{ Str::limit($captain->name, 12) }}
                                            </span>
                                        @elseif($tieneCapitan)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                ⚠️ Sin documento
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Falta
                                            </span>
                                        @endif
                                    </div>
                                    
                                    {{-- Tipo de viaje --}}
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Tipo:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $isConvoyVoyage ? '⛴️ Convoy' : '🚢 Autopropulsado' }}
                                        </span>
                                    </div>
                                    
                                    {{-- Separador si hay historial --}}
                                    @if($lastMicDta)
                                        <hr class="border-gray-200 my-1">
                                    @endif
                                    
                                    {{-- Historial de último registro --}}
                                    @if($lastMicDta)
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Último registro:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastMicDta->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastMicDta->status === 'sent' || $lastMicDta->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastMicDta->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastMicDta->status) }}
                                            </span>
                                        </div>
                                        
                                        {{-- ID MIC/DTA registrado --}}
                                        @if($idMicDtaRegistrado)
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">idMicDta:</span>
                                                <span class="text-gray-900 font-mono text-xs truncate max-w-[100px]" title="{{ $idMicDtaRegistrado }}">
                                                    {{ Str::limit($idMicDtaRegistrado, 12) }}
                                                </span>
                                            </div>
                                        @endif
                                        
                                        {{-- Nro Viaje (solo autopropulsado) --}}
                                        @if($nroViajeRegistrado && !$isConvoyVoyage)
                                            <div class="flex items-center justify-between">
                                                <span class="text-gray-600">Nro Viaje:</span>
                                                <span class="text-green-700 font-mono font-semibold text-xs">
                                                    {{ $nroViajeRegistrado }}
                                                </span>
                                            </div>
                                        @endif
                                        
                                        {{-- Mostrar error si hubo --}}
                                        @if($lastMicDta->status === 'error' && $lastMicDta->error_message)
                                            <div class="mt-1 p-1.5 bg-red-50 rounded text-xs text-red-700 truncate" title="{{ $lastMicDta->error_message }}">
                                                ⚠️ {{ Str::limit($lastMicDta->error_message, 50) }}
                                            </div>
                                        @endif
                                    @endif
                                    
                                    {{-- Mensaje de ayuda si no puede ejecutar --}}
                                    @if(!$puedeEjecutarMicDta)
                                        <div class="mt-1 p-1.5 bg-yellow-50 rounded text-xs text-yellow-700">
                                            💡 Faltan: 
                                            @if(!$tieneTracks) TRACKs @endif
                                            @if(!$embarcacionCompleta) Embarcación @endif
                                            @if(!$capitanCompleto) Capitán @endif
                                            @if(!$tienePuertos) Puertos @endif
                                        </div>
                                    @endif
                                </div>
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
                            
                            {{-- ============================================ --}}
                            {{-- TARJETA 10: RegistrarSalidaZonaPrimaria (MEJORADA) --}}
                            {{-- ============================================ --}}
                            @php
                                // Verificar si hay MIC/DTA registrado (requisito previo)
                                // Buscar MIC/DTA exitoso (independiente de $lastMicDta que puede tener status error)
                                $lastMicDtaExitoso = $voyage->webserviceTransactions()
                                    ->where('webservice_type', 'micdta')
                                    ->where('soap_action', 'like', '%RegistrarMicDta%')
                                    ->where('soap_action', 'NOT LIKE', '%RegistrarTitMicDta%')
                                    ->where('soap_action', 'NOT LIKE', '%Anular%')
                                    ->whereIn('status', ['success', 'sent'])
                                    ->latest()
                                    ->first();
                                $hasMicDtaRegistrado = !is_null($lastMicDtaExitoso);
                                
                                // Obtener nroViaje del MIC/DTA exitoso
                                $nroViaje = null;
                                if ($hasMicDtaRegistrado && $lastMicDtaExitoso->success_data) {
                                    $successDataMicDta = is_array($lastMicDtaExitoso->success_data) 
                                        ? $lastMicDtaExitoso->success_data 
                                        : json_decode($lastMicDtaExitoso->success_data, true);
                                    $nroViaje = $successDataMicDta['nro_viaje'] ?? $successDataMicDta['nroViaje'] ?? null;
                                }
                                
                                // Determinar si puede ejecutar
                                $puedeEjecutarSalidaZP = $hasMicDtaRegistrado && !empty($nroViaje);
                                
                                // Extraer nroSalida y nroPartida si ya se ejecutó
                                $nroSalida = null;
                                $nroPartida = null;
                                if ($lastSalidaZP && $lastSalidaZP->success_data) {
                                    $successDataSalida = is_array($lastSalidaZP->success_data) 
                                        ? $lastSalidaZP->success_data 
                                        : json_decode($lastSalidaZP->success_data, true);
                                    $nroSalida = $successDataSalida['nro_salida'] ?? $successDataSalida['nroSalida'] ?? null;
                                    $nroPartida = $successDataSalida['nro_partida'] ?? $successDataSalida['nroPartida'] ?? null;
                                }
                            @endphp

                            <div class="flex flex-col bg-white border-2 {{ $puedeEjecutarSalidaZP ? 'border-orange-300' : 'border-gray-300' }} rounded-lg overflow-hidden">
                                {{-- Botón principal --}}
                                <button onclick="{{ $puedeEjecutarSalidaZP ? "executeAfipMethod('RegistrarSalidaZonaPrimaria')" : "showRequisitosPrevios('RegistrarSalidaZonaPrimaria')" }}"
                                        class="flex flex-col items-center justify-center p-4 {{ $puedeEjecutarSalidaZP ? 'hover:bg-orange-50' : 'bg-gray-50 cursor-not-allowed' }} transition-colors">
                                    <span class="text-2xl mb-2">🚪</span>
                                    <span class="text-sm font-medium text-center {{ !$puedeEjecutarSalidaZP ? 'text-gray-500' : '' }}">4. RegistrarSalidaZP</span>
                                    <span class="text-xs text-gray-600 text-center mt-1">Salida zona primaria</span>
                                </button>
                                
                                {{-- Panel de requisitos previos --}}
                                @if(!$puedeEjecutarSalidaZP)
                                    <div class="px-3 py-2 bg-yellow-50 border-t border-yellow-200">
                                        <p class="text-xs font-semibold text-yellow-800 mb-1">⚠️ Requisitos faltantes:</p>
                                        <ul class="text-xs text-yellow-700 space-y-0.5">
                                            @if(!$hasMicDtaRegistrado)
                                                <li>• Ejecutar RegistrarMicDta primero</li>
                                            @elseif(empty($nroViaje))
                                                <li>• No se obtuvo Nro. Viaje del MIC/DTA</li>
                                            @endif
                                        </ul>
                                    </div>
                                @else
                                    {{-- Panel de información disponible --}}
                                    <div class="px-3 py-2 bg-orange-50 border-t border-orange-200">
                                        <p class="text-xs font-semibold text-orange-800 mb-1">📋 Datos para envío:</p>
                                        <div class="text-xs space-y-1">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Nro. Viaje:</span>
                                                <span class="font-mono text-orange-900 font-semibold">{{ $nroViaje }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                {{-- Panel de resultado último envío --}}
                                @if($lastSalidaZP)
                                    <div class="px-3 py-2 bg-gray-50 border-t border-orange-200 text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Último envío:</span>
                                            <span class="text-gray-900 font-medium">{{ $lastSalidaZP->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-gray-600">Estado:</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                @if($lastSalidaZP->status === 'success') bg-green-100 text-green-800
                                                @elseif($lastSalidaZP->status === 'sent') bg-blue-100 text-blue-800
                                                @elseif($lastSalidaZP->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($lastSalidaZP->status) }}
                                            </span>
                                        </div>
                                        
                                        {{-- Mostrar Nro. Salida si fue exitoso --}}
                                        @if($nroSalida)
                                            <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded space-y-1">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-green-700 font-semibold text-xs">Nro. Salida:</span>
                                                    <span class="font-mono text-green-900 font-bold text-xs">{{ $nroSalida }}</span>
                                                </div>
                                                @if($nroPartida)
                                                <div class="flex justify-between items-center">
                                                    <span class="text-green-700 font-semibold text-xs">Nro. Partida:</span>
                                                    <span class="font-mono text-green-900 font-bold text-xs">{{ $nroPartida }}</span>
                                                </div>
                                                @endif
                                            </div>
                                        @endif
                                        
                                        {{-- Mostrar error si falló --}}
                                        @if($lastSalidaZP->status === 'error' && $lastSalidaZP->error_message)
                                            <div class="mt-2 p-2 bg-red-50 border-l-2 border-red-400 rounded">
                                                <p class="text-xs text-red-800 font-semibold">❌ Error:</p>
                                                <p class="text-xs text-red-700 mt-1">{{ $lastSalidaZP->error_message }}</p>
                                            </div>
                                        @endif
                                        
                                        {{-- Mostrar advertencia si hay mensaje en estado sent --}}
                                        @if($lastSalidaZP->status === 'sent' && $lastSalidaZP->error_message)
                                            <div class="mt-2 p-2 bg-orange-50 border-l-2 border-orange-400 rounded">
                                                <p class="text-xs text-orange-800 font-semibold">⚠️ Atención:</p>
                                                <p class="text-xs text-orange-700 mt-1">{{ $lastSalidaZP->error_message }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
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
                                        {{-- Botones para ver XML Request/Response --}}
                                        <div class="col-span-2 mt-2 pt-2 border-t border-gray-200 flex items-center gap-2">
                                            <span class="text-gray-600 font-medium">XMLs:</span>
                                            @if($trans->request_xml)
                                                <button type="button" 
                                                        onclick="verXml({{ $trans->id }}, 'request')"
                                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 hover:bg-blue-200">
                                                    📤 Request
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">Sin Request</span>
                                            @endif
                                            
                                            @if($trans->response_xml)
                                                <button type="button" 
                                                        onclick="verXml({{ $trans->id }}, 'response')"
                                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-700 hover:bg-green-200">
                                                    📥 Response
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">Sin Response</span>
                                            @endif
                                        </div>
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
        MODAL: SOLICITAR ANULAR MIC/DTA (Botón 16)
        ======================================================================== --}}
    <div id="anular-micdta-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                {{-- Header --}}
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-red-900">
                        🗂️ Solicitar Anulación MIC/DTA
                    </h3>
                    <button onclick="closeAnularMicDtaModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>

                {{-- Loading --}}
                <div id="anular-micdta-loading" class="hidden text-center py-6">
                    <svg class="animate-spin h-8 w-8 mx-auto text-red-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-600">Cargando MIC/DTAs registrados...</p>
                </div>

                {{-- Error --}}
                <div id="anular-micdta-error" class="hidden mt-4 p-3 bg-red-50 rounded border border-red-200">
                    <p class="text-sm text-red-700">Error al cargar datos.</p>
                </div>

                {{-- Content --}}
                <div id="anular-micdta-content" class="hidden mt-4 space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                        <p class="text-xs text-yellow-800">
                            ⚠️ La solicitud será evaluada por el servicio aduanero (AFIP). Puede ser aprobada o rechazada.
                        </p>
                    </div>

                    {{-- Select MIC/DTA --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            ID MIC/DTA <span class="text-red-500">*</span>
                        </label>
                        <select id="anular-micdta-select" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                            <option value="">-- Seleccione MIC/DTA --</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">O ingrese manualmente:</p>
                        <input type="text" id="anular-micdta-manual" maxlength="16" placeholder="Ej: 25ARMIF00005933A" 
                               class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-red-500 focus:ring-red-500 font-mono">
                    </div>

                    {{-- Motivo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motivo de anulación <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="anular-micdta-motivo" maxlength="50" placeholder="Ej: mal registrado" 
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                        <p class="text-xs text-gray-500 mt-1">Máximo 50 caracteres</p>
                    </div>

                    {{-- Botones --}}
                    <div class="flex justify-end space-x-3 border-t pt-4">
                        <button onclick="closeAnularMicDtaModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm">
                            Cancelar
                        </button>
                        <button onclick="confirmarAnularMicDta()" id="btn-confirmar-anular-micdta"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                            Solicitar Anulación
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================
        MODAL: REGISTRAR SALIDA ZONA PRIMARIA (Botón 10)
        ======================================================================== --}}
    <div id="salida-zp-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-orange-900">
                        🚪 Registrar Salida Zona Primaria
                    </h3>
                    <button onclick="closeSalidaZPModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>
                <div class="mt-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded p-3">
                        <p class="text-xs text-blue-800">
                            📋 Registra la salida del viaje desde la zona primaria aduanera. Verifique el Nro. de Viaje antes de enviar.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nro. Viaje <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="salida-zp-nro-viaje" maxlength="20" placeholder="Ej: AR202600000001V" 
                               value="{{ $nroViaje ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-orange-500 focus:ring-orange-500 font-mono">
                        <p class="text-xs text-gray-500 mt-1">Número de viaje asignado por AFIP al registrar MIC/DTA</p>
                    </div>
                    <div class="flex justify-end space-x-3 border-t pt-4">
                        <button onclick="closeSalidaZPModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm">
                            Cancelar
                        </button>
                        <button onclick="confirmarSalidaZP()" id="btn-confirmar-salida-zp"
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm">
                            Registrar Salida
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================
        MODAL: REGISTRAR ARRIBO ZONA PRIMARIA (Botón 11)
        ======================================================================== --}}
    <div id="arribo-zp-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3 border-b">
                    <h3 class="text-lg font-medium text-orange-900">
                        🛬 Registrar Arribo Zona Primaria
                    </h3>
                    <button onclick="closeArriboZPModal()" class="text-gray-400 hover:text-gray-500">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>
                <div class="mt-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded p-3">
                        <p class="text-xs text-blue-800">
                            📋 Registra el arribo del viaje a una zona primaria aduanera. Complete los datos del punto de arribo.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nro. Viaje <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="arribo-zp-nro-viaje" maxlength="20" placeholder="Ej: AR202600000001V" 
                               value="{{ $nroViaje ?? '' }}"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-orange-500 focus:ring-orange-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Código Aduana <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="arribo-zp-cod-adu" maxlength="3" placeholder="Ej: 033" 
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-orange-500 focus:ring-orange-500 font-mono">
                        <p class="text-xs text-gray-500 mt-1">3 dígitos - Código de aduana destino</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Código Lugar Operativo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="arribo-zp-cod-lug-oper" maxlength="5" placeholder="Ej: 10056" 
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-orange-500 focus:ring-orange-500 font-mono">
                        <p class="text-xs text-gray-500 mt-1">Hasta 5 dígitos - Lugar operativo de arribo</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Descripción Amarre <span class="text-gray-400">(opcional)</span>
                        </label>
                        <input type="text" id="arribo-zp-desc-amarre" maxlength="50" placeholder="Ej: TERMINAL 5" 
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-orange-500 focus:ring-orange-500">
                        <p class="text-xs text-gray-500 mt-1">Máximo 50 caracteres</p>
                    </div>
                    <div class="flex justify-end space-x-3 border-t pt-4">
                        <button onclick="closeArriboZPModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm">
                            Cancelar
                        </button>
                        <button onclick="confirmarArriboZP()" id="btn-confirmar-arribo-zp"
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-sm">
                            Registrar Arribo
                        </button>
                    </div>
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

    {{-- ========================================================================
    MODAL: RECTIFICAR CONVOY / MIC-DTA (Botón 6)
    ======================================================================== --}}
    <div id="rectif-convoy-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-0 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-purple-50 rounded-t-lg">
                <div>
                    <h3 class="text-lg font-semibold text-purple-900">✏️ Rectificar Convoy</h3>
                    <p class="text-sm text-purple-700 mt-1">Rectifica la configuración del convoy en AFIP</p>
                </div>
                <button onclick="closeRectifConvoyModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Loading --}}
            <div id="rectif-convoy-loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-purple-600 mx-auto"></div>
                <p class="text-sm text-gray-500 mt-3">Cargando datos del convoy...</p>
            </div>

            {{-- Error --}}
            <div id="rectif-convoy-error" class="hidden p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 font-medium">❌ Error</p>
                    <p class="text-red-700 text-sm mt-1" id="rectif-convoy-error-msg"></p>
                </div>
                <div class="mt-4 text-right">
                    <button onclick="closeRectifConvoyModal()" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                        Cerrar
                    </button>
                </div>
            </div>

            {{-- Contenido principal --}}
            <div id="rectif-convoy-content" class="hidden">
                <div class="px-6 py-4 space-y-4">

                    {{-- Info del convoy actual --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">📋 Convoy Actual en AFIP</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-gray-600">Nro Viaje:</span>
                                <span id="rectif-nro-viaje" class="font-mono font-bold text-blue-800 ml-1"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Remolcador MIC/DTA:</span>
                                <span id="rectif-micdta-remol" class="font-mono font-bold text-blue-800 ml-1"></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="text-gray-600 text-sm">Barcazas MIC/DTA:</span>
                            <div id="rectif-barcazas-list" class="mt-1"></div>
                        </div>
                    </div>

                    {{-- Tipo de rectificación --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de rectificación</label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-purple-50 border-purple-300 bg-purple-50">
                                <input type="radio" name="rectif_tipo" value="convoy" checked class="text-purple-600">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-900">Configuración de Convoy</span>
                                    <p class="text-xs text-gray-500">Cambiar las barcazas que integran el convoy</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Motivo de rectificación --}}
                    <div>
                        <label for="rectif-motivo" class="block text-sm font-medium text-gray-700 mb-1">
                            Motivo de rectificación <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="rectif-motivo" maxlength="50"
                            placeholder="Ej: Corrección barcaza en convoy"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-purple-500 focus:border-purple-500"
                            oninput="updateRectifButton()">
                        <p class="text-xs text-gray-400 mt-1">Máximo 50 caracteres (<span id="rectif-motivo-count">0</span>/50)</p>
                    </div>

                    {{-- Resumen de lo que se enviará --}}
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-sm text-yellow-800">
                            <strong>⚠️ Se enviará a AFIP:</strong> Rectificación del convoy con el remolcador y barcazas mostrados arriba.
                            El convoy está en estado <strong>Registrado</strong>, por lo que no requiere aprobación aduanera.
                        </p>
                    </div>
                </div>

                {{-- Footer con botones --}}
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <button onclick="closeRectifConvoyModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button id="btn-confirmar-rectif" onclick="confirmarRectifConvoy()" disabled
                            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        ✏️ Rectificar Convoy en AFIP
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- MODAL PARA VER XML REQUEST/RESPONSE --}}
    {{-- ========================================== --}}
    <div id="xml-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            {{-- Fondo oscuro --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="cerrarXmlModal()"></div>

            {{-- Centrador --}}
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            {{-- Contenido del modal --}}
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                {{-- Header --}}
                <div class="bg-gray-800 px-4 py-3 flex items-center justify-between">
                    <h3 id="xml-modal-title" class="text-lg font-medium text-white">
                        XML Request
                    </h3>
                    <button type="button" onclick="cerrarXmlModal()" class="text-gray-300 hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                {{-- Body con XML --}}
                <div class="bg-gray-900 p-4 max-h-[70vh] overflow-auto">
                    <pre id="xml-modal-content" class="text-sm text-green-400 whitespace-pre-wrap font-mono">Cargando...</pre>
                </div>
                
                {{-- Footer --}}
                <div class="bg-gray-100 px-4 py-3 flex justify-between items-center">
                    <span id="xml-modal-info" class="text-xs text-gray-500">Transaction ID: -</span>
                    <div class="flex gap-2">
                        <button type="button" onclick="copiarXml(this)" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            📋 Copiar
                        </button>
                        <button type="button" onclick="cerrarXmlModal()" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded text-white bg-gray-600 hover:bg-gray-700">
                            Cerrar
                        </button>
                    </div>
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

        if (methodName === 'SolicitarAnularMicDta') {
            showAnularMicDtaModal();
            return;
        }

        if (methodName === 'RegistrarArriboZonaPrimaria') {
            showArriboZPModal();
            return;
        }

        if (methodName === 'RegistrarSalidaZonaPrimaria') {
            showSalidaZPModal();
            return;
        }

        if (methodName === 'AnularTitulo') {
            showAnularTituloModal();
            return;
        }

        if (methodName === 'RectifConvoyMicDta') {
            showRectifConvoyModal();
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
     * Mostrar mensaje de requisitos previos faltantes
     */
    function showRequisitosPrevios(methodName) {
        let mensaje = '';
        
        if (methodName === 'RegistrarEnvios') {
            mensaje = 'Para ejecutar RegistrarEnvios necesita:\n\n';
            mensaje += '1. Ejecutar primero RegistrarTitEnvios exitosamente\n';
            mensaje += '2. Todos los BLs deben tener el campo id_decla completado\n\n';
            mensaje += 'Verifique el panel de información de la tarjeta.';
        } else if (methodName === 'RegistrarMicDta') {
            mensaje = 'Para ejecutar RegistrarMicDta necesita:\n\n';
            mensaje += '1. TRACKs generados (ejecutar RegistrarTitEnvios primero)\n';
            mensaje += '2. Embarcación asignada con matrícula\n';
            mensaje += '3. Capitán asignado con número de documento\n';
            mensaje += '4. Puertos de origen y destino configurados\n\n';
            mensaje += 'Verifique el panel de información de la tarjeta.';
        }
        
        alert(mensaje);
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
            
            let successHtml = `
                <p class="text-sm text-gray-700">
                    <strong>Método:</strong> ${methodName}<br>
                    ${result.message ? `<strong>Mensaje:</strong> ${result.message}<br>` : ''}
                    ${result.transaction_id ? `<strong>Transaction ID:</strong> ${result.transaction_id}<br>` : ''}
                    ${result.shipments_processed ? `<strong>Shipments procesados:</strong> ${result.shipments_processed}<br>` : ''}
                    ${result.tracks_generated ? `<strong>TRACKs generados:</strong> ${result.tracks_generated}<br>` : ''}
                    ${result.mic_dta_id ? `<strong>MIC/DTA ID:</strong> ${result.mic_dta_id}<br>` : ''}
                </p>
            `;
            
            // Mostrar detalles de éxito (TRACKs, próximos pasos, etc.)
            if (result.success_details && result.success_details.length > 0) {
                successHtml += `
                    <div class="mt-4 p-3 bg-green-50 rounded-md">
                        <p class="text-sm font-semibold text-green-800 mb-2">Detalles:</p>
                        <ul class="text-sm text-green-700 space-y-1">
                            ${result.success_details.map(detail => `<li>${detail}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            // Mostrar TRACKs por shipment si existen
            if (result.tracks_by_shipment && Object.keys(result.tracks_by_shipment).length > 0) {
                successHtml += `
                    <div class="mt-3 p-3 bg-blue-50 rounded-md">
                        <p class="text-sm font-semibold text-blue-800 mb-2">📦 TRACKs por Shipment:</p>
                        <div class="text-sm text-blue-700 space-y-1">
                            ${Object.entries(result.tracks_by_shipment).map(([shipment, tracks]) => 
                                `<div><strong>${shipment}:</strong> ${tracks.join(', ')}</div>`
                            ).join('')}
                        </div>
                    </div>
                `;
            }

            // ✅ NUEVO: Mostrar mensajes AFIP (Alertas e Informativos)
            if (result.afip_messages) {
                // Mostrar ALERTAS de AFIP
                if (result.afip_messages.alertas && result.afip_messages.alertas.length > 0) {
                    successHtml += `
                        <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded-md">
                            <p class="text-sm font-semibold text-yellow-800 mb-2">⚠️ Alertas de AFIP:</p>
                            <ul class="text-sm text-yellow-700 space-y-2">
                                ${result.afip_messages.alertas.map(alerta => `
                                    <li>
                                        <strong>[${alerta.codigo}]</strong> ${alerta.descripcion}
                                        ${alerta.shipment_number ? `<br><span class="text-xs">Shipment: ${alerta.shipment_number}</span>` : ''}
                                        ${alerta.descripcion_detallada ? `<br><span class="text-xs italic">${alerta.descripcion_detallada}</span>` : ''}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }
                
                // Mostrar MENSAJES INFORMATIVOS de AFIP
                if (result.afip_messages.informativos && result.afip_messages.informativos.length > 0) {
                    successHtml += `
                        <div class="mt-3 p-3 bg-blue-50 border-l-4 border-blue-400 rounded-md">
                            <p class="text-sm font-semibold text-blue-800 mb-2">ℹ️ Información de AFIP:</p>
                            <ul class="text-sm text-blue-700 space-y-1">
                                ${result.afip_messages.informativos.map(info => `
                                    <li>
                                        <strong>[${info.codigo}]</strong> ${info.descripcion}
                                        ${info.shipment_number ? `<br><span class="text-xs">Shipment: ${info.shipment_number}</span>` : ''}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }
            }
            
            // ✅ NUEVO: Mostrar warnings de TRACKs faltantes
            if (result.has_warning && result.warning_messages && result.warning_messages.length > 0) {
                successHtml += `
                    <div class="mt-4 p-3 bg-orange-50 border-l-4 border-orange-500 rounded-md">
                        <p class="text-sm font-semibold text-orange-800 mb-2">⚠️ ATENCIÓN - Acción Requerida:</p>
                        <ul class="text-sm text-orange-700 space-y-1 list-disc list-inside">
                            ${result.warning_messages.map(warning => `<li>${warning}</li>`).join('')}
                        </ul>
                        <p class="text-xs text-orange-600 mt-2 italic">
                            💡 Los TRACKs son necesarios para continuar con RegistrarMicDta. 
                            Por favor contacte a AFIP o reintente el envío.
                        </p>
                    </div>
                `;
            }
            
            message.innerHTML = successHtml;
        } else {
            icon.innerHTML = '❌';
            icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100';
            title.textContent = `${methodName} - Error`;
            
            // ✅ CONSTRUIR MENSAJE DE ERROR CON DETALLES
            let errorHtml = `
                <p class="text-sm text-gray-700">
                    <strong>Error:</strong> ${result.error || result.error_message || 'Error desconocido'}<br>
                    ${result.details ? `<strong>Detalle:</strong> ${result.details}<br>` : ''}
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
    // MODAL RECTIFICAR CONVOY (Botón 6)
    // ========================================================================

    async function showRectifConvoyModal() {
        const modal = document.getElementById('rectif-convoy-modal');
        const loading = document.getElementById('rectif-convoy-loading');
        const content = document.getElementById('rectif-convoy-content');
        const errorDiv = document.getElementById('rectif-convoy-error');

        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        errorDiv.classList.add('hidden');

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/datos-convoy`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'No se encontraron datos del convoy');
            }

            // Llenar datos
            document.getElementById('rectif-nro-viaje').textContent = result.nro_viaje || 'N/A';
            document.getElementById('rectif-micdta-remol').textContent = result.micdta_remolcador || 'N/A';

            // Barcazas
            const barcazasDiv = document.getElementById('rectif-barcazas-list');
            barcazasDiv.innerHTML = '';
            if (result.barcazas && result.barcazas.length > 0) {
                result.barcazas.forEach(b => {
                    const span = document.createElement('span');
                    span.className = 'inline-flex items-center px-2 py-1 mr-2 mb-1 rounded text-xs font-mono font-bold bg-green-100 text-green-800';
                    span.textContent = b.micdta + ' (' + b.vessel_name + ')';
                    barcazasDiv.appendChild(span);
                });
            } else {
                barcazasDiv.innerHTML = '<span class="text-xs text-gray-500">Sin barcazas detectadas</span>';
            }

            // Guardar datos en el modal para el envío
            modal.dataset.nroViaje = result.nro_viaje || '';
            modal.dataset.micDtaRemol = result.micdta_remolcador || '';
            modal.dataset.barcazasIds = JSON.stringify(result.barcazas ? result.barcazas.map(b => b.micdta) : []);

            loading.classList.add('hidden');
            content.classList.remove('hidden');

        } catch (error) {
            loading.classList.add('hidden');
            errorDiv.classList.remove('hidden');
            document.getElementById('rectif-convoy-error-msg').textContent = error.message;
        }
    }

    function updateRectifButton() {
        const motivo = document.getElementById('rectif-motivo').value.trim();
        document.getElementById('rectif-motivo-count').textContent = motivo.length;
        document.getElementById('btn-confirmar-rectif').disabled = motivo.length === 0;
    }

    async function confirmarRectifConvoy() {
        const modal = document.getElementById('rectif-convoy-modal');
        const motivo = document.getElementById('rectif-motivo').value.trim();

        if (!motivo) {
            alert('Debe indicar el motivo de rectificación');
            return;
        }

        const nroViaje = modal.dataset.nroViaje;
        const micDtaRemol = modal.dataset.micDtaRemol;
        const barcazasIds = JSON.parse(modal.dataset.barcazasIds || '[]');

        if (!nroViaje || !micDtaRemol) {
            alert('Faltan datos del convoy. Cierre y reintente.');
            return;
        }

        if (!confirm('¿Confirma enviar la rectificación del convoy a AFIP?')) {
            return;
        }

        const button = document.getElementById('btn-confirmar-rectif');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/rectif-convoy-micdta`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    nro_viaje: nroViaje,
                    desc_motivo: motivo,
                    rectif_convoy: {
                        id_micdta_remol: micDtaRemol,
                        barcazas_micdta_ids: barcazasIds
                    }
                })
            });

            const result = await response.json();
            closeRectifConvoyModal();
            showResultModal('RectifConvoyMicDta', result, response.ok);

            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }

        } catch (error) {
            showResultModal('RectifConvoyMicDta', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    function closeRectifConvoyModal() {
        document.getElementById('rectif-convoy-modal').classList.add('hidden');
        document.getElementById('rectif-motivo').value = '';
        document.getElementById('rectif-motivo-count').textContent = '0';
        document.getElementById('btn-confirmar-rectif').disabled = true;
    }

    // ========================================================================
    // MODAL SOLICITAR ANULAR MIC/DTA (Botón 16)
    // ========================================================================

    function showAnularMicDtaModal() {
        const modal = document.getElementById('anular-micdta-modal');
        const loading = document.getElementById('anular-micdta-loading');
        const content = document.getElementById('anular-micdta-content');
        const errorDiv = document.getElementById('anular-micdta-error');
        
        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        errorDiv.classList.add('hidden');
        
        // Buscar MIC/DTAs registrados en transacciones del viaje
        fetch(`/company/simple/webservices/micdta/${voyageId}/titulos-registrados`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(result => {
            loading.classList.add('hidden');
            
            // Buscar idMicDta en la respuesta (viene de transacciones RegistrarMicDta)
            const select = document.getElementById('anular-micdta-select');
            select.innerHTML = '<option value="">-- Seleccione MIC/DTA --</option>';
            
            if (result.success && result.micdta_ids && result.micdta_ids.length > 0) {
                result.micdta_ids.forEach(id => {
                    select.innerHTML += `<option value="${id}">${id}</option>`;
                });
            }
            
            // También permitir ingreso manual (por si no se detectan automáticamente)
            content.classList.remove('hidden');
        })
        .catch(error => {
            loading.classList.add('hidden');
            // Aunque falle la carga, mostrar el formulario con ingreso manual
            content.classList.remove('hidden');
        });
    }

    function closeAnularMicDtaModal() {
        document.getElementById('anular-micdta-modal').classList.add('hidden');
        document.getElementById('anular-micdta-select').value = '';
        document.getElementById('anular-micdta-manual').value = '';
        document.getElementById('anular-micdta-motivo').value = '';
    }

    async function confirmarAnularMicDta() {
        const select = document.getElementById('anular-micdta-select');
        const manual = document.getElementById('anular-micdta-manual').value.trim();
        const motivo = document.getElementById('anular-micdta-motivo').value.trim();
        
        // Usar select si tiene valor, sino el manual
        const micdtaId = select.value || manual;
        
        if (!micdtaId) {
            alert('Debe seleccionar o ingresar un ID MIC/DTA');
            return;
        }
        
        if (micdtaId.length > 16) {
            alert('El ID MIC/DTA no puede exceder 16 caracteres');
            return;
        }
        
        if (!motivo) {
            alert('Debe ingresar un motivo de anulación');
            document.getElementById('anular-micdta-motivo').focus();
            return;
        }
        
        if (motivo.length > 50) {
            alert('El motivo no puede exceder 50 caracteres');
            return;
        }

        if (!confirm(`⚠️ CONFIRMACIÓN\n\n¿Solicitar anulación del MIC/DTA "${micdtaId}"?\n\nMotivo: ${motivo}\n\nLa solicitud será evaluada por AFIP.`)) {
            return;
        }

        const btn = document.getElementById('btn-confirmar-anular-micdta');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Enviando...';

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/solicitar-anular-micdta`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    micdta_id: micdtaId,
                    motivo_anulacion: motivo,
                    force_send: false,
                    notes: `Anulación MIC/DTA desde panel - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            closeAnularMicDtaModal();
            showResultModal('SolicitarAnularMicDta', result, response.ok);

            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }

        } catch (error) {
            showResultModal('SolicitarAnularMicDta', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }


    // ========================================================================
    // MODAL REGISTRAR SALIDA ZONA PRIMARIA (Botón 10)
    // ========================================================================
    function showSalidaZPModal() {
        document.getElementById('salida-zp-modal').classList.remove('hidden');
    }

    function closeSalidaZPModal() {
        document.getElementById('salida-zp-modal').classList.add('hidden');
    }

    async function confirmarSalidaZP() {
        const nroViaje = document.getElementById('salida-zp-nro-viaje').value.trim();

        if (!nroViaje) {
            alert('Debe ingresar el Nro. de Viaje');
            document.getElementById('salida-zp-nro-viaje').focus();
            return;
        }

        if (!confirm(`⚠️ CONFIRMACIÓN\n\n¿Registrar salida de zona primaria?\n\nNro. Viaje: ${nroViaje}`)) {
            return;
        }

        const btn = document.getElementById('btn-confirmar-salida-zp');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Enviando...';

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/registrar-salida-zona-primaria`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    nro_viaje: nroViaje,
                    force_send: false,
                    notes: `Salida ZP desde panel - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            closeSalidaZPModal();
            showResultModal('RegistrarSalidaZonaPrimaria', result, response.ok);

            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }
        } catch (error) {
            showResultModal('RegistrarSalidaZonaPrimaria', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // ========================================================================
    // MODAL REGISTRAR ARRIBO ZONA PRIMARIA (Botón 11)
    // ========================================================================
    function showArriboZPModal() {
        document.getElementById('arribo-zp-modal').classList.remove('hidden');
    }

    function closeArriboZPModal() {
        document.getElementById('arribo-zp-modal').classList.add('hidden');
        document.getElementById('arribo-zp-cod-adu').value = '';
        document.getElementById('arribo-zp-cod-lug-oper').value = '';
        document.getElementById('arribo-zp-desc-amarre').value = '';
    }

    async function confirmarArriboZP() {
        const nroViaje = document.getElementById('arribo-zp-nro-viaje').value.trim();
        const codAdu = document.getElementById('arribo-zp-cod-adu').value.trim();
        const codLugOper = document.getElementById('arribo-zp-cod-lug-oper').value.trim();
        const descAmarre = document.getElementById('arribo-zp-desc-amarre').value.trim();

        if (!nroViaje) {
            alert('Debe ingresar el Nro. de Viaje');
            document.getElementById('arribo-zp-nro-viaje').focus();
            return;
        }
        if (!codAdu) {
            alert('Debe ingresar el Código de Aduana');
            document.getElementById('arribo-zp-cod-adu').focus();
            return;
        }
        if (!codLugOper) {
            alert('Debe ingresar el Código de Lugar Operativo');
            document.getElementById('arribo-zp-cod-lug-oper').focus();
            return;
        }

        if (!confirm(`⚠️ CONFIRMACIÓN\n\n¿Registrar arribo en zona primaria?\n\nNro. Viaje: ${nroViaje}\nAduana: ${codAdu}\nLugar Operativo: ${codLugOper}\nAmarre: ${descAmarre || '(sin especificar)'}`)) {
            return;
        }

        const btn = document.getElementById('btn-confirmar-arribo-zp');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Enviando...';

        try {
            const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/registrar-arribo-zona-primaria`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    nro_viaje: nroViaje,
                    cod_adu: codAdu,
                    cod_lug_oper: codLugOper,
                    desc_amarre: descAmarre,
                    force_send: false,
                    notes: `Arribo ZP desde panel - ${new Date().toLocaleString()}`
                })
            });

            const result = await response.json();
            closeArriboZPModal();
            showResultModal('RegistrarArriboZonaPrimaria', result, response.ok);

            if (result.success) {
                setTimeout(() => location.reload(), 2000);
            }
        } catch (error) {
            showResultModal('RegistrarArriboZonaPrimaria', { error: 'Error de comunicación: ' + error.message }, false);
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
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

    // ========================================
    // FUNCIONES PARA VER XML REQUEST/RESPONSE
    // ========================================

    function verXml(transactionId, type) {
        const modal = document.getElementById('xml-modal');
        const title = document.getElementById('xml-modal-title');
        const content = document.getElementById('xml-modal-content');
        const info = document.getElementById('xml-modal-info');
        
        // Mostrar modal con loading
        modal.classList.remove('hidden');
        title.textContent = type === 'request' ? '📤 XML Request' : '📥 XML Response';
        content.textContent = 'Cargando XML...';
        content.className = 'text-sm text-yellow-400 whitespace-pre-wrap font-mono';
        info.textContent = 'Transaction ID: ' + transactionId;
        
        // Llamar al endpoint existente
        fetch(`/company/webservices/transaction/${transactionId}/xml/${type}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.textContent = data.xml;
                content.className = 'text-sm text-green-400 whitespace-pre-wrap font-mono';
            } else {
                content.textContent = 'Error: ' + (data.message || 'No se pudo cargar el XML');
                content.className = 'text-sm text-red-400 whitespace-pre-wrap font-mono';
            }
        })
        .catch(error => {
            content.textContent = 'Error de conexión: ' + error.message;
            content.className = 'text-sm text-red-400 whitespace-pre-wrap font-mono';
        });
    }

    function cerrarXmlModal() {
        document.getElementById('xml-modal').classList.add('hidden');
    }

    function copiarXml(btn) {
    const content = document.getElementById('xml-modal-content').textContent;
    navigator.clipboard.writeText(content).then(() => {
        // Feedback visual
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅ Copiado!';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    }).catch(err => {
        alert('Error al copiar: ' + err);
    });
}
</script>
