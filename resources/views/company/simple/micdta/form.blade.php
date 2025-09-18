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
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/status`, {
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
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/validate`, {
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
                
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/preview-xml`, {
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
                const response = await fetch(`/simple/webservices/micdta/${voyageId}/activity`, {
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
    </script>
    @endpush

</x-app-layout>