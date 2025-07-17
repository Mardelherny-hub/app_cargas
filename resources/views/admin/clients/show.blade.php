<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $client->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->getFormattedTaxId() }} • {{ $client->country->name ?? 'País no definido' }}
                </p>
            </div>
            <div class="flex space-x-2">
                <!-- CORRECCIÓN: Permisos simplificados para base compartida -->
                @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                    <a href="{{ route('admin.clients.edit', $client) }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar Cliente
                    </a>
                @endif
                <a href="{{ route('admin.clients.index') }}" 
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
            
            <!-- Encabezado con información crítica -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <!-- Estado del Cliente -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $client->status === 'active' ? 'bg-green-500' : ($client->status === 'inactive' ? 'bg-red-500' : 'bg-yellow-500') }} rounded-full flex items-center justify-center">
                                    @if($client->status === 'active')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif($client->status === 'inactive')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Estado</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ \App\Models\Client::STATUSES[$client->status] ?? $client->status }}
                                    </dd>
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
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Verificación CUIT/RUC</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $client->verified_at ? 'Verificado' : 'Pendiente' }}
                                    </dd>
                                    @if($client->verified_at)
                                        <dd class="text-sm text-gray-500">
                                            {{ $client->verified_at->format('d/m/Y H:i') }}
                                        </dd>
                                    @endif
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
                                <!-- CORRECCIÓN: Solo colores para tipos válidos (sin owner) -->
                                <div class="w-8 h-8 {{ $client->client_type === 'shipper' ? 'bg-green-500' : ($client->client_type === 'consignee' ? 'bg-blue-500' : ($client->client_type === 'notify_party' ? 'bg-yellow-500' : 'bg-gray-500')) }} rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        @if($client->client_type === 'shipper')
                                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                        @elseif($client->client_type === 'consignee')
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        @else
                                            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 01-3.46 0"/>
                                        @endif
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Tipo de Cliente</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ \App\Models\Client::CLIENT_TYPES[$client->client_type] ?? $client->client_type }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $client->hasCompleteContactInfo() ? 'bg-green-500' : 'bg-yellow-500' }} rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Contacto</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $client->hasCompleteContactInfo() ? 'Completo' : 'Incompleto' }}
                                    </dd>
                                    @if($client->getPrimaryEmail())
                                        <dd class="text-sm text-gray-500">
                                            {{ $client->getPrimaryEmail() }}
                                        </dd>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Detallada del Cliente -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Columna Principal: Información Básica -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Datos Básicos -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h1a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Información Básica</h3>
                            </div>
                            
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->legal_name }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->getFormattedTaxId() }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <span>{{ $client->country->name ?? 'No definido' }}</span>
                                            @if($client->country)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $client->country->iso_code }}
                                                </span>
                                            @endif
                                        </div>
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->documentType->name ?? 'No definido' }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Puerto Principal</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->primaryPort->name ?? 'No definido' }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Aduana Habitual</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->customOffice->name ?? 'No definida' }}</dd>
                                </div>
                            </dl>
                            
                            @if($client->notes)
                                <div class="mt-6">
                                    <dt class="text-sm font-medium text-gray-500">Observaciones</dt>
                                    <dd class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md">{{ $client->notes }}</dd>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Información de Contacto Detallada -->
                    @if($client->activeContacts->count() > 0)
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">Información de Contacto</h3>
                                    </div>
                                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                                        <a href="{{ route('admin.clients.edit', $client) }}" 
                                           class="text-sm bg-blue-100 text-blue-800 hover:bg-blue-200 px-3 py-1 rounded-md font-medium">
                                            Editar Contactos
                                        </a>
                                    @endif
                                </div>
                                
                                <div class="space-y-4">
                                    @foreach($client->activeContacts as $contact)
                                        <div class="border rounded-lg p-4 {{ $contact->is_primary ? 'border-blue-200 bg-blue-50' : 'border-gray-200' }}">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center">
                                                    @if($contact->is_primary)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                                                            Principal
                                                        </span>
                                                    @endif
                                                    <span class="text-sm font-medium text-gray-900">
                                                        {{ $contact->contact_name ?: 'Contacto' }}
                                                    </span>
                                                </div>
                                                <span class="text-xs text-gray-500">
                                                    {{ $contact->active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>
                                            
                                            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                                @if($contact->email)
                                                    <div>
                                                        <dt class="text-xs font-medium text-gray-500">Email</dt>
                                                        <dd class="text-sm text-gray-900">{{ $contact->email }}</dd>
                                                    </div>
                                                @endif
                                                
                                                @if($contact->phone)
                                                    <div>
                                                        <dt class="text-xs font-medium text-gray-500">Teléfono</dt>
                                                        <dd class="text-sm text-gray-900">{{ $contact->phone }}</dd>
                                                    </div>
                                                @endif
                                                
                                                @if($contact->mobile_phone)
                                                    <div>
                                                        <dt class="text-xs font-medium text-gray-500">Móvil</dt>
                                                        <dd class="text-sm text-gray-900">{{ $contact->mobile_phone }}</dd>
                                                    </div>
                                                @endif
                                                
                                                @if($contact->address_line_1)
                                                    <div class="sm:col-span-2">
                                                        <dt class="text-xs font-medium text-gray-500">Dirección</dt>
                                                        <dd class="text-sm text-gray-900">
                                                            {{ $contact->address_line_1 }}
                                                            @if($contact->address_line_2), {{ $contact->address_line_2 }}@endif
                                                            @if($contact->city), {{ $contact->city }}@endif
                                                            @if($contact->state_province), {{ $contact->state_province }}@endif
                                                            @if($contact->postal_code) ({{ $contact->postal_code }})@endif
                                                        </dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Sin información de contacto -->
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin información de contacto</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Este cliente no tiene información de contacto registrada.
                                        Es recomendable agregar al menos un email y dirección.
                                    </p>
                                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                                        <div class="mt-4">
                                            <a href="{{ route('admin.clients.edit', $client) }}" 
                                               class="text-sm bg-yellow-100 text-yellow-800 hover:bg-yellow-200 px-3 py-2 rounded-md font-medium">
                                                Agregar Información de Contacto
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Columna Lateral: Metadatos y Acciones -->
                <div class="space-y-6">
                    
                    <!-- CORRECCIÓN: Información de auditoría (reemplaza "Empresas Relacionadas") -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información de Auditoría
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Empresa Creadora</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->createdByCompany->commercial_name ?? $client->createdByCompany->legal_name ?? 'Sistema' }}
                                    </dd>
                                    @if($client->createdByCompany)
                                        <dd class="text-xs text-gray-500">
                                            CUIT: {{ $client->createdByCompany->tax_id }}
                                        </dd>
                                    @endif
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha de Creación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->created_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $client->created_at->diffForHumans() }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Última Modificación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->updated_at->format('d/m/Y H:i') }}</dd>
                                    <dd class="text-xs text-gray-500">{{ $client->updated_at->diffForHumans() }}</dd>
                                </div>
                                
                                @if($client->verified_at)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fecha de Verificación</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $client->verified_at->format('d/m/Y H:i') }}</dd>
                                        <dd class="text-xs text-gray-500">{{ $client->verified_at->diffForHumans() }}</dd>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Acciones</h3>
                                <div class="space-y-3">
                                    
                                    <!-- Verificar -->
                                    @if(!$client->verified_at)
                                        <form method="POST" action="{{ route('admin.clients.verify', $client) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                    onclick="return confirm('¿Confirma que desea verificar este cliente?')">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Verificar Cliente
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Toggle Estado -->
                                    <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white {{ $client->status === 'active' ? 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500' : 'bg-green-600 hover:bg-green-700 focus:ring-green-500' }}"
                                                onclick="return confirm('¿Confirma que desea {{ $client->status === 'active' ? 'desactivar' : 'activar' }} este cliente?')">
                                            @if($client->status === 'active')
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18 12M6 6l12 12"/>
                                                </svg>
                                                Desactivar Cliente
                                            @else
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Activar Cliente
                                            @endif
                                        </button>
                                    </form>

                                    <!-- Editar -->
                                    <a href="{{ route('admin.clients.edit', $client) }}" 
                                       class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Editar Cliente
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Capacidades de Webservices -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Estado para Webservices</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Verificado</span>
                                    <span class="text-sm {{ $client->verified_at ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $client->verified_at ? '✓' : '✗' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Activo</span>
                                    <span class="text-sm {{ $client->status === 'active' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $client->status === 'active' ? '✓' : '✗' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Contacto Completo</span>
                                    <span class="text-sm {{ $client->hasCompleteContactInfo() ? 'text-green-600' : 'text-yellow-600' }}">
                                        {{ $client->hasCompleteContactInfo() ? '✓' : '⚠' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Notificaciones</span>
                                    <span class="text-sm {{ $client->canReceiveEmailNotifications() ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $client->canReceiveEmailNotifications() ? '✓' : '✗' }}
                                    </span>
                                </div>
                                
                                <div class="mt-4 pt-3 border-t border-gray-200">
                                    <span class="text-sm font-medium {{ $client->verified_at && $client->status === 'active' ? 'text-green-600' : 'text-yellow-600' }}">
                                        {{ $client->verified_at && $client->status === 'active' ? 'Listo para Webservices' : 'Requiere verificación' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>