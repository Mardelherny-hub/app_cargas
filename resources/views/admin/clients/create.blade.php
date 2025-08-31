<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Crear Nuevo Cliente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de error --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-md mb-6">
                    <h3 class="font-semibold">Errores encontrados:</h3>
                    <ul class="list-disc pl-5 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.clients.store') }}" method="POST" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-8">
                @csrf

                <!-- Client Data -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-3">Datos del Cliente</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                        <div>
                            <label for="legal_name" class="block text-sm font-medium text-gray-700">Razón Social <span class="text-red-500">*</span></label>
                            <input id="legal_name" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm @error('legal_name') border-red-500 @enderror" type="text" name="legal_name" value="{{ old('legal_name') }}" required />
                            @error('legal_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="commercial_name" class="block text-sm font-medium text-gray-700">Nombre Comercial</label>
                            <input id="commercial_name" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm @error('commercial_name') border-red-500 @enderror" type="text" name="commercial_name" value="{{ old('commercial_name') }}" />
                            @error('commercial_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="tax_id" class="block text-sm font-medium text-gray-700">CUIT / RUC <span class="text-red-500">*</span></label>
                            <input id="tax_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm @error('tax_id') border-red-500 @enderror" type="text" name="tax_id" value="{{ old('tax_id') }}" required />
                            @error('tax_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700">País <span class="text-red-500">*</span></label>
                            <select name="country_id" id="country_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm @error('country_id') border-red-500 @enderror">
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>{{ $country->name }}</option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="document_type_id" class="block text-sm font-medium text-gray-700">Tipo de Documento <span class="text-red-500">*</span></label>
                            <select name="document_type_id" id="document_type_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm @error('document_type_id') border-red-500 @enderror">
                                @foreach($documentTypes as $docType)
                                    <option value="{{ $docType->id }}" {{ old('document_type_id') == $docType->id ? 'selected' : '' }}>{{ $docType->name }}</option>
                                @endforeach
                            </select>
                            @error('document_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Contacts Section -->
                <div class="border-t pt-8" x-data="contactsHandler(<?php echo e(old('contacts') ? collect(old('contacts'))->toJson() : '[]'); ?>)" x-init="init()">
                    <h3 class="text-lg font-medium text-gray-900">Contactos</h3>
                    <p class="text-sm text-gray-500 mt-1">Agregue uno o más contactos. El contacto principal se usará para las comunicaciones por defecto.</p>

                    <div class="mt-4 space-y-6">
                        <template x-for="(contact, index) in contacts" :key="index">
                            <div class="p-4 border rounded-lg bg-gray-50 relative">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-semibold text-gray-800" x-text="'Contacto ' + (index + 1)"></h4>
                                    <div class="flex items-center space-x-4">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="radio" name="primary_contact_selector" :value="index" x-model.number="primaryContactIndex" @change="setPrimary(index)" class="form-radio h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm text-gray-700 font-medium">Principal</span>
                                        </label>
                                        <button type="button" @click="removeContact(index)" x-show="contacts.length > 1" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <input type="hidden" :name="`contacts[${index}][is_primary]`" :value="contact.is_primary ? 1 : 0">
                                    <div>
                                        <label :for="`contact_person_name_${index}`" class="block text-sm font-medium text-gray-700">Nombre del Contacto</label>
                                        <input :id="`contact_person_name_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="text" :name="`contacts[${index}][contact_person_name]`" x-model="contact.contact_person_name" />
                                    </div>
                                    <div>
                                        <label :for="`contact_person_position_${index}`" class="block text-sm font-medium text-gray-700">Cargo</label>
                                        <input :id="`contact_person_position_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="text" :name="`contacts[${index}][contact_person_position]`" x-model="contact.contact_person_position" />
                                    </div>
                                    <div>
                                        <label :for="`email_${index}`" class="block text-sm font-medium text-gray-700">Email</label>
                                        <input :id="`email_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="email" :name="`contacts[${index}][email]`" x-model="contact.email" />
                                    </div>
                                    <div>
                                        <label :for="`phone_${index}`" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                        <input :id="`phone_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="text" :name="`contacts[${index}][phone]`" x-model="contact.phone" />
                                    </div>
                                    <div>
                                        <label :for="`mobile_phone_${index}`" class="block text-sm font-medium text-gray-700">Teléfono Móvil</label>
                                        <input :id="`mobile_phone_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="text" :name="`contacts[${index}][mobile_phone]`" x-model="contact.mobile_phone" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label :for="`address_line_1_${index}`" class="block text-sm font-medium text-gray-700">Dirección</label>
                                        <input :id="`address_line_1_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" type="text" :name="`contacts[${index}][address_line_1]`" x-model="contact.address_line_1" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label :for="`notes_${index}`" class="block text-sm font-medium text-gray-700">Notas</label>
                                        <textarea :id="`notes_${index}`" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" :name="`contacts[${index}][notes]`" x-model="contact.notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addContact()" class="mt-4 bg-blue-100 text-blue-800 hover:bg-blue-200 font-medium py-2 px-4 rounded-md text-sm">
                        + Agregar Contacto
                    </button>
                </div>

                <div class="flex items-center justify-end mt-4 border-t pt-6">
                    <a href="{{ route('admin.clients.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">Cancelar</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
                        {{ __('Crear Cliente') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function contactsHandler(contactsData = []) {
            const initialContacts = contactsData.length > 0 
                ? contactsData.map(c => ({
                    id: c.id || null,
                    contact_person_name: c.contact_person_name || '',
                    contact_person_position: c.contact_person_position || '',
                    email: c.email || '',
                    phone: c.phone || '',
                    mobile_phone: c.mobile_phone || '',
                    address_line_1: c.address_line_1 || '',
                    notes: c.notes || '',
                    is_primary: !!c.is_primary
                }))
                : [{ id: null, contact_person_name: '', contact_person_position: '', email: '', phone: '', mobile_phone: '', address_line_1: '', notes: '', is_primary: true }];

            const primaryIndex = initialContacts.findIndex(c => c.is_primary);

            return {
                contacts: initialContacts,
                primaryContactIndex: primaryIndex !== -1 ? primaryIndex : 0,
                init() {
                    if (this.contacts.length > 0 && this.primaryContactIndex === -1) {
                        this.primaryContactIndex = 0;
                    }
                    this.updatePrimaryFlags();
                },
                addContact() {
                    this.contacts.push({ 
                        id: null, contact_person_name: '', contact_person_position: '', email: '', phone: '', mobile_phone: '', address_line_1: '', notes: '', is_primary: this.contacts.length === 0 
                    });
                    // If it's the first contact, make it primary
                    if (this.contacts.length === 1) {
                        this.setPrimary(0);
                    }
                },
                removeContact(index) {
                    if (this.contacts.length === 1) {
                        alert('Debe haber al menos un contacto.');
                        return;
                    }
                    const wasPrimary = this.primaryContactIndex === index;
                    this.contacts.splice(index, 1);
                    if (wasPrimary) {
                        this.primaryContactIndex = 0;
                    } else if (this.primaryContactIndex > index) {
                        this.primaryContactIndex--;
                    }
                    this.updatePrimaryFlags();
                },
                setPrimary(index) {
                    this.primaryContactIndex = index;
                    this.updatePrimaryFlags();
                },
                updatePrimaryFlags() {
                    this.contacts.forEach((contact, i) => {
                        contact.is_primary = (i == this.primaryContactIndex);
                    });
                }
            }
        }
    </script>
</x-app-layout>