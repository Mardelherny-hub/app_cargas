<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $page_title ?? 'Desconsolidado – AFIP' }} — {{ is_object($voyage) ? ($voyage->voyage_number ?? $voyage->id) : $voyage }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    El sistema generará el XML desde la base de datos y lo enviará a AFIP.
                </p>
            </div>

            <div class="flex items-center space-x-2">
                @if(Route::has('company.simple.anticipada.show') && is_object($voyage))
                    <a href="{{ route('company.simple.anticipada.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">Anticipada</a>
                @endif
                @if(Route::has('company.simple.micdta.show') && is_object($voyage))
                    <a href="{{ route('company.simple.micdta.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">MIC/DTA</a>
                @endif
                <span class="px-3 py-1.5 text-xs rounded border border-blue-500 text-blue-700 bg-blue-50">Desconsolidado</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Generar y enviar</h3>
                    <p class="text-sm text-gray-600 mb-5">
                        Al presionar el botón, se valida el viaje y se arma el XML de desconsolidado con los datos de la BBDD (títulos, ítems, puertos, contenedores).
                    </p>

                    <form id="afipForm" class="space-y-4">
                        @csrf
                        <div class="flex items-center space-x-3">
                            <button id="btnSend" type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg id="spinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Generar y Enviar
                            </button>
                            <a href="{{ url()->previous() }}" class="text-xs text-gray-500 hover:text-gray-700">← Volver</a>
                        </div>
                    </form>
                </div>
            </div>

            <div id="resultPanel" class="hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Resultado</h3>
                        <span id="pillStatus" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">—</span>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">XML de Respuesta (si está disponible)</label>
                        <pre id="responseXml" class="p-3 bg-gray-50 rounded overflow-x-auto text-xs font-mono whitespace-pre-wrap">—</pre>
                    </div>

                    <div class="mt-4">
                        <details class="text-sm">
                            <summary class="cursor-pointer text-gray-700">Ver respuesta SOAP cruda</summary>
                            <pre id="rawResponse" class="mt-2 p-3 bg-gray-50 rounded overflow-x-auto text-xs font-mono whitespace-pre-wrap">—</pre>
                        </details>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('afipForm');
            const btnSend = document.getElementById('btnSend');
            const spinner = document.getElementById('spinner');
            const resultPanel = document.getElementById('resultPanel');
            const pillStatus = document.getElementById('pillStatus');
            const responseXml = document.getElementById('responseXml');
            const rawResponse = document.getElementById('rawResponse');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const sendUrl = "{{ $send_route }}";

            function setLoading(state) {
                if (state) { spinner.classList.remove('hidden'); btnSend.setAttribute('disabled','disabled'); }
                else { spinner.classList.add('hidden'); btnSend.removeAttribute('disabled'); }
            }
            function setResult(ok, xml, raw) {
                resultPanel.classList.remove('hidden');
                pillStatus.textContent = ok ? 'success' : 'error';
                pillStatus.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' +
                    (ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                responseXml.textContent = xml || '—';
                rawResponse.textContent = raw || '—';
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                setLoading(true);
                try {
                    const resp = await fetch(sendUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({}) // no enviamos XML; lo genera backend
                    });
                    const data = await resp.json().catch(() => ({}));
                    const ok = !!(data && data.success);
                    const xml = data?.response_data?.parsed?.xml || data?.response_xml || null;
                    const raw = data?.response_data?.raw_response || data?.raw_response || null;
                    setResult(ok, xml, raw);
                    if (!ok && data?.error_message) alert('Error: ' + data.error_message);
                } catch (err) {
                    setResult(false, null, String(err));
                    alert('Error de comunicación.');
                } finally {
                    setLoading(false);
                }
            });
        })();
    </script>
</x-app-layout>
