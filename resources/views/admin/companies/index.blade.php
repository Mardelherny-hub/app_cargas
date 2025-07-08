<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Gestión de Empresas') }}
            </h2>
            <a href="{{ route('admin.companies.create') }}"
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Crear Empresa
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <!-- Filtros y búsqueda -->
                    <div class="mb-6">
                        <form method="GET" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                           placeholder="Buscar empresa..."
                                           class="w-full border-gray-300 rounded-md shadow-sm">
                                </div>
                                <div>
                                    <select name="country" class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">Todos los países</option>
                                        <option value="AR" {{ request('country') === 'AR' ? 'selected' : '' }}>Argentina</option>
                                        <option value="PY" {{ request('country') === 'PY' ? 'selected' : '' }}>Paraguay</option>
                                    </select>
                                </div>
                                <div>
                                    <select name="role" class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">Todos los roles</option>
                                        <option value="Cargas" {{ request('role') === 'Cargas' ? 'selected' : '' }}>Cargas</option>
                                        <option value="Desconsolidador" {{ request('role') === 'Desconsolidador' ? 'selected' : '' }}>Desconsolidador</option>
                                        <option value="Transbordos" {{ request('role') === 'Transbordos' ? 'selected' : '' }}>Transbordos</option>
                                    </select>
                                </div>
                                <div>
                                    <select name="status" class="w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">Todos los estados</option>
                                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activas</option>
                                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivas</option>
                                        <option value="cert_expired" {{ request('status') === 'cert_expired' ? 'selected' : '' }}>Certificado Vencido</option>
                                        <option value="no_cert" {{ request('status') === 'no_cert' ? 'selected' : '' }}>Sin Certificado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                    Filtrar
                                </button>
                                <a href="{{ route('admin.companies.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                    Limpiar
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $stats['total'] ?? 0 }}</div>
                            <div class="text-sm text-blue-800">Total Empresas</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $stats['active'] ?? 0 }}</div>
                            <div class="text-sm text-green-800">Activas</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ $stats['cert_expiring'] ?? 0 }}</div>
                            <div class="text-sm text-yellow-800">Cert. por Vencer</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ $stats['cert_expired'] ?? 0 }}</div>
                            <div class="text-sm text-red-800">Cert. Vencidos</div>
                        </div>
                    </div>

                    <!-- Tabla de empresas -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Empresa
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        País/CUIT
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Roles de Negocio
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Certificado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Usuarios
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($companies as $company)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $company->business_name }}
                                                </div>
                                                @if($company->commercial_name)
                                                    <div class="text-sm text-gray-500">
                                                        {{ $company->commercial_name }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $company->country === 'AR' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-500">{{ $company->tax_id }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($company->getRoles() as $role)
                                                    @php
                                                        $roleColor = match($role) {
                                                            'Cargas' => 'bg-blue-100 text-blue-800',
                                                            'Desconsolidador' => 'bg-yellow-100 text-yellow-800',
                                                            'Transbordos' => 'bg-purple-100 text-purple-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    @endphp
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColor }}">
                                                        {{ $role }}
                                                    </span>
                                                @endforeach
                                                @if(empty($company->getRoles()))
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Sin Roles
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                WS: {{ $company->webservices_display }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($company->has_certificate)
                                                <div class="text-sm">
                                                    @if($company->is_certificate_expired)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Vencido
                                                        </span>
                                                    @elseif($company->is_certificate_expiring_soon)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            Por Vencer
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            Válido
                                                        </span>
                                                    @endif
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        {{ $company->certificate_expires_at?->format('d/m/Y') }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    Sin Certificado
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <div>{{ $company->users_count ?? 0 }} usuarios</div>
                                            @if($company->admin_count ?? 0 > 0)
                                                <div class="text-xs text-green-600">{{ $company->admin_count }} admin(s)</div>
                                            @else
                                                <div class="text-xs text-red-600">Sin administrador</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $company->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $company->active ? 'Activa' : 'Inactiva' }}
                                            </span>
                                            @if(!$company->ws_active && $company->active)
                                                <div class="text-xs text-yellow-600 mt-1">WS Inactivo</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('admin.companies.show', $company) }}"
                                                   class="text-blue-600 hover:text-blue-900">Ver</a>
                                                <a href="{{ route('admin.companies.edit', $company) }}"
                                                   class="text-green-600 hover:text-green-900">Editar</a>
                                                <a href="{{ route('admin.companies.certificates', $company) }}"
                                                   class="text-purple-600 hover:text-purple-900">Certificados</a>
                                                @if($company->users_count === 0)
                                                    <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900"
                                                                onclick="return confirm('¿Eliminar empresa {{ $company->business_name }}?')">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No se encontraron empresas
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    @if($companies->hasPages())
                        <div class="mt-6">
                            {{ $companies->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
