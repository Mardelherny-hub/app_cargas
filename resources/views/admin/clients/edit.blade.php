<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Cliente: {{ $client->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->getFormattedTaxId() }} • {{ $client->country->name ?? 'País no definido' }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.show', $client) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancelar
                </a>
                <a href="{{ route('admin.clients.index') }}" 
                   class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="space-y-8">
                @csrf
                @method('PUT')

                <!-- Información Básica del Cliente -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información Básica del Cliente
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 
                                   ($client->status === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ \App\Models\Client::STATUSES[$client->status] ?? $client->status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Razón Social -->
                            <div>
                                <label for="legal_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Razón Social *
                                </label>
                                <input type="text" 
                                       name="legal_name" 
                                       id="legal_name" 
                                       value="{{ old('legal_name', $client->legal_name) }}"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('legal_name') border-red-500 @enderror">
                                @error('legal_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CUIT/RUC -->
                            <div>
                                <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    CUIT/RUC *
                                </label>
                                <input type="text" 
                                       name="tax_id" 
                                       id="tax_id" 
                                       value="{{ old('tax_id', $client->tax_id) }}"
                                       maxlength="11"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('tax_id') border-red-500 @enderror">
                                @error('tax_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- País -->
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    País *
                                </label>
                                <select name="country_id" 
                                        id="country_id" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('country_id') border-red-500 @enderror">
                                    <option value="">Seleccionar país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" 
                                                {{ old('country_id', $client->country_id) == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Documento -->
                            <div>
                                <label for="document_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Documento *
                                </label>
                                <select name="document_type_id" 
                                        id="document_type_id" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('document_type_id') border-red-500 @enderror">
                                    <option value="">Seleccionar tipo</option>
                                    @foreach($documentTypes as $docType)
                                        <option value="{{ $docType->id }}" 
                                                {{ old('document_type_id', $client->document_type_id) == $docType->id ? 'selected' : '' }}>
                                            {{ $docType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_type_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Cliente -->
                            <div>
                                <label for="client_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Cliente *
                                </label>
                                <select name="client_type" 
                                        id="client_type" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('client_type') border-red-500 @enderror">
                                    @foreach(\App\Models\Client::getClientTypeOptions() as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('client_type', $client->client_type) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            @if(auth()->user()->hasRole('super-admin'))
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                        Estado
                                    </label>
                                    <select name="status" 
                                            id="status" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        @foreach(\App\Models\Client::getStatusOptions() as $key => $label)
                                            <option value="{{ $key }}" 
                                                    {{ old('status', $client->status) == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <!-- Puerto Principal -->
                            <div>
                                <label for="primary_port_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Puerto Principal
                                </label>
                                <select name="primary_port_id" 
                                        id="primary_port_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Sin puerto principal</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('primary_port_id', $client->primary_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Aduana -->
                            <div>
                                <label for="customs_offices_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Aduana Habitual
                                </label>
                                <select name="customs_offices_id" 
                                        id="customs_offices_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Sin aduana habitual</option>
                                    @foreach($customsOffices as $customs)
                                        <option value="{{ $customs->id }}" 
                                                {{ old('customs_offices_id', $client->customs_offices_id) == $customs->id ? 'selected' : '' }}>
                                            {{ $customs->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Observaciones
                            </label>
                            <textarea name="notes" 
                                      id="notes" 
                                      rows="3"
                                      placeholder="Observaciones internas sobre el cliente..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $client->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información de Contacto
                            </h3>
                            @if($client->primaryContact)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                    </svg>
                                    Contacto Registrado
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Sin Contacto
                                </span>
                            @endif
                        </div>

                        <!-- Emails y Teléfonos -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Emails -->
                            <div class="space-y-4">
                                <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2">
                                    📧 Emails
                                </h4>
                                
                                <div>
                                    <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Principal
                                    </label>
                                    <input type="email" 
                                           name="contact_email" 
                                           id="contact_email" 
                                           value="{{ old('contact_email', $client->primaryContact?->email) }}"
                                           placeholder="correo@empresa.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('contact_email') border-red-500 @enderror">
                                    @error('contact_email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="contact_secondary_email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Secundario
                                    </label>
                                    <input type="email" 
                                           name="contact_secondary_email" 
                                           id="contact_secondary_email" 
                                           value="{{ old('contact_secondary_email', $client->primaryContact?->secondary_email) }}"
                                           placeholder="alternativo@empresa.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <!-- Teléfonos -->
                            <div class="space-y-4">
                                <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2">
                                    📞 Teléfonos
                                </h4>
                                
                                <div>
                                    <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Teléfono Fijo
                                    </label>
                                    <input type="tel" 
                                           name="contact_phone" 
                                           id="contact_phone" 
                                           value="{{ old('contact_phone', $client->primaryContact?->phone) }}"
                                           placeholder="+54 11 4444-5555"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_mobile_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Teléfono Móvil
                                    </label>
                                    <input type="tel" 
                                           name="contact_mobile_phone" 
                                           id="contact_mobile_phone" 
                                           value="{{ old('contact_mobile_phone', $client->primaryContact?->mobile_phone) }}"
                                           placeholder="+54 9 11 5555-6666"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_fax" class="block text-sm font-medium text-gray-700 mb-2">
                                        Fax
                                    </label>
                                    <input type="tel" 
                                           name="contact_fax" 
                                           id="contact_fax" 
                                           value="{{ old('contact_fax', $client->primaryContact?->fax) }}"
                                           placeholder="+54 11 4444-5556"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">
                                📍 Dirección
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label for="contact_address_line_1" class="block text-sm font-medium text-gray-700 mb-2">
                                        Dirección Principal *
                                    </label>
                                    <input type="text" 
                                           name="contact_address_line_1" 
                                           id="contact_address_line_1" 
                                           value="{{ old('contact_address_line_1', $client->primaryContact?->address_line_1) }}"
                                           placeholder="Av. Corrientes 1234"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div class="md:col-span-2">
                                    <label for="contact_address_line_2" class="block text-sm font-medium text-gray-700 mb-2">
                                        Dirección Complementaria
                                    </label>
                                    <input type="text" 
                                           name="contact_address_line_2" 
                                           id="contact_address_line_2" 
                                           value="{{ old('contact_address_line_2', $client->primaryContact?->address_line_2) }}"
                                           placeholder="Piso 5, Oficina B"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_city" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ciudad *
                                    </label>
                                    <input type="text" 
                                           name="contact_city" 
                                           id="contact_city" 
                                           value="{{ old('contact_city', $client->primaryContact?->city) }}"
                                           placeholder="Buenos Aires"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_state_province" class="block text-sm font-medium text-gray-700 mb-2">
                                        Provincia/Estado
                                    </label>
                                    <input type="text" 
                                           name="contact_state_province" 
                                           id="contact_state_province" 
                                           value="{{ old('contact_state_province', $client->primaryContact?->state_province) }}"
                                           placeholder="CABA"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                                        Código Postal
                                    </label>
                                    <input type="text" 
                                           name="contact_postal_code" 
                                           id="contact_postal_code" 
                                           value="{{ old('contact_postal_code', $client->primaryContact?->postal_code) }}"
                                           placeholder="C1043AAZ"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        <!-- Persona de Contacto -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">
                                👤 Persona de Contacto
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="contact_person_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nombre Completo
                                    </label>
                                    <input type="text" 
                                           name="contact_person_name" 
                                           id="contact_person_name" 
                                           value="{{ old('contact_person_name', $client->primaryContact?->contact_person_name) }}"
                                           placeholder="Juan Pérez"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_person_position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cargo/Posición
                                    </label>
                                    <input type="text" 
                                           name="contact_person_position" 
                                           id="contact_person_position" 
                                           value="{{ old('contact_person_position', $client->primaryContact?->contact_person_position) }}"
                                           placeholder="Gerente de Operaciones"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_person_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Teléfono Directo
                                    </label>
                                    <input type="tel" 
                                           name="contact_person_phone" 
                                           id="contact_person_phone" 
                                           value="{{ old('contact_person_phone', $client->primaryContact?->contact_person_phone) }}"
                                           placeholder="+54 9 11 1234-5678"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_person_email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Directo
                                    </label>
                                    <input type="email" 
                                           name="contact_person_email" 
                                           id="contact_person_email" 
                                           value="{{ old('contact_person_email', $client->primaryContact?->contact_person_email) }}"
                                           placeholder="juan.perez@empresa.com"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        <!-- Preferencias de Notificación -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">
                                🔔 Preferencias de Notificación
                            </h4>
                            
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="accepts_email_notifications" 
                                           id="accepts_email_notifications" 
                                           value="1"
                                           {{ old('accepts_email_notifications', $client->primaryContact?->accepts_email_notifications) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="accepts_email_notifications" class="ml-2 text-sm text-gray-700">
                                        Acepta notificaciones por email
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="accepts_sms_notifications" 
                                           id="accepts_sms_notifications" 
                                           value="1"
                                           {{ old('accepts_sms_notifications', $client->primaryContact?->accepts_sms_notifications) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="accepts_sms_notifications" class="ml-2 text-sm text-gray-700">
                                        Acepta notificaciones por SMS
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Notas de Contacto -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">
                                📝 Notas de Contacto
                            </h4>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="contact_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        Notas Generales
                                    </label>
                                    <textarea name="contact_notes" 
                                              id="contact_notes" 
                                              rows="3"
                                              placeholder="Información adicional sobre el contacto..."
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('contact_notes', $client->primaryContact?->notes) }}</textarea>
                                </div>

                                @if(auth()->user()->hasRole('super-admin'))
                                    <div>
                                        <label for="contact_internal_notes" class="block text-sm font-medium text-red-700 mb-2">
                                            Notas Internas (Solo Administradores)
                                        </label>
                                        <textarea name="contact_internal_notes" 
                                                  id="contact_internal_notes" 
                                                  rows="2"
                                                  placeholder="Notas internas confidenciales..."
                                                  class="w-full px-3 py-2 border border-red-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 bg-red-50">{{ old('contact_internal_notes', $client->primaryContact?->internal_notes) }}</textarea>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-3">
                                <a href="{{ route('admin.clients.show', $client) }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                                <a href="{{ route('admin.clients.index') }}" 
                                   class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Volver al Listado
                                </a>
                            </div>
                            
                            <div class="flex space-x-3">
                                @if(auth()->user()->hasRole('super-admin'))
                                    <button type="button" 
                                            onclick="toggleClientStatus()"
                                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }} Cliente
                                    </button>
                                @endif
                                
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>

                        @if($client->verified_at)
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="text-sm text-yellow-700">
                                        <p class="font-medium">Cliente verificado</p>
                                        <p>Si modifica el CUIT/RUC, se deberá reverificar el cliente.</p>
                                        @if(auth()->user()->hasRole('super-admin'))
                                            <div class="mt-2">
                                                <input type="checkbox" id="reverify" name="reverify" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                                <label for="reverify" class="ml-2 text-sm">Reverificar CUIT/RUC</label>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleClientStatus() {
            if (confirm('¿Está seguro de que desea cambiar el estado del cliente?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.clients.toggle-status", $client) }}';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'PATCH';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const legalName = document.getElementById('legal_name').value.trim();
                const taxId = document.getElementById('tax_id').value.trim();
                const countryId = document.getElementById('country_id').value;
                const clientType = document.getElementById('client_type').value;

                if (!legalName || !taxId || !countryId || !clientType) {
                    e.preventDefault();
                    alert('Por favor complete todos los campos obligatorios marcados con *');
                    return false;
                }

                // Validar formato de CUIT/RUC
                if (taxId.length !== 11 || !/^\d+$/.test(taxId)) {
                    e.preventDefault();
                    alert('El CUIT/RUC debe tener exactamente 11 dígitos numéricos');
                    return false;
                }
            });
        });
    </script>
</x-app-layout>