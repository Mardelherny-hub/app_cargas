@php
    $emojis = [
        'primeros-pasos' => '🚀',
        'administracion' => '🛠️',
        'mi-empresa' => '🏢',
        'datos-maestros' => '🗂️',
        'viajes-y-cargas' => '🚢',
        'importar-manifiestos' => '📥',
        'afip-argentina' => '🇦🇷',
        'paraguay-dna' => '🇵🇾',
        'afip-otros-roles' => '📦',
        'reportes' => '📊',
        'preguntas-frecuentes' => '❓',
    ];
    $articulosPorTema = collect($articulos)->groupBy('tema');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manual de usuario</h2>
        <p class="mt-1 text-sm text-gray-500">Guías paso a paso para usar el sistema.</p>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
        @if ($articulos === [])
            <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5 sm:p-6 text-center">
                <p class="text-sm text-gray-600">No hay artículos disponibles para su usuario.</p>
            </div>
        @else
            <div x-data='manualBuscador(@json($articulos), @json($sinonimos), @json(array_keys($temas)))'
                 @keydown="teclaEnBuscador($event)"
                 @keydown.window="teclaGlobal($event)"
                 class="space-y-6">

                {{-- Buscador --}}
                <div class="bg-white shadow rounded-lg px-4 py-5 sm:p-6">
                    <label for="manual-busqueda" class="sr-only">Buscar en el manual</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="M21 21l-4.35-4.35"></path>
                            </svg>
                        </div>
                        <input id="manual-busqueda"
                               x-ref="campo"
                               x-model="consulta"
                               @input="alEscribir()"
                               type="text"
                               autofocus
                               autocomplete="off"
                               placeholder="Buscar: MIC/DTA, anular…"
                               class="block w-full text-base py-3 pl-11 pr-4 sm:pr-14 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <div class="absolute inset-y-0 right-0 pr-3 hidden sm:flex items-center pointer-events-none">
                            <kbd class="inline-flex items-center px-2 py-1 border border-gray-200 rounded text-xs font-medium bg-gray-50 text-gray-500">/</kbd>
                        </div>
                    </div>

                    {{-- Filtro por tema --}}
                    <div class="mt-4 -mx-1 px-1 py-1 flex gap-2 overflow-x-auto sm:flex-wrap" role="group" aria-label="Filtrar por tema">
                        <button type="button"
                                @click="elegirTema(null)"
                                :aria-pressed="tema === null"
                                :class="(tema === null ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') + (hayBusqueda && cantidadEnTema(null) === 0 ? ' opacity-50' : '')"
                                class="inline-flex items-center shrink-0 whitespace-nowrap min-h-10 sm:min-h-0 px-3 py-1 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Todos&nbsp;<span x-text="cantidadEnTema(null)">{{ count($articulos) }}</span>
                        </button>
                        @foreach ($temas as $clave => $nombre)
                            <button type="button"
                                    @click="elegirTema(@js($clave))"
                                    :aria-pressed="tema === @js($clave)"
                                    :class="(tema === @js($clave) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200') + (hayBusqueda && cantidadEnTema(@js($clave)) === 0 ? ' opacity-50' : '')"
                                    class="inline-flex items-center shrink-0 whitespace-nowrap min-h-10 sm:min-h-0 px-3 py-1 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                {{ $nombre }}&nbsp;<span x-text="cantidadEnTema(@js($clave))">{{ $articulosPorTema[$clave]->count() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Sin búsqueda: temas --}}
                <div x-show="!hayBusqueda" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($temas as $clave => $nombre)
                        @php $delTema = $articulosPorTema[$clave]; @endphp
                        <div x-show="tema === null || tema === @js($clave)" class="bg-white overflow-hidden shadow rounded-lg flex flex-col">
                            <div class="px-4 py-5 sm:p-6 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <span aria-hidden="true">{{ $emojis[$clave] ?? '' }}</span> {{ $nombre }}
                                </h3>
                                <p class="text-sm text-gray-500">{{ $delTema->count() }} {{ $delTema->count() === 1 ? 'artículo' : 'artículos' }}</p>
                                <ul class="mt-4 space-y-2">
                                    @foreach ($delTema as $indice => $articulo)
                                        <li @if ($indice >= 5) x-cloak x-show="tema === @js($clave)" @endif>
                                            <a href="{{ $articulo['url'] }}" class="text-sm text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">{{ $articulo['titulo'] }}</a>
                                            @if ($articulo['borrador'])
                                                <span class="ml-1 text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Borrador</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            @if ($delTema->count() > 5)
                                <div x-show="tema !== @js($clave)" class="bg-gray-50 px-5 py-3">
                                    <button type="button" @click="elegirTema(@js($clave))" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                        Ver los {{ $delTema->count() }} artículos →
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Con búsqueda: resultados --}}
                <div x-cloak x-show="hayBusqueda && resultados.length > 0" class="space-y-3">
                    <p class="text-sm text-gray-500" aria-live="polite">
                        <strong x-text="resultados.length"></strong>
                        <span x-text="resultados.length === 1 ? 'resultado' : 'resultados'"></span>
                        para «<span x-text="consulta.trim()"></span>»
                    </p>
                    <div class="bg-white overflow-hidden shadow rounded-lg p-2">
                        <ul class="space-y-1">
                            <template x-for="(resultado, posicion) in resultados" :key="resultado.url">
                                <li>
                                    <a :href="resultado.url"
                                       :data-posicion="posicion"
                                       :class="posicion === activo ? 'bg-gray-50 ring-1 ring-indigo-200' : ''"
                                       class="flex items-start gap-3 w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium text-gray-900" x-html="resultado.tituloMarcado"></span>
                                            <span class="mt-1 flex flex-wrap items-center gap-2">
                                                <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700" x-text="resultado.tema_nombre"></span>
                                                <span x-show="resultado.borrador" class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Borrador</span>
                                            </span>
                                            <span class="mt-1 text-sm text-gray-600 line-clamp-2" x-html="resultado.fragmento"></span>
                                        </span>
                                        <span class="self-center text-gray-400 opacity-0 group-hover:opacity-100 transition-colors" aria-hidden="true">→</span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                {{-- Sin resultados --}}
                <div x-cloak x-show="hayBusqueda && resultados.length === 0" class="bg-white shadow rounded-lg text-center py-12 px-4">
                    <svg class="mx-auto w-10 h-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <p class="mt-4 text-lg font-medium text-gray-900" aria-live="polite">No encontramos artículos para «<span x-text="consulta.trim()"></span>»</p>
                    <p class="mt-2 text-sm text-gray-600">Revise la ortografía o use menos palabras.</p>
                    <p class="mt-1 text-sm text-gray-600">Pruebe con otro nombre: por ejemplo, <em>título</em> en lugar de <em>conocimiento</em>.</p>
                    <button type="button" @click="limpiar()" class="mt-6 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Ver todos los temas
                    </button>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            function manualBuscador(articulos, sinonimos, temas) {
                const PESO_TITULO = 100;
                const PESO_CLAVES = 10;
                const PESO_TEMA = 5;
                const PESO_TEXTO = 1;
                const LARGO_PALABRA_CORTA = 3;
                const LARGO_FRAGMENTO = 180;
                const MARCA = '<mark class="bg-yellow-100 text-gray-900 rounded px-0.5">';

                // Cada carácter da uno solo: así las posiciones coinciden con el texto original.
                const normalizar = (texto) => {
                    let salida = '';
                    for (const unidad of texto.split('')) {
                        const base = unidad.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                        salida += base.length === 1 ? base : unidad;
                    }
                    return salida;
                };

                const escapar = (texto) => texto.replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

                const esLetraONumero = (c) => c !== undefined && /[\p{L}\p{N}]/u.test(c);

                // Posiciones [inicio, fin] de un término; los sinónimos cortos solo valen como palabra completa.
                const posiciones = (texto, alternativa) => {
                    const encontradas = [];
                    let desde = texto.indexOf(alternativa.termino);
                    while (desde !== -1) {
                        const fin = desde + alternativa.termino.length;
                        if (!alternativa.palabraCompleta || (!esLetraONumero(texto[desde - 1]) && !esLetraONumero(texto[fin]))) {
                            encontradas.push([desde, fin]);
                        }
                        desde = texto.indexOf(alternativa.termino, desde + 1);
                    }
                    return encontradas;
                };

                const terminosDeSinonimos = sinonimos
                    .map((grupo) => grupo.map((termino) => normalizar(String(termino))))
                    .flatMap((grupo) => grupo.map((termino) => ({ termino, grupo })))
                    .sort((a, b) => b.termino.length - a.termino.length);

                // Cada concepto debe aparecer; un concepto es una palabra o un grupo de sinónimos.
                const conceptos = (consulta) => {
                    let resto = ` ${consulta} `;
                    const lista = [];
                    for (const { termino, grupo } of terminosDeSinonimos) {
                        const hallados = posiciones(resto, { termino, palabraCompleta: true });
                        if (hallados.length > 0) {
                            lista.push(grupo.map((otro) => ({
                                termino: otro,
                                palabraCompleta: otro !== termino && otro.length <= LARGO_PALABRA_CORTA,
                            })));
                            const [inicio, fin] = hallados[0];
                            resto = `${resto.slice(0, inicio)} ${resto.slice(fin)}`;
                        }
                    }
                    for (const palabra of resto.split(/\s+/).filter(Boolean)) {
                        lista.push([{ termino: palabra, palabraCompleta: false }]);
                    }
                    return lista;
                };

                const marcar = (original, normalizado, alternativas) => {
                    const rangos = alternativas
                        .flatMap((alternativa) => posiciones(normalizado, alternativa))
                        .sort((a, b) => a[0] - b[0]);
                    let salida = '';
                    let cursor = 0;
                    for (const [inicio, fin] of rangos) {
                        if (fin <= cursor) {
                            continue;
                        }
                        const desde = Math.max(inicio, cursor);
                        salida += escapar(original.slice(cursor, desde)) + MARCA + escapar(original.slice(desde, fin)) + '</mark>';
                        cursor = fin;
                    }
                    return salida + escapar(original.slice(cursor));
                };

                const fragmento = (articulo, alternativas) => {
                    const coincidencias = alternativas.flatMap((a) => posiciones(articulo.textoNormal, a)).map((r) => r[0]);
                    const primera = coincidencias.length > 0 ? Math.min(...coincidencias) : 0;
                    let inicio = Math.max(0, primera - 60);
                    if (inicio > 0) {
                        const espacio = articulo.texto.indexOf(' ', inicio);
                        inicio = espacio !== -1 && espacio < primera ? espacio + 1 : inicio;
                    }
                    const fin = Math.min(articulo.texto.length, inicio + LARGO_FRAGMENTO);
                    return (inicio > 0 ? '…' : '')
                        + marcar(articulo.texto.slice(inicio, fin), articulo.textoNormal.slice(inicio, fin), alternativas)
                        + (fin < articulo.texto.length ? '…' : '');
                };

                const indexados = articulos.map((articulo, orden) => ({
                    ...articulo,
                    orden,
                    tituloNormal: normalizar(articulo.titulo),
                    clavesNormal: normalizar(articulo.palabras_clave.join(' · ')),
                    temaNormal: normalizar(articulo.tema_nombre),
                    textoNormal: normalizar(articulo.texto),
                }));

                const puntaje = (articulo, concepto) => {
                    const aparece = (campo) => concepto.some((alternativa) => posiciones(campo, alternativa).length > 0);
                    if (aparece(articulo.tituloNormal)) return PESO_TITULO;
                    if (aparece(articulo.clavesNormal)) return PESO_CLAVES;
                    if (aparece(articulo.temaNormal)) return PESO_TEMA;
                    if (aparece(articulo.textoNormal)) return PESO_TEXTO;
                    return 0;
                };

                return {
                    consulta: '',
                    tema: null,
                    activo: -1,

                    init() {
                        document.title = 'Manual de usuario';
                        const parametros = new URLSearchParams(window.location.search);
                        this.consulta = parametros.get('q') ?? '';
                        this.tema = temas.includes(parametros.get('tema')) ? parametros.get('tema') : null;
                        this.$nextTick(() => this.$refs.campo.focus());
                    },

                    get consultaNormal() {
                        return normalizar(this.consulta).trim().replace(/\s+/g, ' ');
                    },

                    get hayBusqueda() {
                        return this.consultaNormal !== '';
                    },

                    get coincidencias() {
                        if (!this.hayBusqueda) {
                            return [];
                        }
                        const lista = conceptos(this.consultaNormal);
                        const alternativas = lista.flat();
                        return indexados
                            .map((articulo) => {
                                const puntos = lista.map((concepto) => puntaje(articulo, concepto));
                                return { articulo, total: puntos.includes(0) ? 0 : puntos.reduce((a, b) => a + b, 0) };
                            })
                            .filter((fila) => fila.total > 0)
                            .sort((a, b) => b.total - a.total || a.articulo.orden - b.articulo.orden)
                            .map(({ articulo }) => ({
                                ...articulo,
                                tituloMarcado: marcar(articulo.titulo, articulo.tituloNormal, alternativas),
                                fragmento: fragmento(articulo, alternativas),
                            }));
                    },

                    get resultados() {
                        return this.tema === null
                            ? this.coincidencias
                            : this.coincidencias.filter((articulo) => articulo.tema === this.tema);
                    },

                    cantidadEnTema(clave) {
                        const base = this.hayBusqueda ? this.coincidencias : indexados;
                        return clave === null ? base.length : base.filter((articulo) => articulo.tema === clave).length;
                    },

                    alEscribir() {
                        this.activo = -1;
                        this.guardarEnUrl();
                    },

                    elegirTema(clave) {
                        this.tema = clave;
                        this.activo = -1;
                        this.guardarEnUrl();
                    },

                    limpiar() {
                        this.consulta = '';
                        this.tema = null;
                        this.activo = -1;
                        this.guardarEnUrl();
                        this.$refs.campo.focus();
                    },

                    guardarEnUrl() {
                        const parametros = new URLSearchParams();
                        if (this.consulta.trim() !== '') {
                            parametros.set('q', this.consulta.trim());
                        }
                        if (this.tema !== null) {
                            parametros.set('tema', this.tema);
                        }
                        const consulta = parametros.toString();
                        history.replaceState(null, '', window.location.pathname + (consulta ? `?${consulta}` : ''));
                    },

                    teclaGlobal(evento) {
                        const destino = evento.target;
                        const escribiendo = destino.closest && destino.closest('input, textarea, select, [contenteditable="true"]');
                        if (evento.key === '/' && !escribiendo && !evento.ctrlKey && !evento.metaKey && !evento.altKey) {
                            evento.preventDefault();
                            this.$refs.campo.focus();
                        }
                        if (evento.key === 'Escape' && this.consulta !== '' && (!escribiendo || destino === this.$refs.campo)) {
                            this.consulta = '';
                            this.alEscribir();
                            this.$refs.campo.focus();
                        }
                    },

                    teclaEnBuscador(evento) {
                        if (!this.hayBusqueda || this.resultados.length === 0) {
                            return;
                        }
                        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                            evento.preventDefault();
                            const total = this.resultados.length;
                            const paso = evento.key === 'ArrowDown' ? 1 : -1;
                            this.activo = this.activo === -1 && paso === -1 ? total - 1 : (this.activo + paso + total) % total;
                            this.$nextTick(() => {
                                const enlace = this.$el.querySelector(`a[data-posicion="${this.activo}"]`);
                                if (enlace) {
                                    enlace.scrollIntoView({ block: 'nearest' });
                                    if (document.activeElement !== this.$refs.campo) {
                                        enlace.focus();
                                    }
                                }
                            });
                        }
                        if (evento.key === 'Enter' && evento.target === this.$refs.campo) {
                            evento.preventDefault();
                            const elegido = this.resultados[Math.max(this.activo, 0)];
                            window.location.href = elegido.url;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
