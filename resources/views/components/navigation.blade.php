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
                            <!-- Dashboard - Always first -->
                            @if ($user->hasRole('super-admin'))
                                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                            @elseif($user->hasRole('company-admin') || $user->hasRole('user'))
                                <x-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                                    {{ __('Dashboard') }}
                                </x-nav-link>
                            @endif

                            @if ($user->hasRole('super-admin'))
                                <!-- GESTIÓN Dropdown for Super Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('admin.users.*', 'admin.companies.*', 'admin.clients.*', 'admin.vessel-owners.*') ? 'true' : 'false' }} }">                                        {{ __('Gestión') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 top-full mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <a href="{{ route('admin.users.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Usuarios') }}
                                            </a>
                                            <a href="{{ route('admin.companies.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.companies.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Empresas') }}
                                            </a>
                                            <a href="{{ route('admin.clients.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.clients.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Clientes') }}
                                            </a>
                                            <a href="{{ route('admin.vessel-owners.index') }}" 
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.vessel-owners.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Propietarios') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- ADMINISTRACIÓN Dropdown for Super Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('admin.reports.*', 'admin.system.*') ? 'true' : 'false' }} }">
                                        {{ __('Administración') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 top-full mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <a href="{{ route('admin.reports.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.reports.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Reportes') }}
                                            </a>
                                            <a href="{{ route('admin.system.settings') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('admin.system.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Sistema') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            @elseif($user->hasRole('company-admin'))
                                <!-- GESTIÓN Dropdown for Company Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.vessel-owners.*', 'company.clients.*', 'company.operators.*') ? 'true' : 'false' }} }">
                                        {{ __('Gestión') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 top-full mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <a href="{{ route('company.vessel-owners.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.vessel-owners.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Propietarios') }}
                                            </a>
                                            <a href="{{ route('company.clients.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.clients.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Clientes') }}
                                            </a>
                                            <a href="{{ route('company.operators.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.operators.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Operadores') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- OPERACIONES Dropdown for Company Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.shipments.*', 'company.trips.*') ? 'true' : 'false' }} }">
                                        {{ __('Operaciones') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 top-full mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <a href="{{ route('company.shipments.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.shipments.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Cargas') }}
                                            </a>
                                            <a href="{{ route('company.trips.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.trips.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Viajes') }}
                                            </a>

                                        </div>
                                    </div>
                                </div>

                                <!-- WEBSERVICES Dropdown for Company Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.webservices.*', 'company.certificates.*') ? 'true' : 'false' }} }">
                                        {{ __('Webservices') }}
                                        <svg class="ml-1 h-4 w-4" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div x-show="open" 
                                        @click.away="open = false"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 transform scale-95"
                                        x-transition:enter-end="opacity-100 transform scale-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 transform scale-100"
                                        x-transition:leave-end="opacity-0 transform scale-95"
                                        class="absolute top-16 left-0 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                        <div class="py-1">
                                            <a href="{{ route('company.webservices.index') }}" 
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.webservices.index') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                    </svg>
                                                    {{ __('Dashboard') }}
                                                </div>
                                            </a>

                                            @if(in_array('Cargas', $companyRoles))
                                                <a href="{{ route('company.webservices.send') }}" 
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.webservices.send') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    <div class="flex items-center">
                                                        <span class="inline-block w-4 h-4 mr-2 text-xs">🇦🇷</span>
                                                        {{ __('Argentina MIC/DTA') }}
                                                    </div>
                                                </a>
                                            @endif

                                            @if(in_array('Desconsolidador', $companyRoles))
                                                <a href="{{ route('company.webservices.send') }}?type=desconsolidados" 
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                        </svg>
                                                        {{ __('Desconsolidados') }}
                                                    </div>
                                                </a>
                                            @endif

                                            @if(in_array('Transbordos', $companyRoles))
                                                <a href="{{ route('company.webservices.send') }}?type=transbordos" 
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        {{ __('Transbordos') }}
                                                    </div>
                                                </a>
                                            @endif

                                            <div class="border-t border-gray-100 my-1"></div>
                                            
                                            <a href="{{ route('company.webservices.query') }}" 
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.webservices.query') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                    </svg>
                                                    {{ __('Consultas') }}
                                                </div>
                                            </a>

                                            <a href="{{ route('company.webservices.history') }}" 
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.webservices.history') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ __('Historial') }}
                                                </div>
                                            </a>

                                            <a href="{{ route('company.certificates.index') }}" 
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.certificates.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                    {{ __('Certificados') }}
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- ADMINISTRACIÓN Dropdown for Company Admin -->
                                <div class="relative h-full flex items-center" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.reports.*') ? 'true' : 'false' }} }">
                                        {{ __('Administración') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 top-full mt-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <a href="{{ route('company.reports.index') }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.reports.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                {{ __('Reportes') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            @elseif($user->hasRole('user'))
                                @if(in_array('Cargas', $companyRoles))
                                    <!-- GESTIÓN Dropdown for User with Cargas role -->
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = ! open" 
                                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                                :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.vessel-owners.*') ? 'true' : 'false' }} }">
                                            {{ __('Gestión') }}
                                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="open" 
                                             @click.away="open = false"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 transform scale-95"
                                             x-transition:enter-end="opacity-100 transform scale-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100 transform scale-100"
                                             x-transition:leave-end="opacity-0 transform scale-95"
                                             class="absolute z-50 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                            <div class="py-1">
                                                <a href="{{ route('company.vessel-owners.index') }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.vessel-owners.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    {{ __('Propietarios') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <!-- OPERACIONES Dropdown for User/Operator -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = ! open" 
                                            class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                            :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.shipments.*', 'company.trips.*', 'company.deconsolidation.*', 'company.transfers.*') ? 'true' : 'false' }} }">
                                        {{ __('Operaciones') }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute z-50 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            @if(in_array('Cargas', $companyRoles))
                                                <a href="{{ route('company.shipments.index') }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.shipments.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    {{ __('Cargas') }}
                                                </a>
                                                <a href="{{ route('company.trips.index') }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.trips.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    {{ __('Viajes') }}
                                                </a>
                                            @endif
                                            @if(in_array('Desconsolidador', $companyRoles))
                                                <a href="{{ route('company.deconsolidation.index') }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.deconsolidation.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    {{ __('Desconsolidación') }}
                                                </a>
                                            @endif
                                            @if(in_array('Transbordos', $companyRoles))
                                                <a href="{{ route('company.transfers.index') }}" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.transfers.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                    {{ __('Transbordos') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($canImport || $canExport)
                                    <!-- ADMINISTRACIÓN Dropdown for User/Operator with Import/Export -->
                                    <div class="relative" x-data="{ open: false }">
                                        <button @click="open = ! open" 
                                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
                                                :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('company.import.*', 'company.export.*') ? 'true' : 'false' }} }">
                                            {{ __('Administración') }}
                                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="open" 
                                             @click.away="open = false"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 transform scale-95"
                                             x-transition:enter-end="opacity-100 transform scale-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100 transform scale-100"
                                             x-transition:leave-end="opacity-0 transform scale-95"
                                             class="absolute z-50 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                            <div class="py-1">
                                                @if($canImport)
                                                    <a href="{{ route('company.import.index') }}" 
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.import.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                        {{ __('Importación') }}
                                                    </a>
                                                @endif
                                                @if($canExport)
                                                    <a href="{{ route('company.export.index') }}" 
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.export.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                                        {{ __('Exportación') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    @if ($user)
                        <!-- Settings Dropdown -->
                        <div class="ms-3 relative">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button
                                        class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                        <img class="h-8 w-8 rounded-full object-cover"
                                            src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&color=7F9CF5&background=EBF4FF' }}"
                                            alt="{{ $user->name }}" />
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
                        
                        <!-- Gestión Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Gestión') }}
                        </div>
                        <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" class="pl-6">
                            {{ __('Usuarios') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')" class="pl-6">
                            {{ __('Empresas') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.clients.index')" :active="request()->routeIs('admin.clients.*')" class="pl-6">
                            {{ __('Clientes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.vessel-owners.index')" :active="request()->routeIs('admin.vessel-owners.*')" class="pl-6">
                            {{ __('Propietarios') }}
                        </x-responsive-nav-link>
                        
                        <!-- Administración Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Administración') }}
                        </div>
                        <x-responsive-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')" class="pl-6">
                            {{ __('Reportes') }}
                        </x-responsive-nav-link>
                        
                        <x-responsive-nav-link :href="route('admin.system.settings')" :active="request()->routeIs('admin.system.*')" class="pl-6">
                            {{ __('Sistema') }}
                        </x-responsive-nav-link>

                    @elseif($user->hasRole('company-admin'))
                        <!-- Responsive navigation for Company Admin -->
                        <x-responsive-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        
                        <!-- Gestión Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Gestión') }}
                        </div>
                        <x-responsive-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')" class="pl-6">
                            {{ __('Propietarios') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.clients.index')" :active="request()->routeIs('company.clients.*')" class="pl-6">
                            {{ __('Clientes') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.operators.index')" :active="request()->routeIs('company.operators.*')" class="pl-6">
                            {{ __('Operadores') }}
                        </x-responsive-nav-link>
                        
                        <!-- Operaciones Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Operaciones') }}
                        </div>
                        <x-responsive-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')" class="pl-6">
                            {{ __('Cargas') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.trips.index')" :active="request()->routeIs('company.trips.*')" class="pl-6">
                            {{ __('Viajes') }}
                        </x-responsive-nav-link>
                        
                        <!-- Administración Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Administración') }}
                        </div>
                        <x-responsive-nav-link :href="route('company.reports.index')" :active="request()->routeIs('company.reports.*')" class="pl-6">
                            {{ __('Reportes') }}
                        </x-responsive-nav-link>

                        <!-- Webservices Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Webservices') }}
                        </div>
                        <x-responsive-nav-link :href="route('company.webservices.index')" :active="request()->routeIs('company.webservices.index')" class="pl-6">
                            {{ __('Dashboard Webservices') }}
                        </x-responsive-nav-link>

                        @if(in_array('Cargas', $companyRoles))
                            <x-responsive-nav-link :href="route('company.webservices.send')" :active="request()->routeIs('company.webservices.send')" class="pl-6">
                                {{ __('🇦🇷 Argentina MIC/DTA') }}
                            </x-responsive-nav-link>
                        @endif

                        @if(in_array('Desconsolidador', $companyRoles))
                            <x-responsive-nav-link :href="route('company.webservices.send', ['type' => 'desconsolidados'])" class="pl-6">
                                {{ __('Desconsolidados') }}
                            </x-responsive-nav-link>
                        @endif

                        @if(in_array('Transbordos', $companyRoles))
                            <x-responsive-nav-link :href="route('company.webservices.send', ['type' => 'transbordos'])" class="pl-6">
                                {{ __('Transbordos') }}
                            </x-responsive-nav-link>
                        @endif

                        <x-responsive-nav-link :href="route('company.webservices.query')" :active="request()->routeIs('company.webservices.query')" class="pl-6">
                            {{ __('Consultas Estado') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.webservices.history')" :active="request()->routeIs('company.webservices.history')" class="pl-6">
                            {{ __('Historial') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('company.certificates.index')" :active="request()->routeIs('company.certificates.*')" class="pl-6">
                            {{ __('Certificados') }}
                        </x-responsive-nav-link>

                    @elseif($user->hasRole('user'))
                        <!-- Responsive navigation for User/Operator -->
                        <x-responsive-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                            {{ __('Dashboard') }}
                        </x-responsive-nav-link>
                        
                        <!-- Gestión Group (solo propietarios si tiene rol Cargas) -->
                        @if(in_array('Cargas', $companyRoles))
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Gestión') }}
                            </div>
                            <x-responsive-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')" class="pl-6">
                                {{ __('Propietarios') }}
                            </x-responsive-nav-link>
                        @endif
                        
                        <!-- Operaciones Group -->
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ __('Operaciones') }}
                        </div>
                        @if(in_array('Cargas', $companyRoles))
                            <x-responsive-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')" class="pl-6">
                                {{ __('Cargas') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('company.trips.index')" :active="request()->routeIs('company.trips.*')" class="pl-6">
                                {{ __('Viajes') }}
                            </x-responsive-nav-link>
                        @endif
                        @if(in_array('Desconsolidador', $companyRoles))
                            <x-responsive-nav-link :href="route('company.deconsolidation.index')" :active="request()->routeIs('company.deconsolidation.*')" class="pl-6">
                                {{ __('Desconsolidación') }}
                            </x-responsive-nav-link>
                        @endif
                        @if(in_array('Transbordos', $companyRoles))
                            <x-responsive-nav-link :href="route('company.transfers.index')" :active="request()->routeIs('company.transfers.*')" class="pl-6">
                                {{ __('Transbordos') }}
                            </x-responsive-nav-link>
                        @endif
                        
                        <!-- Administración Group (solo si puede importar/exportar) -->
                        @if($canImport || $canExport)
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Administración') }}
                            </div>
                            @if($canImport)
                                <x-responsive-nav-link :href="route('company.import.index')" :active="request()->routeIs('company.import.*')" class="pl-6">
                                    {{ __('Importación') }}
                                </x-responsive-nav-link>
                            @endif
                            @if($canExport)
                                <x-responsive-nav-link :href="route('company.export.index')" :active="request()->routeIs('company.export.*')" class="pl-6">
                                    {{ __('Exportación') }}
                                </x-responsive-nav-link>
                            @endif
                        @endif
                    @endif
                @endif
            </div>

            <!-- Responsive Settings Options -->
            @if ($user)
                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="flex items-center px-4">
                        <div class="shrink-0 me-3">
                            <img class="h-10 w-10 rounded-full object-cover"
                                src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&color=7F9CF5&background=EBF4FF' }}"
                                alt="{{ $user->name }}" />
                        </div>

                        <div>
                            <div class="font-medium text-base text-gray-800">{{ $user->name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ $user->email }}</div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <!-- Account Management -->
                        <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                            {{ __('Perfil') }}
                        </x-responsive-nav-link>

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