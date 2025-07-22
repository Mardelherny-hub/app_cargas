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
                        <li>
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <a href="{{ route('admin.vessel-owners.show', $vesselOwner) }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                                    {{ $vesselOwner->legal_name }}
                                </a>
                            </div>
                        </li>
                        <li aria-current="page">
                            <div class="flex items-center">
                                <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Editar</span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <div class="mt-2">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Propietario: {{ $vesselOwner->legal_name }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Modifique la información del propietario de embarcaciones
                    </p>
                </div>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.vessel-owners.show', $vesselOwner) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Detalle
                </a>
                <a href="{{ route('admin.vessel-owners.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                
                <!-- Header del formulario -->
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Información del Propietario
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Los campos marcados con * son obligatorios.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.vessel-owners.update', $vesselOwner) }}" id="vesselOwnerForm" class="px-6 py-4">
                    @csrf
                    @method('PUT')

                    <div class="space-y-8">
                        
                        <!-- Datos Legales -->
                        <div>
                            <div class="border-b border-gray-200 pb-4 mb-6">
                                <h4 class="text-md font-medium text-gray-900">Datos Legales</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Empresa Asociada (Solo Admin puede cambiar) -->
                                <div class="md:col-span-2">
                                    <label for="company_id" class="block text-sm font-medium text-gray-700">Empresa Asociada *</label>
                                    <select name="company_id" 
                                            id="company_id" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('company_id') border-red-300 @enderror"
                                            required>
                                        <option value="">Seleccione una empresa</option>
                                        @foreach($companies as $id => $name)
                                            <option value="{{ $id }}" {{ (old('company_id', $vesselOwner->company_id) == $id) ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('company_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- CUIT/RUC -->
                                <div>
                                    <label for="tax_id" class="block text-sm font-medium text-gray-700">CUIT/RUC *</label>
                                    <input type="text" 
                                           name="tax_id" 
                                           id="tax_id" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('tax_id') border-red-300 @enderror"
                                           value="{{ old('tax_id', $vesselOwner->tax_id) }}"
                                           placeholder="20-12345678-9"
                                           maxlength="15"
                                           required>
                                    @error('tax_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- País -->
                                <div>
                                    <label for="country_id" class="block text-sm font-medium text-gray-700">País *</label>
                                    <select name="country_id" 
                                            id="country_id" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('country_id') border-red-300 @enderror"
                                            required>
                                        <option value="">Seleccione un país</option>
                                        @foreach($countries as $id => $name)
                                            <option value="{{ $id }}" {{ (old('country_id', $vesselOwner->country_id) == $id) ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Razón Social -->
                                <div>
                                    <label for="legal_name" class="block text-sm font-medium text-gray-700">Razón Social *</label>
                                    <input type="text" 
                                           name="legal_name" 
                                           id="legal_name" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                           value="{{ old('legal_name', $vesselOwner->legal_name) }}"
                                           placeholder="Razón social oficial"
                                           maxlength="200"
                                           required>
                                    @error('legal_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Nombre Comercial -->
                                <div>
                                    <label for="commercial_name" class="block text-sm font-medium text-gray-700">Nombre Comercial</label>
                                    <input type="text" 
                                           name="commercial_name" 
                                           id="commercial_name" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('commercial_name') border-red-300 @enderror"
                                           value="{{ old('commercial_name', $vesselOwner->commercial_name) }}"
                                           placeholder="Nombre comercial (opcional)"
                                           maxlength="200">
                                    @error('commercial_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Tipo de Transportista -->
                                <div>
                                    <label for="transportista_type" class="block text-sm font-medium text-gray-700">Tipo de Transportista *</label>
                                    <select name="transportista_type" 
                                            id="transportista_type" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('transportista_type') border-red-300 @enderror"
                                            required>
                                        <option value="">Seleccione tipo</option>
                                        @foreach(\App\Models\VesselOwner::TRANSPORTISTA_TYPES as $key => $label)
                                            <option value="{{ $key }}" {{ (old('transportista_type', $vesselOwner->transportista_type) == $key) ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('transportista_type')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Estado -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700">Estado *</label>
                                    <select name="status" 
                                            id="status" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('status') border-red-300 @enderror"
                                            required>
                                        @foreach(\App\Models\VesselOwner::STATUSES as $key => $label)
                                            <option value="{{ $key }}" {{ (old('status', $vesselOwner->status) == $key) ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Información de Contacto -->
                        <div>
                            <div class="border-b border-gray-200 pb-4 mb-6">
                                <h4 class="text-md font-medium text-gray-900">Información de Contacto</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('email') border-red-300 @enderror"
                                           value="{{ old('email', $vesselOwner->email) }}"
                                           placeholder="contacto@empresa.com"
                                           maxlength="100">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Teléfono -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('phone') border-red-300 @enderror"
                                           value="{{ old('phone', $vesselOwner->phone) }}"
                                           placeholder="+54 11 1234-5678"
                                           maxlength="50">
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Dirección -->
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Dirección</label>
                                    <input type="text" 
                                           name="address" 
                                           id="address" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('address') border-red-300 @enderror"
                                           value="{{ old('address', $vesselOwner->address) }}"
                                           placeholder="Calle, número, piso, departamento">
                                    @error('address')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Ciudad -->
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700">Ciudad</label>
                                    <input type="text" 
                                           name="city" 
                                           id="city" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('city') border-red-300 @enderror"
                                           value="{{ old('city', $vesselOwner->city) }}"
                                           placeholder="Ciudad"
                                           maxlength="100">
                                    @error('city')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Código Postal -->
                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Código Postal</label>
                                    <input type="text" 
                                           name="postal_code" 
                                           id="postal_code" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('postal_code') border-red-300 @enderror"
                                           value="{{ old('postal_code', $vesselOwner->postal_code) }}"
                                           placeholder="1234"
                                           maxlength="20">
                                    @error('postal_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Configuración y Observaciones -->
                        <div>
                            <div class="border-b border-gray-200 pb-4 mb-6">
                                <h4 class="text-md font-medium text-gray-900">Configuración y Observaciones</h4>
                            </div>
                            
                            <div class="space-y-6">
                                
                                <!-- Autorización Webservices -->
                                <div class="flex items-center">
                                    <input type="hidden" name="webservice_authorized" value="0">
                                    <input type="checkbox" 
                                           name="webservice_authorized" 
                                           id="webservice_authorized" 
                                           value="1"
                                           {{ old('webservice_authorized', $vesselOwner->webservice_authorized) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="webservice_authorized" class="ml-2 block text-sm font-medium text-gray-700">
                                        Autorizado para Webservices
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">
                                    Permite que este propietario use servicios web para declaraciones automáticas.
                                </p>

                                <!-- Notas y Observaciones -->
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700">Notas y Observaciones</label>
                                    <textarea name="notes" 
                                              id="notes" 
                                              rows="4" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('notes') border-red-300 @enderror"
                                              placeholder="Observaciones, comentarios especiales, historial de cambios..."
                                    >{{ old('notes', $vesselOwner->notes) }}</textarea>
                                    @error('notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Botones de Acción -->
                    <div class="pt-6 border-t border-gray-200 mt-8">
                        <div class="flex justify-between">
                            <div class="flex space-x-3">
                                <button type="submit" 
                                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Guardar Cambios
                                </button>
                                
                                <a href="{{ route('admin.vessel-owners.show', $vesselOwner) }}" 
                                   class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Cancelar
                                </a>
                            </div>

                            <!-- Botón de Eliminación (Solo si no tiene embarcaciones) -->
                            @if(auth()->user()->hasRole('super-admin') && !$vesselOwner->vessels()->exists())
                                <div>
                                    <button type="button" 
                                            onclick="confirmDelete()"
                                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar Propietario
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                </form>

                <!-- Formulario de eliminación separado -->
                @if(auth()->user()->hasRole('super-admin') && !$vesselOwner->vessels()->exists())
                <form id="delete-form"
                      method="POST"
                      action="{{ route('admin.vessel-owners.destroy', $vesselOwner) }}"
                      class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                @endif

            </div>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('vesselOwnerForm');
            const taxIdInput = document.getElementById('tax_id');
            const legalNameInput = document.getElementById('legal_name');

            // Validación de CUIT en tiempo real
            taxIdInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value.length > 0) {
                    // Eliminar caracteres no permitidos (solo números y guiones)
                    this.value = value.replace(/[^0-9\-]/g, '');
                }
            });

            // Validación de formulario antes del envío
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');

                // Verificar campos requeridos
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('border-red-300');
                        
                        // Mostrar mensaje de error si no existe
                        if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('text-red-600')) {
                            const errorMsg = document.createElement('p');
                            errorMsg.className = 'mt-1 text-sm text-red-600';
                            errorMsg.textContent = 'Este campo es obligatorio';
                            field.parentNode.insertBefore(errorMsg, field.nextSibling);
                        }
                    } else {
                        field.classList.remove('border-red-300');
                        
                        // Eliminar mensaje de error si existe
                        if (field.nextElementSibling && field.nextElementSibling.classList.contains('text-red-600')) {
                            field.nextElementSibling.remove();
                        }
                    }
                });

                // Validación específica de email si está presente
                const emailField = document.getElementById('email');
                if (emailField.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailField.value)) {
                        isValid = false;
                        emailField.classList.add('border-red-300');
                        
                        if (!emailField.nextElementSibling || !emailField.nextElementSibling.classList.contains('text-red-600')) {
                            const errorMsg = document.createElement('p');
                            errorMsg.className = 'mt-1 text-sm text-red-600';
                            errorMsg.textContent = 'Ingrese un email válido';
                            emailField.parentNode.insertBefore(errorMsg, emailField.nextSibling);
                        }
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    // Scroll al primer campo con error
                    const firstError = form.querySelector('.border-red-300');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });

            // Limpiar errores cuando el usuario empieza a escribir
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    if (this.classList.contains('border-red-300')) {
                        this.classList.remove('border-red-300');
                        
                        // Eliminar mensaje de error dinámico
                        if (this.nextElementSibling && this.nextElementSibling.classList.contains('text-red-600')) {
                            this.nextElementSibling.remove();
                        }
                    }
                });
            });

            // Auto-format para nombres (capitalizar primera letra)
            legalNameInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = this.value.trim()
                        .toLowerCase()
                        .replace(/\b\w/g, l => l.toUpperCase());
                }
            });
        });

        // Función para confirmar eliminación
        function confirmDelete() {
            if (confirm('¿Está seguro que desea eliminar este propietario?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('delete-form').submit();
            }
        }

        // Mostrar información sobre estado
        document.getElementById('status').addEventListener('change', function() {
            const status = this.value;
            const descriptions = {
                'active': 'El propietario puede operar normalmente',
                'inactive': 'El propietario no puede realizar operaciones',
                'suspended': 'El propietario está temporalmente suspendido',
                'pending_verification': 'Pendiente de verificación de documentos'
            };
            
            // Eliminar descripción anterior si existe
            const existingDesc = this.parentNode.querySelector('.status-description');
            if (existingDesc) {
                existingDesc.remove();
            }
            
            // Mostrar nueva descripción
            if (descriptions[status]) {
                const desc = document.createElement('p');
                desc.className = 'mt-1 text-xs text-gray-500 status-description';
                desc.textContent = descriptions[status];
                this.parentNode.appendChild(desc);
            }
        });

        // Trigger inicial para mostrar descripción del estado actual
        document.getElementById('status').dispatchEvent(new Event('change'));
    </script>

</x-app-layout>