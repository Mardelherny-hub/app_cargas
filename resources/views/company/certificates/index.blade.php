<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
               
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔒 Gestión de Certificados Digitales
                </h2>
            </div>
            <a href="{{ route('company.certificates.upload') }}" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Subir/Gestionar Certificados
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado Actual del Certificado -->
            <div class="p-6 bg-gray-50 rounded-lg overflow-hidden shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Certificados por País</h3>

            <div class="space-y-4 p-6">
                <!-- Argentina -->
                <div class="border @if($certificates['argentina']['exists']) border-blue-200 bg-blue-50 @else border-gray-200 bg-gray-50 @endif rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-3xl">🇦🇷</span>
                            <div>
                                <h4 class="text-md font-semibold text-gray-900">Argentina (AFIP)</h4>
                                <p class="text-sm text-gray-600">Certificado para webservices MIC/DTA</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($certificates['argentina']['exists'])
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $certificates['argentina']['status']['class'] }}">
                                    {{ $certificates['argentina']['status']['icon'] }} {{ $certificates['argentina']['status']['message'] }}
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">
                                    Sin certificado
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($certificates['argentina']['exists'])
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-600">Alias:</dt>
                                    <dd class="text-gray-900">{{ $certificates['argentina']['data']['alias'] ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-600">Vencimiento:</dt>
                                    <dd class="text-gray-900">
                                        {{ isset($certificates['argentina']['data']['expires_at']) ? \Carbon\Carbon::parse($certificates['argentina']['data']['expires_at'])->format('d/m/Y') : 'N/A' }}
                                    </dd>
                                </div>
                            </dl>
                            <div class="mt-3 flex space-x-2">
                                <a href="{{ route('company.certificates.upload') }}?country=argentina" 
                                class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                    Renovar
                                </a>
                                <form action="{{ route('company.certificates.destroy') }}" method="POST" class="inline"
                                    onsubmit="return confirm('¿Está seguro de eliminar el certificado de Argentina?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="country" value="argentina">
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="mt-3">
                            <a href="{{ route('company.certificates.upload') }}?country=argentina" 
                            class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Subir Certificado AFIP
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Paraguay -->
                <div class="border @if($certificates['paraguay']['exists']) border-green-200 bg-green-50 @else border-gray-200 bg-gray-50 @endif rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-3xl">🇵🇾</span>
                            <div>
                                <h4 class="text-md font-semibold text-gray-900">Paraguay (DNA)</h4>
                                <p class="text-sm text-gray-600">Certificado para manifiestos GDSF</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($certificates['paraguay']['exists'])
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $certificates['paraguay']['status']['class'] }}">
                                    {{ $certificates['paraguay']['status']['icon'] }} {{ $certificates['paraguay']['status']['message'] }}
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">
                                    Sin certificado
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($certificates['paraguay']['exists'])
                        <div class="mt-3 pt-3 border-t border-green-200">
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="font-medium text-gray-600">Alias:</dt>
                                    <dd class="text-gray-900">{{ $certificates['paraguay']['data']['alias'] ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-600">Vencimiento:</dt>
                                    <dd class="text-gray-900">
                                        {{ isset($certificates['paraguay']['data']['expires_at']) ? \Carbon\Carbon::parse($certificates['paraguay']['data']['expires_at'])->format('d/m/Y') : 'N/A' }}
                                    </dd>
                                </div>
                            </dl>
                            <div class="mt-3 flex space-x-2">
                                <a href="{{ route('company.certificates.upload') }}?country=paraguay" 
                                class="text-sm text-green-600 hover:text-green-800 font-medium">
                                    Renovar
                                </a>
                                <form action="{{ route('company.certificates.destroy') }}" method="POST" class="inline"
                                    onsubmit="return confirm('¿Está seguro de eliminar el certificado de Paraguay?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="country" value="paraguay">
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="mt-3">
                            <a href="{{ route('company.certificates.upload') }}?country=paraguay" 
                            class="inline-flex items-center text-sm text-green-600 hover:text-green-800 font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Subir Certificado DNA
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            </div>

            <!-- Información sobre Certificados -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">¿Qué es un Certificado Digital?</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-md font-medium text-gray-800 mb-3">🔐 Propósito</h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Autenticación segura con las aduanas
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Firma digital de manifiestos y documentos
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Cumplimiento de normativas legales
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="text-md font-medium text-gray-800 mb-3">🛡️ Seguridad</h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Archivos encriptados de forma segura
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Contraseñas protegidas
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Alertas de vencimiento automáticas
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-800 mb-2">💡 Formatos Soportados</h4>
                        <p class="text-sm text-blue-700">
                            El sistema acepta certificados en formato <strong>.p12</strong>, <strong>.pfx</strong> o <strong>.pem</strong> con un tamaño máximo de 2MB.
                            Estos deben ser emitidos por una autoridad certificadora reconocida por las aduanas correspondientes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Enlaces Relacionados -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">🔗 Enlaces Relacionados</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- <a href="{{ route('company.webservices.index') }}" 
                       class="block p-4 bg-white rounded-lg border hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <div>
                                <h5 class="text-sm font-medium text-gray-900">Webservices</h5>
                                <p class="text-xs text-gray-500">Panel principal</p>
                            </div>
                        </div>
                    </a>
 --}}
                    <a href="{{ route('company.settings.index') }}" 
                       class="block p-4 bg-white rounded-lg border hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div>
                                <h5 class="text-sm font-medium text-gray-900">Configuración</h5>
                                <p class="text-xs text-gray-500">Empresa</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('company.dashboard') }}" 
                       class="block p-4 bg-white rounded-lg border hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4"/>
                            </svg>
                            <div>
                                <h5 class="text-sm font-medium text-gray-900">Dashboard</h5>
                                <p class="text-xs text-gray-500">Principal</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        
        function testCertificate() {
            if (!confirm('¿Desea probar la conectividad del certificado con los webservices?')) {
                return;
            }

            // Mostrar indicador de carga
            const testButton = document.querySelector('button[onclick="testCertificate()"]');
            const originalText = testButton.innerHTML;
            testButton.innerHTML = '⏳ Probando...';
            testButton.disabled = true;

            // Crear modal de progreso
            showTestProgressModal();

            // Realizar la prueba via AJAX - NOTA: Cambiar la URL por la correcta
            fetch('{{ route("company.certificates.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTestResults(data.results);
                } else {
                    showTestError(data.message || 'Error desconocido en la prueba');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showTestError('Error de conexión: ' + error.message);
            })
            .finally(() => {
                // Restaurar botón
                testButton.innerHTML = originalText;
                testButton.disabled = false;
                hideTestProgressModal();
            });
        }
    </script>
    @endpush
</x-app-layout>