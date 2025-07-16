<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $client->business_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->tax_id }} • {{ $client->country->name ?? 'País no definido' }}
                </p>
            </div>
            <div class="flex space-x-2">
                @if(auth()->user()->canEditClient($client))
                    <a href="{{ route('admin.clients.edit', $client) }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Editar Cliente
                    </a>
                @endif
                <a href="{{ route('admin.clients.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Encabezado con información crítica -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <!-- Estado del Cliente -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $client->status === 'active' ? 'bg-green-500' : ($client->status === 'inactive' ? 'bg-gray-500' : 'bg-red-500') }} rounded-full flex items-center justify-center">
                                    @if($client->status === 'active')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Estado</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ ucfirst($client->status) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verificación -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $client->verified_at ? 'bg-green-500' : 'bg-yellow-500' }} rounded-full flex items-center justify-center">
                                    @if($client->verified_at)
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Verificación</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $client->verified_at ? 'Verificado' : 'Pendiente' }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipo de Cliente -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tipo</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        @switch($client->client_type)
                                            @case('shipper')
                                                Embarcador
                                                @break
                                            @case('consignee')
                                                Consignatario
                                                @break
                                            @case('notify_party')
                                                Notificado
                                                @break
                                            @case('owner')
                                                Propietario
                                                @break
                                            @default
                                                {{ ucfirst($client->client_type) }}
                                        @endswitch
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empresas Relacionadas -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm3 5a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm0 3a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Empresas</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $client->companyRelations->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Información Principal -->
                <div class="lg:col-span-2">
                    
                    <!-- Datos Básicos -->
                    <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información Básica
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        <span class="font-mono">{{ $client->tax_id }}</span>
                                        @if($client->verified_at)
                                            <svg class="ml-2 w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->business_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        @if($client->country)
                                            <img class="h-4 w-6 mr-2" src="https://flagcdn.com/{{ strtolower($client->country->alpha2_code) }}.svg" alt="{{ $client->country->name }}">
                                            {{ $client->country->name }}
                                        @else
                                            <span class="text-gray-500">No definido</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->documentType->name ?? 'No definido' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Puerto Principal</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->primaryPort->name ?? 'No definido' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Aduana</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->customOffice->name ?? 'No definido' }}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empresas Relacionadas -->
                    <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Empresas Relacionadas
                            </h3>
                            @if($client->companyRelations->count() > 0)
                                <div class="space-y-4">
                                    @foreach($client->companyRelations as $relation)
                                        <div class="border rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                            <span class="text-white font-medium text-sm">
                                                                {{ strtoupper(substr($relation->company->business_name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-medium text-gray-900">
                                                            {{ $relation->company->business_name }}
                                                        </h4>
                                                        <p class="text-sm text-gray-500">
                                                            {{ $relation->company->cuit ?? 'CUIT no disponible' }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                        {{ $relation->relation_type === 'customer' ? 'bg-green-100 text-green-800' : 
                                                           ($relation->relation_type === 'provider' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') }}">
                                                        {{ $relation->relation_type === 'customer' ? 'Cliente' : 
                                                           ($relation->relation_type === 'provider' ? 'Proveedor' : 'Ambos') }}
                                                    </span>
                                                    @if($relation->can_edit)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            Editable
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($relation->credit_limit || $relation->internal_code)
                                                <div class="mt-3 grid grid-cols-2 gap-4">
                                                    @if($relation->credit_limit)
                                                        <div>
                                                            <dt class="text-xs font-medium text-gray-500">Límite de Crédito</dt>
                                                            <dd class="mt-1 text-sm text-gray-900">${{ number_format($relation->credit_limit, 2) }}</dd>
                                                        </div>
                                                    @endif
                                                    @if($relation->internal_code)
                                                        <div>
                                                            <dt class="text-xs font-medium text-gray-500">Código Interno</dt>
                                                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $relation->internal_code }}</dd>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin empresas relacionadas</h3>
                                    <p class="mt-1 text-sm text-gray-500">Este cliente no tiene empresas asociadas.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Observaciones -->
                    @if($client->notes)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Observaciones
                                </h3>
                                <div class="prose prose-sm max-w-none">
                                    <p class="text-gray-700">{{ $client->notes }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Panel Lateral -->
                <div class="space-y-6">
                    
                    <!-- Acciones Rápidas -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Acciones
                            </h3>
                            <div class="space-y-3">
                                @if(auth()->user()->canEditClient($client))
                                    <a href="{{ route('admin.clients.edit', $client) }}" 
                                       class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Editar Cliente
                                    </a>
                                @endif
                                
                                @if(auth()->user()->hasRole('super-admin'))
                                    <button onclick="toggleClientStatus()" 
                                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                        </svg>
                                        {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    
                                    @if(!$client->verified_at)
                                        <button onclick="verifyClient()" 
                                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-white hover:bg-green-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Verificar
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Información del Sistema -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información del Sistema
                            </h3>
                            <div class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Creado</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->created_at->format('d/m/Y H:i') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Actualizado</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->updated_at->format('d/m/Y H:i') }}</dd>
                                </div>
                                @if($client->verified_at)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Verificado</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $client->verified_at->format('d/m/Y H:i') }}</dd>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas de Uso -->
                    @if(isset($usageStats) && $usageStats)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Estadísticas de Uso
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500">Uso en cargas</span>
                                        <span class="text-sm font-medium text-gray-900">{{ $usageStats['shipments'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500">Uso en viajes</span>
                                        <span class="text-sm font-medium text-gray-900">{{ $usageStats['trips'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500">Último uso</span>
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ $usageStats['last_used'] ?? 'Nunca' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para acciones -->
    <script>
        function toggleClientStatus() {
            const clientId = {{ $client->id }};
            const currentStatus = '{{ $client->status }}';
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (confirm(`¿Está seguro de que desea ${newStatus === 'active' ? 'activar' : 'desactivar'} este cliente?`)) {
                fetch(`/admin/clients/${clientId}/toggle-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al cambiar el estado del cliente');
                    }
                })
                .catch(() => {
                    alert('Error al cambiar el estado del cliente');
                });
            }
        }

        function verifyClient() {
            const clientId = {{ $client->id }};
            
            if (confirm('¿Está seguro de que desea verificar este cliente?')) {
                fetch(`/admin/clients/${clientId}/verify`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al verificar el cliente');
                    }
                })
                .catch(() => {
                    alert('Error al verificar el cliente');
                });
            }
        }
    </script>
</x-app-layout>