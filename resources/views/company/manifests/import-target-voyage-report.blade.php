{{-- Importación agregada a un viaje existente --}}
@if(!empty($targetVoyage))
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
        <h3 class="text-sm font-medium text-blue-800">Archivo agregado a un viaje existente</h3>
        <ul class="mt-2 text-sm text-blue-700 list-disc pl-5 space-y-1">
            <li>
                {{ $targetVoyage['shipment_created']
                    ? 'Se creó la carga de la embarcación en el viaje.'
                    : 'Los conocimientos se sumaron a la carga existente de la embarcación.' }}
            </li>
            @if($targetVoyage['converted_to_convoy'])
                <li>El viaje se convirtió en convoy. Si revierte esta importación, vuelve a su configuración anterior.</li>
            @endif
        </ul>

        @if(count($targetVoyage['skipped_bills']) > 0)
            <h4 class="mt-4 text-sm font-medium text-yellow-800">
                Conocimientos salteados porque ya existían en el viaje ({{ count($targetVoyage['skipped_bills']) }})
            </h4>
            <ul class="mt-2 text-sm text-yellow-800 list-disc pl-5 space-y-1">
                @foreach($targetVoyage['skipped_bills'] as $skippedBill)
                    <li>{{ $skippedBill }}</li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-blue-700">No se salteó ningún conocimiento por repetido.</p>
        @endif
    </div>
@endif
