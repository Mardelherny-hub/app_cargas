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
                                @if($xffmTransaction && in_array($xffmTransaction->status, ['sent', 'rejected']))
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

                            @if($xffmTransaction && in_array($xffmTransaction->status, ['sent', 'rejected']))
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
                                        <button onclick="verXml({{ $xffmTransaction->id }}, 'request')"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors cursor-pointer">
                                            📤 Request XML
                                        </button>
                                    @endif
                                    @if($xffmTransaction->response_xml)
                                        <button onclick="verXml({{ $xffmTransaction->id }}, 'response')"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors cursor-pointer">
                                            📥 Response XML
                                        </button>
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
                            $xfblSent = $xfblTransaction && in_array($xfblTransaction->status, ['sent', 'rejected']);
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
                            
                            {{-- Zona de adjuntos (visible cuando XFFM enviado) --}}
                            @if($xffmSent)
                                <div class="mb-4 p-3 bg-white rounded border border-gray-300">
                                    <h5 class="text-sm font-semibold text-gray-700 mb-2">📎 Documentos Adjuntos (DocAnexo)</h5>
                                    <p class="text-xs text-gray-600 mb-3">Facturas, packing lists u otros documentos PDF para enviar a DNA</p>
                                    
                                    {{-- Formulario Upload completo --}}
                                    @if(!$xfblSent)
                                    <div class="mb-3 p-2 bg-gray-50 rounded border border-gray-200">
                                        <div class="grid grid-cols-1 gap-2 mb-2">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Conocimiento (BL)</label>
                                                <select id="att_bl_id" class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                                                    <option value="">-- Seleccionar BL --</option>
                                                    @foreach($voyage->shipments->flatMap->billsOfLading as $bl)
                                                        <option value="{{ $bl->id }}">{{ $bl->bill_number }} - {{ $bl->shipper->name ?? 'N/A' }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo Documento</label>
                                                    <select id="att_doc_type" class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                                                        <option value="380">380 - Factura Comercial</option>
                                                        <option value="271">271 - Packing List</option>
                                                        <option value="861">861 - Certificado</option>
                                                        <option value="911">911 - Permiso</option>
                                                        <option value="999">999 - Otros</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nº Documento</label>
                                                    <input type="text" id="att_doc_number" placeholder="Ej: FAC-001" 
                                                        class="w-full text-xs border-gray-300 rounded-md shadow-sm">
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <input type="file" id="att_file" accept=".pdf" class="flex-1 text-xs border border-gray-300 rounded px-2 py-1">
                                                <button type="button" onclick="uploadAttachment()" 
                                                    class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                                    Subir
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-400">Solo PDF, máx 5MB. Se incluirá en XFBL como DocAnexo.</p>
                                    </div>
                                    @endif
                                    
                                    {{-- Lista archivos con estado DNA --}}
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
                            $xfbtSent = $xfbtTransaction && in_array($xfbtTransaction->status, ['sent', 'rejected']);
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
                                        <button onclick="verXml({{ $xfbtTransaction->id }}, 'request')"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors cursor-pointer">
                                            📤 Request XML
                                        </button>
                                    @endif
                                    @if($xfbtTransaction->response_xml)
                                        <button onclick="verXml({{ $xfbtTransaction->id }}, 'response')"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors cursor-pointer">
                                            📥 Response XML
                                        </button>
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
                            $xfctSent = $xfctTransaction && in_array($xfctTransaction->status, ['sent', 'rejected']);
                            
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
                                        <button onclick="verXml({{ $xfctTransaction->id }}, 'request')"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors cursor-pointer">
                                            📤 Request XML
                                        </button>
                                    @endif
                                    @if($xfctTransaction->response_xml)
                                        <button onclick="verXml({{ $xfctTransaction->id }}, 'response')"
                                           class="flex-1 text-center px-2 py-1.5 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 transition-colors cursor-pointer">
                                            📥 Response XML
                                        </button>
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

            {{-- XISP/XRSP - Gestión de Embarcaciones (Opcional) --}}
            @if($xffmSent)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-cyan-50 to-white">
                    <h3 class="text-lg font-semibold text-gray-900">Gestión de Embarcaciones (Opcional)</h3>
                    <p class="text-sm text-gray-600 mt-1">XISP: Incluir embarcación / XRSP: Desvincular embarcación del viaje</p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        {{-- XISP - Incluir Embarcación --}}
                        <div class="border-2 rounded-lg p-5 border-cyan-400 bg-cyan-50">
                            <div class="flex items-center space-x-2 mb-3">
                                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-cyan-600 text-white text-sm font-bold">+</span>
                                <h4 class="text-base font-semibold text-gray-900">XISP - Incluir Embarcación</h4>
                            </div>
                            <p class="text-xs text-gray-600 mb-4">Agrega una embarcación al viaje antes de generar el manifiesto</p>

                            {{-- Selector de embarcación --}}
                            <div class="space-y-3 mb-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Embarcación</label>
                                    <select id="xisp_vessel_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500">
                                        <option value="">-- Seleccionar embarcación --</option>
                                        @foreach($companyVessels as $v)
                                            <option value="{{ $v->id }}" data-name="{{ $v->name }}" data-reg="{{ $v->registration_number }}" data-type="{{ $v->vesselType->category ?? 'barge' }}">
                                                {{ $v->name }} ({{ $v->registration_number }}) - {{ $v->vesselType->short_name ?? 'N/A' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">¿En Lastre?</label>
                                    <select id="xisp_in_ballast" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500">
                                        <option value="N">No - Con carga</option>
                                        <option value="S">Sí - En lastre (vacía)</option>
                                    </select>
                                </div>

                                {{-- Precintos opcionales --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Precintos (opcional)</label>
                                    <div id="xisp_seals_container">
                                        <div class="flex gap-2 mb-1">
                                            <input type="text" id="xisp_seal_number" placeholder="Nro. precinto" 
                                                class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500">
                                            <select id="xisp_seal_type" class="w-24 text-sm border-gray-300 rounded-md shadow-sm">
                                                <option value="BC">BC</option>
                                                <option value="BF">BF</option>
                                            </select>
                                            <button type="button" onclick="agregarPrecintoXisp()" 
                                                class="px-3 py-1 bg-cyan-100 text-cyan-700 text-sm rounded hover:bg-cyan-200">
                                                +
                                            </button>
                                        </div>
                                        <div id="xisp_seals_list" class="space-y-1 text-xs"></div>
                                    </div>
                                </div>
                            </div>

                            <button onclick="enviarXisp()" 
                                class="w-full px-4 py-2.5 bg-cyan-600 text-white text-sm font-semibold rounded-lg hover:bg-cyan-700 transition-colors">
                                Incluir Embarcación (XISP)
                            </button>

                            {{-- Historial XISP --}}
                            @if($xispTransactions->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-cyan-200">
                                    <p class="text-xs font-medium text-gray-600 mb-2">Embarcaciones incluidas:</p>
                                    @foreach($xispTransactions as $xispTx)
                                        <div class="flex items-center justify-between text-xs mb-1 p-1.5 bg-white rounded">
                                            <span class="text-gray-700">
                                                {{ $xispTx->additional_metadata['vessel_name'] ?? 'N/A' }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $xispTx->status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $xispTx->status === 'sent' ? '✓' : '✗' }} {{ strtoupper($xispTx->status) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- XRSP - Desvincular Embarcación --}}
                        <div class="border-2 rounded-lg p-5 border-red-300 bg-red-50">
                            <div class="flex items-center space-x-2 mb-3">
                                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-sm font-bold">−</span>
                                <h4 class="text-base font-semibold text-gray-900">XRSP - Desvincular Embarcación</h4>
                            </div>
                            <p class="text-xs text-gray-600 mb-4">Remueve una embarcación del viaje antes de generar el manifiesto</p>

                            {{-- Selector de embarcación --}}
                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Embarcación a desvincular</label>
                                <select id="xrsp_vessel_id" class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Seleccionar embarcación --</option>
                                    @foreach($companyVessels as $v)
                                        <option value="{{ $v->id }}">
                                            {{ $v->name }} ({{ $v->registration_number }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button onclick="enviarXrsp()" 
                                class="w-full px-4 py-2.5 bg-red-500 text-white text-sm font-semibold rounded-lg hover:bg-red-600 transition-colors">
                                Desvincular Embarcación (XRSP)
                            </button>

                            {{-- Historial XRSP --}}
                            @if($xrspTransactions->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-red-200">
                                    <p class="text-xs font-medium text-gray-600 mb-2">Embarcaciones desvinculadas:</p>
                                    @foreach($xrspTransactions as $xrspTx)
                                        <div class="flex items-center justify-between text-xs mb-1 p-1.5 bg-white rounded">
                                            <span class="text-gray-700">
                                                {{ $xrspTx->additional_metadata['vessel_name'] ?? 'N/A' }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $xrspTx->status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $xrspTx->status === 'sent' ? '✓' : '✗' }} {{ strtoupper($xrspTx->status) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
            @endif

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
                                            'rejected' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        $statusIcon = match($transaction->status) {
                                            'sent', 'success' => '✅',
                                            'pending' => '⏳',
                                            'error' => '❌',
                                            'rejected' => '🚫',
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
                    <button onclick="cerrarModal(); verXml(${transactionId}, 'request')"
                       class="flex-1 text-center px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium cursor-pointer">
                        📤 Ver Request XML
                    </button>
                ` : ''}
                ${transaction.response_xml ? `
                    <button onclick="cerrarModal(); verXml(${transactionId}, 'response')"
                       class="flex-1 text-center px-3 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors text-sm font-medium cursor-pointer">
                        📥 Ver Response XML
                    </button>
                ` : ''}
            </div>
        `;
    }

    function getStatusBadge(status) {
        const badges = {
            success: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Exitoso</span>',
            sent: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">📤 Enviado</span>',
            rejected: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">🚫 Rechazado</span>',
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
                <div class="flex items-center justify-between bg-gray-50 px-2 py-1.5 rounded border ${att.sent_to_dna ? 'border-green-300 bg-green-50' : 'border-gray-200'}">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate">${att.name}</p>
                        <p class="text-xs text-gray-500">
                            BL: ${att.bl_number || 'N/A'} | Tipo: ${att.document_type} | Doc: ${att.document_number} | ${att.size}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                        ${att.sent_to_dna 
                            ? `<span class="text-xs text-green-700 font-bold px-1.5 py-0.5 bg-green-100 rounded">✓ DNA</span>`
                            : `<button onclick="sendToDna(${att.id})" 
                                class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200" title="Enviar a DNA">
                                📤 DNA
                              </button>
                              <button onclick="deleteAttachment(${att.id})" 
                                class="text-xs text-red-600 hover:text-red-800 px-1">✕</button>`
                        }
                    </div>
                </div>
            `).join('');
        })
        .catch(error => {
            console.error('Error cargando adjuntos:', error);
            const container = document.getElementById('attachmentsList');
            if (container) container.innerHTML = '<p class="text-xs text-red-500">Error al cargar adjuntos</p>';
        });
}

function uploadAttachment() {
    const blId = document.getElementById('att_bl_id')?.value;
    const docType = document.getElementById('att_doc_type')?.value;
    const docNumber = document.getElementById('att_doc_number')?.value?.trim();
    const fileInput = document.getElementById('att_file');
    
    if (!blId) { alert('Seleccione un Conocimiento (BL)'); return; }
    if (!docNumber) { alert('Ingrese el número de documento'); return; }
    if (!fileInput?.files?.length) { alert('Seleccione un archivo PDF'); return; }
    
    const file = fileInput.files[0];
    if (file.size > 5242880) { alert('El archivo supera 5MB'); return; }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('bill_of_lading_id', blId);
    formData.append('document_type', docType);
    formData.append('document_number', docNumber);
    formData.append('_token', '{{ csrf_token() }}');
    
    fetch('{{ route("company.simple.manifiesto.upload-attachment", $voyage) }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fileInput.value = '';
            document.getElementById('att_doc_number').value = '';
            loadAttachmentsList();
            alert('✓ ' + (data.message || 'Documento subido'));
        } else {
            alert('Error: ' + (data.error || 'Error al subir'));
        }
    })
    .catch(error => alert('Error de conexión: ' + error.message));
}

function deleteAttachment(id) {
    if (!confirm('¿Eliminar este documento?')) return;
    
    fetch(`{{ url('company/simple/webservices/manifiesto/' . $voyage->id . '/attachments') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAttachmentsList();
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar'));
        }
    })
    .catch(error => alert('Error: ' + error.message));
}

function sendToDna(attachmentId) {
    if (!confirm('¿Enviar este documento a DNA Paraguay?')) return;
    
    fetch(`{{ url('company/simple/webservices/manifiesto/' . $voyage->id . '/attachments') }}/${attachmentId}/send-dna`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + (data.message || 'Documento enviado a DNA'));
            loadAttachmentsList();
        } else {
            alert('✗ Error: ' + (data.error_message || 'Error al enviar'));
        }
    })
    .catch(error => alert('Error de conexión: ' + error.message));
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

function verXml(transactionId, type) {
    const modal = document.getElementById('xml-modal');
    const title = document.getElementById('xml-modal-title');
    const content = document.getElementById('xml-modal-content');
    const info = document.getElementById('xml-modal-info');
    
    modal.classList.remove('hidden');
    title.textContent = type === 'request' ? '📤 XML Request' : '📥 XML Response';
    content.textContent = 'Cargando XML...';
    content.className = 'mt-3 text-sm text-yellow-400 whitespace-pre-wrap font-mono bg-black p-4 rounded max-h-[70vh] overflow-auto';
    info.textContent = 'Transaction ID: ' + transactionId;
    
    fetch(`/company/webservices/transaction/${transactionId}/xml/${type}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            content.textContent = data.xml;
            content.className = 'mt-3 text-sm text-green-400 whitespace-pre-wrap font-mono bg-black p-4 rounded max-h-[70vh] overflow-auto';
        } else {
            content.textContent = 'Error: ' + (data.message || 'No se pudo cargar el XML');
            content.className = 'mt-3 text-sm text-red-400 whitespace-pre-wrap font-mono bg-black p-4 rounded max-h-[70vh] overflow-auto';
        }
    })
    .catch(error => {
        content.textContent = 'Error de conexión: ' + error.message;
        content.className = 'mt-3 text-sm text-red-400 whitespace-pre-wrap font-mono bg-black p-4 rounded max-h-[70vh] overflow-auto';
    });
}

function cerrarXmlModal() {
    document.getElementById('xml-modal').classList.add('hidden');
}

function copiarXml(btn) {
    const content = document.getElementById('xml-modal-content').textContent;
    navigator.clipboard.writeText(content).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅ Copiado!';
        setTimeout(() => { btn.innerHTML = originalText; }, 2000);
    }).catch(err => {
        alert('Error al copiar: ' + err);
    });
}

// ============================================
        // XISP/XRSP - Gestión de Embarcaciones
        // ============================================

        let xispSeals = [];

        function agregarPrecintoXisp() {
            const number = document.getElementById('xisp_seal_number').value.trim();
            const type = document.getElementById('xisp_seal_type').value;
            
            if (!number) {
                alert('Ingrese el número de precinto');
                return;
            }

            xispSeals.push({ nroPrecinto: number, tipPrecin: type });
            
            // Actualizar lista visual
            const list = document.getElementById('xisp_seals_list');
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-cyan-100 px-2 py-1 rounded';
            item.innerHTML = `
                <span>${number} (${type})</span>
                <button onclick="this.parentElement.remove(); xispSeals = xispSeals.filter(s => s.nroPrecinto !== '${number}')" 
                    class="text-red-500 hover:text-red-700 font-bold">✗</button>
            `;
            list.appendChild(item);
            
            // Limpiar input
            document.getElementById('xisp_seal_number').value = '';
        }

        function enviarXisp() {
            const vesselId = document.getElementById('xisp_vessel_id').value;
            const inBallast = document.getElementById('xisp_in_ballast').value;
            
            if (!vesselId) {
                alert('Seleccione una embarcación');
                return;
            }

            const vesselName = document.getElementById('xisp_vessel_id').selectedOptions[0].dataset.name;
            
            if (!confirm(`¿Incluir embarcación "${vesselName}" en el viaje?`)) return;

            // Deshabilitar botón
            event.target.disabled = true;
            event.target.textContent = 'Enviando...';

            fetch('{{ $send_route }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    method: 'XISP',
                    vessel_id: vesselId,
                    in_ballast: inBallast,
                    seals: xispSeals
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + (data.message || 'Embarcación incluida exitosamente'));
                    xispSeals = [];
                    location.reload();
                } else {
                    alert('✗ Error: ' + (data.error_message || 'Error desconocido'));
                    event.target.disabled = false;
                    event.target.textContent = 'Incluir Embarcación (XISP)';
                }
            })
            .catch(error => {
                alert('Error de conexión: ' + error.message);
                event.target.disabled = false;
                event.target.textContent = 'Incluir Embarcación (XISP)';
            });
        }

        function enviarXrsp() {
            const vesselId = document.getElementById('xrsp_vessel_id').value;
            
            if (!vesselId) {
                alert('Seleccione una embarcación');
                return;
            }

            const vesselName = document.getElementById('xrsp_vessel_id').selectedOptions[0].textContent.trim();
            
            if (!confirm(`¿DESVINCULAR embarcación "${vesselName}" del viaje? Esta acción no se puede deshacer.`)) return;

            // Deshabilitar botón
            event.target.disabled = true;
            event.target.textContent = 'Enviando...';

            fetch('{{ $send_route }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    method: 'XRSP',
                    vessel_id: vesselId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + (data.message || 'Embarcación desvinculada exitosamente'));
                    location.reload();
                } else {
                    alert('✗ Error: ' + (data.error_message || 'Error desconocido'));
                    event.target.disabled = false;
                    event.target.textContent = 'Desvincular Embarcación (XRSP)';
                }
            })
            .catch(error => {
                alert('Error de conexión: ' + error.message);
                event.target.disabled = false;
                event.target.textContent = 'Desvincular Embarcación (XRSP)';
            });
        }
</script>

{{-- Modal XML con botón copiar --}}
<div id="xml-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 lg:w-3/4 max-w-5xl shadow-lg rounded-lg bg-gray-900">
        <div class="flex items-center justify-between pb-3 border-b border-gray-700">
            <h3 id="xml-modal-title" class="text-lg font-semibold text-white">XML</h3>
            <div class="flex items-center space-x-2">
                <button onclick="copiarXml(this)" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">📋 Copiar</button>
                <button onclick="cerrarXmlModal()" class="text-gray-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="mt-2 text-xs text-gray-400" id="xml-modal-info"></div>
        <pre id="xml-modal-content" class="mt-3 text-sm text-green-400 whitespace-pre-wrap font-mono bg-black p-4 rounded max-h-[70vh] overflow-auto"></pre>
    </div>
</div>
</x-app-layout>
