<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🏛️ {{ __('Envío a Aduana') }}
            </h2>
            <div class="flex items-center space-x-4">
                <!-- Selector de País -->
                <form method="GET" action="{{ route('company.manifests.customs.index') }}" class="flex items-center space-x-2">
                    <select name="country" onchange="this.form.submit()" 
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">🌍 Todos los países</option>
                        <option value="AR" {{ request('country') == 'AR' ? 'selected' : '' }}>🇦🇷 Argentina (AFIP)</option>
                        <option value="PY" {{ request('country') == 'PY' ? 'selected' : '' }}>🇵🇾 Paraguay (DNA)</option>
                    </select>
                    
                    <select name="webservice_status" onchange="this.form.submit()"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">📊 Todos los estados</option>
                        <option value="not_sent" {{ request('webservice_status') == 'not_sent' ? 'selected' : '' }}>⏳ No enviado</option>
                        <option value="sent" {{ request('webservice_status') == 'sent' ? 'selected' : '' }}>✅ Enviado exitoso</option>
                        <option value="failed" {{ request('webservice_status') == 'failed' ? 'selected' : '' }}>❌ Error</option>
                        <option value="pending" {{ request('webservice_status') == 'pending' ? 'selected' : '' }}>🔄 Pendiente</option>
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Estadísticas -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        📊 Estadísticas de Envío
                    </h3>
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                        <div class="bg-blue-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-blue-600">📦 Total Manifiestos</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-900">{{ $stats['total'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-green-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-green-600">✅ Enviados</dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-900">{{ $stats['sent'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-yellow-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-yellow-600">⏳ Pendientes</dt>
                            <dd class="mt-1 text-2xl font-semibold text-yellow-900">{{ $stats['pending'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-red-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-red-600">❌ Errores</dt>
                            <dd class="mt-1 text-2xl font-semibold text-red-900">{{ $stats['failed'] ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Información de Webservices Disponibles -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        🔧 Servicios Disponibles
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Argentina AFIP -->
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h4 class="font-medium text-blue-900 mb-2">🇦🇷 Argentina (AFIP)</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• MIC/DTA - Manifiesto de Importación</li>
                                <li>• Anticipada - Declaración Anticipada</li>
                                <li>• Automático según puerto destino</li>
                            </ul>
                        </div>
                        
                        <!-- Paraguay DNA -->
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h4 class="font-medium text-green-900 mb-2">🇵🇾 Paraguay (DNA)</h4>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li>• Manifiesto de Carga</li>
                                <li>• Declaración Aduanera</li>
                                <li>• Seguimiento integrado</li>
                            </ul>
                        </div>
                        
                        <!-- Configuración -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h4 class="font-medium text-gray-900 mb-2">⚙️ Configuración</h4>
                            <ul class="text-sm text-gray-700 space-y-1">
                                <li>• Certificados digitales</li>
                                <li>• URLs de testing/producción</li>
                                <li>• Configuración por país</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Listado de Manifiestos -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                🚢 Manifiestos Listos para Aduana
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Solo se muestran viajes completados con cargas verificadas
                            </p>
                        </div>
                        
                        <!-- Botón de envío masivo -->
                        <button type="button" id="bulk-send-btn" 
                                class="hidden inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                onclick="showBulkSendModal()">
                            🚀 Enviar Seleccionados
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" 
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           onchange="toggleSelectAll()">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Viaje
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ruta & Destino
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cargas
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado Aduana
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($voyages as $voyage)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="voyage_ids[]" value="{{ $voyage->id }}" 
                                           class="voyage-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           onchange="updateBulkSendButton()">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $voyage->voyage_number }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        📅 {{ $voyage->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        🏁 {{ $voyage->origin_port->name ?? 'Puerto Origen' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        🎯 {{ $voyage->destination_port->name ?? 'Puerto Destino' }}
                                        @if($voyage->destination_port && $voyage->destination_port->country)
                                            <span class="ml-1 px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                                {{ $voyage->destination_port->country->iso_code }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        📦 {{ $voyage->shipments->sum('containers_loaded') }} containers
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        📄 {{ $voyage->shipments->sum(function($s) { return $s->billsOfLading->count(); }) }} BL
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        // Obtener todos los estados de webservice del voyage
                                        $webserviceStatuses = $voyage->webserviceStatuses;
                                        $hasAnyStatus = $webserviceStatuses->isNotEmpty();
                                        // Mantener compatibilidad con sistema viejo
                                        $lastTransaction = $voyage->webserviceTransactions->last();
                                    @endphp
                                    
                                    @if(!$hasAnyStatus)
                                        {{-- No hay estados configurados --}}
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            ⚙️ Sin configurar
                                        </span>
                                    @else
                                        {{-- Mostrar badges por cada webservice --}}
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($webserviceStatuses as $status)
                                                @php
                                                    $badgeColor = match($status->status) {
                                                        'approved' => 'bg-green-100 text-green-800',
                                                        'sent' => 'bg-blue-100 text-blue-800', 
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'sending', 'validating' => 'bg-orange-100 text-orange-800',
                                                        'error', 'rejected' => 'bg-red-100 text-red-800',
                                                        'expired' => 'bg-gray-100 text-gray-600',
                                                        default => 'bg-gray-100 text-gray-600'
                                                    };
                                                    
                                                    $badgeIcon = match($status->status) {
                                                        'approved' => '✅',
                                                        'sent' => '📤',
                                                        'pending' => '⏳',
                                                        'sending', 'validating' => '🔄', 
                                                        'error', 'rejected' => '❌',
                                                        'expired' => '⏰',
                                                        default => '⚪'
                                                    };
                                                    
                                                    $shortName = match($status->webservice_type) {
                                                        'anticipada' => 'ANT',
                                                        'micdta' => 'MIC',
                                                        'desconsolidado' => 'DES', 
                                                        'transbordo' => 'TRB',
                                                        'mane' => 'MANE',
                                                        'manifiesto' => 'MAN',
                                                        default => strtoupper(substr($status->webservice_type, 0, 3))
                                                    };
                                                @endphp
                                                
                                                <div class="flex flex-col items-start">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $badgeColor }}"
                                                        title="{{ $status->getWebserviceTypeDescription() }} ({{ $status->getCountryDescription() }}) - {{ $status->getStatusDescription() }}">
                                                        {{ $badgeIcon }} {{ $shortName }}
                                                    </span>
                                                    
                                                    @if(in_array($status->status, ['error', 'rejected']) && $status->last_error_message)
                                                        <div class="text-xs text-red-600 mt-1 break-words whitespace-normal max-w-40 leading-tight">
                                                            {{ Str::limit($status->last_error_message, 80) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                        {{-- Mostrar confirmaciones o errores debajo --}}
                                        @foreach($webserviceStatuses as $status)
                                            @if($status->status === 'approved' && $status->confirmation_number)
                                                <div class="text-xs text-green-600 mt-1">
                                                    {{ $status->webservice_type }}: #{{ $status->confirmation_number }}
                                                </div>
                                            @elseif($status->status === 'error' && $status->last_error_message)
                                                <div class="text-xs text-red-600 mt-1 break-words whitespace-normal max-w-xs">
                                                    {{ $status->webservice_type }}: {{ Str::limit($status->last_error_message, 200) }}
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <!-- Botones de Envío Individual -->
                                <div class="flex flex-col space-y-2">
                                    <!-- Primera fila: Botones de envío tradicionales -->
                                    <div class="flex space-x-2">
                                        {{-- Argentina Tradicional --}}
                                        @if($voyage->destinationPort->country->alpha2_code === 'AR' || 
                                            ($voyage->originPort->country->alpha2_code === 'AR' && $voyage->destinationPort->country->alpha2_code === 'PY'))
                                            <button onclick="showSendModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}', 'AR')"
                                                    class="inline-flex items-center px-3 py-1 border border-blue-300 rounded-md text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                                                🇦🇷 Argentina
                                            </button>
                                        @endif
                                        
                                        {{-- Paraguay --}}
                                        @if($voyage->destinationPort->country->alpha2_code === 'PY' || 
                                            ($voyage->originPort->country->alpha2_code === 'PY' && $voyage->destinationPort->country->alpha2_code === 'AR'))
                                            <button onclick="showSendModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}', 'PY')"
                                                    class="inline-flex items-center px-3 py-1 border border-green-300 rounded-md text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1">
                                                🇵🇾 Paraguay
                                            </button>
                                        @endif
                                    </div>
                                    
                                    <!-- ✅ NUEVA: Segunda fila con botón específico MIC/DTA Completo -->
                                    @if($voyage->destinationPort->country->alpha2_code === 'AR' || 
                                        ($voyage->originPort->country->alpha2_code === 'AR' && $voyage->destinationPort->country->alpha2_code === 'PY'))
                                    <div class="flex space-x-2">
                                        <button onclick="showMicDtaCompleteModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}')"
                                                class="inline-flex items-center px-3 py-1 border border-purple-300 rounded-md text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-1">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            📋 MIC/DTA Completo
                                        </button>
                                    </div>
                                    @endif
                                    
                                    <!-- Tercera fila: Adjuntos Paraguay (existente) -->
                                    @if($voyage->destinationPort->country->alpha2_code === 'PY')
                                    <div class="flex space-x-2">
                                        <button onclick="showAttachmentsModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}', '{{ $voyage->originPort->name ?? "N/A" }} → {{ $voyage->destinationPort->name ?? "N/A" }}')"
                                                class="inline-flex items-center px-3 py-1 border border-yellow-300 rounded-md text-xs font-medium text-yellow-700 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-1">
                                            📎 Adjuntos PY
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay manifiestos listos</h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Los manifiestos aparecerán aquí cuando tengan cargas completadas.
                                        </p>
                                        <div class="mt-6">
                                            <a href="{{ route('company.manifests.index') }}" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                📋 Ver Manifiestos
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($voyages->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $voyages->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de Envío Individual -->
    <div id="send-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">🚀 Enviar a Aduana</h3>
                    <button type="button" onclick="closeSendModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="send-form" method="POST" action="">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Viaje</label>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded" id="modal-voyage-info">
                                <!-- Se llena dinámicamente -->
                            </div>
                        </div>
                        
                        <div>
                            <label for="webservice_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Webservice
                            </label>
                            <select name="webservice_type" id="webservice_type" required
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    onchange="updateWebserviceInfo()">
                                <option value="">Seleccionar webservice</option>
                                
                                <!-- ✅ MODIFICADO: MIC/DTA con información TRACKs -->
                                <option value="micdta" data-process="tracks">
                                    🇦🇷 Argentina MIC/DTA (Sistema TRACKs Automático)
                                </option>
                                
                                <!-- ✅ OTROS: Sin cambios -->
                                <option value="anticipada">🇦🇷 Argentina Anticipada</option>
                                <option value="desconsolidado">🇦🇷 Argentina Desconsolidados</option>
                                <option value="transbordo">🚢 Argentina Transbordos</option>
                                <option value="mane">🏝️ Argentina MANE/Malvina</option>
                                <option value="paraguay_customs">🇵🇾 Paraguay DNA</option>
                            </select>
                            <div id="tracks-info-panel" class="hidden mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-blue-900 mb-2">
                                            🔄 Sistema TRACKs AFIP - Proceso Automático
                                        </h4>
                                        <div class="text-sm text-blue-800 space-y-1">
                                            <p class="font-medium">Este envío ejecutará automáticamente:</p>
                                            <ol class="list-decimal list-inside ml-4 space-y-1">
                                                <li><strong>Paso 1:</strong> RegistrarTitEnvios (genera TRACKs AFIP)</li>
                                                <li><strong>Paso 2:</strong> RegistrarMicDta (usa TRACKs del paso 1)</li>
                                            </ol>
                                            <div class="mt-3 p-2 bg-blue-100 rounded text-xs">
                                                <strong>Nota:</strong> El proceso es secuencial y automático. No requiere intervención manual entre pasos.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="environment" class="block text-sm font-medium text-gray-700 mb-2">
                                Ambiente
                            </label>
                            <select name="environment" id="environment" required
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="testing">🧪 Testing (Homologación)</option>
                                <option value="production">🏭 Producción</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                                Prioridad
                            </label>
                            <select name="priority" id="priority"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="normal">📋 Normal</option>
                                <option value="high">⚡ Alta</option>
                                <option value="urgent">🚨 Urgente</option>
                            </select>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>Importante:</strong> Verifique que todos los datos estén correctos antes de enviar. 
                                        El envío a producción es irreversible.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeSendModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            🚀 Enviar a Aduana
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Adjuntos Paraguay -->
    <div id="attachments-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header del Modal -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        📎 Adjuntos Paraguay GDSF
                    </h3>
                    <button onclick="closeAttachmentsModal()" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Información del Viaje -->
                <div id="modal-voyage-details" class="bg-blue-50 p-4 rounded-lg mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 id="modal-voyage-title" class="font-medium text-blue-900">
                                <!-- Se llena dinámicamente con JS -->
                            </h4>
                            <p id="modal-voyage-route" class="text-sm text-blue-700">
                                <!-- Se llena dinámicamente con JS -->
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                🇵🇾 Paraguay GDSF
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Área de Subida de Archivos - MÚLTIPLES DOCUMENTOS -->
                <div class="mb-6">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                        <div class="space-y-2">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="text-sm text-gray-600">
                                <label for="pdf-files" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                    <span>📄 Seleccionar PDFs</span>
                                    <input id="pdf-files" name="pdf-files" type="file" class="sr-only" multiple accept=".pdf">
                                </label>
                                <span class="pl-1">o arrastrar aquí</span>
                            </div>
                            <p class="text-xs text-gray-500">
                                <strong>Múltiples archivos PDF.</strong> Máximo 10MB por archivo.
                            </p>
                            <p class="text-xs text-blue-600">
                                💡 Los documentos se AGREGAN a los existentes (no se reemplazan)
                            </p>
                        </div>
                    </div>

                    <!-- Lista de archivos seleccionados -->
                    <div id="selected-files-list" class="hidden mt-4">
                        <h5 class="text-sm font-medium text-gray-700 mb-2">📎 Nuevos archivos a subir:</h5>
                        <div id="files-container" class="space-y-2">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>
                </div>

                <!-- Adjuntos Existentes - GESTIÓN INDIVIDUAL -->
                <div id="existing-attachments" class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700">📋 Documentos Actuales</h4>
                        <span id="attachments-counter" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            0 archivos
                        </span>
                    </div>
                    <div id="existing-files-container" class="space-y-2">
                        <div class="text-center text-gray-500 py-4">
                            <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-sm">Cargando adjuntos existentes...</p>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                    <button onclick="closeAttachmentsModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancelar
                    </button>
                    
                    <div class="space-x-2">
                        <button id="clear-files-btn" onclick="clearSelectedFiles()"
                                class="hidden px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition-colors">
                            🗑️ Limpiar
                        </button>
                        
                        <button id="upload-files-btn" onclick="uploadFiles()"
                                class="hidden px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            ⬆️ Subir Archivos
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div id="upload-progress" class="hidden mt-4">
                    <div class="bg-gray-200 rounded-full h-2">
                        <div id="upload-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p id="upload-status" class="text-sm text-gray-600 mt-2">Preparando subida...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ NUEVO: Modal MIC/DTA Completo - PASO A PASO -->
    <div id="micdta-complete-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header del Modal -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            📋 MIC/DTA con Títulos y Envíos AFIP
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Proceso paso a paso para <span id="complete-voyage-title" class="font-medium"></span>
                        </p>
                    </div>
                    <button onclick="closeMicDtaCompleteModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- PASO 1: Configuración Inicial -->
                <div id="step-1-config" class="space-y-6">
                    <!-- Información del Proceso -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-purple-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-purple-900 mb-2">
                                    Proceso controlado paso a paso:
                                </h4>
                                <ol class="text-sm text-purple-800 space-y-2">
                                    <li class="flex items-center space-x-2">
                                        <span class="flex-shrink-0 w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                                        <span><strong>Registro de Títulos y Envíos:</strong> Genera identificadores únicos → <em>Pausa para revisión</em></span>
                                    </li>
                                    <li class="flex items-center space-x-2">
                                        <span class="flex-shrink-0 w-6 h-6 bg-gray-400 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                                        <span><strong>Manifiesto de Carga:</strong> Envía MIC/DTA usando identificadores validados</span>
                                    </li>
                                </ol>
                                <div class="mt-3 p-2 bg-purple-100 rounded text-xs">
                                    <strong>Ventaja:</strong> Podrá revisar y validar los identificadores antes de enviar el manifiesto final.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario Configuración -->
                    <form id="step1-form">
                        @csrf
                        <input type="hidden" name="webservice_type" value="micdta">
                        <input type="hidden" name="step" value="1">
                        <input type="hidden" id="complete-voyage-id" name="voyage_id">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label for="complete-environment" class="block text-sm font-medium text-gray-700 mb-2">
                                    Ambiente AFIP
                                </label>
                                <select name="environment" id="complete-environment" required
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="testing">🧪 Testing (Homologación)</option>
                                    <option value="production">🏭 Producción</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="complete-priority" class="block text-sm font-medium text-gray-700 mb-2">
                                    Prioridad
                                </label>
                                <select name="priority" id="complete-priority"
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                        </div>

                        <!-- Warning Producción -->
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg hidden" id="complete-production-warning">
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-red-900">⚠️ Envío a Producción AFIP</h4>
                                    <p class="text-sm text-red-700 mt-1">
                                        Este envío será procesado por AFIP en ambiente de producción.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeMicDtaCompleteModal()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md shadow-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                🚀 Paso 1: Generar Títulos y Envíos
                            </button>
                        </div>
                    </form>
                </div>

                <!-- PASO 2: Progreso y Resultado Paso 1 -->
                <div id="step-1-progress" class="hidden space-y-6">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto mb-4"></div>
                        <h4 class="text-lg font-medium text-gray-900">Ejecutando Paso 1</h4>
                        <p class="text-sm text-gray-600">Enviando Registro de Títulos y Envíos a AFIP...</p>
                    </div>
                </div>

                <!-- PASO 3: Revisión de TRACKs Generados -->
                <div id="step-1-review" class="hidden space-y-6">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-green-900">✅ Paso 1 Completado</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    Títulos y Envíos registrados exitosamente en AFIP. Revise los identificadores generados.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de TRACKs Generados -->
                    <div class="bg-white border border-gray-200 rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900">Identificadores Generados (TRACKs)</h4>
                            <p class="text-xs text-gray-600 mt-1">Estos identificadores se usarán en el Paso 2</p>
                        </div>
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Track ID</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tracks-table-body" class="bg-white divide-y divide-gray-200">
                                    <!-- Los TRACKs se llenarán aquí dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Detalles de la Transacción -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Detalles de la Transacción</h4>
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="font-medium">Transaction ID:</span>
                                <span id="step1-transaction-id" class="ml-1 font-mono">-</span>
                            </div>
                            <div>
                                <span class="font-medium">Fecha:</span>
                                <span id="step1-timestamp" class="ml-1">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex justify-between">
                        <button type="button" onclick="goBackToStep1()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                            ← Volver a Configuración
                        </button>
                        <div class="space-x-3">
                            <button type="button" onclick="closeMicDtaCompleteModal()"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                                Terminar Aquí
                            </button>
                            <button type="button" onclick="proceedToStep2()"
                                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                🚀 Paso 2: Enviar Manifiesto
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PASO 4: Progreso Paso 2 -->
                <div id="step-2-progress" class="hidden space-y-6">
                    <div class="text-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto mb-4"></div>
                        <h4 class="text-lg font-medium text-gray-900">Ejecutando Paso 2</h4>
                        <p class="text-sm text-gray-600">Enviando Manifiesto de Carga con identificadores validados...</p>
                    </div>
                </div>

                <!-- PASO 5: Resultado Final -->
                <div id="final-result" class="hidden space-y-6">
                    <div id="final-status-panel" class="border rounded-lg p-4">
                        <!-- Se llenará dinámicamente -->
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="viewTransactionDetails()"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700">
                            Ver Detalles Completos
                        </button>
                        <button type="button" onclick="closeMicDtaCompleteModal()"
                                class="px-4 py-2 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-md shadow-sm hover:bg-gray-700">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    @push('scripts')
    <script>
        // Variables globales
        let selectedVoyageId = null;
        
        // Mostrar modal de envío individual
        function showSendModal(voyageId, voyageNumber, countryCode) {
            selectedVoyageId = voyageId;
            
            // Actualizar información del viaje
            document.getElementById('modal-voyage-info').innerHTML = `
                <strong>${voyageNumber}</strong><br>
                <span class="text-gray-500">País destino: ${countryCode}</span>
            `;
            
            // Actualizar action del formulario
            document.getElementById('send-form').action = `/company/manifests/customs/${voyageId}/send`;
            
            // Pre-seleccionar webservice según país
            const webserviceSelect = document.getElementById('webservice_type');
            if (countryCode === 'AR') {
                webserviceSelect.value = 'micdta';
            } else if (countryCode === 'PY') {
                webserviceSelect.value = 'paraguay_customs';
            }
            
            // Mostrar modal
            document.getElementById('send-modal').classList.remove('hidden');
        }
        
        // Cerrar modal de envío
        function closeSendModal() {
            document.getElementById('send-modal').classList.add('hidden');
            selectedVoyageId = null;
        }
        
        // Manejar select all
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.voyage-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkSendButton();
        }
        
        // Actualizar botón de envío masivo
        function updateBulkSendButton() {
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            const bulkButton = document.getElementById('bulk-send-btn');
            const selectAll = document.getElementById('select-all');
            
            if (checkedBoxes.length > 0) {
                bulkButton.classList.remove('hidden');
                bulkButton.textContent = `🚀 Enviar ${checkedBoxes.length} Seleccionado(s)`;
            } else {
                bulkButton.classList.add('hidden');
            }
            
            // Actualizar estado del select all
            const allCheckboxes = document.querySelectorAll('.voyage-checkbox');
            selectAll.checked = allCheckboxes.length > 0 && checkedBoxes.length === allCheckboxes.length;
            selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
        }
        
        // Mostrar modal de envío masivo
        function showBulkSendModal() {
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Debe seleccionar al menos un manifiesto');
                return;
            }
            
            // Crear formulario dinámico para envío masivo
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("company.manifests.customs.sendBatch") }}';
            
            // Agregar token CSRF
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            // Agregar IDs de viajes seleccionados
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'voyage_ids[]';
                hiddenInput.value = checkbox.value;
                form.appendChild(hiddenInput);
            });
            
            // Confirmar envío
            if (confirm(`¿Confirma el envío de ${checkedBoxes.length} manifiesto(s) a la aduana?`)) {
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Reintentar transacción
        function retryTransaction(transactionId) {
            if (confirm('¿Desea reintentar el envío de este manifiesto?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/company/manifests/customs/${transactionId}/retry`;
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSendModal();
            }
        });
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkSendButton();
        });

        // Nueva función para modal específico
        function showSendModalSpecific(voyageId, voyageNumber, countryCode, webserviceType) {
            selectedVoyageId = voyageId;
            
            // Actualizar información del viaje
            document.getElementById('modal-voyage-info').innerHTML = `
                <strong>${voyageNumber}</strong><br>
                <span class="text-gray-500">Webservice: ${webserviceType.toUpperCase()} (${countryCode})</span>
            `;
            
            // Actualizar action del formulario
            document.getElementById('send-form').action = `/company/manifests/customs/${voyageId}/send`;
            
            // Pre-seleccionar webservice específico
            const webserviceSelect = document.getElementById('webservice_type');
            webserviceSelect.value = webserviceType;
            
            // Mostrar modal
            document.getElementById('send-modal').classList.remove('hidden');
        }

        let currentVoyageId = null;
