<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    MIC/DTA Argentina - {{ $voyage->voyage_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Formulario de envío MIC/DTA a AFIP Argentina - FLUJO CORREGIDO
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('company.simple.micdta.index') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Estado Actual del Envío --}}
            <div id="statusCard" class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Estado Actual</h3>
                        <button onclick="refreshStatus()" class="text-sm text-indigo-600 hover:text-indigo-500">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Actualizar
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4" id="statusContent">
                    {{-- Contenido dinámico cargado por JavaScript --}}
                    <div class="animate-pulse">
                        <div class="h-4 bg-gray-200 rounded w-1/4 mb-2"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            </div>

            {{-- Resumen del Voyage MEJORADO --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Viaje</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Voyage</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $voyage->voyage_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Embarcación</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->leadVessel->name ?? 'No asignada' }}</dd>
                            @if($voyage->leadVessel?->registration_number)
                                <dd class="text-xs text-gray-500">Reg: {{ $voyage->leadVessel->registration_number }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->originPort->code ?? 'N/A' }} → {{ $voyage->destinationPort->code ?? 'N/A' }}
                            </dd>
                            <dd class="text-xs text-gray-500">
                                {{ $voyage->originPort->name ?? 'N/A' }} → {{ $voyage->destinationPort->name ?? 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Shipments</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->shipments->count() }} envíos</dd>
                            <dd class="text-xs text-gray-500">{{ $voyage->billsOfLading->count() }} BL</dd>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Validaciones Pre-envío MEJORADAS --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Validaciones Pre-envío</h3>
                        <button onclick="validateData()" class="text-sm text-indigo-600 hover:text-indigo-500">
                            Revalidar
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4" id="validationContent">
                    {{-- Contenido dinámico --}}
                </div>
            </div>

             {{-- ===================================================================== --}}
                    {{-- DISEÑO UX PROGRESIVO AFIP - SECUENCIA VISUAL INTUITIVA --}}
                    {{-- ===================================================================== --}}

                    {{-- INDICADOR DE PROGRESO AFIP --}}
                    <div class="mt-6 mb-8">
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
                                <h3 class="text-lg font-medium text-blue-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Proceso AFIP - Hidrovía Paraná
                                </h3>
                                <p class="mt-1 text-sm text-blue-700">
                                    Secuencia oficial requerida por AFIP para manifiestos internacionales
                                </p>
                            </div>
                            
                            {{-- BARRA DE PROGRESO VISUAL --}}
                            <div class="px-6 py-6">
                                <div class="flex items-center justify-between">
                                    
                                    {{-- PASO 1: MIC/DTA --}}
                                    <div class="flex items-center flex-1">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full 
                                            @if($micdta_status?->status === 'sent')
                                                bg-green-500 text-white
                                            @elseif($micdta_status?->status === 'sending')
                                                bg-yellow-500 text-white animate-pulse
                                            @else
                                                bg-gray-300 text-gray-600
                                            @endif">
                                            @if($micdta_status?->status === 'sent')
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($micdta_status?->status === 'sending')
                                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            @else
                                                <span class="text-sm font-medium">1</span>
                                            @endif
                                        </div>
                                        <div class="ml-3 min-w-0 flex-1">
                                            <p class="text-sm font-medium 
                                                @if($micdta_status?->status === 'sent') text-green-700 
                                                @elseif($micdta_status?->status === 'sending') text-yellow-700
                                                @else text-gray-500 @endif">
                                                MIC/DTA Manifiesto
                                            </p>
                                            <p class="text-xs 
                                                @if($micdta_status?->status === 'sent') text-green-600
                                                @elseif($micdta_status?->status === 'sending') text-yellow-600  
                                                @else text-gray-400 @endif">
                                                @if($micdta_status?->status === 'sent')
                                                    ✅ Enviado exitosamente
                                                @elseif($micdta_status?->status === 'sending')
                                                    🔄 Enviando...
                                                @else
                                                    📋 Registrar títulos y envíos
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    
                                    {{-- FLECHA 1 → 2 --}}
                                    <div class="flex-shrink-0 px-4">
                                        <svg class="w-5 h-5 
                                            @if($micdta_status?->status === 'sent') text-green-400 
                                            @else text-gray-300 @endif" 
                                            fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    
                                    {{-- PASO 2: GPS TRACKING --}}
                                    <div class="flex items-center flex-1" id="gps-step">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full
                                            @if($micdta_status?->status === 'sent')
                                                bg-blue-100 text-blue-600 border-2 border-blue-300
                                            @else
                                                bg-gray-200 text-gray-400
                                            @endif">
                                            @if($micdta_status?->status === 'sent')
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                            @else
                                                <span class="text-sm font-medium">2</span>
                                            @endif
                                        </div>
                                        <div class="ml-3 min-w-0 flex-1">
                                            <p class="text-sm font-medium
                                                @if($micdta_status?->status === 'sent') text-blue-700
                                                @else text-gray-500 @endif">
                                                Seguimiento GPS
                                            </p>
                                            <p class="text-xs 
                                                @if($micdta_status?->status === 'sent') text-blue-600
                                                @else text-gray-400 @endif">
                                                @if($micdta_status?->status === 'sent')
                                                    🛰️ Actualizar posición en ruta
                                                @else
                                                    ⏳ Requiere MIC/DTA enviado
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    
                                    {{-- FLECHA 2 → 3 --}}
                                    <div class="flex-shrink-0 px-4">
                                        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    
                                    {{-- PASO 3: ARRIBO --}}
                                    <div class="flex items-center flex-1">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-200 text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3 min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-500">Notificar Arribo</p>
                                            <p class="text-xs text-gray-400">🏁 Llegada a destino</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

            {{-- Formulario de Envío ACTUALIZADO --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Envío MIC/DTA</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        <span class="font-medium">Flujo secuencial AFIP:</span> 
                        RegistrarTitEnvios → RegistrarEnvios → RegistrarMicDta
                    </p>
                </div>
                <div class="px-6 py-6">
                    
                    <form id="micDtaSendForm" action="{{ route('company.simple.micdta.send', $voyage) }}" method="POST">
                        @csrf
                        
                        {{-- Opciones de Envío --}}
                        <div class="space-y-4 mb-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-3">Opciones de Envío</h4>
                                
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input id="test_mode" name="test_mode" type="checkbox" checked 
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="test_mode" class="ml-2 block text-sm text-gray-900">
                                            <span class="font-medium">Modo de prueba</span> - Usar ambiente homologación AFIP
                                        </label>
                                    </div>
                                    
                                    @if($micdta_status && $micdta_status->status !== 'pending')
                                        <div class="flex items-center">
                                            <input id="force_send" name="force_send" type="checkbox" 
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="force_send" class="ml-2 block text-sm text-gray-900">
                                                <span class="font-medium">Forzar reenvío</span> - Ya fue enviado anteriormente
                                            </label>
                                        </div>
                                    @endif
                                    
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="debug_mode" name="debug_mode" type="checkbox" 
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-2">
                                            <label for="debug_mode" class="block text-sm text-gray-900">
                                                <span class="font-medium">Modo debug</span> - Mostrar XMLs generados
                                            </label>
                                            <p class="text-xs text-gray-500">Útil para depuración y verificación</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Notas del Usuario --}}
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">
                                    Notas (opcional)
                                </label>
                                <textarea id="notes" name="notes" rows="3" 
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                          placeholder="Agregar notas sobre este envío..."></textarea>
                            </div>
                        </div>
                        
                        {{-- Botones de Acción MEJORADOS --}}
                        <div class="flex justify-between items-center">
                            <div class="flex space-x-3">
                                <button type="button" onclick="previewXml()" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview XML
                                </button>
                                
                                <button type="button" onclick="validateData()" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Validar Datos
                                </button>
                            </div>
                            
                            <button type="submit" id="sendButton"
                                    class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <span id="sendButtonText">Enviar MIC/DTA</span>
                            </button>
                        </div>
                        
                    </form>

                   

                    {{-- SECCIÓN GPS - SOLO SE MUESTRA SI MIC/DTA FUE ENVIADO --}}
                    @if($micdta_status?->status === 'sent')
                    <div class="mt-6 bg-white shadow rounded-lg border-l-4 border-blue-400" id="gps-section">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-lg font-medium text-blue-900">
                                            Paso 2: Seguimiento GPS en Ruta
                                        </h3>
                                        <p class="text-sm text-blue-700">
                                            MIC/DTA enviado ✅ - Ahora puede actualizar la posición GPS durante el viaje
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Habilitado
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            {{-- ESTADO GPS ACTUAL --}}
                            <div class="mb-6 bg-gray-50 rounded-lg p-4" id="current-gps-status">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">Estado GPS Actual</h4>
                                        <p class="text-sm text-gray-600 mt-1">Cargando información GPS...</p>
                                    </div>
                                    <button onclick="refreshGpsStatus()" 
                                            class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                        🔄 Actualizar
                                    </button>
                                </div>
                            </div>

                            {{-- ACCIONES GPS PRINCIPALES --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                {{-- OPCIÓN A: GPS AUTOMÁTICO --}}
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h4 class="text-sm font-medium text-gray-900">📱 GPS del Dispositivo</h4>
                                            <p class="text-xs text-gray-600 mt-1">
                                                Usar la ubicación actual de su dispositivo (recomendado)
                                            </p>
                                            <div class="mt-3 space-y-2">
                                                <button onclick="getCurrentGPS()" 
                                                        class="w-full bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors">
                                                    🎯 Obtener Mi Ubicación
                                                </button>
                                                <div id="current-coordinates" class="hidden">
                                                    <div class="text-xs bg-blue-50 p-2 rounded border">
                                                        <div id="coordinates-display"></div>
                                                        <button onclick="sendCurrentGPS()" 
                                                                class="mt-2 w-full bg-green-600 text-white px-3 py-1 rounded text-xs font-medium hover:bg-green-700">
                                                            📡 Enviar a AFIP Ahora
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- OPCIÓN B: COORDENADAS MANUALES --}}
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-orange-300 transition-colors">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h4 class="text-sm font-medium text-gray-900">✏️ Coordenadas Manuales</h4>
                                            <p class="text-xs text-gray-600 mt-1">
                                                Ingresar coordenadas específicas si conoce la posición exacta
                                            </p>
                                            <div class="mt-3 space-y-2">
                                                <input type="number" placeholder="Latitud (ej: -34.6118)" 
                                                    class="w-full px-3 py-1 border border-gray-300 rounded text-xs"
                                                    step="0.00000001" id="manual-lat">
                                                <input type="number" placeholder="Longitud (ej: -58.3960)" 
                                                    class="w-full px-3 py-1 border border-gray-300 rounded text-xs"
                                                    step="0.00000001" id="manual-lng">
                                                <button onclick="validateAndSendManual()" 
                                                        class="w-full bg-orange-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-orange-700 transition-colors">
                                                    🚀 Validar y Enviar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- INFORMACIÓN Y ACCIONES ADICIONALES --}}
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="flex flex-wrap gap-3 justify-center">
                                    <button onclick="showGpsHistory()" 
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                                        📊 Ver Historial GPS
                                    </button>
                                    <button onclick="showControlPoints()" 
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                                        🎯 Puntos de Control AFIP
                                    </button>
                                    <button onclick="showGpsConfig()" 
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                                        ⚙️ Configuración GPS
                                    </button>
                                </div>
                                
                                {{-- INFORMACIÓN IMPORTANTE --}}
                                <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">
                                                Requerimientos AFIP para GPS:
                                            </h3>
                                            <div class="mt-2 text-xs text-yellow-700">
                                                <ul class="list-disc pl-5 space-y-1">
                                                    <li>Actualizar posición mínimo cada <strong>15 minutos</strong> durante el viaje</li>
                                                    <li>Coordenadas dentro del rango de <strong>hidrovía Paraná</strong></li>
                                                    <li>Detección automática de <strong>puntos de control</strong> (Buenos Aires, Rosario, Asunción, Villeta)</li>
                                                    <li>Tolerancia mínima de movimiento: <strong>50 metros</strong></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- SECCIÓN PRÓXIMO PASO (ARRIBO) - SOLO MOSTRAR CUANDO CORRESPONDA --}}
                    @if($micdta_status?->status === 'sent')
                    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-200">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="mt-3">
                                <h3 class="text-sm font-medium text-gray-900">Paso 3: Notificar Arribo</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Una vez que llegue al destino final, deberá notificar el arribo a AFIP
                                </p>
                                <p class="mt-2 text-xs text-gray-400">
                                    Esta funcionalidad estará disponible próximamente
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            {{-- GPS Control Panel - Se agrega después del formulario MIC/DTA --}}
            <div class="mt-8 bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900">Gestión GPS - ActualizarPosicion AFIP</h3>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $company->ws_environment === 'production' ? 'PRODUCCIÓN' : 'TESTING' }}
                            </span>
                        </div>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        Envío de posición GPS actual a AFIP. Requiere MIC/DTA enviado exitosamente.
                    </p>
                </div>

                <div class="px-6 py-4">
                    {{-- Estado GPS Actual --}}
                    <div id="gps-status" class="mb-6">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">Estado GPS del Voyage</h4>
                                    <p class="text-sm text-gray-500">Cargando estado GPS...</p>
                                </div>
                                <button type="button" onclick="gpsManager?.loadCurrentGpsStatus()" 
                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Panel de Captura GPS --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        {{-- Columna Izquierda: GPS Automático --}}
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">📱 GPS del Navegador</h4>
                            
                            <div class="space-y-3">
                                {{-- Botón Obtener GPS --}}
                                <button type="button" id="btn-get-gps"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Obtener Posición GPS
                                </button>

                                {{-- Botón Enviar a AFIP --}}
                                <button type="button" id="btn-send-gps"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                                    </svg>
                                    Enviar Posición a AFIP
                                </button>

                                {{-- Botón GPS Automático --}}
                                <button type="button" id="btn-auto-gps"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293H15"/>
                                    </svg>
                                    Activar GPS Automático
                                </button>
                            </div>

                            {{-- Display Posición Actual --}}
                            <div id="current-position" class="mt-4">
                                {{-- Contenido dinámico via JavaScript --}}
                            </div>
                        </div>

                        {{-- Columna Derecha: Coordenadas Manuales --}}
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">✏️ Coordenadas Manuales</h4>
                            
                            <div class="space-y-3">
                                {{-- Input Latitud --}}
                                <div>
                                    <label for="input-latitude" class="block text-xs font-medium text-gray-700">
                                        Latitud (-90 a 90)
                                    </label>
                                    <input type="number" id="input-latitude" step="0.00000001"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        placeholder="-34.61180000">
                                </div>

                                {{-- Input Longitud --}}
                                <div>
                                    <label for="input-longitude" class="block text-xs font-medium text-gray-700">
                                        Longitud (-180 a 180)
                                    </label>
                                    <input type="number" id="input-longitude" step="0.00000001"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        placeholder="-58.39600000">
                                </div>

                                {{-- Validación en Tiempo Real --}}
                                <div id="coordinates-validation" class="text-xs">
                                    <div class="text-gray-500">Ingrese coordenadas para validar</div>
                                </div>

                                {{-- Botón Enviar Manual --}}
                                <button type="button" id="btn-send-manual"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z"/>
                                    </svg>
                                    Enviar Coordenadas Manuales
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Panel Inferior: Acciones Avanzadas --}}
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex flex-wrap gap-3">
                            
                            {{-- Botón Ver Historial --}}
                            <button type="button" id="btn-gps-history"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Ver Historial GPS
                            </button>

                            {{-- Botón Puntos de Control --}}
                            <button type="button" onclick="showControlPoints()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                Puntos de Control AFIP
                            </button>

                            {{-- Botón Configuración GPS --}}
                            <button type="button" onclick="showGpsConfig()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Configuración GPS
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Log de Actividad --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Log de Actividad</h3>
                </div>
                <div class="px-6 py-4" id="activityLog">
                    {{-- Contenido dinámico --}}
                </div>
            </div>

        </div>
    </div>

    {{-- Modal para Preview XML --}}
    <div id="xmlPreviewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Preview XML</h3>
                    <button onclick="closeXmlPreview()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="xmlPreviewContent" class="max-h-96 overflow-y-auto">
                    {{-- Contenido dinámico --}}
                </div>
            </div>
        </div>
    </div>
