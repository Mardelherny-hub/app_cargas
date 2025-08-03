<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $client->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->commercial_name ?? 'Sin nombre comercial' }} • CUIT/RUC: {{ $client->getFormattedTaxId() }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.clients.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    ← Volver al Listado
                </a>
                @if(!$client->verified_at)
                    <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="inline">
                        @csrf
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                                onclick="return confirm('¿Verificar este cliente?')">
                            Verificar Cliente
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.clients.edit', $client) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Editar Cliente
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Columna Principal: Información del Cliente -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Información Legal -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Información Legal</h3>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
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
                                    <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $client->getFormattedTaxId() }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                        @if($client->country)
                                            <span class="fi fi-{{ strtolower($client->country->alpha2_code) }} mr-2"></span>
                                            {{ $client->country->name }}
                                        @else
                                            Sin especificar
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Documento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->documentType->name ?? 'Sin especificar' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 
                                               ($client->status === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ ucfirst($client->status) }}
                                        </span>
                                        @if($client->verified_at)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Verificado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                Sin verificar
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Información Operativa -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Información Operativa</h3>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                @if($client->address)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->address }}</dd>
                                </div>
                                @endif

                                @if($client->email)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if(str_contains($client->email, ';'))
                                            @foreach(explode(';', $client->email) as $email)
                                                <a href="mailto:{{ trim($email) }}" class="text-blue-600 hover:text-blue-800 block">
                                                    {{ trim($email) }}
                                                </a>
                                            @endforeach
                                        @else
                                            <a href="mailto:{{ $client->email }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $client->email }}
                                            </a>
                                        @endif
                                    </dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Puerto Principal</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->primaryPort->name ?? 'Sin especificar' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Aduana Habitual</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $client->customsOffice->name ?? 'Sin especificar' }}
                                    </dd>
                                </div>
                            </dl>

                            @if($client->notes)
                            <div class="mt-6">
                                <dt class="text-sm font-medium text-gray-500 mb-2">Observaciones</dt>
                                <dd class="text-sm text-gray-900 bg-gray-50 p-3 rounded">
                                    {{ $client->notes }}
                                </dd>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Contactos del Cliente -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-purple-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Contactos</h3>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        {{ $client->contactData->count() }} contacto{{ $client->contactData->count() !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                                <a href="{{ route('admin.clients.edit', $client) }}" 
                                   class="text-sm bg-purple-100 text-purple-800 hover:bg-purple-200 px-3 py-1 rounded-md font-medium">
                                    Editar Contactos
                                </a>
                            </div>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            @if($client->contactData->count() > 0)
                                <div class="space-y-4">
                                    @foreach($client->contactData as $contact)
                                        <div class="border border-gray-200 rounded-lg p-4 {{ $contact->is_primary ? 'border-blue-300 bg-blue-50' : '' }}">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center">
                                                        <h4 class="text-sm font-medium text-gray-900">
                                                            {{ $contact->contact_person_name ?: 'Contacto sin nombre' }}
                                                        </h4>
                                                        @if($contact->is_primary)
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                Principal
                                                            </span>
                                                        @endif
                                                    </div>
                                                    
                                                    @if($contact->contact_person_position)
                                                        <p class="text-sm text-gray-600">{{ $contact->contact_person_position }}</p>
                                                    @endif

                                                    <div class="mt-2 space-y-1">
                                                        @if($contact->email)
                                                            <div class="flex items-center text-sm text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"></path>
                                                                </svg>
                                                                @if(str_contains($contact->email, ';'))
                                                                    <div>
                                                                        @foreach(explode(';', $contact->email) as $email)
                                                                            <a href="mailto:{{ trim($email) }}" class="text-blue-600 hover:text-blue-800 block">
                                                                                {{ trim($email) }}
                                                                            </a>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:text-blue-800">
                                                                        {{ $contact->email }}
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        @if($contact->phone)
                                                            <div class="flex items-center text-sm text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                                </svg>
                                                                <a href="tel:{{ $contact->phone }}" class="text-blue-600 hover:text-blue-800">
                                                                    {{ $contact->phone }}
                                                                </a>
                                                            </div>
                                                        @endif

                                                        @if($contact->mobile_phone)
                                                            <div class="flex items-center text-sm text-gray-600">
                                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"></path>
                                                                </svg>
                                                                <a href="tel:{{ $contact->mobile_phone }}" class="text-blue-600 hover:text-blue-800">
                                                                    {{ $contact->mobile_phone }} (móvil)
                                                                </a>
                                                            </div>
                                                        @endif

                                                        @if($contact->address_line_1)
                                                            <div class="flex items-start text-sm text-gray-600">
                                                                <svg class="w-4 h-4 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                </svg>
                                                                <div>
                                                                    {{ $contact->address_line_1 }}
                                                                    @if($contact->address_line_2)
                                                                        <br>{{ $contact->address_line_2 }}
                                                                    @endif
                                                                    @if($contact->city || $contact->state_province)
                                                                        <br>{{ $contact->city }}{{ $contact->city && $contact->state_province ? ', ' : '' }}{{ $contact->state_province }}
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if($contact->notes)
                                                        <div class="mt-3 text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                                            {{ $contact->notes }}
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">No hay contactos registrados</p>
                                    <div class="mt-4">
                                        <a href="{{ route('admin.clients.edit', $client) }}" 
                                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                                            Agregar Contactos
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Columna Lateral: Metadatos y Acciones -->
                <div class="space-y-6">
                    
                    <!-- Información de Auditoría -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información de Auditoría
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Empresa Creadora</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($client->created_by_company_id === 999)
                                            Sistema (Super Admin)
                                        @else
                                            {{ $client->createdByCompany->commercial_name ?? $client->createdByCompany->legal_name ?? 'No especificada' }}
                                        @endif
                                    </dd>
                                    @if($client->createdByCompany && $client->created_by_company_id !== 999)
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
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Acciones Rápidas
                            </h3>
                            <div class="space-y-3">
                                <a href="{{ route('admin.clients.edit', $client) }}" 
                                   class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium text-center block">
                                    Editar Cliente
                                </a>

                                @if(!$client->verified_at)
                                    <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="w-full">
                                        @csrf
                                        <button type="submit" 
                                                class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                                                onclick="return confirm('¿Verificar este cliente?')">
                                            Verificar Cliente
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="w-full">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="w-full bg-{{ $client->status === 'active' ? 'red' : 'green' }}-600 hover:bg-{{ $client->status === 'active' ? 'red' : 'green' }}-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                                            onclick="return confirm('¿Cambiar estado del cliente?')">
                                        {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }} Cliente
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>