let selectedFiles = [];

// Mostrar modal de adjuntos
function showAttachmentsModal(voyageId, voyageNumber, route) {
    currentVoyageId = voyageId;
    
    // Actualizar información del voyage
    document.getElementById('modal-voyage-title').textContent = `Viaje: ${voyageNumber}`;
    document.getElementById('modal-voyage-route').textContent = route;
    
    // Limpiar archivos seleccionados previos
    clearSelectedFiles();
    
    // Cargar adjuntos existentes
    loadExistingAttachments(voyageId);
    
    // Mostrar modal
    document.getElementById('attachments-modal').classList.remove('hidden');
}

// Cerrar modal
function closeAttachmentsModal() {
    document.getElementById('attachments-modal').classList.add('hidden');
    currentVoyageId = null;
    selectedFiles = [];
}

// Manejar selección de archivos
document.getElementById('pdf-files').addEventListener('change', function(e) {
    handleFileSelection(e.target.files);
});

// Manejar drag & drop
const dropZone = document.querySelector('#attachments-modal .border-dashed');
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-blue-500', 'bg-blue-50');
});

dropZone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-blue-500', 'bg-blue-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-blue-500', 'bg-blue-50');
    handleFileSelection(e.dataTransfer.files);
});

// Procesar archivos seleccionados
function handleFileSelection(files) {
    selectedFiles = Array.from(files).filter(file => file.type === 'application/pdf');
    
    if (selectedFiles.length !== files.length) {
        alert('Solo se permiten archivos PDF');
    }
    
    updateSelectedFilesList();
}

