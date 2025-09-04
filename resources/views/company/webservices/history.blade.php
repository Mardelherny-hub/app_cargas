<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Historial de Webservices') }} - {{ $company->legal_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                    ← Dashboard
                </a>
                <a href="{{ route('company.webservices.query') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    🔍 Consultar Estado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filtros de Búsqueda --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">🔍 Filtros de Búsqueda</h3>
                </div>
                <div class="p-6">
                    <form method="GET" action="{{ route('company.webservices.history') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            
                            {{-- Tipo de Webservice --}}
                            <div>
                                <label for="webservice_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Webservice
                                </label>
                                <select name="webservice_type" id="webservice_type" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="">Todos los tipos</option>
                                    @foreach($availableTypes as $key => $name)
                                    <option value="{{ $key }}" {{ request('webservice_type') === $key ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Estado --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select name="status" id="status" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="">Todos los estados</option>
                                    @foreach($statuses as $key => $name)
                                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Fecha Desde --}}
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">
                                    Fecha Desde
                                </label>
                                <input type="date" name="date_from" id="date_from" 
                                       value="{{ request('date_from') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>

                            {{-- Fecha Hasta --}}
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">
                                    Fecha Hasta
                                </label>
                                <input type="date" name="date_to" id="date_to" 
                                       value="{{ request('date_to') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>

                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                Mostrando {{ $transactions->count() }} de {{ $transactions->total() }} transacciones
                            </div>
                            <div class="flex space-x-2">
                                <a href="{{ route('company.webservices.history') }}" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg text-sm transition-colors">
                                    Limpiar Filtros
                                </a>
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                    Filtrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Lista de Transacciones --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">📋 Transacciones de Webservices</h3>
                </div>
                
                @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tipo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID de Transacción
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Usuario
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Entorno
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                {{-- Estado --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="w-2 h-2 rounded-full mr-2 
                                            {{ $transaction->status === 'success' ? 'bg-green-500' : 
                                               ($transaction->status === 'pending' ? 'bg-yellow-500' : 'bg-red-500') }}">
                                        </span>
                                        <span class="text-sm font-medium 
                                            {{ $transaction->status === 'success' ? 'text-green-700' : 
                                               ($transaction->status === 'pending' ? 'text-yellow-700' : 'text-red-700') }}">
                                            {{ match($transaction->status) {
                                                'success' => 'Exitoso',
                                                'pending' => 'Pendiente',
                                                'error' => 'Error',
                                                'expired' => 'Expirado',
                                                default => ucfirst($transaction->status)
                                            } }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Tipo --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ ucfirst($transaction->webservice_type) }}</div>
                                    @if($transaction->external_reference)
                                    <div class="text-xs text-gray-500">Ref: {{ $transaction->external_reference }}</div>
                                    @endif
                                </td>

                                {{-- ID de Transacción --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900">{{ $transaction->transaction_id }}</div>
                                </td>

                                {{-- Usuario --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $transaction->user->name ?? 'Sistema' }}</div>
                                </td>

                                {{-- Fecha --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $transaction->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $transaction->created_at->format('H:i:s') }}</div>
                                </td>

                                {{-- Entorno --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $transaction->environment === 'production' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $transaction->environment === 'production' ? 'Producción' : 'Testing' }}
                                    </span>
                                </td>

                                {{-- Acciones --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        {{-- Ver Detalles --}}
                                        <button onclick="showTransactionDetails({{ $transaction->id }})" 
                                                class="text-blue-600 hover:text-blue-900">
                                            Ver
                                        </button>
                                        
                                        {{-- Reintentar si falló --}}
                                        @if($transaction->status === 'error')
                                        <button onclick="retryTransaction({{ $transaction->id }})" 
                                                class="text-green-600 hover:text-green-900">
                                            Reintentar
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
                @if($transactions->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $transactions->appends(request()->query())->links() }}
                </div>
                @endif

                @else
                {{-- Estado Vacío --}}
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-gray-400 text-2xl">📋</span>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay transacciones</h3>
                    <p class="text-gray-600 mb-4">
                        @if(request()->hasAny(['webservice_type', 'status', 'date_from', 'date_to']))
                            No se encontraron transacciones con los filtros aplicados.
                        @else
                            Aún no se han enviado manifiestos a los webservices aduaneros.
                        @endif
                    </p>
                    <div class="flex justify-center space-x-3">
                        @if(request()->hasAny(['webservice_type', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('company.webservices.history') }}" 
                           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            Limpiar Filtros
                        </a>
                        @endif
                        <a href="{{ route('company.manifests.import.index') }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            Importar Manifiestos
                        </a>
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Modal para Detalles de Transacción --}}
    <div id="transactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Detalles de Transacción</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Cerrar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="modalContent">
                    <!-- Contenido se carga dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript --}}
    <script>

       // Reemplazar la función showTransactionDetails() en resources/views/company/webservices/history.blade.php

function showTransactionDetails(transactionId) {
    const modal = document.getElementById('transactionModal');
    const content = document.getElementById('modalContent');
    
    // Mostrar loading
    content.innerHTML = `
        <div class="space-y-4">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-sm text-gray-500 mt-4">Cargando detalles de la transacción...</p>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    
    // Llamada AJAX para obtener detalles reales - URL directa
    fetch(`/company/webservices/transaction/${transactionId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderTransactionDetails(data.transaction, data.response);
        } else {
            showErrorModal(data.message || 'Error al cargar los detalles');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorModal('Error de conexión al cargar los detalles');
    });
}

