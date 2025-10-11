<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Cartas de Aviso de Llegada') }}
            </h2>
            <a href="{{ route('company.reports.index') }}" 
               class="text-sm text-blue-600 hover:text-blue-800">
                ← Volver a Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Descripción del reporte --}}
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-blue-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-blue-800 mb-1">Acerca de este reporte</h3>
                        <p class="text-sm text-blue-700">
                            Las cartas de aviso notifican formalmente a los consignatarios sobre el arribo de mercadería. 
                            Se genera <strong>una carta por cada consignatario</strong> con todos sus conocimientos del viaje seleccionado.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Seleccionar Viaje
                </h3>

                <form id="filterForm" class="space-y-4">
                    @csrf
                    
                    {{-- Selector de Viaje --}}
                    <div>
                        <label for="voyage_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Viaje <span class="text-red-500">*</span>
                        </label>
                        <select name="voyage_id" 
                                id="voyage_id" 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                required>
                            <option value="">-- Seleccione un viaje --</option>
                            @foreach($voyages as $voyage)
                                <option value="{{ $voyage->id }}" 
                                        data-consignees="{{ $voyage->billsOfLading->pluck('consignee_id')->unique()->count() }}">
                                    {{ $voyage->voyage_number }} - 
                                    {{ $voyage->leadVessel->name ?? 'N/A' }} - 
                                    {{ $voyage->originPort->name ?? '' }} → {{ $voyage->destinationPort->name ?? '' }}
                                    ({{ $voyage->billsOfLading->count() }} BLs)
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Seleccione el viaje del cual desea generar las cartas de aviso
                        </p>
                    </div>

                    {{-- Info dinámica del viaje --}}
                    <div id="voyageInfo" class="hidden bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Información del viaje</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                            <div>
                                <span class="text-gray-600">Total BLs:</span>
                                <span class="font-medium ml-1" id="totalBls">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Consignatarios únicos:</span>
                                <span class="font-medium ml-1" id="totalConsignees">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Cartas a generar:</span>
                                <span class="font-medium ml-1 text-blue-600" id="totalLetters">-</span>
                            </div>
                        </div>
                    </div>

                    {{-- Selector de Consignatario (Opcional) --}}
                    <div id="consigneeSelector" class="hidden">
                        <label for="consignee_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Consignatario (opcional)
                        </label>
                        <select name="consignee_id" 
                                id="consignee_id" 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Todos los consignatarios (ZIP) --</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Deje vacío para generar todas las cartas en un archivo ZIP
                        </p>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="flex items-center space-x-3 pt-4">
                        <button type="submit" 
                                id="generateBtn"
                                class="flex-1 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                <span id="btnText">Generar Cartas PDF</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Información adicional --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ¿Qué incluye cada carta?
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <strong class="text-gray-800">Membrete profesional</strong>
                            <p class="text-xs">Con datos de su empresa</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <strong class="text-gray-800">Datos del consignatario</strong>
                            <p class="text-xs">Nombre, dirección, contacto</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <strong class="text-gray-800">Detalle de mercadería</strong>
                            <p class="text-xs">Todos los BLs del consignatario</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div>
                            <strong class="text-gray-800">Lugar de retiro</strong>
                            <p class="text-xs">Puerto y documentación requerida</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const voyageSelect = document.getElementById('voyage_id');
            const consigneeSelect = document.getElementById('consignee_id');
            const consigneeSelector = document.getElementById('consigneeSelector');
            const voyageInfo = document.getElementById('voyageInfo');
            const generateBtn = document.getElementById('generateBtn');
            const btnText = document.getElementById('btnText');
            const filterForm = document.getElementById('filterForm');

            // Cargar consignatarios cuando se selecciona un viaje
            voyageSelect.addEventListener('change', async function() {
                const voyageId = this.value;
                
                if (!voyageId) {
                    voyageInfo.classList.add('hidden');
                    consigneeSelector.classList.add('hidden');
                    generateBtn.disabled = true;
                    return;
                }

                try {
                    // Mostrar info del viaje
                    const option = this.options[this.selectedIndex];
                    const totalConsignees = parseInt(option.dataset.consignees || 0);
                    
                    document.getElementById('totalBls').textContent = option.text.match(/\((\d+) BLs\)/)?.[1] || '0';
                    document.getElementById('totalConsignees').textContent = totalConsignees;
                    document.getElementById('totalLetters').textContent = totalConsignees;
                    
                    voyageInfo.classList.remove('hidden');

                    // Cargar consignatarios del viaje
                    const response = await fetch(`/company/reports/arrival-notices/consignees?voyage_id=${voyageId}`);
                    const consignees = await response.json();

                    // Limpiar selector
                    consigneeSelect.innerHTML = '<option value="">-- Todos los consignatarios (ZIP) --</option>';
                    
                    // Agregar opciones
                    consignees.forEach(consignee => {
                        const option = document.createElement('option');
                        option.value = consignee.id;
                        option.textContent = `${consignee.legal_name} (${consignee.bills_count} BLs)`;
                        consigneeSelect.appendChild(option);
                    });

                    consigneeSelector.classList.remove('hidden');
                    generateBtn.disabled = false;

                } catch (error) {
                    console.error('Error cargando consignatarios:', error);
                    alert('Error cargando datos del viaje');
                }
            });

            // Actualizar texto del botón según selección
            consigneeSelect.addEventListener('change', function() {
                if (this.value) {
                    btnText.textContent = 'Generar Carta Individual PDF';
                } else {
                    btnText.textContent = 'Generar Todas las Cartas (ZIP)';
                }
            });

            // Enviar formulario
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const voyageId = voyageSelect.value;
                const consigneeId = consigneeSelect.value;

                if (!voyageId) {
                    alert('Debe seleccionar un viaje');
                    return;
                }

                // Deshabilitar botón
                generateBtn.disabled = true;
                btnText.textContent = 'Generando...';

                // Construir URL
                const params = new URLSearchParams({
                    report: 'arrival-notices',
                    format: 'pdf',
                    'filters[voyage_id]': voyageId
                });

                if (consigneeId) {
                    params.append('filters[consignee_id]', consigneeId);
                }

                // Descargar archivo
                fetch('{{ route("company.reports.export", ["report" => "arrival-notices"]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: params.toString()
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error generando cartas');
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Descargar archivo
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = consigneeId ? 'carta_aviso.pdf' : 'cartas_aviso.zip';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();

                    // Restaurar botón
                    generateBtn.disabled = false;
                    btnText.textContent = consigneeId ? 'Generar Carta Individual PDF' : 'Generar Todas las Cartas (ZIP)';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generando las cartas de aviso');
                    generateBtn.disabled = false;
                    btnText.textContent = consigneeId ? 'Generar Carta Individual PDF' : 'Generar Todas las Cartas (ZIP)';
                });
            });
        });
    </script>
    @endpush
</x-app-layout>