<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.companies.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalles de la Empresa') }}
                </h2>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.companies.edit', $company) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                </a>
                <a href="{{ route('admin.companies.certificates', $company) }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Certificados
                </a>
                @if($company->active)
                    <form method="POST" action="{{-- route('admin.companies.toggle-status', $company) --}} #" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                onclick="return confirm('¿Estás seguro de que quieres desactivar esta empresa? Esto afectará a todos sus operadores.')"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Desactivar
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{-- route('admin.companies.toggle-status', $company) --}} #" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Activar
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Información Principal de la Empresa -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-6">
                            <!-- Logo/Avatar de la empresa -->
                            <div class="h-20 w-20 bg-gradient-to-br from-green-500 to-blue-600 rounded-lg flex items-center justify-center">
                                <span class="text-2xl font-bold text-white">
                                    {{ strtoupper(substr($company->legal_name, 0, 2)) }}
                                </span>
                            </div>
                            <!-- Información básica -->
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ $company->legal_name }}</h1>
                                @if($company->commercial_name)
                                    <p class="text-lg text-gray-600">{{ $company->commercial_name }}</p>
                                @endif
                                <div class="flex items-center space-x-4 mt-2">
                                    <!-- CUIT -->
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        CUIT: {{ $company->tax_id }}
                                    </span>
                                    <!-- País -->
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $company->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                    </span>
                                    <!-- Estado -->
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $company->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $company->active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Información adicional -->
                        <div class="text-right text-sm text-gray-500">
                            <div>ID: #{{ $company->id }}</div>
                            <div>Registrada: {{ $company->created_date ? Carbon\Carbon::parse($company->created_date)->format('d/m/Y') : 'N/A' }}</div>
                            @if($company->last_access)
                                <div>Último acceso: {{ Carbon\Carbon::parse($company->last_access)->format('d/m/Y H:i') }}</div>
                            @else
                                <div>Último acceso: Nunca</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Información de Contacto -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de Contacto</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($company->email)
                                        <a href="mailto:{{ $company->email }}" class="text-blue-600 hover:text-blue-500">
                                            {{ $company->email }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">No especificado</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Teléfono</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($company->phone)
                                        <a href="tel:{{ $company->phone }}" class="text-blue-600 hover:text-blue-500">
                                            {{ $company->phone }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">No especificado</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($company->address)
                                        {{ $company->address }}
                                        @if($company->city), {{ $company->city }}@endif
                                        @if($company->postal_code) ({{ $company->postal_code }})@endif
                                    @else
                                        <span class="text-gray-500">No especificada</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Estado de Certificados -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Certificados Digitales</h3>
                            <a href="{{ route('admin.companies.certificates', $company) }}"
                               class="text-sm text-blue-600 hover:text-blue-500">
                                Gestionar →
                            </a>
                        </div>

                        @if($company->certificate_path)
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-green-800">Certificado Configurado</span>
                                </div>

                                @if($company->certificate_alias)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Alias</dt>
                                        <dd class="text-sm text-gray-900">{{ $company->certificate_alias }}</dd>
                                    </div>
                                @endif

                                @if($company->certificate_expires_at)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fecha de Vencimiento</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ $company->certificate_expires_at->format('d/m/Y') }}
                                            @if($company->certificate_expires_at->isPast())
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                    Vencido
                                                </span>
                                            @elseif($company->certificate_expires_at->diffInDays() <= 30)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                                                    Vence pronto
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 ml-2">
                                                    Vigente
                                                </span>
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                <span class="text-sm font-medium text-red-800">No hay certificado configurado</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Es necesario configurar un certificado digital para utilizar los webservices.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- WebServices Configuration -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Configuración de WebServices</h3>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $company->ws_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $company->ws_active ? 'Activo' : 'Inactivo' }}
                            </span>
                            @if($company->ws_environment)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ ucfirst($company->ws_environment) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($company->ws_config)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @php
                                $wsConfig = is_string($company->ws_config) ? json_decode($company->ws_config, true) : $company->ws_config;
                            @endphp

                            @if(is_array($wsConfig))
                                @foreach($wsConfig as $key => $value)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                        <dd class="text-sm text-gray-900">
                                            @if(is_bool($value))
                                                {{ $value ? 'Sí' : 'No' }}
                                            @elseif(is_array($value))
                                                {{ json_encode($value) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No hay configuración de webservices definida.</p>
                    @endif

                    <div class="mt-4 flex space-x-3">
                        <a href="{{ route('admin.companies.webservices', $company) }}"
                           class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Configurar WebServices
                        </a>
                        @if($company->ws_active)
                            <button onclick="testWebservice({{ $company->id }})"
                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                Probar Conexión
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Operadores de la Empresa -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Operadores ({{ $company->operators->count() }})</h3>
                        <a href="{{ route('admin.companies.operators', $company) }}"
                           class="text-sm text-blue-600 hover:text-blue-500">
                            Ver todos →
                        </a>
                    </div>

                    @if($company->operators->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($company->operators->take(6) as $operator)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-white">
                                                {{ strtoupper(substr($operator->first_name, 0, 1) . substr($operator->last_name, 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $operator->first_name }} {{ $operator->last_name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $operator->position ?? 'Sin cargo especificado' }}
                                            </div>
                                            @if($operator->user)
                                                <div class="text-xs text-blue-600">
                                                    {{ $operator->user->email }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $operator->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $operator->active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                        @if($operator->user)
                                            <a href="{{ route('admin.users.show', $operator->user) }}"
                                               class="text-blue-600 hover:text-blue-500">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($company->operators->count() > 6)
                            <div class="mt-4 text-center">
                                <a href="{{ route('admin.companies.operators', $company) }}"
                                   class="text-sm text-blue-600 hover:text-blue-500">
                                    Ver todos los {{ $company->operators->count() }} operadores →
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-6">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay operadores</h3>
                            <p class="mt-1 text-sm text-gray-500">Esta empresa no tiene operadores asignados</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Estadísticas y Actividad -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Estadísticas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ $company->operators->count() }}</div>
                                <div class="text-sm text-gray-600">Operadores</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    {{ $company->operators->where('active', true)->count() }}
                                </div>
                                <div class="text-sm text-gray-600">Activos</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">
                                    {{ $company->created_date ? \Carbon\Carbon::parse($company->created_date)->diffInDays() : 0 }}
                                </div>
                                <div class="text-sm text-gray-600">Días en sistema</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600">
                                    @if($company->last_access)
                                        {{ \Carbon\Carbon::parse($company->last_access)->diffInDays() }}
                                    @else
                                        --
                                    @endif
                                </div>
                                <div class="text-sm text-gray-600">Días último acceso</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
                        <div class="space-y-3">
                            <a href="{{ route('admin.companies.certificates', $company) }}"
                               class="flex items-center p-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                                <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span class="text-sm font-medium">Gestionar Certificados</span>
                            </a>

                            <a href="{{ route('admin.companies.webservices', $company) }}"
                               class="flex items-center p-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                                <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"/>
                                </svg>
                                <span class="text-sm font-medium">Configurar WebServices</span>
                            </a>

                            <a href="{{ route('admin.companies.operators', $company) }}"
                               class="flex items-center p-3 border border-gray-300 rounded-lg hover:bg-gray-50">
                                <svg class="w-5 h-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span class="text-sm font-medium">Gestionar Operadores</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function testWebservice(companyId) {
            // Aquí iría la lógica para probar la conexión del webservice
            // Por ahora mostramos un mensaje
            alert('Función de prueba de webservice en desarrollo');
        }
    </script>
    @endpush
</x-app-layout>
