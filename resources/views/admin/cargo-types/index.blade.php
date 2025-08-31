{{-- resources/views/admin/cargo-types/index.blade.php --}}
<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Tipos de Carga') }}
                </h2>
                @if(($q ?? '') !== '')
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ __('Mostrando resultados para:') }} <span class="font-medium">“{{ $q }}”</span>
                    </p>
                @endif
            </div>

            <a href="{{ route('admin.cargo-types.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Nuevo') }}
            </a>
        </div>
    </x-slot>

    {{-- Content --}}
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Alerts --}}
            @if (session('success'))
                <div class="rounded-lg bg-green-50 p-3 text-green-700 text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-lg bg-red-50 p-3 text-red-700 text-sm">{{ session('error') }}</div>
            @endif

            {{-- Toolbar --}}
            <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4">
                <form method="GET" action="{{ route('admin.cargo-types.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por cualquier campo…"
                               class="w-full rounded-full border-gray-300 pl-10 pr-10 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                        </div>
                        @if(($q ?? '') !== '')
                            <a href="{{ route('admin.cargo-types.index') }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                               title="Limpiar búsqueda">
                                ✕
                            </a>
                        @endif
                    </div>
                    <button type="submit"
                            class="px-4 py-2.5 rounded-full bg-gray-900 text-white text-sm font-medium hover:bg-black">
                        {{ __('Buscar') }}
                    </button>
                </form>
            </div>

            {{-- Table Card --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-gray-600">
                                <th class="px-3 sm:px-4 py-2.5 font-semibold">ID</th>
                                @foreach ($columns as $col)
                                    <th class="px-3 sm:px-4 py-2.5 font-semibold">
                                        {{ \Illuminate\Support\Str::of($col)->replace('_', ' ')->title() }}
                                    </th>
                                @endforeach
                                <th class="px-3 sm:px-4 py-2.5 text-right font-semibold">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-4 py-2.5 text-gray-900">{{ $item->id }}</td>

                                    @foreach ($columns as $col)
                                        @php $value = data_get($item, $col); @endphp
                                        <td class="px-3 sm:px-4 py-2.5 text-gray-700 max-w-xs truncate" title="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @if (is_bool($value))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $value ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                                    {{ $value ? 'Sí' : 'No' }}
                                                </span>
                                            @elseif (is_null($value) || $value === '')
                                                <span class="text-gray-400">—</span>
                                            @else
                                                {{ is_scalar($value) ? \Illuminate\Support\Str::limit((string) $value, 60) : json_encode($value) }}
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="px-3 sm:px-4 py-2.5 text-right whitespace-nowrap">
                                        <div class="inline-flex gap-1">
                                            <a href="{{ route('admin.cargo-types.edit', $item) }}"
                                               class="px-2 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50"
                                               title="Editar">
                                                {{ __('Editar') }}
                                            </a>
                                            <form method="POST" action="{{ route('admin.cargo-types.destroy', $item) }}"
                                                  onsubmit="return confirm('¿Eliminar este registro?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="px-2 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700"
                                                        title="Eliminar">
                                                    {{ __('Eliminar') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($columns) }}" class="px-6 py-16 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="text-3xl mb-2">📦</div>
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                {{ $q ? 'Sin resultados' : 'Aún no hay tipos de carga' }}
                                            </h3>
                                            <p class="text-gray-500 mt-1">
                                                {{ $q ? 'Ajustá la búsqueda e intentá nuevamente.' : 'Creá tu primer registro para comenzar.' }}
                                            </p>
                                            <div class="mt-4">
                                                <a href="{{ route('admin.cargo-types.create') }}"
                                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                                                    + {{ __('Nuevo') }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-3 sm:px-4 py-3 border-t border-gray-100">
                    {{ $items->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
