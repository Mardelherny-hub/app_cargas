<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Crear Usuario') }}</h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center py-12">
                    <h3 class="text-lg font-medium text-gray-900">Crear Usuario - En Desarrollo</h3>
                    <p class="mt-2 text-sm text-gray-500">Formulario de creación de usuarios estará disponible próximamente.</p>
                    <div class="mt-6">
                        <a href="{{ route('admin.users.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Volver a Usuarios
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
