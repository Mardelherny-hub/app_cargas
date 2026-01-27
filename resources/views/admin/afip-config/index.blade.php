<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Configuración AFIP
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Descripción y Guía --}}
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">¿Qué es esta configuración?</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p class="mb-2">Los webservices de AFIP requieren códigos específicos (<code class="bg-blue-100 px-1 rounded">codAdu</code> y <code class="bg-blue-100 px-1 rounded">codLugOper</code>) para identificar aduanas y lugares operativos. Estos códigos son <strong>diferentes</strong> a los códigos internacionales UN/LOCODE.</p>
                            
                            <details class="mt-3">
                                <summary class="cursor-pointer font-medium text-blue-800 hover:text-blue-900">📖 Ver explicación de cada sección</summary>
                                <div class="mt-3 space-y-3 pl-4 border-l-2 border-blue-200">
                                    <div>
                                        <strong>🏛️ Aduanas AFIP</strong>
                                        <p class="text-xs mt-1">Catálogo de las 70 aduanas argentinas con su código AFIP (3 dígitos). Ej: <code class="bg-blue-100 px-1 rounded">001</code> = Aduana de Buenos Aires.</p>
                                    </div>
                                    <div>
                                        <strong>📍 Lugares Operativos</strong>
                                        <p class="text-xs mt-1">Puntos específicos dentro de cada aduana donde se realizan operaciones. Ej: <code class="bg-blue-100 px-1 rounded">10073</code> = Terminal Sur en Buenos Aires. Incluye lugares nacionales y extranjeros (Paraguay, Brasil, etc.).</p>
                                    </div>
                                    <div>
                                        <strong>🔗 Puertos ↔ Aduanas</strong>
                                        <p class="text-xs mt-1">Vincula los puertos del sistema (UN/LOCODE) con sus aduanas AFIP correspondientes. Un puerto puede tener varias aduanas, pero una debe ser la <span class="bg-green-100 px-1 rounded">predeterminada</span>.</p>
                                    </div>
                                </div>
                            </details>
                            
                            <p class="mt-3 text-xs text-blue-600">
                                <strong>Ejemplo XML:</strong> Buenos Aires → <code class="bg-blue-100 px-1 rounded">&lt;codAdu&gt;001&lt;/codAdu&gt; &lt;codLugOper&gt;10073&lt;/codLugOper&gt;</code>
                            </p>
                        </div>
                    </div>
                </div>
            </div><p class="mb-4 text-gray-600">Gestión de aduanas, lugares operativos y vínculos con puertos</p>

            {{-- Alertas --}}
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Tabs Navigation --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <a href="{{ route('admin.afip-config.index', ['tab' => 'customs-offices']) }}"
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'customs-offices' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            🏛️ Aduanas AFIP
                            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $tab === 'customs-offices' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' }}">
                                {{ $customsOffices->count() }}
                            </span>
                        </a>
                        <a href="{{ route('admin.afip-config.index', ['tab' => 'locations']) }}"
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'locations' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            📍 Lugares Operativos
                            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $tab === 'locations' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' }}">
                                {{ $locations->total() }}
                            </span>
                        </a>
                        <a href="{{ route('admin.afip-config.index', ['tab' => 'port-customs']) }}"
                           class="py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'port-customs' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            🔗 Puertos ↔ Aduanas
                            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $tab === 'port-customs' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' }}">
                                {{ $portCustoms->count() }}
                            </span>
                        </a>
                    </nav>
                </div>

                {{-- Tab Content --}}
                <div class="p-6">
                    @if($tab === 'customs-offices')
                        @include('admin.afip-config.partials.customs-offices')
                    @elseif($tab === 'locations')
                        @include('admin.afip-config.partials.locations')
                    @elseif($tab === 'port-customs')
                        @include('admin.afip-config.partials.port-customs')
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>