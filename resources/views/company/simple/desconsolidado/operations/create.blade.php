{{-- ATA-DESC: inicio de expediente. Solo datos que la desconsolidadora recibe de Aduana. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                   class="hover:text-indigo-700 focus-visible:underline">Mis desconsolidaciones</a>
                <span aria-hidden="true">/</span>
                <span class="text-slate-800">Nueva operación</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Nueva desconsolidación</h1>
            <p class="mt-1 text-sm text-slate-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
            <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
               class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
                Volver a mis desconsolidaciones
            </a>

            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 sm:p-5">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.8 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                        <div>
                            <p class="font-bold">Hay datos que necesitamos revisar</p>
                            <p class="mt-1 text-red-700">Corregilos en el formulario. Lo que escribiste se mantiene.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <section aria-labelledby="operation-heading" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:col-span-8">
                    <div class="border-b border-slate-100 px-5 pb-5 pt-6 sm:px-8 sm:pt-8">
                        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">
                            <span class="h-2 w-2 rounded-full bg-indigo-600" aria-hidden="true"></span>
                            PASO 1 · IDENTIFICAR EL TÍTULO MADRE
                        </div>
                        <h2 id="operation-heading" class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Completá las referencias de Aduana
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                            Para empezar solo necesitás tres datos. No se solicitan el barco, el capitán ni los datos del viaje de la transportista.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('company.simple.desconsolidado.operations.store') }}"
                          class="space-y-7 p-5 sm:p-8">
                        @csrf

                        <div class="flex items-start gap-4">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">01</span>
                            <div class="min-w-0 flex-1">
                                <label for="argentina_voyage_id" class="block text-sm font-bold text-slate-900">
                                    Identificador de viaje AFIP <span class="text-red-600">*</span>
                                </label>
                                <p id="voyage-help" class="mt-1 text-sm leading-5 text-slate-500">
                                    Es el identificador que Aduana asignó al viaje. No es el número interno de la transportista.
                                </p>
                                <input id="argentina_voyage_id" name="argentina_voyage_id" type="text"
                                       value="{{ old('argentina_voyage_id') }}" required maxlength="16"
                                       autocomplete="off" spellcheck="false"
                                       aria-describedby="voyage-help{{ $errors->has('argentina_voyage_id') ? ' voyage-error' : '' }}"
                                       aria-invalid="{{ $errors->has('argentina_voyage_id') ? 'true' : 'false' }}"
                                       placeholder="Ingresá el identificador de Aduana"
                                       class="mt-3 block min-h-12 w-full rounded-xl border px-4 py-3 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-600 focus:ring-indigo-500 {{ $errors->has('argentina_voyage_id') ? 'border-red-400 bg-red-50/30' : 'border-slate-300 bg-white' }}"/>
                                @error('argentina_voyage_id')
                                    <p id="voyage-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-slate-500">Hasta 16 caracteres. Copialo como figura en Aduana.</p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100"></div>

                        <div class="flex items-start gap-4">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">02</span>
                            <div class="min-w-0 flex-1">
                                <label for="master_loading_port_code" class="block text-sm font-bold text-slate-900">
                                    Código del puerto de embarque <span class="text-red-600">*</span>
                                </label>
                                <p id="port-help" class="mt-1 text-sm leading-5 text-slate-500">
                                    El puerto de embarque del <strong>conocimiento madre</strong>, no el puerto de descarga de los conocimientos hijos.
                                </p>
                                <input id="master_loading_port_code" name="master_loading_port_code" type="text"
                                       value="{{ old('master_loading_port_code') }}" required maxlength="5" minlength="5"
                                       pattern="[A-Za-z0-9]{5}" autocapitalize="characters" autocomplete="off" spellcheck="false"
                                       aria-describedby="port-help{{ $errors->has('master_loading_port_code') ? ' port-error' : '' }}"
                                       aria-invalid="{{ $errors->has('master_loading_port_code') ? 'true' : 'false' }}"
                                       placeholder="Ej.: ARBUE"
                                       class="mt-3 block min-h-12 w-full rounded-xl border px-4 py-3 font-mono text-base uppercase tracking-widest text-slate-900 shadow-sm placeholder:font-sans placeholder:normal-case placeholder:tracking-normal placeholder:text-slate-400 focus:border-indigo-600 focus:ring-indigo-500 {{ $errors->has('master_loading_port_code') ? 'border-red-400 bg-red-50/30' : 'border-slate-300 bg-white' }}"/>
                                @error('master_loading_port_code')
                                    <p id="port-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-slate-500">5 letras o números. Se guardará en mayúsculas.</p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100"></div>

                        <div class="flex items-start gap-4">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">03</span>
                            <div class="min-w-0 flex-1">
                                <label for="master_bill_number" class="block text-sm font-bold text-slate-900">
                                    Número del conocimiento madre <span class="text-red-600">*</span>
                                </label>
                                <p id="master-help" class="mt-1 text-sm leading-5 text-slate-500">
                                    Copiá el número del título de transporte que se va a desconsolidar.
                                </p>
                                <input id="master_bill_number" name="master_bill_number" type="text"
                                       value="{{ old('master_bill_number') }}" required maxlength="18"
                                       autocomplete="off" spellcheck="false"
                                       aria-describedby="master-help{{ $errors->has('master_bill_number') ? ' master-error' : '' }}"
                                       aria-invalid="{{ $errors->has('master_bill_number') ? 'true' : 'false' }}"
                                       placeholder="Número del conocimiento madre"
                                       class="mt-3 block min-h-12 w-full rounded-xl border px-4 py-3 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-600 focus:ring-indigo-500 {{ $errors->has('master_bill_number') ? 'border-red-400 bg-red-50/30' : 'border-slate-300 bg-white' }}"/>
                                @error('master_bill_number')
                                    <p id="master-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs text-slate-500">Hasta 18 caracteres. Conservá letras, números y separadores del documento.</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 sm:p-5">
                            <div class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5m0 4h.01M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/>
                                </svg>
                                <div class="text-sm leading-6 text-indigo-950">
                                    <p class="font-bold">Guardar no envía información a Aduana.</p>
                                    <p class="mt-1 text-indigo-800">Primero se crea o se recupera tu expediente. Después podrás cargar los conocimientos hijos y revisar los datos antes de transmitir.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                               class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Cancelar y volver
                            </a>
                            <button type="submit"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Guardar y continuar
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </section>

                <aside aria-label="Guía de la operación" class="space-y-5 lg:col-span-4">
                    <section class="overflow-hidden rounded-3xl bg-slate-900 p-5 text-white shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-300">Tu operación, paso a paso</p>
                        <h2 class="mt-2 text-xl font-bold">¿Qué pasa después?</h2>
                        <ol class="mt-6 space-y-5">
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-500 text-sm font-bold text-white">1</span>
                                <div>
                                    <p class="text-sm font-bold">Identificar el título madre</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-300">Estás en este paso. Guardá las tres referencias.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-sm font-semibold text-slate-200">2</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Agregar conocimientos hijos</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-300">Cargá los datos de cada conocimiento.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-sm font-semibold text-slate-200">3</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Revisar la información</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-300">Controlá los datos y corregí los faltantes.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-sm font-semibold text-slate-200">4</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Transmitir a Aduana</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-300">El envío se hace únicamente cuando los datos estén listos.</p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 class="text-sm font-bold text-slate-900">¿Ya habías empezado esta operación?</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Podés ingresar las mismas referencias. Si el expediente ya existe para tu empresa, el sistema lo abrirá sin crear uno duplicado.
                        </p>
                        <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                           class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-700 hover:text-indigo-900 focus-visible:underline">
                            Ver mis operaciones guardadas
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
