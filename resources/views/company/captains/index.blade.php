<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Capitanes') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Administración de capitanes para transporte fluvial
                </p>
            </div>          
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">           

            <!-- Contenido Principal - Componente Livewire -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Aquí llamamos al componente Livewire pasándole las variables del controlador -->
                   @livewire('company.captains', [
                        'countries' => $countries,
                        'companies' => $companies,
                        'filterOptions' => $filterOptions
                    ])
                </div>
            </div>

  

        </div>
    </div>

</x-app-layout>