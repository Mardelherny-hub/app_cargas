{{-- 
  SISTEMA MODULAR WEBSERVICES - Vista Index MIC/DTA Argentina
  Ubicación: resources/views/company/simple/micdta/index.blade.php
  
  Lista específica de voyages para MIC/DTA Argentina con filtros y validaciones.
  Integra con ArgentinaMicDtaService para validaciones específicas.
  
  DATOS VERIFICADOS:
  - Variables del controlador: $voyages, $company, $status_filter
  - Campos Voyage: voyage_number, leadVessel->name, originPort->code, destinationPort->code
  - Campo micdta_status (VoyageWebserviceStatus específico para MIC/DTA)
  - Campo micdta_validation (resultado de canProcessVoyage)
--}}

<x-app-layout>
    <x-slot name="header">
    @include('company.simple.partials.afip-header', [
        'voyage'  => $voyage,
        'company' => $company ?? null,
        'active'  => 'micdta',
    ])
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

            {{-- Información y Filtros --}}
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <svg class="flex-shrink-0 h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Flujo Secuencial AFIP Obligatorio
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p class="mb-2">El envío MIC/DTA sigue un proceso secuencial automático:</p>
                            <ol class="list-decimal list-inside space-y-1 ml-4">
                                <li><strong>RegistrarTitEnvios:</strong> Registra los títulos con sus envíos y recupera los TRACKs reales devueltos por AFIP</li>
                                <li><strong>RegistrarMicDta:</strong> Usa únicamente esos TRACKs reales para registrar el MIC/DTA</li>
                            </ol>
                            <p class="mt-2 text-xs">
                                <strong>Nota:</strong> RegistrarEnvios se utiliza sólo cuando corresponde agregar nuevos envíos a un título ya registrado.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="mb-6 bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <form method="GET" action="{{ route('company.simple.micdta.index') }}" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="status" class="block text-sm font-medium text-gray-700">Filtrar por Estado</label>
                            <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Todos los estados</option>
                                <option value="pending" {{ $status_filter === 'pending' ? 'selected' : '' }}>Pendientes</option>
                                <option value="validating" {{ $status_filter === 'validating' ? 'selected' : '' }}>Validando</option>
                                <option value="sending" {{ $status_filter === 'sending' ? 'selected' : '' }}>Enviando</option>
                                <option value="sent" {{ $status_filter === 'sent' ? 'selected' : '' }}>Enviados</option>
                                <option value="approved" {{ $status_filter === 'approved' ? 'selected' : '' }}>Aprobados</option>
                                <option value="error" {{ $status_filter === 'error' ? 'selected' : '' }}>Con Error</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                Filtrar
                            </button>
                        </div>
                        @if($status_filter)
                            <div>
                                <a href="{{ route('company.simple.micdta.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Limpiar
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Lista de Voyages --}}
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                @if($voyages->count() > 0)
                    {{-- Header con acciones masivas --}}
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <h3 class="text-sm font-medium text-gray-900">
                                {{ $voyages->total() }} voyage{{ $voyages->total() != 1 ? 's' : '' }} encontrado{{ $voyages->total() != 1 ? 's' : '' }}
                            </h3>
                            <div id="loading-indicator" class="hidden flex items-center">
                                <svg class="animate-spin h-4 w-4 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm text-blue-600">Consultando estados...</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button id="btn-consultar-masivo" 
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Consultar Todos
                            </button>
                            <button id="btn-auto-refresh" 
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Auto-refresh: <span id="auto-refresh-status">OFF</span>
                            </button>
                        </div>
                    </div>

                    {{-- Tabla principal simplificada --}}                   
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    {{-- Columnas existentes --}}
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Voyage
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Vessel
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ruta
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado MIC/DTA
                                    </th>

                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($voyages as $voyage)
                                    <tr data-voyage-id="{{ $voyage->id }}" class="hover:bg-gray-50">
                                        {{-- Columnas existentes --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $voyage->voyage_number }}
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $voyage->leadVessel?->name ?? 'Sin embarcación' }}
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $voyage->originPort?->code ?? '?' }} → {{ $voyage->destinationPort?->code ?? '?' }}
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{-- Estado MIC/DTA existente --}}
                                            @if($voyage->micdta_status?->status === 'sent')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Enviado
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Pendiente
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                
                                                {{-- Botón Ver/Enviar MIC/DTA (existente) --}}
                                                <a href="{{ route('company.simple.micdta.show', $voyage->id) }}" 
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-indigo-600 hover:text-indigo-900">
                                                    Ver MIC/DTA
                                                </a>


                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No hay voyages disponibles para MIC/DTA
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación --}}
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $voyages->appends(request()->query())->links() }}
                    </div>
                @else
                    {{-- Estado vacío --}}
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8-4 4-4-4m4 4-4 4-4-4"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay voyages disponibles</h3>
                        <p class="mt-1 text-sm text-gray-500">No se encontraron Viajes que cumplan con los criterios de búsqueda.</p>
                    </div>
                @endif
            </div>

            {{-- MODALES --}}
            <div id="modal-historial" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0" onclick="cerrarModal('modal-historial')"></div>
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-historial-title">
                                        Historial de Consultas
                                    </h3>
                                    <div id="modal-historial-content" class="mt-2">
                                        {{-- Contenido cargado dinámicamente --}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button onclick="cerrarModal('modal-historial')" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="modal-resultado" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0" onclick="cerrarModal('modal-resultado')"></div>
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div id="modal-resultado-content">
                                {{-- Contenido cargado dinámicamente --}}
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button onclick="cerrarModal('modal-resultado')" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Modal de Confirmación de Envío --}}
    <div id="sendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg font-medium text-gray-900">Confirmar Envío MIC/DTA</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="sendModalMessage">
                        ¿Está seguro que desea enviar el MIC/DTA para el Viaje <span id="voyageNumber" class="font-mono font-semibold"></span>?
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        Se ejecutará el flujo secuencial: RegistrarTitEnvios → RegistrarMicDta
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="confirmSend" type="button" 
                            class="px-4 py-2 bg-green-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300">
                        Enviar MIC/DTA
                    </button>
                    <button onclick="closeSendModal()" type="button" 
                            class="mt-3 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    
