<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Resultado de Consulta') }} - {{ $company->legal_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.query') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                    ← Nueva Consulta
                </a>
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    📋 Historial
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Información de la Transacción --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">📋 Información de la Transacción</h3>
                        <div class="flex items-center space-x-2">
                            <span class="w-3 h-3 rounded-full 
                                {{ $transaction->status === 'success' ? 'bg-green-500' : 
                                   ($transaction->status === 'pending' ? 'bg-yellow-500' : 'bg-red-500') }}">
                            </span>
                            <span class="text-lg font-semibold 
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
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        {{-- Información Básica --}}
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">ID de Transacción</label>
                                <p class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 p-2 rounded">
                                    {{ $transaction->transaction_id }}
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Tipo de Webservice</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ match($transaction->webservice_type) {
                                        'anticipada' => 'Información Anticipada',
                                        'micdta' => 'MIC/DTA',
                                        'desconsolidados' => 'Desconsolidados',
                                        'transbordos' => 'Transbordos',
                                        'paraguay' => 'DNA Paraguay',
                                        default => ucfirst($transaction->webservice_type)
                                    } }}
                                </p>
                            </div>

                            @if($transaction->external_reference)
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Referencia Externa</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $transaction->external_reference }}</p>
                            </div>
                            @endif
                        </div>

                        {{-- Información de Fechas y Usuario --}}
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Fecha de Envío</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $transaction->created_at->format('d/m/Y H:i:s') }}
                                </p>
                            </div>

                            @if($transaction->processed_at)
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Fecha de Procesamiento</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $transaction->processed_at->format('d/m/Y H:i:s') }}
                                </p>
                            </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Usuario</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $transaction->user->name ?? 'Sistema' }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Entorno</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $transaction->environment === 'production' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $transaction->environment === 'production' ? 'Producción' : 'Testing' }}
                                </span>
                            </div>
                        </div>

                    </div>

                    {{-- Mensaje de Error si existe --}}
                    @if($transaction->status === 'error' && $transaction->error_message)
                    <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-red-800">Error en el Procesamiento</h4>
                                <p class="mt-1 text-sm text-red-700">{{ $transaction->error_message }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Información Adicional si existe --}}
                    @if($transaction->response_data)
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-800 mb-2">Respuesta de la Aduana</h4>
                        <div class="text-sm text-blue-700">
                            @if(is_array($transaction->response_data))
                                @foreach($transaction->response_data as $key => $value)
                                    @if(!is_array($value))
                                    <p><strong>{{ ucfirst($key) }}:</strong> {{ $value }}</p>
                                    @endif
                                @endforeach
                            @else
                                <p>{{ $transaction->response_data }}</p>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            {{-- Logs de la Transacción --}}
            @if($logs->count() > 0)
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">📄 Logs de Procesamiento</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Registro detallado del procesamiento de la transacción
                    </p>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($logs->take(10) as $log)
                        <div class="flex items-start space-x-3 p-3 
                            {{ $log->level === 'error' ? 'bg-red-50' : 
                               ($log->level === 'warning' ? 'bg-yellow-50' : 'bg-gray-50') }} 
                            rounded-lg">
                            
                            {{-- Icono según el nivel --}}
                            <div class="flex-shrink-0 mt-1">
                                @if($log->level === 'error')
                                    <svg class="h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                @elseif($log->level === 'warning')
                                    <svg class="h-4 w-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                @else
                                    <svg class="h-4 w-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </div>

                            {{-- Contenido del log --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium 
                                        {{ $log->level === 'error' ? 'text-red-800' : 
                                           ($log->level === 'warning' ? 'text-yellow-800' : 'text-gray-800') }}">
                                        {{ $log->message }}
                                    </p>
                                    <span class="text-xs text-gray-500">
                                        {{ $log->created_at->format('H:i:s') }}
                                    </span>
                                </div>
                                
                                {{-- Contexto adicional si existe --}}
                                @if($log->context && is_array($log->context))
                                <div class="mt-1 text-xs text-gray-600">
                                    @foreach($log->context as $key => $value)
                                        @if(!is_array($value) && !is_object($value))
                                        <span class="mr-3"><strong>{{ $key }}:</strong> {{ $value }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach

                        @if($logs->count() > 10)
                        <div class="text-center pt-4">
                            <p class="text-sm text-gray-500">
                                Mostrando los últimos 10 logs de {{ $logs->count() }} totales
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Acciones Disponibles --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ Acciones Disponibles</h3>
                    
                    <div class="flex flex-wrap gap-3">
                        {{-- Nueva Consulta --}}
                        <a href="{{ route('company.webservices.query') }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            🔍 Nueva Consulta
                        </a>

                        {{-- Ver Historial --}}
                        <a href="{{ route('company.webservices.history') }}" 
                           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            📋 Ver Historial
                        </a>

                        {{-- Reintentar si hay error --}}
                        @if($transaction->status === 'error')
                        <button onclick="retryTransaction()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            🔄 Reintentar Envío
                        </button>
                        @endif

                        {{-- Enviar Nuevo Manifiesto --}}
                        <a href="{{ route('company.manifests.import.index') }}" 
                           class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            📤 Enviar Nuevo Manifiesto
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- JavaScript --}}
    <script>
        function retryTransaction() {
            if (confirm('¿Está seguro que desea reintentar el envío de esta transacción?')) {
                // Aquí iría la lógica de reintento
                alert('Funcionalidad de reintento en desarrollo.\n\nPor ahora puede enviar un nuevo manifiesto desde el módulo de importación.');
            }
        }

        // Auto-refresh para transacciones pendientes
        @if($transaction->status === 'pending')
        setTimeout(function() {
            if (confirm('Esta transacción sigue pendiente. ¿Desea actualizar la página para verificar el estado?')) {
                location.reload();
            }
        }, 30000); // 30 segundos
        @endif
    </script>
</x-app-layout>