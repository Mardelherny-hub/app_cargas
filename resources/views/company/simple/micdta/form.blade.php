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

            {{-- Resumen del Viaje MEJORADO --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Viaje</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Viaje</dt>
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

                            {{-- Botón "Métodos Avanzados AFIP" --}}
                            <a href="{{ route('company.simple.micdta.methods-dashboard', $voyage) }}" 
                            class="inline-flex items-center px-4 py-2 border border-orange-300 shadow-sm text-sm font-medium rounded-md text-orange-700 bg-orange-50 hover:bg-orange-100">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
                                </svg>
                                Métodos Avanzados AFIP
                            </a>
                            
                            <button type="submit" id="sendButton"
                                    class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <span id="sendButtonText">Enviar MIC/DTA</span>
                            </button>
                        </div>
                        
                    </form>


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
    const voyageId = {{ $voyage->id }};
    const environment = '{{ config('webservices.environment', 'testing') }}';
    
    window.voyageId = voyageId;
    window.environment = environment;
    
    console.log('🔧 Variables Blade inicializadas:', {
        voyageId: voyageId,
        environment: environment
    });
</script>

{{-- JavaScript Principal (SEGUNDO) --}}
<script>
       /**
 * ================================================================================
 * SISTEMA COMPLETO MIC/DTA ARGENTINA + GPS - JAVASCRIPT UNIFICADO
 * ================================================================================
 * 
 * Sistema integrado para:
 * - Envío MIC/DTA a AFIP Argentina
 * - Gestión GPS (obtener, enviar, automático, manual)
 * - Actualización de estados en tiempo real
 * - Validación y logs de actividad
 * - Preview XML y utilidades
 * 
 * Versión: Completa - Sin conflictos - Código limpio
 * ================================================================================
 */

class MicDtaFormManager {
    constructor(voyageId) {
        this.voyageId = voyageId;
        
        // Estado del sistema
        this.state = {
            isProcessing: false,
            isGpsUpdating: false,
            gpsAutoInterval: null,
            statusPollingInterval: null
        };
        
        // GPS Data
        this.gpsData = {
            currentPosition: null,
            lastUpdate: null,
            autoMode: false
        };
        
        // Referencias DOM
        this.elements = {};
        
        this.initialize();
    }

    /**
     * ================================================================================
     * INICIALIZACIÓN SISTEMA
     * ================================================================================
     */
    initialize() {
        this.cacheElements();
        this.setupEventListeners();
        this.loadInitialData();
        this.startStatusPolling();
        
        console.log('🚀 Sistema MIC/DTA + GPS inicializado para Viaje:', this.voyageId);

        // Hacer disponible globalmente para onclick
        window.micDtaManager = this;
    }

    cacheElements() {
        // Formulario principal MIC/DTA
        this.elements.form = document.getElementById('micDtaSendForm');
        this.elements.sendButton = document.getElementById('sendButton');
        
        // Elementos GPS
        this.elements.btnGetGps = document.getElementById('btn-get-gps');
        this.elements.btnSendGps = document.getElementById('btn-send-gps');
        this.elements.btnAutoGps = document.getElementById('btn-auto-gps');
        this.elements.btnSendManual = document.getElementById('btn-send-manual');
        this.elements.btnGpsHistory = document.getElementById('btn-gps-history');
        
        // Contenedores de información
        this.elements.gpsStatus = document.getElementById('gps-status');
        this.elements.currentPosition = document.getElementById('current-position');
        this.elements.activityLog = document.getElementById('activityLog');
        this.elements.xmlPreview = document.getElementById('xmlPreviewModal');
    }

    setupEventListeners() {
        // Formulario principal MIC/DTA
        if (this.elements.form) {
            this.elements.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Botones GPS
        if (this.elements.btnGetGps) {
            this.elements.btnGetGps.addEventListener('click', () => this.getCurrentGpsPosition());
        }
        
        if (this.elements.btnSendGps) {
            this.elements.btnSendGps.addEventListener('click', () => this.sendGpsToAfip());
        }
        
        if (this.elements.btnAutoGps) {
            this.elements.btnAutoGps.addEventListener('click', () => this.toggleAutoGps());
        }
        
        if (this.elements.btnSendManual) {
            this.elements.btnSendManual.addEventListener('click', () => this.sendManualCoordinates());
        }
        
        if (this.elements.btnGpsHistory) {
            this.elements.btnGpsHistory.addEventListener('click', () => this.showGpsHistory());
        }

       
        
        // Cleanup al salir
        window.addEventListener('beforeunload', () => this.cleanup());
    }

    loadInitialData() {
        this.refreshStatus();
        this.validateData();
        this.loadActivityLog();
    }

    /**
     * ================================================================================
     * MÓDULO MIC/DTA - ENVÍO Y GESTIÓN FORMULARIO
     * ================================================================================
     */
    async handleFormSubmit(event) {
        event.preventDefault();
        
        if (this.state.isProcessing) {
            this.showNotification('Ya hay un envío en proceso', 'warning');
            return;
        }

        const confirmSend = confirm('¿Está seguro de enviar el MIC/DTA a AFIP Argentina?\n\nEsta acción no se puede deshacer.');
        if (!confirmSend) return;

        this.setProcessingState(true);
        
        try {
            const formData = new FormData(event.target);
            
            const response = await fetch(event.target.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('MIC/DTA enviado exitosamente', 'success');
                
                if (result.data) {
                    this.showSuccessDetails(result.data);
                }
                
                // Actualizar datos después de 2 segundos
                setTimeout(() => {
                    this.refreshStatus();
                    this.loadActivityLog();
                }, 2000);
                
            } else {
                this.showNotification(`Error: ${result.error}`, 'error');
                if (result.details) {
                    console.error('Detalles del error:', result.details);
                }
            }

        } catch (error) {
            console.error('Error en envío MIC/DTA:', error);
            this.showNotification('Error de comunicación con el servidor', 'error');
        } finally {
            this.setProcessingState(false);
        }
    }

    async refreshStatus() {
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${this.voyageId}/status`, {
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateFormState(data);
            }
        } catch (error) {
            console.error('Error actualizando estado:', error);
        }
    }

    async validateData() {
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${this.voyageId}/validate`, {
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateValidationStatus(data);
            }
        } catch (error) {
            console.error('Error en validación:', error);
        }
    }

    async loadActivityLog() {
        try {
            const response = await fetch(`/company/simple/webservices/micdta/${this.voyageId}/activity`, {
                headers: {
                    'X-CSRF-TOKEN': this.getCSRFToken(),
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateActivityLog(data);
            }
        } catch (error) {
            console.error('Error cargando log:', error);
        }
    }

   
    /**
     * ================================================================================
     * FUNCIONES DE ACTUALIZACIÓN DE UI
     * ================================================================================
     */
    displayCurrentPosition() {
        if (!this.gpsData.currentPosition || !this.elements.currentPosition) return;

        const pos = this.gpsData.currentPosition;
        this.elements.currentPosition.innerHTML = `
            <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded">
                <h6 class="text-sm font-medium text-green-800 mb-2">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    </svg>
                    Posición GPS Actual
                </h6>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Latitud:</strong> ${pos.lat.toFixed(8)}
                    </div>
                    <div>
                        <strong>Longitud:</strong> ${pos.lng.toFixed(8)}
                    </div>
                    ${pos.accuracy ? `<div class="col-span-2"><strong>Precisión:</strong> ${Math.round(pos.accuracy)}m</div>` : ''}
                    <div class="col-span-2 text-xs text-gray-600">
                        <strong>Obtenido:</strong> ${pos.timestamp.toLocaleString()}
                    </div>
                </div>
            </div>
        `;
    }

    updateGpsStatus(estadoGps) {
        if (!this.elements.gpsStatus || !estadoGps) return;

        let html = '<div class="bg-gray-50 rounded-lg p-4"><div class="flex items-center justify-between"><div>';
        html += '<h4 class="text-sm font-medium text-gray-900">Estado GPS del Viaje</h4>';
        
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
        this.elements.gpsStatus.innerHTML = html;
    }

    displayGpsHistory(data) {
        let info = `📍 Historial GPS (7 días)\nVoyage: ${data.voyage_number}\nPosiciones: ${data.total_posiciones}\n\n`;
        
        if (data.estadisticas) {
            info += `📊 Estadísticas:\n`;
            info += `• Distancia: ${data.estadisticas.distancia_total_km} km\n`;
            info += `• Puntos control: ${data.estadisticas.puntos_control_detectados}\n`;
            info += `• Tiempo activo: ${data.estadisticas.periodo_activo_horas}h\n`;
            info += `• Velocidad: ${data.estadisticas.velocidad_promedio_kmh} km/h`;
        }
        
        alert(info);
    }

    setProcessingState(isProcessing) {
        this.state.isProcessing = isProcessing;
        
        if (this.elements.sendButton) {
            this.elements.sendButton.disabled = isProcessing;
            this.elements.sendButton.textContent = isProcessing ? 'Enviando...' : 'Enviar MIC/DTA';
        }
    }

    updateFormState(statusData) {
        if (statusData && statusData.status && this.elements.sendButton) {
            const canSend = statusData.status.can_send && !this.state.isProcessing;
            this.elements.sendButton.disabled = !canSend;
        }
    }

    updateValidationStatus(data) {
    console.log('Validación actualizada:', data);
    
    // Buscar un contenedor o crear notificación
    let validationContainer = document.getElementById('validation-status');
    
    if (!validationContainer) {
        // Si no existe, buscar el botón de validar y agregar después
        const validateButton = document.querySelector('[onclick*="validateData"]');
        if (validateButton) {
            validationContainer = document.createElement('div');
            validationContainer.id = 'validation-status';
            validationContainer.className = 'mt-4';
            validateButton.parentNode.insertBefore(validationContainer, validateButton.nextSibling);
        }
    }
    
    if (!validationContainer) {
        // Fallback: mostrar como notificación
        this.showValidationAsNotification(data);
        return;
    }
    
    // Construir HTML del estado de validación
    let html = '<div class="border rounded-lg p-4 ' + 
        (data.can_process ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50') + '">';
    
    // Estado principal
    html += '<div class="flex items-center mb-3">';
    html += '<div class="flex-shrink-0">';
    if (data.can_process) {
        html += '<svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
    } else {
        html += '<svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
    }
    html += '</div>';
    html += '<div class="ml-3">';
    html += '<h3 class="text-sm font-medium ' + (data.can_process ? 'text-green-800' : 'text-yellow-800') + '">';
    html += data.can_process ? '✅ Viaje válido para envío' : '⚠️ Viaje requiere atención';
    html += '</h3></div></div>';
    
    // Errores (si los hay)
    if (data.errors && data.errors.length > 0) {
        html += '<div class="mt-3"><h4 class="text-sm font-medium text-red-800">❌ Errores:</h4>';
        html += '<ul class="mt-2 text-sm text-red-700 list-disc list-inside">';
        data.errors.forEach(error => {
            html += `<li>${error}</li>`;
        });
        html += '</ul></div>';
    }
    
    // Warnings (si los hay)
    if (data.warnings && data.warnings.length > 0) {
        html += '<div class="mt-3"><h4 class="text-sm font-medium text-yellow-800">⚠️ Advertencias:</h4>';
        html += '<ul class="mt-2 text-sm text-yellow-700 list-disc list-inside">';
        data.warnings.forEach(warning => {
            html += `<li>${warning}</li>`;
        });
        html += '</ul></div>';
    }
    
    html += '</div>';
    validationContainer.innerHTML = html;
}

showValidationAsNotification(data) {
    let message = data.can_process ? 
        '✅ Validación OK - Viaje listo para envío' : 
        '⚠️ Validación con advertencias';
    
    if (data.errors && data.errors.length > 0) {
        message += `\n❌ Errores: ${data.errors.length}`;
    }
    if (data.warnings && data.warnings.length > 0) {
        message += `\n⚠️ Advertencias: ${data.warnings.length}`;
    }
    
    this.showNotification(message, data.can_process ? 'success' : 'warning');
}

    updateActivityLog(data) {
        if (!this.elements.activityLog || !data.recent_transactions) return;
        
        if (data.recent_transactions.length > 0) {
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
            this.elements.activityLog.innerHTML = html;
        } else {
            this.elements.activityLog.innerHTML = '<p class="text-gray-500 text-sm">Sin actividad reciente</p>';
        }
    }

    showSuccessDetails(data) {
        let message = 'MIC/DTA enviado exitosamente';
        if (data.mic_dta_id) message += `\nID MIC/DTA: ${data.mic_dta_id}`;
        if (data.tracks_generated) message += `\nTRACKs generados: ${data.tracks_generated}`;
        
        alert(message);
    }

    /**
     * ================================================================================
     * SISTEMA DE POLLING Y LIMPIEZA
     * ================================================================================
     */
    startStatusPolling() {
        // Actualizar estado cada 30 segundos si está procesando
        this.state.statusPollingInterval = setInterval(() => {
            if (this.state.isProcessing) {
                this.refreshStatus();
            }
        }, 30000);
    }

    cleanup() {
        if (this.state.gpsAutoInterval) {
            clearInterval(this.state.gpsAutoInterval);
        }
        
        if (this.state.statusPollingInterval) {
            clearInterval(this.state.statusPollingInterval);
        }
        
        console.log('🧹 Sistema MIC/DTA limpiado');
    }

    /**
     * ================================================================================
     * FUNCIONES AUXILIARES Y UTILIDADES
     * ================================================================================
     */
    getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    getSpinnerHTML() {
        return '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
    }

    showNotification(message, type = 'info') {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // Usar alert por simplicidad (se puede reemplazar por toast notifications)
        if (type === 'error' || type === 'warning') {
            alert(`${icons[type]} ${message}`);
        } else if (type === 'success') {
            // Solo mostrar success importantes
            if (message.includes('exitosamente')) {
                alert(`${icons[type]} ${message}`);
            }
        }
    }
}

/**
 * ================================================================================
 * FUNCIONES GLOBALES ADICIONALES (COMPATIBILIDAD)
 * ================================================================================
 */

// Función para Preview XML (llamada desde onclick en HTML)
async function previewXml() {
    if (!micDtaManager) {
        alert('❌ Sistema no inicializado');
        return;
    }

    try {
        const response = await fetch(`/company/simple/webservices/micdta/${micDtaManager.voyageId}/preview-xml`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': micDtaManager.getCSRFToken(),
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            const data = await response.json();
            showXmlPreviewModal(data.preview);
        } else {
            alert('❌ Error generando preview XML');
        }
    } catch (error) {
        console.error('Error en preview XML:', error);
        alert('❌ Error de comunicación');
    }
}

// Función para validar datos (llamada desde onclick en HTML)
async function validateData() {
    if (!micDtaManager) {
        alert('❌ Sistema no inicializado');
        return;
    }

    // Llamar al método de validación de la clase
    await micDtaManager.validateData();
    micDtaManager.showNotification('Validación completada', 'info');
}

// Función global para mostrar historial GPS (llamada desde onclick si existe)
async function showGpsHistory() {
    if (!micDtaManager) {
        alert('❌ Sistema no inicializado');
        return;
    }

    // Llamar al método de la clase
    await micDtaManager.showGpsHistory();
}

// Funciones para XML Preview Modal
function showXmlPreviewModal(preview) {
    const content = document.getElementById('xmlPreviewContent');
    
    if (content && preview) {
        content.innerHTML = `
            <div class="space-y-4">
                <div class="border-b border-gray-200 pb-2">
                    <h4 class="text-sm font-medium text-gray-900">RegistrarTitEnvios XML</h4>
                    <pre class="mt-2 text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>${escapeHtml(preview.titenvios_xml || 'XML no disponible')}</code></pre>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-900">RegistrarEnvios XML</h4>
                    <pre class="mt-2 text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>${escapeHtml(preview.envios_xml || 'XML no disponible')}</code></pre>
                </div>
            </div>
        `;
    }
    
    const modal = document.getElementById('xmlPreviewModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeXmlPreview() {
    const modal = document.getElementById('xmlPreviewModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function showControlPoints() {
    fetch('/company/simple/webservices/micdta/puntos-control', {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let info = '📍 Puntos de Control AFIP - Hidrovía Paraná\n\n';
            data.puntos_control.forEach(punto => {
                info += `🏛️ ${punto.nombre} (${punto.codigo})\n`;
                info += `   📍 ${punto.coordenadas.lat.toFixed(4)}, ${punto.coordenadas.lng.toFixed(4)}\n`;
                info += `   📏 Radio: ${punto.radio_km}km\n\n`;
            });
            alert(info);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error cargando puntos de control');
    });
}

// Funciones auxiliares para compatibilidad
function toggleDetails() {
    const detailsList = document.getElementById('detailsList');
    const toggleText = document.getElementById('toggleDetailsText');
    
    if (detailsList && toggleText) {
        if (detailsList.classList.contains('hidden')) {
            detailsList.classList.remove('hidden');
            toggleText.textContent = 'Ocultar detalles';
        } else {
            detailsList.classList.add('hidden');
            toggleText.textContent = 'Mostrar detalles';
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * ================================================================================
 * INICIALIZACIÓN AUTOMÁTICA
 * ================================================================================
 */

// Variable global para acceso desde el exterior
let micDtaManager = null;

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Obtener voyage ID desde múltiples fuentes (compatibilidad total)
    let voyageId = null;
    
    // Método 1: Desde elemento con data-voyage-id
    const voyageIdElement = document.querySelector('[data-voyage-id]');
    if (voyageIdElement) {
        voyageId = voyageIdElement.dataset.voyageId;
    }
    
    // Método 2: Desde variable global window.voyageId
    if (!voyageId && window.voyageId) {
        voyageId = window.voyageId;
    }
    
    // Método 3: Desde variable global voyageId (generada por Blade)
    if (!voyageId && typeof window !== 'undefined' && window.voyageId !== undefined) {
        voyageId = window.voyageId;
    }
    
    // Método 4: Intentar extraer de script o variable global definida por Blade
    if (!voyageId) {
        // Buscar en scripts inline por patrones como "voyageId = 123"
        const scripts = document.querySelectorAll('script');
        for (const script of scripts) {
            const match = script.textContent.match(/voyageId\s*=\s*(\d+)/);
            if (match) {
                voyageId = parseInt(match[1]);
                break;
            }
        }
    }
    
    if (voyageId) {
        console.log('🎯 Viaje ID encontrado:', voyageId);
        micDtaManager = new MicDtaFormManager(voyageId);
        
        // Exponer globalmente para compatibilidad
        window.micDtaManager = micDtaManager;
        
        // También exponer el voyageId globalmente
        window.voyageId = voyageId;
    } else {
        console.error('⚠️ No se pudo obtener Viaje ID. Asegúrese de que esté definido en el HTML o como variable global.');
    }
});

// Funciones globales para onclick (PUENTE A LA CLASE)
function refreshStatus() {
    if (micDtaManager) micDtaManager.refreshStatus();
}

function getCurrentGPS() {
    if (micDtaManager) micDtaManager.getCurrentGpsPosition();
}

// Función global para compatibilidad con onclick
function validateAndSendManual() {
    // Buscar la instancia del MicDtaFormManager
    if (window.micDtaManager) {
        window.micDtaManager.sendManualCoordinates();
    } else {
        console.error('MicDtaFormManager no inicializado');
        alert('Error: Sistema no inicializado');
    }
}


</script>

@endpush


</x-app-layout>