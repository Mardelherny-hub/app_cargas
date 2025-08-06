<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.show', $user) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestionar Permisos') }} - {{ $user->name }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Información del usuario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-gray-300 rounded-full flex items-center justify-center">
                            <span class="text-lg font-medium text-gray-600">
                                {{ substr($user->name, 0, 2) }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $user->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $user->email }}</p>
                            <div class="flex items-center mt-1">
                                @php $info = $user->display_info; @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $info['badge_class'] }}">
                                    {{ $info['type'] }}
                                </span>
                                @if($info['subtitle'])
                                    <span class="ml-2 text-xs text-gray-500">{{ $info['subtitle'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ADVERTENCIA para el sistema actual -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">
                            Gestión de Permisos Simplificada
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p><strong>Nuestro sistema usa 3 roles simplificados:</strong></p>
                            <ul class="mt-1 ml-4 list-disc">
                                <li><strong>Super Administrador:</strong> Acceso total automático</li>
                                <li><strong>Administrador de Empresa:</strong> Permisos completos en su empresa</li>
                                <li><strong>Operador:</strong> Permisos según roles de empresa + permisos individuales</li>
                            </ul>
                            <p class="mt-2"><strong>Los permisos se gestionan automáticamente según el tipo de usuario.</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permisos actuales del usuario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Permisos Actuales</h3>
                    
                    @if($user->hasRole('super-admin'))
                        <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-purple-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <span class="text-purple-900 font-medium">ACCESO TOTAL AL SISTEMA</span>
                            </div>
                            <p class="text-sm text-purple-700 mt-1">
                                Como Super Administrador, tiene acceso completo a todas las funcionalidades.
                            </p>
                        </div>

                    @elseif($user->hasRole('company-admin'))
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-blue-900 font-medium">ADMINISTRADOR DE EMPRESA</span>
                            </div>
                            <p class="text-sm text-blue-700 mt-1">
                                Gestión completa de su empresa: usuarios, operadores, datos y reportes.
                            </p>
                            @if($user->userable && $user->userable_type === 'App\Models\Company')
                                <p class="text-sm text-blue-600 mt-2">
                                    <strong>Empresa:</strong> {{ $user->userable->legal_name }}
                                </p>
                            @endif
                        </div>

                    @elseif($user->hasRole('user'))
                        @if($user->userable && $user->userable_type === 'App\Models\Operator')
                            @php $operator = $user->userable; @endphp
                            <div class="space-y-4">
                                <!-- Tipo de operador -->
                                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-green-900 font-medium">
                                            OPERADOR {{ strtoupper($operator->type === 'internal' ? 'INTERNO' : 'EXTERNO') }}
                                        </span>
                                    </div>
                                    @if($operator->type === 'external' && $operator->company)
                                        <p class="text-sm text-green-700 mt-1">
                                            <strong>Empresa:</strong> {{ $operator->company->legal_name }}
                                        </p>
                                    @elseif($operator->type === 'internal')
                                        <p class="text-sm text-green-700 mt-1">
                                            Acceso global al sistema para soporte técnico
                                        </p>
                                    @endif
                                </div>

                                <!-- Permisos individuales -->
                                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">Permisos Individuales:</h4>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div class="flex items-center">
                                            @if($operator->can_import)
                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-green-700 text-sm">Puede Importar</span>
                                            @else
                                                <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-red-700 text-sm">No puede Importar</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center">
                                            @if($operator->can_export)
                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-green-700 text-sm">Puede Exportar</span>
                                            @else
                                                <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-red-700 text-sm">No puede Exportar</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center">
                                            @if($operator->can_transfer)
                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-green-700 text-sm">Puede Transferir</span>
                                            @else
                                                <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-red-700 text-sm">No puede Transferir</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Roles de empresa -->
                                @if($operator->type === 'external' && $operator->company && $operator->company->company_roles)
                                    <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                                        <h4 class="text-sm font-medium text-indigo-900 mb-3">Roles de Empresa (Funcionalidades Disponibles):</h4>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($operator->company->company_roles as $role)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                    {{ $role }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-red-900 font-medium">USUARIO MAL CONFIGURADO</span>
                                </div>
                                <p class="text-sm text-red-700 mt-1">
                                    Este usuario tiene rol 'user' pero no tiene un operador asociado. 
                                    <a href="{{ route('admin.users.edit', $user) }}" class="underline">Editar usuario</a> para corregir.
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Instrucciones para modificar permisos -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">¿Cómo Modificar Permisos?</h3>
                    
                    <div class="space-y-4">
                        @if($user->hasRole('super-admin'))
                            <p class="text-sm text-gray-600">
                                Los permisos del Super Administrador no se pueden modificar - siempre tiene acceso total.
                            </p>

                        @elseif($user->hasRole('company-admin'))
                            <div>
                                <p class="text-sm text-gray-600 mb-2">
                                    Los permisos del Administrador de Empresa son automáticos según su empresa asignada.
                                </p>
                                <a href="{{ route('admin.users.edit', $user) }}" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                                    Cambiar Empresa Asignada
                                </a>
                            </div>

                        @elseif($user->hasRole('user'))
                            <div>
                                <p class="text-sm text-gray-600 mb-3">
                                    Los permisos del Operador se modifican editando sus datos:
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-900">Permisos Individuales</h4>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Import/Export/Transfer se configuran en la edición del usuario.
                                        </p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <h4 class="text-sm font-medium text-gray-900">Funcionalidades</h4>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Dependen de los roles asignados a su empresa.
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <a href="{{ route('admin.users.edit', $user) }}" 
                                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md">
                                        Editar Operador
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Botón volver -->
            <div class="mt-6 flex justify-end">
                <a href="{{ route('admin.users.show', $user) }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-md text-sm font-medium">
                    ← Volver al Usuario
                </a>
            </div>
        </div>
    </div>
</x-app-layout>