{{-- Filtros --}}
<div class="mb-6 p-4 bg-gray-50 rounded-lg">
    <form action="{{ route('admin.afip-config.index') }}" method="GET" class="flex items-end gap-4 flex-wrap">
        <input type="hidden" name="tab" value="locations">
        
        <div>
            <label for="customs_filter" class="block text-sm font-medium text-gray-700">Filtrar por Aduana</label>
            <select name="customs_filter" id="customs_filter" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todas</option>
                @foreach($customsOffices as $office)
                    <option value="{{ $office->code }}" {{ request('customs_filter') == $office->code ? 'selected' : '' }}>
                        {{ $office->code }} - {{ Str::limit($office->name, 30) }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label for="country_filter" class="block text-sm font-medium text-gray-700">Filtrar por País</label>
            <select name="country_filter" id="country_filter" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todos</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ request('country_filter') == $country->id ? 'selected' : '' }}>
                        {{ $country->alpha2_code }} - {{ $country->name }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
            Filtrar
        </button>
        
        @if(request('customs_filter') || request('country_filter'))
            <a href="{{ route('admin.afip-config.index', ['tab' => 'locations']) }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Limpiar
            </a>
        @endif
    </form>
</div>

{{-- Formulario Crear Lugar Operativo --}}
<div class="mb-6 p-4 bg-blue-50 rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Agregar Nuevo Lugar Operativo</h3>
    <form action="{{ route('admin.afip-config.locations.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        @csrf
        <div>
            <label for="country_id" class="block text-sm font-medium text-gray-700">País *</label>
            <select name="country_id" id="country_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->alpha2_code }} - {{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="customs_code" class="block text-sm font-medium text-gray-700">Cód. Aduana *</label>
            <input type="text" name="customs_code" id="customs_code" maxlength="10" required
                   placeholder="001"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono">
        </div>
        <div>
            <label for="location_code" class="block text-sm font-medium text-gray-700">Cód. Lugar *</label>
            <input type="text" name="location_code" id="location_code" maxlength="10" required
                   placeholder="10073"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono">
        </div>
        <div class="md:col-span-2">
            <label for="description" class="block text-sm font-medium text-gray-700">Descripción *</label>
            <input type="text" name="description" id="description" maxlength="150" required
                   placeholder="TERMINAL PORTUARIA SUR"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="port_id" class="block text-sm font-medium text-gray-700">Puerto</label>
            <select name="port_id" id="port_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Sin asignar</option>
                @foreach($ports as $port)
                    <option value="{{ $port->id }}">{{ $port->code }} - {{ $port->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_foreign" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Extranjero</span>
            </label>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Agregar
            </button>
        </div>
    </form>
</div>

{{-- Tabla de Lugares Operativos --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">País</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aduana</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lugar</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Puerto</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($locations as $location)
                <tr class="{{ $location->is_active ? '' : 'bg-gray-50 opacity-60' }}">
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="text-sm font-medium">{{ $location->country->alpha2_code ?? '—' }}</span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="font-mono text-sm text-blue-600">{{ $location->customs_code }}</span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="font-mono text-sm font-bold text-green-600">{{ $location->location_code }}</span>
                    </td>
                    
                    <td class="px-3 py-2">
                        <span class="text-sm text-gray-900">{{ Str::limit($location->description, 50) }}</span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        @if($location->port)
                            <span class="text-sm font-mono text-indigo-600 mr-1">{{ $location->port->code }}</span>
                        @endif
                        <button type="button" 
                                onclick="openVincularModal({{ $location->id }}, '{{ $location->location_code }}', '{{ $location->description }}', '{{ $location->port_id ?? '' }}')"
                                class="px-2 py-1 text-xs {{ $location->port ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' }} rounded">
                            {{ $location->port ? '✏️' : 'Vincular' }}
                        </button>
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($location->is_foreign)
                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Exterior</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Nacional</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        @if($location->is_active)
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Activo</span>
                        @else
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right space-x-1">
                        {{-- Toggle --}}
                        <form action="{{ route('admin.afip-config.locations.toggle', $location) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-sm {{ $location->is_active ? 'text-yellow-600' : 'text-green-600' }}" title="{{ $location->is_active ? 'Desactivar' : 'Activar' }}">
                                {{ $location->is_active ? '⏸️' : '▶️' }}
                            </button>
                        </form>
                        
                        {{-- Delete --}}
                        <form action="{{ route('admin.afip-config.locations.destroy', $location) }}" method="POST" class="inline"
                              onsubmit="return confirm('¿Eliminar lugar {{ $location->location_code }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800" title="Eliminar">
                                🗑️
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        No hay lugares operativos registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal Vincular Puerto --}}
<div id="vincularModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-900">Vincular Puerto</h3>
            <p class="text-sm text-gray-500 mt-1">
                Lugar: <span id="modalLocationCode" class="font-mono font-bold"></span> - <span id="modalLocationDesc"></span>
            </p>
        </div>
        
        <form id="vincularForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="px-6 py-4">
                <label for="modal_port_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Seleccionar Puerto
                </label>
                <select name="port_id" id="modal_port_id" required 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Seleccionar --</option>
                    @foreach($ports as $port)
                        <option value="{{ $port->id }}">{{ $port->code }} - {{ $port->name }} ({{ $port->city }})</option>
                    @endforeach
                </select>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 rounded-b-lg">
                <button type="button" onclick="closeVincularModal()" 
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                    Cancelar
                </button>
                <button type="submit" 
                        class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                    Vincular
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openVincularModal(locationId, code, description, currentPortId) {
    document.getElementById('modalLocationCode').textContent = code;
    document.getElementById('modalLocationDesc').textContent = description;
    document.getElementById('vincularForm').action = `/admin/afip-config/locations/${locationId}/vincular-puerto`;
    document.getElementById('modal_port_id').value = currentPortId || '';
    document.getElementById('vincularModal').classList.remove('hidden');
}

function closeVincularModal() {
    document.getElementById('vincularModal').classList.add('hidden');
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVincularModal();
});
</script>

{{-- Paginación --}}
@if($locations->hasPages())
    <div class="mt-4">
        {{ $locations->links() }}
    </div>
@endif