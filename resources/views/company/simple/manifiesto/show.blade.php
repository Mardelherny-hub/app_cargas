<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $page_title ?? 'Manifiesto – DNA Paraguay' }} — {{ is_object($voyage) ? ($voyage->voyage_number ?? $voyage->id) : $voyage }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Envío de mensajes <span class="font-medium">GDSF</span>: XFFM, XFBL, XFBT, XISP, XRSP, XFCT.
                </p>
            </div>

            <div class="flex items-center space-x-2">
                @if(Route::has('company.simple.anticipada.show') && is_object($voyage))
                    <a href="{{ route('company.simple.anticipada.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">Anticipada</a>
                @else
                    <span class="px-3 py-1.5 text-xs rounded border text-gray-400 bg-gray-50 cursor-not-allowed">Anticipada</span>
                @endif

                @if(Route::has('company.simple.micdta.show') && is_object($voyage))
                    <a href="{{ route('company.simple.micdta.show', $voyage) }}"
                       class="px-3 py-1.5 text-xs rounded border text-gray-700 hover:bg-gray-50">MIC/DTA</a>
                @else
                    <span class="px-3 py-1.5 text-xs rounded border text-gray-400 bg-gray-50 cursor-not-allowed">MIC/DTA</span>
                @endif

                <span class="px-3 py-1.5 text-xs rounded border text-gray-400 bg-gray-50 cursor-not-allowed">Desconsolidado</span>
                <span class="px-3 py-1.5 text-xs rounded border text-gray-400 bg-gray-50 cursor-not-allowed">Transbordo</span>

                <span class="px-3 py-1.5 text-xs rounded border border-emerald-500 text-emerald-700 bg-emerald-50">Paraguay</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Formulario de Envío --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Enviar a DNA Paraguay</h3>
                    <p class="text-sm text-gray-600 mb-5">
                        Completá los datos y pegá el XML del mensaje GDSF correspondiente. Podés adjuntar un PDF (opcional) para auditoría/documentación.
                    </p>

                    <form id="dnaForm" class="space-y-5" enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="codigo" class="block text-sm font-medium text-gray-700">Código</label>
                                <select id="codigo" name="codigo" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                                    <option value="" disabled selected>Seleccioná…</option>
                                    <option value="XFFM">XFFM – Carátula</option>
                                    <option value="XFBL">XFBL – Conocimientos</option>
                                    <option value="XFBT">XFBT – Hoja de ruta</option>
                                    <option value="XISP">XISP – Incluir embarcación</option>
                                    <option value="XRSP">XRSP – Retirar embarcación</option>
                                    <option value="XFCT">XFCT – Cerrar viaje</option>
                                </select>
                            </div>

                            <div>
                                <label for="version" class="block text-sm font-medium text-gray-700">Versión</label>
                                <input id="version" name="version" type="text" required placeholder="1.0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            </div>

                            <div>
                                <label for="viaje" class="block text-sm font-medium text-gray-700">Nro. Viaje (opcional)</label>
                                <input id="viaje" name="viaje" type="text" placeholder="p. ej. 15000TEMF000001C"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="idUsuario" class="block text-sm font-medium text-gray-700">idUsuario</label>
                                <input id="idUsuario" name="idUsuario" type="text" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            </div>
                            <div>
                                <label for="ticket" class="block text-sm font-medium text-gray-700">Ticket</label>
                                <input id="ticket" name="ticket" type="text" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            </div>
                            <div>
                                <label for="firma" class="block text-sm font-medium text-gray-700">Firma</label>
                                <input id="firma" name="firma" type="text" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="xml" class="block text-sm font-medium text-gray-700">XML</label>
                            <textarea id="xml" name="xml" rows="10" required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 font-mono text-xs"
                                      placeholder="Pegá aquí el XML completo del mensaje (XFFM/XFBL/XFBT/XISP/XRSP/XFCT)"></textarea>
                        </div>

                        {{-- ⬇️ NUEVO: Adjunto PDF opcional --}}
                        <div>
                            <label for="attachment" class="block text-sm font-medium text-gray-700">Adjuntar PDF (opcional)</label>
                            <input id="attachment" name="attachment" type="file" accept="application/pdf"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <p class="mt-1 text-xs text-gray-500">Formato permitido: PDF. Tamaño máx. 10 MB.</p>
                            <p id="attachmentInfo" class="mt-1 text-xs text-gray-600 hidden"></p>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <button id="btnSend" type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                    <svg id="spinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    Enviar a DNA
                                </button>
                                <button type="button" id="btnClear"
                                        class="px-4 py-2 border rounded-md text-xs text-gray-700 hover:bg-gray-50">
                                    Limpiar
                                </button>
                            </div>

                            @if(url()->previous())
                                <a href="{{ url()->previous() }}" class="text-xs text-gray-500 hover:text-gray-700">← Volver</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Resultado del envío --}}
            <div id="resultPanel" class="hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Resultado</h3>
                        <div class="flex items-center space-x-2">
                            <span id="pillStatus" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">—</span>
                            <button id="btnCopy" type="button" class="text-xs px-2 py-1 border rounded hover:bg-gray-50">Copiar XML</button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">XML de Respuesta (si el servidor lo devuelve)</label>
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

            {{-- Ayuda rápida --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 mb-2">Ayuda rápida</h4>
                    <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                        <li><span class="font-medium">Código</span> define el tipo de mensaje.</li>
                        <li><span class="font-medium">Versión</span> según el formulario.</li>
                        <li><span class="font-medium">Viaje</span> solo si aplica (rectificación, etc.).</li>
                        <li>Pegá el <span class="font-medium">XML</span> completo.</li>
                        <li>Completá <span class="font-medium">idUsuario</span>, <span class="font-medium">ticket</span> y <span class="font-medium">firma</span> de DNA.</li>
                        <li>Podés adjuntar un <span class="font-medium">PDF</span> (opcional) para documentación.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script>
        (function () {
            const form = document.getElementById('dnaForm');
            const btnSend = document.getElementById('btnSend');
            const btnClear = document.getElementById('btnClear');
            const spinner = document.getElementById('spinner');
            const resultPanel = document.getElementById('resultPanel');
            const pillStatus = document.getElementById('pillStatus');
            const responseXml = document.getElementById('responseXml');
            const rawResponse = document.getElementById('rawResponse');
            const btnCopy = document.getElementById('btnCopy');
            const attachment = document.getElementById('attachment');
            const attachmentInfo = document.getElementById('attachmentInfo');

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const sendUrl = "{{ $send_route }}";

            function setLoading(state) {
                if (state) {
                    spinner.classList.remove('hidden');
                    btnSend.setAttribute('disabled', 'disabled');
                    btnSend.classList.add('opacity-75', 'cursor-not-allowed');
                } else {
                    spinner.classList.add('hidden');
                    btnSend.removeAttribute('disabled');
                    btnSend.classList.remove('opacity-75', 'cursor-not-allowed');
                }
            }

            function setResult(statusText, xml, raw) {
                resultPanel.classList.remove('hidden');
                pillStatus.textContent = statusText;
                pillStatus.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' +
                    (statusText === 'success'
                        ? 'bg-green-100 text-green-800'
                        : statusText === 'error'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-yellow-100 text-yellow-800');

                responseXml.textContent = xml || '—';
                rawResponse.textContent = raw || '—';
            }

            function bytesToSize(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024, sizes = ['B','KB','MB','GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            attachment?.addEventListener('change', function () {
                const f = attachment.files?.[0];
                if (!f) {
                    attachmentInfo.classList.add('hidden');
                    attachmentInfo.textContent = '';
                    return;
                }
                if (f.type !== 'application/pdf') {
                    alert('Solo se permite PDF.');
                    attachment.value = '';
                    attachmentInfo.classList.add('hidden');
                    attachmentInfo.textContent = '';
                    return;
                }
                if (f.size > 10 * 1024 * 1024) { // 10 MB
                    alert('El PDF supera el tamaño máximo permitido (10 MB).');
                    attachment.value = '';
                    attachmentInfo.classList.add('hidden');
                    attachmentInfo.textContent = '';
                    return;
                }
                attachmentInfo.textContent = `Adjunto: ${f.name} (${bytesToSize(f.size)})`;
                attachmentInfo.classList.remove('hidden');
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const payload = {
                    codigo: document.getElementById('codigo').value.trim(),
                    version: document.getElementById('version').value.trim(),
                    xml: document.getElementById('xml').value,
                    viaje: document.getElementById('viaje').value.trim() || null,
                    idUsuario: document.getElementById('idUsuario').value.trim(),
                    ticket: document.getElementById('ticket').value.trim(),
                    firma: document.getElementById('firma').value.trim(),
                };

                if (!payload.codigo || !payload.version || !payload.xml || !payload.idUsuario || !payload.ticket || !payload.firma) {
                    alert('Completá los campos obligatorios.');
                    return;
                }

                // Usamos FormData para permitir multipart (PDF)
                const fd = new FormData();
                fd.append('_token', csrf);
                for (const [k, v] of Object.entries(payload)) {
                    if (v !== null && v !== undefined) fd.append(k, v);
                }
                const f = attachment?.files?.[0];
                if (f) fd.append('attachment', f);

                setLoading(true);
                try {
                    const resp = await fetch(sendUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            // NO seteamos Content-Type: el navegador lo define con boundary
                        },
                        body: fd
                    });

                    const data = await resp.json().catch(() => ({}));

                    const ok = !!(data && data.success);
                    const xml = data?.response_data?.parsed?.xml || data?.response_xml || null;
                    const raw = data?.response_data?.raw_response || data?.raw_response || null;

                    setResult(ok ? 'success' : 'error', xml, raw);
                    if (!ok && data?.error_message) {
                        alert('Error: ' + data.error_message);
                    }
                } catch (err) {
                    setResult('error', null, String(err));
                    alert('Error de comunicación.');
                } finally {
                    setLoading(false);
                }
            });

            btnClear.addEventListener('click', function () {
                form.reset();
                attachmentInfo.classList.add('hidden');
                attachmentInfo.textContent = '';
                resultPanel.classList.add('hidden');
                responseXml.textContent = '—';
                rawResponse.textContent = '—';
                pillStatus.textContent = '—';
                pillStatus.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800';
            });

            btnCopy.addEventListener('click', function () {
                const text = responseXml.textContent || '';
                if (!text || text === '—') {
                    alert('No hay XML para copiar.');
                    return;
                }
                navigator.clipboard.writeText(text).then(() => {
                    btnCopy.textContent = 'Copiado ✓';
                    setTimeout(() => btnCopy.textContent = 'Copiar XML', 1200);
                });
            });
        })();
    </script>
</x-app-layout>