@push('scripts')

    <script>
        // Variables globales
        const voyageId = {{ $voyage->id }};
        let isProcessing = false;
        let statusInterval = null;

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
            loadInitialStatus();
            startStatusPolling();
        });

        function initializeForm() {
            const form = document.getElementById('micDtaSendForm');
            form.addEventListener('submit', handleFormSubmit);
        }

        function loadInitialStatus() {
            refreshStatus();
            validateData();
            loadActivityLog();
        }

        function startStatusPolling() {
            // Actualizar estado cada 30 segundos si está procesando
            statusInterval = setInterval(() => {
                if (isProcessing) {
                    refreshStatus();
                }
            }, 30000);
        }

        // FUNCIÓN PRINCIPAL: Envío del formulario
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            if (isProcessing) {
                showNotification('Ya hay un envío en proceso', 'warning');
                return;
            }

            const confirmSend = confirm('¿Está seguro de enviar el MIC/DTA a AFIP Argentina?');
            if (!confirmSend) return;

            setProcessingState(true);
            
            try {
                const formData = new FormData(e.target);
                
                const response = await fetch(e.target.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('MIC/DTA enviado exitosamente', 'success');
                    
                    // Mostrar detalles del resultado
                    if (result.data) {
                        showSuccessDetails(result.data);
                    }
                    
                    // Actualizar estado inmediatamente
                    setTimeout(() => {
                        refreshStatus();
                        loadActivityLog();
                    }, 2000);
                    
                } else {
                    showNotification(`Error: ${result.error}`, 'error');
                    if (result.details) {
                        console.error('Detalles del error:', result.details);
                    }
                }

            } catch (error) {
                console.error('Error en envío:', error);
                showNotification('Error de comunicación con el servidor', 'error');
            } finally {
                setProcessingState(false);
            }
        }

        // Actualizar estado del voyage
        async function refreshStatus() {
            try {
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/status`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();
                
                if (response.ok) {
                    updateStatusDisplay(result);
                    updateFormState(result);
                } else {
                    console.error('Error obteniendo estado:', result.error);
                }

            } catch (error) {
                console.error('Error en refreshStatus:', error);
            }
        }

        // Validar datos del voyage
        async function validateData() {
            try {
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/validate`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();
                updateValidationDisplay(result);

            } catch (error) {
                console.error('Error en validación:', error);
                document.getElementById('validationContent').innerHTML = 
                    '<div class="text-red-600">Error cargando validaciones</div>';
            }
        }

        // Preview XML
        async function previewXml() {
            try {
                showNotification('Generando preview XML...', 'info');
                
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/preview-xml`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();
                
                if (response.ok) {
                    showXmlPreview(result.preview);
                } else {
                    showNotification(`Error: ${result.error}`, 'error');
                }

            } catch (error) {
                console.error('Error en preview XML:', error);
                showNotification('Error generando preview', 'error');
            }
        }

        // Cargar log de actividad
        async function loadActivityLog() {
            try {
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/activity`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();
                updateActivityLog(result);

            } catch (error) {
                console.error('Error cargando actividad:', error);
            }
        }

        // Funciones de actualización de UI
        function updateStatusDisplay(statusData) {
            const content = document.getElementById('statusContent');
            const status = statusData.status;
            
            let statusClass = 'bg-gray-100 text-gray-800';
            if (status.current === 'sent') statusClass = 'bg-green-100 text-green-800';
            else if (status.current === 'error') statusClass = 'bg-red-100 text-red-800';
            else if (status.current === 'sending') statusClass = 'bg-blue-100 text-blue-800';

            content.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                            ${status.current.toUpperCase()}
                        </span>
                        ${status.last_sent_at ? `<p class="text-sm text-gray-600 mt-1">Último envío: ${new Date(status.last_sent_at).toLocaleString()}</p>` : ''}
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-900">Reintentos: ${status.retry_count || 0}</p>
                        ${statusData.tracks && Object.keys(statusData.tracks).length > 0 ? 
                            `<p class="text-sm text-gray-600">TRACKs: ${Object.values(statusData.tracks).flat().length}</p>` : ''}
                    </div>
                </div>
            `;

            // Actualizar estado de procesamiento
            isProcessing = ['sending', 'validating'].includes(status.current);
        }

      
        function updateValidationDisplay(validation) {
            const content = document.getElementById('validationContent');
            
            let html = '<div class="space-y-4">';
            
            // Estado general
            if (validation.can_process) {
                html += `
                    <div class="flex items-center text-green-600 bg-green-50 border border-green-200 rounded-lg p-3">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Voyage válido para envío MIC/DTA</span>
                    </div>
                `;
            } else {
                html += `
                    <div class="flex items-center text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Voyage NO válido para envío MIC/DTA</span>
                    </div>
                `;
            }

            // ERRORES (bloqueantes)
            if (validation.errors && validation.errors.length > 0) {
                html += `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <h4 class="text-sm font-bold text-red-800">ERRORES (deben corregirse antes del envío)</h4>
                        </div>
                        <ul class="text-sm text-red-700 space-y-2">
                `;
                validation.errors.forEach(error => {
                    html += `<li class="flex items-start"><span class="text-red-500 mr-2">•</span><span>${error}</span></li>`;
                });
                html += '</ul></div>';
            }

            // ADVERTENCIAS (no bloquean pero recomendable revisar)
            if (validation.warnings && validation.warnings.length > 0) {
                html += `
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <h4 class="text-sm font-bold text-yellow-800">ADVERTENCIAS (recomendable revisar)</h4>
                        </div>
                        <ul class="text-sm text-yellow-700 space-y-2">
                `;
                validation.warnings.forEach(warning => {
                    html += `<li class="flex items-start"><span class="text-yellow-500 mr-2">⚠</span><span>${warning}</span></li>`;
                });
                html += '</ul></div>';
            }

            // DETALLES (información positiva verificada)
            if (validation.details && validation.details.length > 0) {
                html += `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <h4 class="text-sm font-bold text-green-800">DATOS VERIFICADOS</h4>
                            <button onclick="toggleDetails()" class="ml-auto text-xs text-green-600 hover:text-green-500">
                                <span id="toggleDetailsText">Mostrar detalles</span>
                            </button>
                        </div>
                        <div id="detailsList" class="hidden">
                            <ul class="text-sm text-green-700 space-y-1">
                `;
                validation.details.forEach(detail => {
                    html += `<li class="flex items-start"><span class="text-green-500 mr-2">✓</span><span>${detail}</span></li>`;
                });
                html += '</ul></div></div>';
            }

            // Resumen de conteos
            const errorsCount = validation.errors ? validation.errors.length : 0;
            const warningsCount = validation.warnings ? validation.warnings.length : 0;
            const detailsCount = validation.details ? validation.details.length : 0;

            html += `
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Resumen de validación:</span>
                        <div class="space-x-4">
                            <span class="text-red-600">${errorsCount} errores</span>
                            <span class="text-yellow-600">${warningsCount} advertencias</span>
                            <span class="text-green-600">${detailsCount} verificados</span>
                        </div>
                    </div>
                </div>
            `;

            html += '</div>';
            content.innerHTML = html;
        }

        function toggleDetails() {
            const detailsList = document.getElementById('detailsList');
            const toggleText = document.getElementById('toggleDetailsText');
            
            if (detailsList.classList.contains('hidden')) {
                detailsList.classList.remove('hidden');
                toggleText.textContent = 'Ocultar detalles';
            } else {
                detailsList.classList.add('hidden');
                toggleText.textContent = 'Mostrar detalles';
            }
        }

        function showXmlPreview(preview) {
            const content = document.getElementById('xmlPreviewContent');
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="border-b border-gray-200 pb-2">
                        <h4 class="text-sm font-medium text-gray-900">RegistrarTitEnvios XML</h4>
                        <pre class="mt-2 text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>${escapeHtml(preview.titenvios_xml)}</code></pre>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">RegistrarEnvios XML</h4>
                        <pre class="mt-2 text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>${escapeHtml(preview.envios_xml)}</code></pre>
                    </div>
                </div>
            `;
            
            document.getElementById('xmlPreviewModal').classList.remove('hidden');
        }

        function closeXmlPreview() {
            document.getElementById('xmlPreviewModal').classList.add('hidden');
        }

        function setProcessingState(processing) {
            isProcessing = processing;
            const button = document.getElementById('sendButton');
            const buttonText = document.getElementById('sendButtonText');
            
            button.disabled = processing;
            buttonText.textContent = processing ? 'Enviando...' : 'Enviar MIC/DTA';
        }

        function showSuccessDetails(data) {
            let message = 'MIC/DTA enviado exitosamente';
            if (data.mic_dta_id) message += `\nID MIC/DTA: ${data.mic_dta_id}`;
            if (data.tracks_generated) message += `\nTRACKs generados: ${data.tracks_generated}`;
            
            alert(message);
        }

        function showNotification(message, type = 'info') {
            // Implementación simple con alert, puedes mejorar con toast notifications
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            alert(`${icons[type]} ${message}`);
        }

        function updateFormState(statusData) {
            const sendButton = document.getElementById('sendButton');
            const canSend = statusData.status.can_send && !isProcessing;
            
            sendButton.disabled = !canSend;
        }

        function updateActivityLog(data) {
            const log = document.getElementById('activityLog');
            
            if (data.recent_transactions && data.recent_transactions.length > 0) {
                let html = '<div class="space-y-3">';
                data.recent_transactions.forEach(transaction => {
                    html += `
                        <div class="border-l-4 ${transaction.status === 'sent' ? 'border-green-400' : 'border-red-400'} pl-3">
                            <p class="text-sm font-medium">${transaction.transaction_id}</p>
                            <p class="text-xs text-gray-600">${new Date(transaction.created_at).toLocaleString()}</p>
                            <p class="text-xs ${transaction.status === 'sent' ? 'text-green-600' : 'text-red-600'}">${transaction.status}</p>
                            ${transaction.error_message ? `<p class="text-xs text-red-600">${transaction.error_message}</p>` : ''}
                        </div>
                    `;
                });
                html += '</div>';
                log.innerHTML = html;
            } else {
                log.innerHTML = '<p class="text-gray-500 text-sm">Sin actividad reciente</p>';
            }
        }

        // Utilidades
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '{{ csrf_token() }}';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Limpiar interval al salir
        window.addEventListener('beforeunload', function() {
            if (statusInterval) {
                clearInterval(statusInterval);
            }
        });

                
        // Variables globales simples
        let currentGPSData = null;

        // Función para obtener GPS del dispositivo
        function getCurrentGPS() {
            if (!navigator.geolocation) {
                alert('❌ Su navegador no soporta GPS');
                return;
            }

            const button = event.target;
            button.disabled = true;
            button.textContent = '🔄 Obteniendo GPS...';

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentGPSData = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    
                    // Mostrar coordenadas
                    document.getElementById('coordinates-display').innerHTML = `
                        <strong>📍 Posición obtenida:</strong><br>
                        Lat: ${currentGPSData.lat.toFixed(6)}<br>
                        Lng: ${currentGPSData.lng.toFixed(6)}<br>
                        <small>Precisión: ±${Math.round(currentGPSData.accuracy)}m</small>
                    `;
                    
                    document.getElementById('current-coordinates').classList.remove('hidden');
                    button.textContent = '✅ GPS Obtenido';
                    
                    // Validar si está en punto de control
                    checkControlPoint(currentGPSData.lat, currentGPSData.lng);
                },
                function(error) {
                    button.disabled = false;
                    button.textContent = '🎯 Obtener Mi Ubicación';
                    alert('❌ Error obteniendo GPS: ' + error.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        // Función para enviar GPS actual a AFIP
        async function sendCurrentGPS() {
            if (!currentGPSData) {
                alert('❌ Primero debe obtener la posición GPS');
                return;
            }

            const button = event.target;
            button.disabled = true;
            button.textContent = '📡 Enviando a AFIP...';

            try {
                const response = await fetch(`/simple/webservices/micdta/{{ $voyage->id }}/actualizar-posicion`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        latitude: currentGPSData.lat,
                        longitude: currentGPSData.lng,
                        source: 'dispositivo'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    if (result.skipped) {
                        alert('ℹ️ ' + result.message);
                    } else {
                        let message = '✅ Posición GPS enviada exitosamente a AFIP';
                        if (result.control_point_detected) {
                            message += '\n\n🎯 Punto de control detectado: ' + result.control_point_detected.nombre;
                        }
                        alert(message);
                        
                        // Actualizar estado GPS
                        refreshGpsStatus();
                    }
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Error de comunicación con AFIP');
            } finally {
                button.disabled = false;
                button.textContent = '📡 Enviar a AFIP Ahora';
            }
        }

        // Función para validar y enviar coordenadas manuales
        async function validateAndSendManual() {
            const lat = parseFloat(document.getElementById('manual-lat').value);
            const lng = parseFloat(document.getElementById('manual-lng').value);
            
            if (isNaN(lat) || isNaN(lng)) {
                alert('❌ Por favor ingrese coordenadas válidas');
                return;
            }
            
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                alert('❌ Coordenadas fuera de rango válido');
                return;
            }

            const button = event.target;
            button.disabled = true;
            button.textContent = '🚀 Enviando...';

            try {
                const response = await fetch(`/simple/webservices/micdta/{{ $voyage->id }}/actualizar-posicion`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        latitude: lat,
                        longitude: lng,
                        source: 'manual'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Coordenadas enviadas exitosamente a AFIP');
                    refreshGpsStatus();
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Error de comunicación');
            } finally {
                button.disabled = false;
                button.textContent = '🚀 Validar y Enviar';
            }
        }

        // Funciones auxiliares simples
        async function refreshGpsStatus() {
            // Actualizar estado GPS actual
            console.log('🔄 Actualizando estado GPS...');
        }

        function checkControlPoint(lat, lng) {
            // Verificar si está cerca de punto de control
            console.log('🎯 Verificando puntos de control...');
        }

        function showGpsHistory() {
            alert('📊 Función historial GPS próximamente');
        }

        function showControlPoints() {
            alert('🎯 Puntos de Control AFIP:\n\n• Buenos Aires (ARBUE)\n• Rosario (ARROS)\n• Asunción (PYASU)\n• Terminal Villeta (PYTVT)');
        }

        function showGpsConfig() {
            alert('⚙️ Configuración GPS AFIP:\n\n• Intervalo mínimo: 15 minutos\n• Tolerancia: 50 metros\n• Ambiente: {{ $company->ws_environment ?? 'testing' }}');
        }
        

        /**
         * ================================================================================
         * GPS JAVASCRIPT SIMPLE - SIN CLASES (MÁS FÁCIL DE ENTENDER)
         * ================================================================================
         */

        // Variables globales simples
        let voyageId = {{ $voyage->id }};
        let currentGpsPosition = null;
        let isGpsUpdating = false;

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🌍 Inicializando GPS para voyage:', voyageId);
            
            // Configurar botones GPS
            setupGpsButtons();
            
            // Cargar estado GPS inicial
            loadCurrentGpsStatus();
            
            // Configurar validación coordenadas manuales
            setupManualCoordinates();
        });

        /**
         * Configurar event listeners de los botones GPS
         */
        function setupGpsButtons() {
            // Botón Obtener GPS del navegador
            const btnGetGps = document.getElementById('btn-get-gps');
            if (btnGetGps) {
                btnGetGps.addEventListener('click', getCurrentGpsPosition);
            }

            // Botón Enviar GPS a AFIP
            const btnSendGps = document.getElementById('btn-send-gps');
            if (btnSendGps) {
                btnSendGps.addEventListener('click', sendGpsToAfip);
            }

            // Botón GPS Automático
            const btnAutoGps = document.getElementById('btn-auto-gps');
            if (btnAutoGps) {
                btnAutoGps.addEventListener('click', toggleAutoGps);
            }

            // Botón Enviar Manual
            const btnSendManual = document.getElementById('btn-send-manual');
            if (btnSendManual) {
                btnSendManual.addEventListener('click', sendManualCoordinates);
            }

            // Botón Ver Historial
            const btnGpsHistory = document.getElementById('btn-gps-history');
            if (btnGpsHistory) {
                btnGpsHistory.addEventListener('click', showGpsHistory);
            }
        }

        /**
         * Obtener posición GPS del navegador
         */
        function getCurrentGpsPosition() {
            if (!navigator.geolocation) {
                showNotification('error', 'Su navegador no soporta GPS');
                return;
            }

            const btn = document.getElementById('btn-get-gps');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Obteniendo GPS...';
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // GPS obtenido exitosamente
                    currentGpsPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: new Date()
                    };

                    displayCurrentPosition();
                    showNotification('success', `GPS obtenido: ${currentGpsPosition.lat.toFixed(6)}, ${currentGpsPosition.lng.toFixed(6)}`);

                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg> Obtener Posición GPS';
                    }
                },
                function(error) {
                    // Error obteniendo GPS
                    let errorMsg = 'Error obteniendo GPS';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Permiso GPS denegado';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'GPS no disponible';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Timeout GPS';
                            break;
                    }

                    showNotification('error', errorMsg);

                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg> Obtener Posición GPS';
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        }

        /**
         * Enviar posición GPS a AFIP
         */
        async function sendGpsToAfip() {
            if (!currentGpsPosition) {
                showNotification('warning', 'Debe obtener la posición GPS primero');
                return;
            }

            if (isGpsUpdating) {
                return;
            }

            isGpsUpdating = true;
            const btn = document.getElementById('btn-send-gps');
            
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg> Enviando a AFIP...';
            }

            try {
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/actualizar-posicion`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        latitude: currentGpsPosition.lat,
                        longitude: currentGpsPosition.lng,
                        source: 'navegador',
                        notes: 'Actualización GPS desde formulario web'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (result.skipped) {
                        showNotification('info', result.message);
                    } else {
                        showNotification('success', '✅ Posición enviada exitosamente a AFIP');

                        // Mostrar información adicional
                        if (result.control_point_detected) {
                            showNotification('info', `🎯 Punto de control: ${result.control_point_detected.nombre}`);
                        }

                        if (result.distance_moved_meters) {
                            showNotification('info', `📍 Movimiento: ${result.distance_moved_meters}m`);
                        }
                    }

                    // Recargar estado GPS
                    loadCurrentGpsStatus();

                } else {
                    showNotification('error', `❌ Error AFIP: ${result.error}`);
                }

            } catch (error) {
                console.error('❌ Error enviando GPS:', error);
                showNotification('error', 'Error de comunicación con AFIP');
            } finally {
                isGpsUpdating = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path></svg> Enviar Posición a AFIP';
                }
            }
        }

        /**
         * Mostrar la posición GPS actual en la pantalla
         */
        function displayCurrentPosition() {
            if (!currentGpsPosition) return;

            const positionDiv = document.getElementById('current-position');
            if (positionDiv) {
                positionDiv.innerHTML = `
                    <div class="mt-3 p-3 bg-light border rounded">
                        <h6><i class="fas fa-crosshairs text-primary"></i> Posición GPS Actual</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Latitud:</strong> ${currentGpsPosition.lat.toFixed(8)}
                            </div>
                            <div class="col-md-6">
                                <strong>Longitud:</strong> ${currentGpsPosition.lng.toFixed(8)}
                            </div>
                        </div>
                        ${currentGpsPosition.accuracy ? `
                            <div class="mt-2">
                                <strong>Precisión:</strong> ±${currentGpsPosition.accuracy.toFixed(0)} metros
                            </div>
                        ` : ''}
                        <div class="mt-2">
                            <strong>Obtenida:</strong> ${currentGpsPosition.timestamp.toLocaleString()}
                        </div>
                    </div>
                `;
            }

            // Actualizar campos manuales también
            const latInput = document.getElementById('input-latitude');
            const lngInput = document.getElementById('input-longitude');
            
            if (latInput) latInput.value = currentGpsPosition.lat.toFixed(8);
            if (lngInput) lngInput.value = currentGpsPosition.lng.toFixed(8);
        }

        /**
         * Configurar inputs de coordenadas manuales
         */
        function setupManualCoordinates() {
            const latInput = document.getElementById('input-latitude');
            const lngInput = document.getElementById('input-longitude');

            if (latInput) {
                latInput.addEventListener('input', validateManualCoordinates);
            }

            if (lngInput) {
                lngInput.addEventListener('input', validateManualCoordinates);
            }
        }

        /**
         * Validar coordenadas manuales en tiempo real
         */
        async function validateManualCoordinates() {
            const latInput = document.getElementById('input-latitude');
            const lngInput = document.getElementById('input-longitude');
            const validationDiv = document.getElementById('coordinates-validation');

            if (!latInput || !lngInput || !validationDiv) return;

            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);

            if (isNaN(lat) || isNaN(lng)) {
                validationDiv.innerHTML = '<div class="text-gray-500 text-xs">Ingrese coordenadas válidas</div>';
                return;
            }

            try {
                const response = await fetch('/simple/webservices/micdta/validar-coordenadas', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ latitude: lat, longitude: lng })
                });

                const result = await response.json();

                if (result.success) {
                    let html = '<div class="mt-2 text-xs">';
                    
                    // GPS válido
                    html += `<div class="text-${result.validaciones.gps_valido.valido ? 'green' : 'red'}-600">
                        ✓ ${result.validaciones.gps_valido.mensaje}
                    </div>`;

                    // Hidrovía
                    html += `<div class="text-${result.validaciones.hidrovia_parana.valido ? 'green' : 'yellow'}-600">
                        ${result.validaciones.hidrovia_parana.valido ? '✓' : '⚠'} ${result.validaciones.hidrovia_parana.mensaje}
                    </div>`;

                    // Punto control
                    if (result.validaciones.punto_control.detectado) {
                        html += `<div class="text-blue-600">
                            📍 ${result.validaciones.punto_control.mensaje}
                        </div>`;
                    }

                    html += '</div>';
                    validationDiv.innerHTML = html;
                }
            } catch (error) {
                validationDiv.innerHTML = '<div class="text-red-600 text-xs">Error validando</div>';
            }
        }

        /**
         * Enviar coordenadas manuales
         */
        async function sendManualCoordinates() {
            const latInput = document.getElementById('input-latitude');
            const lngInput = document.getElementById('input-longitude');

            if (!latInput || !lngInput) return;

            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);

            if (isNaN(lat) || isNaN(lng)) {
                showNotification('error', 'Coordenadas inválidas');
                return;
            }

            // Simular posición GPS con coordenadas manuales
            currentGpsPosition = { 
                lat, 
                lng, 
                timestamp: new Date(), 
                source: 'manual',
                accuracy: null 
            };

            // Enviar a AFIP
            await sendGpsToAfip();
        }

        /**
         * Ver historial GPS
         */
        async function showGpsHistory() {
            try {
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/historial-posiciones?days=7`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    
                    let info = `📍 Historial GPS (7 días)\nVoyage: ${data.voyage_number}\nPosiciones: ${data.total_posiciones}\n\n`;
                    
                    if (data.estadisticas) {
                        info += `📊 Estadísticas:\n`;
                        info += `• Distancia: ${data.estadisticas.distancia_total_km} km\n`;
                        info += `• Puntos control: ${data.estadisticas.puntos_control_detectados}\n`;
                        info += `• Tiempo activo: ${data.estadisticas.periodo_activo_horas}h\n`;
                        info += `• Velocidad: ${data.estadisticas.velocidad_promedio_kmh} km/h`;
                    }
                    
                    alert(info); // Puedes reemplazar por un modal más bonito
                } else {
                    showNotification('error', 'Error cargando historial');
                }
            } catch (error) {
                showNotification('error', 'Error de comunicación');
            }
        }

        /**
         * Cargar estado GPS actual del voyage
         */
        async function loadCurrentGpsStatus() {
            try {
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/estado-gps`, {
                    headers: {
                        'X-CSRF-TOKEN': getCSRFToken(),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    updateGpsStatusDisplay(data.estado_gps);
                }
            } catch (error) {
                console.error('❌ Error cargando estado GPS:', error);
            }
        }

        /**
         * Actualizar display del estado GPS
         */
        function updateGpsStatusDisplay(estadoGps) {
            const statusDiv = document.getElementById('gps-status');
            if (!statusDiv) return;

            let html = '<div class="bg-gray-50 rounded-lg p-4"><div class="flex items-center justify-between"><div>';
            html += '<h4 class="text-sm font-medium text-gray-900">Estado GPS del Voyage</h4>';
            
            if (estadoGps.tiene_coordenadas) {
                html += `<p class="text-sm text-green-600">✅ ${estadoGps.shipments_con_gps}/${estadoGps.total_shipments} shipments con GPS</p>`;
                
                if (estadoGps.ultima_actualizacion_afip) {
                    const lastUpdate = new Date(estadoGps.ultima_actualizacion_afip.enviada_at);
                    html += `<p class="text-xs text-gray-500">📡 Última actualización AFIP: ${lastUpdate.toLocaleString()}</p>`;
                } else {
                    html += `<p class="text-xs text-yellow-600">⚠ Sin actualizaciones enviadas a AFIP</p>`;
                }
            } else {
                html += '<p class="text-sm text-gray-500">⭕ Sin coordenadas GPS</p>';
            }
            
            html += '</div></div></div>';
            statusDiv.innerHTML = html;
        }

        /**
         * GPS Automático (funcionalidad básica)
         */
        let autoGpsInterval = null;

        function toggleAutoGps() {
            const btn = document.getElementById('btn-auto-gps');
            
            if (autoGpsInterval) {
                // Detener GPS automático
                clearInterval(autoGpsInterval);
                autoGpsInterval = null;
                
                if (btn) {
                    btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293H15"></path></svg> Activar GPS Automático';
                    btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    btn.classList.add('bg-white', 'hover:bg-gray-50', 'border-gray-300', 'text-gray-700');
                }
                
                showNotification('info', 'GPS automático desactivado');
            } else {
                // Iniciar GPS automático
                autoGpsInterval = setInterval(() => {
                    getCurrentGpsPosition();
                    // Auto-enviar después de 2 segundos si hay posición
                    setTimeout(() => {
                        if (currentGpsPosition && !isGpsUpdating) {
                            sendGpsToAfip();
                        }
                    }, 2000);
                }, 15 * 60 * 1000); // Cada 15 minutos

                if (btn) {
                    btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"></path></svg> Parar GPS Automático';
                    btn.classList.remove('bg-white', 'hover:bg-gray-50', 'border-gray-300', 'text-gray-700');
                    btn.classList.add('bg-red-600', 'hover:bg-red-700');
                }
                
                showNotification('success', 'GPS automático activado (cada 15 min)');
                
                // Ejecutar una vez inmediatamente
                getCurrentGpsPosition();
            }
        }

        /**
         * Utilidades
         */
        function getCSRFToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        function showNotification(type, message) {
            // Sistema simple de notificaciones con alert()
            // Puedes reemplazar por toast notifications más bonitas
            const icons = {
                success: '✅',
                error: '❌', 
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            console.log(`${type.toUpperCase()}: ${message}`);
            
            // Por ahora usar alert, después puedes hacer toasts
            if (type === 'error' || type === 'warning') {
                alert(`${icons[type]} ${message}`);
            } else {
                // Solo mostrar en consola los success/info para no molestar
                console.log(`${icons[type]} ${message}`);
            }
        }

        // Limpiar interval al salir
        window.addEventListener('beforeunload', function() {
            if (autoGpsInterval) {
                clearInterval(autoGpsInterval);
            }
        });

        </script>

@endpush


</x-app-layout>