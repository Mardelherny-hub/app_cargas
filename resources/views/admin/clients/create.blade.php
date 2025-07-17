<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Nuevo Cliente
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Complete la información básica y de contacto del cliente
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancelar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <form method="POST" action="{{ route('admin.clients.store') }}" class="space-y-8">
                @csrf

                <!-- Información Básica del Cliente -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h1a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información Básica del Cliente
                            </h3>
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
                                       value="{{ old('legal_name') }}"
                                       required
                                       placeholder="Ej: Transportes Río de la Plata S.A."
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
                                       value="{{ old('tax_id') }}"
                                       maxlength="11"
                                       required
                                       placeholder="20123456789"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('tax_id') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500">Solo números, 11 dígitos</p>
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
                                                {{ old('country_id') == $country->id ? 'selected' : '' }}>
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
                                                {{ old('document_type_id') == $docType->id ? 'selected' : '' }}>
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
                                    <option value="">Seleccionar tipo</option>
                                    @foreach(\App\Models\Client::getClientTypeOptions() as $key => $label)
                                        <option value="{{ $key }}" 
                                                {{ old('client_type') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Empresa Creadora -->
                            <div>
                                <label for="created_by_company_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Empresa Creadora *
                                </label>
                                <select name="created_by_company_id" 
                                        id="created_by_company_id" 
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('created_by_company_id') border-red-500 @enderror">
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" 
                                                {{ old('created_by_company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('created_by_company_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

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
                                                {{ old('primary_port_id') == $port->id ? 'selected' : '' }}>
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
                                                {{ old('customs_offices_id') == $customs->id ? 'selected' : '' }}>
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
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-6">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información de Contacto
                            </h3>
                            <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Recomendado
                            </span>
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
                                           value="{{ old('contact_email') }}"
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
                                           value="{{ old('contact_secondary_email') }}"
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
                                           value="{{ old('contact_phone') }}"
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
                                           value="{{ old('contact_mobile_phone') }}"
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
                                           value="{{ old('contact_fax') }}"
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
                                        Dirección Principal
                                    </label>
                                    <input type="text" 
                                           name="contact_address_line_1" 
                                           id="contact_address_line_1" 
                                           value="{{ old('contact_address_line_1') }}"
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
                                           value="{{ old('contact_address_line_2') }}"
                                           placeholder="Piso 5, Oficina B"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="contact_city" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ciudad
                                    </label>
                                    <input type="text" 
                                           name="contact_city" 
                                           id="contact_city" 
                                           value="{{ old('contact_city') }}"
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
                                           value="{{ old('contact_state_province') }}"
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
                                           value="{{ old('contact_postal_code') }}"
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
                                           value="{{ old('contact_person_name') }}"
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
                                           value="{{ old('contact_person_position') }}"
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
                                           value="{{ old('contact_person_phone') }}"
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
                                           value="{{ old('contact_person_email') }}"
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
                                           {{ old('accepts_email_notifications', true) ? 'checked' : '' }}
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
                                           {{ old('accepts_sms_notifications') ? 'checked' : '' }}
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
                            
                            <div>
                                <label for="contact_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Notas Generales
                                </label>
                                <textarea name="contact_notes" 
                                          id="contact_notes" 
                                          rows="3"
                                          placeholder="Información adicional sobre el contacto..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('contact_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional (Solo para contexto) -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Información importante
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Los campos marcados con * son obligatorios</li>
                                        <li>El CUIT/RUC debe tener exactamente 11 dígitos numéricos</li>
                                        <li>La información de contacto es opcional pero recomendada</li>
                                        <li>Una vez creado, el cliente deberá ser verificado por un administrador</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-3">
                                <a href="{{ route('admin.clients.index') }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                            </div>
                            
                            <div class="flex space-x-3">
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Crear Cliente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const legalName = document.getElementById('legal_name').value.trim();
                const taxId = document.getElementById('tax_id').value.trim();
                const countryId = document.getElementById('country_id').value;
                const documentTypeId = document.getElementById('document_type_id').value;
                const clientType = document.getElementById('client_type').value;
                const companyId = document.getElementById('created_by_company_id').value;

                if (!legalName || !taxId || !countryId || !documentTypeId || !clientType || !companyId) {
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

            // Auto-formatear CUIT mientras se escribe
            const taxIdInput = document.getElementById('tax_id');
            taxIdInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }
                e.target.value = value;
            });
        });
    </script>
</x-app-layout>