function renderTransactionDetails(transaction, response) {
    const content = document.getElementById('modalContent');
    
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Estado General -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-semibold text-gray-900">📋 Información General</h4>
                    ${getStatusBadge(transaction.status)}
                </div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">ID Transacción:</span>
                        <span class="font-mono text-blue-600">${transaction.transaction_id}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Tipo:</span>
                        <span class="font-medium">${getWebserviceTypeName(transaction.webservice_type)}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">País:</span>
                        <span class="font-medium">${transaction.country === 'AR' ? '🇦🇷 Argentina' : '🇵🇾 Paraguay'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Entorno:</span>
                        <span class="font-medium ${transaction.environment === 'production' ? 'text-red-600' : 'text-blue-600'}">
                            ${transaction.environment === 'production' ? 'Producción' : 'Testing'}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Usuario:</span>
                        <span class="font-medium">${transaction.user_name || 'Sistema'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Fecha:</span>
                        <span class="font-medium">${formatDate(transaction.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Respuesta del Webservice -->
            ${renderResponseSection(transaction, response)}

            <!-- Datos de Envío -->
            ${renderShipmentData(transaction)}

            <!-- Logs y Detalles Técnicos -->
            ${renderTechnicalDetails(transaction)}

            <!-- Acciones Disponibles -->
            ${renderAvailableActions(transaction)}
        </div>
    `;
}

function renderResponseSection(transaction, response) {
    if (!response || transaction.status === 'pending') {
        return `
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <h4 class="font-semibold text-yellow-800 mb-2">⏳ Sin Respuesta</h4>
                <p class="text-yellow-700 text-sm">La transacción aún está pendiente o no se ha recibido respuesta del webservice.</p>
            </div>
        `;
    }

    if (transaction.status === 'success') {
        return `
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <h4 class="font-semibold text-green-800 mb-3">✅ Respuesta Exitosa</h4>
                <div class="space-y-3">
                    ${response.confirmation_number ? `
                        <div class="flex items-center justify-between p-3 bg-white rounded border">
                            <span class="text-sm text-gray-600">Número de Confirmación:</span>
                            <span class="font-mono text-green-700 font-medium">${response.confirmation_number}</span>
                        </div>
                    ` : ''}
                    ${response.reference_number ? `
                        <div class="flex items-center justify-between p-3 bg-white rounded border">
                            <span class="text-sm text-gray-600">Número de Referencia:</span>
                            <span class="font-mono text-blue-700 font-medium">${response.reference_number}</span>
                        </div>
                    ` : ''}
                    ${response.voyage_number ? `
                        <div class="flex items-center justify-between p-3 bg-white rounded border">
                            <span class="text-sm text-gray-600">Número de Viaje:</span>
                            <span class="font-mono text-purple-700 font-medium">${response.voyage_number}</span>
                        </div>
                    ` : ''}
                    ${renderTrackingNumbers(response.tracking_numbers)}
                    ${renderCustomsStatus(response)}
                </div>
            </div>
        `;
    } else {
        return `
            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                <h4 class="font-semibold text-red-800 mb-3">❌ Error en Respuesta</h4>
                <div class="space-y-3">
                    <div class="p-3 bg-white rounded border border-red-300">
                        <div class="text-sm text-gray-600 mb-1">Código de Error:</div>
                        <div class="font-mono text-red-700 font-medium">${transaction.error_code || 'N/A'}</div>
                    </div>
                    <div class="p-3 bg-white rounded border border-red-300">
                        <div class="text-sm text-gray-600 mb-1">Mensaje de Error:</div>
                        <div class="text-red-700">${transaction.error_message || 'Error desconocido'}</div>
                    </div>
                    ${transaction.retry_count > 0 ? `
                        <div class="p-3 bg-yellow-100 rounded border border-yellow-300">
                            <div class="text-sm text-yellow-800">
                                <strong>Reintentos:</strong> ${transaction.retry_count} de ${transaction.max_retries}
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
}

function renderTrackingNumbers(trackingNumbers) {
    if (!trackingNumbers || trackingNumbers.length === 0) return '';
    
    return `
        <div class="p-3 bg-white rounded border">
            <div class="text-sm text-gray-600 mb-2">Números de Seguimiento:</div>
            <div class="flex flex-wrap gap-2">
                ${trackingNumbers.map(num => `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${num}
                    </span>
                `).join('')}
            </div>
        </div>
    `;
}

function renderCustomsStatus(response) {
    if (!response.customs_status) return '';
    
    const statusColor = {
        'approved': 'green',
        'pending': 'yellow', 
        'rejected': 'red',
        'review': 'orange'
    }[response.customs_status] || 'gray';
    
    return `
        <div class="p-3 bg-white rounded border">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600">Estado Aduanero:</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-${statusColor}-100 text-${statusColor}-800">
                    ${response.customs_status.toUpperCase()}
                </span>
            </div>
            ${response.customs_processed_at ? `
                <div class="text-xs text-gray-500 mt-1">
                    Procesado: ${formatDate(response.customs_processed_at)}
                </div>
            ` : ''}
        </div>
    `;
}

function renderShipmentData(transaction) {
    return `
        <div class="bg-blue-50 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-3">📦 Datos de Envío</h4>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center p-3 bg-white rounded">
                    <div class="text-2xl font-bold text-blue-600">${transaction.container_count || 0}</div>
                    <div class="text-gray-600">Contenedores</div>
                </div>
                <div class="text-center p-3 bg-white rounded">
                    <div class="text-2xl font-bold text-green-600">${formatWeight(transaction.total_weight_kg)}</div>
                    <div class="text-gray-600">Peso Total</div>
                </div>
                <div class="text-center p-3 bg-white rounded">
                    <div class="text-2xl font-bold text-purple-600">${formatCurrency(transaction.total_value, transaction.currency_code)}</div>
                    <div class="text-gray-600">Valor Total</div>
                </div>
            </div>
        </div>
    `;
}

function renderTechnicalDetails(transaction) {
    return `
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-800 mb-3">🔧 Detalles Técnicos</h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">URL Webservice:</span>
                    <div class="font-mono text-xs text-blue-600 break-all">${transaction.webservice_url}</div>
                </div>
                <div>
                    <span class="text-gray-500">Tiempo de Respuesta:</span>
                    <span class="font-medium">${transaction.response_time_ms ? transaction.response_time_ms + 'ms' : 'N/A'}</span>
                </div>
                ${transaction.sent_at ? `
                    <div>
                        <span class="text-gray-500">Enviado:</span>
                        <span class="font-medium">${formatDateTime(transaction.sent_at)}</span>
                    </div>
                ` : ''}
                ${transaction.response_at ? `
                    <div>
                        <span class="text-gray-500">Respuesta:</span>
                        <span class="font-medium">${formatDateTime(transaction.response_at)}</span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function renderAvailableActions(transaction) {
    const actions = [];
    
    if (transaction.status === 'error' && transaction.retry_count < transaction.max_retries) {
        actions.push(`
            <button onclick="retryTransaction(${transaction.id})" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                🔄 Reintentar
            </button>
        `);
    }
    
    if (transaction.request_xml) {
        actions.push(`
            <button onclick="viewXML('request', ${transaction.id})" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                📄 Ver XML Enviado
            </button>
        `);
    }
    
    if (transaction.response_xml) {
        actions.push(`
            <button onclick="viewXML('response', ${transaction.id})" 
                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                📄 Ver XML Respuesta
            </button>
        `);
    }
    
    if (actions.length === 0) {
        return '';
    }
    
    return `
        <div class="bg-white p-4 rounded-lg border-t border-gray-200">
            <h4 class="font-semibold text-gray-800 mb-3">⚡ Acciones Disponibles</h4>
            <div class="flex flex-wrap gap-2">
                ${actions.join('')}
            </div>
        </div>
    `;
}

// Funciones auxiliares
function getStatusBadge(status) {
    const badges = {
        'success': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Exitoso</span>',
        'error': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Error</span>',
        'pending': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⏳ Pendiente</span>',
        'retry': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">🔄 Reintentando</span>'
    };
    return badges[status] || badges['pending'];
}

function getWebserviceTypeName(type) {
    const names = {
        'micdta': 'MIC/DTA',
        'anticipada': 'Información Anticipada',
        'transbordo': 'Transbordos',
        'desconsolidados': 'Desconsolidados',
        'paraguay_customs': 'Aduana Paraguay'
    };
    return names[type] || type;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatWeight(kg) {
    if (!kg) return '0 kg';
    if (kg >= 1000) {
        return (kg / 1000).toFixed(1) + ' t';
    }
    return kg.toFixed(0) + ' kg';
}

function formatCurrency(value, currency) {
    if (!value) return '$0';
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: 0
    }).format(value);
}

function showErrorModal(message) {
    const content = document.getElementById('modalContent');
    content.innerHTML = `
        <div class="text-center py-8">
            <div class="text-red-500 text-6xl mb-4">❌</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error</h3>
            <p class="text-gray-600">${message}</p>
        </div>
    `;
}

        function renderTransactionDetails(transaction, response) {
            const content = document.getElementById('modalContent');
            
            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Estado General -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-gray-900">📋 Información General</h4>
                            ${getStatusBadge(transaction.status)}
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">ID Transacción:</span>
                                <span class="font-mono text-blue-600">${transaction.transaction_id}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Tipo:</span>
                                <span class="font-medium">${getWebserviceTypeName(transaction.webservice_type)}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">País:</span>
                                <span class="font-medium">${transaction.country === 'AR' ? '🇦🇷 Argentina' : '🇵🇾 Paraguay'}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Entorno:</span>
                                <span class="font-medium ${transaction.environment === 'production' ? 'text-red-600' : 'text-blue-600'}">
                                    ${transaction.environment === 'production' ? 'Producción' : 'Testing'}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500">Usuario:</span>
                                <span class="font-medium">${transaction.user_name || 'Sistema'}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Fecha:</span>
                                <span class="font-medium">${formatDate(transaction.created_at)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Respuesta del Webservice -->
                    ${renderResponseSection(transaction, response)}

                    <!-- Datos de Envío -->
                    ${renderShipmentData(transaction)}

                    <!-- Logs y Detalles Técnicos -->
                    ${renderTechnicalDetails(transaction)}

                    <!-- Acciones Disponibles -->
                    ${renderAvailableActions(transaction)}
                </div>
            `;
        }

        function renderResponseSection(transaction, response) {
            if (!response || transaction.status === 'pending') {
                return `
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-800 mb-2">⏳ Sin Respuesta</h4>
                        <p class="text-yellow-700 text-sm">La transacción aún está pendiente o no se ha recibido respuesta del webservice.</p>
                    </div>
                `;
            }

            if (transaction.status === 'success') {
                return `
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-800 mb-3">✅ Respuesta Exitosa</h4>
                        <div class="space-y-3">
                            ${response.confirmation_number ? `
                                <div class="flex items-center justify-between p-3 bg-white rounded border">
                                    <span class="text-sm text-gray-600">Número de Confirmación:</span>
                                    <span class="font-mono text-green-700 font-medium">${response.confirmation_number}</span>
                                </div>
                            ` : ''}
                            ${response.reference_number ? `
                                <div class="flex items-center justify-between p-3 bg-white rounded border">
                                    <span class="text-sm text-gray-600">Número de Referencia:</span>
                                    <span class="font-mono text-blue-700 font-medium">${response.reference_number}</span>
                                </div>
                            ` : ''}
                            ${response.voyage_number ? `
                                <div class="flex items-center justify-between p-3 bg-white rounded border">
                                    <span class="text-sm text-gray-600">Número de Viaje:</span>
                                    <span class="font-mono text-purple-700 font-medium">${response.voyage_number}</span>
                                </div>
                            ` : ''}
                            ${renderTrackingNumbers(response.tracking_numbers)}
                            ${renderCustomsStatus(response)}
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-800 mb-3">❌ Error en Respuesta</h4>
                        <div class="space-y-3">
                            <div class="p-3 bg-white rounded border border-red-300">
                                <div class="text-sm text-gray-600 mb-1">Código de Error:</div>
                                <div class="font-mono text-red-700 font-medium">${transaction.error_code || 'N/A'}</div>
                            </div>
                            <div class="p-3 bg-white rounded border border-red-300">
                                <div class="text-sm text-gray-600 mb-1">Mensaje de Error:</div>
                                <div class="text-red-700">${transaction.error_message || 'Error desconocido'}</div>
                            </div>
                            ${transaction.retry_count > 0 ? `
                                <div class="p-3 bg-yellow-100 rounded border border-yellow-300">
                                    <div class="text-sm text-yellow-800">
                                        <strong>Reintentos:</strong> ${transaction.retry_count} de ${transaction.max_retries}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }
        }

        function renderTrackingNumbers(trackingNumbers) {
            if (!trackingNumbers || trackingNumbers.length === 0) return '';
            
            return `
                <div class="p-3 bg-white rounded border">
                    <div class="text-sm text-gray-600 mb-2">Números de Seguimiento:</div>
                    <div class="flex flex-wrap gap-2">
                        ${trackingNumbers.map(num => `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                ${num}
                            </span>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        function renderCustomsStatus(response) {
            if (!response.customs_status) return '';
            
            const statusColor = {
                'approved': 'green',
                'pending': 'yellow', 
                'rejected': 'red',
                'review': 'orange'
            }[response.customs_status] || 'gray';
            
            return `
                <div class="p-3 bg-white rounded border">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Estado Aduanero:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-${statusColor}-100 text-${statusColor}-800">
                            ${response.customs_status.toUpperCase()}
                        </span>
                    </div>
                    ${response.customs_processed_at ? `
                        <div class="text-xs text-gray-500 mt-1">
                            Procesado: ${formatDate(response.customs_processed_at)}
                        </div>
                    ` : ''}
                </div>
            `;
        }

        function renderShipmentData(transaction) {
            return `
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-3">📦 Datos de Envío</h4>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div class="text-center p-3 bg-white rounded">
                            <div class="text-2xl font-bold text-blue-600">${transaction.container_count || 0}</div>
                            <div class="text-gray-600">Contenedores</div>
                        </div>
                        <div class="text-center p-3 bg-white rounded">
                            <div class="text-2xl font-bold text-green-600">${formatWeight(transaction.total_weight_kg)}</div>
                            <div class="text-gray-600">Peso Total</div>
                        </div>
                        <div class="text-center p-3 bg-white rounded">
                            <div class="text-2xl font-bold text-purple-600">${formatCurrency(transaction.total_value, transaction.currency_code)}</div>
                            <div class="text-gray-600">Valor Total</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderTechnicalDetails(transaction) {
            return `
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">🔧 Detalles Técnicos</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">URL Webservice:</span>
                            <div class="font-mono text-xs text-blue-600 break-all">${transaction.webservice_url}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Tiempo de Respuesta:</span>
                            <span class="font-medium">${transaction.response_time_ms ? transaction.response_time_ms + 'ms' : 'N/A'}</span>
                        </div>
                        ${transaction.sent_at ? `
                            <div>
                                <span class="text-gray-500">Enviado:</span>
                                <span class="font-medium">${formatDateTime(transaction.sent_at)}</span>
                            </div>
                        ` : ''}
                        ${transaction.response_at ? `
                            <div>
                                <span class="text-gray-500">Respuesta:</span>
                                <span class="font-medium">${formatDateTime(transaction.response_at)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        function renderAvailableActions(transaction) {
            const actions = [];
            
            if (transaction.status === 'error' && transaction.retry_count < transaction.max_retries) {
                actions.push(`
                    <button onclick="retryTransaction(${transaction.id})" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        🔄 Reintentar
                    </button>
                `);
            }
            
            if (transaction.request_xml) {
                actions.push(`
                    <button onclick="viewXML('request', ${transaction.id})" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        📄 Ver XML Enviado
                    </button>
                `);
            }
            
            if (transaction.response_xml) {
                actions.push(`
                    <button onclick="viewXML('response', ${transaction.id})" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        📄 Ver XML Respuesta
                    </button>
                `);
            }
            
            if (actions.length === 0) {
                return '';
            }
            
            return `
                <div class="bg-white p-4 rounded-lg border-t border-gray-200">
                    <h4 class="font-semibold text-gray-800 mb-3">⚡ Acciones Disponibles</h4>
                    <div class="flex flex-wrap gap-2">
                        ${actions.join('')}
                    </div>
                </div>
            `;
        }

        // Funciones auxiliares
        function getStatusBadge(status) {
            const badges = {
                'success': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Exitoso</span>',
                'error': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Error</span>',
                'pending': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⏳ Pendiente</span>',
                'retry': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">🔄 Reintentando</span>'
            };
            return badges[status] || badges['pending'];
        }

        function getWebserviceTypeName(type) {
            const names = {
                'micdta': 'MIC/DTA',
                'anticipada': 'Información Anticipada',
                'transbordo': 'Transbordos',
                'desconsolidados': 'Desconsolidados',
                'paraguay_customs': 'Aduana Paraguay'
            };
            return names[type] || type;
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('es-AR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatDateTime(dateString) {
            return new Date(dateString).toLocaleDateString('es-AR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatWeight(kg) {
            if (!kg) return '0 kg';
            if (kg >= 1000) {
                return (kg / 1000).toFixed(1) + ' t';
            }
            return kg.toFixed(0) + ' kg';
        }

        function formatCurrency(value, currency) {
            if (!value) return '$0';
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: currency || 'USD',
                minimumFractionDigits: 0
            }).format(value);
        }

        function showErrorModal(message) {
            const content = document.getElementById('modalContent');
            content.innerHTML = `
                <div class="text-center py-8">
                    <div class="text-red-500 text-6xl mb-4">❌</div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error</h3>
                    <p class="text-gray-600">${message}</p>
                </div>
            `;
        }

        function retryTransaction(transactionId) {
            if (confirm('¿Está seguro que desea reintentar esta transacción?')) {
                // Aquí iría la lógica de reintento
                alert('Funcionalidad de reintento en desarrollo');
            }
        }

        function closeModal() {
            document.getElementById('transactionModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Agregar estas funciones después de las funciones existentes en history.blade.php

        function viewXML(type, transactionId) {
            // Crear modal para mostrar XML
            const xmlModal = document.createElement('div');
            xmlModal.id = 'xmlModal';
            xmlModal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            xmlModal.innerHTML = `
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4 sticky top-0 bg-white pb-4 border-b">
                        <h3 class="text-lg font-medium text-gray-900">
                            📄 XML ${type === 'request' ? 'de Solicitud' : 'de Respuesta'}
                        </h3>
                        <button onclick="closeXMLModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div id="xmlContent" class="space-y-4">
                        <div class="text-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-sm text-gray-500 mt-2">Cargando XML...</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(xmlModal);
            
            // Cargar XML via AJAX
            fetch(`/company/webservices/transaction/${transactionId}/xml/${type}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                const content = document.getElementById('xmlContent');
                if (data.success) {
                    content.innerHTML = `
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-900">Transacción: ${data.transaction_id}</h4>
                                <button onclick="copyXMLToClipboard()" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                    📋 Copiar XML
                                </button>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <pre id="xmlCode" class="text-xs text-gray-800 overflow-x-auto whitespace-pre-wrap break-words">${escapeHtml(data.xml)}</pre>
                            </div>
                        </div>
                    `;
                } else {
                    content.innerHTML = `
                        <div class="text-center py-8">
                            <div class="text-red-500 text-4xl mb-4">❌</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Error</h3>
                            <p class="text-gray-600">${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const content = document.getElementById('xmlContent');
                content.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-red-500 text-4xl mb-4">❌</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error de Conexión</h3>
                        <p class="text-gray-600">No se pudo cargar el XML</p>
                    </div>
                `;
            });
        }

        function closeXMLModal() {
            const modal = document.getElementById('xmlModal');
            if (modal) {
                modal.remove();
            }
        }

        function copyXMLToClipboard() {
            const xmlCode = document.getElementById('xmlCode');
            if (xmlCode) {
                const textArea = document.createElement('textarea');
                textArea.value = xmlCode.textContent;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                // Mostrar confirmación
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '✅ Copiado';
                button.disabled = true;
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            }
        }

        function retryTransaction(transactionId) {
            if (!confirm('¿Está seguro que desea reintentar esta transacción?')) {
                return;
            }
            
            // Mostrar loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '⏳ Reintentando...';
            button.disabled = true;
            
            fetch(`/company/webservices/transaction/${transactionId}/retry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito
                    showNotification('✅ Transacción reintentada exitosamente', 'success');
                    
                    // Recargar la página después de 2 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('❌ Error al reintentar: ' + (data.message || 'Error desconocido'), 'error');
                    
                    // Restaurar botón
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Error de conexión al reintentar', 'error');
                
                // Restaurar botón
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function showNotification(message, type = 'info') {
            // Crear notificación toast
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 max-w-sm w-full p-4 rounded-lg shadow-lg transition-all transform translate-x-full`;
            
            const bgColor = {
                'success': 'bg-green-100 border-green-500 text-green-800',
                'error': 'bg-red-100 border-red-500 text-red-800',
                'warning': 'bg-yellow-100 border-yellow-500 text-yellow-800',
                'info': 'bg-blue-100 border-blue-500 text-blue-800'
            }[type] || 'bg-gray-100 border-gray-500 text-gray-800';
            
            notification.className += ` ${bgColor} border-l-4`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="text-sm font-medium">${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current hover:text-opacity-75">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animar entrada
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Función para refrescar los detalles después de una acción
        function refreshTransactionDetails(transactionId) {
            closeModal();
            setTimeout(() => {
                showTransactionDetails(transactionId);
            }, 500);
        }
    </script>
</x-app-layout>