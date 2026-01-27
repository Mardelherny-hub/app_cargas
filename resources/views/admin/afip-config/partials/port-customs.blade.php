{{-- Formulario Crear Vínculo --}}
<div class="mb-6 p-4 bg-gray-50 rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Vincular Puerto con Aduana AFIP</h3>
    <form action="{{ route('admin.afip-config.port-customs.attach') }}" method="POST" class="flex items-end gap-4 flex-wrap">
        @csrf
        <div class="flex-1 min-w-[200px]">
            <label for="port_id" class="block text-sm font-medium text-gray-700">Puerto (Argentina)</label>
            <select name="port_id" id="port_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar puerto</option>
                @foreach($ports as $port)
                    <option value="{{ $port->id }}">{{ $port->code }} - {{ $port->name }} ({{ $port->city }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label for="afip_customs_office_id" class="block text-sm font-medium text-gray-700">Aduana AFIP</label>
            <select name="afip_customs_office_id" id="afip_customs_office_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar aduana</option>
                @foreach($customsOffices->where('is_active', true) as $office)
                    <option value="{{ $office->id }}">{{ $office->code }} - {{ $office->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Predeterminada</span>
            </label>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Vincular
            </button>
        </div>
    </form>
</div>

{{-- Tabla de Vínculos agrupada por Puerto --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Puerto</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ciudad</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aduana AFIP</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Predeterminada</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($portCustoms->groupBy('port_id') as $portId => $links)
                @foreach($links as $index => $link)
                    <tr class="{{ $link->is_default ? 'bg-green-50' : '' }}">
                        @if($index === 0)
                            <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $links->count() }}">
                                <span class="font-mono text-sm font-bold text-blue-600">{{ $link->port->code ?? '—' }}</span>
                                <br>
                                <span class="text-sm text-gray-600">{{ $link->port->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $links->count() }}">
                                <span class="text-sm text-gray-500">{{ $link->port->city ?? '—' }}</span>
                            </td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-mono text-sm text-green-600 font-bold">{{ $link->afipCustomsOffice->code ?? '—' }}</span>
                            <span class="text-sm text-gray-600"> - {{ $link->afipCustomsOffice->name ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($link->is_default)
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">✓ Sí</span>
                            @else
                                <form action="{{ route('admin.afip-config.port-customs.set-default', $link) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600" title="Marcar como predeterminada">
                                        Hacer default
                                    </button>
                                </form>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.afip-config.port-customs.detach', $link) }}" method="POST" class="inline"
                                  onsubmit="return confirm('¿Eliminar vínculo {{ $link->port->code ?? '' }} ↔ {{ $link->afipCustomsOffice->code ?? '' }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800" title="Eliminar vínculo">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                        No hay vínculos puerto-aduana configurados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Resumen --}}
<div class="mt-6 p-4 bg-blue-50 rounded-lg">
    <h4 class="text-sm font-medium text-blue-800 mb-2">📊 Resumen</h4>
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
            <span class="text-blue-600 font-bold">{{ $portCustoms->pluck('port_id')->unique()->count() }}</span>
            <span class="text-gray-600">puertos configurados</span>
        </div>
        <div>
            <span class="text-blue-600 font-bold">{{ $portCustoms->pluck('afip_customs_office_id')->unique()->count() }}</span>
            <span class="text-gray-600">aduanas vinculadas</span>
        </div>
        <div>
            <span class="text-blue-600 font-bold">{{ $portCustoms->where('is_default', true)->count() }}</span>
            <span class="text-gray-600">predeterminadas</span>
        </div>
    </div>
</div>