{{-- 
  SISTEMA MODULAR WEBSERVICES - Vista Index Información Anticipada Argentina
  Ubicación: resources/views/company/simple/anticipada/index.blade.php
  
  Lista específica de voyages para Información Anticipada Argentina con filtros.
  Integra con ArgentinaAnticipatedService para validaciones específicas.
  
  DATOS VERIFICADOS DEL CONTROLADOR:
  - $voyages (collection con paginación)
  - $company (modelo Company)
  - $status_filter (string nullable)
  - $webservice_type ('anticipada')
  - $webservice_config (array de configuración)
  
  CAMPOS Viaje VERIFICADOS:
  - voyage_number, departure_date
  - leadVessel->name, leadVessel->registration_number
  - originPort->code, destinationPort->code
  - webserviceStatuses (relación hasMany filtrada por 'anticipada')
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
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-blue-800">Información Anticipada</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Sistema para envío anticipado de información de viajes a AFIP Argentina. Métodos disponibles:</p>
                            <ul class="list-disc ml-5 mt-1">
                                <li><strong>RegistrarViaje:</strong> Registro inicial del viaje con información anticipada</li>
                                <li><strong>RectificarViaje:</strong> Rectificación de viaje ya registrado</li>
                                <li><strong>RegistrarTitulosCbc:</strong> Registro de títulos ATA CBC</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Filtros --}}
                <div class="mt-4 pt-4 border-t border-blue-200">
                    <form method="GET" class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="status" class="text-sm font-medium text-blue-700">Estado:</label>
                            <select name="status" id="status" class="border-gray-300 rounded-md shadow-sm text-sm">
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
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                Filtrar
                            </button>
                        </div>
                        @if($status_filter)
                            <div>
                                <a href="{{ route('company.simple.anticipada.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Limpiar
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Lista de Viajes --}}
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                @if($voyages->count() > 0)
                    {{-- Header con información --}}
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <h3 class="text-sm font-medium text-gray-900">
                                {{ $voyages->total() }} viaje{{ $voyages->total() != 1 ? 's' : '' }} encontrado{{ $voyages->total() != 1 ? 's' : '' }}
                            </h3>
                            @if($status_filter)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Filtrado por: {{ ucfirst($status_filter) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Tabla de voyages --}}                   
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Viaje
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Embarcación
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ruta
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha Salida
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado Anticipada
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($voyages as $voyage)
                                    @php
                                        // Obtener estado de anticipada para este voyage
                                        $anticipadaStatus = $voyage->webserviceStatuses->where('webservice_type', 'anticipada')->first();
                                        $statusText = $anticipadaStatus ? $anticipadaStatus->status : 'pending';
                                        $statusColor = $anticipadaStatus ? $anticipadaStatus->getStatusColor() : 'gray';
                                        $canSend = $anticipadaStatus ? $anticipadaStatus->canSend() : true;
                                    @endphp
                                    
                                    <tr data-voyage-id="{{ $voyage->id }}" class="hover:bg-gray-50">
                                        {{-- Voyage Number --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $voyage->voyage_number }}
                                                </div>
                                            </div>
                                        </td>
                                        
                                        {{-- Embarcación --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="font-medium">{{ $voyage->leadVessel?->name ?? 'Sin embarcación' }}</div>
                                                @if($voyage->leadVessel?->registration_number)
                                                    <div class="text-gray-500 text-xs">{{ $voyage->leadVessel->registration_number }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        
                                        {{-- Ruta --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $voyage->originPort?->code ?? '?' }} → {{ $voyage->destinationPort?->code ?? '?' }}
                                        </td>

                                        {{-- Fecha Salida --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($voyage->departure_date)
                                                {{ $voyage->departure_date->format('d/m/Y') }}
                                                <div class="text-xs text-gray-400">{{ $voyage->departure_date->format('H:i') }}</div>
                                            @else
                                                <span class="text-gray-400">Sin fecha</span>
                                            @endif
                                        </td>
                                        
                                        {{-- Estado Anticipada --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800">
                                                @if($anticipadaStatus)
                                                    {{ $anticipadaStatus->getStatusDescription() }}
                                                @else
                                                    No enviado
                                                @endif
                                            </span>
                                            
                                            @if($anticipadaStatus && $anticipadaStatus->last_sent_at)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    {{ $anticipadaStatus->last_sent_at->format('d/m/Y H:i') }}
                                                </div>
                                            @endif
                                        </td>
                                        
                                        {{-- Acciones --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                {{-- Ver detalles --}}
                                                <a href="{{ route('company.simple.anticipada.show', $voyage) }}" 
                                                   class="text-blue-600 hover:text-blue-900 text-sm">
                                                    Ver Detalles
                                                </a>
                                                
                                                {{-- Enviar (si puede) --}}
                                                @if($canSend)
                                                    <span class="text-gray-300">|</span>
                                                    <button onclick="sendAnticipada({{ $voyage->id }}, 'RegistrarViaje')" 
                                                            class="text-green-600 hover:text-green-900 text-sm">
                                                        Enviar
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación --}}
                    @if($voyages->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $voyages->appends(request()->query())->links() }}
                        </div>
                    @endif

                @else
                    {{-- Sin resultados --}}
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay Viajes</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            @if($status_filter)
                                No se encontraron Viajes con el filtro aplicado.
                            @else
                                Aún no tienes Viajes para enviar información anticipada.
                            @endif
                        </p>
                        @if($status_filter)
                            <div class="mt-6">
                                <a href="{{ route('company.simple.anticipada.index') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    Ver todos los Viajes
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- JavaScript para funcionalidad AJAX --}}
    <script>
        // Función para enviar información anticipada
        function sendAnticipada(voyageId, method = 'Registrar Viaje') {
            if (!confirm(`¿Está seguro de enviar ${method} para este Viaje?`)) {
                return;
            }

            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Enviando...';
            button.disabled = true;

            fetch(`/company/simple/webservices/anticipada/${voyageId}/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    method: method,
                    environment: 'testing',
                    notes: `Enviado desde vista index - ${method}`
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${method} enviado exitosamente`);
                    location.reload(); // Recargar para ver estado actualizado
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
            });
        }

        // Auto-refresh opcional cada 30 segundos para estados
        let autoRefresh = false;
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            const status = document.getElementById('auto-refresh-status');
            if (status) {
                status.textContent = autoRefresh ? 'ON' : 'OFF';
            }
            
            if (autoRefresh) {
                setTimeout(function refreshPage() {
                    if (autoRefresh) {
                        location.reload();
                    }
                }, 30000);
            }
        }
    </script>
</x-app-layout>