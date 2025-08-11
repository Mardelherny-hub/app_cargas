{{-- resources/views/company/webservices/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard Webservices') }} - {{ $company->legal_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                    📊 Historial
                </a>
                @if(auth()->user()->hasRole('company-admin'))
                <a href="{{ route('company.certificates.index') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    🔐 Certificados
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Estado del Certificado Digital --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        🔐 Estado del Certificado Digital
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 rounded-full 
                                {{ $certificateStatus['status_color'] === 'green' ? 'bg-green-500' : 
                                   ($certificateStatus['status_color'] === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}">
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Estado</p>
                                <p class="text-lg font-semibold 
                                    {{ $certificateStatus['status_color'] === 'green' ? 'text-green-700' : 
                                       ($certificateStatus['status_color'] === 'yellow' ? 'text-yellow-700' : 'text-red-700') }}">
                                    {{ $certificateStatus['status_text'] }}
                                </p>
                            </div>
                        </div>
                        
                        @if($certificateStatus['expires_at'])
                        <div>
                            <p class="text-sm font-medium text-gray-600">Fecha de Vencimiento</p>
                            <p class="text-lg font-semibold text-gray-900">
                                {{ $certificateStatus['expires_at']->format('d/m/Y') }}
                            </p>
                            @if($certificateStatus['days_to_expiry'] !== null)
                            <p class="text-xs text-gray-500">
                                @if($certificateStatus['days_to_expiry'] > 0)
                                    {{ $certificateStatus['days_to_expiry'] }} días restantes
                                @else
                                    Vencido hace {{ abs($certificateStatus['days_to_expiry']) }} días
                                @endif
                            </p>
                            @endif
                        </div>
                        @endif
                        
                        <div class="flex space-x-2">
                            @if(!$certificateStatus['has_certificate'])
                                <a href="{{ route('company.certificates.index') }}" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                    Subir Certificado
                                </a>
                            @elseif($certificateStatus['needs_renewal'])
                                <a href="{{ route('company.certificates.index') }}" 
                                   class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                    Renovar Certificado
                                </a>
                            @else
                                <a href="{{ route('company.certificates.index') }}" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                    Ver Certificado
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Estadísticas Generales --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <span class="text-blue-600 font-bold text-lg">📊</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total (30d)</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_transactions'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                    <span class="text-green-600 font-bold text-lg">✅</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Tasa de Éxito</p>
                                <p class="text-2xl font-bold text-green-700">{{ $stats['success_rate'] }}%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <span class="text-yellow-600 font-bold text-lg">⏰</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Última semana</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $stats['last_7d'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <span class="text-purple-600 font-bold text-lg">📈</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Últimas 24h</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $stats['last_24h'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Webservices Disponibles según Roles --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        🌐 Webservices Disponibles
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Roles de empresa: 
                        <span class="font-medium">{{ implode(', ', $companyRoles) ?: 'Sin roles asignados' }}</span>
                    </p>
                </div>
                
                <div class="p-6">
                    @if(!empty($companyRoles))
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            
                            {{-- Argentina - Información Anticipada --}}
                            @if(in_array('Cargas', $companyRoles))
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">🇦🇷 Información Anticipada</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Activo
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Envío de información anticipada de carga
                                </p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.manifests.customs.index', ['webservice_type' => 'anticipada']) }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Enviar
                                    </a>
                                    <a href="{{ route('company.webservices.query', ['type' => 'anticipada']) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Consultar
                                    </a>
                                </div>
                                @if(isset($stats['anticipada']))
                                <div class="mt-3 text-xs text-gray-500">
                                    Total: {{ $stats['anticipada']['total'] }} | 
                                    Exitosos: {{ $stats['anticipada']['success'] }} | 
                                    Pendientes: {{ $stats['anticipada']['pending'] }}
                                </div>
                                @endif
                            </div>

                            {{-- Argentina - MIC/DTA --}}
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">🇦🇷 MIC/DTA</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Activo
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Manifiesto Internacional de Carga
                                </p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.manifests.customs.index', ['webservice_type' => 'micdta']) }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Enviar
                                    </a>
                                    <a href="{{ route('company.webservices.query', ['type' => 'micdta']) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Consultar
                                    </a>
                                </div>
                                @if(isset($stats['micdta']))
                                <div class="mt-3 text-xs text-gray-500">
                                    Total: {{ $stats['micdta']['total'] }} | 
                                    Exitosos: {{ $stats['micdta']['success'] }} | 
                                    Pendientes: {{ $stats['micdta']['pending'] }}
                                </div>
                                @endif
                            </div>
                            @endif

                            {{-- Argentina - Desconsolidados --}}
                            @if(in_array('Desconsolidador', $companyRoles))
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">🇦🇷 Desconsolidados</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Activo
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Títulos de desconsolidación
                                </p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.manifests.customs.index', ['webservice_type' => 'desconsolidados']) }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Enviar
                                    </a>
                                    <a href="{{ route('company.webservices.query', ['type' => 'desconsolidados']) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Consultar
                                    </a>
                                </div>
                                @if(isset($stats['desconsolidados']))
                                <div class="mt-3 text-xs text-gray-500">
                                    Total: {{ $stats['desconsolidados']['total'] }} | 
                                    Exitosos: {{ $stats['desconsolidados']['success'] }} | 
                                    Pendientes: {{ $stats['desconsolidados']['pending'] }}
                                </div>
                                @endif
                            </div>
                            @endif

                            {{-- Argentina - Transbordos --}}
                            @if(in_array('Transbordos', $companyRoles))
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">🇦🇷 Transbordos</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Activo
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Operaciones de transbordo
                                </p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.manifests.customs.index', ['webservice_type' => 'transbordos']) }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Enviar
                                    </a>
                                    <a href="{{ route('company.webservices.query', ['type' => 'transbordos']) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Consultar
                                    </a>
                                </div>
                                @if(isset($stats['transbordos']))
                                <div class="mt-3 text-xs text-gray-500">
                                    Total: {{ $stats['transbordos']['total'] }} | 
                                    Exitosos: {{ $stats['transbordos']['success'] }} | 
                                    Pendientes: {{ $stats['transbordos']['pending'] }}
                                </div>
                                @endif
                            </div>
                            @endif

                            {{-- Paraguay --}}
                            @if($company->country === 'PY' || in_array($company->country, ['AR', 'PY']))
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-green-300 transition-colors">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-900">🇵🇾 DNA Paraguay</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Activo
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Declaraciones aduaneras Paraguay
                                </p>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.manifests.customs.index', ['webservice_type' => 'paraguay']) }}" 
                                       class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Enviar
                                    </a>
                                    <a href="{{ route('company.webservices.query', ['type' => 'paraguay']) }}" 
                                       class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Consultar
                                    </a>
                                </div>
                                @if(isset($stats['paraguay']))
                                <div class="mt-3 text-xs text-gray-500">
                                    Total: {{ $stats['paraguay']['total'] }} | 
                                    Exitosos: {{ $stats['paraguay']['success'] }} | 
                                    Pendientes: {{ $stats['paraguay']['pending'] }}
                                </div>
                                @endif
                            </div>
                            @endif

                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-gray-400 text-2xl">⚠️</span>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Sin Roles Asignados</h3>
                            <p class="text-gray-600 mb-4">
                                Su empresa no tiene roles asignados para usar webservices.
                            </p>
                            <p class="text-sm text-gray-500">
                                Contacte al administrador del sistema para asignar roles como: Cargas, Desconsolidador, Transbordos.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Acciones Rápidas --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {{-- Transacciones Recientes --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">📋 Transacciones Recientes</h3>
                            <a href="{{ route('company.webservices.history') }}" 
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Ver todas →
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        @if($recentTransactions->count() > 0)
                            <div class="space-y-3">
                                @foreach($recentTransactions as $transaction)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-2 h-2 rounded-full 
                                                {{ $transaction['status_color'] === 'green' ? 'bg-green-500' : 
                                                   ($transaction['status_color'] === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}">
                                            </span>
                                            <span class="font-medium text-sm text-gray-900">
                                                {{ ucfirst($transaction['type']) }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                {{ $transaction['status_text'] }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600 mt-1">
                                            {{ $transaction['transaction_id'] }} - 
                                            {{ $transaction['created_at']->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">{{ $transaction['user_name'] }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-gray-400">📋</span>
                                </div>
                                <p class="text-gray-600 text-sm">No hay transacciones recientes</p>
                                <p class="text-gray-500 text-xs mt-1">
                                    Las transacciones aparecerán aquí cuando envíe manifiestos
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Acciones Rápidas --}}
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">⚡ Acciones Rápidas</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            
                            {{-- Importar Manifiestos --}}
                            <a href="{{ route('company.manifests.import.index') }}" 
                               class="block w-full p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <span class="text-blue-600 text-lg">📊</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Importar Manifiestos</h4>
                                        <p class="text-sm text-gray-600">Subir archivo Excel/CSV</p>
                                    </div>
                                </div>
                            </a>

                            {{-- Enviar a Aduana --}}
                            <a href="{{ route('company.manifests.customs.index') }}" 
                               class="block w-full p-4 border border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <span class="text-purple-600 text-lg">🏛️</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Enviar a Aduana</h4>
                                        <p class="text-sm text-gray-600">Enviar manifiestos a AFIP/DNA</p>
                                    </div>
                                </div>
                            </a>

                            {{-- Ver Historial --}}
                            <a href="{{ route('company.webservices.history') }}" 
                               class="block w-full p-4 border border-gray-200 rounded-lg hover:border-gray-300 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <span class="text-gray-600 text-lg">📈</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Historial Completo</h4>
                                        <p class="text-sm text-gray-600">Ver todas las transacciones y logs</p>
                                    </div>
                                </div>
                            </a>

                            {{-- Consultar Estado --}}
                            <a href="{{ route('company.webservices.query') }}" 
                               class="block w-full p-4 border border-gray-200 rounded-lg hover:border-green-300 hover:bg-green-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <span class="text-green-600 text-lg">🔍</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Consultar Estado</h4>
                                        <p class="text-sm text-gray-600">Verificar estado de manifiestos enviados</p>
                                    </div>
                                </div>
                            </a>

                            {{-- Certificados (solo admins) --}}
                            @if(auth()->user()->hasRole('company-admin'))
                            <a href="{{ route('company.certificates.index') }}" 
                               class="block w-full p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <span class="text-blue-600 text-lg">🔐</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Gestionar Certificados</h4>
                                        <p class="text-sm text-gray-600">Subir, renovar y probar certificados digitales</p>
                                    </div>
                                </div>
                            </a>
                            @endif

                        </div>
                    </div>
                </div>

            </div>

            {{-- Alertas y Notificaciones --}}
            @if($certificateStatus['needs_renewal'] || !$certificateStatus['has_certificate'])
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                        <span class="text-yellow-600">⚠️</span>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-yellow-800">
                            @if(!$certificateStatus['has_certificate'])
                                Certificado Digital Requerido
                            @else
                                Certificado Próximo a Vencer
                            @endif
                        </h4>
                        <p class="text-sm text-yellow-700 mt-1">
                            @if(!$certificateStatus['has_certificate'])
                                Debe subir un certificado digital .p12 para usar los webservices aduaneros.
                            @elseif($certificateStatus['is_expired'])
                                Su certificado ha vencido el {{ $certificateStatus['expires_at']->format('d/m/Y') }}. 
                                Renuévelo para continuar usando webservices.
                            @else
                                Su certificado vence en {{ $certificateStatus['days_to_expiry'] }} días 
                                ({{ $certificateStatus['expires_at']->format('d/m/Y') }}). Se recomienda renovarlo.
                            @endif
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('company.certificates.index') }}" 
                           class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            @if(!$certificateStatus['has_certificate'])
                                Subir Certificado
                            @else
                                Renovar Ahora
                            @endif
                        </a>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>