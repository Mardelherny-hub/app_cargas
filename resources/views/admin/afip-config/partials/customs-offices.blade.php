{{-- Formulario Crear Aduana --}}
<div class="mb-6 p-4 bg-gray-50 rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Agregar Nueva Aduana</h3>
    <form action="{{ route('admin.afip-config.customs-offices.store') }}" method="POST" class="flex items-end gap-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Código</label>
            <input type="text" name="code" id="code" maxlength="3" required
                   placeholder="001"
                   class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-mono">
        </div>
        <div class="flex-1">
            <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
            <input type="text" name="name" id="name" maxlength="100" required
                   placeholder="ADUANA DE BUENOS AIRES"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            Agregar
        </button>
    </form>
</div>

{{-- Tabla de Aduanas --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Puertos</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($customsOffices as $office)
                <tr class="{{ $office->is_active ? '' : 'bg-gray-50 opacity-60' }}">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="font-mono text-sm font-bold text-blue-600">{{ $office->code }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-gray-900">{{ $office->name }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($office->is_active)
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Activa</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm text-gray-500">{{ $office->ports->count() }}</span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        {{-- Toggle Status --}}
                        <form action="{{ route('admin.afip-config.customs-offices.toggle', $office) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-sm {{ $office->is_active ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800' }}"
                                    title="{{ $office->is_active ? 'Desactivar' : 'Activar' }}">
                                {{ $office->is_active ? '⏸️' : '▶️' }}
                            </button>
                        </form>
                        
                        {{-- Edit (Modal trigger) --}}
                        <button type="button" 
                                onclick="editCustomsOffice({{ $office->id }}, '{{ $office->code }}', '{{ addslashes($office->name) }}')"
                                class="text-sm text-blue-600 hover:text-blue-800" title="Editar">
                            ✏️
                        </button>
                        
                        {{-- Delete --}}
                        <form action="{{ route('admin.afip-config.customs-offices.destroy', $office) }}" method="POST" class="inline"
                              onsubmit="return confirm('¿Eliminar aduana {{ $office->code }}?')">
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
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                        No hay aduanas registradas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal Editar --}}
<div id="editCustomsOfficeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Editar Aduana</h3>
        <form id="editCustomsOfficeForm" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Código</label>
                <input type="text" name="code" id="edit_code" maxlength="3" required
                       class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-mono">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" name="name" id="edit_name" maxlength="100" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editCustomsOffice(id, code, name) {
    document.getElementById('editCustomsOfficeForm').action = '{{ route("admin.afip-config.index") }}/customs-offices/' + id;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_name').value = name;
    document.getElementById('editCustomsOfficeModal').classList.remove('hidden');
    document.getElementById('editCustomsOfficeModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editCustomsOfficeModal').classList.add('hidden');
    document.getElementById('editCustomsOfficeModal').classList.remove('flex');
}
</script>