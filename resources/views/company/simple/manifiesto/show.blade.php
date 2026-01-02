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
                                <div class="flex gap-2">
                                    <button disabled class="flex-1 px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                                        ✓ XFFM Ya Enviado
                                    </button>
                                    <button 
                                        onclick="rectificarMetodo('XFFM')" 
                                        class="px-4 py-2.5 bg-amber-600 text-white text-sm font-semibold rounded-lg hover:bg-amber-700 transition-colors"
                                        title="Reenviar con datos corregidos">
                                        🔄 Rectificar
                                    </button>
                                </div>
                                {{-- Enlaces descarga XML para soporte DNA --}}
                                <div class="mt-3 pt-3 border-t border-green-200 flex gap-2 text-xs">
                                    @if($xffmTransaction->request_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xffmTransaction->id, 'type' => 'request']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                            📤 Request XML
                                        </a>
                                    @endif
                                    @if($xffmTransaction->response_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xffmTransaction->id, 'type' => 'response']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors">
                                            📥 Response XML
                                        </a>
                                    @endif
                                </div>
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
                            
                            {{-- Zona de adjuntos (solo cuando XFFM enviado y XFBL NO enviado) --}}
                            @if($xffmSent && !$xfblSent)
                                <div class="mb-4 p-3 bg-white rounded border border-gray-300">
                                    <h5 class="text-sm font-semibold text-gray-700 mb-2">📎 Documentos Adjuntos</h5>
                                    <p class="text-xs text-gray-600 mb-3">Facturas, packing lists u otros documentos (PDF)</p>
                                    
                                    {{-- Formulario Upload --}}
                                    <form id="uploadAttachmentsForm" enctype="multipart/form-data" class="mb-3">
                                        @csrf
                                        <div class="flex gap-2">
                                            <input 
                                                type="file" 
                                                name="files[]" 
                                                id="attachmentFiles"
                                                accept=".pdf"
                                                multiple
                                                class="flex-1 text-sm border border-gray-300 rounded px-2 py-1"
                                            >
                                            <button 
                                                type="button"
                                                onclick="uploadAttachments()"
                                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                                Subir
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Solo PDF, máx 5MB c/u</p>
                                    </form>
                                    
                                    {{-- Lista archivos --}}
                                    <div id="attachmentsList" class="space-y-1">
                                        <p class="text-xs text-gray-500 italic">Cargando...</p>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Botones de acción --}}
                            @if(!$xffmSent)
                                <button disabled title="Debe enviar XFFM primero"
                                    class="w-full px-4 py-2.5 bg-gray-300 text-gray-500 text-sm font-semibold rounded-lg cursor-not-allowed">
                                    Requiere XFFM Primero
                                </button>
                            @elseif($xfblSent)
                                <div class="flex gap-2">
                                    <button disabled class="flex-1 px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                                        ✓ XFBL Ya Enviado
                                    </button>
                                    <button 
                                        onclick="rectificarMetodo('XFBL')" 
                                        class="px-4 py-2.5 bg-amber-600 text-white text-sm font-semibold rounded-lg hover:bg-amber-700 transition-colors"
                                        title="Reenviar con datos corregidos">
                                        🔄 Rectificar
                                    </button>
                                </div>
                                {{-- Enlaces descarga XML para soporte DNA --}}
                                <div class="mt-3 pt-3 border-t border-green-200 flex gap-2 text-xs">
                                    @if($xfblTransaction->request_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfblTransaction->id, 'type' => 'request']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                            📤 Request XML
                                        </a>
                                    @endif
                                    @if($xfblTransaction->response_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfblTransaction->id, 'type' => 'response']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors">
                                            📥 Response XML
                                        </a>
                                    @endif
                                </div>
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
                            $xfbtStatus = $voyage->webserviceStatuses->where('webservice_type', 'XFBT')->first();
                            $xfbtSkipped = $xfbtStatus && ($xfbtStatus->additional_data['skipped'] ?? false);
                        @endphp

                        <div class="border-2 rounded-lg p-5 {{ $xfbtSent ? 'border-green-400 bg-green-50' : ($xfbtSkipped ? 'border-gray-400 bg-gray-100' : ($xffmSent ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-100')) }}">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full {{ $xfbtSent ? 'bg-emerald-600' : 'bg-gray-400' }} text-white text-sm font-bold">3</span>
                                        <h4 class="text-base font-semibold text-gray-900">XFBT</h4>
                                    </div>
                                    <p class="text-sm text-gray-700 font-medium">Hoja de Ruta/Contenedores</p>
                                    <p class="text-xs text-gray-600 mt-1">Declara los contenedores ({{ $containerCount ?? 0 }} detectados)</p>
                                </div>
                                @if($xfbtSent)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                                        ✓ ENVIADO
                                    </span>
                                @elseif($xfbtSkipped)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-500 text-white">
                                        ⊘ OMITIDO
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
                            @elseif($xfbtSkipped)
                                <div class="w-full px-4 py-2.5 bg-gray-200 text-gray-700 text-sm rounded-lg text-center">
                                    <div class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="font-semibold">Viaje sin contenedores - XFBT no requerido</span>
                                    </div>
                                </div>
                            @elseif($xfbtSent)
                                <div class="flex gap-2">
                                    <button disabled class="flex-1 px-4 py-2.5 bg-green-100 text-green-800 text-sm font-semibold rounded-lg cursor-not-allowed">
                                        ✓ XFBT Ya Enviado
                                    </button>
                                    <button 
                                        onclick="rectificarMetodo('XFBT')" 
                                        class="px-4 py-2.5 bg-amber-600 text-white text-sm font-semibold rounded-lg hover:bg-amber-700 transition-colors"
                                        title="Reenviar con datos corregidos">
                                        🔄 Rectificar
                                    </button>
                                </div>
                                {{-- Enlaces descarga XML para soporte DNA --}}
                                <div class="mt-3 pt-3 border-t border-green-200 flex gap-2 text-xs">
                                    @if($xfbtTransaction->request_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfbtTransaction->id, 'type' => 'request']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                            📤 Request XML
                                        </a>
                                    @endif
                                    @if($xfbtTransaction->response_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfbtTransaction->id, 'type' => 'response']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors">
                                            📥 Response XML
                                        </a>
                                    @endif
                                </div>
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
                                {{-- Enlaces descarga XML para soporte DNA --}}
                                <div class="mt-3 pt-3 border-t border-green-200 flex gap-2 text-xs">
                                    @if($xfctTransaction->request_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfctTransaction->id, 'type' => 'request']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                            📤 Request XML
                                        </a>
                                    @endif
                                    @if($xfctTransaction->response_xml)
                                        <a href="{{ route('company.webservices.transaction.xml', ['id' => $xfctTransaction->id, 'type' => 'response']) }}" 
                                           target="_blank"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors">
                                            📥 Response XML
                                        </a>
                                    @endif
                                </div>
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

            @php
                $transactions = \App\Models\WebserviceTransaction::where('voyage_id', $voyage->id)
                    ->where('country', 'PY')
                    ->whereNotNull('request_xml')
                    ->get()
                    ->groupBy(fn($t) => $t->additional_metadata['tipo_mensaje'] ?? 'UNKNOWN');
            @endphp

            @if($transactions->count() > 0)
            <div class="bg-white shadow rounded-lg p-6 mt-6">
                <h3 class="text-lg font-bold mb-4">📄 XMLs Generados</h3>
                
                @foreach(['XFFM', 'XFBL', 'XFBT', 'XFCT'] as $type)
                    @if($transactions->has($type))
                        <div class="mb-4 p-4 bg-blue-50 rounded">
                            <h4 class="font-bold">{{ $type }}</h4>
                            <pre class="text-xs bg-white p-2 max-h-32 overflow-auto">{{ $transactions[$type]->first()->request_xml }}</pre>
                            <form action="{{ route('company.simple.manifiesto.download-xml', ['voyage' => $voyage, 'type' => $type]) }}" method="POST" class="mt-2">
                                @csrf
                                <button class="bg-blue-600 text-white px-4 py-2 rounded">Descargar</button>
                            </form>
                        </div>
                    @endif
                @endforeach
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
                    // Verificar si fue aceptado o rechazado por DNA
                    if (data.accepted === false) {
                        showDnaResponseModal(metodo, data, 'rejected');
                    } else if (data.nroViaje) {
                        showDnaResponseModal(metodo, data, 'success');
                        setTimeout(() => window.location.reload(), 3000);
                    } else {
                        showDnaResponseModal(metodo, data, 'warning');
                    }
                } else {
                    showDnaResponseModal(metodo, data, 'error');
                }
                button.disabled = false;
                button.textContent = originalText;
            })
            .catch(error => {
                alert(`✗ Error de conexión: ${error.message}`);
                button.disabled = false;
                button.textContent = originalText;
            });
        }

    /**
     * Mostrar modal con respuesta de DNA Paraguay
     */
    function showDnaResponseModal(metodo, data, type) {
        const modal = document.getElementById('dnaResponseModal');
        const title = document.getElementById('dnaModalTitle');
        const content = document.getElementById('dnaModalContent');
        const icon = document.getElementById('dnaModalIcon');
        
        // Configurar según tipo
        const configs = {
            success: {
                iconBg: 'bg-green-100',
                iconColor: 'text-green-600',
                iconSvg: '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
                titleText: `✅ ${metodo} - Aceptado`
            },
            rejected: {
                iconBg: 'bg-red-100',
                iconColor: 'text-red-600',
                iconSvg: '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
                titleText: `❌ ${metodo} - Rechazado por DNA`
            },
            warning: {
                iconBg: 'bg-yellow-100',
                iconColor: 'text-yellow-600',
                iconSvg: '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
                titleText: `⚠️ ${metodo} - Enviado (sin nroViaje)`
            },
            error: {
                iconBg: 'bg-red-100',
                iconColor: 'text-red-600',
                iconSvg: '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                titleText: `❌ ${metodo} - Error`
            }
        };
        
        const config = configs[type] || configs.error;
        
        icon.className = `mx-auto flex items-center justify-center h-12 w-12 rounded-full ${config.iconBg}`;
        icon.innerHTML = `<span class="${config.iconColor}">${config.iconSvg}</span>`;
        title.textContent = config.titleText;
        
        // Construir contenido
        let html = '<div class="space-y-3 text-left">';
        
        if (data.nroViaje) {
            html += `<div class="p-3 bg-green-50 rounded-lg"><span class="font-semibold text-green-800">Nro. Viaje:</span> <span class="font-mono text-green-900">${data.nroViaje}</span></div>`;
        }
        
        if (data.transaction_id) {
            html += `<div class="text-sm"><span class="font-semibold text-gray-600">ID Transacción:</span> <span class="font-mono">${data.transaction_id}</span></div>`;
        }
        
        if (data.dna_response) {
            const dna = data.dna_response;
            html += '<div class="mt-4 p-4 bg-gray-50 rounded-lg border">';
            html += '<h4 class="font-semibold text-gray-700 mb-2">Respuesta DNA Paraguay:</h4>';
            
            if (dna.status_code) {
                const statusClass = dna.status_code === 'REJECTED' ? 'text-red-600 bg-red-100' : 'text-green-600 bg-green-100';
                html += `<div class="mb-2"><span class="font-medium">Estado:</span> <span class="px-2 py-1 rounded ${statusClass} font-semibold">${dna.status_code}</span></div>`;
            }
            
            if (dna.reason_code) {
                html += `<div class="mb-2"><span class="font-medium">Código:</span> <span class="font-mono">${dna.reason_code}</span></div>`;
            }
            
            if (dna.reason) {
                html += `<div class="p-3 bg-white rounded border-l-4 ${type === 'rejected' ? 'border-red-500' : 'border-blue-500'}"><span class="font-medium block mb-1">Mensaje:</span><span class="text-sm">${dna.reason}</span></div>`;
            }
            
            html += '</div>';
        }
        
        if (data.error_message) {
            html += `<div class="p-3 bg-red-50 rounded-lg border border-red-200"><span class="font-semibold text-red-800">Error:</span> <span class="text-red-700">${data.error_message}</span></div>`;
        }
        
        if (data.message) {
            html += `<div class="text-sm text-gray-600 mt-3">${data.message}</div>`;
        }
        
        if (type === 'success') {
            html += '<div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-700">La página se recargará automáticamente en 3 segundos...</div>';
        }
        
        html += '</div>';
        
        content.innerHTML = html;
        modal.classList.remove('hidden');
    }
    
    function closeDnaModal() {
        document.getElementById('dnaResponseModal').classList.add('hidden');
    }

    function verDetalles(transactionId) {
        const modal = document.getElementById('detallesModal');
        const content = document.getElementById('detallesContent');

        content.innerHTML = `
            <div class="flex justify-center items-center py-8">
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-600">Cargando detalles...</span>
            </div>
        `;

        modal.classList.remove('hidden');

        fetch(`/company/webservices/transaction/${transactionId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDetallesTransaccion(data.transaction, data.response, transactionId);
            } else {
                renderDetallesError(data.message || 'Error al cargar los detalles');
            }
        })
        .catch(() => {
            renderDetallesError('Error de conexión al cargar los detalles');
        });
    }
    
    function cerrarModal() {
        document.getElementById('detallesModal').classList.add('hidden');
    }

    function renderDetallesError(message) {
        const content = document.getElementById('detallesContent');
        content.innerHTML = `
            <div class="text-center py-8">
                <div class="text-red-500 text-5xl mb-3">❌</div>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">No se pudieron cargar los detalles</h4>
                <p class="text-gray-600 text-sm">${message}</p>
            </div>
        `;
    }

    function renderDetallesTransaccion(transaction, response, transactionId) {
        const content = document.getElementById('detallesContent');

        content.innerHTML = `
            <div class="space-y-5">
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900">📋 Información General</h4>
                        ${getStatusBadge(transaction.status)}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">ID Transacción:</span>
                            <span class="font-mono text-blue-600">${transaction.transaction_id || 'N/A'}</span>
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
                            <span class="text-gray-500">Referencia:</span>
                            <span class="font-mono">${transaction.external_reference || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Usuario:</span>
                            <span class="font-medium">${transaction.user_name || 'Sistema'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Fecha:</span>
                            <span class="font-medium">${formatDateTime(transaction.created_at)}</span>
                        </div>
                    </div>
                </div>

                ${renderResponseSection(transaction, response)}

                <div class="bg-white p-4 rounded-lg border">
                    <h4 class="font-semibold text-gray-900 mb-3">🔧 Detalles Técnicos</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">URL Webservice:</span>
                            <div class="font-mono text-xs text-blue-600 break-all">${transaction.webservice_url || 'N/A'}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Tiempo de Respuesta:</span>
                            <span class="font-medium">${transaction.response_time_ms ? transaction.response_time_ms + ' ms' : 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Enviado:</span>
                            <span class="font-medium">${formatDateTime(transaction.sent_at)}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Respuesta:</span>
                            <span class="font-medium">${formatDateTime(transaction.response_at)}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">XML Solicitud:</span>
                            <span class="font-medium">${transaction.request_xml ? 'Disponible' : 'No disponible'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">XML Respuesta:</span>
                            <span class="font-medium">${transaction.response_xml ? 'Disponible' : 'No disponible'}</span>
                        </div>
                    </div>
                </div>

                ${renderXmlLinks(transaction, transactionId)}
            </div>
        `;
    }

    function renderResponseSection(transaction, response) {
        if (transaction.status === 'pending' || transaction.status === 'sent') {
            return `
                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <h4 class="font-semibold text-yellow-800 mb-2">⏳ Sin Respuesta</h4>
                    <p class="text-yellow-700 text-sm">La transacción está pendiente o aún no recibió respuesta.</p>
                </div>
            `;
        }

        if (transaction.status === 'success') {
            return `
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <h4 class="font-semibold text-green-800 mb-3">✅ Respuesta Exitosa</h4>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-gray-600">Confirmación:</span>
                            <span class="font-mono text-green-700 font-medium">${response?.confirmation_number || transaction.confirmation_number || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Referencia:</span>
                            <span class="font-mono text-blue-700 font-medium">${response?.reference_number || transaction.external_reference || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                <h4 class="font-semibold text-red-800 mb-3">❌ Error en Respuesta</h4>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-gray-600">Código:</span>
                        <span class="font-mono text-red-700 font-medium">${transaction.error_code || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Mensaje:</span>
                        <span class="text-red-700">${transaction.error_message || 'Error desconocido'}</span>
                    </div>
                </div>
            </div>
        `;
    }

    function renderXmlLinks(transaction, transactionId) {
        if (!transaction.request_xml && !transaction.response_xml) {
            return '';
        }

        return `
            <div class="pt-2 flex gap-3">
                ${transaction.request_xml ? `
                    <a href="/company/webservices/transaction/${transactionId}/xml/request" 
                       target="_blank"
                       class="flex-1 text-center px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                        📤 Descargar Request XML
                    </a>
                ` : ''}
                ${transaction.response_xml ? `
                    <a href="/company/webservices/transaction/${transactionId}/xml/response" 
                       target="_blank"
                       class="flex-1 text-center px-3 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors text-sm font-medium">
                        📥 Descargar Response XML
                    </a>
                ` : ''}
            </div>
        `;
    }

    function getStatusBadge(status) {
        const badges = {
            success: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Exitoso</span>',
            sent: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">📤 Enviado</span>',
            error: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Error</span>',
            pending: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">⏳ Pendiente</span>'
        };
        return badges[status] || badges.pending;
    }

    function getWebserviceTypeName(type) {
        const names = {
            micdta: 'MIC/DTA',
            anticipada: 'Información Anticipada',
            transbordo: 'Transbordos',
            desconsolidados: 'Desconsolidados',
            paraguay_customs: 'Aduana Paraguay'
        };
        return names[type] || type || 'N/A';
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    </script>

    {{-- Modal Ve</script>

{{-- Modal Respuesta DNA --}}
<div id="dnaResponseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl shadow-lg rounded-lg bg-white">
        {{-- Header --}}
        <div class="flex items-center justify-between pb-3 border-b">
            <div class="flex items-center space-x-3">
                <div id="dnaModalIcon" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100">
                </div>
                <h3 id="dnaModalTitle" class="text-lg font-semibold text-gray-900">Respuesta DNA</h3>
            </div>
            <button onclick="closeDnaModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        {{-- Content --}}
        <div id="dnaModalContent" class="py-4">
            <!-- Contenido dinámico -->
        </div>
        
        {{-- Footer --}}
        <div class="pt-3 border-t flex justify-end space-x-3">
            <button onclick="closeDnaModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Cerrar
            </button>
            <button onclick="closeDnaModal(); window.location.reload();" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Actualizar Página
            </button>
        </div>
    </div>
</div>

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
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAttachmentsList();
});

function loadAttachmentsList() {
    fetch('{{ route("company.simple.manifiesto.attachments-list", $voyage) }}')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('attachmentsList');
            if (!container) return;
            
            if (data.length === 0) {
                container.innerHTML = '<p class="text-xs text-gray-500 italic">No hay archivos adjuntos</p>';
                return;
            }
            
            container.innerHTML = data.map(att => `
                <div class="flex items-center justify-between bg-gray-50 px-2 py-1.5 rounded border border-gray-200">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-gray-700">${att.name}</p>
                        <p class="text-xs text-gray-500">${att.size}</p>
                    </div>
                    <button 
                        onclick="deleteAttachment(${att.id})"
                        class="text-red-600 hover:text-red-800 text-sm ml-2">
                        ✕
                    </button>
                </div>
            `).join('');
        })
        .catch(error => console.error('Error:', error));
}

function uploadAttachments() {
    const input = document.getElementById('attachmentFiles');
    if (!input || input.files.length === 0) {
        alert('Seleccione archivos');
        return;
    }
    
    const formData = new FormData();
    for (let file of input.files) {
        if (file.size > 5242880) {
            alert(`${file.name} supera 5MB`);
            return;
        }
        formData.append('files[]', file);
    }
    formData.append('_token', '{{ csrf_token() }}');
    
    fetch('{{ route("company.simple.manifiesto.upload-attachments", $voyage) }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadAttachmentsList();
            alert(`${data.total_uploaded} archivo(s) subido(s)`);
        } else {
            alert('Error: ' + (data.error || 'Error'));
        }
    })
    .catch(error => alert('Error al subir'));
}

function deleteAttachment(id) {
    if (!confirm('¿Eliminar?')) return;
    
    fetch(`/manifests/customs/attachments/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) loadAttachmentsList();
        else alert('Error al eliminar');
    });
}

/**
 * Rectificar mensaje enviado (reenvío con force_resend)
 */
async function rectificarMetodo(method) {
    if (!confirm(`¿Está seguro de rectificar ${method}?\n\nEsto reenviará el mensaje con los datos actuales de la base de datos, manteniendo el mismo nroViaje.`)) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Rectificando...';
    
    try {
        const response = await fetch('{{ route('company.simple.manifiesto.send', $voyage) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                method: method,
                force_resend: true
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Verificar si fue aceptado o rechazado por DNA
            if (result.accepted === false) {
                showDnaResponseModal(method + ' (Rectificación)', result, 'rejected');
            } else if (result.nroViaje) {
                showDnaResponseModal(method + ' (Rectificación)', result, 'success');
                setTimeout(() => window.location.reload(), 3000);
            } else {
                showDnaResponseModal(method + ' (Rectificación)', result, 'warning');
            }
        } else {
            showDnaResponseModal(method + ' (Rectificación)', result, 'error');
        }
        button.disabled = false;
        button.innerHTML = originalText;
    } catch (error) {
        alert(`❌ Error de conexión: ${error.message}`);
        button.disabled = false;
        button.innerHTML = originalText;
    }
}


</script>
</x-app-layout>
