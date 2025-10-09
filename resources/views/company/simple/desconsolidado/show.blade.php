<x-app-layout>
    <x-slot name="header">
    @include('company.simple.partials.afip-header', [
        'voyage'  => $voyage,
        'company' => $company ?? null,
        'active'  => 'desconsolidado',
    ])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Mensajes de éxito/error --}}
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Información del Viaje --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Viaje</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Títulos Desconsolidados</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $desconsolidatedBillsCount }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Contenedores</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $containersCount }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Estado General</p>
                            <p class="mt-1">
                                @if($validation['errors'])
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                        ❌ Con Errores
                                    </span>
                                @elseif($validation['warnings'])
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        ⚠️ Con Advertencias
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        ✅ Listo
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Validaciones y Advertencias --}}
            @if($validation['errors'] || $validation['warnings'])
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        @if($validation['errors'])
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-red-800 mb-2">❌ Errores que deben corregirse:</h4>
                                <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
                                    @foreach($validation['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($validation['warnings'])
                            <div>
                                <h4 class="text-sm font-medium text-yellow-800 mb-2">⚠️ Advertencias:</h4>
                                <ul class="list-disc list-inside space-y-1 text-sm text-yellow-700">
                                    @foreach($validation['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Botones de Acción Secuencial --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Operaciones Disponibles</h3>
                    
                    <div class="space-y-4">
                        {{-- PASO 1: Registrar Títulos --}}
                        <div class="border rounded-lg p-4 {{ $estados['registrar'] === 'success' ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $estados['registrar'] === 'success' ? 'bg-green-500' : 'bg-gray-400' }} text-white font-bold mr-3">
                                            1
                                        </span>
                                        <div>
                                            <h4 class="text-base font-medium text-gray-900">Registrar Títulos Desconsolidador</h4>
                                            <p class="text-sm text-gray-600">Enviar títulos desconsolidados a AFIP por primera vez</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    @if($estados['registrar'] === 'success')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✅ Registrado
                                        </span>
                                    @elseif($estados['registrar'] === 'pending')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                            ⏳ Pendiente
                                        </span>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('company.simple.desconsolidado.send', $voyage) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="registrar">
                                        <button type="submit" 
                                                {{ $validation['errors'] ? 'disabled' : '' }}
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 {{ $validation['errors'] ? 'opacity-50 cursor-not-allowed' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                            {{ $estados['registrar'] === 'success' ? 'Re-enviar' : 'Registrar' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- PASO 2: Rectificar Títulos --}}
                        <div class="border rounded-lg p-4 {{ $estados['rectificar'] === 'success' ? 'bg-green-50 border-green-200' : ($estados['registrar'] !== 'success' ? 'bg-gray-100 border-gray-200' : 'bg-gray-50 border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $estados['rectificar'] === 'success' ? 'bg-green-500' : 'bg-gray-400' }} text-white font-bold mr-3">
                                            2
                                        </span>
                                        <div>
                                            <h4 class="text-base font-medium {{ $estados['registrar'] !== 'success' ? 'text-gray-500' : 'text-gray-900' }}">
                                                Rectificar Títulos Desconsolidador
                                            </h4>
                                            <p class="text-sm text-gray-600">Modificar títulos previamente registrados</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    @if($estados['rectificar'] === 'success')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            ✅ Rectificado
                                        </span>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('company.simple.desconsolidado.send', $voyage) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="rectificar">
                                        <button type="submit" 
                                                {{ ($estados['registrar'] !== 'success' || $validation['errors']) ? 'disabled' : '' }}
                                                class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 {{ ($estados['registrar'] !== 'success' || $validation['errors']) ? 'opacity-50 cursor-not-allowed' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Rectificar
                                        </button>
                                    </form>
                                </div>
                            </div>
                            @if($estados['registrar'] !== 'success')
                                <p class="mt-2 text-sm text-gray-500 ml-11">⚠️ Primero debe registrar los títulos</p>
                            @endif
                        </div>

                        {{-- PASO 3: Eliminar Títulos --}}
                        <div class="border rounded-lg p-4 {{ $estados['eliminar'] === 'success' ? 'bg-red-50 border-red-200' : ($estados['registrar'] !== 'success' ? 'bg-gray-100 border-gray-200' : 'bg-gray-50 border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $estados['eliminar'] === 'success' ? 'bg-red-500' : 'bg-gray-400' }} text-white font-bold mr-3">
                                            3
                                        </span>
                                        <div>
                                            <h4 class="text-base font-medium {{ $estados['registrar'] !== 'success' ? 'text-gray-500' : 'text-gray-900' }}">
                                                Eliminar Títulos Desconsolidador
                                            </h4>
                                            <p class="text-sm text-gray-600">Eliminar títulos registrados en AFIP</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center space-x-3">
                                    @if($estados['eliminar'] === 'success')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            🗑️ Eliminado
                                        </span>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('company.simple.desconsolidado.send', $voyage) }}"
                                        onsubmit="return confirm('⚠️ ATENCIÓN: Esta acción eliminará los títulos de AFIP. ¿Está seguro?');">
                                        @csrf
                                        <input type="hidden" name="action" value="eliminar">
                                        <button type="submit" 
                                                {{ $estados['registrar'] !== 'success' ? 'disabled' : '' }}
                                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 {{ $estados['registrar'] !== 'success' ? 'opacity-50 cursor-not-allowed' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                            @if($estados['registrar'] !== 'success')
                                <p class="mt-2 text-sm text-gray-500 ml-11">⚠️ Primero debe registrar los títulos</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Historial de Transacciones --}}
            @if($transactions->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Historial de Transacciones</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operación</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensaje</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($transactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ ucfirst($transaction->additional_metadata['method'] ?? 'N/A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($transaction->status === 'success')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        ✅ Exitoso
                                                    </span>
                                                @elseif($transaction->status === 'error')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        ❌ Error
                                                    </span>
                                                @elseif($transaction->status === 'pending')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        ⏳ Pendiente
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        {{ $transaction->status }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ Str::limit($transaction->error_message ?? $transaction->confirmation_number ?? 'Procesado correctamente', 50) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $transaction->user->name ?? 'Sistema' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>