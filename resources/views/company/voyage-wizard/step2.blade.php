<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">Nuevo Viaje Completo</h1>
                            <p class="text-sm text-gray-600">Captura todos los datos requeridos por AFIP</p>
                        </div>
                        <div class="flex items-center space-x-4 mx-8">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                PASO 2 de 3
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- BARRA DE PROGRESO --}}
            <div class="mt-4">
                <div class="flex items-center">
                    <div class="flex-1">
                        <div class="flex items-center">
                            {{-- Paso 1 - Completado --}}
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="ml-2 text-sm font-medium text-green-600">Datos del Viaje</span>
                            </div>
                            
                            {{-- Línea conectora --}}
                            <div class="flex-1 mx-4 h-0.5 bg-blue-600"></div>
                            
                            {{-- Paso 2 - Activo --}}
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm font-medium">2</span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-blue-600">Conocimientos</span>
                            </div>
                            
                            {{-- Línea conectora --}}
                            <div class="flex-1 mx-4 h-0.5 bg-gray-300"></div>
                            
                            {{-- Paso 3 - Pendiente --}}
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <span class="text-gray-500 text-sm font-medium">3</span>
                                </div>
                                <span class="ml-2 text-sm text-gray-500">Mercadería</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Barra de progreso numérica --}}
                <div class="mt-2">
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 66.66%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Progreso: 67% completado</p>
                </div>
            </div>
        </div>

       <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('bill-of-lading-create-form', [
                'shipmentId' => null,
                'wizardMode' => true,
                'step1Data' => $step1Data,
                'preselectedLoadingPortId' => $step1Data['origin_port_id'] ?? null,
                'preselectedDischargePortId' => $step1Data['destination_port_id'] ?? null,
            ])
        </div>
    </div>
</x-app-layout>