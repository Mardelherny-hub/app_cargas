<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar Tipo de Contenedor') }}
            </h2>

            <a href="{{ route('admin.container-types.index') }}"
               class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                ← {{ __('Volver') }}
            </a>
        </div>
    </x-slot>

    {{-- Invoca al componente Livewire de edición.
         Pasamos el ID del registro; usá la variable que envíes desde el controlador. --}}
    @livewire('admin.container-types.edit-container-type', [
        'containerTypeId' => isset($item) ? $item->id : (isset($containerType) ? $containerType->id : null)
    ])
</x-app-layout>
