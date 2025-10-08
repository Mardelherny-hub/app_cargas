<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Paraguay – Manifiestos (DNA)
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Seleccioná un viaje para enviar mensajes GDSF (XFFM, XFBL, XFBT, XISP, XRSP, XFCT).
                </p>
            </div>
            <div class="flex items-center space-x-2">
                @if(url()->previous())
                    <a href="{{ url()->previous() }}" class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">← Volver</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filtros --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Tus viajes</h3>
                            <p class="text-sm text-gray-600">Listado de viajes disponibles para envío a DNA Paraguay.</p>
                        </div>
                        <div class="w-full md:w-80">
                            <label for="search" class="sr-only">Buscar</label>
                            <input id="search" type="text" placeholder="Buscar por # de viaje..."
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabla --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm" id="voyagesTable">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">ID</th>
                                <th class="px-3 py-2 text-left font-medium">Nº de viaje</th>
                                <th class="px-3 py-2 text-left font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700" id="voyagesBody">
                        @forelse($voyages ?? [] as $voyage)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ $voyage->id }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ $voyage->voyage_number ?? '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if(Route::has('company.simple.manifiesto.show'))
                                        <a href="{{ route('company.simple.manifiesto.show', $voyage) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium transition">
                                            Abrir
                                        </a>
                                    @else
                                        <button disabled
                                                class="inline-flex items-center px-3 py-1.5 bg-gray-200 text-gray-500 rounded text-xs font-medium cursor-not-allowed">
                                            Ruta no disponible
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-gray-500">
                                    No hay viajes disponibles.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Ayuda --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 mb-2">Ayuda</h4>
                    <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                        <li>Usá el buscador para filtrar por número de viaje.</li>
                        <li>Hacé clic en <span class="font-medium">Abrir</span> para cargar el XML y enviar a DNA.</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const search = document.getElementById('search');
            const tbody = document.getElementById('voyagesBody');

            function filterRows() {
                const q = (search.value || '').toLowerCase();
                Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
                    const idCell = tr.children[0]?.textContent?.toLowerCase() || '';
                    const vnCell = tr.children[1]?.textContent?.toLowerCase() || '';
                    const show = idCell.includes(q) || vnCell.includes(q);
                    tr.style.display = show ? '' : 'none';
                });
            }

            search?.addEventListener('input', filterRows);
        })();
    </script>
</x-app-layout>
