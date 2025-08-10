<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $vessel->name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $vessel->vesselType->name ?? 'Tipo no especificado' }} • 
                    @if($vessel->imo_number)
                        IMO: {{ $vessel->imo_number }}
                    @else
                        Sin número IMO
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Editar -->
                @if(auth()->user()->hasRole('company-admin'))
                    <a href="{{ route('company.vessels.edit', $vessel) }}" 
                       class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar
                    </a>
                @endif
                
                <!-- Volver a Lista -->
                <a href="{{ route('company.vessels.index') }}" 
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Estado y Información Principal -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        
                        <!-- Estado Actual -->
                        <div class="text-center">
                            <div class="flex flex-col items-center">
                                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium
                                    @switch($vessel->operational_status)
                                        @case('active')
                                            bg-green-100 text-green-800
                                            @break
                                        @case('inactive')
                                            bg-gray-100 text-gray-800
                                            @break
                                        @case('maintenance')
                                            bg-yellow-100 text-yellow-800
                                            @break
                                        @case('dry_dock')
                                            bg-red-100 text-red-800
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800
                                    @endswitch">
                                    @switch($vessel->operational_status)
                                        @case('active')
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Activa
                                            @break
                                        @case('inactive')
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            Inactiva
                                            @break
                                        @case('maintenance')
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                            </svg>
                                            Mantenimiento
                                            @break
                                        @case('dry_dock')
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            Dique Seco
                                            @break
                                        @default
                                            {{ ucfirst($vessel->operational_status) }}
                                    @endswitch
                                </span>
                                <div class="mt-2 text-xs text-gray-500">Estado Actual</div>
                            </div>
                        </div>

                        <!-- Estadísticas Básicas -->
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                {{ $stats['age_years'] }}
                            </div>
                            <div class="text-xs text-gray-500">Años en Sistema</div>
                        </div>

                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                @if($vessel->length_meters)
                                    {{ number_format($vessel->length_meters, 1) }}m
                                @else
                                    N/D
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">Longitud</div>
                        </div>

                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">
                                @if($vessel->gross_tonnage)
                                    {{ number_format($vessel->gross_tonnage) }}t
                                @else
                                    N/D
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">Tonelaje Bruto</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Detallada -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Información General -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información General</h3>
                        
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre de la Embarcación</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $vessel->name }}</dd>
                            </div>

                            @if($vessel->imo_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Número IMO</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vessel->imo_number }}</dd>
                                </div>
                            @endif

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Embarcación</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $vessel->vesselType->name ?? 'No especificado' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($vessel->operational_status)
                                            @case('active') bg-green-100 text-green-800 @break
                                            @case('inactive') bg-gray-100 text-gray-800 @break
                                            @case('maintenance') bg-yellow-100 text-yellow-800 @break
                                            @case('dry_dock') bg-red-100 text-red-800 @break
                                            @default bg-gray-100 text-gray-800
                                        @endswitch">
                                        @switch($vessel->operational_status)
                                            @case('active') Activa @break
                                            @case('inactive') Inactiva @break
                                            @case('maintenance') Mantenimiento @break
                                            @case('dry_dock') Dique Seco @break
                                            @default {{ ucfirst($vessel->operational_status) }}
                                        @endswitch
                                    </span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Registro</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $vessel->created_at->format('d/m/Y H:i') }}</dd>
                            </div>

                            @if($vessel->updated_at && $vessel->updated_at->ne($vessel->created_at))
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Última Actualización</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vessel->updated_at->format('d/m/Y H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Propietario -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Propietario</h3>
                        
                        @if($vessel->vesselOwner)
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vessel->vesselOwner->legal_name }}</dd>
                                </div>

                                @if($vessel->vesselOwner->commercial_name && $vessel->vesselOwner->commercial_name !== $vessel->vesselOwner->legal_name)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $vessel->vesselOwner->commercial_name }}</dd>
                                    </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vessel->vesselOwner->tax_id }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $vessel->vesselOwner->transportista_type === 'O' ? 'Operador' : 'Representante' }}
                                    </dd>
                                </div>

                                @if($vessel->vesselOwner->country)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">País</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $vessel->vesselOwner->country->name }}</dd>
                                    </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado del Propietario</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @switch($vessel->operational_status)
                                                @case('active') bg-green-100 text-green-800 @break
                                                @case('inactive') bg-gray-100 text-gray-800 @break
                                                @case('maintenance') bg-yellow-100 text-yellow-800 @break
                                                @case('dry_dock') bg-red-100 text-red-800 @break
                                                @case('under_repair') bg-orange-100 text-orange-800 @break
                                                @case('decommissioned') bg-red-200 text-red-900 @break
                                                @default bg-gray-100 text-gray-800
                                            @endswitch">
                                            @switch($vessel->operational_status)
                                                @case('active') Activa @break
                                                @case('inactive') Inactiva @break
                                                @case('maintenance') Mantenimiento @break
                                                @case('dry_dock') Dique Seco @break
                                                @case('under_repair') En Reparación @break
                                                @case('decommissioned') Descomisionada @break
                                                @default {{ ucfirst($vessel->operational_status) }}
                                            @endswitch
                                        </span>
                                    </dd>
                                </div>

                                <!-- Enlace para ver propietario -->
                                <div class="pt-4 border-t border-gray-200">
                                    <a href="{{ route('company.vessel-owners.show', $vessel->vesselOwner) }}" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Ver detalles del propietario →
                                    </a>
                                </div>
                            </dl>
                        @else
                            <p class="text-sm text-gray-500">No se ha asignado un propietario a esta embarcación.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Especificaciones Técnicas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Especificaciones Técnicas</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Dimensiones -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Dimensiones</h4>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Longitud:</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $vessel->length_meters ? number_format($vessel->length_meters, 1) . ' m' : 'N/D' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Capacidades -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Capacidades</h4>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Tonelaje Bruto:</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $vessel->gross_tonnage ? number_format($vessel->gross_tonnage) . ' t' : 'N/D' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Contenedores:</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $vessel->container_capacity ? number_format($vessel->container_capacity) . ' TEU' : 'N/D' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Estado del IMO -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Certificación</h4>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Número IMO:</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($vessel->imo_number)
                                            <span class="inline-flex items-center">
                                                {{ $vessel->imo_number }}
                                                <svg class="w-4 h-4 ml-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @else
                                            <span class="text-gray-400">Sin IMO</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auditoría -->
            @if($vessel->createdByUser || $vessel->updatedByUser)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Auditoría</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if($vessel->createdByUser)
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Creación</h4>
                                    <dl class="space-y-1">
                                        <div>
                                            <dt class="text-xs text-gray-500">Usuario:</dt>
                                            <dd class="text-sm text-gray-900">{{ $vessel->createdByUser->name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Fecha:</dt>
                                            <dd class="text-sm text-gray-900">{{ $vessel->created_at->format('d/m/Y H:i:s') }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @if($vessel->updatedByUser)
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Última Modificación</h4>
                                    <dl class="space-y-1">
                                        <div>
                                            <dt class="text-xs text-gray-500">Usuario:</dt>
                                            <dd class="text-sm text-gray-900">{{ $vessel->updatedByUser->name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Fecha:</dt>
                                            <dd class="text-sm text-gray-900">{{ $vessel->updated_at->format('d/m/Y H:i:s') }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Acciones Rápidas -->
            @if(auth()->user()->hasRole('company-admin'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones</h3>
                        
                        <div class="flex flex-wrap gap-3">
                            <!-- Cambiar Estado -->
                            <form action="{{ route('company.vessels.toggle-status', $vessel) }}" 
                                  method="POST" class="inline" 
                                  onsubmit="return confirm('¿Está seguro de cambiar el estado de esta embarcación?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    @if($vessel->operational_status === 'active')
                                        <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Desactivar
                                    @else
                                        <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Activar
                                    @endif
                                </button>
                            </form>

                            <!-- Eliminar -->
                            <form action="{{ route('company.vessels.destroy', $vessel) }}" 
                                  method="POST" class="inline" 
                                  onsubmit="return confirm('¿Está seguro de eliminar esta embarcación? Esta acción no se puede deshacer.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Eliminar Embarcación
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>