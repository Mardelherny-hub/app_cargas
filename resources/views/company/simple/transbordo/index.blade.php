{{--
    Listado de preparación de Transbordos Hidrovía (sin transmisión aduanera).
    El controlador proporcionará $voyages (paginador con shipments.billsOfLading,
    shipments.vessel.vesselType), $company y $search; todos de la empresa autorizada.
    Se enlaza únicamente al editor local drafts.edit, nunca a una ruta SOAP.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Transbordos — Preparación de cargas</h1>
                <p class="text-sm text-gray-600">{{ $company->name }} · Distribución de conocimientos entre barcazas</p>
            </div>
            <a href="{{ route('company.simple.dashboard') }}" class="text-sm font-medium text-blue-700 hover:underline">Volver al panel</a>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <strong>Etapa de pruebas:</strong> desde aquí se prepara y valida el reparto de mercadería por barcaza.
            Los borradores no se envían a ARCA. La transmisión aduanera todavía no está habilitada.
        </div>

        <section class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <form method="GET" action="{{ route('company.simple.transbordo.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <label for="transbordo-search" class="flex-1 text-sm font-medium text-gray-700">
                    Buscar viaje o conocimiento
                    <input id="transbordo-search" name="search" type="search" value="{{ $search }}"
                        placeholder="Número de viaje o conocimiento" maxlength="100"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Buscar</button>
                @if($search !== '')
                    <a href="{{ route('company.simple.transbordo.index') }}" class="px-2 py-2 text-sm text-gray-600 hover:underline">Limpiar búsqueda</a>
                @endif
            </form>
        </section>

        @forelse($voyages as $voyage)
            @php
                $eligibleBills = $voyage->shipments->flatMap(
                    fn ($shipment) => $shipment->billsOfLading->filter(
                        fn ($bill) => $bill->is_transit_transshipment === 'S'
                    )
                );
                $bargeCount = $voyage->shipments->filter(
                    fn ($shipment) => $shipment->vessel?->vesselType?->category === 'barge'
                )->count();
            @endphp
            <section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="flex flex-wrap justify-between gap-3 items-start px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Viaje {{ $voyage->voyage_number }}</h2>
                        <p class="text-sm text-gray-600">Estado: {{ $voyage->status }} · {{ $bargeCount }} barcazas registradas</p>
                    </div>
                    <span class="text-xs text-gray-600">{{ $eligibleBills->count() }} conocimientos de tránsito/transbordo</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($eligibleBills as $bill)
                        @php
                            $canPrepare = !$bill->is_fractional && $bill->canBeEdited() && $bargeCount >= 2;
                        @endphp
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 break-words">Conocimiento {{ $bill->bill_number }}</p>
                                <p class="mt-1 text-xs text-gray-600">
                                    Estado: {{ $bill->status }}
                                    @if($bill->is_fractional)
                                        · Fraccionado (pendiente de implementación)
                                    @elseif(!$bill->canBeEdited())
                                        · No editable
                                    @elseif($bargeCount < 2)
                                        · Se necesitan al menos dos barcazas en este viaje
                                    @else
                                        · Disponible para preparar distribución
                                    @endif
                                </p>
                            </div>
                            @if($canPrepare)
                                <a href="{{ route('company.simple.transbordo.drafts.edit', ['voyage' => $voyage->id, 'bill' => $bill->id]) }}"
                                    class="inline-flex shrink-0 justify-center rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                                    Preparar distribución
                                </a>
                            @else
                                <span class="inline-flex shrink-0 justify-center rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600">No disponible para edición</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="bg-white rounded-lg border border-gray-200 shadow-sm p-8 text-center">
                <h2 class="text-base font-semibold text-gray-900">No se encontraron viajes con conocimientos de transbordo.</h2>
                <p class="mt-2 text-sm text-gray-600">Verificá los datos cargados o ajustá la búsqueda. No se modificó ningún registro.</p>
            </section>
        @endforelse

        @if($voyages->hasPages())
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                {{ $voyages->appends(['search' => $search])->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
