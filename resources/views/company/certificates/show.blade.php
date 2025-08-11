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
                    👁️ Detalles del Certificado Digital
                </h2>
            </div>
            <div class="flex space-x-3">
                @if($certificateStatus['is_expired'] || $certificateStatus['is_expiring_soon'])
                    <a href="{{ route('company.certificates.renew') }}" 
                       class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        🔄 Renovar
                    </a>
                @endif
                <button onclick="testCertificate()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    🧪 Probar
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado del Certificado -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Información del Certificado</h3>
                        <div class="flex items-center space-x-2">
                            @if($certificateStatus['is_expired'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    ❌ Vencido
                                </span>
                            @elseif($certificateStatus['is_expiring_soon'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    ⚠️ Por vencer
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✅ Activo
                                </span>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Empresa -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->business_name }}</dd>
                        </div>

                        <!-- CUIT/RUC -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->tax_id }}</dd>
                        </div>

                        <!-- Alias del Certificado -->
                        @if($certificateStatus['alias'])
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Alias del Certificado</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $certificateStatus['alias'] }}</dd>
                            </div>
                        @endif

                        <!-- Estado -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $certificateStatus['status'] }}</dd>
                        </div>

                        <!-- Fecha de Vencimiento -->
                        @if($certificateStatus['expires_at'])
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Vencimiento</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $certificateStatus['expires_at']->format('d/m/Y H:i') }}
                                    @if($certificateStatus['days_to_expiry'] !== null)
                                        <span class="ml-2 text-xs text-gray-500">
                                            @if($certificateStatus['is_expired'])
                                                (Vencido hace {{ abs($certificateStatus['days_to_expiry']) }} días)
                                            @else
                                                ({{ $certificateStatus['days_to_expiry'] }} días restantes)
                                            @endif
                                        </span>
                                    @endif
                                </dd>
                            </div>
                        @endif

                        <!-- Última Modificación -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Última Modificación</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $company->updated_at->format('d/m/Y H:i') }}
                                <span class="text-xs text-gray-500">
                                    ({{ $company->updated_at->diffForHumans() }})
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Estado de Webservices -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado de Webservices</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Estado General -->
                        <div class="@if($webserviceStatus['enabled']) bg-green-50 @else bg-red-50 @endif rounded-lg p-4">
                            <div class="flex items-center">
                                @if($webserviceStatus['enabled'])
                                    <svg class="w-8 h-8 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @else
                                    <svg class="w-8 h-8 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                @endif
                                <div>
                                    <h4 class="text-sm font-medium @if($webserviceStatus['enabled']) text-green-800 @else text-red-800 @endif">
                                        @if($webserviceStatus['enabled']) Habilitados @else Deshabilitados @endif
                                    </h4>
                                    <p class="text-xs @if($webserviceStatus['enabled']) text-green-600 @else text-red-600 @endif">
                                        Estado general
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Ambiente -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-8 h-8 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"/>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-800">
                                        {{ ucfirst($webserviceStatus['environment']) }}
                                    </h4>
                                    <p class="text-xs text-blue-600">Ambiente actual</p>
                                </div>
                            </div>
                        </div>

                        <!-- Última Prueba -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-8 h-8 text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-800">
                                        @if($webserviceStatus['last_test'])
                                            {{ $webserviceStatus['last_test']->format('d/m H:i') }}
                                        @else
                                            No probado
                                        @endif
                                    </h4>
                                    <p class="text-xs text-gray-600">Última prueba</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($webserviceStatus['disabled_reason'])
                        <div class="mt-4 p-3 bg-red-50 rounded-lg">
                            <p class="text-sm text-red-700">
                                <strong>Razón de deshabilitación:</strong> {{ $webserviceStatus['disabled_reason'] }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Información Técnica -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Información Técnica</h3>
                    
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Formato del Certificado -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Formato del Certificado</dt>
                            <dd class="mt-1 text-sm text-gray-900">.p12 / PKCS#12</dd>
                        </div>

                        <!-- Algoritmo de Encriptación -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Encriptación</dt>
                            <dd class="mt-1 text-sm text-gray-900">AES-256 (Contraseña encriptada)</dd>
                        </div>

                        <!-- Compatible con -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Compatible con</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <div class="flex flex-wrap gap-1">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Argentina AFIP
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Paraguay DNA
                                    </span>
                                </div>
                            </dd>
                        </div>

                        <!-- Ubicación del Archivo -->
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Almacenamiento</dt>
                            <dd class="mt-1 text-sm text-gray-900">Servidor seguro encriptado</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Acciones Disponibles -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Disponibles</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Probar Certificado -->
                        <button onclick="testCertificate()" 
                                class="flex items-center justify-center p-4 border-2 border-green-300 rounded-lg hover:border-green-500 transition-colors">
                            <div class="text-center">
                                <svg class="w-8 h-8 text-green-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-medium text-green-800">Probar Certificado</span>
                            </div>
                        </button>

                        <!-- Renovar Certificado -->
                        <a href="{{ route('company.certificates.renew') }}" 
                           class="flex items-center justify-center p-4 border-2 border-orange-300 rounded-lg hover:border-orange-500 transition-colors">
                            <div class="text-center">
                                <svg class="w-8 h-8 text-orange-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span class="text-sm font-medium text-orange-800">Renovar</span>
                            </div>
                        </a>

                        <!-- Ver Webservices -->
                        <a href="{{ route('company.webservices.index') }}" 
                           class="flex items-center justify-center p-4 border-2 border-blue-300 rounded-lg hover:border-blue-500 transition-colors">
                            <div class="text-center">
                                <svg class="w-8 h-8 text-blue-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-800">Webservices</span>
                            </div>
                        </a>

                        <!-- Eliminar Certificado -->
                        <form method="POST" action="{{ route('company.certificates.destroy') }}" 
                              onsubmit="return confirm('¿Está seguro de eliminar el certificado? Esto deshabilitará todos los webservices.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="w-full flex items-center justify-center p-4 border-2 border-red-300 rounded-lg hover:border-red-500 transition-colors">
                                <div class="text-center">
                                    <svg class="w-8 h-8 text-red-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <span class="text-sm font-medium text-red-800">Eliminar</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Historial de Cambios -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">📄 Historial</h4>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-2 h-2 bg-green-400 rounded-full mt-2 mr-3"></div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-700">
                                <strong>Certificado configurado</strong>
                                @if($certificateStatus['alias'])
                                    <span class="text-gray-500">- {{ $certificateStatus['alias'] }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $company->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    
                    <!-- Aquí podrías agregar más entradas del historial si tienes logs -->
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function testCertificate() {
    // Mostrar indicador de carga
    const button = document.querySelector('[onclick="testCertificate()"]');
    const originalText = button.innerHTML;
    button.innerHTML = '🔄 Probando...';
    button.disabled = true;
    
    // Realizar petición AJAX
    fetch('{{ route("company.certificates.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        // Restaurar botón
        button.innerHTML = originalText;
        button.disabled = false;
        
        if (data.success) {
            showTestResults(data.results);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        // Restaurar botón
        button.innerHTML = originalText;
        button.disabled = false;
        
        console.error('Error:', error);
        alert('Error de conexión al probar el certificado');
    });
}

function showTestResults(results) {
    // Crear ventana modal con resultados
    let html = `
        <div id="testResultsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full m-4 max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Resultados de la Prueba del Certificado</h3>
                        <button onclick="closeTestResults()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Estado General -->
                    <div class="mb-6 p-4 rounded-lg ${getStatusColor(results.overall_status)}">
                        <h4 class="font-medium">Estado General: ${getStatusText(results.overall_status)}</h4>
                    </div>
                    
                    <!-- Resultados Detallados -->
                    <div class="space-y-4">
    `;
    
    // Agregar cada categoría de resultados
    Object.keys(results).forEach(category => {
        if (category === 'overall_status') return;
        
        const result = results[category];
        html += `
            <div class="border rounded-lg p-4">
                <h5 class="font-medium mb-2">${getCategoryTitle(category)}</h5>
                <div class="text-sm ${getStatusColor(result.status)} p-2 rounded">
                    Estado: ${getStatusText(result.status)}
                </div>
        `;
        
        if (result.checks) {
            html += '<div class="mt-2 space-y-1">';
            Object.keys(result.checks).forEach(checkKey => {
                const check = result.checks[checkKey];
                html += `
                    <div class="flex items-center text-sm">
                        <span class="w-4 h-4 mr-2">${getStatusIcon(check.status)}</span>
                        <span>${check.message}</span>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        html += '</div>';
    });
    
    html += `
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button onclick="closeTestResults()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insertar en el DOM
    document.body.insertAdjacentHTML('beforeend', html);
}

function closeTestResults() {
    const modal = document.getElementById('testResultsModal');
    if (modal) {
        modal.remove();
    }
}

function getStatusColor(status) {
    switch(status) {
        case 'success': return 'bg-green-100 text-green-800';
        case 'warning': return 'bg-yellow-100 text-yellow-800';
        case 'error': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'success': return 'Éxito';
        case 'warning': return 'Advertencia';
        case 'error': return 'Error';
        case 'skipped': return 'Omitido';
        default: return 'Desconocido';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'success': return '✅';
        case 'warning': return '⚠️';
        case 'error': return '❌';
        default: return '❓';
    }
}

function getCategoryTitle(category) {
    switch(category) {
        case 'basic_validation': return 'Validación Básica';
        case 'file_validation': return 'Validación de Archivo';
        case 'certificate_validation': return 'Validación del Certificado';
        case 'webservice_testing': return 'Testing de Webservices';
        default: return category;
    }
}
    </script>
    @endpush
</x-app-layout>