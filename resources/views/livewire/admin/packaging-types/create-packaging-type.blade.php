<div>
    <div class="py-6"
     x-data="packagingCreate({
        groups: {
            @php
                // Agrupación automática por patrones (no asumimos campos fijos)
                $defs = [
                    'dim' => ['title' => 'Dimensiones / Capacidad', 'match' => '/(length|width|height|size|volume|capacity|mm|cm|inch|feet|m3)/i'],
                    'mat' => ['title' => 'Materiales', 'match' => '/(material|wall|roof|floor|lining|coating)/i'],
                    'ops' => ['title' => 'Operación & Manejo', 'match' => '/(handle|stack|pallet|load|unload|equipment|seal|fragile|liquid|powder|bulk)/i'],
                    'reg' => ['title' => 'Regulación & Aduana', 'match' => '/(permit|regulat|compliance|danger|hazard|imo|un_|packing_group|hs_code|customs|aduana)/i'],
                    'ws'  => ['title' => 'Códigos / WS', 'match' => '/(iso|code|ws|argentina|paraguay|customs)/i'],
                    'env' => ['title' => 'Ambiental', 'match' => '/(eco|environment|carbon|footprint|recycle|reusable)/i'],
                    'vis' => ['title' => 'Visual & Orden', 'match' => '/(icon|color|display|order)/i'],
                    'gen' => ['title' => 'General / Otros', 'match' => null],
                ];
                $groups = [];
                foreach ($defs as $k => $d) { $groups[$k] = ['title' => $d['title'], 'cols' => []]; }
                foreach ($columns as $c) {
                    $placed = false;
                    foreach ($defs as $k => $d) {
                        if ($d['match'] && preg_match($d['match'], $c)) { $groups[$k]['cols'][] = $c; $placed = true; break; }
                    }
                    if (!$placed) { $groups['gen']['cols'][] = $c; }
                }
                $groups = array_filter($groups, fn($g) => count($g['cols']) > 0);
            @endphp
            @foreach ($groups as $k => $g)
                '{{ $k }}': @json($g['cols'])@if(!$loop->last),@endif
            @endforeach
        },
        required: @json($requiredColumns ?? []),
     })"
