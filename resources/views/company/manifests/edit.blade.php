<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Manifiesto</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('company.manifests.update', $voyage->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <x-input-label for="origin_port_id" :value="__('Puerto de Origen')" />
                        <x-text-input id="origin_port_id" name="origin_port_id" type="number" class="mt-1 block w-full" value="{{ $voyage->origin_port_id }}" required />
                        <x-input-error :messages="$errors->get('origin_port_id')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="destination_port_id" :value="__('Puerto de Destino')" />
                        <x-text-input id="destination_port_id" name="destination_port_id" type="number" class="mt-1 block w-full" value="{{ $voyage->destination_port_id }}" required />
                        <x-input-error :messages="$errors->get('destination_port_id')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Actualizar Manifiesto') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>