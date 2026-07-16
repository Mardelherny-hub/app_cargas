<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📥 Procesando Importación
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-8 text-center">

                {{-- Spinner --}}
                <div id="spinner" class="mb-6">
                    <svg class="animate-spin mx-auto h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Archivo: <span class="font-semibold">{{ $fileName }}</span>
                </h3>

                <p id="status-text" class="text-gray-600 mb-2">
                    El archivo fue recibido correctamente. La importación comenzará automáticamente.
                </p>

                {{-- Bloque de errores (oculto hasta que falle) --}}
                <div id="error-box" class="hidden mt-6 text-left bg-red-50 border border-red-200 rounded-md p-4">
                    <p class="font-medium text-red-800 mb-2">No se pudo completar la importación:</p>
                    <ul id="error-list" class="list-disc list-inside text-sm text-red-700 space-y-1"></ul>
                    <a href="{{ route('company.manifests.import.index') }}"
                       class="inline-block mt-4 text-sm text-blue-600 hover:underline">
                        ← Volver a Importar
                    </a>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const statusUrl = "{{ route('company.manifests.import.status', ['uuid' => $uuid]) }}";
            const statusText = document.getElementById('status-text');
            const spinner = document.getElementById('spinner');
            const errorBox = document.getElementById('error-box');
            const errorList = document.getElementById('error-list');

            let polling = true;

            function poll() {
                if (!polling) return;
                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        switch (data.state) {
                            case 'queued':
                                statusText.textContent = 'En cola para procesamiento…';
                                setTimeout(poll, 2000);
                                break;
                            case 'processing':
                                statusText.textContent = 'Procesando archivo y creando viaje, conocimientos y contenedores…';
                                setTimeout(poll, 2000);
                                break;
                            case 'completed':
                                polling = false;
                                statusText.textContent = 'Importación completada correctamente. Abriendo el reporte…';
                                window.location.href = data.redirect_url;
                                break;
                            case 'failed':
                                polling = false;
                                spinner.style.display = 'none';
                                statusText.style.display = 'none';
                                errorList.innerHTML = '';
                                (data.errors || ['La importación falló.']).forEach(function (err) {
                                    const li = document.createElement('li');
                                    li.textContent = (typeof err === 'string') ? err : JSON.stringify(err);
                                    errorList.appendChild(li);
                                });
                                errorBox.classList.remove('hidden');
                                break;
                            default:
                                setTimeout(poll, 2000);
                        }
                    })
                    .catch(function () {
                        // Error de red puntual: reintenta sin cortar el polling.
                        setTimeout(poll, 3000);
                    });
            }

            // Primer poll con un pequeño delay para dar tiempo al worker.
            setTimeout(poll, 1500);
        })();
    </script>
    @endpush
</x-app-layout>