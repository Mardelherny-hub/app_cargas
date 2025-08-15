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
                                        $lastTransaction = $voyage->webserviceTransactions->last();
                                    @endphp
                                    
                                    @if(!$lastTransaction)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            ⏳ No enviado
                                        </span>
                                    @elseif($lastTransaction->status === 'success')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            ✅ Enviado
                                        </span>
                                        @if($lastTransaction->confirmation_number)
                                            <div class="text-xs text-gray-500 mt-1">
                                                #{{ $lastTransaction->confirmation_number }}
                                            </div>
                                        @endif
                                    @elseif($lastTransaction->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            🔄 Procesando...
                                        </span>
                                    @elseif($lastTransaction->status === 'error')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ❌ Error
                                        </span>
                                        <div class="text-xs text-red-600 mt-1">
                                            {{ Str::limit($lastTransaction->error_message, 30) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    @if(!$lastTransaction || $lastTransaction->status === 'error')
                                        <button type="button" 
                                                class="text-indigo-600 hover:text-indigo-900"
                                                onclick="showSendModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}', '{{ $voyage->destination_port->country->iso_code ?? 'AR' }}')">
                                            🚀 Enviar
                                        </button>
                                    @endif
                                    
                                    @if($lastTransaction)
                                        <a href="{{ route('company.manifests.customs.status', $lastTransaction->id) }}" 
                                           class="text-gray-600 hover:text-gray-900">
                                            📊 Estado
                                        </a>
                                    @endif
                                    
                                    @if($lastTransaction && $lastTransaction->status === 'error')
                                        <button type="button" 
                                                class="text-green-600 hover:text-green-900"
                                                onclick="retryTransaction({{ $lastTransaction->id }})">
                                            🔄 Reintentar
                                        </button>
                                    @endif
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
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option value="micdta">🇦🇷 Argentina MIC/DTA</option>
                                <option value="anticipada">🇦🇷 Argentina Anticipada</option>
                                <option value="desconsolidado">🇦🇷 Argentina Desconsolidados</option>
                                <option value="paraguay_customs">🇵🇾 Paraguay DNA</option>
                            </select>
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
    </script>
    @endpush
</x-app-layout>