@push('scripts')
<script>
        let currentVoyageId = null;
        let autoRefreshInterval = null;
        let autoRefreshEnabled = false;

        async function consultarEstadoIndividual(voyageId) {
            const btn = document.querySelector(`tr[data-voyage-id="${voyageId}"] .consultar-btn`);
            const estadoContainer = document.getElementById(`estado-afip-${voyageId}`);
            const consultaContainer = document.getElementById(`ultima-consulta-${voyageId}`);
            
            // UI Loading
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `
                    <svg class="animate-spin -ml-0.5 mr-2 h-3 w-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Consultando...
                `;
            }
            
            if (estadoContainer) {
                estadoContainer.innerHTML = `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 animate-pulse" fill="currentColor" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        Consultando...
                    </span>`;
            }
            
            try {
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/consultar-estado`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Actualizar estado AFIP
                    if (estadoContainer) {
                        estadoContainer.innerHTML = `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <svg class="-ml-0.5 mr-1.5 h-2 w-2" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Consultado
                            </span>`;
                    }
                    
                    // Actualizar timestamp consulta
                    if (consultaContainer) {
                        consultaContainer.innerHTML = `<span class="text-green-600 text-sm">Hace unos segundos</span>`;
                    }
                    
                    // Mostrar notificación
                    mostrarNotificacion('success', `Estado de ${data.voyage_number} consultado exitosamente`);
                } else {
                    if (estadoContainer) {
                        estadoContainer.innerHTML = `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                <svg class="-ml-0.5 mr-1.5 h-2 w-2" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                Error
                            </span>`;
                    }
                    mostrarNotificacion('error', detalleErrorConsulta(data));
                }

            } catch (error) {
                console.error('Error:', error);
                if (estadoContainer) {
                    estadoContainer.innerHTML = `
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            <svg class="-ml-0.5 mr-1.5 h-2 w-2" fill="currentColor" viewBox="0 0 8 8">
                                <circle cx="4" cy="4" r="3" />
                            </svg>
                            Error conexión
                        </span>`;
                }
                mostrarNotificacion('error', 'Error de conexión al consultar estado');
            } finally {
                // Restaurar botón
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="-ml-0.5 mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Consultar
                    `;
                }
            }
        }

        function detalleErrorConsulta(data) {
            const result = data.resultado || {};
            const detail = data.details || result.error || data.error || 'Error consultando estado';
            const code = data.error_code || result.error_code;
            const transaction = data.transaction_record_id || result.consulta_transaction_id;
            return [detail, code ? 'Código: ' + code : '',
                transaction ? 'Transacción: ' + transaction : ''].filter(Boolean).join(' — ');
        }

        async function consultarEstadoMasivo() {
            const btn = document.getElementById('btn-consultar-masivo');
            const loadingIndicator = document.getElementById('loading-indicator');
            
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `
                    <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Consultando...
                `;
            }
            
            if (loadingIndicator) {
                loadingIndicator.classList.remove('hidden');
            }

            try {
                const response = await fetch('/company/simple/webservices/micdta/consultar-estados-masivo', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    const resultado = data.resultado;
                    mostrarNotificacion('success', 
                        `Consulta completada: ${resultado.consultas_exitosas} exitosas, ${resultado.consultas_error} errores`
                    );
                } else {
                    mostrarNotificacion('error', detalleErrorConsulta(data));
                }

            } catch (error) {
                console.error('Error:', error);
                mostrarNotificacion('error', 'Error de conexión en consulta masiva');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Consultar Todos
                    `;
                }
                if (loadingIndicator) {
                    loadingIndicator.classList.add('hidden');
                }
            }
        }

        async function verHistorialConsultas(voyageId) {
            try {
                const response = await fetch(`/company/simple/webservices/micdta/${voyageId}/historial-consultas`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('modal-historial-title').textContent = 
                        `Historial de Consultas - ${data.voyage_number}`;
                    
                    let content = `
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Total de transacciones: ${data.total_transacciones}</p>
                        </div>`;
                        
                    if (data.historial && data.historial.length > 0) {
                        content += `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Enviado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">`;

                        data.historial.forEach(trans => {
                            content += `
                                <tr>
                                    <td class="px-4 py-2 text-sm">${trans.webservice_type === 'micdta' ? 'Envío' : 'Consulta'}</td>
                                    <td class="px-4 py-2 text-sm">${trans.status}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">${trans.sent_at || 'N/A'}</td>
                                </tr>`;
                        });

                        content += `</tbody></table></div>`;
                    } else {
                        content += `<p class="text-sm text-gray-500">No hay historial disponible.</p>`;
                    }
                    
                    document.getElementById('modal-historial-content').innerHTML = content;
                    document.getElementById('modal-historial').classList.remove('hidden');
                } else {
                    mostrarNotificacion('error', data.error || 'Error cargando historial');
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarNotificacion('error', 'Error cargando historial');
            }
        }

        function cerrarModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function mostrarNotificacion(tipo, mensaje) {
            const notification = document.createElement('div');
            const colorClasses = {
                'success': 'bg-green-500 text-white',
                'error': 'bg-red-500 text-white',
                'warning': 'bg-yellow-500 text-white'
            };
            
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 ${colorClasses[tipo] || colorClasses.success}`;
            notification.textContent = mensaje;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        function toggleAutoRefresh() {
            const btn = document.getElementById('btn-auto-refresh');
            const status = document.getElementById('auto-refresh-status');
            
            if (!btn || !status) return;
            
            if (autoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                autoRefreshEnabled = false;
                status.textContent = 'OFF';
                btn.classList.remove('bg-green-50', 'text-green-700');
                btn.classList.add('bg-white', 'text-gray-700');
            } else {
                autoRefreshInterval = setInterval(() => {
                    // Actualizar estados cada 2 minutos
                    const voyagesEnviados = document.querySelectorAll('tr[data-voyage-id]');
                    voyagesEnviados.forEach(row => {
                        const voyageId = row.getAttribute('data-voyage-id');
                        const envioStatus = row.querySelector('td:nth-child(4) span')?.textContent;
                        if (envioStatus && (envioStatus.includes('Enviado') || envioStatus.includes('Aprobado'))) {
                            // Consultar estado sin interfaz de usuario
                            fetch(`/company/simple/webservices/micdta/${voyageId}/estado-afip`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const container = document.getElementById(`estado-afip-${voyageId}`);
                                        const consultaContainer = document.getElementById(`ultima-consulta-${voyageId}`);
                                        if (container && data.estado_afip) {
                                            // Actualizar silenciosamente
                                        }
                                    }
                                })
                                .catch(error => console.error('Error en auto-refresh:', error));
                        }
                    });
                }, 120000); // 2 minutos
                
                autoRefreshEnabled = true;
                status.textContent = 'ON (2min)';
                btn.classList.remove('bg-white', 'text-gray-700');
                btn.classList.add('bg-green-50', 'text-green-700');
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const btnConsultarMasivo = document.getElementById('btn-consultar-masivo');
            const btnAutoRefresh = document.getElementById('btn-auto-refresh');
            
            if (btnConsultarMasivo) {
                btnConsultarMasivo.addEventListener('click', consultarEstadoMasivo);
            }
            
            if (btnAutoRefresh) {
                btnAutoRefresh.addEventListener('click', toggleAutoRefresh);
            }
        });

        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });

        function openSendModal(voyageId, voyageNumber) {
            currentVoyageId = voyageId;
            document.getElementById('voyageNumber').textContent = voyageNumber;
            document.getElementById('sendModal').classList.remove('hidden');
        }

        function closeSendModal() {
            currentVoyageId = null;
            document.getElementById('sendModal').classList.add('hidden');
        }

        document.getElementById('confirmSend').addEventListener('click', function() {
            if (!currentVoyageId) return;

            // Deshabilitar botón para evitar doble envío
            this.disabled = true;
            this.textContent = 'Enviando...';

            // Envío AJAX
            fetch(`/company/simple/webservices/micdta/${currentVoyageId}/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    force_send: false,
                    notes: 'Envío iniciado desde el listado MIC/DTA'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const micDtaId =
                        data?.data?.mic_dta_id
                        || data?.mic_dta_id
                        || 'Pendiente';

                    alert(
                        'MIC/DTA enviado exitosamente!\n\n'
                        + 'MIC/DTA ID: '
                        + micDtaId
                    );
                    window.location.reload();
                } else {
                    const validation = Array.isArray(data.validation_errors)
                        ? data.validation_errors.join('\n')
                        : '';

                    const detail =
                        data.details
                        || data.error
                        || validation
                        || 'Error desconocido';

                    alert('Error enviando MIC/DTA:\n' + detail);

                    // Rehabilitar botón
                    this.disabled = false;
                    this.textContent = 'Enviar MIC/DTA';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión. Intente nuevamente.');
                
                // Rehabilitar botón
                this.disabled = false;
                this.textContent = 'Enviar MIC/DTA';
            })
            .finally(() => {
                closeSendModal();
            });
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSendModal();
            }
        });

        // Cerrar modal al hacer click fuera
        document.getElementById('sendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSendModal();
            }
        });
   


    </script>
    @endpush

</x-app-layout>