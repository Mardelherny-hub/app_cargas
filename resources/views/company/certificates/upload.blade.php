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
                    📤 Subir Certificado Digital
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <!-- Formulario de Subida -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Configurar Certificado Digital</h3>

                    <form method="POST" action="{{ route('company.certificates.process-upload') }}" enctype="multipart/form-data" id="certificateForm">
                        @csrf

                        @if ($errors->any())
                            <div class="mb-4 p-4 bg-red-100 border border-red-400 rounded">
                                <h4 class="text-red-800 font-bold">Errores de validación:</h4>
                                <ul class="text-red-700 text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Selector de País -->
                        <div class="mb-6">
                            <label for="country" class="block text-sm font-medium text-gray-700">
                                País del Certificado *
                            </label>
                            <select name="country" 
                                    id="country" 
                                    required
                                    onchange="updateCountryInfo(this.value)"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('country') border-red-300 @enderror">
                                <option value="">Seleccione un país...</option>
                                @foreach($countries as $key => $country)
                                    <option value="{{ $key }}" 
                                            {{ old('country', request('country')) == $key ? 'selected' : '' }}
                                            data-issuer="{{ $country['issuer'] }}"
                                            data-has-cert="{{ $country['has_certificate'] ? '1' : '0' }}">
                                        {{ $country['label'] }}
                                        @if($country['has_certificate'])
                                            (Ya tiene certificado - Renovar)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('country')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            
                            <!-- Info dinámica del país seleccionado -->
                            <div id="countryInfo" class="mt-2 hidden">
                                <div class="p-3 rounded-lg" id="countryInfoBox">
                                    <p class="text-sm font-medium" id="countryInfoText"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Zona de Subida de Archivo -->
                        <!-- ============================================ -->
                        <!-- ZONA DE SUBIDA - ARGENTINA (archivo único) -->
                        <!-- ============================================ -->
                        <div id="argentinaUpload" class="mb-6 hidden">
                            <label for="certificate" class="block text-sm font-medium text-gray-700 mb-2">
                                Archivo de Certificado (.p12 o .pfx) *
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors cursor-pointer"
                                onclick="document.getElementById('certificate').click()">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <span class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500">
                                            Subir archivo .p12 o .pfx
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500">Certificado AFIP hasta 2MB</p>
                                    <div id="fileNameArg" class="text-sm text-green-600 font-medium hidden"></div>
                                </div>
                            </div>
                            <input id="certificate" 
                                name="certificate" 
                                type="file" 
                                accept=".p12,.pfx,.pem" 
                                class="sr-only" 
                                onchange="showFileNameArg(this)">
                            @error('certificate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- ============================================ -->
                        <!-- ZONA DE SUBIDA - PARAGUAY (2 archivos) -->
                        <!-- ============================================ -->
                        <div id="paraguayUpload" class="mb-6 hidden">
                            <!-- Instrucciones claras para Paraguay -->
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <h4 class="text-sm font-medium text-green-800 mb-2">🇵🇾 Certificados DNA Paraguay</h4>
                                <p class="text-xs text-green-700 mb-2">
                                    DNA entrega los certificados en <strong>2 archivos separados</strong>. 
                                    Debe subir ambos archivos tal como los recibió de Aduana:
                                </p>
                                <ul class="text-xs text-green-700 space-y-1">
                                    <li>📄 <strong>Certificado:</strong> Archivo con su RUC (ej: <code>800292944.pem</code>)</li>
                                    <li>🔑 <strong>Clave Privada:</strong> Archivo <code>pkey.pem</code></li>
                                </ul>
                            </div>

                            <!-- Campo 1: Certificado Público -->
                            <div class="mb-4">
                                <label for="certificate_py" class="block text-sm font-medium text-gray-700 mb-2">
                                    📄 Certificado Público (.pem) *
                                </label>
                                <p class="text-xs text-gray-500 mb-2">
                                    El archivo con su número de RUC, por ejemplo: <strong>800292944.pem</strong>
                                </p>
                                <div class="mt-1 flex justify-center px-4 py-4 border-2 border-gray-300 border-dashed rounded-md hover:border-green-400 transition-colors cursor-pointer"
                                    onclick="document.getElementById('certificate_py').click()">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="text-sm text-green-600 font-medium">Seleccionar certificado .pem</span>
                                        <div id="fileNameCert" class="text-sm text-green-600 font-medium hidden mt-1"></div>
                                    </div>
                                </div>
                                <input id="certificate_py" 
                                    name="certificate" 
                                    type="file" 
                                    accept=".pem" 
                                    class="sr-only" 
                                    onchange="showFileNameCert(this)">
                                @error('certificate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Campo 2: Clave Privada -->
                            <div class="mb-4">
                                <label for="private_key" class="block text-sm font-medium text-gray-700 mb-2">
                                    🔑 Clave Privada (.pem) *
                                </label>
                                <p class="text-xs text-gray-500 mb-2">
                                    El archivo de clave privada: <strong>pkey.pem</strong>
                                </p>
                                <div class="mt-1 flex justify-center px-4 py-4 border-2 border-gray-300 border-dashed rounded-md hover:border-yellow-400 transition-colors cursor-pointer"
                                    onclick="document.getElementById('private_key').click()">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                        </svg>
                                        <span class="text-sm text-yellow-600 font-medium">Seleccionar clave privada .pem</span>
                                        <div id="fileNameKey" class="text-sm text-yellow-600 font-medium hidden mt-1"></div>
                                    </div>
                                </div>
                                <input id="private_key" 
                                    name="private_key" 
                                    type="file" 
                                    accept=".pem" 
                                    class="sr-only" 
                                    onchange="showFileNameKey(this)">
                                @error('private_key')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Contraseña del Certificado -->
                        <div class="mb-6 hidden" id="passwordSection">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Contraseña del Certificado *
                            </label>
                            <div class="mt-1 relative">
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('password') border-red-300 @enderror"
                                       placeholder="Ingrese la contraseña de su certificado"
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
                                   value="{{ old('alias') }}">
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
                                Fecha de Vencimiento del Certificado *
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
                                Fecha y hora en que vence el certificado
                            </p>
                        </div>

                        <!-- Información de Seguridad -->
                        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">🔒 Información de Seguridad</h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li>• El certificado se almacena de forma encriptada en el servidor</li>
                                <li>• La contraseña se encripta antes del almacenamiento</li>
                                <li>• Solo usuarios autorizados pueden gestionar certificados</li>
                                <li>• Se mantiene un registro de todas las acciones</li>
                            </ul>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-between pt-6">
                            <a href="{{ route('company.certificates.index') }}" 
                               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-sm font-medium">
                                ← Cancelar
                            </a>
                            
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                📤 Subir Certificado
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Instrucciones Detalladas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📖 Instrucciones</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Cómo Obtener el Certificado -->
                        <div>
                            <h4 class="text-md font-medium text-gray-800 mb-3">📋 Cómo Obtener su Certificado</h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium mr-3 mt-0.5">1</span>
                                    <span>Contacte a su autoridad certificadora autorizada (AFIP, DNA, etc.)</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium mr-3 mt-0.5">2</span>
                                    <span>Presente la documentación requerida de su empresa</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium mr-3 mt-0.5">3</span>
                                    <span>Reciba el certificado en formato .p12 o .pfx</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium mr-3 mt-0.5">4</span>
                                    <span>Suba el certificado usando este formulario</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Requisitos Técnicos -->
                        <div>
                            <h4 class="text-md font-medium text-gray-800 mb-3">⚙️ Requisitos Técnicos</h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span><strong>Formato:</strong> .p12 o .pfx</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span><strong>Tamaño máximo:</strong> 2MB</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span><strong>Vigencia:</strong> Mínimo 30 días</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span><strong>Autoridad:</strong> Reconocida por aduanas</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información por País -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🌎 Información por País</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Argentina -->
                        <div class="border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <span class="text-2xl mr-2">🇦🇷</span>
                                <h4 class="text-md font-medium text-blue-800">Argentina (AFIP)</h4>
                            </div>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• Certificados emitidos por AFIP</li>
                                <li>• Válidos para MIC/DTA y Información Anticipada</li>
                                <li>• Renovación anual requerida</li>
                                <li>• Soporte: 0800-222-AFIP (2347)</li>
                            </ul>
                        </div>

                        <!-- Paraguay -->
                        <div class="border border-green-200 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <span class="text-2xl mr-2">🇵🇾</span>
                                <h4 class="text-md font-medium text-green-800">Paraguay (DNA)</h4>
                            </div>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li>• Certificados emitidos por DNA</li>
                                <li>• Válidos para manifiestos GDSF</li>
                                <li>• Vigencia de 2 años</li>
                                <li>• Soporte: (+595) 21-441-000</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preguntas Frecuentes -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">❓ Preguntas Frecuentes</h4>
                
                <div class="space-y-4">
                    <div>
                        <h5 class="text-sm font-medium text-gray-800">¿Qué sucede si mi certificado vence?</h5>
                        <p class="text-sm text-gray-600 mt-1">
                            Los webservices se deshabilitarán automáticamente. Debe renovar el certificado inmediatamente para reanudar las operaciones.
                        </p>
                    </div>
                    
                    <div>
                        <h5 class="text-sm font-medium text-gray-800">¿Puedo usar el mismo certificado para Argentina y Paraguay?</h5>
                        <p class="text-sm text-gray-600 mt-1">
                            No, cada país requiere certificados emitidos por su autoridad certificadora específica.
                        </p>
                    </div>
                    
                    <div>
                        <h5 class="text-sm font-medium text-gray-800">¿Con qué frecuencia debo actualizar mi certificado?</h5>
                        <p class="text-sm text-gray-600 mt-1">
                            Depende del país: Argentina requiere renovación anual, Paraguay cada 2 años. El sistema le notificará 30 días antes del vencimiento.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Mostrar/ocultar secciones según país seleccionado
        function updateCountryInfo(country) {
            const countryInfo = document.getElementById('countryInfo');
            const countryInfoBox = document.getElementById('countryInfoBox');
            const countryInfoText = document.getElementById('countryInfoText');
            const aliasInput = document.getElementById('alias');
            
            // Secciones de upload
            const argentinaUpload = document.getElementById('argentinaUpload');
            const paraguayUpload = document.getElementById('paraguayUpload');
            const passwordSection = document.getElementById('passwordSection');
            
            // Inputs
            const certificateArg = document.getElementById('certificate');
            const certificatePy = document.getElementById('certificate_py');
            const privateKey = document.getElementById('private_key');
            const passwordInput = document.getElementById('password');
            
            // Ocultar todo primero
            argentinaUpload.classList.add('hidden');
            paraguayUpload.classList.add('hidden');
            passwordSection.classList.add('hidden');
            
            // Limpiar required
            if (certificateArg) certificateArg.removeAttribute('required');
            if (certificatePy) certificatePy.removeAttribute('required');
            if (privateKey) privateKey.removeAttribute('required');
            if (passwordInput) passwordInput.removeAttribute('required');
            
            if (!country) {
                countryInfo.classList.add('hidden');
                return;
            }
            
            const option = document.querySelector(`option[value="${country}"]`);
            const hasCert = option.dataset.hasCert === '1';
            const issuer = option.dataset.issuer;
            
            // Mostrar información del país
            countryInfo.classList.remove('hidden');
            
            if (hasCert) {
                countryInfoBox.className = 'p-3 rounded-lg bg-yellow-50 border border-yellow-200';
                countryInfoText.className = 'text-sm font-medium text-yellow-800';
                countryInfoText.innerHTML = `⚠️ Ya existe un certificado de ${issuer}. Al subir uno nuevo, se reemplazará el anterior.`;
            } else {
                countryInfoBox.className = 'p-3 rounded-lg bg-blue-50 border border-blue-200';
                countryInfoText.className = 'text-sm font-medium text-blue-800';
                countryInfoText.innerHTML = `ℹ️ Subirá un certificado nuevo para ${issuer}.`;
            }
            
            // Mostrar sección correspondiente según país
            if (country === 'argentina') {
                argentinaUpload.classList.remove('hidden');
                passwordSection.classList.remove('hidden');
                certificateArg.setAttribute('required', 'required');
                passwordInput.setAttribute('required', 'required');
                
                if (!aliasInput.value) {
                    aliasInput.value = 'AFIP_CERT_' + new Date().getFullYear();
                }
            } else if (country === 'paraguay') {
                paraguayUpload.classList.remove('hidden');
                // Password NO requerido para Paraguay
                certificatePy.setAttribute('required', 'required');
                privateKey.setAttribute('required', 'required');
                
                if (!aliasInput.value) {
                    aliasInput.value = 'DNA_CERT_' + new Date().getFullYear();
                }
            }
            
            // =============================================
            // CRÍTICO: Deshabilitar inputs del país NO seleccionado
            // Los inputs disabled NO se envían en el formulario
            // Esto evita conflicto de dos inputs con name="certificate"
            // =============================================
            if (country === 'argentina') {
                certificateArg.disabled = false;
                certificatePy.disabled = true;
                privateKey.disabled = true;
                passwordInput.disabled = false;
            } else if (country === 'paraguay') {
                certificateArg.disabled = true;
                certificatePy.disabled = false;
                privateKey.disabled = false;
                passwordInput.disabled = true;
            }
        }

        // Mostrar nombre de archivo - Argentina
        function showFileNameArg(input) {
            const fileNameDiv = document.getElementById('fileNameArg');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '✓ ' + input.files[0].name;
                fileNameDiv.classList.remove('hidden');
            } else {
                fileNameDiv.classList.add('hidden');
            }
        }
        
        // Mostrar nombre de archivo - Paraguay Certificado
        function showFileNameCert(input) {
            const fileNameDiv = document.getElementById('fileNameCert');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '✓ ' + input.files[0].name;
                fileNameDiv.classList.remove('hidden');
            } else {
                fileNameDiv.classList.add('hidden');
            }
        }
        
        // Mostrar nombre de archivo - Paraguay Clave Privada
        function showFileNameKey(input) {
            const fileNameDiv = document.getElementById('fileNameKey');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '✓ ' + input.files[0].name;
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
        document.getElementById('certificateForm').addEventListener('submit', function(e) {
            // DEBUG - Verificar estado de elementos
            console.log('=== DEBUG SUBMIT ===');
            console.log('argentinaUpload hidden?', document.getElementById('argentinaUpload').classList.contains('hidden'));
            console.log('certificate input:', document.getElementById('certificate'));
            console.log('certificate files:', document.getElementById('certificate').files);
            console.log('certificate files length:', document.getElementById('certificate').files.length);
            console.log('certificate disabled?', document.getElementById('certificate').disabled);
            console.log('certificate name:', document.getElementById('certificate').name);
            const countrySelect = document.getElementById('country');
            const expiresInput = document.getElementById('expires_at');
            
            if (!countrySelect.value) {
                e.preventDefault();
                alert('Debe seleccionar el país del certificado.');
                return;
            }
            
            // Validar fecha
            if (!expiresInput.value) {
                e.preventDefault();
                alert('Debe ingresar la fecha de vencimiento del certificado.');
                expiresInput.focus();
                return;
            }

            const selectedDate = new Date(expiresInput.value);
            const currentDate = new Date();
            
            if (selectedDate <= currentDate) {
                e.preventDefault();
                alert('La fecha de vencimiento debe ser posterior a hoy.');
                expiresInput.focus();
                return;
            }

            // Validaciones específicas por país
            if (countrySelect.value === 'argentina') {
                const certFile = document.getElementById('certificate');
                const password = document.getElementById('password');
                
                if (!certFile.files || !certFile.files[0]) {
                    e.preventDefault();
                    alert('Debe seleccionar el archivo del certificado (.p12 o .pfx).');
                    return;
                }
                if (!password.value) {
                    e.preventDefault();
                    alert('Debe ingresar la contraseña del certificado.');
                    password.focus();
                    return;
                }
            } else if (countrySelect.value === 'paraguay') {
                const certFile = document.getElementById('certificate_py');
                const keyFile = document.getElementById('private_key');
                
                if (!certFile.files || !certFile.files[0]) {
                    e.preventDefault();
                    alert('Debe seleccionar el archivo del certificado (.pem).');
                    return;
                }
                if (!keyFile.files || !keyFile.files[0]) {
                    e.preventDefault();
                    alert('Debe seleccionar el archivo de clave privada (.pem).');
                    return;
                }
            }

            // Confirmación final
            const countryName = countrySelect.options[countrySelect.selectedIndex].text;
            if (!confirm(`¿Está seguro de subir el certificado para ${countryName}?\n\nLos webservices se configurarán inmediatamente.`)) {
                e.preventDefault();
                return;
            }

            // Mostrar mensaje de carga
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '⏳ Subiendo...';
            submitButton.disabled = true;
        });

        // Ejecutar al cargar si ya hay país seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country');
            if (countrySelect.value) {
                updateCountryInfo(countrySelect.value);
            }
        });
    </script>
    @endpush
</x-app-layout>