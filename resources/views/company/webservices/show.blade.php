<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transacción {{ $webservice->transaction_id }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $webservice->webservice_type_name }} - {{ $webservice->country_name }} - {{ $company->legal_name }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    ← Volver al Historial
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Notificaciones --}}
            @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                            @if(session('confirmation_number'))
                                <p class="text-xs text-green-600 mt-1">
                                    <strong>Confirmación:</strong> {{ session('confirmation_number') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                            @if(session('error_details'))
                                <p class="text-xs text-red-600 mt-1">{{ session('error_details') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Botón de acción principal para PENDING --}}
            @if($webservice->status === 'pending')
                <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-yellow-800">Transacción Pendiente</h3>
                            <p class="text-sm text-yellow-700">Esta transacción está lista para ser enviada al webservice.</p>
                        </div>
                        <form method="POST" 
                              action="{{ route('company.webservices.send-pending-transaction', $webservice) }}"
                              onsubmit="return confirm('¿Confirma el envío inmediato de esta transacción?')">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                🚀 Procesar Ahora
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Información principal --}}
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Información de la Transacción</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Detalles completos de la transacción webservice</p>
                </div>
                <div class="border-t border-gray-200">
                    <dl class="divide-y divide-gray-200">
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">ID Transacción</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->transaction_id }}</dd>
                        </div>
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd class="mt-1 text-sm sm:mt-0 sm:col-span-2">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                             @if($webservice->status === 'pending') bg-yellow-100 text-yellow-800
                                             @elseif($webservice->status === 'success') bg-green-100 text-green-800
                                             @elseif($webservice->status === 'error') bg-red-100 text-red-800
                                             @elseif($webservice->status === 'sending') bg-blue-100 text-blue-800
                                             @else bg-gray-100 text-gray-800 @endif">
                                    {{ $webservice->status_name }}
                                </span>
                            </dd>
                        </div>
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Tipo de Webservice</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->webservice_type_name }}</dd>
                        </div>
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">País</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->country_name }}</dd>
                        </div>
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Usuario</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->user->name ?? 'N/A' }}</dd>
                        </div>
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Fecha Creación</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->created_at->format('d/m/Y H:i:s') }}</dd>
                        </div>
                        @if($webservice->sent_at)
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Fecha Envío</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->sent_at->format('d/m/Y H:i:s') }}</dd>
                        </div>
                        @endif
                        @if($webservice->response_at)
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Fecha Respuesta</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $webservice->response_at->format('d/m/Y H:i:s') }}</dd>
                        </div>
                        @endif
                        @if($webservice->confirmation_number)
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Número de Confirmación</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono">{{ $webservice->confirmation_number }}</dd>
                        </div>
                        @endif
                        @if($webservice->error_message)
                        <div class="py-4 px-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Error</dt>
                            <dd class="mt-1 text-sm text-red-600 sm:mt-0 sm:col-span-2">
                                <strong>{{ $webservice->error_code ?? 'ERROR' }}:</strong> {{ $webservice->error_message }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Información adicional si hay voyage o shipment --}}
            @if($webservice->voyage)
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Información del Viaje</h3>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Código de Viaje</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $webservice->voyage->voyage_code ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Barcaza</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $webservice->voyage->barge_name ?? 'N/A' }}</dd>
                            </div>
                            @if($webservice->voyage->departure_port)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Puerto Salida</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $webservice->voyage->departure_port }}</dd>
                            </div>
                            @endif
                            @if($webservice->voyage->arrival_port)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Puerto Llegada</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $webservice->voyage->arrival_port }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            {{-- Acciones disponibles --}}
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Acciones Disponibles</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <div class="flex items-center space-x-3">
                        
                        {{-- Reintento para errores --}}
                        @if($webservice->status === 'error' && $webservice->can_retry)
                            <form method="POST" action="{{ route('company.webservices.retry-transaction', $webservice) }}" style="display: inline;">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-3 py-2 border border-orange-300 shadow-sm text-sm leading-4 font-medium rounded-md text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                                        onclick="return confirm('¿Confirma el reenvío de esta transacción?')">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Reintentar
                                </button>
                            </form>
                        @endif

                        {{-- Descargar XML si existe --}}
                        @if($webservice->response_xml)
                            <a href="{{ route('company.webservices.download-xml', $webservice) }}" 
                               class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Descargar XML
                            </a>
                        @endif

                        <span class="text-sm text-gray-500">
                            Última actualización: {{ $webservice->updated_at->format('d/m/Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>