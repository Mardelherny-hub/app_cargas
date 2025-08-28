<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Conocimiento de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Modificar datos del conocimiento: {{ $billOfLading->bill_number }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                </a>
                <a href="{{ route('company.bills-of-lading.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </a>
            </div>
        </div>
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

            {{-- Mensajes flash de sesión --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-md mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-md mb-6">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-md mb-6">
                    {{ session('warning') }}
                </div>
            @endif

            {{-- Información del estado actual --}}
            @if($billOfLading->status === 'verified')
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-900">
                                Conocimiento Verificado
                            </p>
                            <p class="text-xs text-blue-700">
                                Este conocimiento ha sido verificado. Los cambios pueden requerir nueva verificación.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- COMPONENTE LIVEWIRE PRINCIPAL --}}
            {{-- Aquí es donde llamamos al componente que acabamos de crear --}}
            @livewire('bill-of-lading-edit-form', ['billOfLading' => $billOfLading])

        </div>
    </div>

    {{-- Scripts adicionales si son necesarios --}}
    @push('scripts')
    <script>
        // Confirmación antes de salir si hay cambios no guardados
        let hasUnsavedChanges = false;
        
        // Escuchar eventos Livewire para detectar cambios
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el, component }) => {
                // Detectar si hay cambios en el formulario
                const formElements = el.querySelectorAll('input, select, textarea');
                formElements.forEach(element => {
                    element.addEventListener('change', () => {
                        hasUnsavedChanges = true;
                    });
                });
            });

            // Listener para cuando se envía el formulario exitosamente
            Livewire.on('form-saved', () => {
                hasUnsavedChanges = false;
            });
        });

        // Confirmar antes de salir de la página
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '¿Está seguro de que desea salir? Los cambios no guardados se perderán.';
            }
        });

        // Confirmar antes de usar enlaces de navegación
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('a[href*="/bills-of-lading"]');
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (hasUnsavedChanges && !confirm('¿Está seguro de que desea salir? Los cambios no guardados se perderán.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>