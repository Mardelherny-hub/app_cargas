<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.companies.show', $company) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Certificados') }} - {{ $company->legal_name }}
                </h2>
            </div>
            @if($company->certificate_path)
                <button onclick="testCertificate()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Probar Certificado
                </button>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado Actual del Certificado -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado Actual del Certificado</h3>

                    @if($company->certificate_path)
                        <div class="bg-green-50 rounded-lg p-6">
                            <div class="flex items-start">
                                <svg class="w-8 h-8 text-green-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-green-800 mb-3">Certificado Digital Configurado</h4>

                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-sm font-medium text-green-700">Archivo</dt>
                                            <dd class="text-sm text-green-900">{{ basename($company->certificate_path) }}</dd>
                                        </div>

                                        @if($company->certificate_alias)
                                            <div>
                                                <dt class="text-sm font-medium text-green-700">Alias</dt>
                                                <dd class="text-sm text-green-900">{{ $company->certificate_alias }}</dd>
                                            </div>
                                        @endif

                                        @if($company->certificate_expires_at)
                                            <div>
                                                <dt class="text-sm font-medium text-green-700">Fecha de Vencimiento</dt>
                                                <dd class="text-sm text-green-900">
                                                    {{ $company->certificate_expires_at->format('d/m/Y H:i') }}
                                                    @if($company->certificate_expires_at->isPast())
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                            </svg>
                                                            VENCIDO
                                                        </span>
                                                    @elseif($company->certificate_expires_at->diffInDays() <= 30)
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                            </svg>
                                                            VENCE EN {{ $company->certificate_expires_at->diffInDays() }} DÍAS
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                            VIGENTE
                                                        </span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif

                                        <div>
                                            <dt class="text-sm font-medium text-green-700">Fecha de Subida</dt>
                                            <dd class="text-sm text-green-900">{{ $company->updated_at->format('d/m/Y H:i') }}</dd>
                                        </div>

                                        <div>
                                            <dt class="text-sm font-medium text-green-700">Contraseña</dt>
                                            <dd class="text-sm text-green-900">
                                                {{ $company->certificate_password ? '●●●●●●●●' : 'No configurada' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-yellow-50 rounded-lg p-6">
                            <div class="flex items-start">
                                <svg class="w-8 h-8 text-yellow-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                <div>
                                    <h4 class="text-lg font-medium text-yellow-800 mb-2">No hay certificado configurado</h4>
                                    <p class="text-sm text-yellow-700">
                                        Es necesario subir un certificado digital (.p12 o .pfx) para habilitar los webservices
                                        con la Administración Tributaria (AFIP/SET).
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Subir/Actualizar Certificado -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        {{ $company->certificate_path ? 'Actualizar Certificado' : 'Subir Certificado Digital' }}
                    </h3>

                    <form method="POST" action="{{ route('admin.companies.upload-certificate', $company) }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <!-- Archivo del certificado -->
                        <div>
                            <label for="certificate" class="block text-sm font-medium text-gray-700 mb-2">
                                Archivo del Certificado (.p12 o .pfx) *
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="certificate" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Seleccionar archivo</span>
                                            <input id="certificate"
                                                   name="certificate"
                                                   type="file"
                                                   accept=".p12,.pfx"
                                                   class="sr-only"
                                                   {{ !$company->certificate_path ? 'required' : '' }}
                                                   onchange="showFileName(this)">
                                        </label>
                                        <p class="pl-1">o arrastrar aquí</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Archivos .p12 o .pfx hasta 10MB
                                    </p>
                                    <div id="file-info" class="mt-2 text-sm text-gray-600 hidden"></div>
                                </div>
                            </div>
                            @error('certificate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Contraseña del certificado -->
                        <div>
                            <label for="certificate_password" class="block text-sm font-medium text-gray-700">
                                Contraseña del Certificado *
                            </label>
                            <input type="password"
                                   name="certificate_password"
                                   id="certificate_password"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('certificate_password') border-red-300 @enderror"
                                   {{ !$company->certificate_path ? 'required' : '' }}
                                   placeholder="Ingresa la contraseña del archivo .p12/.pfx">
                            @error('certificate_password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Esta contraseña se almacenará de forma segura y encriptada
                            </p>
                        </div>

                        <!-- Alias del certificado -->
                        <div>
                            <label for="certificate_alias" class="block text-sm font-medium text-gray-700">
                                Alias del Certificado
                            </label>
                            <input type="text"
                                   name="certificate_alias"
                                   id="certificate_alias"
                                   value="{{ old('certificate_alias', $company->certificate_alias) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('certificate_alias') border-red-300 @enderror"
                                   placeholder="Nombre identificativo del certificado">
                            @error('certificate_alias')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Opcional: Nombre para identificar fácilmente este certificado
                            </p>
                        </div>

                        <!-- Fecha de vencimiento -->
                        <div>
                            <label for="certificate_expires_at" class="block text-sm font-medium text-gray-700">
                                Fecha de Vencimiento
                            </label>
                            <input type="datetime-local"
                                   name="certificate_expires_at"
                                   id="certificate_expires_at"
                                   value="{{ old('certificate_expires_at', $company->certificate_expires_at ? $company->certificate_expires_at->format('Y-m-d\TH:i') : '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('certificate_expires_at') border-red-300 @enderror">
                            @error('certificate_expires_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                El sistema te avisará cuando el certificado esté próximo a vencer
                            </p>
                        </div>

                        <!-- Botón de envío -->
                        <div class="flex items-center justify-between pt-4">
                            <div>
                                <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    {{ $company->certificate_path ? 'Actualizar Certificado' : 'Subir Certificado' }}
                                </button>
                            </div>

                            @if($company->certificate_path)
                                <button type="button"
                                        onclick="return confirm('¿Estás seguro de que quieres eliminar el certificado actual? Esto deshabilitará los webservices.') && document.getElementById('delete-certificate-form').submit()"
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Eliminar Certificado
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información de Seguridad -->
            <div class="bg-blue-50 rounded-lg p-6">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-blue-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h4 class="text-lg font-medium text-blue-800 mb-3">Información de Seguridad</h4>
                        <ul class="space-y-2 text-sm text-blue-700">
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Los certificados se almacenan de forma segura en el servidor
                            </li>
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Las contraseñas se encriptan antes de ser guardadas
                            </li>
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Solo el certificado válido permite acceso a los webservices
                            </li>
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                El sistema notifica automáticamente cuando el certificado está próximo a vencer
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Instrucciones -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-3">Instrucciones</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700">
                    <div>
                        <h5 class="font-medium text-gray-900 mb-2">¿Cómo obtener el certificado?</h5>
                        <ul class="space-y-1">
                            @if($company->country === 'AR')
                                <li>• Solicitar en AFIP (Administrador de Relaciones de Clave Fiscal)</li>
                                <li>• Descargar el certificado en formato .p12</li>
                                <li>• Guardar la contraseña de forma segura</li>
                            @else
                                <li>• Solicitar en SET (Servicio de Administración Tributaria)</li>
                                <li>• Descargar el certificado en formato .p12 o .pfx</li>
                                <li>• Guardar la contraseña de forma segura</li>
                            @endif
                        </ul>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-900 mb-2">Problemas comunes</h5>
                        <ul class="space-y-1">
                            <li>• Verificar que el archivo sea .p12 o .pfx</li>
                            <li>• Asegurarse de que la contraseña sea correcta</li>
                            <li>• Verificar que el certificado no haya vencido</li>
                            <li>• Contactar soporte si persisten los problemas</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario de eliminación separado -->
    @if($company->certificate_path)
        <form id="delete-certificate-form"
              method="POST"
              action="{{ route('admin.companies.delete-certificate', $company) }}"
              class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @push('scripts')
    <script>
        function showFileName(input) {
            const fileInfo = document.getElementById('file-info');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2); // MB
                fileInfo.innerHTML = `
                    <div class="flex items-center text-green-600">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        ${file.name} (${size} MB)
                    </div>
                `;
                fileInfo.classList.remove('hidden');
            } else {
                fileInfo.classList.add('hidden');
            }
        }

        function testCertificate() {
            // Aquí iría la lógica para probar el certificado
            // Por ahora mostramos un mensaje
            alert('Función de prueba de certificado en desarrollo.\n\nEsta función verificará:\n- Validez del certificado\n- Conexión con webservices\n- Fecha de vencimiento');
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const certificateInput = document.getElementById('certificate');
            const passwordInput = document.getElementById('certificate_password');

            form.addEventListener('submit', function(e) {
                // Validar archivo
                if (certificateInput.files.length > 0) {
                    const file = certificateInput.files[0];
                    const validExtensions = ['.p12', '.pfx'];
                    const extension = file.name.toLowerCase().substring(file.name.lastIndexOf('.'));

                    if (!validExtensions.includes(extension)) {
                        e.preventDefault();
                        alert('Por favor selecciona un archivo con extensión .p12 o .pfx');
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) { // 10MB
                        e.preventDefault();
                        alert('El archivo es demasiado grande. El tamaño máximo es 10MB.');
                        return;
                    }
                }

                // Validar contraseña
                if (certificateInput.files.length > 0 && !passwordInput.value.trim()) {
                    e.preventDefault();
                    alert('Por favor ingresa la contraseña del certificado.');
                    passwordInput.focus();
                    return;
                }

                // Confirmación
                if (!confirm('¿Estás seguro de que quieres ' + ({{ $company->certificate_path ? 'true' : 'false' }} ? 'actualizar' : 'subir') + ' el certificado?')) {
                    e.preventDefault();
                    return;
                }
            });

            // Drag and drop functionality
            const dropZone = document.querySelector('.border-dashed');

            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('border-blue-500', 'bg-blue-50');
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('border-blue-500', 'bg-blue-50');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('border-blue-500', 'bg-blue-50');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    certificateInput.files = files;
                    showFileName(certificateInput);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
