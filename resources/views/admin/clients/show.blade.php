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
                @if(auth()->user()->canEditClient($client))
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
                                    @else
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
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
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
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
                                <div class="w-8 h-8 {{ $client->client_type === 'shipper' ? 'bg-green-500' : ($client->client_type === 'consignee' ? 'bg-blue-500' : ($client->client_type === 'notify_party' ? 'bg-yellow-500' : 'bg-purple-500')) }} rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
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
                                <div class="w-8 h-8 {{ $client->hasCompleteContactInfo() ? 'bg-green-500' : 'bg-gray-400' }} rounded-full flex items-center justify-center">
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
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información Principal del Cliente -->
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información del Cliente
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-medium">{{ $client->legal_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $client->getFormattedTaxId() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">País</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->country->name ?? 'No definido' }}</dd>
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
                            <dt class="text-sm font-medium text-gray-500">Aduana</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->customOffice->name ?? 'No definido' }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de Contacto Principal -->
            @if($client->primaryContact)
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información de Contacto Principal
                            </h3>
                            @if($client->primaryContact->verified)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Verificado
                                </span>
                            @endif
                        </div>

                        <!-- Emails y Teléfonos -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Emails</h4>
                                <div class="space-y-2">
                                    @if($client->primaryContact->email)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                            </svg>
                                            <a href="mailto:{{ $client->primaryContact->email }}" 
                                               class="text-blue-600 hover:text-blue-800">
                                                {{ $client->primaryContact->email }}
                                            </a>
                                            <span class="ml-2 text-xs text-gray-500">(Principal)</span>
                                        </div>
                                    @endif
                                    @if($client->primaryContact->secondary_email)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                            </svg>
                                            <a href="mailto:{{ $client->primaryContact->secondary_email }}" 
                                               class="text-blue-600 hover:text-blue-800">
                                                {{ $client->primaryContact->secondary_email }}
                                            </a>
                                            <span class="ml-2 text-xs text-gray-500">(Secundario)</span>
                                        </div>
                                    @endif
                                    @if(!$client->primaryContact->email && !$client->primaryContact->secondary_email)
                                        <p class="text-sm text-gray-500 italic">Sin emails registrados</p>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Teléfonos</h4>
                                <div class="space-y-2">
                                    @if($client->primaryContact->phone)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                            </svg>
                                            <span class="text-gray-900">{{ $client->primaryContact->phone }}</span>
                                            <span class="ml-2 text-xs text-gray-500">(Fijo)</span>
                                        </div>
                                    @endif
                                    @if($client->primaryContact->mobile_phone)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 011 1v11a1 1 0 01-1 1H5a1 1 0 01-1-1V7zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                            </svg>
                                            <span class="text-gray-900">{{ $client->primaryContact->mobile_phone }}</span>
                                            <span class="ml-2 text-xs text-gray-500">(Móvil)</span>
                                        </div>
                                    @endif
                                    @if($client->primaryContact->fax)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm3 2h6v4H7V5zm8 8v2h1v-2h-1zm-2-2H7v4h6v-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-900">{{ $client->primaryContact->fax }}</span>
                                            <span class="ml-2 text-xs text-gray-500">(Fax)</span>
                                        </div>
                                    @endif
                                    @if(!$client->primaryContact->phone && !$client->primaryContact->mobile_phone && !$client->primaryContact->fax)
                                        <p class="text-sm text-gray-500 italic">Sin teléfonos registrados</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Dirección -->
                        @if($client->primaryContact->address_line_1 || $client->primaryContact->city)
                            <div class="mb-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Dirección</h4>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-gray-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            @if($client->primaryContact->address_line_1)
                                                <p class="text-sm text-gray-900">{{ $client->primaryContact->address_line_1 }}</p>
                                            @endif
                                            @if($client->primaryContact->address_line_2)
                                                <p class="text-sm text-gray-600">{{ $client->primaryContact->address_line_2 }}</p>
                                            @endif
                                            <p class="text-sm text-gray-900">
                                                {{ $client->primaryContact->city }}
                                                @if($client->primaryContact->state_province), {{ $client->primaryContact->state_province }}@endif
                                                @if($client->primaryContact->postal_code) - {{ $client->primaryContact->postal_code }}@endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Persona de Contacto -->
                        @if($client->primaryContact->contact_person_name)
                            <div class="mb-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Persona de Contacto</h4>
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $client->primaryContact->contact_person_name }}
                                                @if($client->primaryContact->contact_person_position)
                                                    <span class="text-gray-600">- {{ $client->primaryContact->contact_person_position }}</span>
                                                @endif
                                            </p>
                                            @if($client->primaryContact->contact_person_phone || $client->primaryContact->contact_person_email)
                                                <div class="mt-2 space-y-1">
                                                    @if($client->primaryContact->contact_person_phone)
                                                        <p class="text-xs text-gray-600">📞 {{ $client->primaryContact->contact_person_phone }}</p>
                                                    @endif
                                                    @if($client->primaryContact->contact_person_email)
                                                        <p class="text-xs text-gray-600">
                                                            ✉️ <a href="mailto:{{ $client->primaryContact->contact_person_email }}" class="text-blue-600 hover:text-blue-800">{{ $client->primaryContact->contact_person_email }}</a>
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Preferencias de Notificación -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Preferencias de Notificación</h4>
                            <div class="flex space-x-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           disabled 
                                           {{ $client->primaryContact->accepts_email_notifications ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    <label class="ml-2 text-sm text-gray-600">Notificaciones por Email</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           disabled 
                                           {{ $client->primaryContact->accepts_sms_notifications ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    <label class="ml-2 text-sm text-gray-600">Notificaciones por SMS</label>
                                </div>
                            </div>
                        </div>

                        <!-- Notas de Contacto -->
                        @if($client->primaryContact->notes || $client->primaryContact->internal_notes)
                            <div class="mt-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Notas</h4>
                                @if($client->primaryContact->notes)
                                    <div class="mb-3">
                                        <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notas Generales</label>
                                        <p class="mt-1 text-sm text-gray-700 bg-gray-50 p-3 rounded">{{ $client->primaryContact->notes }}</p>
                                    </div>
                                @endif
                                @if($client->primaryContact->internal_notes)
                                    <div>
                                        <label class="text-xs font-medium text-red-500 uppercase tracking-wider">Notas Internas</label>
                                        <p class="mt-1 text-sm text-gray-700 bg-red-50 p-3 rounded">{{ $client->primaryContact->internal_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <!-- Sin información de contacto -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Sin información de contacto
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Este cliente no tiene información de contacto registrada. Es recomendable agregar al menos un email y dirección.</p>
                                </div>
                                @if(auth()->user()->canEditClient($client))
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
                </div>
            @endif

            <!-- Empresas Relacionadas -->
            @if($client->companyRelations->count() > 0)
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Empresas Relacionadas
                        </h3>
                        <div class="space-y-4">
                            @foreach($client->companyRelations as $relation)
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-medium text-sm">
                                                        {{ strtoupper(substr($relation->company->legal_name, 0, 2)) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-900">
                                                    {{ $relation->company->legal_name }}
                                                </h4>
                                                <p class="text-sm text-gray-500">
                                                    {{ $relation->company->tax_id ?? 'CUIT no disponible' }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                {{ $relation->relation_type === 'customer' ? 'bg-green-100 text-green-800' : 
                                                   ($relation->relation_type === 'provider' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') }}">
                                                {{ ucfirst($relation->relation_type) }}
                                            </span>
                                            @if($relation->active)
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Activa
                                                </span>
                                            @else
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Inactiva
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Notas del Cliente -->
            @if($client->notes)
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Observaciones
                        </h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-700">{{ $client->notes }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Panel de Acciones -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Acciones
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                            @if(!$client->verified_at)
                                <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Verificar Cliente
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                    </svg>
                                    {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>