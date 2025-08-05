<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Contactos') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $client->legal_name }} • {{ $client->tax_id }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.clients.show', $client) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Cliente
                </a>
                @can('update', $client)
                    <a href="{{ route('company.clients.edit', $client) }}" 
                       class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar Cliente
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
            <!-- Información del Cliente -->
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $client->legal_name }}</h3>
                            <p class="text-sm text-gray-600">{{ $client->tax_id }}</p>
                            @if($client->commercial_name)
                                <p class="text-sm text-gray-600">{{ $client->commercial_name }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">
                                <strong>País:</strong> {{ $client->country->name ?? 'No especificado' }}
                            </p>
                            <p class="text-sm text-gray-600">
                                <strong>Estado:</strong> 
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $client->status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </p>
                        </div>
                        <div class="text-right">
                            @can('update', $client)
                                <button type="button" 
                                        id="addContactBtn"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Agregar Contacto
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Contactos -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-purple-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Lista de Contactos</h3>
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
                                            <div class="flex items-center mb-3">
                                                @if($contact->contact_person_name)
                                                    <h4 class="text-lg font-medium text-gray-900">
                                                        {{ $contact->contact_person_name }}
                                                    </h4>
                                                @else
                                                    <h4 class="text-lg font-medium text-gray-500 italic">
                                                        Contacto sin nombre
                                                    </h4>
                                                @endif
                                                
                                                @if($contact->is_primary)
                                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Principal
                                                    </span>
                                                @endif
                                                
                                                @if(!$contact->active)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Inactivo
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
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
                                                    <div class="flex items-start text-gray-600 md:col-span-2 lg:col-span-3">
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
                                                <div class="mt-3 p-3 bg-gray-50 rounded text-sm text-gray-700">
                                                    <div class="flex items-start">
                                                        <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                                        </svg>
                                                        <span>{{ $contact->notes }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-3 flex items-center text-xs text-gray-500">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Creado: {{ $contact->created_at->format('d/m/Y H:i') }}
                                                @if($contact->updated_at != $contact->created_at)
                                                    • Actualizado: {{ $contact->updated_at->format('d/m/Y H:i') }}
                                                @endif
                                            </div>
                                        </div>

                                        @can('update', $client)
                                            <div class="flex flex-col space-y-2 ml-4">
                                                <button type="button" 
                                                        class="edit-contact text-yellow-600 hover:text-yellow-800 text-sm font-medium"
                                                        data-contact-id="{{ $contact->id }}">
                                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Editar
                                                </button>
                                                @if(!$contact->is_primary)
                                                    <button type="button" 
                                                            class="delete-contact text-red-600 hover:text-red-800 text-sm font-medium"
                                                            data-contact-id="{{ $contact->id }}">
                                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        Eliminar
                                                    </button>
                                                @endif
                                            </div>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin contactos registrados</h3>
                            <p class="mt-1 text-sm text-gray-500">Este cliente no tiene contactos adicionales registrados.</p>
                            @can('update', $client)
                                <div class="mt-6">
                                    <button type="button" 
                                            id="addFirstContactBtn"
                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Agregar Primer Contacto
                                    </button>
                                </div>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('update', $client)
    <!-- Modal para agregar/editar contacto -->
    <div id="contactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Agregar Nuevo Contacto</h3>
                    <button type="button" id="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="contactForm" method="POST">
                    @csrf
                    <input type="hidden" id="methodField" name="_method" value="POST">
                    <input type="hidden" name="contact_type" value="general">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Nombre del Contacto -->
                        <div>
                            <label for="contact_person_name" class="block text-sm font-medium text-gray-700">Nombre del Contacto</label>
                            <input type="text" 
                                   name="contact_person_name" 
                                   id="contact_person_name"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Nombre completo"
                                   maxlength="255">
                        </div>

                        <!-- Posición/Cargo -->
                        <div>
                            <label for="contact_person_position" class="block text-sm font-medium text-gray-700">Posición/Cargo</label>
                            <input type="text" 
                                   name="contact_person_position" 
                                   id="contact_person_position"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Cargo o posición"
                                   maxlength="255">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="contacto@empresa.com">
                        </div>

                        <!-- Teléfono -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input type="text" 
                                   name="phone" 
                                   id="phone"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="+54 11 1234-5678">
                        </div>

                        <!-- Teléfono Móvil -->
                        <div>
                            <label for="mobile_phone" class="block text-sm font-medium text-gray-700">Teléfono Móvil</label>
                            <input type="text" 
                                   name="mobile_phone" 
                                   id="mobile_phone"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="+54 9 11 1234-5678">
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label for="address_line_1" class="block text-sm font-medium text-gray-700">Dirección</label>
                            <input type="text" 
                                   name="address_line_1" 
                                   id="address_line_1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Dirección completa"
                                   maxlength="255">
                        </div>

                        <!-- Ciudad -->
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">Ciudad</label>
                            <input type="text" 
                                   name="city" 
                                   id="city"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Ciudad"
                                   maxlength="100">
                        </div>

                        <!-- Contacto Principal -->
                        <div>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="is_primary" 
                                       id="is_primary"
                                       value="1"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_primary" class="ml-2 block text-sm font-medium text-gray-700">
                                    Contacto Principal
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Solo puede haber un contacto principal por cliente</p>
                        </div>

                        <!-- Notas -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notas</label>
                            <textarea name="notes" 
                                      id="notes"
                                      rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                      placeholder="Información adicional sobre este contacto"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" 
                                id="cancelBtn"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md text-sm font-medium">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <span id="submitText">Guardar Contacto</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('contactModal');
            const form = document.getElementById('contactForm');
            const modalTitle = document.getElementById('modalTitle');
            const submitText = document.getElementById('submitText');
            const methodField = document.getElementById('methodField');
            
            let isEditing = false;
            let editingContactId = null;

            // Botones para abrir modal
            document.getElementById('addContactBtn')?.addEventListener('click', openAddModal);
            document.getElementById('addFirstContactBtn')?.addEventListener('click', openAddModal);
            
            // Botones para cerrar modal
            document.getElementById('closeModal').addEventListener('click', closeModal);
            document.getElementById('cancelBtn').addEventListener('click', closeModal);

            // Botones de editar contacto
            document.querySelectorAll('.edit-contact').forEach(button => {
                button.addEventListener('click', function() {
                    const contactId = this.dataset.contactId;
                    openEditModal(contactId);
                });
            });

            // Botones de eliminar contacto
            document.querySelectorAll('.delete-contact').forEach(button => {
                button.addEventListener('click', function() {
                    const contactId = this.dataset.contactId;
                    deleteContact(contactId);
                });
            });

            // Envío del formulario
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm();
            });

            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });

            function openAddModal() {
                isEditing = false;
                editingContactId = null;
                modalTitle.textContent = 'Agregar Nuevo Contacto';
                submitText.textContent = 'Guardar Contacto';
                methodField.value = 'POST';
                form.action = `{{ route('company.clients.store-contact', $client) }}`;
                clearForm();
                modal.classList.remove('hidden');
            }

            function openEditModal(contactId) {
                isEditing = true;
                editingContactId = contactId;
                modalTitle.textContent = 'Editar Contacto';
                submitText.textContent = 'Actualizar Contacto';
                methodField.value = 'PUT';
                form.action = `{{ route('company.clients.update-contact', [$client, '__CONTACT_ID__']) }}`.replace('__CONTACT_ID__', contactId);
                
                // Cargar datos del contacto
                loadContactData(contactId);
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
                clearForm();
            }

            function clearForm() {
                form.reset();
                document.querySelectorAll('.text-red-600').forEach(el => el.remove());
            }

            function loadContactData(contactId) {
                // Obtener datos del contacto desde el DOM o mediante AJAX
                const contactElement = document.querySelector(`[data-contact-id="${contactId}"]`).closest('.border');
                
                // Extraer datos básicos del DOM (método simple)
                // En un caso real, se podría hacer una llamada AJAX para obtener todos los datos
                
                // Por ahora, limpiar el formulario y dejarlo listo para edición manual
                clearForm();
                
                // Nota: Aquí se podría implementar una llamada AJAX para cargar los datos completos:
                /*
                fetch(`/company/clients/${clientId}/contacts/${contactId}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('contact_person_name').value = data.contact_person_name || '';
                        document.getElementById('contact_person_position').value = data.contact_person_position || '';
                        // ... llenar otros campos
                    });
                */
            }

            function submitForm() {
                const formData = new FormData(form);
                const submitBtn = form.querySelector('[type="submit"]');
                const originalText = submitText.textContent;
                
                // Mostrar estado de carga
                submitBtn.disabled = true;
                submitText.textContent = isEditing ? 'Actualizando...' : 'Guardando...';

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recargar la página para mostrar los cambios
                        window.location.reload();
                    } else {
                        // Mostrar errores de validación
                        showValidationErrors(data.errors || {});
                        submitBtn.disabled = false;
                        submitText.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud. Por favor, intente nuevamente.');
                    submitBtn.disabled = false;
                    submitText.textContent = originalText;
                });
            }

            function deleteContact(contactId) {
                if (!confirm('¿Está seguro de eliminar este contacto? Esta acción no se puede deshacer.')) {
                    return;
                }

                fetch(`{{ route('company.clients.destroy-contact', [$client, '__CONTACT_ID__']) }}`.replace('__CONTACT_ID__', contactId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recargar la página para mostrar los cambios
                        window.location.reload();
                    } else {
                        alert('Error al eliminar el contacto: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el contacto. Por favor, intente nuevamente.');
                });
            }

            function showValidationErrors(errors) {
                // Limpiar errores anteriores
                document.querySelectorAll('.text-red-600').forEach(el => el.remove());

                // Mostrar nuevos errores
                Object.keys(errors).forEach(field => {
                    const input = document.getElementById(field);
                    if (input) {
                        const error = document.createElement('p');
                        error.className = 'mt-1 text-sm text-red-600';
                        error.textContent = errors[field][0];
                        input.parentNode.appendChild(error);
                    }
                });
            }
        });
    </script>
</x-app-layout>