<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard Webservices') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $company->ws_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $company->ws_active ? 'Activo' : 'Inactivo' }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ ucfirst($company->ws_environment ?? 'testing') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Estado General de Certificados -->
            @if(!$certificateStatus['has_certificate'])
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Certificado Digital Requerido</h3>
                            <p class="mt-1 text-sm text-red-700">
                                Para usar los webservices necesita configurar un certificado digital válido.
                                <a href="{{ route('company.certificates.index') }}" class="font-medium underline">Configurar ahora</a>
                            </p>
                        </div>
                    </div>
                </div>
            @elseif($certificateStatus['is_expired'])
                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Certificado Digital Vencido</h3>
                            <p class="mt-1 text-sm text-yellow-700">
                                Su certificado digital está vencido. Los webservices no funcionarán correctamente.
                                <a href="{{ route('company.certificates.index') }}" class="font-medium underline">Renovar certificado</a>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Widgets de Estado por Webservice -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                @if(in_array('Cargas', $companyRoles))
                    <!-- Argentina Anticipada -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <span class="text-white text-lg">🇦🇷</span>
                                </div>
                                <div class="ml-4 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Info. Anticipada</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ $stats['anticipada']['total'] ?? 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm">
                                    <span class="text-green-600 font-medium">{{ $stats['anticipada']['success'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">éxito</span>
                                    <span class="text-red-600 font-medium">{{ $stats['anticipada']['failed'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">error</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('company.webservices.send') }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Enviar manifiesto →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Argentina MIC/DTA -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">MIC/DTA</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ $stats['micdta']['total'] ?? 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm">
                                    <span class="text-green-600 font-medium">{{ $stats['micdta']['success'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">éxito</span>
                                    <span class="text-red-600 font-medium">{{ $stats['micdta']['failed'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">error</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('company.webservices.send') }}?type=micdta" class="text-green-600 hover:text-green-800 text-xs font-medium">
                                        Registrar envíos →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(in_array('Desconsolidador', $companyRoles))
                    <!-- Desconsolidados -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </div>
                                <div class="ml-4 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Desconsolidados</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ $stats['desconsolidados']['total'] ?? 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm">
                                    <span class="text-green-600 font-medium">{{ $stats['desconsolidados']['success'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">éxito</span>
                                    <span class="text-red-600 font-medium">{{ $stats['desconsolidados']['failed'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">error</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('company.webservices.send') }}?type=desconsolidados" class="text-purple-600 hover:text-purple-800 text-xs font-medium">
                                        Gestionar títulos →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(in_array('Transbordos', $companyRoles))
                    <!-- Transbordos -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-4 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Transbordos</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ $stats['transbordos']['total'] ?? 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm">
                                    <span class="text-green-600 font-medium">{{ $stats['transbordos']['success'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">éxito</span>
                                    <span class="text-red-600 font-medium">{{ $stats['transbordos']['failed'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">error</span>
                                </div>
                                <div class="mt-2">
                                    <a href="{{ route('company.webservices.send') }}?type=transbordos" class="text-orange-600 hover:text-orange-800 text-xs font-medium">
                                        Registrar barcazas →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Paraguay (si corresponde) -->
                @if($company->country === 'PY' || in_array('Cargas', $companyRoles))
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                    <span class="text-white text-lg">🇵🇾</span>
                                </div>
                                <div class="ml-4 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Paraguay</dt>
                                        <dd class="text-lg font-medium text-gray-900">{{ $stats['paraguay']['total'] ?? 0 }}</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm">
                                    <span class="text-green-600 font-medium">{{ $stats['paraguay']['success'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">éxito</span>
                                    <span class="text-red-600 font-medium">{{ $stats['paraguay']['failed'] ?? 0 }}</span>
                                    <span class="text-gray-500 mx-1">error</span>
                                </div>
                                <div class="mt-2">
                                    <span class="text-gray-500 text-xs">Próximamente</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Panel Principal -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Transacciones Recientes -->
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">Últimas Transacciones</h3>
                                <a href="{{ route('company.webservices.history') }}" class="text-sm text-blue-600 hover:text-blue-800">
                                    Ver todas →
                                </a>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            @forelse($recentTransactions as $transaction)
                                <div class="px-6 py-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                @if($transaction->status === 'success')
                                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                                @elseif($transaction->status === 'failed')
                                                    <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                                @else
                                                    <div class="w-2 h-2 bg-yellow-400 rounded-full"></div>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ ucfirst($transaction->webservice_type) }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                                    @if($transaction->reference_number)
                                                        • Ref: {{ $transaction->reference_number }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($transaction->status === 'success') bg-green-100 text-green-800 
                                                @elseif($transaction->status === 'failed') bg-red-100 text-red-800 
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($transaction->status) }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ $transaction->country }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-6 py-8 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Sin transacciones</h3>
                                    <p class="mt-1 text-sm text-gray-500">Comience enviando un manifiesto a los webservices.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Panel Lateral -->
                <div class="space-y-6">
                    
                    <!-- Acciones Rápidas -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Acciones Rápidas</h3>
                        </div>
                        <div class="p-6 space-y-3">
                            @if(in_array('Cargas', $companyRoles))
                                <a href="{{ route('company.webservices.send') }}" 
                                   class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md border border-gray-200">
                                    📤 Enviar Manifiesto
                                </a>
                            @endif
                            <a href="{{ route('company.webservices.query') }}" 
                               class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md border border-gray-200">
                                🔍 Consultar Estado
                            </a>
                            @if($certificateStatus['has_certificate'])
                                <a href="{{ route('company.certificates.index') }}" 
                                   class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md border border-gray-200">
                                    🔒 Gestionar Certificados
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Estado del Sistema -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Estado del Sistema</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Webservices</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $company->ws_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $company->ws_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Ambiente</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ ucfirst($company->ws_environment ?? 'testing') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Certificado</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($certificateStatus['has_certificate'] && !$certificateStatus['is_expired']) bg-green-100 text-green-800
                                    @elseif($certificateStatus['has_certificate'] && $certificateStatus['is_expired']) bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800 @endif">
                                    @if($certificateStatus['has_certificate'] && !$certificateStatus['is_expired']) Válido
                                    @elseif($certificateStatus['has_certificate'] && $certificateStatus['is_expired']) Vencido
                                    @else Sin certificado @endif
                                </span>
                            </div>
                            @if($certificateStatus['has_certificate'] && $certificateStatus['expires_at'])
                                <div class="text-xs text-gray-500">
                                    Vence: {{ $certificateStatus['expires_at']->format('d/m/Y') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Roles Activos -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Roles Activos</h3>
                        </div>
                        <div class="p-6">
                            @if(count($companyRoles) > 0)
                                <div class="space-y-2">
                                    @foreach($companyRoles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($role === 'Cargas') bg-blue-100 text-blue-800
                                            @elseif($role === 'Desconsolidador') bg-purple-100 text-purple-800
                                            @elseif($role === 'Transbordos') bg-orange-100 text-orange-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ $role }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No hay roles asignados</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>