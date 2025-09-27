{{-- 
  SISTEMA MODULAR WEBSERVICES - Vista Show Información Anticipada Argentina
  Ubicación: resources/views/company/simple/anticipada/show.blade.php
  
  Vista detallada para un voyage específico con opciones de envío de métodos AFIP.
  Integra con ArgentinaAnticipatedService para envío real.
  
  DATOS VERIFICADOS DEL CONTROLADOR:
  - $voyage (modelo Voyage con relaciones cargadas)
  - $validation (array resultado de canProcessVoyage)
  - $transactions (collection de WebserviceTransaction)
  - $webservice_config (array configuración del webservice)
  
  CAMPOS VOYAGE VERIFICADOS:
  - voyage_number, departure_date, estimated_arrival_date
  - leadVessel->name, leadVessel->registration_number
  - originPort->code, destinationPort->code
  - company->legal_name
  - webserviceStatuses (relación filtrada por 'anticipada')
--}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Información Anticipada - Voyage {{ $voyage->voyage_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Gestión de envío anticipado de información a AFIP Argentina
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.simple.anticipada.index') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-0.5 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Volver a Lista
                </a>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- Columna Principal: Información del Voyage --}}
                <div class="lg:col-span-2 space-y-6">
                    
                    {{-- Información General del Voyage --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información del Voyage
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Número de Voyage</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $voyage->voyage_number }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $voyage->company->legal_name }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Embarcación Principal</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $voyage->leadVessel?->name ?? 'Sin embarcación' }}
                                        @if($voyage->leadVessel?->registration_number)
                                            <span class="text-gray-500">({{ $voyage->leadVessel->registration_number }})</span>
                                        @endif
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $voyage->originPort?->code ?? '?' }} → {{ $voyage->destinationPort?->code ?? '?' }}
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha de Salida</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($voyage->departure_date)
                                            {{ $voyage->departure_date->format('d/m/Y H:i') }}
                                        @else
                                            <span class="text-gray-400">Sin fecha programada</span>
                                        @endif
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha Estimada de Llegada</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($voyage->estimated_arrival_date)
                                            {{ $voyage->estimated_arrival_date->format('d/m/Y H:i') }}
                                        @else
                                            <span class="text-gray-400">Sin fecha estimada</span>
                                        @endif
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Estado de Validación --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Estado de Validación
                            </h3>
                            
                            @if($validation['can_process'])
                                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-green-800">
                                                Voyage válido para envío
                                            </h3>
                                            <div class="mt-2 text-sm text-green-700">
                                                <p>El voyage cumple con todos los requisitos para enviar información anticipada a AFIP.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-red-800">
                                                Errores de validación
                                            </h3>
                                            <div class="mt-2 text-sm text-red-700">
                                                <ul class="list-disc pl-5 space-y-1">
                                                    @foreach($validation['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(!empty($validation['warnings']))
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mt-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">
                                                Advertencias
                                            </h3>
                                            <div class="mt-2 text-sm text-yellow-700">
                                                <ul class="list-disc pl-5 space-y-1">
                                                    @foreach($validation['warnings'] as $warning)
                                                        <li>{{ $warning }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Historial de Transacciones --}}
                    @if($transactions->count() > 0)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Historial de Transacciones
                                </h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Fecha
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Método
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Estado
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Referencia
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($transactions as $transaction)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $transaction->created_at->format('d/m/Y H:i') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $transaction->method_name ?? 'RegistrarViaje' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                            @if($transaction->status === 'sent') bg-green-100 text-green-800
                                                            @elseif($transaction->status === 'error') bg-red-100 text-red-800
                                                            @else bg-yellow-100 text-yellow-800 @endif">
                                                            {{ ucfirst($transaction->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $transaction->external_reference ?? '-' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Columna Lateral: Acciones de Envío --}}
                <div class="space-y-6">
                    
                    {{-- Estado Actual --}}
                    @php
                        $anticipadaStatus = $voyage->webserviceStatuses->where('webservice_type', 'anticipada')->first();
                    @endphp
                    
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Estado Actual
                            </h3>
                            
                            @if($anticipadaStatus)
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-sm text-gray-500">Estado:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $anticipadaStatus->getStatusColor() }}-100 text-{{ $anticipadaStatus->getStatusColor() }}-800">
                                        {{ $anticipadaStatus->getStatusDescription() }}
                                    </span>
                                </div>
                                
                                @if($anticipadaStatus->last_sent_at)
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-500">Último envío:</span>
                                        <span class="text-sm text-gray-900">{{ $anticipadaStatus->last_sent_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                @endif
                                
                                @if($anticipadaStatus->confirmation_number)
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-500">Confirmación:</span>
                                        <span class="text-sm text-gray-900 font-mono">{{ $anticipadaStatus->confirmation_number }}</span>
                                    </div>
                                @endif
                            @else
                                <p class="text-sm text-gray-500 mb-4">Aún no se ha enviado información anticipada para este voyage.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Acciones de Envío --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Métodos Disponibles
                            </h3>
                            
                            <div class="space-y-3">
                                {{-- RegistrarViaje --}}
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-900">RegistrarViaje</h4>
                                        @if(!$anticipadaStatus || $anticipadaStatus->canSend())
                                            <button onclick="sendMethod('RegistrarViaje')" 
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700"
                                                    @if(!$validation['can_process']) disabled @endif>
                                                Enviar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-500">Ya enviado</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600">Registro inicial del viaje con información anticipada</p>
                                </div>

                                {{-- RectificarViaje --}}
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-900">RectificarViaje</h4>
                                        @if($anticipadaStatus && in_array($anticipadaStatus->status, ['sent', 'approved']))
                                            <button onclick="sendMethod('RectificarViaje')" 
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-orange-600 hover:bg-orange-700">
                                                Rectificar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-500">Requiere envío previo</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600">Rectificación de viaje ya registrado</p>
                                </div>

                                {{-- RegistrarTitulosCbc --}}
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-900">RegistrarTitulosCbc</h4>
                                        @if($anticipadaStatus && in_array($anticipadaStatus->status, ['sent', 'approved']))
                                            <button onclick="sendMethod('RegistrarTitulosCbc')" 
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700">
                                                Registrar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-500">Requiere envío previo</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600">Registro de títulos ATA CBC</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Información Técnica --}}
                    <div class="bg-gray-50 overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información Técnica
                            </h3>
                            
                            <div class="space-y-2 text-xs text-gray-600">
                                <div><strong>Webservice:</strong> wgesinformacionanticipada</div>
                                <div><strong>Namespace:</strong> Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada</div>
                                <div><strong>Ambiente:</strong> {{ $webservice_config['environment'] ?? 'testing' }}</div>
                                <div><strong>Requiere certificado:</strong> Sí</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal para confirmación de envío --}}
    <div id="sendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 text-center" id="modalTitle">
                    Confirmar Envío
                </h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 text-center" id="modalMessage">
                        ¿Está seguro de enviar este método a AFIP?
                    </p>
                    
                    <div class="mt-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notas (opcional):</label>
                        <textarea id="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Ingrese observaciones..."></textarea>
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <div class="flex space-x-2">
                        <button id="confirmSend" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700">
                            Confirmar Envío
                        </button>
                        <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-900 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript para funcionalidad --}}
    <script>
        let currentMethod = '';
        
        function sendMethod(method) {
            currentMethod = method;
            document.getElementById('modalTitle').textContent = `Enviar ${method}`;
            document.getElementById('modalMessage').textContent = `¿Está seguro de enviar ${method} para el voyage {{ $voyage->voyage_number }}?`;
            document.getElementById('sendModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('sendModal').classList.add('hidden');
            document.getElementById('notes').value = '';
        }
        
        document.getElementById('confirmSend').addEventListener('click', function() {
            const notes = document.getElementById('notes').value;
            const button = this;
            const originalText = button.textContent;
            
            button.textContent = 'Enviando...';
            button.disabled = true;

            fetch(`{{ route('company.simple.anticipada.send', $voyage) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    method: currentMethod,
                    environment: 'testing',
                    notes: notes,
                    rectification_reason: currentMethod === 'RectificarViaje' ? notes : null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${currentMethod} enviado exitosamente`);
                    location.reload();
                } else {
                    alert(`Error: ${data.message || 'Error desconocido'}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            })
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
                closeModal();
            });
        });

        // Cerrar modal al hacer clic fuera
        document.getElementById('sendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</x-app-layout>