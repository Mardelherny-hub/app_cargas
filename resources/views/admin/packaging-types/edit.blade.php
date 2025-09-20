<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar Tipo de Packaging') }}
            </h2>

            <a href="{{ route('admin.packaging-types.index') }}"
               class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                ← {{ __('Volver') }}
            </a>
        </div>
    </x-slot>

    {{-- Pasamos el ID del registro recibido desde el controlador (variable $item) --}}
    @livewire('admin.packaging-types.edit-packaging-type', ['packagingTypeId' => $item->id])
</x-app-layout>