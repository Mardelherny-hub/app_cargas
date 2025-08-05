<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Detalle del Manifiesto #{{ $voyage->voyage_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Datos del Viaje</h3>

                <p><strong>Puerto Origen:</strong> {{ $voyage->origin_port_id }}</p>
                <p><strong>Puerto Destino:</strong> {{ $voyage->destination_port_id }}</p>
                <p><strong>Estado:</strong> {{ $voyage->status }}</p>

                <div class="mt-6">
                    <h4 class="font-semibold">Cargas (Shipments)</h4>
                    @forelse ($voyage->shipments as $shipment)
                        <div class="mt-2 p-3 border rounded">
                            <p><strong>Barcaza:</strong> {{ $shipment->vessel->name ?? 'N/D' }}</p>
                            <p><strong>Conocimientos:</strong> {{ $shipment->billsOfLading->count() }}</p>
                        </div>
                    @empty
                        <p class="text-gray-600">No hay cargas registradas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>