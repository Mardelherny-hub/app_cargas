<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🇵🇾 Paraguay - Manifiesto Fluvial DNA
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Viaje: <span class="font-medium">{{ $voyage->voyage_number }}</span>
                    @if($voyage->leadVessel)
                        • Embarcación: <span class="font-medium">{{ $voyage->leadVessel->name }}</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center space-x-2">
                @if(Route::has('company.simple.anticipada.show'))
                    <a href="{{ route('company.simple.anticipada.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">
                        🇦🇷 Anticipada
                    </a>
                @endif

                @if(Route::has('company.simple.micdta.show'))
                    <a href="{{ route('company.simple.micdta.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">
                        🇦🇷 MIC/DTA
                    </a>
                @endif

                <span class="px-3 py-1.5 text-xs rounded border border-emerald-600 text-emerald-700 bg-emerald-50 font-medium">
                    🇵🇾 Paraguay DNA
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Validación del Voyage --}}
            @if(isset($validation))
                {{-- Errores --}}
                @if(count($validation['errors']) > 0)
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Errores de Validación</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc pl-5 space-y-1">
                                    @foreach($validation['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Advertencias --}}
                @if(count($validation['warnings']) > 0)
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <div class="flex">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Advertencias</h3>
                                <ul class="mt-2 text-sm text-yellow-700 list-disc pl-5 space-y-1">
                                    @foreach($validation['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Estado General XFFM --}}
                @if($xffmTransaction)
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-white">
                        <h3 class="text-lg font-semibold text-gray-900">📊 Estado del Manifiesto</h3>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            
                            {{-- Badge Estado --}}
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @if($xffmTransaction->status === 'sent')
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold bg-green-100 text-green-800">
                                            ✅ Enviado Exitosamente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800">
                                            ⏳ Pendiente
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- nroViaje --}}
                            @if($xffmTransaction->external_reference)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nº Viaje Paraguay</dt>
                                <dd class="mt-1 text-lg font-bold text-gray-900">{{ $xffmTransaction->external_reference }}</dd>
                            </div>
                            @endif

                            {{-- Fecha y Hora --}}
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de Envío</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">
                                    {{ $xffmTransaction->created_at->format('d/m/Y H:i') }}
                                </dd>
                            </div>

                            {{-- Usuario --}}
                            @if($xffmTransaction->user)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Enviado por</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">
                                    {{ $xffmTransaction->user->name }}
                                </dd>
                            </div>
                            @endif

                            {{-- Modo --}}
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Modo de Envío</dt>
                                <dd class="mt-1">
                                    @if($validation['bypass_enabled'] ?? false)
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-orange-100 text-orange-800">
                                            🔄 BYPASS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                            🔐 REAL
                                        </span>
                                    @endif
                                </dd>
                            </div>

                            {{-- Transaction ID --}}
                            @if($xffmTransaction->transaction_id)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transacción</dt>
                                <dd class="mt-1 text-xs font-mono text-gray-700">
                                    {{ $xffmTransaction->transaction_id }}
                                </dd>
                            </div>
                            @endif

                        </div>
                    </div>
                </div>
                @endif
            @endif

            {{-- Botones de Envío GDSF --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-emerald-50 to-white">
                    <h3 class="text-lg font-semibold text-gray-900">Métodos GDSF Disponibles</h3>
                    <p class="text-sm text-gray-600 mt-1">Envíe cada mensaje según el flujo obligatorio de DNA Paraguay</p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    {{-- 1. XFFM - Carátula/Manifiesto --}}
    <div class="border-2 rounded-lg p-5 {{ $xffmTransaction && $xffmTransaction->status === 'sent' ? 'border-green-400 bg-green-50' : 'border-blue-400 bg-blue-50' }}">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center space-x-2 mb-2">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold">1</span>
                    <h4 class="text-base font-semibold text-gray-900">XFFM</h4>
                </div>
                <p class="text-sm text-gray-700 font-medium">Carátula/Manifiesto Fluvial</p>
                <p class="text-xs text-gray-600 mt-1">Primer envío obligatorio. Retorna nroViaje.</p>
            </div>
            @if($xffmTransaction && $xffmTransaction->status === 'sent')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                    ✓ ENVIADO
                </span>
            @endif
        </div>

        @if($xffmTransaction && $xffmTransaction->external_reference)
            <div class="mb-3 p-2 bg-white rounded text-xs space-y-1">
                <div><span class="text-gray-500">nroViaje:</span> <span class="font-bold text-green-700">{{ $xffmTransaction->external_reference }}</span></div>
                <div><span class="text-gray-500">Enviado:</span> <span class="font-medium">{{ $xffmTransaction->created_at->format('d/m/Y H:i') }}</span></div>
            </div>
        @endif

        @if($xffmTransaction && $xffmTransaction->status === 'sent')
            <button disabled class="w-full px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                ✓ XFFM Ya Enviado
            </button>
        @else
            <button 
                onclick="enviarMetodo('XFFM')" 
                class="w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                Enviar XFFM (Carátula)
            </button>
        @endif
    </div>

    {{-- 2. XFBL - Conocimientos --}}
    @php
        $xffmSent = $xffmTransaction && $xffmTransaction->status === 'sent';
        $xfblSent = $xfblTransaction && $xfblTransaction->status === 'sent';
    @endphp
    
    <div class="border-2 rounded-lg p-5 {{ $xfblSent ? 'border-green-400 bg-green-50' : ($xffmSent ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-100') }}">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center space-x-2 mb-2">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $xffmSent ? 'bg-emerald-600' : 'bg-gray-400' }} text-white text-sm font-bold">2</span>
                    <h4 class="text-base font-semibold text-gray-900">XFBL</h4>
                </div>
                <p class="text-sm text-gray-700 font-medium">Conocimientos/BLs</p>
                <p class="text-xs text-gray-600 mt-1">Declara los Bills of Lading ({{ $blCount ?? 0 }} detectados)</p>
            </div>
            @if($xfblSent)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                    ✓ ENVIADO
                </span>
            @endif
        </div>

        @if($xfblTransaction)
            <div class="mb-3 p-2 bg-white rounded text-xs">
                <span class="text-gray-500">Enviado:</span> <span class="font-medium">{{ $xfblTransaction->created_at->format('d/m/Y H:i') }}</span>
            </div>
        @endif

        @if(!$xffmSent)
            <button disabled title="Debe enviar XFFM primero"
                class="w-full px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">
                Requiere XFFM Primero
            </button>
        @elseif($xfblSent)
            <button disabled class="w-full px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                ✓ XFBL Ya Enviado
            </button>
        @else
            <button 
                onclick="enviarMetodo('XFBL')" 
                class="w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
                Enviar XFBL (Conocimientos)
            </button>
        @endif
    </div>

    {{-- 3. XFBT - Contenedores --}}
    @php
        $xfbtSent = $xfbtTransaction && $xfbtTransaction->status === 'sent';
    @endphp
    
    <div class="border-2 rounded-lg p-5 {{ $xfbtSent ? 'border-green-400 bg-green-50' : ($xffmSent ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-100') }}">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center space-x-2 mb-2">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $xffmSent ? 'bg-emerald-600' : 'bg-gray-400' }} text-white text-sm font-bold">3</span>
                    <h4 class="text-base font-semibold text-gray-900">XFBT</h4>
                </div>
                <p class="text-sm text-gray-700 font-medium">Hoja de Ruta/Contenedores</p>
                <p class="text-xs text-gray-600 mt-1">Declara los contenedores ({{ $containerCount ?? 0 }} detectados)</p>
            </div>
            @if($xfbtSent)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                    ✓ ENVIADO
                </span>
            @endif
        </div>

        @if($xfbtTransaction)
            <div class="mb-3 p-2 bg-white rounded text-xs">
                <span class="text-gray-500">Enviado:</span> <span class="font-medium">{{ $xfbtTransaction->created_at->format('d/m/Y H:i') }}</span>
            </div>
        @endif

        @if(!$xffmSent)
            <button disabled title="Debe enviar XFFM primero"
                class="w-full px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">
                Requiere XFFM Primero
            </button>
        @elseif($xfbtSent)
            <button disabled class="w-full px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                ✓ XFBT Ya Enviado
            </button>
        @else
            <button 
                onclick="enviarMetodo('XFBT')" 
                class="w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
                Enviar XFBT (Contenedores)
            </button>
        @endif
    </div>

    {{-- 4. XFCT - Cerrar Viaje --}}
    @php
        $xfctSent = $xfctTransaction && $xfctTransaction->status === 'sent';
        
        // XFCT requiere XFFM y XFBL obligatorios
        // XFBT es opcional (solo si hay contenedores)
        $hasContainers = ($containerCount ?? 0) > 0;
        $xfbtRequired = $hasContainers;
        
        // Si hay contenedores, XFBT es obligatorio para cerrar
        // Si NO hay contenedores, solo se requiere XFFM + XFBL
        $canCloseVoyage = $xffmSent && $xfblSent && (!$xfbtRequired || $xfbtSent);
    @endphp
    
    <div class="border-2 rounded-lg p-5 {{ $xfctSent ? 'border-green-400 bg-green-50' : ($canCloseVoyage ? 'border-purple-400 bg-purple-50' : 'border-gray-300 bg-gray-100') }}">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center space-x-2 mb-2">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $canCloseVoyage ? 'bg-purple-600' : 'bg-gray-400' }} text-white text-sm font-bold">4</span>
                    <h4 class="text-base font-semibold text-gray-900">XFCT</h4>
                </div>
                <p class="text-sm text-gray-700 font-medium">Cerrar Viaje</p>
                <p class="text-xs text-gray-600 mt-1">Finaliza el nroViaje en DNA Paraguay</p>
            </div>
            @if($xfctSent)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                    ✓ CERRADO
                </span>
            @endif
        </div>

        @if($xfctTransaction)
            <div class="mb-3 p-2 bg-white rounded text-xs">
                <span class="text-gray-500">Cerrado:</span> <span class="font-medium">{{ $xfctTransaction->created_at->format('d/m/Y H:i') }}</span>
            </div>
        @endif

        @if(!$canCloseVoyage)
            <button disabled title="Debe enviar XFFM, XFBL y XFBT primero"
                class="w-full px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">
                Requiere XFFM + XFBL + XFBT
            </button>
        @elseif($xfctSent)
            <button disabled class="w-full px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                ✓ Viaje Ya Cerrado
            </button>
        @else
            <button 
                onclick="enviarMetodo('XFCT')" 
                class="w-full px-4 py-2.5 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-700 transition-colors">
                Cerrar Viaje (XFCT)
            </button>
        @endif
    </div>

</div>
                </div>
            </div>

            {{-- Historial de Transacciones --}}
@if($transactions->isNotEmpty())
<div class="bg-white shadow rounded-lg overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
        <h3 class="text-lg font-semibold text-gray-900">📜 Historial de Transacciones GDSF</h3>
        <p class="text-sm text-gray-600 mt-1">Registro completo de envíos a DNA Paraguay</p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha/Hora</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">nroViaje</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($transactions as $transaction)
                    @php
                        $tipoMensaje = $transaction->additional_metadata['tipo_mensaje'] ?? 'N/A';
                        $statusBadge = match($transaction->status) {
                            'sent', 'success' => 'bg-green-100 text-green-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'error' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                        $statusIcon = match($transaction->status) {
                            'sent', 'success' => '✅',
                            'pending' => '⏳',
                            'error' => '❌',
                            default => '⚪'
                        };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            {{ $transaction->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                {{ $tipoMensaje }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                {{ $statusIcon }} {{ strtoupper($transaction->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-700">
                            {{ $transaction->external_reference ?? '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $transaction->user->name ?? 'Sistema' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            <button 
                                onclick="verDetalles({{ $transaction->id }})"
                                class="text-blue-600 hover:text-blue-900 font-medium">
                                Ver Detalles
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

        </div>
    </div>

    {{-- JavaScript --}}
    <script>
        function enviarMetodo(metodo) {
            if (!confirm(`¿Confirma enviar ${metodo} a DNA Paraguay?`)) {
                return;
            }

            // Deshabilitar botón
            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Enviando...';

            fetch("{{ $send_route }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ method: metodo })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✓ ${metodo} enviado exitosamente`);
                    window.location.reload();
                } else {
                    alert(`✗ Error: ${data.error_message || 'Error desconocido'}`);
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                alert(`✗ Error de conexión: ${error.message}`);
                button.disabled = false;
                button.textContent = originalText;
            });
        }

    function verDetalles(transactionId) {
        // TODO: Implementar modal con detalles de la transacción
        alert('Ver detalles de transacción ID: ' + transactionId + '\n(Implementar modal próximamente)');
    }
    </script>

    {{-- Modal Ver Detalles de Transacción --}}
<div id="detallesModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        
        {{-- Header --}}
        <div class="flex items-center justify-between pb-3 border-b">
            <h3 class="text-lg font-semibold text-gray-900">📋 Detalles de la Transacción</h3>
            <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div id="detallesContent" class="mt-4">
            <div class="flex justify-center items-center py-8">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-600">Cargando detalles...</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end pt-4 border-t mt-4">
            <button onclick="cerrarModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>
</x-app-layout>