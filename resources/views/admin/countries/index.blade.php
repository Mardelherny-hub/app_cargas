<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Países') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                
                {{-- Botón crear --}}
                <div class="flex justify-between items-center mb-4">
                    <a href="{{ route('admin.countries.create') }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        + {{ __('Nuevo País') }}
                    </a>
                </div>

                {{-- Componente Livewire listado --}}
                @livewire('admin.countries.index')
            </div>
        </div>
    </div>
</x-app-layout>
