{{-- Sidebar para rutas company.* --}}
<div x-data="{ sidebarOpen: false }">
    {{-- Sidebar --}}
    <div class="fixed inset-y-0 left-0 z-50 mt-16 w-64 overflow-y-auto bg-white shadow-lg transform transition-transform duration-200 ease-in-out lg:translate-x-0"
         :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
         x-show="sidebarOpen || window.innerWidth >= 1024"
         @click.away="sidebarOpen = false">
        
        {{-- Sidebar Header --}}
        <div class="flex items-center justify-between p-9 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Panel Empresa</h2>
            <button @click="sidebarOpen = false" class="lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Sidebar Content --}}
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            @php
                $user = auth()->user();
                $company = $user->company ?? null;
            @endphp

            {{-- Dashboard --}}
            <a href="{{ route('company.dashboard') }}" 
               class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.dashboard') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                </svg>
                🏠 Dashboard
            </a>

            {{-- CREAR VIAJE COMPLETO --}}
            <div class="pt-4">
                <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    📋 Crear Viaje Completo
                </h3>
                <div class="mt-2 space-y-1">
                    {{-- Quick Start --}}
                    {{-- <a href="#" 
                       class="flex items-center px-3 py-2 text-sm font-medium text-orange-700 bg-orange-50 rounded-md hover:bg-orange-100">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        ⚡ Quick Start
                    </a> --}}

                    {{-- Planificar Viaje --}}
                    <a href="{{ route('company.voyages.create') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.voyages.create') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 9m0 8V9"/>
                        </svg>
                        🗓️ Planificar Viaje
                    </a>

                    {{-- Configurar Cargas --}}
                    <a href="{{ route('company.shipments.create') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.shipments.create') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                        </svg>
                        🚢 Configurar Cargas
                    </a>

                    {{-- Generar Conocimientos --}}
                    <a href="{{ route('company.bills-of-lading.create') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.bills-of-lading.create') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        📄 Generar Conocimientos
                    </a>
                    {{-- Envíos a Aduanas --}}
                    <a href="{{ route('company.simple.dashboard') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.bills-of-lading.create') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        🏛️ Enviar a AFIP/DNA
                    </a>
                </div>
            </div>

            {{-- GESTIÓN --}}
            <div class="pt-4" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center w-full px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-700">
                    <span>⚙️ Gestión</span>
                    <svg class="w-4 h-4 ml-auto transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="mt-2 space-y-1">
                    <a href="{{ route('company.vessel-owners.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.vessel-owners.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        👥 Propietarios
                    </a>
                    <a href="{{ route('company.captains.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.captains.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        👨‍✈️ Capitanes
                    </a>
                    <a href="{{ route('company.vessels.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.vessels.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        🚢 Embarcaciones
                    </a>
                    <a href="{{ route('company.clients.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.clients.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        👤 Clientes
                    </a>
                    @if($user && $user->hasRole('company-admin'))
                    <a href="{{ route('company.operators.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.operators.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        ⚙️ Operadores
                    </a>
                    @endif
                </div>
            </div>

            {{-- MANIFIESTOS --}}
            @if($company)
            <div class="pt-4" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center w-full px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-700">
                    <span>📊 Manifiestos</span>
                    <svg class="w-4 h-4 ml-auto transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="mt-2 space-y-1">
                    <a href="{{ route('company.manifests.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.manifests.index') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        🏠 Dashboard Manifiestos
                    </a>
                    <a href="{{ route('company.dashboard-estados.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.dashboard-estados.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📊 Dashboard Estados
                    </a>
                    <a href="{{ route('company.voyages.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.voyages.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        🚢 Viajes
                    </a>
                    <a href="{{ route('company.shipments.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.shipments.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📦 Cargas
                    </a>
                    <a href="{{ route('company.bills-of-lading.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.bills-of-lading.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📄 Conocimientos
                    </a>
                    <a href="{{ route('company.manifests.import.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.manifests.import.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📊 Importar Excel/CSV
                    </a>
                    <a href="{{ route('company.manifests.export.index') }}" 
                       class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.manifests.export.*') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📤 Exportar
                    </a>
                </div>
            </div>
            @endif

            {{-- REPORTES --}}
            @if($company)
            <div class="pt-4" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center w-full px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-700">
                    <span>📊 Reportes</span>
                    <svg class="w-4 h-4 ml-auto transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition class="mt-2 space-y-1">
                    {{-- Dashboard Reportes --}}
                    <a href="{{ route('company.reports.index') }}" 
                    class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.reports.index') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        🏠 Dashboard Reportes
                    </a>
                    
                    {{-- Manifiesto de Carga --}}
                    <a href="{{ route('company.reports.manifests') }}" 
                    class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.reports.manifests') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📄 Manifiesto de Carga
                    </a>
                    
                    {{-- Listado de Conocimientos --}}
                    <a href="{{ route('company.reports.bills-of-lading') }}" 
                    class="flex items-center px-6 py-2 text-sm font-medium rounded-md {{ request()->routeIs('company.reports.bills-of-lading') ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                        📋 Listado de Conocimientos
                    </a>
                    
                    {{-- Cartas de Aviso (próximamente) --}}
                    <a href="#" 
                    class="flex items-center px-6 py-2 text-sm font-medium rounded-md text-gray-400 cursor-not-allowed"
                    title="Próximamente">
                        📧 Cartas de Aviso
                        <span class="ml-auto text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Pronto</span>
                    </a>
                    
                    {{-- MIC/DTA (próximamente) --}}
                    <a href="#" 
                    class="flex items-center px-6 py-2 text-sm font-medium rounded-md text-gray-400 cursor-not-allowed"
                    title="Próximamente">
                        🛂 MIC/DTA
                        <span class="ml-auto text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Pronto</span>
                    </a>
                </div>
            </div>
            @endif
        </nav>
    </div>

    {{-- Overlay para mobile --}}
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 lg:hidden"
         @click="sidebarOpen = false">
    </div>

    {{-- Botón toggle sidebar en mobile (se incluirá en el layout) --}}
    <div class="lg:hidden bg-white border-b border-gray-200 px-4 py-2">
        <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>
</div>