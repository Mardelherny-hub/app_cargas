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
        function showTransactionDetails(transactionId) {
            // Aquí podrías hacer una llamada AJAX para obtener los detalles
            const modal = document.getElementById('transactionModal');
            const content = document.getElementById('modalContent');
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="text-center py-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-sm text-gray-500 mt-2">Cargando detalles...</p>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
            
            // Simular carga de datos
            setTimeout(() => {
                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Información General</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">ID:</span>
                                    <span class="font-mono">${transactionId}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Estado:</span>
                                    <span class="text-green-600">Exitoso</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Logs de Envío</h4>
                            <div class="text-sm text-gray-600">
                                <p>• Solicitud enviada exitosamente</p>
                                <p>• Respuesta recibida de AFIP</p>
                                <p>• Transacción completada</p>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
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
    </script>
</x-app-layout>