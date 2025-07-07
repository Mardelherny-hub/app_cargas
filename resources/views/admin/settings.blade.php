<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            ⚙️ Configuración del sistema
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-8 space-y-6">

            {{-- Sección 1: Datos generales --}}
            <x-card>
                <x-slot name="title">Datos generales</x-slot>

                <form method="POST" action="{{ route('admin.settings.updateGeneral') }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="app_name" value="Nombre del sistema" />
                            <x-input id="app_name" name="app_name" type="text" class="mt-1 block w-full" value="{{ old('app_name', config('app.name')) }}" />
                        </div>

                        <div>
                            <x-label for="company_name" value="Nombre de la empresa" />
                            <x-input id="company_name" name="company_name" type="text" class="mt-1 block w-full" value="{{ old('company_name') }}" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-button>Guardar</x-button>
                    </div>
                </form>
            </x-card>

            {{-- Sección 2: Seguridad --}}
            <x-card>
                <x-slot name="title">Seguridad</x-slot>

                <form method="POST" action="{{ route('admin.settings.updateSecurity') }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="two_factor_enabled" value="Requiere 2FA para usuarios" />
                            <select name="two_factor_enabled" id="two_factor_enabled" class="mt-1 block w-full">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <div>
                            <x-label for="session_timeout" value="Tiempo de expiración de sesión (min)" />
                            <x-input id="session_timeout" name="session_timeout" type="number" class="mt-1 block w-full" value="{{ old('session_timeout', 30) }}" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-button>Guardar seguridad</x-button>
                    </div>
                </form>
            </x-card>

            {{-- Sección 3: Mantenimiento --}}
            <x-card>
                <x-slot name="title">Modo Mantenimiento</x-slot>

                <form method="POST" action="{{ route('admin.settings.toggleMaintenance') }}">
                    @csrf
                    @method('PATCH')

                    <p class="text-sm text-gray-600 mb-2">
                        El sistema se pondrá en modo mantenimiento y los usuarios no podrán acceder, excepto administradores.
                    </p>

                    <x-button type="submit" color="red">
                        Activar mantenimiento
                    </x-button>
                </form>
            </x-card>

        </div>
    </div>
</x-app-layout>