// Actualizar lista de archivos seleccionados
function updateSelectedFilesList() {
    const container = document.getElementById('files-container');
    const listDiv = document.getElementById('selected-files-list');
    const uploadBtn = document.getElementById('upload-files-btn');
    const clearBtn = document.getElementById('clear-files-btn');
    
    if (selectedFiles.length === 0) {
        listDiv.classList.add('hidden');
        uploadBtn.classList.add('hidden');
        clearBtn.classList.add('hidden');
        return;
    }
    
    listDiv.classList.remove('hidden');
    uploadBtn.classList.remove('hidden');
    clearBtn.classList.remove('hidden');
    
    container.innerHTML = selectedFiles.map((file, index) => `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
            <div class="flex items-center">
                <svg class="w-4 h-4 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm">${file.name}</span>
                <span class="text-xs text-gray-500 ml-2">(${(file.size / 1024 / 1024).toFixed(1)} MB)</span>
            </div>
            <button onclick="removeFile(${index})" class="text-red-500 hover:text-red-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `).join('');
}

// Remover archivo específico
function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateSelectedFilesList();
}

// Limpiar todos los archivos
function clearSelectedFiles() {
    selectedFiles = [];
    document.getElementById('pdf-files').value = '';
    updateSelectedFilesList();
}

