<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Agregar Item al Shipment') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Shipment: <span class="font-medium">{{ $shipment->shipment_number }}</span> - 
                    Viaje: <span class="font-medium">{{ $shipment->voyage->voyage_number }}</span>
                </p>
            </div>
            <a href="{{ route('company.shipments.show', $shipment) }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Volver al Shipment
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @livewire('shipment-item-create-form', [
                'shipment' => $shipment,
                'billOfLading' => $billOfLading,
                'needsToCreateBL' => $needsToCreateBL,
                'defaultBLData' => $defaultBLData,
                'cargoTypes' => $cargoTypes,
                'packagingTypes' => $packagingTypes,
                'clients' => $clients,
                'ports' => $ports,
                'countries' => $countries,
                'containerTypes' => $containerTypes,
                'nextLineNumber' => $nextLineNumber
            ])
        </div>
    </div>
</x-app-layout>