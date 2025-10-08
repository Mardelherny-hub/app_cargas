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
                        <div class="border-2 rounded-lg p-5 {{ $xffmStatus === 'sent' ? 'border-green-400 bg-green-50' : 'border-blue-400 bg-blue-50' }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold">1</span>
                                        <h4 class="text-base font-semibold text-gray-900">XFFM</h4>
                                    </div>
                                    <p class="text-sm text-gray-700 font-medium">Carátula/Manifiesto Fluvial</p>
                                    <p class="text-xs text-gray-600 mt-1">Primer envío obligatorio. Registra el viaje en DNA.</p>
                                </div>
                                @if($xffmStatus === 'sent')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                                        ✓ ENVIADO
                                    </span>
                                @endif
                            </div>

                            @if($xffmTransaction)
                                <div class="mb-3 p-2 bg-white rounded text-xs space-y-1">
                                    <div><span class="text-gray-500">Enviado:</span> <span class="font-medium">{{ $xffmTransaction->created_at->format('d/m/Y H:i') }}</span></div>
                                    <div><span class="text-gray-500">Nro. Viaje DNA:</span> <span class="font-mono font-bold text-emerald-700">{{ $xffmTransaction->external_reference ?? 'N/A' }}</span></div>
                                </div>
                            @endif

                            <button 
                                onclick="enviarMetodo('XFFM')" 
                                {{ ($xffmStatus === 'sent' || !$validation['can_process']) ? 'disabled' : '' }}
                                class="w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                {{ $xffmStatus === 'sent' ? 'Ya Enviado' : 'Enviar XFFM' }}
                            </button>
                        </div>

                        {{-- 2. XFBL - Conocimientos/BLs --}}
                        <div class="border-2 rounded-lg p-5 {{ $xfblStatus === 'sent' ? 'border-green-400 bg-green-50' : ($xffmStatus === 'sent' ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-100') }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $xffmStatus === 'sent' ? 'bg-emerald-600' : 'bg-gray-400' }} text-white text-sm font-bold">2</span>
                                        <h4 class="text-base font-semibold text-gray-900">XFBL</h4>
                                    </div>
                                    <p class="text-sm text-gray-700 font-medium">Conocimientos/BLs</p>
                                    <p class="text-xs text-gray-600 mt-1">Declara los Bills of Lading ({{ $blCount }} BLs detectados)</p>
                                </div>
                                @if($xfblStatus === 'sent')
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

                            <button 
                                onclick="enviarMetodo('XFBL')" 
                                {{ ($xfblStatus === 'sent' || $xffmStatus !== 'sent') ? 'disabled' : '' }}
                                class="w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                {{ $xfblStatus === 'sent' ? 'Ya Enviado' : ($xffmStatus === 'sent' ? 'Enviar XFBL' : 'Requiere XFFM') }}
                            </button>
                        </div>

                        {{-- 3. XFBT - Contenedores --}}
                        <div class="border-2 rounded-lg p-5 {{ $xfbtStatus === 'sent' ? 'border-green-400 bg-green-50' : ($xffmStatus === 'sent' ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-100') }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $xffmStatus === 'sent' ? 'bg-emerald-600' : 'bg-gray-400' }} text-white text-sm font-bold">3</span>
                                        <h4 class="text-base font-semibold text-gray-900">XFBT</h4>
                                    </div>
                                    <p class="text-sm text-gray-700 font-medium">Hoja de Ruta/Contenedores</p>
                                    <p class="text-xs text-gray-600 mt-1">Declara los contenedores ({{ $containerCount }} detectados)</p>
                                </div>
                                @if($xfbtStatus === 'sent')
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

                            <button 
                                onclick="enviarMetodo('XFBT')" 
                                {{ ($xfbtStatus === 'sent' || $xffmStatus !== 'sent') ? 'disabled' : '' }}
                                class="w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                {{ $xfbtStatus === 'sent' ? 'Ya Enviado' : ($xffmStatus === 'sent' ? 'Enviar XFBT' : 'Requiere XFFM') }}
                            </button>
                        </div>

                        {{-- 4. XFCT - Cerrar Viaje --}}
                        <div class="border-2 rounded-lg p-5 {{ $xfctStatus === 'sent' ? 'border-green-400 bg-green-50' : (($xffmStatus === 'sent' && $xfblStatus === 'sent') ? 'border-purple-400 bg-purple-50' : 'border-gray-300 bg-gray-100') }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full {{ ($xffmStatus === 'sent' && $xfblStatus === 'sent') ? 'bg-purple-600' : 'bg-gray-400' }} text-white text-sm font-bold">4</span>
                                        <h4 class="text-base font-semibold text-gray-900">XFCT</h4>
                                    </div>
                                    <p class="text-sm text-gray-700 font-medium">Cerrar Viaje</p>
                                    <p class="text-xs text-gray-600 mt-1">Último paso. Cierra el nroViaje en DNA.</p>
                                </div>
                                @if($xfctStatus === 'sent')
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

                            <button 
                                onclick="enviarMetodo('XFCT')" 
                                {{ ($xfctStatus === 'sent' || $xffmStatus !== 'sent' || $xfblStatus !== 'sent') ? 'disabled' : '' }}
                                class="w-full px-4 py-2.5 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                {{ $xfctStatus === 'sent' ? 'Ya Cerrado' : (($xffmStatus === 'sent' && $xfblStatus === 'sent') ? 'Cerrar Viaje' : 'Requiere XFFM + XFBL') }}
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Historial de Transacciones --}}
            @if(isset($transactions) && $transactions->count() > 0)
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Historial de Envíos</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($transactions as $transaction)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $transaction->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                                {{ $transaction->request_data['tipo_mensaje'] ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($transaction->status === 'sent') bg-green-100 text-green-800
                                                @elseif($transaction->status === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($transaction->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700">
                                            {{ $transaction->external_reference ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $transaction->user->name ?? 'N/A' }}
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
    </script>
</x-app-layout>