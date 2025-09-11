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
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    MIC/DTA Argentina
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Manifiesto Internacional de Carga / Documento de Transporte Aduanero - AFIP
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.simple.dashboard') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-0.5 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Volver al Dashboard
                </a>
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
                                <li><strong>RegistrarTitEnvios:</strong> Genera TRACKs por cada shipment</li>
                                <li><strong>RegistrarMicDta:</strong> Usa los TRACKs del paso anterior</li>
                            </ol>
                            <p class="mt-2 text-xs">
                                <strong>Nota:</strong> Ambos pasos se ejecutan automáticamente en una sola operación.
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
            <div class="bg-white shadow overflow-hidden rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Voyages Disponibles para MIC/DTA ({{ $voyages->total() }})
                        </h3>
                    </div>

                    @if($voyages->count() > 0)
                        <div class="space-y-6">
                            @foreach($voyages as $voyage)
                                @php
                                    $micdta_status = $voyage->micdta_status;
                                    $validation = $voyage->micdta_validation;
                                    
                                    // Determinar color y estado visual
                                    $status_color = 'gray';
                                    $status_text = 'Sin configurar';
                                    $can_send = false;
                                    
                                    if ($micdta_status) {
                                        $can_send = $micdta_status->can_send;
                                        
                                        switch($micdta_status->status) {
                                            case 'approved':
                                                $status_color = 'green';
                                                $status_text = 'Aprobado';
                                                break;
                                            case 'sent':
                                                $status_color = 'blue';
                                                $status_text = 'Enviado';
                                                break;
                                            case 'sending':
                                                $status_color = 'blue';
                                                $status_text = 'Enviando...';
                                                break;
                                            case 'validating':
                                                $status_color = 'yellow';
                                                $status_text = 'Validando...';
                                                break;
                                            case 'pending':
                                                $status_color = 'yellow';
                                                $status_text = 'Pendiente';
                                                break;
                                            case 'error':
                                                $status_color = 'red';
                                                $status_text = 'Error';
                                                break;
                                            case 'retry':
                                                $status_color = 'orange';
                                                $status_text = 'Reintentando';
                                                break;
                                            default:
                                                $status_color = 'gray';
                                                $status_text = ucfirst($micdta_status->status);
                                        }
                                    }
                                @endphp
                                
                                <div class="border border-gray-200 rounded-lg p-6 hover:bg-gray-50 transition duration-150">
                                    <div class="flex items-start justify-between">
                                        {{-- Información del Voyage --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-4 mb-3">
                                                <div>
                                                    <h4 class="text-lg font-semibold text-gray-900">
                                                        {{ $voyage->voyage_number }}
                                                    </h4>
                                                    <p class="text-sm text-gray-600">
                                                        @if($voyage->leadVessel)
                                                            <span class="font-medium">{{ $voyage->leadVessel->name }}</span>
                                                        @else
                                                            <span class="italic text-red-500">Sin embarcación asignada</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                
                                                <div class="flex items-center text-sm text-gray-600">
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span>{{ $voyage->originPort->code ?? 'N/D' }}</span>
                                                    <svg class="w-4 h-4 mx-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span>{{ $voyage->destinationPort->code ?? 'N/D' }}</span>
                                                </div>
                                                
                                                <div class="text-sm text-gray-600">
                                                    <span class="font-medium">Shipments:</span> {{ $voyage->shipments->count() }}
                                                </div>
                                            </div>

                                            {{-- Validación MIC/DTA --}}
                                            @if($validation)
                                                <div class="mt-3">
                                                    @if($validation['can_process'])
                                                        <div class="flex items-center text-sm text-green-700">
                                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                            </svg>
                                                            <span class="font-medium">Listo para envío MIC/DTA</span>
                                                        </div>
                                                    @else
                                                        <div class="text-sm">
                                                            <div class="flex items-center text-red-700 mb-1">
                                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="font-medium">Requiere atención</span>
                                                            </div>
                                                            @if(count($validation['errors']) > 0)
                                                                <div class="ml-5 space-y-1">
                                                                    @foreach($validation['errors'] as $error)
                                                                        <p class="text-xs text-red-600">• {{ $error }}</p>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    
                                                    @if(count($validation['warnings']) > 0)
                                                        <div class="mt-2 text-sm">
                                                            <div class="flex items-center text-yellow-700 mb-1">
                                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="font-medium">Advertencias:</span>
                                                            </div>
                                                            <div class="ml-5 space-y-1">
                                                                @foreach($validation['warnings'] as $warning)
                                                                    <p class="text-xs text-yellow-600">• {{ $warning }}</p>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Estado y Acciones --}}
                                        <div class="flex items-center space-x-4">
                                            {{-- Estado MIC/DTA --}}
                                            <div class="text-center">
                                                <div class="text-xs font-medium text-gray-700 mb-2">Estado MIC/DTA</div>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium 
                                                    @if($status_color === 'green') bg-green-100 text-green-800
                                                    @elseif($status_color === 'blue') bg-blue-100 text-blue-800
                                                    @elseif($status_color === 'yellow') bg-yellow-100 text-yellow-800
                                                    @elseif($status_color === 'red') bg-red-100 text-red-800
                                                    @elseif($status_color === 'orange') bg-orange-100 text-orange-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    {{ $status_text }}
                                                </span>
                                                
                                                @if($micdta_status && $micdta_status->last_sent_at)
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        {{ $micdta_status->last_sent_at->format('d/m/Y H:i') }}
                                                    </div>
                                                @endif
                                                
                                                @if($micdta_status && $micdta_status->confirmation_number)
                                                    <div class="text-xs text-gray-600 mt-1 font-mono">
                                                        #{{ $micdta_status->confirmation_number }}
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Acciones --}}
                                            <div class="flex flex-col space-y-2">
                                                <a href="{{ route('company.simple.micdta.show', $voyage->id) }}" 
                                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Ver Detalle
                                                </a>
                                                
                                                @if($validation && $validation['can_process'] && $can_send)
                                                    <button type="button" 
                                                            onclick="openSendModal('{{ $voyage->id }}', '{{ $voyage->voyage_number }}')"
                                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                        <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Enviar MIC/DTA
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Paginación --}}
                        @if($voyages->hasPages())
                            <div class="mt-8">
                                {{ $voyages->appends(request()->query())->links() }}
                            </div>
                        @endif
                        
                    @else
                        {{-- Estado vacío --}}
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">
                                @if($status_filter)
                                    No hay voyages con estado "{{ $status_filter }}"
                                @else
                                    No hay voyages disponibles para MIC/DTA
                                @endif
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if($status_filter)
                                    Prueba con un filtro diferente o crea un nuevo voyage.
                                @else
                                    Crea un nuevo voyage para comenzar a usar MIC/DTA Argentina.
                                @endif
                            </p>
                            <div class="mt-6">
                                @if($status_filter)
                                    <a href="{{ route('company.simple.micdta.index') }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Ver Todos
                                    </a>
                                @else
                                    <a href="{{ route('company.voyages.create') }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Crear Voyage
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
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
                        ¿Está seguro que desea enviar el MIC/DTA para el voyage <span id="voyageNumber" class="font-mono font-semibold"></span>?
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

    {{-- JavaScript para Modal y Envío AJAX --}}
    <script>
        let currentVoyageId = null;

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
                    test_mode: true // Por defecto en modo test
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito y recargar
                    alert('MIC/DTA enviado exitosamente!\n\nConfirmación: ' + (data.confirmation_number || 'Pendiente'));
                    window.location.reload();
                } else {
                    // Mostrar error
                    alert('Error enviando MIC/DTA:\n' + data.message);
                    
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
</x-app-layout>