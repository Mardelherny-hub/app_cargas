<div>
    @php
        $user = Auth::user();
        $company = null;
        $canImport = false;
        $canExport = false;
        $canTransfer = false;

        if ($user) {
            // Obtener información de la empresa
            if ($user->userable_type === 'App\\Models\\Company') {
                $company = $user->userable;
            } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $company = $user->userable->company;
                $canImport = $user->userable->can_import ?? false;
                $canExport = $user->userable->can_export ?? false;
                $canTransfer = $user->userable->can_transfer ?? false;
            }
        }
    @endphp

    <nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('welcome') }}">
                            <div class="flex items-center space-x-2">
                                <div
                                    class="w-8 h-8 bg-gradient-to-br from-blue-600 to-teal-600 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M3 17h18l-2-4H5l-2 4zM12 2L8 6h8l-4-4zm-9 7h18v2H3v-2z" />
                                    </svg>
                                </div>
                                <span class="font-semibold text-gray-800">Cargas</span>
                            </div>
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                        @if ($user)
                            @if ($user->hasRole('super-admin'))
                                <!-- Navigation for Super Admin -->
                                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                    {{ __('Usuarios') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')">
                                    {{ __('Empresas') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">
                                    {{ __('Reportes') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.system.settings')" :active="request()->routeIs('admin.system.*')">
                                    {{ __('Sistema') }}
                                </x-nav-link>
                            @elseif($user->hasRole('company-admin'))
                                <!-- Navigation for Company Admin -->
                                <x-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')">
                                    {{ __('Cargas') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.trips.index')" :active="request()->routeIs('company.trips.*')">
                                    {{ __('Viajes') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.operators.index')" :active="request()->routeIs('company.operators.*')">
                                    {{ __('Operadores') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')">
                                    {{ __('Reportes') }}
                                </x-nav-link>
                            @elseif($user->hasRole('internal-operator'))
                                <!-- Navigation for Internal Operator -->
                                <x-nav-link :href="route('internal.dashboard')" :active="request()->routeIs('internal.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                                <x-nav-link :href="route('internal.monitoring.index')" :active="request()->routeIs('internal.monitoring.*')">
                                    {{ __('Monitoreo') }}
                                </x-nav-link>
                                <x-nav-link :href="route('internal.companies.index')" :active="request()->routeIs('internal.companies.*')">
                                    {{ __('Empresas') }}
                                </x-nav-link>
                                <x-nav-link :href="route('internal.transfers.index')" :active="request()->routeIs('internal.transfers.*')">
                                    {{ __('Transferencias') }}
                                </x-nav-link>
                                <x-nav-link :href="route('internal.webservices.index')" :active="request()->routeIs('internal.webservices.*')">
                                    {{ __('WebServices') }}
                                </x-nav-link>
                                <x-nav-link :href="route('internal.support.index')" :active="request()->routeIs('internal.support.*')">
                                    {{ __('Soporte') }}
                                </x-nav-link>
                            @elseif($user->hasRole('external-operator'))
                                <!-- Navigation for External Operator -->
                                <x-nav-link :href="route('operator.dashboard')" :active="request()->routeIs('operator.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                                <x-nav-link :href="route('operator.shipments.index')" :active="request()->routeIs('operator.shipments.*')">
                                    {{ __('Mis Cargas') }}
                                </x-nav-link>
                                <x-nav-link :href="route('operator.trips.index')" :active="request()->routeIs('operator.trips.*')">
                                    {{ __('Mis Viajes') }}
                                </x-nav-link>
                                @if ($canImport)
                                    <x-nav-link :href="route('operator.import.index')" :active="request()->routeIs('operator.import.*')">
                                        {{ __('Importar') }}
                                    </x-nav-link>
                                @endif
                                <x-nav-link :href="route('operator.reports.index')" :active="request()->routeIs('operator.reports.*')">
                                    {{ __('Reportes') }}
                                </x-nav-link>
                                <x-nav-link :href="route('operator.help.index')" :active="request()->routeIs('operator.help.*')">
                                    {{ __('Ayuda') }}
                                </x-nav-link>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Settings Dropdown -->
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    @if ($user)
                        <!-- User Info -->
                        <div class="hidden lg:flex items-center space-x-4 mr-4">
                            @if ($company)
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">{{ $company->business_name }}</p>
                                    <p class="text-xs text-gray-400">
                                        @if ($user->hasRole('super-admin'))
                                            Super Administrador
                                        @elseif($user->hasRole('company-admin'))
                                            Admin. Empresa
                                        @elseif($user->hasRole('internal-operator'))
                                            Op. Interno
                                        @elseif($user->hasRole('external-operator'))
                                            Op. Externo
                                        @else
                                            Usuario
                                        @endif
                                    </p>
                                </div>
                            @else
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">
                                        @if ($user->hasRole('super-admin'))
                                            Super Administrador
                                        @elseif($user->hasRole('company-admin'))
                                            Admin. Empresa
                                        @elseif($user->hasRole('internal-operator'))
                                            Op. Interno
                                        @elseif($user->hasRole('external-operator'))
                                            Op. Externo
                                        @else
                                            Usuario
                                        @endif
                                    </p>
                                </div>
                            @endif

                            <!-- Status indicators -->
                            <div class="flex flex-col space-y-1">
                                @if (($user->hasRole('company-admin') || $user->hasRole('external-operator')) && $company)
                                    <div class="flex items-center space-x-1">
                                        <div
                                            class="w-2 h-2 bg-{{ $company->ws_active ? 'green' : 'red' }}-500 rounded-full">
                                        </div>
                                        <span class="text-xs text-gray-500">WS</span>
                                    </div>
                                    @if ($company->certificate_expires_at)
                                        @php
                                            $daysToExpiry = now()->diffInDays($company->certificate_expires_at, false);
                                            $certStatus =
                                                $daysToExpiry < 0 ? 'red' : ($daysToExpiry <= 30 ? 'yellow' : 'green');
                                        @endphp
                                        <div class="flex items-center space-x-1">
                                            <div class="w-2 h-2 bg-{{ $certStatus }}-500 rounded-full"></div>
                                            <span class="text-xs text-gray-500">Cert</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button
                                    class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                    <div class="flex items-center space-x-2">
                                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                            <img class="h-8 w-8 rounded-full object-cover"
                                                src="{{ Auth::user()->profile_photo_url }}"
                                                alt="{{ Auth::user()->name }}" />
                                        @else
                                            <div
                                                class="h-8 w-8 bg-gray-300 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-600">
                                                    {{ substr($user->name, 0, 2) }}
                                                </span>
                                            </div>
                                        @endif
                                        <span>{{ $user->name }}</span>
                                    </div>
                                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <!-- Account Management -->
                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    {{ __('Gestionar Cuenta') }}
                                </div>

                                <x-dropdown-link href="{{ route('profile.show') }}">
                                    {{ __('Perfil') }}
                                </x-dropdown-link>

                                @if ($user->hasRole('external-operator'))
                                    <x-dropdown-link href="{{ route('operator.settings.index') }}">
                                        {{ __('Configuración') }}
                                    </x-dropdown-link>
                                @elseif($user->hasRole('company-admin'))
                                    <x-dropdown-link href="{{ route('company.settings.index') }}">
                                        {{ __('Configuración') }}
                                    </x-dropdown-link>
                                @endif

                                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                    <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                        {{ __('API Tokens') }}
                                    </x-dropdown-link>
                                @endif

                                <div class="border-t border-gray-200"></div>

                                <!-- Quick Actions -->
                                @if ($user->hasRole('company-admin') || $user->hasRole('external-operator'))
                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        {{ __('Acciones Rápidas') }}
                                    </div>

                                    @if ($user->hasRole('company-admin'))
                                        <x-dropdown-link href="{{ route('company.shipments.create') }}">
                                            📦 {{ __('Nueva Carga') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link href="{{ route('company.trips.create') }}">
                                            🚢 {{ __('Nuevo Viaje') }}
                                        </x-dropdown-link>
                                    @elseif($user->hasRole('external-operator'))
                                        <x-dropdown-link href="{{ route('operator.shipments.create') }}">
                                            📦 {{ __('Nueva Carga') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link href="{{ route('operator.trips.create') }}">
                                            🚢 {{ __('Nuevo Viaje') }}
                                        </x-dropdown-link>
                                    @endif

                                    <div class="border-t border-gray-200"></div>
                                @endif

                                <!-- Authentication -->
                                <form method="POST" action="{{ route('logout') }}" x-data>
                                    @csrf
                                    <x-dropdown-link href="{{ route('logout') }}" @click.prevent="$root.submit();">
                                        {{ __('Cerrar Sesión') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    @else
                        <!-- Guest Navigation -->
                        <div class="space-x-4">
                            <a href="{{ route('login') }}" class="text-sm text-gray-700 hover:text-gray-900">
                                {{ __('Iniciar Sesión') }}
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Hamburger -->
                <div class="-me-2 flex items-center sm:hidden">
                    <button @click="open = ! open"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                @if ($user)
                    @if ($user->hasRole('super-admin'))
                        <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Usuarios') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')">
                            {{ __('Empresas') }}
                        </x-responsive-nav-link>
                    @elseif($user->hasRole('company-admin'))
                        <x-responsive-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')">
                            {{ __('Cargas') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.trips.index')" :active="request()->routeIs('company.trips.*')">
                            {{ __('Viajes') }}
                        </x-responsive-nav-link>
                    @elseif($user->hasRole('internal-operator'))
                        <x-responsive-nav-link :href="route('internal.dashboard')" :active="request()->routeIs('internal.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('internal.monitoring.index')" :active="request()->routeIs('internal.monitoring.*')">
                            {{ __('Monitoreo') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('internal.companies.index')" :active="request()->routeIs('internal.companies.*')">
                            {{ __('Empresas') }}
                        </x-responsive-nav-link>
                    @elseif($user->hasRole('external-operator'))
                        <x-responsive-nav-link :href="route('operator.dashboard')" :active="request()->routeIs('operator.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('operator.shipments.index')" :active="request()->routeIs('operator.shipments.*')">
                            {{ __('Mis Cargas') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('operator.trips.index')" :active="request()->routeIs('operator.trips.*')">
                            {{ __('Mis Viajes') }}
                        </x-responsive-nav-link>
                    @endif
                @endif
            </div>

            <!-- Responsive Settings Options -->
            @if ($user)
                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="flex items-center px-4">
                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                            <div class="shrink-0 me-3">
                                <img class="h-10 w-10 rounded-full object-cover"
                                    src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                            </div>
                        @endif

                        <div>
                            <div class="font-medium text-base text-gray-800">{{ $user->name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ $user->email }}</div>
                            @if ($company)
                                <div class="font-medium text-xs text-gray-400">{{ $company->business_name }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                            {{ __('Perfil') }}
                        </x-responsive-nav-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf
                            <x-responsive-nav-link href="{{ route('logout') }}" @click.prevent="$root.submit();">
                                {{ __('Cerrar Sesión') }}
                            </x-responsive-nav-link>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </nav>
</div>
