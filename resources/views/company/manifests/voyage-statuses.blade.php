<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Estados de Webservices - Voyage {{ $voyage->voyage_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Estados detallados de todos los envíos a aduana
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('company.manifests.customs.index') }}" 
                   class="text-gray-600 hover:text-gray-900 text-sm">
                    ← Volver a Customs
                </a>
                
                <a href="{{ route('company.manifests.show', $voyage->id) }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                    📋 Ver Manifiesto
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Información del Viaje -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🚢 Información del Viaje</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número de Viaje</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $voyage->voyage_number }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->company->name }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->originPort->name ?? 'Puerto Origen' }} 
                                → 
                                {{ $voyage->destinationPort->name ?? 'Puerto Destino' }}
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado del Viaje</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $voyage->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                       ($voyage->status === 'in_transit' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst($voyage->status) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Estados de Webservices -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🏛️ Estados de Webservices por País</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Resumen de envíos a las aduanas de Argentina y Paraguay
                    </p>
                </div>
                
                @if(empty($statusesData))
                <div class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin envíos registrados</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Este voyage aún no tiene envíos a aduana registrados.
                        </p>
                    </div>
                </div>
                @else
                <div class="px-6 py-4">
                    <div class="space-y-6">
                        @php
                            $argentineStatuses = collect($statusesData)->filter(function($data, $key) {
                                return str_starts_with($key, 'AR_');
                            });
                            $paraguayStatuses = collect($statusesData)->filter(function($data, $key) {
                                return str_starts_with($key, 'PY_');
                            });
                        @endphp

                        <!-- Argentina -->
                        @if($argentineStatuses->isNotEmpty())
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                                🇦🇷 Argentina
                            </h4>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($argentineStatuses as $key => $data)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <h5 class="text-sm font-medium text-gray-900">
                                            {{ $data['webservice_name'] }}
                                        </h5>
                                        @if($data['status_record'])
                                            @php
                                                $status = $data['status_record']->status;
                                                $statusColor = match($status) {
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'sent' => 'bg-blue-100 text-blue-800',
                                                    'sending' => 'bg-yellow-100 text-yellow-800',
                                                    'error' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Legacy
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                                        @if($data['status_record'])
                                            @if($data['status_record']->last_sent_at)
                                            <div>
                                                📅 Último envío: {{ $data['status_record']->last_sent_at->format('d/m/Y H:i') }}
                                            </div>
                                            @endif
                                            
                                            @if($data['status_record']->confirmation_number)
                                            <div>
                                                📋 Confirmación: {{ $data['status_record']->confirmation_number }}
                                            </div>
                                            @endif
                                            
                                            @if($data['status_record']->retry_count > 0)
                                            <div>
                                                🔄 Reintentos: {{ $data['status_record']->retry_count }}/{{ $data['status_record']->max_retries }}
                                            </div>
                                            @endif
                                        @endif
                                        
                                        <div>
                                            📊 Transacciones: {{ $data['transactions']->count() }}
                                        </div>
                                    </div>
                                    
                                    @if($data['transactions']->isNotEmpty())
                                    <div class="mt-3">
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-blue-600 hover:text-blue-800">
                                                Ver transacciones ({{ $data['transactions']->count() }})
                                            </summary>
                                            <div class="mt-2 space-y-2">
                                                @foreach($data['transactions']->take(3) as $transaction)
                                                <div class="bg-gray-50 p-2 rounded border-l-4 
                                                    {{ $transaction->status === 'success' ? 'border-green-400' : 
                                                       ($transaction->status === 'error' ? 'border-red-400' : 'border-gray-400') }}">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <div class="font-medium">
                                                                {{ $transaction->transaction_id }}
                                                            </div>
                                                            <div class="text-gray-500">
                                                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                                                            </div>
                                                        </div>
                                                        <a href="{{ route('company.manifests.customs.status', $transaction->id) }}" 
                                                           class="text-blue-600 hover:text-blue-800">
                                                            Ver →
                                                        </a>
                                                    </div>
                                                </div>
                                                @endforeach
                                                
                                                @if($data['transactions']->count() > 3)
                                                <div class="text-center text-gray-500">
                                                    ... y {{ $data['transactions']->count() - 3 }} más
                                                </div>
                                                @endif
                                            </div>
                                        </details>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Paraguay -->
                        @if($paraguayStatuses->isNotEmpty())
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                                🇵🇾 Paraguay
                            </h4>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($paraguayStatuses as $key => $data)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <h5 class="text-sm font-medium text-gray-900">
                                            {{ $data['webservice_name'] }}
                                        </h5>
                                        @if($data['status_record'])
                                            @php
                                                $status = $data['status_record']->status;
                                                $statusColor = match($status) {
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'sent' => 'bg-blue-100 text-blue-800',
                                                    'sending' => 'bg-yellow-100 text-yellow-800',
                                                    'error' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Legacy
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                                        @if($data['status_record'])
                                            @if($data['status_record']->last_sent_at)
                                            <div>
                                                📅 Último envío: {{ $data['status_record']->last_sent_at->format('d/m/Y H:i') }}
                                            </div>
                                            @endif
                                            
                                            @if($data['status_record']->confirmation_number)
                                            <div>
                                                📋 Confirmación: {{ $data['status_record']->confirmation_number }}
                                            </div>
                                            @endif
                                        @endif
                                        
                                        <div>
                                            📊 Transacciones: {{ $data['transactions']->count() }}
                                        </div>
                                    </div>
                                    
                                    @if($data['transactions']->isNotEmpty())
                                    <div class="mt-3">
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-blue-600 hover:text-blue-800">
                                                Ver transacciones ({{ $data['transactions']->count() }})
                                            </summary>
                                            <div class="mt-2 space-y-2">
                                                @foreach($data['transactions']->take(3) as $transaction)
                                                <div class="bg-gray-50 p-2 rounded border-l-4 
                                                    {{ $transaction->status === 'success' ? 'border-green-400' : 
                                                       ($transaction->status === 'error' ? 'border-red-400' : 'border-gray-400') }}">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <div class="font-medium">
                                                                {{ $transaction->transaction_id }}
                                                            </div>
                                                            <div class="text-gray-500">
                                                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                                                            </div>
                                                        </div>
                                                        <a href="{{ route('company.manifests.customs.status', $transaction->id) }}" 
                                                           class="text-blue-600 hover:text-blue-800">
                                                            Ver →
                                                        </a>
                                                    </div>
                                                </div>
                                                @endforeach
                                                
                                                @if($data['transactions']->count() > 3)
                                                <div class="text-center text-gray-500">
                                                    ... y {{ $data['transactions']->count() - 3 }} más
                                                </div>
                                                @endif
                                            </div>
                                        </details>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>