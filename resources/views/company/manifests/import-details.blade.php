<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔍 Detalle de Importación
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $import->file_name }}
                </p>
            </div>
            <div>
                <a href="{{ route('company.manifests.import.history') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Volver al historial
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- =========================================================
                 1. RESUMEN GENERAL
                 ========================================================= --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        Resumen general
                    </h3>
                    @php $badge = $import->status_badge @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        @if($badge['color'] === 'green') bg-green-100 text-green-800
                        @elseif($badge['color'] === 'yellow') bg-yellow-100 text-yellow-800
                        @elseif($badge['color'] === 'orange') bg-orange-100 text-orange-800
                        @elseif($badge['color'] === 'red') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ $badge['text'] }}
                    </span>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</dt>
                            <dd class="mt-1 text-sm text-gray-900 break-all">{{ $import->file_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Formato</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border">
                                    {{ strtoupper($import->file_format) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $import->file_size_formatted }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $import->user->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Creado</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $import->created_at->format('d/m/Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tiempo de proceso</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $import->processing_time_formatted }}</dd>
                        </div>
                        @if($import->started_at)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Iniciado</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $import->started_at->format('d/m/Y H:i:s') }}</dd>
                            </div>
                        @endif
                        @if($import->completed_at)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Completado</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $import->completed_at->format('d/m/Y H:i:s') }}</dd>
                            </div>
                        @endif
                        @if($import->file_hash)
                            <div class="md:col-span-2 lg:col-span-3">
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Hash del archivo</dt>
                                <dd class="mt-1 text-xs text-gray-600 font-mono break-all">{{ $import->file_hash }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- =========================================================
                 2. RESULTADOS DE LA IMPORTACIÓN
                 ========================================================= --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Resultados de la importación</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $import->created_objects_summary }}</p>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Viajes</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $import->created_voyages }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Shipments</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $import->created_shipments }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">BLs</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-600">{{ $import->created_bills }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Items</dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-600">{{ $import->created_items }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Contenedores</dt>
                            <dd class="mt-1 text-2xl font-semibold text-indigo-600">{{ $import->created_containers }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Clientes</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $import->created_clients }}</dd>
                        </div>
                        <div class="bg-gray-50 rounded-md p-4 text-center">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Puertos</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $import->created_ports }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- =========================================================
                 3. VIAJE CREADO (si existe)
                 ========================================================= --}}
            @if($import->voyage)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Viaje asociado</h3>
                    </div>
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Número de viaje</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ $import->voyage->voyage_number ?? '#' . $import->voyage->id }}
                            </div>
                            @if($import->voyage->status ?? null)
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border">
                                        {{ $import->voyage->status }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('company.voyages.show', $import->voyage) }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/>
                            </svg>
                            Ver viaje
                        </a>
                    </div>
                </div>
            @endif

            {{-- =========================================================
                 4. ADVERTENCIAS
                 ========================================================= --}}
            @if($import->warnings_count > 0 && !empty($import->warnings))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-yellow-400">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center">
                        <svg class="h-5 w-5 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900">
                            Advertencias ({{ $import->warnings_count }})
                        </h3>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-2">
                            @foreach($import->warnings as $warning)
                                <li class="text-sm text-gray-700">
                                    @if(is_string($warning))
                                        <span class="text-yellow-700">⚠</span> {{ $warning }}
                                    @else
                                        <pre class="bg-yellow-50 p-2 rounded text-xs text-gray-800 overflow-x-auto">{{ json_encode($warning, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- =========================================================
                 5. ERRORES
                 ========================================================= --}}
            @if($import->errors_count > 0 && !empty($import->errors))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-red-400">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center">
                        <svg class="h-5 w-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900">
                            Errores ({{ $import->errors_count }})
                        </h3>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-2">
                            @foreach($import->errors as $error)
                                <li class="text-sm text-gray-700">
                                    @if(is_string($error))
                                        <span class="text-red-700">✗</span> {{ $error }}
                                    @else
                                        <pre class="bg-red-50 p-2 rounded text-xs text-gray-800 overflow-x-auto">{{ json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- =========================================================
                 6. ESTADO DE REVERSIÓN
                 ========================================================= --}}
            @if($import->reverted_at || $import->revert_blocked_reason || !$import->can_be_reverted)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Estado de reversión</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        @if($import->reverted_at)
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-gray-700">
                                    <div class="font-medium">Importación revertida</div>
                                    <div class="text-gray-500 mt-1">
                                        El {{ $import->reverted_at->format('d/m/Y H:i:s') }}
                                        @if($import->revertedByUser)
                                            por {{ $import->revertedByUser->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($import->revert_blocked_reason)
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-orange-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-gray-700">
                                    <div class="font-medium">Reversión bloqueada</div>
                                    <div class="text-gray-500 mt-1">{{ $import->revert_blocked_reason }}</div>
                                </div>
                            </div>
                        @endif

                        @if(!$import->reverted_at && !$import->revert_blocked_reason && !$import->can_be_reverted)
                            <div class="text-sm text-gray-500">
                                Esta importación no puede ser revertida.
                            </div>
                        @endif

                        @if($import->revert_details && is_array($import->revert_details) && !empty($import->revert_details))
                            <details class="mt-3">
                                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                                    Ver detalles técnicos de la reversión
                                </summary>
                                <pre class="mt-2 bg-gray-50 p-3 rounded text-xs text-gray-800 overflow-x-auto">{{ json_encode($import->revert_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @endif
                    </div>
                </div>
            @endif

            {{-- =========================================================
                 7. NOTAS Y CONFIGURACIÓN DEL PARSER
                 ========================================================= --}}
            @if($import->notes || ($import->parser_config && !empty($import->parser_config)))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Información adicional</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        @if($import->notes)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notas</dt>
                                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $import->notes }}</dd>
                            </div>
                        @endif
                        @if($import->parser_config && !empty($import->parser_config))
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Configuración del parser</dt>
                                <dd class="mt-1">
                                    <pre class="bg-gray-50 p-3 rounded text-xs text-gray-800 overflow-x-auto">{{ json_encode($import->parser_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
