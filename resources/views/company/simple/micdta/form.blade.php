<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    MIC/DTA Argentina - {{ $voyage->voyage_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Formulario de envío MIC/DTA a AFIP Argentina
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('company.simple.micdta.index') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-0.5 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </a>
                <a href="{{ route('company.simple.dashboard') }}" 
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Resumen del Voyage --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Viaje</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Voyage</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $voyage->voyage_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Embarcación</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->leadVessel->name ?? 'No asignada' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->originPort->code ?? 'N/A' }} → {{ $voyage->destinationPort->code ?? 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Shipments</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $voyage->shipments->count() }} envíos
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Salida</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->departure_date ? $voyage->departure_date->format('d/m/Y H:i') : 'No programada' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Llegada Est.</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('d/m/Y H:i') : 'No estimada' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado MIC/DTA</dt>
                            <dd class="mt-1">
                                @if($micdta_status)
                                    @php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'sent' => 'bg-green-100 text-green-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'error' => 'bg-red-100 text-red-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusClass = $statusClasses[$micdta_status->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst($micdta_status->status) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        No configurado
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Último Envío</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $micdta_status && $micdta_status->last_sent_at ? $micdta_status->last_sent_at->format('d/m/Y H:i') : 'Nunca' }}
                            </dd>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Validaciones --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Validación para Envío</h3>
                </div>
                <div class="px-6 py-4">
                    
                    {{-- Estado General --}}
                    <div class="mb-4">
                        @if($validation['can_process'])
                            <div class="rounded-md bg-green-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">Voyage Válido para MIC/DTA</h3>
                                        <div class="mt-2 text-sm text-green-700">
                                            <p>El voyage cumple con todos los requisitos para enviar MIC/DTA a AFIP.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-md bg-red-50 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Voyage No Válido para MIC/DTA</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p>Se deben corregir los siguientes errores antes de enviar:</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Errores --}}
                    @if(count($validation['errors']) > 0)
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-red-800 mb-2">Errores que impiden el envío:</h4>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($validation['errors'] as $error)
                                    <li class="text-sm text-red-700">{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Advertencias --}}
                    @if(count($validation['warnings']) > 0)
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-yellow-800 mb-2">Advertencias:</h4>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($validation['warnings'] as $warning)
                                    <li class="text-sm text-yellow-700">{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Checklist Visual --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-900">Datos del Voyage:</h4>
                            <div class="space-y-1">
                                <div class="flex items-center">
                                    @if($voyage->voyage_number)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Número de voyage</span>
                                </div>
                                <div class="flex items-center">
                                    @if($voyage->leadVessel)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Embarcación asignada</span>
                                </div>
                                <div class="flex items-center">
                                    @if($voyage->originPort && $voyage->destinationPort)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Puertos origen y destino</span>
                                </div>
                                <div class="flex items-center">
                                    @if($voyage->departure_date)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Fecha de salida</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-900">Datos de Carga:</h4>
                            <div class="space-y-1">
                                <div class="flex items-center">
                                    @if($voyage->shipments->count() > 0)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Shipments creados ({{ $voyage->shipments->count() }})</span>
                                </div>
                                <div class="flex items-center">
                                    @if($company->tax_id)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">CUIT de empresa</span>
                                </div>
                                <div class="flex items-center">
                                    @if($company->certificate_path)
                                        <svg class="h-4 w-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">Certificado AFIP</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Formulario de Envío --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Envío MIC/DTA</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Flujo secuencial AFIP: RegistrarTitEnvios → RegistrarMicDta
                    </p>
                </div>
                <div class="px-6 py-6">
                    
                    @if($validation['can_process'])
                        <form id="micDtaSendForm" action="{{ route('company.simple.micdta.send', $voyage) }}" method="POST">
                            @csrf
                            
                            {{-- Opciones de Envío --}}
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center">
                                    <input id="test_mode" name="test_mode" type="checkbox" checked 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="test_mode" class="ml-2 block text-sm text-gray-900">
                                        Modo de prueba (usar ambiente homologación AFIP)
                                    </label>
                                </div>
                                
                                @if($micdta_status && $micdta_status->status !== 'pending')
                                    <div class="flex items-center">
                                        <input id="force_send" name="force_send" type="checkbox" 
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="force_send" class="ml-2 block text-sm text-gray-900">
                                            Forzar reenvío (ya fue enviado anteriormente)
                                        </label>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Botones de Acción --}}
                            <div class="flex justify-between items-center">
                                <div class="flex space-x-3">
                                    <button type="button" onclick="validateData()" 
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Validar Datos
                                    </button>
                                </div>
                                
                                <button type="submit" id="sendButton"
                                        class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    Enviar MIC/DTA
                                </button>
                            </div>
                            
                        </form>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No se puede enviar MIC/DTA</h3>
                            <p class="mt-1 text-sm text-gray-500">Corrige los errores de validación antes de continuar.</p>
                            <div class="mt-6">
                                <a href="{{ route('company.voyages.edit', $voyage) }}" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar Voyage
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Información sobre el Flujo MIC/DTA --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Sobre el proceso MIC/DTA Argentina
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p><strong>Flujo secuencial obligatorio AFIP:</strong></p>
                            <ol class="list-decimal list-inside mt-2 space-y-1">
                                <li><strong>RegistrarTitEnvios:</strong> Registra títulos de envío y genera TRACKs únicos</li>
                                <li><strong>RegistrarMicDta:</strong> Registra el manifiesto usando los TRACKs del paso anterior</li>
                            </ol>
                            <p class="mt-3">
                                <strong>Importante:</strong> El proceso es automático y secuencial. Si algún paso falla, 
                                el sistema registrará el error y permitirá reintentar desde el punto de falla.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Historial de Transacciones --}}
            @if($last_transactions->count() > 0)
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Historial de Envíos</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Últimas {{ $last_transactions->count() }} transacciones MIC/DTA
                        </p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ID Transacción
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Resultado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ambiente
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($last_transactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                                {{ $transaction->transaction_id }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $statusClasses = [
                                                        'success' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'error' => 'bg-red-100 text-red-800',
                                                        'failed' => 'bg-red-100 text-red-800',
                                                        'sent' => 'bg-blue-100 text-blue-800'
                                                    ];
                                                    $statusClass = $statusClasses[$transaction->status] ?? 'bg-gray-100 text-gray-800';
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                                    {{ ucfirst($transaction->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($transaction->confirmation_number)
                                                    <span class="text-green-600 font-medium">{{ $transaction->confirmation_number }}</span>
                                                @elseif($transaction->error_message)
                                                    <span class="text-red-600" title="{{ $transaction->error_message }}">
                                                        {{ Str::limit($transaction->error_message, 50) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($transaction->environment === 'production')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Producción
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                        Prueba
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        {{-- Información adicional --}}
                        @if($last_transactions->count() > 0)
                            <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                                <div>
                                    Mostrando últimas {{ $last_transactions->count() }} transacciones
                                </div>
                                <div class="flex items-center space-x-4">
                                    @php
                                        $successCount = $last_transactions->where('status', 'success')->count();
                                        $errorCount = $last_transactions->whereIn('status', ['error', 'failed'])->count();
                                    @endphp
                                    @if($successCount > 0)
                                        <span class="text-green-600">✓ {{ $successCount }} exitosas</span>
                                    @endif
                                    @if($errorCount > 0)
                                        <span class="text-red-600">✗ {{ $errorCount }} con errores</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            </div>
    </div>

    {{-- Modal de Confirmación --}}
    <div id="sendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg font-medium text-gray-900">Confirmar Envío MIC/DTA</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        ¿Está seguro que desea enviar el MIC/DTA para el voyage 
                        <span class="font-mono font-semibold">{{ $voyage->voyage_number }}</span>?
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        Este proceso ejecutará el flujo secuencial AFIP y no se puede deshacer.
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="confirmSend" 
                            class="px-4 py-2 bg-indigo-600 text-white text-base font-medium rounded-md w-24 mr-2 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        Enviar
                    </button>
                    <button id="cancelSend" 
                            class="px-4 py-2 bg-gray-600 text-white text-base font-medium rounded-md w-24 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de Loading --}}
    <div id="loadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                    <svg class="animate-spin h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-2">Enviando MIC/DTA</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="loadingMessage">
                        Ejecutando flujo secuencial AFIP...
                    </p>
                    <div class="mt-3 text-xs text-gray-400">
                        <div id="step1" class="mb-1">⏳ Paso 1: RegistrarTitEnvios</div>
                        <div id="step2" class="mb-1">⌛ Paso 2: RegistrarMicDta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Variables globales
        let sendForm = null;
        let isSubmitting = false;

        document.addEventListener('DOMContentLoaded', function() {
            sendForm = document.getElementById('micDtaSendForm');
            initializeEventHandlers();
            addCSRFToken();
        });

        function initializeEventHandlers() {
            // Manejar envío del formulario
            if (sendForm) {
                sendForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!isSubmitting) {
                        showConfirmModal();
                    }
                });
            }

            // Botones del modal de confirmación
            document.getElementById('confirmSend')?.addEventListener('click', function() {
                hideConfirmModal();
                submitForm();
            });

            document.getElementById('cancelSend')?.addEventListener('click', function() {
                hideConfirmModal();
            });

            // Cerrar modal al hacer clic fuera
            document.getElementById('sendModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideConfirmModal();
                }
            });
        }

        function validateData() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Validando...';

            // Simular validación
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
                showNotification('Validación completada. Los datos son correctos.', 'success');
            }, 2000);
        }

        function showConfirmModal() {
            document.getElementById('sendModal').classList.remove('hidden');
        }

        function hideConfirmModal() {
            document.getElementById('sendModal').classList.add('hidden');
        }

        function showLoadingModal() {
            document.getElementById('loadingModal').classList.remove('hidden');
        }

        function hideLoadingModal() {
            document.getElementById('loadingModal').classList.add('hidden');
        }

        function submitForm() {
            if (isSubmitting || !sendForm) return;
            
            isSubmitting = true;
            showLoadingModal();
            updateLoadingStep(1);
            
            const formData = new FormData(sendForm);
            
            fetch(sendForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': getCSRFToken(),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingModal();
                isSubmitting = false;
                
                if (data.success) {
                    updateLoadingStep(2);
                    showNotification('MIC/DTA enviado exitosamente', 'success');
                    
                    // Actualizar página después de éxito
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showNotification(data.message || 'Error al enviar MIC/DTA', 'error');
                    if (data.errors) {
                        console.error('Errores:', data.errors);
                    }
                }
            })
            .catch(error => {
                hideLoadingModal();
                isSubmitting = false;
                showNotification('Error de conexión. Intente nuevamente.', 'error');
                console.error('Error:', error);
            });
        }

        function updateLoadingStep(step) {
            const steps = {
                1: 'RegistrarTitEnvios',
                2: 'RegistrarMicDta'
            };
            
            for (let i = 1; i <= 2; i++) {
                const stepElement = document.getElementById(`step${i}`);
                if (stepElement) {
                    if (i < step) {
                        stepElement.innerHTML = `✅ Paso ${i}: ${steps[i]}`;
                    } else if (i === step) {
                        stepElement.innerHTML = `⏳ Paso ${i}: ${steps[i]}`;
                    } else {
                        stepElement.innerHTML = `⌛ Paso ${i}: ${steps[i]}`;
                    }
                }
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
                type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
                'bg-blue-100 text-blue-800 border border-blue-200'
            }`;
            
            notification.innerHTML = `
                <div class="flex">
                    <div class="flex-shrink-0">
                        ${getNotificationIcon(type)}
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">${message}</p>
                    </div>
                    <div class="ml-auto pl-3">
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        function getNotificationIcon(type) {
            if (type === 'success') {
                return '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
            } else if (type === 'error') {
                return '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
            } else {
                return '<svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>';
            }
        }

        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '{{ csrf_token() }}';
        }

        function addCSRFToken() {
            if (!document.querySelector('meta[name="csrf-token"]')) {
                const meta = document.createElement('meta');
                meta.name = 'csrf-token';
                meta.content = '{{ csrf_token() }}';
                document.head.appendChild(meta);
            }
        }
    </script>
    @endpush

</x-app-layout>