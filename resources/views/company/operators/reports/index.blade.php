<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Mis Reportes') }}</h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center py-12">
                    <h3 class="text-lg font-medium text-gray-900">Mis Reportes - En Desarrollo</h3>
                    <p class="mt-2 text-sm text-gray-500">Reportes personales de tu actividad estarán disponibles próximamente.</p>
                    <div class="mt-6">
                        <a href="{{ route('operator.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Volver al Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
