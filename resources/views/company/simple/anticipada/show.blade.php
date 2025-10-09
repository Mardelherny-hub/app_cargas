{{-- 
  SISTEMA MODULAR WEBSERVICES - Vista Show Información Anticipada Argentina
  Ubicación: resources/views/company/simple/anticipada/show.blade.php
  
  Vista detallada para un Viajeespecífico con opciones de envío de métodos AFIP.
  Integra con ArgentinaAnticipatedService para envío real.
  
  DATOS VERIFICADOS DEL CONTROLADOR:
  - $voyage (modelo Voyage con relaciones cargadas)
  - $validation (array resultado de canProcessVoyage)
  - $transactions (collection de WebserviceTransaction)
  - $webservice_config (array configuración del webservice)
  
  CAMPOS Viaje VERIFICADOS:
  - voyage_number, departure_date, estimated_arrival_date
  - leadVessel->name, leadVessel->registration_number
  - originPort->code, destinationPort->code
  - company->legal_name
  - webserviceStatuses (relación filtrada por 'anticipada')
--}}

<x-app-layout>
    <x-slot name="header">
    @include('company.simple.partials.afip-header', [
        'voyage'  => $voyage,
        'company' => $company ?? null,
        'active'  => 'anticipada',
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- Columna Principal: Información del Viaje--}}
                <div class="lg:col-span-2 space-y-6">
                    
                    {{-- Información General del Viaje--}}
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
                                                Viaje válido para envío
                                            </h3>
                                            <div class="mt-2 text-sm text-green-700">
                                                <p>El Viajecumple con todos los requisitos para enviar información anticipada a AFIP.</p>
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
                            
                            @php
                                // Filtrar transacciones por método
                                $registrarViajeTransactions = $transactions->filter(function($t) {
                                    return isset($t->additional_metadata['method']) && $t->additional_metadata['method'] === 'RegistrarViaje';
                                });

                                $rectificarViajeTransactions = $transactions->filter(function($t) {
                                    return isset($t->additional_metadata['method']) && $t->additional_metadata['method'] === 'RectificarViaje';
                                });

                                $registrarTitulosCbcTransactions = $transactions->filter(function($t) {
                                    return isset($t->additional_metadata['method']) && $t->additional_metadata['method'] === 'RegistrarTitulosCbc';
                                });
                                // Obtener la última de cada método
                                $lastRegistrarViaje = $registrarViajeTransactions->sortByDesc('created_at')->first();
                                $lastRectificarViaje = $rectificarViajeTransactions->sortByDesc('created_at')->first();
                                $lastRegistrarTitulosCbc = $registrarTitulosCbcTransactions->sortByDesc('created_at')->first();
                            @endphp
                            
                            <div class="space-y-3">
                                {{-- RegistrarViaje --}}
                                <div class="border rounded-lg p-4 @if($lastRegistrarViaje && $lastRegistrarViaje->status === 'success') border-green-300 bg-green-50 @endif">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-900">RegistrarViaje</h4>
                                        @if(!$anticipadaStatus || $anticipadaStatus->canSend())
                                            <button onclick="sendMethod('RegistrarViaje')" 
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700"
                                                    @if(!$validation['can_process']) disabled @endif>
                                                Enviar
                                            </button>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Enviado
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600 mb-2">Registro inicial del viaje con información anticipada</p>
                                    
                                    @if($lastRegistrarViaje)
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <span class="text-gray-500">Último envío:</span>
                                                    <span class="text-gray-900 font-medium">{{ $lastRegistrarViaje->created_at->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Estado:</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($lastRegistrarViaje->status === 'success') bg-green-100 text-green-800
                                                        @elseif($lastRegistrarViaje->status === 'error') bg-red-100 text-red-800
                                                        @else bg-yellow-100 text-yellow-800 @endif">
                                                        {{ ucfirst($lastRegistrarViaje->status) }}
                                                    </span>
                                                </div>
                                                @if($lastRegistrarViaje->external_reference)
                                                    <div class="col-span-2">
                                                        <span class="text-gray-500">IdentificadorViaje:</span>
                                                        <span class="text-green-600 font-mono text-xs">{{ $lastRegistrarViaje->external_reference }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400 mt-2">No se ha enviado aún</p>
                                    @endif
                                </div>

                                {{-- RectificarViaje --}}
                                <div class="border rounded-lg p-4 @if($lastRectificarViaje && $lastRectificarViaje->status === 'success') border-orange-300 bg-orange-50 @endif">
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
                                    <p class="text-xs text-gray-600 mb-2">Rectificación de viaje ya registrado</p>
                                    
                                    @if($lastRectificarViaje)
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <span class="text-gray-500">Último envío:</span>
                                                    <span class="text-gray-900 font-medium">{{ $lastRectificarViaje->created_at->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Estado:</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($lastRectificarViaje->status === 'success') bg-green-100 text-green-800
                                                        @elseif($lastRectificarViaje->status === 'error') bg-red-100 text-red-800
                                                        @else bg-yellow-100 text-yellow-800 @endif">
                                                        {{ ucfirst($lastRectificarViaje->status) }}
                                                    </span>
                                                </div>
                                                @if($lastRectificarViaje->external_reference)
                                                    <div class="col-span-2">
                                                        <span class="text-gray-500">IdentificadorViaje:</span>
                                                        <span class="text-orange-600 font-mono text-xs">{{ $lastRectificarViaje->external_reference }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            @if($rectificarViajeTransactions->count() > 1)
                                                <p class="text-xs text-gray-500 mt-2">Total de rectificaciones: {{ $rectificarViajeTransactions->count() }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400 mt-2">No se han realizado rectificaciones</p>
                                    @endif
                                </div>

                                {{-- RegistrarTitulosCbc --}}
                                <div class="border rounded-lg p-4 @if($lastRegistrarTitulosCbc && $lastRegistrarTitulosCbc->status === 'success') border-green-300 bg-green-50 @endif">
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
                                    <p class="text-xs text-gray-600 mb-2">Registro de títulos ATA CBC</p>
                                    
                                    @if($lastRegistrarTitulosCbc)
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <span class="text-gray-500">Último envío:</span>
                                                    <span class="text-gray-900 font-medium">{{ $lastRegistrarTitulosCbc->created_at->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Estado:</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($lastRegistrarTitulosCbc->status === 'success') bg-green-100 text-green-800
                                                        @elseif($lastRegistrarTitulosCbc->status === 'error') bg-red-100 text-red-800
                                                        @else bg-yellow-100 text-yellow-800 @endif">
                                                        {{ ucfirst($lastRegistrarTitulosCbc->status) }}
                                                    </span>
                                                </div>
                                                @if($lastRegistrarTitulosCbc->external_reference)
                                                    <div class="col-span-2">
                                                        <span class="text-gray-500">Referencia:</span>
                                                        <span class="text-green-600 font-mono text-xs">{{ $lastRegistrarTitulosCbc->external_reference }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            @if($registrarTitulosCbcTransactions->count() > 1)
                                                <p class="text-xs text-gray-500 mt-2">Total de registros: {{ $registrarTitulosCbcTransactions->count() }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400 mt-2">No se han registrado títulos CBC</p>
                                    @endif
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

    {{-- Modal de Feedback --}}
    <div id="feedbackModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div id="feedbackIcon" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full">
                    <!-- El icono se insertará dinámicamente -->
                </div>
                <h3 id="feedbackTitle" class="text-lg leading-6 font-medium text-gray-900 mt-4"></h3>
                <div class="mt-2 px-7 py-3">
                    <p id="feedbackMessage" class="text-sm text-gray-500"></p>
                    <div id="feedbackDetails" class="mt-3 hidden">
                        <div class="bg-gray-50 p-3 rounded text-left">
                            <p class="text-xs text-gray-600"><strong>ID Transacción:</strong> <span id="transactionId"></span></p>
                            <p class="text-xs text-gray-600 mt-1"><strong>IdentificadorViaje AFIP:</strong> <span id="externalReference" class="font-mono text-green-600"></span></p>
                        </div>
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="feedbackCloseBtn" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Spinner de carga --}}
    <div id="loadingSpinner" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-1/2 mx-auto text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-500"></div>
            <p class="text-white mt-4 text-lg">Enviando a AFIP...</p>
        </div>
    </div>

    {{-- JavaScript para funcionalidad --}}
    <script>
        let currentMethod = '';
        
        function sendMethod(method) {
            currentMethod = method;
            document.getElementById('modalTitle').textContent = `Enviar ${method}`;
            document.getElementById('modalMessage').textContent = `¿Está seguro de enviar ${method} para el Viaje{{ $voyage->voyage_number }}?`;
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
            
            // Cerrar modal de confirmación
            closeModal();
            
            // Mostrar spinner
            document.getElementById('loadingSpinner').classList.remove('hidden');
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
                // Ocultar spinner
                document.getElementById('loadingSpinner').classList.add('hidden');
                
                // Mostrar modal de feedback
                showFeedbackModal(data);
                
                // Si fue exitoso, recargar después de cerrar modal
                if (data.success) {
                    document.getElementById('feedbackCloseBtn').onclick = function() {
                        location.reload();
                    };
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').classList.add('hidden');
                
                showFeedbackModal({
                    success: false,
                    message: 'Error de conexión con el servidor'
                });
            })
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
            });
        });

        function showFeedbackModal(data) {
            const modal = document.getElementById('feedbackModal');
            const icon = document.getElementById('feedbackIcon');
            const title = document.getElementById('feedbackTitle');
            const message = document.getElementById('feedbackMessage');
            const details = document.getElementById('feedbackDetails');
            
            if (data.success) {
                // Configurar para ÉXITO
                icon.innerHTML = '<svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100';
                title.textContent = '¡Envío Exitoso!';
                title.className = 'text-lg leading-6 font-medium text-green-900 mt-4';
                message.textContent = data.message || 'El método fue enviado correctamente a AFIP';
                
                // Mostrar detalles si existen
                if (data.data) {
                    document.getElementById('transactionId').textContent = data.data.transaction_id || 'N/A';
                    document.getElementById('externalReference').textContent = data.data.external_reference || 'Pendiente';
                    details.classList.remove('hidden');
                }
            } else {
                // Configurar para ERROR
                icon.innerHTML = '<svg class="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100';
                title.textContent = 'Error en el Envío';
                title.className = 'text-lg leading-6 font-medium text-red-900 mt-4';
                message.textContent = data.message || 'Ocurrió un error al procesar la solicitud';
                details.classList.add('hidden');
            }
            
            modal.classList.remove('hidden');
            
            // Cerrar modal al hacer clic en el botón
            document.getElementById('feedbackCloseBtn').onclick = function() {
                modal.classList.add('hidden');
            };
            
            // Cerrar modal al hacer clic fuera
            modal.onclick = function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            };
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('sendModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</x-app-layout>