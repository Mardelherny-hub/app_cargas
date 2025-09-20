<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Puerto') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                
                {{-- Botón crear --}}
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <a href="{{ route('admin.ports.index') }}"
                           class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            < {{ __('Volver al Listado') }}
                        </a>
                    </div>
                </div>

                {{-- Componente Livewire listado --}}
                @livewire('admin.ports.edit', ['portId' => $port->id])
            </div>
        </div>
    </div>
</x-app-layout>