>
    <form wire:submit.prevent="save">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Encabezado --}}
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('Nuevo Tipo de Packaging') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Completa los campos necesarios. Podés filtrar o mostrar solo los obligatorios.') }}</p>
                </div>
                <a href="{{ route('admin.packaging-types.index') }}"
                   class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                    ← {{ __('Volver') }}
                </a>
            </div>

            {{-- Barra de control: búsqueda + solo obligatorios --}}
            <div class="bg-white shadow-sm rounded-xl p-4 flex flex-col sm:flex-row gap-3 sm:items-center">
                <div class="relative flex-1">
                    <input type="text" x-model="q" placeholder="Buscar campo por nombre…"
                           class="w-full rounded-full border-gray-300 pl-10 pr-10 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <button type="button" x-show="q" x-on:click="q=''"
                            class="absolute inset-y-0 right-0 pr-3 text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <label class="inline-flex items-center gap-2 select-none">
                    <input type="checkbox" x-model="onlyReq"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">{{ __('Mostrar solo obligatorios') }}</span>
                </label>
            </div>

            {{-- Secciones / grupos --}}
            <div class="bg-white shadow-sm rounded-xl p-2 sm:p-4">
                @foreach ($groups as $key => $g)
                    <section class="border border-gray-100 rounded-lg mb-3">
                        <button type="button"
                                class="w-full flex items-center justify-between px-4 py-3"
                                x-on:click="open['{{ $key }}'] = !open['{{ $key }}']">
                            <span class="font-medium text-gray-800">{{ $g['title'] }}</span>
                            <span class="inline-flex items-center gap-2 text-sm text-gray-500">
                                <span class="px-2 py-0.5 rounded-full bg-gray-100"
                                      x-text="countInGroup(groups['{{ $key }}'])"></span>
                                <svg :class="open['{{ $key }}'] ? 'rotate-180' : ''" class="h-4 w-4 text-gray-500 transition-transform"
                                     viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.177l3.71-3.946a.75.75 0 111.08 1.04l-4.25 4.52a.75.75 0 01-1.08 0l-4.25-4.52a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>

                        <div class="px-4 pb-4" x-show="open['{{ $key }}']" x-cloak>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($g['cols'] as $col)
                                    @php
                                        $label = \Illuminate\Support\Str::of($col)->replace('_',' ')->title();
                                        $isReq = in_array($col, $requiredColumns ?? [], true);
                                        $isBool = $isBoolean[$col] ?? false;
                                        $isDateF = $isDate[$col] ?? false;
                                        $isNum = $isNumeric[$col] ?? false;
                                        $isJs = $isJson[$col] ?? false;
                                        $isEn = $isEnum[$col] ?? false;
                                        $looksLongText = preg_match('/desc|note|instruction|observ|comment/i', $col);
                                        $isColor = preg_match('/color(_code)?$/i', $col);
                                    @endphp

                                    <div x-show="show('{{ $col }}')">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ $label }}
                                            @if($isReq) <span class="text-red-600">*</span> @endif
                                        </label>

                                        {{-- Boolean --}}
                                        @if ($isBool)
                                            <label class="inline-flex items-center gap-2">
                                                <input type="checkbox" wire:model="form.{{ $col }}"
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-sm text-gray-600">Sí / No</span>
                                            </label>

                                        {{-- Enum --}}
                                        @elseif ($isEn)
                                            <select wire:model.defer="form.{{ $col }}"
                                                    @required($isReq)
                                                    class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                                <option value="">{{ __('Seleccione…') }}</option>
                                                @foreach (($enumOptions[$col] ?? []) as $opt)
                                                    <option value="{{ $opt }}">{{ \Illuminate\Support\Str::of($opt)->replace('_',' ')->title() }}</option>
                                                @endforeach
                                            </select>

                                        {{-- JSON --}}
                                        @elseif ($isJs)
                                            <textarea rows="4" wire:model.defer="form.{{ $col }}"
                                                      @required($isReq)
                                                      class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                      placeholder='{"key":"value"}'></textarea>
                                            <p class="mt-1 text-xs text-gray-500">Pegá un JSON válido (se valida al guardar).</p>

                                        {{-- Fecha --}}
                                        @elseif ($isDateF)
                                            <input type="date" wire:model.defer="form.{{ $col }}"
                                                   @required($isReq)
                                                   class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm">

                                        {{-- Numérico --}}
                                        @elseif ($isNum)
                                            <input type="number" step="any" wire:model.defer="form.{{ $col }}"
                                                   @required($isReq)
                                                   class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                   placeholder="0">

                                        {{-- Color --}}
                                        @elseif ($isColor)
                                            <div class="flex items-center gap-2">
                                                <input type="text" wire:model.defer="form.{{ $col }}"
                                                       @required($isReq)
                                                       class="flex-1 rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                       placeholder="#000000">
                                                <input type="color"
                                                       x-on:input="$wire.set('form.{{ $col }}', $event.target.value)"
                                                       value="{{ $form[$col] ?? '#000000' }}"
                                                       class="h-9 w-10 rounded border cursor-pointer"/>
                                            </div>

                                        {{-- Texto largo --}}
                                        @elseif ($looksLongText)
                                            <textarea rows="3" wire:model.defer="form.{{ $col }}"
                                                      @required($isReq)
                                                      class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                      placeholder="—"></textarea>

                                        {{-- Texto genérico --}}
                                        @else
                                            <input type="text" wire:model.defer="form.{{ $col }}"
                                                   @required($isReq)
                                                   class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                   placeholder="—">
                                        @endif

                                        @error("form.$col")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror

                                        <p class="mt-1 text-xs text-gray-400">Campo: <code>{{ $col }}</code></p>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Estado vacío por filtro --}}
                            <div class="text-center py-8 text-gray-500" x-show="countInGroup(groups['{{ $key }}'])===0">
                                {{ __('No hay campos para mostrar con el filtro actual.') }}
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>

            {{-- Botonera --}}
            <div class="bg-white shadow-sm rounded-xl p-4 flex items-center justify-end gap-2">
                <a href="{{ route('admin.packaging-types.index') }}"
                   class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">
                    {{ __('Cancelar') }}
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    {{ __('Guardar') }}
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Alpine helpers --}}
<script>
function packagingCreate(init){
    return {
        q: '',
        onlyReq: false,
        groups: init.groups || {},
        required: init.required || [],
        open: {},
        init() {
            for (const k of Object.keys(this.groups)) this.open[k] = true;
        },
        isReq(col) {
            return this.required.includes(col);
        },
        matches(col) {
            if (!this.q) return true;
            return col.toLowerCase().includes(this.q.toLowerCase());
        },
        show(col) {
            return this.matches(col) && (!this.onlyReq || this.isReq(col));
        },
        countInGroup(cols) {
            return (cols || []).filter(c => this.show(c)).length;
        }
    }
}
</script>

</div>
