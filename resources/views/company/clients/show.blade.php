<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalle del Cliente') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->legal_name }} • {{ $client->tax_id }}
                </p>
            </div>
            <div class="flex space-x-2">
                @can('update', $client)
                    <a href="{{ route('company.clients.edit', $client) }}" 
                       class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar
                    </a>
                @endcan
                <a href="{{ route('company.clients.index') }}" 
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Información Principal del Cliente -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Datos Básicos -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Información General</h3>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $client->legal_name }}</dd>
                                </div>

                                @if($client->commercial_name)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->commercial_name }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->documentType->name ?? 'No definido' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Número de Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $client->tax_id }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->country->name ?? 'No especificado' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $client->status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </dd>
                                </div>

                                @if($client->customsOffice)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Aduana Habitual</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->customsOffice->name }}
                                    </dd>
                                </div>
                                @endif

                                @if($client->notes)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500 mb-2">Observaciones</dt>
                                    <dd class="text-sm text-gray-900 bg-gray-50 p-3 rounded">
                                        {{ $client->notes }}
                                    </dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Contactos del Cliente -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-purple-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 01-2 2z"/>
                                    </svg>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Contactos</h3>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        {{ $client->contactData->count() }} contacto{{ $client->contactData->count() !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            @if($client->contactData->count() > 0)
                                <div class="space-y-4">
                                    @foreach($client->contactData->sortByDesc('is_primary') as $contact)
                                        <div class="border border-gray-200 rounded-lg p-4 {{ $contact->is_primary ? 'bg-blue-50 border-blue-200' : '' }}">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center mb-2">
                                                        @if($contact->contact_person_name)
                                                            <h4 class="text-md font-medium text-gray-900">
                                                                {{ $contact->contact_person_name }}
                                                            </h4>
                                                        @else
                                                            <h4 class="text-md font-medium text-gray-500 italic">
                                                                Contacto sin nombre
                                                            </h4>
                                                        @endif
                                                        
                                                        @if($contact->is_primary)
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                </svg>
                                                                Principal
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                                        @if($contact->contact_person_position)
                                                            <div class="flex items-center text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V6m0 0v6m0-6H8m0 0v6m0-6V4a2 2 0 012-2h4a2 2 0 012 2v2z"/>
                                                                </svg>
                                                                <span class="font-medium">{{ $contact->contact_person_position }}</span>
                                                            </div>
                                                        @endif

                                                        @if($contact->email)
                                                            <div class="flex items-center text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 01-2 2z"/>
                                                                </svg>
                                                                <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:text-blue-800">
                                                                    {{ $contact->email }}
                                                                </a>
                                                            </div>
                                                        @endif

                                                        @if($contact->phone)
                                                            <div class="flex items-center text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                                </svg>
                                                                <a href="tel:{{ $contact->phone }}" class="text-blue-600 hover:text-blue-800">
                                                                    {{ $contact->phone }}
                                                                </a>
                                                            </div>
                                                        @endif

                                                        @if($contact->mobile_phone)
                                                            <div class="flex items-center text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"/>
                                                                </svg>
                                                                <a href="tel:{{ $contact->mobile_phone }}" class="text-blue-600 hover:text-blue-800">
                                                                    {{ $contact->mobile_phone }}
                                                                </a>
                                                            </div>
                                                        @endif

                                                        @if($contact->address_line_1)
                                                            <div class="flex items-start text-gray-600 sm:col-span-2">
                                                                <svg class="w-4 h-4 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                </svg>
                                                                <span>
                                                                    {{ $contact->address_line_1 }}
                                                                    @if($contact->city), {{ $contact->city }}@endif
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if($contact->notes)
                                                        <div class="mt-3 p-2 bg-gray-50 rounded text-sm text-gray-700">
                                                            <div class="flex items-start">
                                                                <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                                                </svg>
                                                                <span>{{ $contact->notes }}</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin contactos registrados</h3>
                                    <p class="mt-1 text-sm text-gray-500">No hay información de contacto para este cliente.</p>
                                    @can('update', $client)
                                        <div class="mt-6">
                                            <a href="{{ route('company.clients.edit', $client) }}" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                                Agregar Contacto
                                            </a>
                                        </div>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Panel Lateral con Estadísticas -->
                <div class="space-y-6">
                    <!-- Estado y Verificación -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Estado</dt>
                                        <dd class="text-lg font-medium {{ $client->status === 'active' ? 'text-green-900' : 'text-red-900' }}">
                                            {{ $client->status === 'active' ? 'Activo' : 'Inactivo' }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Auditoría -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Fecha de Registro</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ $client->created_at->format('d/m/Y') }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empresa Creadora -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Registrado por</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ $client->createdByCompany->legal_name ?? 'Sistema' }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto Principal -->
                    @if($client->email || $client->phone)
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Contacto Directo</dt>
                                        <dd class="text-sm text-gray-900 space-y-1">
                                            @if($client->email)
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <a href="mailto:{{ $client->email }}" class="text-blue-600 hover:text-blue-800">
                                                        {{ $client->email }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if($client->phone)
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                    </svg>
                                                    <a href="tel:{{ $client->phone }}" class="text-blue-600 hover:text-blue-800">
                                                        {{ $client->phone }}
                                                    </a>
                                                </div>
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Acciones Rápidas -->
                    @can('update', $client)
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones</h3>
                            <div class="space-y-3">
                                <a href="{{ route('company.clients.edit', $client) }}" 
                                   class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar Cliente
                                </a>
                                
                                <form method="POST" action="{{ route('company.clients.toggle-status', $client) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="w-full {{ $client->status === 'active' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }} text-white px-4 py-2 rounded-md text-sm font-medium flex items-center justify-center"
                                            onclick="return confirm('¿Está seguro de {{ $client->status === 'active' ? 'desactivar' : 'activar' }} este cliente?')">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($client->status === 'active')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            @endif
                                        </svg>
                                        {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }} Cliente
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>