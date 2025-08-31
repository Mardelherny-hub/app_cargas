<div>
    <div class="py-6">
    <form wire:submit.prevent="save">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Alertas --}}
            @if (session()->has('success'))
                <div class="rounded-md bg-green-50 p-3 text-green-700 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Card principal --}}
            <div class="bg-white shadow-sm rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ __('Nuevo Tipo de Carga') }}
                    </h3>
                    <a href="{{ route('admin.cargo-types.index') }}"
                       class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                        ← {{ __('Volver') }}
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($columns as $col)
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

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $label }}
                                @if($isReq) <span class="text-red-600">*</span> @endif
                            </label>

                            {{-- Boolean (checkbox) --}}
                            @if ($isBool)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model="form.{{ $col }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-600">Sí / No</span>
                                </label>

                            {{-- Enum (select) --}}
                            @elseif ($isEn)
                                <select wire:model.defer="form.{{ $col }}"
                                        @required($isReq)
                                        class="w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="">{{ __('Seleccione…') }}</option>
                                    @foreach (($enumOptions[$col] ?? []) as $opt)
                                        <option value="{{ $opt }}">{{ \Illuminate\Support\Str::of($opt)->replace('_',' ')->title() }}</option>
                                    @endforeach
                                </select>

                            {{-- JSON (textarea) --}}
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

                            {{-- Color (heurística) --}}
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
            </div>

            {{-- Botonera --}}
            <div class="bg-white shadow-sm rounded-xl p-4 flex items-center justify-end gap-2">
                <a href="{{ route('admin.cargo-types.index') }}"
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

</div>