// Subir archivos
async function uploadFiles() {
    if (selectedFiles.length === 0) return;
    
    const formData = new FormData();
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const statusText = document.getElementById('upload-status');
    
    progressDiv.classList.remove('hidden');
    statusText.textContent = 'Subiendo archivos...';
    
    try {
        const response = await fetch(`{{ route('company.manifests.customs.upload-attachments', '') }}/${currentVoyageId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            progressBar.style.width = '100%';
            statusText.textContent = '¡Archivos subidos exitosamente!';
            
            setTimeout(() => {
                clearSelectedFiles();
                loadExistingAttachments(currentVoyageId);
                progressDiv.classList.add('hidden');
            }, 2000);
        } else {
            throw new Error(result.error || 'Error al subir archivos');
        }
    } catch (error) {
        statusText.textContent = 'Error: ' + error.message;
        progressBar.style.width = '0%';
    }
}

// Cargar adjuntos existentes - VERSIÓN REAL
async function loadExistingAttachments(voyageId) {
    const container = document.getElementById('existing-files-container');
    const counter = document.getElementById('attachments-counter');
    
    try {
        // Consulta real a la API
        const response = await fetch(`/company/manifests/customs/${voyageId}/attachments-list`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        if (!response.ok) {
            throw new Error('Error al cargar adjuntos');
        }
        
        const attachments = await response.json();
        
        if (attachments.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-4">
                    <p class="text-sm">No hay documentos adjuntos</p>
                    <p class="text-xs text-gray-400">Los archivos subidos aparecerán aquí</p>
                </div>
            `;
            counter.textContent = '0 archivos';
        } else {
            container.innerHTML = attachments.map(file => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center flex-1">
                        <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                            <p class="text-xs text-gray-500">${file.size} • ${file.uploaded_at}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="downloadAttachment(${file.id})" 
                                class="p-1 text-blue-600 hover:text-blue-800 transition-colors"
                                title="Descargar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                        <button onclick="deleteAttachment(${file.id}, '${file.name}')" 
                                class="p-1 text-red-600 hover:text-red-800 transition-colors"
                                title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `).join('');
            
            counter.textContent = `${attachments.length} archivo${attachments.length !== 1 ? 's' : ''}`;
        }
    } catch (error) {
        container.innerHTML = `
            <div class="text-center text-red-500 py-4">
                <p class="text-sm">Error cargando adjuntos existentes</p>
                <p class="text-xs text-gray-400">${error.message}</p>
            </div>
        `;
        counter.textContent = 'Error';
    }
}

// Descargar adjunto específico
async function downloadAttachment(fileId) {
    try {
        window.open(`/company/manifests/customs/attachments/${fileId}/download`);
    } catch (error) {
        alert('Error al descargar el archivo');
    }
}

// Eliminar adjunto específico
async function deleteAttachment(fileId, fileName) {
    if (!confirm(`¿Está seguro de eliminar "${fileName}"?\n\nEsta acción no se puede deshacer.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/company/manifests/customs/attachments/${fileId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Recargar lista de adjuntos
            loadExistingAttachments(currentVoyageId);
            
            // Mostrar mensaje de éxito temporal
            alert(`Archivo "${fileName}" eliminado correctamente`);
        } else {
            throw new Error(result.error || 'Error al eliminar archivo');
        }
    } catch (error) {
        alert('Error al eliminar el archivo: ' + error.message);
    }
}

// Cerrar modal with Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAttachmentsModal();
    }
});

        // Descargar adjunto específico
        async function downloadAttachment(fileId) {
            try {
                // TODO: Implementar endpoint de descarga
                console.log('Descargando archivo ID:', fileId);
                // window.open(`/company/manifests/customs/attachments/${fileId}/download`);
            } catch (error) {
                alert('Error al descargar el archivo');
            }
        }

        // Eliminar adjunto específico
        async function deleteAttachment(fileId, fileName) {
            if (!confirm(`¿Está seguro de eliminar "${fileName}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            try {
                // TODO: Implementar endpoint de eliminación
                const response = await fetch(`/company/manifests/customs/attachments/${fileId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Recargar lista de adjuntos
                    loadExistingAttachments(currentVoyageId);
                    
                    // Mostrar mensaje de éxito
                    showSuccessMessage(`Archivo "${fileName}" eliminado correctamente`);
                } else {
                    throw new Error(result.error || 'Error al eliminar archivo');
                }
            } catch (error) {
                alert('Error al eliminar el archivo: ' + error.message);
            }
        }

        // Función helper para mostrar mensajes de éxito
        function showSuccessMessage(message) {
            // TODO: Implementar toast/notification
            console.log('SUCCESS:', message);
        }

        // Cerrar modal with Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAttachmentsModal();
            }
        });

        // Función para verificar adjuntos existentes (llamar al cargar la página)
        async function checkExistingAttachments() {
            const indicators = document.querySelectorAll('[id^="attachments-indicator-"]');
            
            indicators.forEach(async (indicator) => {
                const voyageId = indicator.id.replace('attachments-indicator-', '');
                
                try {
                    // TODO: Implementar endpoint para consultar adjuntos existentes
                    // const response = await fetch(`/company/manifests/customs/${voyageId}/attachments-count`);
                    // const data = await response.json();
                    
                    // if (data.count > 0) {
                    //     indicator.textContent = `📄 ${data.count}`;
                    //     indicator.classList.remove('hidden');
                    // }
                } catch (error) {
                    console.log('No se pudieron verificar adjuntos para voyage:', voyageId);
                }
            });
        }

        // Llamar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            checkExistingAttachments();
        });

        // Función para mostrar/ocultar información según webservice seleccionado
        function updateWebserviceInfo() {
            const select = document.getElementById('webservice_type');
            const tracksPanel = document.getElementById('tracks-info-panel');
            
            if (select.value === 'micdta') {
                tracksPanel.classList.remove('hidden');
            } else {
                tracksPanel.classList.add('hidden');
            }
        }

        // Llamar al cargar la página si hay valor preseleccionado
        document.addEventListener('DOMContentLoaded', function() {
            updateWebserviceInfo();
        });

        // Variables para el modal MIC/DTA Completo
        let selectedCompleteVoyageId = null;
        let step1TransactionId = null;
        let generatedTracks = [];

        // Mostrar modal MIC/DTA Completo
        function showMicDtaCompleteModal(voyageId, voyageNumber) {
            selectedCompleteVoyageId = voyageId;
            
            // Resetear estado
            resetCompleteModal();
            
            // Actualizar información del modal
            document.getElementById('complete-voyage-title').textContent = voyageNumber;
            document.getElementById('complete-voyage-id').value = voyageId;
            
            // Mostrar modal
            document.getElementById('micdta-complete-modal').classList.remove('hidden');
        }

        // Cerrar modal MIC/DTA Completo
        function closeMicDtaCompleteModal() {
            document.getElementById('micdta-complete-modal').classList.add('hidden');
            selectedCompleteVoyageId = null;
            step1TransactionId = null;
            generatedTracks = [];
        }

        // Resetear modal a estado inicial
        function resetCompleteModal() {
            // Mostrar solo paso 1
            document.getElementById('step-1-config').classList.remove('hidden');
            document.getElementById('step-1-progress').classList.add('hidden');
            document.getElementById('step-1-review').classList.add('hidden');
            document.getElementById('step-2-progress').classList.add('hidden');
            document.getElementById('final-result').classList.add('hidden');
            
            // Limpiar datos
            step1TransactionId = null;
            generatedTracks = [];
        }

        // Volver al paso 1
        function goBackToStep1() {
            resetCompleteModal();
        }

        // Manejar cambio de ambiente para mostrar warning
        document.getElementById('complete-environment').addEventListener('change', function() {
            const warning = document.getElementById('complete-production-warning');
            if (this.value === 'production') {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        });

        // Interceptar envío del formulario Paso 1
        document.getElementById('step1-form').addEventListener('submit', function(e) {
            e.preventDefault();
            executeStep1();
        });

        // Ejecutar Paso 1: RegistrarTitEnvios
        function executeStep1() {
            // Mostrar progreso
            document.getElementById('step-1-config').classList.add('hidden');
            document.getElementById('step-1-progress').classList.remove('hidden');
            
            // Preparar datos del formulario
            const formData = new FormData(document.getElementById('step1-form'));
            formData.append('step', '1'); // Indicar que es solo paso 1
            
            // Enviar a controller
            fetch(`/company/manifests/customs/${selectedCompleteVoyageId}/send-step1`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStep1Results(data);
                } else {
                    showStep1Error(data);
                }
            })
            .catch(error => {
                showStep1Error({ error_message: 'Error de conexión: ' + error.message });
            });
        }

        // Mostrar resultados del Paso 1
        function showStep1Results(data) {
            // Ocultar progreso
            document.getElementById('step-1-progress').classList.add('hidden');
            
            // Guardar datos
            step1TransactionId = data.transaction_id;
            generatedTracks = data.tracks || [];
            
            // Llenar tabla de TRACKs
            const tableBody = document.getElementById('tracks-table-body');
            tableBody.innerHTML = '';
            
            if (generatedTracks.length > 0) {
                generatedTracks.forEach(track => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-4 py-2 text-xs font-mono">${track.track_number}</td>
                        <td class="px-4 py-2 text-xs">${track.track_type || 'envio'}</td>
                        <td class="px-4 py-2 text-xs">${track.reference_number || 'N/A'}</td>
                        <td class="px-4 py-2 text-xs">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Generado
                            </span>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-xs text-center text-gray-500">
                            No se generaron TRACKs (posible error en respuesta AFIP)
                        </td>
                    </tr>
                `;
            }
            
            // Llenar detalles de transacción
            document.getElementById('step1-transaction-id').textContent = data.transaction_id || 'N/A';
            document.getElementById('step1-timestamp').textContent = new Date().toLocaleString();
            
            // Mostrar panel de revisión
            document.getElementById('step-1-review').classList.remove('hidden');
        }

        // Mostrar error del Paso 1
        function showStep1Error(data) {
            // Ocultar progreso
            document.getElementById('step-1-progress').classList.add('hidden');
            
            // Mostrar error
            alert('❌ Error en Paso 1:\n\n' + (data.error_message || 'Error desconocido'));
            
            // Volver a configuración
            goBackToStep1();
        }

        // Proceder al Paso 2
        function proceedToStep2() {
            if (!step1TransactionId || generatedTracks.length === 0) {
                alert('⚠️ No hay TRACKs válidos para proceder al Paso 2');
                return;
            }
            
            // Mostrar progreso del paso 2
            document.getElementById('step-1-review').classList.add('hidden');
            document.getElementById('step-2-progress').classList.remove('hidden');
            
            // Preparar datos para paso 2
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('step', '2');
            formData.append('step1_transaction_id', step1TransactionId);
            formData.append('tracks', JSON.stringify(generatedTracks.map(t => t.track_number)));
            
            // Enviar paso 2
            fetch(`/company/manifests/customs/${selectedCompleteVoyageId}/send-step2`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                showFinalResults(data);
            })
            .catch(error => {
                showFinalResults({ 
                    success: false, 
                    error_message: 'Error de conexión: ' + error.message 
                });
            });
        }

        // Mostrar resultados finales
        function showFinalResults(data) {
            // Ocultar progreso
            document.getElementById('step-2-progress').classList.add('hidden');
            
            // Preparar panel de resultados
            const statusPanel = document.getElementById('final-status-panel');
            
            if (data.success) {
                statusPanel.className = 'border border-green-200 bg-green-50 rounded-lg p-4';
                statusPanel.innerHTML = `
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-green-900">✅ Proceso Completo Exitoso</h4>
                            <p class="text-sm text-green-700 mt-1">
                                Ambos pasos ejecutados correctamente. MIC/DTA enviado con identificadores validados.
                            </p>
                            <div class="mt-3 text-xs text-green-600">
                                <p><strong>Paso 1:</strong> ${generatedTracks.length} identificadores generados</p>
                                <p><strong>Paso 2:</strong> Manifiesto enviado exitosamente</p>
                                ${data.confirmation_number ? `<p><strong>Confirmación:</strong> ${data.confirmation_number}</p>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                statusPanel.className = 'border border-red-200 bg-red-50 rounded-lg p-4';
                statusPanel.innerHTML = `
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-red-900">❌ Error en Paso 2</h4>
                            <p class="text-sm text-red-700 mt-1">
                                El Paso 1 fue exitoso, pero falló el envío del manifiesto.
                            </p>
                            <div class="mt-3 text-xs text-red-600">
                                <p><strong>Error:</strong> ${data.error_message || 'Error desconocido'}</p>
                                <p><strong>Nota:</strong> Los identificadores del Paso 1 siguen siendo válidos.</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Mostrar resultado final
            document.getElementById('final-result').classList.remove('hidden');
        }

        // Ver detalles de transacción
        function viewTransactionDetails() {
            if (step1TransactionId) {
                window.open(`/company/manifests/customs/status/${step1TransactionId}`, '_blank');
            }
        }

    </script>


    @endpush
</x-app-layout>