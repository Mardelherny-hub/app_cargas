<div>
    @php
        $user = Auth::user();
        $company = null;
        $companyRoles = [];
        $canImport = false;
        $canExport = false;
        $canTransfer = false;

        if ($user) {
            // Obtener información de la empresa
            if ($user->userable_type === 'App\\Models\\Company') {
                $company = $user->userable;
                $companyRoles = $company->company_roles ?? [];
            } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $company = $user->userable->company;
                $companyRoles = $company->company_roles ?? [];
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
                                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                    {{ __('Usuarios') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')">
                                    {{ __('Empresas') }}
                                </x-nav-link>
                                <x-nav-link :href="route('admin.clients.index')" :active="request()->routeIs('admin.clients.*')">
                                    {{ __('Clientes') }}
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
                                <!-- NUEVO: Propietarios de Embarcaciones -->
                                <x-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')">
                                    {{ __('Propietarios') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.clients.index')" :active="request()->routeIs('company.clients.*')">
                                    {{ __('Clientes') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.operators.index')" :active="request()->routeIs('company.operators.*')">
                                    {{ __('Operadores') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')">
                                    {{ __('Reportes') }}
                                </x-nav-link>
                                <x-nav-link :href="route('company.settings.index')" :active="request()->routeIs('company.settings.*')">
                                    {{ __('Configuración') }}
                                </x-nav-link>
                            @elseif($user->hasRole('user'))
                                <!-- Navigation for User (based on company business roles) -->
                                <x-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>

                                @if(in_array('Cargas', $companyRoles))
                                    <x-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')">
                                        {{ __('Cargas') }}
                                    </x-nav-link>

                                    <!-- NUEVO: Propietarios solo para usuarios con rol Cargas (solo lectura) -->
                                    <x-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')">
                                        {{ __('Propietarios') }}
                                    </x-nav-link>
                                @endif

                                @if(in_array('Desconsolidador', $companyRoles))
                                    <x-nav-link :href="route('company.deconsolidation.index')" :active="request()->routeIs('company.deconsolidation.*')">
                                        {{ __('Desconsolidación') }}
                                    </x-nav-link>
                                @endif

                                @if(in_array('Transbordos', $companyRoles))
                                    <x-nav-link :href="route('company.transfers.index')" :active="request()->routeIs('company.transfers.*')">
                                        {{ __('Transbordos') }}
                                    </x-nav-link>
                                @endif

                                @if($canImport)
                                    <x-nav-link :href="route('company.import.index')" :active="request()->routeIs('company.import.*')">
                                        {{ __('Importar') }}
                                    </x-nav-link>
                                @endif

                                @if($canExport)
                                    <x-nav-link :href="route('company.export.index')" :active="request()->routeIs('company.export.*')">
                                        {{ __('Exportar') }}
                                    </x-nav-link>
                                @endif

                                <x-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')">
                                    {{ __('Reportes') }}
                                </x-nav-link>
                            @endif
                        @else
                            <!-- Public navigation for guests -->
                            <x-nav-link :href="route('welcome')" :active="request()->routeIs('welcome')">
                                {{ __('Inicio') }}
                            </x-nav-link>
                        @endif
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    @if ($user)
                        <!-- Company info display -->
                        @if ($company)
                            <div class="flex items-center space-x-3 me-3">
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium">{{ $company->commercial_name ?? $company->business_name }}</span>
                                    @if($companyRoles)
                                        <div class="text-xs text-gray-500">
                                            {{ implode(', ', $companyRoles) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Settings Dropdown -->
                        <div class="ms-3 relative">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                        <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                        </button>
                                    @else
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ Auth::user()->name }}

                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    @endif
                                </x-slot>

                                <x-slot name="content">
                                    <!-- Account Management -->
                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        {{ __('Gestionar Cuenta') }}
                                    </div>

                                    <x-dropdown-link href="{{ route('profile.show') }}">
                                        {{ __('Perfil') }}
                                    </x-dropdown-link>

                                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                        <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                            {{ __('API Tokens') }}
                                        </x-dropdown-link>
                                    @endif

                                    <div class="border-t border-gray-200"></div>

                                    <!-- Authentication -->
                                    <form method="POST" action="{{ route('logout') }}" x-data>
                                        @csrf

                                        <x-dropdown-link href="{{ route('logout') }}"
                                                 @click.prevent="$root.submit();">
                                            {{ __('Salir') }}
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @else
                        <!-- Guest links -->
                        <div class="space-x-4">
                            <x-nav-link :href="route('login')" :active="request()->routeIs('login')">
                                {{ __('Iniciar Sesión') }}
                            </x-nav-link>
                            @if (Route::has('register'))
                                <x-nav-link :href="route('register')" :active="request()->routeIs('register')">
                                    {{ __('Registrarse') }}
                                </x-nav-link>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Hamburger -->
                <div class="-me-2 flex items-center sm:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                @if ($user)
                    @if ($user->hasRole('super-admin'))
                        <!-- Responsive navigation for Super Admin -->
                        <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Usuarios') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')">
                            {{ __('Empresas') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.clients.index')" :active="request()->routeIs('admin.clients.*')">
                            {{ __('Clientes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">
                            {{ __('Reportes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.system.settings')" :active="request()->routeIs('admin.system.*')">
                            {{ __('Sistema') }}
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
                        <!-- NUEVO: Propietarios de Embarcaciones en responsive -->
                        <x-responsive-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')">
                            {{ __('Propietarios') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.clients.index')" :active="request()->routeIs('company.clients.*')">
                            {{ __('Clientes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.operators.index')" :active="request()->routeIs('company.operators.*')">
                            {{ __('Operadores') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')">
                            {{ __('Reportes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.settings.index')" :active="request()->routeIs('company.settings.*')">
                            {{ __('Configuración') }}
                        </x-responsive-nav-link>
                    @elseif($user->hasRole('user'))
                        <x-responsive-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>

                        @if(in_array('Cargas', $companyRoles))
                            <x-responsive-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')">
                                {{ __('Cargas') }}
                            </x-responsive-nav-link>

                            <!-- NUEVO: Propietarios para usuarios con rol Cargas en responsive -->
                            <x-responsive-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')">
                                {{ __('Propietarios') }}
                            </x-responsive-nav-link>
                        @endif

                        @if(in_array('Desconsolidador', $companyRoles))
                            <x-responsive-nav-link :href="route('company.deconsolidation.index')" :active="request()->routeIs('company.deconsolidation.*')">
                                {{ __('Desconsolidación') }}
                            </x-responsive-nav-link>
                        @endif

                        @if(in_array('Transbordos', $companyRoles))
                            <x-responsive-nav-link :href="route('company.transfers.index')" :active="request()->routeIs('company.transfers.*')">
                                {{ __('Transbordos') }}
                            </x-responsive-nav-link>
                        @endif

                        @if($canImport)
                            <x-responsive-nav-link :href="route('company.import.index')" :active="request()->routeIs('company.import.*')">
                                {{ __('Importar') }}
                            </x-responsive-nav-link>
                        @endif

                        @if($canExport)
                            <x-responsive-nav-link :href="route('company.export.index')" :active="request()->routeIs('company.export.*')">
                                {{ __('Exportar') }}
                            </x-responsive-nav-link>
                        @endif

                        <x-responsive-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')">
                            {{ __('Reportes') }}
                        </x-responsive-nav-link>
                    @endif
                @else
                    <!-- Guest responsive links -->
                    <x-responsive-nav-link :href="route('welcome')" :active="request()->routeIs('welcome')">
                        {{ __('Inicio') }}
                    </x-responsive-nav-link>
                @endif
            </div>

            @if ($user)
                <!-- Responsive Settings Options -->
                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="flex items-center px-4">
                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                            <div class="shrink-0 me-3">
                                <img class="h-10 w-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                            </div>
                        @endif

                        <div>
                            <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                            @if ($company)
                                <div class="text-xs text-gray-400">{{ $company->commercial_name ?? $company->business_name }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                            {{ __('Perfil') }}
                        </x-responsive-nav-link>

                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                                {{ __('API Tokens') }}
                            </x-responsive-nav-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf

                            <x-responsive-nav-link href="{{ route('logout') }}"
                                           @click.prevent="$root.submit();">
                                {{ __('Salir') }}
                            </x-responsive-nav-link>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </nav>
</div>