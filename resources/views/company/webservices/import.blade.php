<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Importar Manifiestos') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Subir archivos CSV para procesamiento automático de webservices
                </p>
            </div>
            <a href="{{ route('company.webservices.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver a Webservices
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <!-- Estado del certificado -->
            @if(!$certificateStatus['has_certificate'])
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-yellow-800">
                                Certificado Digital Requerido
                            </h3>
                            <p class="text-sm text-yellow-700 mt-1">
                                Para ambiente de producción necesita cargar un certificado digital. 
                                Puede usar testing para pruebas.
                            </p>
                        </div>
                    </div>
                </div>
            @elseif($certificateStatus['is_expired'])
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-red-800">
                                Certificado Vencido
                            </h3>
                            <p class="text-sm text-red-700 mt-1">
                                El certificado digital venció el {{ $certificateStatus['expires_at'] }}. 
                                Debe renovarlo para usar ambiente de producción.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Formulario de importación -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Subir Archivo de Manifiesto</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Formatos soportados: PARANA (MAERSK), Guaran Fee (Multi-línea)
                    </p>
                </div>

                <form action="{{ route('company.webservices.process-import') }}" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      class="px-6 py-4 space-y-6">
                    @csrf

                    <!-- Archivo CSV - SIMPLIFICADO -->
                    <div>
                        <label for="manifest_file" class="block text-sm font-medium text-gray-700 mb-2">
                            Archivo CSV/TXT
                        </label>
                        <input type="file" 
                               id="manifest_file" 
                               name="manifest_file" 
                               accept=".csv,.txt" 
                               required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        @error('manifest_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tipo de manifiesto -->
                        <div>
                            <label for="manifest_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Manifiesto
                            </label>
                            <select id="manifest_type" name="manifest_type" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                                @foreach($manifestTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('manifest_type', 'auto_detect') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('manifest_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Webservice destino -->
                        <div>
                            <label for="webservice_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Webservice Destino
                            </label>
                            <select id="webservice_type" name="webservice_type" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Seleccionar webservice</option>
                                @foreach($availableWebservices as $key => $wsName)
                                    <option value="{{ $key }}" {{ old('webservice_type') == $key ? 'selected' : '' }}>
                                        {{ $wsName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('webservice_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Ambiente -->
                    <div>
                        <label for="environment" class="block text-sm font-medium text-gray-700 mb-2">
                            Ambiente
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($environments as $env => $label)
                                <div class="relative">
                                    <input id="environment_{{ $env }}" name="environment" type="radio" value="{{ $env }}" class="sr-only" {{ old('environment', 'testing') == $env ? 'checked' : '' }} required>
                                    <label for="environment_{{ $env }}" class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors peer-checked:border-blue-500 peer-checked:bg-blue-50">
                                        <div class="flex-1">
                                            <div class="flex items-center">
                                                <div class="text-sm font-medium text-gray-900">{{ $label }}</div>
                                                @if($env === 'production')
                                                    <svg class="w-4 h-4 text-green-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 text-yellow-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                @if($env === 'production')
                                                    Requiere certificado válido
                                                @else
                                                    Para pruebas y desarrollo
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('environment')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('company.webservices.index') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Importar Manifiesto
                        </button>
                    </div>
                </form>
            </div>

            <!-- Estadísticas recientes -->
            @if($recentImports->count() > 0)
                <div class="mt-6 bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Importaciones Recientes</h3>
                        <p class="text-sm text-gray-600 mt-1">Últimos 7 días</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="space-y-3">
                            @foreach($recentImports as $import)
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($import->date)->format('d/m/Y') }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ ucfirst(str_replace('_', ' ', $import->webservice_type)) }}
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        {{ $import->count }} registros
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
        // Radio button styling simplificado
        document.querySelectorAll('input[name="environment"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('label[for^="environment_"]').forEach(label => {
                    label.classList.remove('border-blue-500', 'bg-blue-50');
                    label.classList.add('border-gray-300');
                });
                
                if (this.checked) {
                    const label = document.querySelector(`label[for="${this.id}"]`);
                    label.classList.remove('border-gray-300');
                    label.classList.add('border-blue-500', 'bg-blue-50');
                }
            });
        });

        // Initialize selected radio
        const checkedRadio = document.querySelector('input[name="environment"]:checked');
        if (checkedRadio) {
            const label = document.querySelector(`label[for="${checkedRadio.id}"]`);
            label.classList.remove('border-gray-300');
            label.classList.add('border-blue-500', 'bg-blue-50');
        }
    </script>
    @endpush
</x-app-layout>