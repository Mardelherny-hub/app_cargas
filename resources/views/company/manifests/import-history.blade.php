<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📥 Importar Manifiestos
            </h2>
            <a href="{{ route('company.manifests.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                ← Volver a Manifiestos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Información de formatos soportados -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            🤖 Auto-detección de Formato Activada
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>
                                El sistema detectará automáticamente el formato del archivo y usará el parser apropiado.
                                <strong>Formatos soportados:</strong> KLine.DAT, PARANA.xlsx, Guaran.csv
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de importación -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📤 Subir Archivo de Manifiesto</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Seleccione el archivo de manifiesto para importar. El sistema detectará automáticamente el formato.
                    </p>
                </div>

                <form action="{{ route('company.manifests.import.store') }}" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      class="px-6 py-4 space-y-6">
                    @csrf

                    <!-- Selección de archivo -->
                    <div>
                        <label for="manifest_file" class="block text-sm font-medium text-gray-700 mb-2">
                            📎 Archivo de Manifiesto
                        </label>
                        <input type="file" 
                               id="manifest_file" 
                               name="manifest_file" 
                               accept=".dat,.xlsx,.csv,.xml,.txt,.edi" 
                               required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        
                        @error('manifest_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        
                        <p class="mt-1 text-xs text-gray-500">
                            Tamaño máximo: 10MB. Formatos: .dat, .xlsx, .csv, .xml, .txt, .edi
                        </p>
                    </div>

                    <!-- Botón de importación -->
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <strong>Importante:</strong> El archivo será procesado y se creará un nuevo viaje con los datos importados.
                        </div>
                        
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Importar Manifiesto
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información de parsers disponibles -->
            @if(isset($supportedFormats) && $supportedFormats)
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($supportedFormats as $format => $info)
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">
                        {{ $info['icon'] ?? '📄' }} {{ $format }}
                    </h4>
                    <p class="text-sm text-gray-600 mb-2">
                        {{ $info['description'] ?? 'Formato de manifiesto' }}
                    </p>
                    <div class="text-xs text-green-600">
                        ✅ Parser disponible
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <!-- Información por defecto si no hay supportedFormats -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- KLine DAT -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">🚢 KLine.DAT</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        Archivos de datos de K-Line en formato DAT
                    </p>
                    <div class="text-xs text-green-600">✅ Parser disponible</div>
                </div>

                <!-- PARANA Excel -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">📊 PARANA.xlsx</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        Formato Excel MAERSK con 73 columnas
                    </p>
                    <div class="text-xs text-green-600">✅ Parser disponible</div>
                </div>

                <!-- Guaran CSV -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">📝 Guaran.csv</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        CSV consolidado multi-línea
                    </p>
                    <div class="text-xs text-green-600">✅ Parser disponible</div>
                </div>
            </div>
            @endif

            <!-- Historial reciente -->
            <div class="mt-6">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">📜 Importaciones Recientes</h3>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-gray-600">
                            No hay importaciones recientes. 
                            <a href="{{ route('company.manifests.import.history') }}" class="text-blue-600 hover:text-blue-500">
                                Ver historial completo →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Agregar indicador de progreso al formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Procesando...
            `;
        });
        
        // Información del archivo seleccionado
        document.getElementById('manifest_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('Archivo seleccionado:', file.name, 'Tamaño:', (file.size / 1024 / 1024).toFixed(2) + 'MB');
            }
        });
    </script>
    @endpush
</x-app-layout>