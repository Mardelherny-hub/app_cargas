<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <svg class="w-3 h-3 mr-2.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="m19.707 9.293-2-2-7-7a1 1 0 0 0-1.414 0l-7 7-2 2a1 1 0 0 0 1.414 1.414L2 10.414V18a2 2 0 0 0 2 2h3a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h3a2 2 0 0 0 2-2v-7.586l.293.293a1 1 0 0 0 1.414-1.414Z"/>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <a href="{{ route('admin.vessel-owners.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                                    Propietarios
                                </a>
                            </div>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">{{ $vesselOwner->legal_name }}</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <div class="mt-2">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ $vesselOwner->legal_name }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        CUIT: {{ $vesselOwner->tax_id }} • {{ $vesselOwner->country->name ?? 'País no definido' }} 
                        • Tipo: {{ \App\Models\VesselOwner::TRANSPORTISTA_TYPES[$vesselOwner->transportista_type] ?? $vesselOwner->transportista_type }}
                    </p>
                </div>
            </div>
            <div class="flex space-x-2">
                @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                    <a href="{{ route('admin.vessel-owners.edit', $vesselOwner) }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar Propietario
                    </a>
                @endif
                <a href="{{ route('admin.vessel-owners.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Encabezado con estadísticas críticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <!-- Estado del Propietario -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $vesselOwner->status === 'active' ? 'bg-green-500' : ($vesselOwner->status === 'inactive' ? 'bg-yellow-500' : ($vesselOwner->status === 'suspended' ? 'bg-red-500' : 'bg-gray-500')) }} rounded-full flex items-center justify-center">
                                    @if($vesselOwner->status === 'active')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif($vesselOwner->status === 'suspended')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Estado</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ \App\Models\VesselOwner::STATUSES[$vesselOwner->status] ?? $vesselOwner->status }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verificación Fiscal -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $vesselOwner->tax_id_verified_at ? 'bg-green-500' : 'bg-yellow-500' }} rounded-full flex items-center justify-center">
                                    @if($vesselOwner->tax_id_verified_at)
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Verificación</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $vesselOwner->tax_id_verified_at ? 'Verificado' : 'Pendiente' }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total de Embarcaciones -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Embarcaciones</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_vessels'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Webservices -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $vesselOwner->webservice_authorized ? 'bg-green-500' : 'bg-gray-400' }} rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Webservices</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $vesselOwner->webservice_authorized ? 'Autorizado' : 'No autorizado' }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Contenido Principal -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Columna Principal (2/3) -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Información Básica del Propietario -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información Básica
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $vesselOwner->legal_name }}</dd>
                                </div>
                                
                                @if($vesselOwner->commercial_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->commercial_name }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $vesselOwner->tax_id }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->country->name ?? 'No definido' }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Transportista</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $vesselOwner->transportista_type === 'O' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ \App\Models\VesselOwner::TRANSPORTISTA_TYPES[$vesselOwner->transportista_type] ?? $vesselOwner->transportista_type }}
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Empresa Asociada</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($vesselOwner->company)
                                            <a href="{{ route('admin.companies.show', $vesselOwner->company) }}" class="text-blue-600 hover:text-blue-900">
                                                {{ $vesselOwner->company->legal_name }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">No asignada</span>
                                        @endif
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    @if($vesselOwner->email || $vesselOwner->phone || $vesselOwner->address)
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información de Contacto
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @if($vesselOwner->email)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="mailto:{{ $vesselOwner->email }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $vesselOwner->email }}
                                        </a>
                                    </dd>
                                </div>
                                @endif

                                @if($vesselOwner->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="tel:{{ $vesselOwner->phone }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $vesselOwner->phone }}
                                        </a>
                                    </dd>
                                </div>
                                @endif

                                @if($vesselOwner->address)
                                <div class="md:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $vesselOwner->address }}
                                        @if($vesselOwner->city), {{ $vesselOwner->city }}@endif
                                        @if($vesselOwner->postal_code) ({{ $vesselOwner->postal_code }})@endif
                                    </dd>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Embarcaciones Asociadas -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Embarcaciones ({{ $stats['total_vessels'] }})
                                </h3>
                                @if($stats['total_vessels'] > 0)
                                    <span class="text-sm text-gray-500">
                                        {{ $stats['active_vessels'] }} activas, {{ $stats['inactive_vessels'] }} inactivas
                                    </span>
                                @endif
                            </div>

                            @if($vesselOwner->vessels->count() > 0)
                                <div class="space-y-3">
                                    @foreach($vesselOwner->vessels->take(5) as $vessel)
                                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900">{{ $vessel->name }}</p>
                                                        <p class="text-sm text-gray-500">
                                                            Matrícula: {{ $vessel->registration_number }}
                                                            @if($vessel->vesselType)
                                                                • {{ $vessel->vesselType->name }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $vessel->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $vessel->active ? 'Activa' : 'Inactiva' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($vesselOwner->vessels->count() > 5)
                                        <div class="text-center pt-3">
                                            <p class="text-sm text-gray-500">
                                                Y {{ $vesselOwner->vessels->count() - 5 }} embarcaciones más...
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin embarcaciones</h3>
                                    <p class="mt-1 text-sm text-gray-500">Este propietario no tiene embarcaciones registradas.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Actividad Reciente -->
                    @if(isset($recentActivity) && $recentActivity->count() > 0)
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Actividad Reciente (Últimos 30 días)
                            </h3>
                            <div class="space-y-3">
                                @foreach($recentActivity as $vessel)
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-2 h-2 bg-blue-400 rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900">
                                                <span class="font-medium">{{ $vessel->name }}</span> fue actualizada
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ $vessel->updated_at->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Notas -->
                    @if($vesselOwner->notes)
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Notas y Observaciones
                            </h3>
                            <div class="prose prose-sm max-w-none">
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $vesselOwner->notes }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>

                <!-- Columna Lateral (1/3) -->
                <div class="space-y-6">
                    
                    <!-- Acciones Rápidas -->
                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Acciones Rápidas
                            </h3>
                            <div class="space-y-3">
                                <form method="POST" action="{{ route('admin.vessel-owners.toggle-status', $vesselOwner) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white {{ $vesselOwner->status === 'active' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700' }}">
                                        @if($vesselOwner->status === 'active')
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Desactivar
                                        @else
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M19 10a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Activar
                                        @endif
                                    </button>
                                </form>

                                @if(!$vesselOwner->tax_id_verified_at)
                                <form method="POST" action="{{ route('admin.vessel-owners.verify', $vesselOwner) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Verificar CUIT
                                    </button>
                                </form>
                                @endif

                                @if(auth()->user()->hasRole('super-admin'))
                                <button type="button" 
                                        onclick="openTransferModal()"
                                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                    </svg>
                                    Transferir Empresa
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Información de Auditoría -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información de Auditoría
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Empresa Asociada</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $vesselOwner->company->legal_name ?? 'No asignada' }}
                                    </dd>
                                    @if($vesselOwner->company)
                                        <dd class="text-xs text-gray-500">
                                            CUIT: {{ $vesselOwner->company->tax_id }}
                                        </dd>
                                    @endif
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha de Creación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->created_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->created_at->diffForHumans() }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Última Modificación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->updated_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->updated_at->diffForHumans() }}</dd>
                                </div>

                                @if($vesselOwner->createdByUser)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Creado Por</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->createdByUser->name }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->createdByUser->email }}</dd>
                                </div>
                                @endif

                                @if($vesselOwner->updatedByUser)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Modificado Por</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->updatedByUser->name }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->updatedByUser->email }}</dd>
                                </div>
                                @endif

                                @if($vesselOwner->tax_id_verified_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Verificado</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->tax_id_verified_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->tax_id_verified_at->diffForHumans() }}</dd>
                                </div>
                                @endif

                                @if($vesselOwner->last_activity_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Última Actividad</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $vesselOwner->last_activity_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $vesselOwner->last_activity_at->diffForHumans() }}</dd>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Webservices -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Configuración
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Webservices</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $vesselOwner->webservice_authorized ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $vesselOwner->webservice_authorized ? 'Autorizado' : 'No autorizado' }}
                                        </span>
                                    </dd>
                                </div>

                                @if($vesselOwner->webservice_config)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Configuración API</dt>
                                    <dd class="mt-1 text-xs text-gray-600 font-mono bg-gray-50 p-2 rounded">
                                        {{ json_encode($vesselOwner->webservice_config, JSON_PRETTY_PRINT) }}
                                    </dd>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- Modal de Transferencia (Solo Super Admin) -->
    @if(auth()->user()->hasRole('super-admin'))
    <div id="transferModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Transferir Propietario</h3>
                <form method="POST" action="{{ route('admin.vessel-owners.transfer', $vesselOwner) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="new_company_id" class="block text-sm font-medium text-gray-700">Nueva Empresa</label>
                            <select name="new_company_id" id="new_company_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar empresa...</option>
                                @foreach(\App\Models\Company::where('id', '!=', $vesselOwner->company_id)->orderBy('legal_name')->get() as $company)
                                    <option value="{{ $company->id }}">{{ $company->legal_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="transfer_vessels" id="transfer_vessels" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="transfer_vessels" class="ml-2 block text-sm text-gray-700">
                                Transferir también las embarcaciones
                            </label>
                        </div>

                        <div>
                            <label for="transfer_reason" class="block text-sm font-medium text-gray-700">Motivo de transferencia</label>
                            <textarea name="transfer_reason" id="transfer_reason" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeTransferModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Transferir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <script>
        function openTransferModal() {
            document.getElementById('transferModal').classList.remove('hidden');
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('transferModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTransferModal();
            }
        });
    </script>

</x-app-layout>