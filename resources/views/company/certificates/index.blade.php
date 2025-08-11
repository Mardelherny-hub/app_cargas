<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.webservices.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔒 Gestión de Certificados Digitales
                </h2>
            </div>
            @if(!$certificateStatus['has_certificate'])
                <a href="{{ route('company.certificates.upload') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Subir Certificado
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estado Actual del Certificado -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado Actual del Certificado</h3>

                    @if($certificateStatus['has_certificate'])
                        <!-- Certificado Configurado -->
                        <div class="@if($certificateStatus['is_expired']) bg-red-50 @elseif($certificateStatus['is_expiring_soon']) bg-yellow-50 @else bg-green-50 @endif rounded-lg p-6">
                            <div class="flex items-start">
                                @if($certificateStatus['is_expired'])
                                    <svg class="w-8 h-8 text-red-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                @elseif($certificateStatus['is_expiring_soon'])
                                    <svg class="w-8 h-8 text-yellow-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @else
                                    <svg class="w-8 h-8 text-green-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                @endif
                                
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium @if($certificateStatus['is_expired']) text-red-800 @elseif($certificateStatus['is_expiring_soon']) text-yellow-800 @else text-green-800 @endif mb-3">
                                        {{ $certificateStatus['status'] }}
                                    </h4>

                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @if($certificateStatus['alias'])
                                            <div>
                                                <dt class="text-sm font-medium @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif">Alias del Certificado</dt>
                                                <dd class="text-sm @if($certificateStatus['is_expired']) text-red-900 @elseif($certificateStatus['is_expiring_soon']) text-yellow-900 @else text-green-900 @endif">{{ $certificateStatus['alias'] }}</dd>
                                            </div>
                                        @endif

                                        @if($certificateStatus['expires_at'])
                                            <div>
                                                <dt class="text-sm font-medium @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif">Fecha de Vencimiento</dt>
                                                <dd class="text-sm @if($certificateStatus['is_expired']) text-red-900 @elseif($certificateStatus['is_expiring_soon']) text-yellow-900 @else text-green-900 @endif">
                                                    {{ $certificateStatus['expires_at']->format('d/m/Y') }}
                                                    @if($certificateStatus['days_to_expiry'] !== null)
                                                        <span class="ml-2 text-xs">
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

                                        <div>
                                            <dt class="text-sm font-medium @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif">Estado de Webservices</dt>
                                            <dd class="text-sm @if($certificateStatus['is_expired']) text-red-900 @elseif($certificateStatus['is_expiring_soon']) text-yellow-900 @else text-green-900 @endif">
                                                @if($webserviceStatus['enabled'])
                                                    ✅ Habilitados ({{ $webserviceStatus['environment'] }})
                                                @else
                                                    ❌ Deshabilitados
                                                    @if($webserviceStatus['disabled_reason'])
                                                        <br><span class="text-xs">Razón: {{ $webserviceStatus['disabled_reason'] }}</span>
                                                    @endif
                                                @endif
                                            </dd>
                                        </div>

                                        <div>
                                            <dt class="text-sm font-medium @if($certificateStatus['is_expired']) text-red-700 @elseif($certificateStatus['is_expiring_soon']) text-yellow-700 @else text-green-700 @endif">Roles que Requieren Certificado</dt>
                                            <dd class="text-sm @if($certificateStatus['is_expired']) text-red-900 @elseif($certificateStatus['is_expiring_soon']) text-yellow-900 @else text-green-900 @endif">
                                                @if(count($rolesRequiringCertificate) > 0)
                                                    {{ implode(', ', $rolesRequiringCertificate) }}
                                                @else
                                                    No aplica
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>

                                    <!-- Acciones disponibles -->
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        @if($certificateStatus['is_expired'] || $certificateStatus['is_expiring_soon'])
                                            <a href="{{ route('company.certificates.renew') }}" 
                                               class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                                🔄 Renovar Certificado
                                            </a>
                                        @endif
                                        
                                        @if($certificateStatus['has_certificate'])
                                            <a href="{{ route('company.certificates.show', 'certificate') }}" 
                                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                                👁️ Ver Detalles
                                            </a>
                                        @endif

                                        <button onclick="testCertificate()" 
                                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                            🧪 Probar Certificado
                                        </button>

                                        <form method="POST" action="{{ route('company.certificates.destroy', 'certificate') }}" class="inline-block"
                                              onsubmit="return confirm('¿Está seguro? Esto deshabilitará todos los webservices.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Sin Certificado -->
                        <div class="bg-red-50 rounded-lg p-6">
                            <div class="flex items-start">
                                <svg class="w-8 h-8 text-red-500 mt-1 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-red-800 mb-3">Certificado Digital Requerido</h4>
                                    <p class="text-sm text-red-700 mb-4">
                                        Para utilizar los webservices aduaneros, su empresa debe configurar un certificado digital .p12 válido.
                                        Los webservices están actualmente deshabilitados.
                                    </p>

                                    @if(count($rolesRequiringCertificate) > 0)
                                        <div class="mb-4">
                                            <h5 class="text-sm font-medium text-red-800 mb-2">Roles que requieren certificado:</h5>
                                            <ul class="text-sm text-red-700">
                                                @foreach($rolesRequiringCertificate as $role)
                                                    <li>• {{ $role }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="flex gap-3">
                                        <a href="{{ route('company.certificates.upload') }}" 
                                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                            📤 Subir Certificado Ahora
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
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
                            El sistema acepta certificados en formato <strong>.p12</strong> o <strong>.pfx</strong> con un tamaño máximo de 2MB.
                            Estos deben ser emitidos por una autoridad certificadora reconocida por las aduanas correspondientes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Enlaces Relacionados -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">🔗 Enlaces Relacionados</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route('company.webservices.index') }}" 
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

            // TODO: Implementar test de certificado via AJAX
            alert('Función de prueba en desarrollo. Próximamente disponible.');
        }
    </script>
    @endpush
</x-app-layout>