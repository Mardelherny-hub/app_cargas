<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.certificates.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔄 Renovar Certificado Digital
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado del Certificado Actual -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Certificado Actual</h3>
                    
                    <div class="@if($certificateStatus['is_expired']) bg-red-50 @elseif($certificateStatus['is_expiring_soon']) bg-yellow-50 @else bg-green-50 @endif rounded-lg p-4">
                        <div class="flex items-start">
                            @if($certificateStatus['is_expired'])
                                <svg class="w-6 h-6 text-red-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @elseif($certificateStatus['is_expiring_soon'])
                                <svg class="w-6 h-6 text-yellow-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @else
                                <svg class="w-6 h-6 text-green-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            @endif
                            
                            <div class="flex-1">
                                <h4 class="text-md font-medium @if($certificateStatus['is_expired']) text-red-800 @elseif($certificateStatus['is_expiring_soon']) text-yellow-800 @else text-green-800 @endif">
                                    Estado: {{ $certificateStatus['status'] }}
                                </h4>
                                
                                @if($certificateStatus['expires_at'])
                                    <p class="text-sm @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif mt-1">
                                        <strong>Vencimiento:</strong> {{ $certificateStatus['expires_at']->format('d/m/Y H:i') }}
                                        @if($certificateStatus['days_to_expiry'] !== null)
                                            @if($certificateStatus['is_expired'])
                                                (Vencido hace {{ abs($certificateStatus['days_to_expiry']) }} días)
                                            @else
                                                ({{ $certificateStatus['days_to_expiry'] }} días restantes)
                                            @endif
                                        @endif
                                    </p>
                                @endif

                                @if($certificateStatus['alias'])
                                    <p class="text-sm @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif">
                                        <strong>Alias:</strong> {{ $certificateStatus['alias'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($certificateStatus['is_expired'])
                        <div class="mt-4 p-3 bg-red-100 rounded-lg">
                            <p class="text-sm text-red-800">
                                ⚠️ <strong>Certificado vencido:</strong> Los webservices están deshabilitados. Debe renovar el certificado inmediatamente.
                            </p>
                        </div>
                    @elseif($certificateStatus['is_expiring_soon'])
                        <div class="mt-4 p-3 bg-yellow-100 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                ⏰ <strong>Certificado próximo a vencer:</strong> Se recomienda renovarlo pronto para evitar interrupciones en el servicio.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Formulario de Renovación -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Renovar con Nuevo Certificado</h3>

                    <form method="POST" action="{{ route('company.certificates.processRenew') }}" enctype="multipart/form-data" id="renewForm">
                        @csrf

                        <!-- Zona de Subida de Nuevo Certificado -->
                        <div class="mb-6">
                            <label for="certificate" class="block text-sm font-medium text-gray-700 mb-2">
                                Nuevo Archivo de Certificado (.p12 o .pfx) *
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors cursor-pointer"
                                 onclick="document.getElementById('certificate').click()">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <span class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500">
                                            Seleccionar nuevo certificado
                                        </span>
                                        <p class="pl-1">o arrastrar aquí</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        .p12, .pfx hasta 2MB
                                    </p>
                                    <div id="fileName" class="text-sm text-green-600 font-medium hidden"></div>
                                </div>
                            </div>
                            <input id="certificate" 
                                   name="certificate" 
                                   type="file" 
                                   accept=".p12,.pfx" 
                                   required 
                                   class="sr-only" 
                                   onchange="showFileName(this)">
                            @error('certificate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Contraseña del Nuevo Certificado -->
                        <div class="mb-6">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Contraseña del Nuevo Certificado *
                            </label>
                            <div class="mt-1 relative">
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 @enderror"
                                       placeholder="Contraseña del nuevo certificado"
                                       value="{{ old('password') }}">
                                <button type="button" 
                                        onclick="togglePassword()" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg id="eyeIcon" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Alias del Certificado -->
                        <div class="mb-6">
                            <label for="alias" class="block text-sm font-medium text-gray-700">
                                Alias del Certificado (Opcional)
                            </label>
                            <input type="text" 
                                   name="alias" 
                                   id="alias" 
                                   class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('alias') border-red-300 @enderror"
                                   placeholder="Nombre descriptivo para el certificado"
                                   value="{{ old('alias', $certificateStatus['alias']) }}">
                            @error('alias')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Ej: "MAERSK_CERT_2025", "Certificado Principal", etc.
                            </p>
                        </div>

                        <!-- Fecha de Vencimiento -->
                        <div class="mb-6">
                            <label for="expires_at" class="block text-sm font-medium text-gray-700">
                                Fecha de Vencimiento del Nuevo Certificado *
                            </label>
                            <input type="datetime-local" 
                                   name="expires_at" 
                                   id="expires_at" 
                                   required
                                   min="{{ now()->format('Y-m-d\TH:i') }}"
                                   class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('expires_at') border-red-300 @enderror"
                                   value="{{ old('expires_at') }}">
                            @error('expires_at')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Fecha y hora en que vence el nuevo certificado
                            </p>
                        </div>

                        <!-- Información de Seguridad -->
                        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">🔒 Información de Seguridad</h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li>• El certificado anterior será reemplazado automáticamente</li>
                                <li>• La contraseña se encripta antes del almacenamiento</li>
                                <li>• Los webservices se actualizarán inmediatamente</li>
                                <li>• Se mantendrá un registro de la renovación</li>
                            </ul>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-between pt-6">
                            <a href="{{ route('company.certificates.index') }}" 
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-sm font-medium">
                                ← Cancelar
                            </a>
                            
                            <button type="submit" 
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                🔄 Renovar Certificado
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información Importante -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h4 class="text-lg font-medium text-yellow-800 mb-3">⚠️ Información Importante</h4>
                
                <div class="space-y-3 text-sm text-yellow-700">
                    <div class="flex items-start">
                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p><strong>Interrupción temporal:</strong> Durante la renovación, los webservices estarán brevemente inactivos (menos de 1 minuto).</p>
                    </div>
                    
                    <div class="flex items-start">
                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p><strong>Validación automática:</strong> El sistema verificará que el nuevo certificado sea válido antes de reemplazar el actual.</p>
                    </div>
                    
                    <div class="flex items-start">
                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 9l6 6 6-6"/>
                        </svg>
                        <p><strong>Respaldo automático:</strong> El certificado anterior se elimina de forma segura después de confirmar que el nuevo funciona.</p>
                    </div>
                    
                    <div class="flex items-start">
                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p><strong>Recomendación:</strong> Realice la renovación fuera del horario pico para minimizar el impacto en las operaciones.</p>
                    </div>
                </div>
            </div>

            <!-- Contacto de Soporte -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-3">🆘 ¿Necesita Ayuda?</h4>
                <p class="text-sm text-gray-700 mb-3">
                    Si tiene problemas durante la renovación o necesita asistencia técnica:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center p-3 bg-white rounded border">
                        <svg class="w-5 h-5 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Soporte Técnico</p>
                            <p class="text-xs text-gray-500">soporte@empresa.com</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-white rounded border">
                        <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Emergencias</p>
                            <p class="text-xs text-gray-500">+54 11 1234-5678</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function showFileName(input) {
            const fileNameDiv = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '📄 ' + input.files[0].name;
                fileNameDiv.classList.remove('hidden');
            } else {
                fileNameDiv.classList.add('hidden');
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }

        // Validación del formulario
        document.getElementById('renewForm').addEventListener('submit', function(e) {
            const certificateInput = document.getElementById('certificate');
            const passwordInput = document.getElementById('password');
            const expiresInput = document.getElementById('expires_at');

            // Validar que se haya seleccionado un archivo
            if (!certificateInput.files || certificateInput.files.length === 0) {
                e.preventDefault();
                alert('Por favor, seleccione un archivo de certificado.');
                certificateInput.focus();
                return;
            }

            // Validar contraseña
            if (passwordInput.value.trim() === '') {
                e.preventDefault();
                alert('La contraseña del certificado es obligatoria.');
                passwordInput.focus();
                return;
            }

            // Validar fecha de vencimiento
            if (!expiresInput.value) {
                e.preventDefault();
                alert('La fecha de vencimiento es obligatoria.');
                expiresInput.focus();
                return;
            }

            // Validar que la fecha sea futura
            const selectedDate = new Date(expiresInput.value);
            const currentDate = new Date();
            if (selectedDate <= currentDate) {
                e.preventDefault();
                alert('La fecha de vencimiento debe ser posterior a la fecha actual.');
                expiresInput.focus();
                return;
            }

            // Confirmación final
            if (!confirm('¿Está seguro de renovar el certificado? El certificado actual será reemplazado.')) {
                e.preventDefault();
                return;
            }

            // Mostrar mensaje de carga
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '⏳ Renovando...';
            submitButton.disabled = true;
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
                const certificateInput = document.getElementById('certificate');
                certificateInput.files = files;
                showFileName(certificateInput);
            }
        });
    </script>
    @endpush
</x-app-layout>