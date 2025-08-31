<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Nuevo Tipo de Packaging') }}
            </h2>
            <a href="{{ route('admin.packaging-types.index') }}"
               class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                ← {{ __('Volver') }}
            </a>
        </div>
    </x-slot>

    @livewire('admin.packaging-types.create-packaging-type')
</x-app-layout>
