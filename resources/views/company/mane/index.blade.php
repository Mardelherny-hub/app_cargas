<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Archivos MANE/Malvina') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Generación de archivos para sistema legacy Malvina de Aduana
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    ID María: {{ $company->id_maria }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Información sobre el sistema MANE/Malvina -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            📄 Sistema Malvina - Aduana Argentina
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>
                                Genere archivos de texto para importar en el sistema legacy Malvina de la Aduana. 
                                Cada archivo incluye automáticamente su <strong>ID María ({{ $company->id_maria }})</strong> 
                                en la primera línea para identificar a su empresa.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Viajes disponibles para generar archivos MANE -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md mb-6">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        📊 Viajes Disponibles para MANE
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Seleccione uno o más viajes para generar archivos MANE
                    </p>
                </div>
                <div class="px-4 py-4">
                    @if($availableVoyages->count() > 0)
                        <form id="maneGenerationForm">
                            @csrf
                            <div class="space-y-4">
                                @foreach($availableVoyages as $voyage)
                                    <div class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   name="voyage_ids[]" 
                                                   value="{{ $voyage->id }}"
                                                   class="voyage-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900">
                                                        {{ $voyage->voyage_number }}
                                                    </h4>
                                                    <p class="text-sm text-gray-500">
                                                        {{ $voyage->originPort?->name ?? 'Puerto origen' }} → 
                                                        {{ $voyage->destinationPort?->name ?? 'Puerto destino' }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center space-x-4">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        {{ $voyage->shipments->count() }} envíos
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $voyage->status }}
                                                    </span>
                                                    <button type="button" 
                                                            onclick="generateSingleVoyage({{ $voyage->id }})"
                                                            class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        Generar Individual
                                                    </button>
                                                </div>
                                            </div>
                                            @if($voyage->departure_date)
                                                <p class="text-xs text-gray-400 mt-1">
                                                    Salida: {{ $voyage->departure_date->format('d/m/Y') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Botones de acción para archivos consolidados -->
                            <div class="mt-6 flex items-center justify-between">
                                <div>
                                    <button type="button" id="selectAllBtn" onclick="selectAllVoyages()" 
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        Seleccionar todos
                                    </button>
                                    <span class="text-gray-300 mx-2">|</span>
                                    <button type="button" onclick="clearSelection()" 
                                            class="text-sm text-gray-600 hover:text-gray-800">
                                        Limpiar selección
                                    </button>
                                </div>
                                <button type="button" 
                                        onclick="generateConsolidated()"
                                        id="consolidatedBtn"
                                        disabled
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Generar Archivo Consolidado
                                </button>
                            </div>
                        </form>

                        <!-- Paginación -->
                        <div class="mt-6">
                            {{ $availableVoyages->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay viajes disponibles</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No se encontraron viajes con envíos para generar archivos MANE.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Archivos generados recientemente -->
            @if($recentFiles->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            📁 Archivos Generados Recientemente
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        @foreach($recentFiles as $file)
                            <li class="px-4 py-4 flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $file['filename'] }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ \Carbon\Carbon::createFromTimestamp($file['modified'])->format('d/m/Y H:i') }} - 
                                            {{ \Illuminate\Support\Number::fileSize($file['size']) }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ $file['download_url'] }}" 
                                   class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Descargar
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
        // Gestión de selección de viajes
        function updateConsolidatedButton() {
            const checkboxes = document.querySelectorAll('.voyage-checkbox');
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            const consolidatedBtn = document.getElementById('consolidatedBtn');
            
            consolidatedBtn.disabled = checkedBoxes.length === 0;
            
            // Actualizar texto del botón
            if (checkedBoxes.length > 0) {
                consolidatedBtn.innerHTML = `
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Generar Consolidado (${checkedBoxes.length} viajes)
                `;
            } else {
                consolidatedBtn.innerHTML = `
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Generar Archivo Consolidado
                `;
            }
        }

        function selectAllVoyages() {
            const checkboxes = document.querySelectorAll('.voyage-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateConsolidatedButton();
            
            const selectAllBtn = document.getElementById('selectAllBtn');
            selectAllBtn.textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
        }

        function clearSelection() {
            document.querySelectorAll('.voyage-checkbox').forEach(cb => cb.checked = false);
            updateConsolidatedButton();
            document.getElementById('selectAllBtn').textContent = 'Seleccionar todos';
        }

        // Generar archivo para un viaje individual
        async function generateSingleVoyage(voyageId) {
            try {
                const response = await fetch(`{{ route('company.mane.generate-voyage', '') }}/${voyageId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessMessage(result.message, result.download_url);
                } else {
                    showErrorMessage(result.error || 'Error generando archivo MANE');
                }
            } catch (error) {
                showErrorMessage('Error de conexión: ' + error.message);
            }
        }

        // Generar archivo consolidado
        async function generateConsolidated() {
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            if (checkedBoxes.length === 0) {
                showErrorMessage('Debe seleccionar al menos un viaje');
                return;
            }

            const voyageIds = Array.from(checkedBoxes).map(cb => cb.value);

            try {
                const response = await fetch('{{ route('company.mane.generate-consolidated') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ voyage_ids: voyageIds })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessMessage(result.message, result.download_url);
                    clearSelection();
                } else {
                    showErrorMessage(result.error || 'Error generando archivo consolidado');
                }
            } catch (error) {
                showErrorMessage('Error de conexión: ' + error.message);
            }
        }

        // Mostrar mensajes
        function showSuccessMessage(message, downloadUrl) {
            // Crear alerta de éxito temporal
            const alert = document.createElement('div');
            alert.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50';
            alert.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>${message}</span>
                    <a href="${downloadUrl}" class="ml-2 underline font-medium">Descargar</a>
                </div>
            `;
            document.body.appendChild(alert);
            
            setTimeout(() => {
                document.body.removeChild(alert);
                location.reload(); // Recargar para mostrar el archivo en la lista
            }, 3000);
        }

        function showErrorMessage(message) {
            const alert = document.createElement('div');
            alert.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
            alert.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(alert);
            
            setTimeout(() => {
                document.body.removeChild(alert);
            }, 5000);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.voyage-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateConsolidatedButton);
            });
        });
    </script>
    @endpush
</x-app-layout>