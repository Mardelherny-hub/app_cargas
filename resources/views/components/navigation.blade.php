<?php
?>

@php
    $user = Auth::user();
    $company = null;
    $companyRoles = [];
    
    // Obtener empresa y roles solo para users con empresa
    if ($user && ($user->hasRole('company-admin') || $user->hasRole('user'))) {
        $company = $user->company ?? $user->companies->first();
        $companyRoles = $company->company_roles ?? [];
    }
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('admin.dashboard') }}">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    
                    @if($user && $user->hasRole('super-admin'))
                        <!-- SUPER ADMIN Navigation -->
                        <x-nav-link href="{{ route('admin.dashboard') }}" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>

                        <!-- ADMINISTRACIÓN Dropdown for Super Admin -->
                        <div class="relative h-full flex items-center" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out h-full"
                                :class="{ 'border-indigo-400 text-gray-900': open || {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }">
                                <span>Administración</span>
                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                class="absolute top-full left-0 mt-1 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
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

                    @elseif($user && ($user->hasRole('company-admin') || $user->hasRole('user')))
                        <!-- COMPANY ADMIN Y OPERADORES (USER) Navigation -->
                        <x-nav-link href="{{ route('company.dashboard') }}" :active="request()->routeIs('company.dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>

                        <!-- GESTIÓN Dropdown - ACCESO COMPLETO para Admin y Operadores -->
                        <div class="relative h-full flex items-center" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out h-full"
                                {{ request()->routeIs('company.vessel-owners.*', 'company.vessels.*', 'company.clients.*', 'company.operators.*') ? 'true' : 'false' }}
                                <span>Gestión</span>
                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                class="absolute top-full left-0 mt-1 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                <div class="py-1">
                                    <a href="{{ route('company.vessel-owners.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.vessel-owners.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        👥 {{ __('Propietarios') }}
                                    </a>
                                    <a href="{{ route('company.captains.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.captains.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        ⚓ {{ __('Capitanes') }}
                                    </a>
                                    <a href="{{ route('company.vessels.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.vessels.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        🚢 {{ __('Embarcaciones') }}
                                    </a>
                                    <a href="{{ route('company.clients.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.clients.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        👤 {{ __('Clientes') }}
                                    </a>
                                    <!-- OPERADORES: Solo company-admin puede gestionar operadores -->
                                    @if($user->hasRole('company-admin'))
                                    <a href="{{ route('company.operators.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request()->routeIs('company.operators.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        ⚙️ {{ __('Operadores') }}
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 📋 MANIFIESTOS Dropdown - ACCESO COMPLETO para Admin y Operadores -->
                        @if($company)
                        <div class="relative h-full flex items-center" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out h-full {{ request()->routeIs('company.manifests.*', 'company.voyages.*', 'company.shipments.*', 'company.bills-of-lading.*') ? 'border-indigo-400 text-gray-900' : '' }}"
                                :class="{ 'border-indigo-400 text-gray-900': open }">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Manifiestos</span>
                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                class="absolute top-full left-0 mt-1 w-96 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                <div class="py-2">
                                    <!-- Header -->
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">📋 SISTEMA DE MANIFIESTOS</p>
                                        <p class="text-xs text-gray-400 mt-1">Crear → Exportar → Enviar Aduana</p>
                                    </div>

                                    <!-- 🏠 DASHBOARD PRINCIPAL -->
                                    <a href="{{ route('company.manifests.index') }}" 
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 mx-2 rounded-md transition-colors duration-200 {{ request()->routeIs('company.manifests.index') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <div>
                                            <div class="font-medium">🏠 Dashboard Manifiestos</div>
                                            <div class="text-xs text-gray-500">Vista general de todos los viajes</div>
                                        </div>
                                    </a>

                                    <!-- 1. 📝 CREAR/GESTIONAR -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">📝 CREAR Y GESTIONAR</p>
                                        </div>
                                        
                                        <!-- Viajes -->
                                        <a href="{{ route('company.voyages.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.voyages.*') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">🚢 Viajes</div>
                                                <div class="text-xs text-gray-500">Rutas, embarcaciones, capitanes</div>
                                            </div>
                                        </a>

                                        <!-- Cargas -->
                                        <a href="{{ route('company.shipments.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.shipments.*') ? 'bg-green-50 text-green-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">📦 Cargas</div>
                                                <div class="text-xs text-gray-500">Gestión de cargas y contenedores</div>
                                            </div>
                                        </a>

                                        <!-- Conocimientos -->
                                        <a href="{{ route('company.bills-of-lading.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.bills-of-lading.*') ? 'bg-purple-50 text-purple-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">📄 Conocimientos</div>
                                                <div class="text-xs text-gray-500">B/L, títulos, carga manual</div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- 2. 📊 IMPORTAR/EXPORTAR -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">📊 IMPORTAR / EXPORTAR</p>
                                        </div>
                                        
                                        <!-- Importar Datos -->
                                        <a href="{{ route('company.manifests.import.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.manifests.import.*') ? 'bg-green-50 text-green-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">📊 Importar Excel/CSV</div>
                                                <div class="text-xs text-gray-500">Cargar datos masivos al sistema</div>
                                            </div>
                                        </a>

                                        <!-- Exportar Manifiestos -->
                                        <a href="{{ route('company.manifests.export.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.manifests.export.*') ? 'bg-yellow-50 text-yellow-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">📤 Exportar Manifiestos</div>
                                                <div class="text-xs text-gray-500">PARANA.xlsx, Guaran.csv, Login.xml</div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- 3. 🏛️ ENVÍO A ADUANA -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">🏛️ ENVÍO A ADUANA</p>
                                        </div>
                                        
                                        <!-- Envío Directo -->
                                        <a href="{{ route('company.manifests.customs.index') }}"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 rounded-md mx-2 transition-colors duration-200 {{ request()->routeIs('company.manifests.customs.*') ? 'bg-red-50 text-red-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">🏛️ Enviar a AFIP/DNA</div>
                                                <div class="text-xs text-gray-500">Desde manifiestos creados</div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Quick Actions -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones Rápidas</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-1 px-2">
                                            <a href="{{ route('company.manifests.create') }}" 
                                               class="flex items-center justify-center px-3 py-2 text-xs bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 transition-colors duration-200">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Nuevo Manifiesto
                                            </a>
                                            <a href="{{ route('company.bills-of-lading.create') }}" 
                                               class="flex items-center justify-center px-3 py-2 text-xs bg-purple-50 text-purple-700 rounded-md hover:bg-purple-100 transition-colors duration-200">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Nuevo B/L
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- 🔧 WEBSERVICES Dropdown - ACCESO SEGÚN ROLES para Admin y Operadores -->
                        @if($company)
                        <div class="relative h-full flex items-center" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out h-full {{ request()->routeIs('company.webservices.*', 'company.certificates.*') ? 'border-indigo-400 text-gray-900' : '' }}"
                                :class="{ 'border-indigo-400 text-gray-900': open }">
                                <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                                </svg>
                                <span>Webservices</span>
                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition
                                class="absolute top-full left-0 mt-1 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                <div class="py-2">
                                    <!-- Header -->
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="text-xs font-medium text-green-600 uppercase tracking-wider">⚙️ CONFIGURACIÓN Y MONITOREO</p>
                                        <div class="text-xs text-gray-400 mt-1">Certificados, envíos y monitoreo</div>
                                    </div>

                                    <!-- Dashboard Webservices -->
                                    <a href="{{ route('company.webservices.index') }}" 
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 mx-2 rounded-md transition-colors duration-200 {{ request()->routeIs('company.webservices.index') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <div>
                                            <div class="font-medium">Dashboard Webservices</div>
                                            <div class="text-xs text-gray-500">Estado y configuración</div>
                                        </div>
                                    </a>

                                    <!-- IMPORTAR Y ENVIAR - Nuevo para operadores -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">📊 IMPORTAR Y ENVIAR</p>
                                        </div>
                                        <a href="{{ route('company.webservices.import') }}" 
                                           class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 mx-2 rounded-md transition-colors duration-200 {{ request()->routeIs('company.webservices.import') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">📊 Importar y Enviar</div>
                                                <div class="text-xs text-gray-500">Cargar manifiestos y enviar a aduana</div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Consultas y Historial -->
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <div class="px-4 py-1">
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">📊 MONITOREO</p>
                                        </div>
                                        <a href="{{ route('company.webservices.history') }}" 
                                           class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 mx-2 rounded-md transition-colors duration-200 {{ request()->routeIs('company.webservices.history') ? 'bg-gray-50 text-gray-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">Historial</div>
                                                <div class="text-xs text-gray-500">Transacciones enviadas</div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Certificados - Solo Company Admin puede gestionar -->
                                    @if($user->hasRole('company-admin'))
                                    <div class="border-t border-gray-100 mt-2 pt-2">
                                        <a href="{{ route('company.certificates.index') }}" 
                                           class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 mx-2 rounded-md transition-colors duration-200 {{ request()->routeIs('company.certificates.*') ? 'bg-red-50 text-red-700 font-medium' : '' }}">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            <div>
                                                <div class="font-medium">🔐 Certificados .p12</div>
                                                <div class="text-xs text-gray-500">AFIP y DNA</div>
                                            </div>
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
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
                                {{ __('Manage Account') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <div class="border-t border-gray-200"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf

                                <x-dropdown-link href="{{ route('logout') }}"
                                         @click.prevent="$root.submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
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
            @if($user && $user->hasRole('super-admin'))
                <!-- Responsive Navigation Links for Super Admin -->
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                
                <!-- Administración Group -->
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    {{ __('Administración') }}
                </div>
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" class="pl-6">
                    {{ __('Usuarios') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.companies.index')" :active="request()->routeIs('admin.companies.*')" class="pl-6">
                    {{ __('Empresas') }}
                </x-responsive-nav-link>

            @elseif($user && ($user->hasRole('company-admin') || $user->hasRole('user')))
                <!-- Responsive Navigation for Company Admin and Operadores -->
                <x-responsive-nav-link :href="route('company.dashboard')" :active="request()->routeIs('company.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                
                <!-- Gestión Group - COMPLETO para Admin y Operadores -->
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    {{ __('Gestión') }}
                </div>
                <x-responsive-nav-link :href="route('company.vessel-owners.index')" :active="request()->routeIs('company.vessel-owners.*')" class="pl-6">
                    {{ __('Propietarios') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.captains.index')" :active="request()->routeIs('company.captains.*')" class="pl-6">
                    👨‍✈️ {{ __('Capitanes') }}
                </x-responsive-nav-link>}
                <x-responsive-nav-link :href="route('company.vessels.index')" :active="request()->routeIs('company.vessels.*')" class="pl-6">
                    🚢 {{ __('Embarcaciones') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.clients.index')" :active="request()->routeIs('company.clients.*')" class="pl-6">
                    {{ __('Clientes') }}
                </x-responsive-nav-link>
                @if($user->hasRole('company-admin'))
                <x-responsive-nav-link :href="route('company.operators.index')" :active="request()->routeIs('company.operators.*')" class="pl-6">
                    {{ __('Operadores') }}
                </x-responsive-nav-link>
                @endif

                <!-- Manifiestos Group - COMPLETO para Admin y Operadores -->
                @if($company)
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    📋 {{ __('Manifiestos') }}
                </div>
                <x-responsive-nav-link :href="route('company.manifests.index')" :active="request()->routeIs('company.manifests.index')" class="pl-6">
                    🏠 {{ __('Dashboard Manifiestos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.voyages.index')" :active="request()->routeIs('company.voyages.*')" class="pl-6">
                    🚢 {{ __('Viajes') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.shipments.index')" :active="request()->routeIs('company.shipments.*')" class="pl-6">
                    📦 {{ __('Cargas') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.bills-of-lading.index')" :active="request()->routeIs('company.bills-of-lading.*')" class="pl-6">
                    📄 {{ __('Conocimientos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.manifests.import.index')" :active="request()->routeIs('company.manifests.import.*')" class="pl-6">
                    📊 {{ __('Importar Excel/CSV') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.manifests.export.index')" :active="request()->routeIs('company.manifests.export.*')" class="pl-6">
                    📤 {{ __('Exportar Manifiestos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.manifests.customs.index')" :active="request()->routeIs('company.manifests.customs.*')" class="pl-6">
                    🏛️ {{ __('Enviar a AFIP/DNA') }}
                </x-responsive-nav-link>
                @endif

                <!-- Webservices Group - COMPLETO para Admin y Operadores -->
                @if($company)
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    ⚙️ {{ __('Webservices') }}
                </div>
                <x-responsive-nav-link :href="route('company.webservices.index')" :active="request()->routeIs('company.webservices.index')" class="pl-6">
                    {{ __('Dashboard Webservices') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.webservices.import')" :active="request()->routeIs('company.webservices.import')" class="pl-6">
                    📊 {{ __('Importar y Enviar') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('company.webservices.history')" :active="request()->routeIs('company.webservices.history')" class="pl-6">
                    {{ __('Historial') }}
                </x-responsive-nav-link>
                @if($user->hasRole('company-admin'))
                <x-responsive-nav-link :href="route('company.certificates.index')" :active="request()->routeIs('company.certificates.*')" class="pl-6">
                    🔐 {{ __('Certificados') }}
                </x-responsive-nav-link>
                @endif
                @endif

            @endif
        </div>

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
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-responsive-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                                   @click.prevent="$root.submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>