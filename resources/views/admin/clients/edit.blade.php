<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Cliente: {{ $client->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->getFormattedTaxId() }} • {{ $client->country->name ?? 'País no definido' }}
                    @if($client->verified_at)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">
                            ✓ Verificado
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                            ⚠ Pendiente
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.show', $client) }}" 
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Cliente
                </a>
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
            
            <!-- Estado del Cliente -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                
                <!-- Verificación -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-4">
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
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $client->verified_at ? 'Cliente Verificado' : 'Verificación Pendiente' }}
                                </p>
                                @if($client->verified_at)
                                    <p class="text-xs text-gray-500">{{ $client->verified_at->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 {{ $client->status === 'active' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        @if($client->status === 'active')
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        @else
                                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                                        @endif
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">
                                    Estado: {{ \App\Models\Client::STATUSES[$client->status] ?? $client->status }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-4">
                        <div class="flex space-x-2">
                            @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                                @if($client->verified_at)
                                    <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                                class="text-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 px-2 py-1 rounded font-medium"
                                                onclick="return confirm('¿Desea reverificar este cliente?')"
                                                title="Actualizar fecha de verificación">
                                            🔄 Reverificar
                                        </button>
                                    </form>
                                @endif
                                
                                <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="text-xs {{ $client->status === 'active' ? 'bg-red-100 text-red-800 hover:bg-red-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }} px-2 py-1 rounded font-medium"
                                            onclick="return confirm('¿Confirma cambiar el estado del cliente?')">
                                        {{ $client->status === 'active' ? '🚫 Desactivar' : '✅ Activar' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Errores en el formulario:</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Formulario de Edición -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <form method="POST" action="{{ route('admin.clients.update', $client) }}">
                        @csrf
                        @method('PUT')

                        <!-- Información Básica -->
                        <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center mb-6">
                                    <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Información Básica</h3>
                                </div>

                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                    
                                    <!-- País -->
                                    <div class="sm:col-span-1">
                                        <label for="country_id" class="block text-sm font-medium text-gray-700">
                                            País <span class="text-red-500">*</span>
                                        </label>
                                        <select id="country_id" name="country_id" required
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Seleccione un país</option>
                                            @foreach($countries as $country)
                                                <option value="{{ $country->id }}" {{ old('country_id', $client->country_id) == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('country_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- CUIT/RUC -->
                                    <div class="sm:col-span-1">
                                        <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                            CUIT/RUC <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="tax_id" name="tax_id" 
                                               value="{{ old('tax_id', $client->tax_id) }}" required maxlength="15"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @error('tax_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Razón Social -->
                                    <div class="sm:col-span-2">
                                        <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                            Razón Social <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="legal_name" name="legal_name" 
                                               value="{{ old('legal_name', $client->legal_name) }}" required
                                               minlength="3" maxlength="255"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @error('legal_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Tipo de Cliente -->
                                    <div class="sm:col-span-1">
                                        <label for="client_type" class="block text-sm font-medium text-gray-700">
                                            Tipo de Cliente <span class="text-red-500">*</span>
                                        </label>
                                        <select id="client_type" name="client_type" required
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Seleccione el tipo</option>
                                            <option value="shipper" {{ old('client_type', $client->client_type) === 'shipper' ? 'selected' : '' }}>
                                                Cargador/Exportador
                                            </option>
                                            <option value="consignee" {{ old('client_type', $client->client_type) === 'consignee' ? 'selected' : '' }}>
                                                Consignatario/Importador
                                            </option>
                                            <option value="notify_party" {{ old('client_type', $client->client_type) === 'notify_party' ? 'selected' : '' }}>
                                                Notificatario
                                            </option>
                                        </select>
                                        @error('client_type')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Tipo de Documento -->
                                    <div class="sm:col-span-1">
                                        <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                                            Tipo de Documento
                                        </label>
                                        <select id="document_type_id" name="document_type_id"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Seleccione tipo</option>
                                            @foreach($documentTypes as $type)
                                                <option value="{{ $type->id }}" {{ old('document_type_id', $client->document_type_id) == $type->id ? 'selected' : '' }}>
                                                    {{ $type->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('document_type_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información Complementaria -->
                        <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center mb-6">
                                    <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Información Complementaria</h3>
                                </div>

                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                    
                                    <!-- Puerto Principal -->
                                    <div class="sm:col-span-1">
                                        <label for="primary_port_id" class="block text-sm font-medium text-gray-700">
                                            Puerto Principal
                                        </label>
                                        <select id="primary_port_id" name="primary_port_id"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Seleccione puerto</option>
                                            @foreach($ports as $port)
                                                <option value="{{ $port->id }}" {{ old('primary_port_id', $client->primary_port_id) == $port->id ? 'selected' : '' }}>
                                                    {{ $port->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('primary_port_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Aduana Habitual -->
                                    <div class="sm:col-span-1">
                                        <label for="customs_offices_id" class="block text-sm font-medium text-gray-700">
                                            Aduana Habitual
                                        </label>
                                        <select id="customs_offices_id" name="customs_offices_id"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Seleccione aduana</option>
                                            @foreach($customOffices as $office)
                                                <option value="{{ $office->id }}" {{ old('customs_offices_id', $client->customs_offices_id) == $office->id ? 'selected' : '' }}>
                                                    {{ $office->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('customs_offices_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Observaciones -->
                                    <div class="sm:col-span-2">
                                        <label for="notes" class="block text-sm font-medium text-gray-700">
                                            Observaciones
                                        </label>
                                        <textarea id="notes" name="notes" rows="3" maxlength="1000"
                                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('notes', $client->notes) }}</textarea>
                                        @error('notes')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center mb-6">
                                    <svg class="w-6 h-6 text-purple-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Información de Contacto</h3>
                                </div>

                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                                    
                                    <!-- Email -->
                                    <div class="sm:col-span-1">
                                        <label for="contact_email" class="block text-sm font-medium text-gray-700">
                                            Email Principal
                                        </label>
                                        <input type="email" id="contact_email" name="contact_email" maxlength="100"
                                               value="{{ old('contact_email', $client->primaryContact?->email) }}"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @error('contact_email')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Teléfono -->
                                    <div class="sm:col-span-1">
                                        <label for="contact_phone" class="block text-sm font-medium text-gray-700">
                                            Teléfono
                                        </label>
                                        <input type="tel" id="contact_phone" name="contact_phone" maxlength="50"
                                               value="{{ old('contact_phone', $client->primaryContact?->phone) }}"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @error('contact_phone')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Dirección -->
                                    <div class="sm:col-span-1">
                                        <label for="contact_address" class="block text-sm font-medium text-gray-700">
                                            Dirección
                                        </label>
                                        <textarea id="contact_address" name="contact_address" rows="2" maxlength="500"
                                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">{{ old('contact_address', $client->primaryContact?->address_line_1) }}</textarea>
                                        @error('contact_address')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Ciudad -->
                                    <div class="sm:col-span-1">
                                        <label for="contact_city" class="block text-sm font-medium text-gray-700">
                                            Ciudad
                                        </label>
                                        <input type="text" id="contact_city" name="contact_city" maxlength="100"
                                               value="{{ old('contact_city', $client->primaryContact?->city) }}"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @error('contact_city')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('admin.clients.show', $client) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                        Cancelar
                                    </a>
                                    <button type="submit" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Panel Lateral -->
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
                                        {{ $client->createdByCompany->commercial_name ?? $client->createdByCompany->legal_name ?? 'Sistema' }}
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha de Creación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->created_at->format('d/m/Y H:i') }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Última Modificación</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $client->updated_at->format('d/m/Y H:i') }}</dd>
                                </div>
                                
                                @if($client->verified_at)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fecha de Verificación</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $client->verified_at->format('d/m/Y H:i') }}</dd>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Estado para Webservices -->
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