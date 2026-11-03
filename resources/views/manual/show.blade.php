<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-500" aria-label="Miga de pan">
            <a href="{{ route('manual.index') }}" class="hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">Manual</a>
            <span class="mx-1" aria-hidden="true">›</span>
            <a href="{{ route('manual.index', ['tema' => $articulo['tema']]) }}" class="hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">{{ $articulo['tema_nombre'] }}</a>
        </nav>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $articulo['titulo'] }}</h2>
            @if ($articulo['borrador'])
                <span class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Borrador</span>
            @endif
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <div class="min-w-0 space-y-6">
                <article class="bg-white shadow rounded-lg px-6 py-8 sm:px-10">
                    <div class="prose prose-gray max-w-none
                                prose-headings:font-semibold prose-headings:text-gray-900
                                prose-h2:text-lg prose-h2:mb-3 prose-h2:pb-2 prose-h2:border-b prose-h2:border-gray-100
                                prose-a:text-indigo-600 prose-a:font-medium prose-a:no-underline hover:prose-a:underline
                                prose-strong:text-gray-900
                                prose-ol:pl-6 prose-li:my-1.5 prose-li:marker:font-semibold prose-li:marker:text-indigo-600
                                prose-code:before:content-none prose-code:after:content-none">
                        {!! $articulo['html'] !!}
                    </div>
                </article>

                {{-- Pie: volver y vecinos del mismo tema --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <a href="{{ route('manual.index') }}"
                       x-data
                       x-init="if (document.referrer.startsWith(window.location.origin + '/manual') && new URL(document.referrer).pathname === '/manual') { $el.href = document.referrer }"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                        ← Volver al buscador
                    </a>
                    @if ($articulo['anterior'] || $articulo['siguiente'])
                        <div class="flex flex-col gap-2 sm:items-end">
                            @if ($articulo['anterior'])
                                <a href="{{ $articulo['anterior']['url'] }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                    ← {{ $articulo['anterior']['titulo'] }}
                                </a>
                            @endif
                            @if ($articulo['siguiente'])
                                <a href="{{ $articulo['siguiente']['url'] }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
                                    {{ $articulo['siguiente']['titulo'] }} →
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($articulo['secciones'] !== [] || $articulo['relacionados'] !== [])
                <aside class="space-y-6 lg:sticky lg:top-6 self-start">
                    @if ($articulo['secciones'] !== [])
                        <nav class="bg-white overflow-hidden shadow rounded-lg px-4 py-5" aria-labelledby="manual-en-esta-pagina">
                            <h3 id="manual-en-esta-pagina" class="text-sm font-medium text-gray-900">En esta página</h3>
                            <ul class="mt-3 space-y-2">
                                @foreach ($articulo['secciones'] as $seccion)
                                    <li>
                                        <a href="#{{ $seccion['id'] }}" class="text-sm text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">{{ $seccion['titulo'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif

                    @if ($articulo['relacionados'] !== [])
                        <div class="bg-white overflow-hidden shadow rounded-lg px-4 py-5">
                            <h3 class="text-sm font-medium text-gray-900">Relacionados</h3>
                            <ul class="mt-3 space-y-3">
                                @foreach ($articulo['relacionados'] as $relacionado)
                                    <li>
                                        <a href="{{ $relacionado['url'] }}" class="block text-sm text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">{{ $relacionado['titulo'] }}</a>
                                        <span class="text-xs text-gray-500">{{ $relacionado['tema_nombre'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </aside>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.title = @js('Manual — '.$articulo['titulo']);
        </script>
    @endpush
</x-app-layout>
