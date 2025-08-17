<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Estado de Transacción Aduana
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Seguimiento detallado de envío: {{ $transaction->transaction_id }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('company.manifests.customs.index') }}" 
                   class="text-gray-600 hover:text-gray-900 text-sm">
                    ← Volver a Customs
                </a>
                
                @if($transaction->status === 'error')
                <form method="POST" action="{{ route('company.manifests.customs.retry', $transaction->id) }}" class="inline">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                            onclick="return confirm('¿Confirma el reintento de esta transacción?')">
                        🔄 Reintentar
                    </button>
                </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Estado General -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🎯 Estado General</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Estado -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado Actual</dt>
                            <dd class="mt-1">
                                @switch($transaction->status)
                                    @case('success')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✅ Enviado Exitosamente
                                        </span>
                                        @break
                                    @case('pending')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            🔄 Procesando...
                                        </span>
                                        @break
                                    @case('error')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            ❌ Error de Envío
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                            ⏳ {{ ucfirst($transaction->status) }}
                                        </span>
                                @endswitch
                            </dd>
                        </div>

                        <!-- País y Webservice -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Webservice</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @switch($transaction->country)
                                    @case('AR')
                                        🇦🇷 Argentina (AFIP)
                                        @break
                                    @case('PY')
                                        🇵🇾 Paraguay (DNA)
                                        @break
                                    @default
                                        {{ $transaction->country }}
                                @endswitch
                                <div class="text-xs text-gray-500">{{ ucfirst($transaction->webservice_type) }}</div>
                            </dd>
                        </div>

                        <!-- Ambiente -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ambiente</dt>
                            <dd class="mt-1">
                                @if($transaction->environment === 'production')
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                        🏭 Producción
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        🧪 Testing
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <!-- Tiempo de Respuesta -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tiempo Respuesta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($transaction->response_time_ms)
                                    {{ number_format($transaction->response_time_ms) }} ms
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </dd>
                        </div>

                        <!-- Número de Confirmación -->
                        @if($transaction->confirmation_number)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número de Confirmación</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 px-2 py-1 rounded">
                                {{ $transaction->confirmation_number }}
                            </dd>
                        </div>
                        @endif

                        <!-- Intentos -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Intentos</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $transaction->retry_count ?? 0 }} / {{ $transaction->max_retries ?? 3 }}
                                @if(($transaction->retry_count ?? 0) > 0)
                                    <span class="text-yellow-600">(con reintentos)</span>
                                @endif
                            </dd>
                        </div>

                        <!-- Fechas -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Enviado</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($transaction->sent_at)
                                    {{ $transaction->sent_at->format('d/m/Y H:i:s') }}
                                @else
                                    <span class="text-gray-400">No enviado</span>
                                @endif
                            </dd>
                        </div>

                        <!-- Respuesta recibida -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Respuesta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($transaction->response_at)
                                    {{ $transaction->response_at->format('d/m/Y H:i:s') }}
                                @else
                                    <span class="text-gray-400">Pendiente</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Información del Viaje -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🚢 Información del Viaje</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número de Viaje</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $transaction->voyage->voyage_number ?? 'N/A' }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $transaction->voyage->origin_port->name ?? 'Puerto Origen' }} 
                                → 
                                {{ $transaction->voyage->destination_port->name ?? 'Puerto Destino' }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contenedores</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                📦 {{ $transaction->container_count }} containers
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Conocimientos</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                📄 {{ $transaction->bill_of_lading_count }} BL
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Peso Total</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($transaction->total_weight_kg)
                                    {{ number_format($transaction->total_weight_kg, 2) }} kg
                                @else
                                    <span class="text-gray-400">No especificado</span>
                                @endif
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Valor Total</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($transaction->total_value)
                                    {{ $transaction->currency_code }} {{ number_format($transaction->total_value, 2) }}
                                @else
                                    <span class="text-gray-400">No especificado</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if($transaction->status === 'error')
            <!-- Información del Error -->
            <div class="bg-red-50 border border-red-200 rounded-lg">
                <div class="px-6 py-4 border-b border-red-200">
                    <h3 class="text-lg font-medium text-red-900">❌ Detalles del Error</h3>
                </div>
                <div class="px-6 py-4">
                    @if($transaction->error_code)
                    <div class="mb-4">
                        <dt class="text-sm font-medium text-red-700">Código de Error</dt>
                        <dd class="mt-1 text-sm font-mono text-red-900 bg-red-100 px-2 py-1 rounded">
                            {{ $transaction->error_code }}
                        </dd>
                    </div>
                    @endif
                    
                    @if($transaction->error_message)
                    <div class="mb-4">
                        <dt class="text-sm font-medium text-red-700">Mensaje de Error</dt>
                        <dd class="mt-1 text-sm text-red-900 bg-red-100 p-3 rounded">
                            {{ $transaction->error_message }}
                        </dd>
                    </div>
                    @endif
                    
                    @if($transaction->error_details)
                    <div>
                        <dt class="text-sm font-medium text-red-700 mb-2">Detalles Técnicos</dt>
                        <dd class="text-xs font-mono text-red-800 bg-red-100 p-3 rounded overflow-auto max-h-40">
                            <pre>{{ json_encode($transaction->error_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </dd>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            @if($transaction->status === 'success' && ($transaction->success_data || $transaction->tracking_numbers))
            <!-- Datos de Éxito -->
            <div class="bg-green-50 border border-green-200 rounded-lg">
                <div class="px-6 py-4 border-b border-green-200">
                    <h3 class="text-lg font-medium text-green-900">✅ Información de Éxito</h3>
                </div>
                <div class="px-6 py-4">
                    @if($transaction->tracking_numbers)
                    <div class="mb-4">
                        <dt class="text-sm font-medium text-green-700">Números de Seguimiento</dt>
                        <dd class="mt-1 space-y-1">
                            @foreach($transaction->tracking_numbers as $trackingNumber)
                            <div class="text-sm font-mono text-green-900 bg-green-100 px-2 py-1 rounded inline-block mr-2">
                                {{ $trackingNumber }}
                            </div>
                            @endforeach
                        </dd>
                    </div>
                    @endif
                    
                    @if($transaction->success_data)
                    <div>
                        <dt class="text-sm font-medium text-green-700 mb-2">Datos de Respuesta</dt>
                        <dd class="text-xs font-mono text-green-800 bg-green-100 p-3 rounded overflow-auto max-h-40">
                            <pre>{{ json_encode($transaction->success_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </dd>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Datos Técnicos -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🔧 Datos Técnicos</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">ID Transacción</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 px-2 py-1 rounded">
                                {{ $transaction->transaction_id }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">URL Webservice</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 px-2 py-1 rounded break-all">
                                {{ $transaction->webservice_url ?? 'No especificada' }}
                            </dd>
                        </div>
                        
                        @if($transaction->soap_action)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">SOAP Action</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 px-2 py-1 rounded">
                                {{ $transaction->soap_action }}
                            </dd>
                        </div>
                        @endif
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">IP de Origen</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900">
                                {{ $transaction->ip_address ?? 'No registrada' }}
                            </dd>
                        </div>
                        
                        @if($transaction->certificate_used)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Certificado Usado</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $transaction->certificate_used }}
                            </dd>
                        </div>
                        @endif
                        
                        @if($transaction->external_reference)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Referencia Externa</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900 bg-gray-50 px-2 py-1 rounded">
                                {{ $transaction->external_reference }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Logs de la Transacción -->
            @if($transaction->logs && $transaction->logs->count() > 0)
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📝 Log de Eventos</h3>
                    <p class="text-sm text-gray-600 mt-1">Cronología detallada de la transacción</p>
                </div>
                <div class="px-6 py-4">
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($transaction->logs->sortBy('created_at') as $log)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            @switch($log->level)
                                                @case('error')
                                                @case('critical')
                                                    <span class="h-8 w-8 rounded-full bg-red-500 flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    @break
                                                @case('warning')
                                                    <span class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    @break
                                                @case('info')
                                                    <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="h-8 w-8 rounded-full bg-gray-500 flex items-center justify-center ring-8 ring-white">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                            @endswitch
                                        </div>
                                        <div class="min-w-0 flex-1 pt-1.5">
                                            <div class="flex justify-between">
                                                <p class="text-sm text-gray-900 font-medium">
                                                    {{ $log->message }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ $log->created_at->format('H:i:s') }}
                                                </p>
                                            </div>
                                            @if($log->category || $log->process_step)
                                            <div class="mt-1 space-x-2">
                                                @if($log->category)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $log->category }}
                                                </span>
                                                @endif
                                                @if($log->process_step)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $log->process_step }}
                                                </span>
                                                @endif
                                            </div>
                                            @endif
                                            
                                            @if($log->context_data)
                                            <details class="mt-2">
                                                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                                                    Ver detalles técnicos
                                                </summary>
                                                <div class="mt-1 text-xs font-mono text-gray-600 bg-gray-50 p-2 rounded overflow-auto max-h-32">
                                                    <pre>{{ json_encode($log->context_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </div>
                                            </details>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            <!-- Request/Response XML (solo para debugging) -->
            @if(auth()->user()->hasRole('company-admin') && ($transaction->request_xml || $transaction->response_xml))
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🔍 Datos XML (Solo Admin)</h3>
                    <p class="text-sm text-gray-600 mt-1">Request y Response para debugging técnico</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    @if($transaction->request_xml)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Request XML Enviado</h4>
                        <details>
                            <summary class="text-sm text-blue-600 cursor-pointer hover:text-blue-800">
                                Ver XML Request ({{ strlen($transaction->request_xml) }} caracteres)
                            </summary>
                            <div class="mt-2 text-xs font-mono text-gray-700 bg-gray-50 p-3 rounded overflow-auto max-h-64 border">
                                <pre>{{ $transaction->request_xml }}</pre>
                            </div>
                        </details>
                    </div>
                    @endif
                    
                    @if($transaction->response_xml)
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Response XML Recibido</h4>
                        <details>
                            <summary class="text-sm text-blue-600 cursor-pointer hover:text-blue-800">
                                Ver XML Response ({{ strlen($transaction->response_xml) }} caracteres)
                            </summary>
                            <div class="mt-2 text-xs font-mono text-gray-700 bg-gray-50 p-3 rounded overflow-auto max-h-64 border">
                                <pre>{{ $transaction->response_xml }}</pre>
                            </div>
                        </details>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Acciones Disponibles -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">⚡ Acciones Disponibles</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="flex flex-wrap gap-3">
                        @if($transaction->status === 'error')
                        <form method="POST" action="{{ route('company.manifests.customs.retry', $transaction->id) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                    onclick="return confirm('¿Confirma el reintento de esta transacción?')">
                                🔄 Reintentar Envío
                            </button>
                        </form>
                        @endif

                        @if($transaction->webservice_type === 'mane' && $transaction->status === 'success' && isset($transaction->additional_metadata['file_path']))
                        <a href="{{ route('company.mane.download', ['filename' => basename($transaction->additional_metadata['file_path'])]) }}" 
                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                        target="_blank">
                            📁 Descargar Archivo MANE
                        </a>
                        @endif
                        
                        <a href="{{ route('company.manifests.show', $transaction->voyage_id) }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            📋 Ver Manifiesto
                        </a>
                        
                        <a href="{{ route('company.manifests.customs.index') }}" 
                           class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            🏛️ Volver a Customs
                        </a>
                        
                        @if($transaction->status === 'success' && $transaction->confirmation_number)
                        <button type="button" 
                                onclick="copyToClipboard('{{ $transaction->confirmation_number }}')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            📋 Copiar Confirmación
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Información de Revisión Manual (si aplica) -->
            @if($transaction->requires_manual_review)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="px-6 py-4 border-b border-yellow-200">
                    <h3 class="text-lg font-medium text-yellow-900">⚠️ Requiere Revisión Manual</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-yellow-800 mb-4">
                        Esta transacción ha sido marcada para revisión manual por parte del equipo técnico.
                        Esto puede deberse a validaciones especiales, errores técnicos o políticas de seguridad.
                    </p>
                    
                    @if($transaction->reviewed_at)
                    <div class="bg-yellow-100 p-3 rounded">
                        <p class="text-sm text-yellow-800">
                            <strong>✅ Revisado:</strong> {{ $transaction->reviewed_at->format('d/m/Y H:i:s') }}
                            @if($transaction->reviewer_user_id)
                                por Usuario ID: {{ $transaction->reviewer_user_id }}
                            @endif
                        </p>
                    </div>
                    @else
                    <div class="bg-yellow-100 p-3 rounded">
                        <p class="text-sm text-yellow-800">
                            <strong>⏳ Estado:</strong> Pendiente de revisión por el equipo técnico
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Timeline de Estados (para tracking visual) -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🕐 Timeline de la Transacción</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="relative">
                        <!-- Línea base del timeline -->
                        <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-gray-200"></div>
                        
                        <div class="relative space-y-6">
                            <!-- Creación -->
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900">Transacción Creada</div>
                                    <div class="text-sm text-gray-500">{{ $transaction->created_at->format('d/m/Y H:i:s') }}</div>
                                    <div class="text-xs text-gray-400">ID: {{ $transaction->transaction_id }}</div>
                                </div>
                            </div>

                            <!-- Envío -->
                            @if($transaction->sent_at)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900">Enviado a {{ $transaction->country === 'AR' ? 'AFIP' : 'DNA' }}</div>
                                    <div class="text-sm text-gray-500">{{ $transaction->sent_at->format('d/m/Y H:i:s') }}</div>
                                    <div class="text-xs text-gray-400">Ambiente: {{ ucfirst($transaction->environment) }}</div>
                                </div>
                            </div>
                            @endif

                            <!-- Respuesta -->
                            @if($transaction->response_at)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    @if($transaction->status === 'success')
                                        <div class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center">
                                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-red-500 flex items-center justify-center">
                                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900">
                                        Respuesta {{ $transaction->status === 'success' ? 'Exitosa' : 'con Error' }}
                                    </div>
                                    <div class="text-sm text-gray-500">{{ $transaction->response_at->format('d/m/Y H:i:s') }}</div>
                                    @if($transaction->response_time_ms)
                                    <div class="text-xs text-gray-400">Tiempo: {{ number_format($transaction->response_time_ms) }}ms</div>
                                    @endif
                                </div>
                            </div>
                            @endif

                            <!-- Estado actual si está pendiente -->
                            @if($transaction->status === 'pending')
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center animate-pulse">
                                        <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-yellow-900">Procesando...</div>
                                    <div class="text-sm text-yellow-600">Esperando respuesta del webservice</div>
                                    <div class="text-xs text-gray-400">La transacción está en curso</div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar notificación de éxito
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-md shadow-lg z-50';
                notification.textContent = 'Número copiado al portapapeles';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }).catch(function() {
                // Fallback para navegadores que no soportan clipboard
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('Número copiado: ' + text);
            });
        }

        // Auto-refresh para transacciones pendientes
        @if($transaction->status === 'pending')
        let refreshInterval = setInterval(function() {
            // Recargar la página cada 30 segundos si está pendiente
            window.location.reload();
        }, 30000);

        // Detener auto-refresh si el usuario cambia de pestaña
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else if ('{{ $transaction->status }}' === 'pending') {
                refreshInterval = setInterval(function() {
                    window.location.reload();
                }, 30000);
            }
        });
        @endif
    </script>
    @endpush
</x-app-layout>