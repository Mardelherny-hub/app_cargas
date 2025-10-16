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
                            
                            <button onclick="executeAfipMethod('RegistrarTitEnvios')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-blue-300 rounded-lg hover:bg-blue-100 hover:border-blue-400 transition-colors">
                                <span class="text-2xl mb-2">📋</span>
                                <span class="text-sm font-medium text-center">1. RegistrarTitEnvios</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Registra títulos de transporte</span>
                            </button>

                            <button onclick="executeAfipMethod('RegistrarEnvios')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-blue-300 rounded-lg hover:bg-blue-100 hover:border-blue-400 transition-colors">
                                <span class="text-2xl mb-2">📦</span>
                                <span class="text-sm font-medium text-center">2. RegistrarEnvios</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Genera TRACKs de envíos</span>
                            </button>

                            <button onclick="executeAfipMethod('RegistrarMicDta')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-blue-300 rounded-lg hover:bg-blue-100 hover:border-blue-400 transition-colors">
                                <span class="text-2xl mb-2">📄</span>
                                <span class="text-sm font-medium text-center">3. RegistrarMicDta</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Registra MIC/DTA completo</span>
                            </button>
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
                            
                            <button onclick="executeAfipMethod('RegistrarTitMicDta')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                <span class="text-2xl mb-2">📝</span>
                                <span class="text-sm font-medium text-center">7. RegistrarTitMicDta</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Vincula títulos a MIC/DTA</span>
                            </button>

                            <button onclick="executeAfipMethod('DesvincularTitMicDta')"
                                    class="flex flex-col items-center justify-center p-4 bg-white border-2 border-green-300 rounded-lg hover:bg-green-100 hover:border-green-400 transition-colors">
                                <span class="text-2xl mb-2">🔗</span>
                                <span class="text-sm font-medium text-center">8. DesvincularTitMicDta</span>
                                <span class="text-xs text-gray-600 text-center mt-1">Desvincula títulos</span>
                            </button>

                            <button onclick="executeAfipMethod('AnularTitulo')"
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
</script>