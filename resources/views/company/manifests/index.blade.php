<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manifiestos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Listado de manifiestos</h3>
                    <a href="{{ route('company.manifests.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700">
                        + Nuevo Manifiesto
                    </a>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Viaje</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($voyages as $voyage)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $voyage->voyage_number }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $voyage->origin_port_id }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $voyage->destination_port_id }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $voyage->status }}</td>
                            <td class="px-4 py-2 text-sm text-right">
                                <a href="{{ route('company.manifests.show', $voyage->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-2">Ver</a>
                                @if($voyage->status === 'planning')
                                    <a href="{{ route('company.manifests.edit', $voyage->id) }}" class="text-blue-600 hover:text-blue-900 mr-2">Editar</a>
                                    <form action="{{ route('company.manifests.destroy', $voyage->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $voyages->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>