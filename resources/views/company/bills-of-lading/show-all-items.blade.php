<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Items del Conocimiento {{ $billOfLading->bill_number }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Mostrando los {{ $billOfLading->shipmentItems->count() }} items importados.
                </p>
            </div>
            <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}#items"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm font-medium">
                ← Volver al conocimiento
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <input type="text"
                           id="itemSearch"
                           placeholder="Buscar por descripción, referencia, NCM o contenedor..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <div id="searchResults" class="text-sm text-gray-600 mt-2"></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200" id="itemsTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Línea</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mercadería / Referencia</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contenedor</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NCM / Posición</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Peso (kg)</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($billOfLading->shipmentItems as $item)
                                <tr class="item-row"
                                    data-search="{{ strtolower(trim(
                                        $item->item_description . ' '
                                        . $item->item_reference . ' '
                                        . ($item->commodity_code ?? '') . ' '
                                        . ($item->tariff_position ?? '') . ' '
                                        . $item->containers->pluck('container_number')->implode(' ')
                                    )) }}">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->line_number }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $item->item_description ?: '-' }}</div>
                                        @if($item->item_reference)
                                            <div class="text-xs text-gray-500">Ref: {{ $item->item_reference }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        @forelse($item->containers as $container)
                                            <div>{{ $container->container_number }}</div>
                                        @empty
                                            <span class="text-gray-400">-</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item->tariff_position ?: ($item->commodity_code ?: '-') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item->cargoType->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                        {{ number_format((float) $item->package_quantity, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                        {{ number_format((float) $item->gross_weight_kg, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('company.shipment-items.edit', $item) }}"
                                           class="text-blue-600 hover:text-blue-800 font-medium">
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const input = document.getElementById('itemSearch');
                const rows = Array.from(document.querySelectorAll('.item-row'));
                const result = document.getElementById('searchResults');

                function filterRows() {
                    const term = (input.value || '').trim().toLowerCase();
                    let visible = 0;

                    rows.forEach(function (row) {
                        const matches = !term || (row.dataset.search || '').includes(term);
                        row.style.display = matches ? '' : 'none';
                        if (matches) visible++;
                    });

                    result.textContent = term
                        ? visible + ' de ' + rows.length + ' items'
                        : rows.length + ' items';
                }

                input.addEventListener('input', filterRows);
                filterRows();
            })();
        </script>
    @endpush
</x-app-layout>
