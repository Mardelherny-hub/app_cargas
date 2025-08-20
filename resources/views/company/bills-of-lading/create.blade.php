<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Conocimiento de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Complete todos los campos obligatorios marcados con *
                </p>
            </div>
            <div>
                <a href="{{ route('company.bills-of-lading.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- mensajes de error --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-md mb-6">
                    <h3 class="font-semibold">Errores encontrados:</h3>
                    <ul class="list-disc pl-5 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            {{-- Información de contexto si viene de un shipment --}}
            @if(isset($componentData['shipmentId']) && $componentData['shipmentId'])
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-900">
                                Creando conocimiento para el shipment preseleccionado
                            </p>
                            <p class="text-xs text-blue-700">
                                Los datos del viaje se cargarán automáticamente
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Componente Livewire Principal --}}
            <div class="space-y-6">
                @livewire('bill-of-lading-create-form', [
                    'shipmentId' => $componentData['shipmentId'] ?? null,
                    'preselectedLoadingPortId' => $componentData['preselectedLoadingPortId'] ?? null,
                    'preselectedDischargePortId' => $componentData['preselectedDischargePortId'] ?? null,
                ])
            </div>

        </div>
    </div>


</x-app-layout>