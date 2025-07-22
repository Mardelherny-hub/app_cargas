<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Embarcaciones de ') }}{{ $vesselOwner->commercial_name ?? $vesselOwner->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $vesselOwner->legal_name }} • {{ $vesselOwner->tax_id }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Ver Propietario -->
                <a href="{{ route('company.vessel-owners.show', $vesselOwner) }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Propietario
                </a>
                <!-- Volver a Lista -->
                <a href="{{ route('company.vessel-owners.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Resumen del Propietario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Info Principal -->
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $vesselOwner->legal_name }}</h3>
                            <div class="space-y-1 text-sm text-gray-600">
                                @if($vesselOwner->commercial_name && $vesselOwner->commercial_name !== $vesselOwner->legal_name)
                                    <p><strong>Comercial:</strong> {{ $vesselOwner->commercial_name }}</p>
                                @endif
                                <p><strong>CUIT/RUC:</strong> {{ $vesselOwner->tax_id }}</p>
                                <p><strong>País:</strong> {{ $vesselOwner->country->name }}</p>
                                <p><strong>Tipo:</strong> {{ \App\Models\VesselOwner::TRANSPORTISTA_TYPES[$vesselOwner->transportista_type] }}</p>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Estado</p>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                    {{ $vesselOwner->status == 'active' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $vesselOwner->status == 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                    {{ $vesselOwner->status == 'suspended' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $vesselOwner->status == 'pending_verification' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ \App\Models\VesselOwner::STATUSES[$vesselOwner->status] }}
                                </span>
                            </div>
                        </div>

                        <!-- Webservices -->
                        <div class="flex items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Webservices</p>
                                @if($vesselOwner->webservice_authorized)
                                    <span class="inline-flex items-center text-green-600 text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Autorizado
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-gray-500 text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        No autorizado
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Embarcaciones -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Embarcaciones</h3>
                            <p class="text-sm text-gray-600">{{ $vessels->total() }} embarcación(es) registrada(s)</p>
                        </div>
                        
                        @if(auth()->user()->hasRole('company-admin'))
                            <div class="text-sm text-gray-500">
                                <!-- TODO: Agregar botón "Nueva Embarcación" cuando esté el módulo -->
                                <span class="italic">Gestión de embarcaciones próximamente</span>
                            </div>
                        @endif
                    </div>

                    @if($vessels->count() > 0)
                        <!-- Tabla de Embarcaciones -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Matrícula
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Dimensiones
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado Operacional
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Bandera
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Activo
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vessels as $vessel)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2-2h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H17M3 19h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H15"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $vessel->name }}</div>
                                                    @if($vessel->call_sign)
                                                        <div class="text-sm text-gray-500">{{ $vessel->call_sign }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-mono text-gray-900">{{ $vessel->registration_number }}</div>
                                            @if($vessel->imo_number)
                                                <div class="text-xs text-gray-500">IMO: {{ $vessel->imo_number }}</div>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vessel->vessel_type_id }} <!-- TODO: Reemplazar con $vessel->vesselType->name cuando exista -->
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($vessel->length_meters, 1) }}m × {{ number_format($vessel->beam_meters, 1) }}m
                                            <div class="text-xs">
                                                Calado: {{ number_format($vessel->draft_meters, 2) }}m
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $vessel->operational_status == 'active' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $vessel->operational_status == 'maintenance' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                {{ $vessel->operational_status == 'dry_dock' ? 'bg-orange-100 text-orange-800' : '' }}
                                                {{ $vessel->operational_status == 'charter' ? 'bg-blue-100 text-blue-800' : '' }}
                                                {{ $vessel->operational_status == 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                                {{ $vessel->operational_status == 'decommissioned' ? 'bg-red-100 text-red-800' : '' }}">
                                                {{ \App\Models\Vessel::OPERATIONAL_STATUSES[$vessel->operational_status] ?? ucfirst($vessel->operational_status) }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vessel->flagCountry->name ?? 'N/A' }}
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($vessel->active)
                                                <span class="inline-flex items-center text-green-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-gray-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <!-- TODO: Agregar enlaces cuando esté el módulo de vessels -->
                                            <span class="text-gray-400 italic text-xs">Acciones próximamente</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="mt-6">
                            {{ $vessels->links() }}
                        </div>

                    @else
                        <!-- Estado Vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2-2h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H17M3 19h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H15"/>
                            </svg>
                            <h3 class="mt-4 text-sm font-medium text-gray-900">Sin Embarcaciones</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                Este propietario no tiene embarcaciones registradas en el sistema.
                            </p>
                            @if(auth()->user()->hasRole('company-admin'))
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-700 bg-gray-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Gestión de embarcaciones próximamente
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Información de Capacidades -->
            @if($vessels->count() > 0)
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resumen de Capacidades</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $vessels->where('active', true)->count() }}</div>
                            <div class="text-sm text-gray-500">Embarcaciones Activas</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $vessels->where('operational_status', 'active')->count() }}</div>
                            <div class="text-sm text-gray-500">En Operación</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ $vessels->whereIn('operational_status', ['maintenance', 'dry_dock'])->count() }}</div>
                            <div class="text-sm text-gray-500">En Mantenimiento</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ number_format($vessels->sum('max_cargo_capacity'), 0) }}
                            </div>
                            <div class="text-sm text-gray-500">Capacidad Total (Ton)</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout><x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Embarcaciones de ') }}{{ $vesselOwner->commercial_name ?? $vesselOwner->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $vesselOwner->legal_name }} • {{ $vesselOwner->tax_id }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Ver Propietario -->
                <a href="{{ route('company.vessel-owners.show', $vesselOwner) }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Propietario
                </a>
                <!-- Volver a Lista -->
                <a href="{{ route('company.vessel-owners.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Resumen del Propietario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Info Principal -->
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $vesselOwner->legal_name }}</h3>
                            <div class="space-y-1 text-sm text-gray-600">
                                @if($vesselOwner->commercial_name && $vesselOwner->commercial_name !== $vesselOwner->legal_name)
                                    <p><strong>Comercial:</strong> {{ $vesselOwner->commercial_name }}</p>
                                @endif
                                <p><strong>CUIT/RUC:</strong> {{ $vesselOwner->tax_id }}</p>
                                <p><strong>País:</strong> {{ $vesselOwner->country->name }}</p>
                                <p><strong>Tipo:</strong> {{ \App\Models\VesselOwner::TRANSPORTISTA_TYPES[$vesselOwner->transportista_type] }}</p>
                            </div>
                        </div>
                        
                        <!-- Estado -->
                        <div class="flex items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Estado</p>
                                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                    {{ $vesselOwner->status == 'active' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $vesselOwner->status == 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                    {{ $vesselOwner->status == 'suspended' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $vesselOwner->status == 'pending_verification' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ \App\Models\VesselOwner::STATUSES[$vesselOwner->status] }}
                                </span>
                            </div>
                        </div>

                        <!-- Webservices -->
                        <div class="flex items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Webservices</p>
                                @if($vesselOwner->webservice_authorized)
                                    <span class="inline-flex items-center text-green-600 text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Autorizado
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-gray-500 text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        No autorizado
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Embarcaciones -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Embarcaciones</h3>
                            <p class="text-sm text-gray-600">{{ $vessels->total() }} embarcación(es) registrada(s)</p>
                        </div>
                        
                        @if(auth()->user()->hasRole('company-admin'))
                            <div class="text-sm text-gray-500">
                                <!-- TODO: Agregar botón "Nueva Embarcación" cuando esté el módulo -->
                                <span class="italic">Gestión de embarcaciones próximamente</span>
                            </div>
                        @endif
                    </div>

                    @if($vessels->count() > 0)
                        <!-- Tabla de Embarcaciones -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Matrícula
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Dimensiones
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado Operacional
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Bandera
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Activo
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vessels as $vessel)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2-2h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H17M3 19h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H15"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $vessel->name }}</div>
                                                    @if($vessel->call_sign)
                                                        <div class="text-sm text-gray-500">{{ $vessel->call_sign }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-mono text-gray-900">{{ $vessel->registration_number }}</div>
                                            @if($vessel->imo_number)
                                                <div class="text-xs text-gray-500">IMO: {{ $vessel->imo_number }}</div>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vessel->vessel_type_id }} <!-- TODO: Reemplazar con $vessel->vesselType->name cuando exista -->
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($vessel->length_meters, 1) }}m × {{ number_format($vessel->beam_meters, 1) }}m
                                            <div class="text-xs">
                                                Calado: {{ number_format($vessel->draft_meters, 2) }}m
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $vessel->operational_status == 'active' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $vessel->operational_status == 'maintenance' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                {{ $vessel->operational_status == 'dry_dock' ? 'bg-orange-100 text-orange-800' : '' }}
                                                {{ $vessel->operational_status == 'charter' ? 'bg-blue-100 text-blue-800' : '' }}
                                                {{ $vessel->operational_status == 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                                {{ $vessel->operational_status == 'decommissioned' ? 'bg-red-100 text-red-800' : '' }}">
                                                {{ \App\Models\Vessel::OPERATIONAL_STATUSES[$vessel->operational_status] ?? ucfirst($vessel->operational_status) }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vessel->flagCountry->name ?? 'N/A' }}
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($vessel->active)
                                                <span class="inline-flex items-center text-green-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-gray-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <!-- TODO: Agregar enlaces cuando esté el módulo de vessels -->
                                            <span class="text-gray-400 italic text-xs">Acciones próximamente</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="mt-6">
                            {{ $vessels->links() }}
                        </div>

                    @else
                        <!-- Estado Vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2-2h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H17M3 19h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H15"/>
                            </svg>
                            <h3 class="mt-4 text-sm font-medium text-gray-900">Sin Embarcaciones</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                Este propietario no tiene embarcaciones registradas en el sistema.
                            </p>
                            @if(auth()->user()->hasRole('company-admin'))
                                <div class="mt-4">
                                    <span class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-700 bg-gray-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Gestión de embarcaciones próximamente
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Información de Capacidades -->
            @if($vessels->count() > 0)
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resumen de Capacidades</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $vessels->where('active', true)->count() }}</div>
                            <div class="text-sm text-gray-500">Embarcaciones Activas</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $vessels->where('operational_status', 'active')->count() }}</div>
                            <div class="text-sm text-gray-500">En Operación</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ $vessels->whereIn('operational_status', ['maintenance', 'dry_dock'])->count() }}</div>
                            <div class="text-sm text-gray-500">En Mantenimiento</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ number_format($vessels->sum('max_cargo_capacity'), 0) }}
                            </div>
                            <div class="text-sm text-gray-500">Capacidad Total (Ton)</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>