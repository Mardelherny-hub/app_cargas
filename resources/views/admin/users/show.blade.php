<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalles del Usuario') }}
                </h2>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.users.edit', $user) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                </a>
                @if($user->active)
                    <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                onclick="return confirm('¿Estás seguro de que quieres desactivar este usuario?')"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Desactivar
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" class="inline">
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

            <!-- Información Principal del Usuario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-6">
                            <!-- Avatar -->
                            <div class="h-20 w-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                <span class="text-2xl font-bold text-white">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </span>
                            </div>
                            <!-- Información básica -->
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
                                <p class="text-lg text-gray-600">{{ $user->email }}</p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <!-- Estado -->
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                    <!-- Roles -->
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Información adicional -->
                        <div class="text-right text-sm text-gray-500">
                            <div>ID: #{{ $user->id }}</div>
                            <div>Registrado: {{ $user->created_at->format('d/m/Y H:i') }}</div>
                            @if($user->last_access)
                                <div>Último acceso: {{ $user->last_access->format('d/m/Y H:i') }}</div>
                            @else
                                <div>Último acceso: Nunca</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Información del Sistema -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Sistema</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Zona Horaria</dt>
                                <dd class="text-sm text-gray-900">{{ $user->timezone ?? 'UTC' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email Verificado</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($user->email_verified_at)
                                        <span class="inline-flex items-center text-green-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Verificado el {{ $user->email_verified_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-red-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            No verificado
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Relación</dt>
                                <dd class="text-sm text-gray-900">
                                    @if($user->userable_type)
                                        {{ class_basename($user->userable_type) }}
                                        @if($user->userable)
                                            <span class="text-gray-500">(ID: {{ $user->userable_id }})</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500">Sin relación polimórfica</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Información de la Entidad Relacionada -->
                @if($user->userable)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            @if($user->userable_type === 'App\Models\Company')
                                Información de la Empresa
                            @elseif($user->userable_type === 'App\Models\Operator')
                                Información del Operador
                            @else
                                Información Adicional
                            @endif
                        </h3>

                        @if($user->userable_type === 'App\Models\Company')
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->legal_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->commercial_name ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">CUIT</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->tax_id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">País</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $user->userable->country === 'AR' ? 'Argentina' : 'Paraguay' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Certificado Digital</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($user->userable->certificate_path)
                                            <span class="inline-flex items-center text-green-600">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Configurado
                                            </span>
                                            @if($user->userable->certificate_expires_at)
                                                <span class="ml-2 text-xs text-gray-500">
                                                    (Vence: {{ $user->userable->certificate_expires_at->format('d/m/Y') }})
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-red-600">No configurado</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="pt-2">
                                    <a href="{{ route('admin.companies.show', $user->userable->id) }}"
                                       class="text-sm text-blue-600 hover:text-blue-500">
                                        Ver detalles completos de la empresa →
                                    </a>
                                </div>
                            </dl>
                        @elseif($user->userable_type === 'App\Models\Operator')
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nombre Completo</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->first_name }} {{ $user->userable->last_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Documento</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->document_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Cargo</dt>
                                    <dd class="text-sm text-gray-900">{{ $user->userable->position ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                                    <dd class="text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->userable->type === 'internal' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800' }}">
                                            {{ $user->userable->type === 'internal' ? 'Interno' : 'Externo' }}
                                        </span>
                                    </dd>
                                </div>
                                @if($user->userable->company)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Empresa</dt>
                                        <dd class="text-sm text-gray-900">
                                            <a href="{{ route('admin.companies.show', $user->userable->company->id) }}"
                                               class="text-blue-600 hover:text-blue-500">
                                                {{ $user->userable->company->legal_name }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Permisos Especiales</dt>
                                    <dd class="text-sm text-gray-900">
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @if($user->userable->can_import)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    Importar
                                                </span>
                                            @endif
                                            @if($user->userable->can_export)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    Exportar
                                                </span>
                                            @endif
                                            @if($user->userable->can_transfer)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                    Transferir
                                                </span>
                                            @endif
                                            @if($user->userable->special_permissions && count($user->userable->special_permissions) > 0)
                                                @foreach($user->userable->special_permissions as $permission)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        {{ $permission }}
                                                    </span>
                                                @endforeach
                                            @endif
                                        </div>
                                        @if(!$user->userable->can_import && !$user->userable->can_export && !$user->userable->can_transfer && (!$user->userable->special_permissions || count($user->userable->special_permissions) === 0))
                                            <span class="text-gray-500">Sin permisos especiales</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Permisos y Roles -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Roles y Permisos</h3>
                        <a href="{{ route('admin.users.permissions', $user) }}"
                           class="text-sm text-blue-600 hover:text-blue-500">
                            Gestionar permisos →
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Roles asignados -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Roles Asignados</h4>
                            @if($user->roles->count() > 0)
                                <div class="space-y-2">
                                    @foreach($user->roles as $role)
                                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                            <div>
                                                <div class="text-sm font-medium text-blue-900">
                                                    {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                                                </div>
                                                <div class="text-xs text-blue-600">
                                                    {{ $role->permissions->count() }} permisos incluidos
                                                </div>
                                            </div>
                                            <span class="text-xs text-blue-600">
                                                Asignado {{ $user->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No hay roles asignados</p>
                            @endif
                        </div>

                        <!-- Permisos directos -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Permisos Directos</h4>
                            @if($user->permissions->count() > 0)
                                <div class="space-y-1">
                                    @foreach($user->permissions->take(10) as $permission)
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 mr-1 mb-1">
                                            {{ $permission->name }}
                                        </span>
                                    @endforeach
                                    @if($user->permissions->count() > 10)
                                        <div class="text-xs text-gray-500 mt-2">
                                            Y {{ $user->permissions->count() - 10 }} permisos más...
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No hay permisos directos asignados</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad y Estadísticas -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Actividad y Estadísticas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $user->created_at->diffInDays() }}</div>
                            <div class="text-sm text-gray-600">Días en el sistema</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                @if($user->last_access)
                                    {{ $user->last_access->diffInDays() }}
                                @else
                                    --
                                @endif
                            </div>
                            <div class="text-sm text-gray-600">Días desde último acceso</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ $user->roles->count() }}</div>
                            <div class="text-sm text-gray-600">Roles asignados</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ $user->getAllPermissions()->count() }}
                            </div>
                            <div class="text-sm text-gray-600">Permisos totales</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Adicionales -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Acciones Adicionales</h3>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    onclick="return confirm('¿Estás seguro de que quieres resetear la contraseña de este usuario?')"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m0 0a2 2 0 01-2 2m2-2h6m-6 0H9m0 0a2 2 0 01-2-2m2 2a2 2 0 002 2m-2-2H3"/>
                                </svg>
                                Resetear Contraseña
                            </button>
                        </form>

                        @if(!$user->email_verified_at)
                            <button onclick="alert('Función de reenvío de verificación no implementada aún')"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Reenviar Verificación
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
